<?php

namespace App\Services\Agent;

use App\Models\AgentConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Signed HTTPS calls to the central update server.
 * Signature: hex HMAC-SHA256(secret, METHOD \n path \n timestamp \n nonce \n sha256(body)).
 */
class CentralClient
{
    public function __construct(protected AgentConnection $connection) {}

    /** Unsigned call used once, to register with a connection code. */
    public static function register(string $serverUrl, array $payload): array
    {
        $serverUrl = self::checkUrl($serverUrl);
        try {
            $response = Http::acceptJson()->asJson()->timeout(config('agent.timeout'))->withUserAgent('catalog-update-agent')
                ->post($serverUrl.'/api/v1/register', $payload);
        } catch (ConnectionException $e) {
            throw new AgentException('Could not reach the update server at '.$serverUrl.'. Check the address.', 'network', true);
        }

        return self::decode($response);
    }

    /** Server address must be https:// (http only when explicitly allowed for local testing). */
    public static function checkUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['https', 'http'], true)) {
            throw new AgentException('Enter the update server address, e.g. https://updates.example.com', 'invalid_url');
        }
        if ($scheme !== 'https' && ! config('agent.allow_insecure')) {
            throw new AgentException('The update server address must start with https://', 'insecure_url');
        }

        return $url;
    }

    public function get(string $path): array
    {
        return self::decode($this->send('GET', $path));
    }

    public function post(string $path, array $body = []): array
    {
        return self::decode($this->send('POST', $path, $body));
    }

    /** POST raw bytes (a backup chunk), signed over exactly those bytes. */
    public function upload(string $path, string $bytes, array $headers = []): array
    {
        return self::decode($this->send('POST', $path, null, null, 1, $headers, $bytes));
    }

    /**
     * Download a file to $sink. An interrupted download resumes where it stopped
     * (HTTP Range), so a flaky connection or a host that drops long transfers
     * doesn't force starting over. The caller verifies the checksum afterwards.
     */
    public function download(string $path, string $sink, ?int $expectedSize = null, int $maxAttempts = 6): array
    {
        @unlink($sink);
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            clearstatcache(true, $sink);
            $have = is_file($sink) ? filesize($sink) : 0;
            if ($expectedSize && $have >= $expectedSize) {
                return []; // everything arrived just before the connection dropped
            }
            $handle = fopen($sink, $have ? 'ab' : 'wb');
            try {
                $response = $this->send('GET', $path, null, $handle, 2, $have ? ['Range' => "bytes={$have}-"] : []);
            } catch (AgentException $e) {
                fclose($handle);
                if ($e->errorCode === 'network' && $attempt < $maxAttempts) {
                    usleep(500_000 * $attempt);

                    continue; // resume from what arrived
                }
                @unlink($sink);
                throw $e;
            }
            fclose($handle);

            if (! $response->successful()) {
                @unlink($sink);
                self::decode($response);
            }
            if ($have > 0 && $response->status() !== 206) {
                // The server ignored the range and sent everything again after what we had: start over cleanly.
                @unlink($sink);

                continue;
            }

            // A complete response: done. (Whether the file is right is decided by the signed checksum.)
            return $response->headers();
        }
        @unlink($sink);
        throw new AgentException('The download kept being interrupted. It will be tried again later.', 'network', true);
    }

    /** @param  resource|string|null  $sink */
    protected function send(string $method, string $path, ?array $body = null, $sink = null, int $attempt = 1, array $headers = [], ?string $raw = null): Response
    {
        $path = ltrim($path, '/');
        $json = $raw ?? ($body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES));
        $timestamp = (string) time();
        $nonce = Str::random(32);
        $signature = hash_hmac('sha256', implode("\n", [$method, $path, $timestamp, $nonce, hash('sha256', $json)]), $this->connection->secret);

        $request = Http::withHeaders([
            'X-Installation-Id' => $this->connection->installation_id,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
            // Never reuse a kept-alive connection: the agent can sit idle for a while (e.g. while
            // checking thousands of files) and the server may have closed it in the meantime.
            'Connection' => 'close',
            'Accept-Encoding' => $sink ? 'identity' : 'gzip',
            ...$headers,
        ])->withUserAgent('catalog-update-agent')->timeout($sink ? config('agent.download_timeout') : config('agent.timeout'))->connectTimeout(10);

        if ($sink) {
            $request = $request->withOptions(['sink' => $sink]);
        }

        $url = self::checkUrl($this->connection->server_url).'/'.$path;
        try {
            return $method === 'GET'
                ? $request->get($url)
                : $request->withBody($json, $raw !== null ? 'application/octet-stream' : 'application/json')->send($method, $url);
        } catch (ConnectionException $e) {
            // Reads are safe to repeat (each time with a fresh signature and nonce). Downloads resume instead.
            if ($method === 'GET' && $sink === null && $attempt < 3) {
                usleep(500_000 * $attempt);

                return $this->send($method, $path, $body, null, $attempt + 1, $headers);
            }
            throw new AgentException('The update server could not be reached ('.Str::limit($e->getMessage(), 120).').', 'network', true);
        }
    }

    protected static function decode(Response $response): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }
        $code = (string) ($response->json('error') ?? 'http_'.$response->status());
        $message = (string) ($response->json('message') ?? 'The update server returned error '.$response->status().'.');
        if ($response->status() === 422 && $response->json('errors')) {
            $message = collect($response->json('errors'))->flatten()->implode(' ');
        }

        // 5xx, rate limits and clock problems may pass; authentication and validation errors won't.
        $retryable = $response->serverError() || in_array($response->status(), [408, 429], true) || $code === 'clock_skew';

        throw new AgentException($message, $code, $retryable, $response->status());
    }
}

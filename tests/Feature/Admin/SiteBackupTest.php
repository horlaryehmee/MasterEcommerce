<?php

namespace Tests\Feature\Admin;

use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Services\Agent\Agent;
use App\Services\Agent\SiteBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/** Backups requested by the central server: built here, uploaded in resumable chunks. */
class SiteBackupTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmp;

    /** What the fake central server has received so far. */
    protected string $received = '';

    protected array $complete = [];

    protected array $heartbeats = [];

    protected int $failChunksLeft = 0;

    protected bool $cancelled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/site-backup-'.Str::random(8);
        config(['agent.state_path' => $this->tmp.'/agent', 'agent.allow_backups' => true]);
        Storage::fake('public');
        Storage::disk('public')->put('products/kettle.webp', 'IMAGEBYTES');
        Storage::disk('public')->put('branding/logo.webp', 'LOGO');

        AgentConnection::create([
            'server_url' => 'https://central.test', 'installation_id' => (string) Str::uuid(), 'client_key' => 'client-acme',
            'secret' => 'shared-secret', 'signing_key_id' => 'key1', 'signing_public_key' => base64_encode(random_bytes(32)), 'signing_fingerprint' => 'AAAA', 'check_interval' => 15,
        ]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** A central server that wants backup #5 and reassembles the chunks exactly like the real one. */
    protected function fakeCentral(bool $includeFiles = true): void
    {
        Http::swap(new HttpFactory);
        Http::fake(function (Request $r) use ($includeFiles) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/heartbeat')) {
                $this->heartbeats[] = json_decode($r->body(), true);

                return Http::response(['status' => 'active', 'check_interval' => 15, 'instructions' => $this->complete || $this->cancelled ? [] : [['type' => 'backup', 'target' => 5, 'include_files' => $includeFiles]]]);
            }
            if (str_ends_with($path, '/backups/5/accept')) {
                return $this->cancelled ? Http::response(['error' => 'not_found'], 404) : Http::response(['target' => 5, 'include_files' => $includeFiles, 'chunk_size' => 65536, 'max_size' => 100000000, 'received' => strlen($this->received)]);
            }
            if (str_ends_with($path, '/backups/5/chunk')) {
                // The signature covers the raw bytes.
                $expected = hash_hmac('sha256', implode("\n", ['POST', 'api/v1/backups/5/chunk', $r->header('X-Timestamp')[0], $r->header('X-Nonce')[0], hash('sha256', $r->body())]), 'shared-secret');
                $this->assertSame($expected, $r->header('X-Signature')[0]);
                if ($this->failChunksLeft-- > 0) {
                    throw new \Illuminate\Http\Client\ConnectionException('Connection reset');
                }
                $offset = (int) $r->header('X-Offset')[0];
                $this->received = substr($this->received, 0, $offset).$r->body();

                return Http::response(['received' => strlen($this->received)]);
            }
            if (str_ends_with($path, '/backups/5/complete')) {
                $this->complete = json_decode($r->body(), true);
                $ok = ($this->complete['result'] ?? '') === 'completed' && hash('sha256', $this->received) === $this->complete['sha256'];

                return Http::response(['ok' => $ok, 'status' => $ok ? 'completed' : 'failed']);
            }

            return Http::response(['ok' => true]);
        });
    }

    protected function zipEntries(string $bytes): array
    {
        $file = $this->tmp.'/check.zip';
        file_put_contents($file, $bytes);
        $zip = new ZipArchive;
        $zip->open($file);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $names;
    }

    public function test_a_requested_backup_is_built_uploaded_and_reported(): void
    {
        $this->fakeCentral();
        app(Agent::class)->sync(force: true);

        $this->assertSame(['backup'], $this->heartbeats[0]['capabilities']);
        $this->assertSame('completed', $this->complete['result']);
        $this->assertSame(strlen($this->received), $this->complete['size']);
        $this->assertSame(2, $this->complete['contents']['files']);

        $entries = $this->zipEntries($this->received);
        $this->assertArrayHasKey('database.sql.gz', $entries);
        $this->assertNotFalse(gzdecode($entries['database.sql.gz'])); // (the test database is in memory, so the dump is a placeholder)
        $this->assertSame('IMAGEBYTES', $entries['files/public/products/kettle.webp']);
        $this->assertArrayHasKey('backup.json', $entries);
        $this->assertArrayNotHasKey('.env', $entries);

        // Cleaned up locally and logged for the owner.
        $this->assertSame([], app(SiteBackup::class)->state());
        $this->assertSame([], glob($this->tmp.'/agent/backups/backup-*') ?: []);
        $this->assertTrue(AgentLog::query()->where('message', 'like', 'Backup sent to the update server%')->exists());
    }

    public function test_an_interrupted_upload_continues_on_the_next_run(): void
    {
        // Big enough for several chunks (random bytes don't compress).
        Storage::disk('public')->put('banners/big.bin', random_bytes(300000));
        $this->fakeCentral();
        $this->failChunksLeft = 1; // the connection drops on the first chunk

        app(Agent::class)->sync(force: true);
        $this->assertSame([], $this->complete);
        $this->assertNotSame([], app(SiteBackup::class)->state(), 'Kept to continue later.');

        app(Agent::class)->sync();
        $this->assertSame('completed', $this->complete['result']);
        $this->assertSame(hash('sha256', $this->received), $this->complete['sha256']);
    }

    public function test_database_only_and_switched_off_and_cancelled_backups(): void
    {
        $this->fakeCentral(includeFiles: false);
        app(Agent::class)->sync(force: true);
        $this->assertSame(0, $this->complete['contents']['files']);
        $this->assertArrayNotHasKey('files/public/products/kettle.webp', $this->zipEntries($this->received));

        // Switched off by the owner: the server isn't told the site can do backups, and a request is refused.
        config(['agent.allow_backups' => false]);
        $this->complete = [];
        $this->received = '';
        app(Agent::class)->sync(force: true);
        $this->assertSame([], end($this->heartbeats)['capabilities']);
        $this->assertSame('failed', $this->complete['result']);

        // Cancelled on the server: nothing is sent and nothing is left behind.
        config(['agent.allow_backups' => true]);
        $this->complete = [];
        $this->cancelled = true;
        app(Agent::class)->sync(force: true);
        $this->assertSame([], app(SiteBackup::class)->state());
    }
}

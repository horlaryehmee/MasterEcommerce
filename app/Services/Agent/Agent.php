<?php

namespace App\Services\Agent;

use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Models\Order;
use App\Services\Updater\Updater;
use App\Support\Modules\ModuleRegistry;
use App\Support\VersionConstraint as V;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The update agent. Runs from the scheduler (cron → schedule:run → agent:sync)
 * and from the Updates page. Everything is outgoing HTTPS to the central
 * update server; the shop itself never depends on that server.
 *
 * A deploy instruction is carried out as:
 *   accept → verify signature, client and compatibility → check for hand-edited
 *   core files → wait while customers are checking out → download + checksum →
 *   database backup → unpack + verify every file → swap in (maintenance mode)
 *   → finish in a fresh PHP process (migrations, caches, back online) → report.
 * The result is only reported once it is known; reports that can't be sent are
 * queued in an outbox and sent on the next run.
 */
class Agent
{
    protected string $dir;

    public function __construct(protected Updater $updater, protected ModuleRegistry $modules)
    {
        $this->dir = rtrim(config('agent.state_path') ?: storage_path('app/agent'), '/\\');
    }

    public function connection(): ?AgentConnection
    {
        return AgentConnection::current();
    }

    /* ------------------------------------------------------------------ */
    /* Connecting                                                          */
    /* ------------------------------------------------------------------ */

    public function connect(string $serverUrl, string $code): AgentConnection
    {
        $serverUrl = CentralClient::checkUrl($serverUrl);
        $data = CentralClient::register($serverUrl, [
            'code' => trim($code),
            'site_url' => config('app.url'),
            'version' => $this->updater->currentVersion(),
        ]);

        foreach (['installation_id', 'secret', 'client_key'] as $key) {
            if (empty($data[$key])) {
                throw new AgentException('The update server sent an incomplete answer.', 'bad_response');
            }
        }
        $public = base64_decode((string) ($data['signing']['public_key'] ?? ''), true);
        if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new AgentException('The update server sent an invalid signing key.', 'bad_response');
        }

        AgentConnection::query()->delete();
        $connection = AgentConnection::create([
            'server_url' => $serverUrl,
            'installation_id' => $data['installation_id'],
            'client_key' => $data['client_key'],
            'secret' => $data['secret'],
            'signing_key_id' => $data['signing']['key_id'],
            'signing_public_key' => $data['signing']['public_key'],
            'signing_fingerprint' => $data['signing']['fingerprint'] ?? null,
            'check_interval' => max(5, (int) ($data['check_interval'] ?? 15)),
            'connected_at' => now(),
            'last_contact_at' => now(),
        ]);
        AgentLog::write('success', "Connected to the update server {$serverUrl} (key fingerprint {$connection->signing_fingerprint}).");

        try {
            $this->heartbeat($connection);
        } catch (Throwable) {
            // Reported on the next run.
        }

        return $connection;
    }

    public function disconnect(): void
    {
        AgentConnection::query()->delete();
        @unlink($this->dir.'/outbox.json');
        AgentLog::write('warning', 'Disconnected from the update server.');
    }

    /* ------------------------------------------------------------------ */
    /* Main loop                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  bool  $force  contact the server now, even if the check interval hasn't passed
     * @param  bool  $work  carry out an instruction if there is one
     * @param  bool  $manual  started by an administrator (skips the checkout-quiet wait)
     * @return array{status: string, message: string}
     */
    public function sync(bool $force = false, bool $work = true, bool $manual = false): array
    {
        $connection = $this->connection();
        if (! $connection) {
            return ['status' => 'not_connected', 'message' => 'Not connected to an update server.'];
        }

        $lock = Cache::lock('update-agent', 3600);
        if (! $lock->get()) {
            return ['status' => 'busy', 'message' => 'An update is already running.'];
        }

        try {
            $this->flushOutbox($connection);

            // Work left from an earlier run.
            if ($job = $this->job()) {
                if (($job['step'] ?? null) === 'install' && ($this->updater->pending()['step'] ?? null) === 'install') {
                    return $this->finishPending();
                }
                if (($job['step'] ?? null) !== 'waiting') {
                    $this->abandon($connection, $job, 'The previous update attempt was interrupted before it could finish (nothing was changed).');
                }
            }

            $due = $force || ! $connection->last_attempt_at || $connection->last_attempt_at->lte(now()->subMinutes($connection->check_interval))
                || $this->job() !== null;
            if (! $due) {
                return ['status' => 'idle', 'message' => 'Not due yet.'];
            }

            try {
                $response = $this->heartbeat($connection);
            } catch (AgentException $e) {
                // Server unreachable or refusing: logged on the connection; the shop is unaffected. Try again next run.
                return ['status' => 'offline', 'message' => $e->getMessage()];
            }
            $instruction = $response['instructions'][0] ?? null;
            if (! $work || ! $instruction) {
                return ['status' => 'ok', 'message' => $instruction ? 'An update is waiting.' : 'No instructions.'];
            }

            return $instruction['type'] === 'rollback'
                ? $this->rollback($connection, (int) $instruction['target'])
                : $this->deploy($connection, (int) $instruction['target'], $manual);
        } finally {
            $lock->release();
        }
    }

    public function heartbeat(?AgentConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $connection->forceFill(['last_attempt_at' => now()])->save();
        $this->modules->flush();

        try {
            $response = $this->client($connection)->post('api/v1/heartbeat', [
                'version' => $this->updater->currentVersion(),
                'php' => PHP_VERSION,
                'framework' => app()->version(),
                'server' => $this->serverInfo(),
                'modules' => $this->modules->report(),
            ]);
        } catch (AgentException $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();
            if (in_array($e->status, [401, 403], true)) {
                AgentLog::write('error', 'The update server refused this website: '.$e->getMessage());
            }
            throw $e;
        }

        $connection->forceFill([
            'last_contact_at' => now(),
            'last_error' => null,
            'status' => $response['status'] ?? 'active',
            'check_interval' => max(5, (int) ($response['check_interval'] ?? $connection->check_interval)),
            'pending' => $response['instructions'] ?? [],
        ])->save();

        return $response;
    }

    /* ------------------------------------------------------------------ */
    /* Deploy                                                              */
    /* ------------------------------------------------------------------ */

    protected function deploy(AgentConnection $connection, int $target, bool $manual): array
    {
        $client = $this->client($connection);
        $log = [];
        $note = function (string $message, string $level = 'info') use (&$log, $target) {
            $log[] = now()->format('H:i:s').' '.$message;
            AgentLog::write($level, $message, $target);
        };

        try {
            $payload = $client->post("api/v1/deployments/{$target}/accept");
            $manifest = $this->verifyManifest($connection, (string) $payload['manifest'], (string) $payload['signature'], $payload['key_id'] ?? null);
            $label = ($manifest['type'] === 'core' ? 'core' : $manifest['slug']).' '.$manifest['version'];
            if (($manifest['release'] ?? null) !== ($payload['release']['uuid'] ?? null)) {
                throw new AgentException('The update server sent a manifest for a different release.', 'manifest_mismatch');
            }
            $job = $this->job();
            if (! $job || ($job['target'] ?? null) !== $target) {
                $note("Received update {$label}; signature verified.");
            }

            $this->checkCompatibility($connection, $manifest);

            if ($manifest['type'] === 'core' && empty($payload['options']['allow_modified_files'])) {
                $changed = $this->modifiedCoreFiles($connection);
                if ($changed === null) {
                    $note('No signed file list for the installed version, so hand-edited core files cannot be detected for this update.', 'warning');
                } elseif ($changed) {
                    throw new AgentException(
                        'Core files on this website were changed by hand, so the update was not installed (it would overwrite them). Move these customisations into an extension, or deploy again with "overwrite modified files". Changed: '.implode(', ', array_slice($changed, 0, 15)).(count($changed) > 15 ? ' and '.(count($changed) - 15).' more' : ''),
                        'modified_files'
                    );
                }
            }

            // Don't interrupt customers who are checking out right now.
            if (! $manual && $this->checkoutBusy()) {
                $since = $job['deferred_since'] ?? now()->toIso8601String();
                if (now()->diffInMinutes($since, true) < config('agent.max_defer_minutes')) {
                    $this->saveJob(['target' => $target, 'action' => 'deploy', 'step' => 'waiting', 'deferred_since' => $since]);
                    $this->progress($client, $target, 'waiting', 'Waiting for checkout activity to finish before installing.');

                    return ['status' => 'waiting', 'message' => 'Waiting for checkout activity to finish.'];
                }
                $note('Customers are still checking out, but the update has waited long enough; installing now.', 'warning');
            }

            $this->saveJob([
                'target' => $target, 'action' => 'deploy', 'step' => 'download', 'manifest' => $manifest, 'label' => $label,
                'signed' => ['manifest' => $payload['manifest'], 'signature' => $payload['signature'], 'key_id' => $payload['key_id'] ?? null],
                'started_at' => now()->toIso8601String(),
            ]);
            $this->assertServerReady($manifest['package']['size'] ?? null);

            // Download and verify the package.
            $this->progress($client, $target, 'download', "Downloading {$label}.");
            $this->updater->resetWork();
            $zip = $this->updater->workPath().'/package.zip';
            $client->download($payload['download']['path'], $zip, (int) ($manifest['package']['size'] ?? 0) ?: null);
            $hash = hash_file('sha256', $zip);
            if (! hash_equals((string) $manifest['package']['sha256'], $hash)) {
                @unlink($zip);
                throw new AgentException('The downloaded package does not match its signed checksum.', 'checksum', true);
            }
            $size = filesize($zip);
            $note('Downloaded '.($size >= 1048576 ? round($size / 1048576, 1).' MB' : max(1, round($size / 1024)).' KB').'; checksum verified.');

            $verify = $this->updater->workPath().'/verify.json';
            file_put_contents($verify, json_encode(['root' => $manifest['package']['root'] ?? '', 'files' => $manifest['files']]));
            $this->updater->stage($zip, [
                'from' => $manifest['type'] === 'core' ? $this->updater->currentVersion() : ($this->modules->version($manifest['slug']) ?? '0.0.0'),
                'to' => $manifest['version'],
                'kind' => $manifest['type'],
                'slug' => $manifest['slug'],
                'verify' => $verify,
            ]);

            $this->progress($client, $target, 'backup', 'Backing up the database.');
            $note($this->updater->backup()['message']);
            $this->progress($client, $target, 'extract', 'Unpacking and verifying every file.');
            $note($this->updater->extract()['message']);
            $this->progress($client, $target, 'install', 'Installing (website briefly in maintenance mode).');
            $note($this->updater->install()['message']);
            $this->saveJob([...$this->job(), 'step' => 'install', 'log' => $log]);
        } catch (Throwable $e) {
            $error = $e instanceof AgentException ? $e : new AgentException($e->getMessage(), 'install_error', false);
            if (($error->errorCode === 'not_deliverable' || $error->errorCode === 'release_withdrawn') && $error->status === 409) {
                $this->clearJob();

                return ['status' => 'skipped', 'message' => $error->getMessage()];
            }
            $note('Update failed: '.$error->getMessage(), 'error');
            try {
                $this->updater->cancel();
            } catch (Throwable) {
            }
            $this->clearJob();
            $this->report($connection, $target, ['result' => 'failed', 'error' => $error->getMessage(), 'error_code' => $error->errorCode, 'retryable' => $error->retryable, 'log' => $log]);

            return ['status' => 'failed', 'message' => $error->getMessage()];
        }

        // Migrations and cache clearing must run on the NEW code: a fresh PHP process.
        if (app()->runningInConsole() && config('agent.spawn_finish') && $this->canSpawn()) {
            $result = Process::path($this->updater->basePath())->timeout(1800)->run([PHP_BINARY, 'artisan', 'agent:finish']);
            if ($result->successful() || ! $this->job()) {
                return ['status' => 'done', 'message' => trim($result->output()) ?: 'Finished.'];
            }
            AgentLog::write('warning', 'Could not finish in a separate process; finishing on the next scheduled run. '.trim($result->errorOutput()), $target);
        }

        return ['status' => 'needs_finish', 'message' => 'Installed; finishing on the next run.'];
    }

    /** Last step, run in a fresh process (agent:finish, the next cron run, or the Updates page). */
    public function finishPending(): array
    {
        $connection = $this->connection();
        $job = $this->job();
        if (! $connection || ! $job || ($job['step'] ?? null) !== 'install') {
            return ['status' => 'idle', 'message' => 'Nothing to finish.'];
        }
        $target = (int) $job['target'];
        $log = $job['log'] ?? [];
        $manifest = $job['manifest'];

        try {
            $this->progress($this->client($connection), $target, 'finish', 'Running database migrations and clearing caches.');
            $result = $this->updater->finish();
            $log[] = now()->format('H:i:s').' '.$result['message'];
            foreach ($result['details'] ?? [] as $line) {
                $log[] = $line;
            }

            $this->remember($target, ['backup' => $result['backup'], 'batch' => $result['migration_batch'], 'type' => $manifest['type'], 'slug' => $manifest['slug'], 'version' => $manifest['version']]);
            $this->storeInstalledManifest($manifest, $job);
            $this->clearJob();
            AgentLog::write('success', $result['message'], $target);
            $this->report($connection, $target, ['result' => 'succeeded', 'version' => $manifest['version'], 'log' => $log]);
            $this->modules->flush();
            try {
                $this->heartbeat($connection);
            } catch (Throwable) {
            }

            return ['status' => 'done', 'message' => $result['message']];
        } catch (Throwable $e) {
            // The new files are in place but finishing failed (usually a migration): put the old files back.
            $message = 'Finishing the update failed: '.$e->getMessage();
            $backup = $this->updater->pending()['backup'] ?? ($this->updater->backups()[0]['id'] ?? null);
            try {
                if ($backup) {
                    $this->updater->restore($backup);
                    $message .= ' The previous files were restored automatically. Database changes made before the error may remain; a database backup was taken before the update.';
                }
            } catch (Throwable $restoreError) {
                $message .= ' Restoring the previous files also failed: '.$restoreError->getMessage().'. Restore it from Admin → Updates → Previous versions.';
            }
            $this->updater->bringOnline();
            $this->updater->forgetState();
            $this->clearJob();
            AgentLog::write('error', $message, $target);
            $this->report($connection, $target, ['result' => 'failed', 'error' => $message, 'error_code' => 'finish_failed', 'retryable' => false, 'log' => [...$log, $message]]);

            return ['status' => 'failed', 'message' => $message];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Rollback                                                            */
    /* ------------------------------------------------------------------ */

    protected function rollback(AgentConnection $connection, int $target): array
    {
        $client = $this->client($connection);
        try {
            $payload = $client->post("api/v1/deployments/{$target}/accept");
            $this->verifyManifest($connection, (string) $payload['manifest'], (string) $payload['signature'], $payload['key_id'] ?? null);
            $history = $this->history()[$target] ?? null;
            if (! $history || ! collect($this->updater->backups())->contains('id', $history['backup'])) {
                throw new AgentException('No backup of the previous version is kept on this website any more, so it cannot be rolled back automatically.', 'no_backup');
            }
            AgentLog::write('warning', "Rolling back {$history['slug']} {$history['version']}.", $target);
            $this->progress($client, $target, 'rollback', 'Rolling back.');

            $notes = [];
            if (! empty($payload['rollback_database']) && $history['batch']) {
                \Illuminate\Support\Facades\Artisan::call('down', ['--retry' => 30]);
                $output = (string) $this->updater->rollbackDatabase($history['backup']);
                preg_match_all('/(\d{4}_\d{2}_\d{2}_\d{6}_\w+)\s.*DONE/', $output, $m);
                $notes[] = $m[1] ? 'Undid database changes: '.implode(', ', $m[1]).'.' : 'No database changes to undo.';
            } elseif ($history['batch']) {
                $notes[] = 'Database changes were kept (this release does not support undoing them).';
            }
            $notes[] = $this->updater->restore($history['backup'], mentionDatabase: false)['message'];
            $this->forget($target);
            @unlink($this->installedManifestPath($history['type'], $history['slug']));
            AgentLog::write('success', implode(' ', $notes), $target);
            $this->report($connection, $target, ['result' => 'rolled_back', 'log' => $notes]);
            $this->modules->flush();

            return ['status' => 'rolled_back', 'message' => implode(' ', $notes)];
        } catch (Throwable $e) {
            $this->updater->bringOnline();
            AgentLog::write('error', 'Rollback failed: '.$e->getMessage(), $target);
            $this->report($connection, $target, ['result' => 'rollback_failed', 'error' => $e->getMessage(), 'error_code' => $e instanceof AgentException ? $e->errorCode : 'rollback_error']);

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Checks                                                              */
    /* ------------------------------------------------------------------ */

    /** Verify the Ed25519 signature with the key received when connecting; returns the decoded manifest. */
    public function verifyManifest(AgentConnection $connection, string $json, string $signature, ?string $keyId): array
    {
        $sig = base64_decode($signature, true);
        $key = base64_decode($connection->signing_public_key, true);
        if ($keyId !== null && $keyId !== $connection->signing_key_id) {
            throw new AgentException('The update was signed with a key this website does not trust.', 'untrusted_key');
        }
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($sig, $json, $key)) {
            throw new AgentException('The update\'s signature is invalid. It was rejected.', 'bad_signature');
        }
        $manifest = json_decode($json, true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1 || ! isset($manifest['type'], $manifest['slug'], $manifest['version'], $manifest['package']['sha256'], $manifest['files'])) {
            throw new AgentException('The update manifest is not in a format this website understands.', 'bad_manifest');
        }

        return $manifest;
    }

    /** The same rules the update server applies, checked again against this site's real state. */
    public function checkCompatibility(AgentConnection $connection, array $manifest): void
    {
        $fail = fn (string $why) => throw new AgentException($why, 'incompatible');
        $core = $this->updater->currentVersion();
        $modules = $this->modules->all();
        $requires = (array) ($manifest['requires'] ?? []);
        $from = (array) ($manifest['upgrade_from'] ?? []);

        // Client-specific code is bound to one client.
        if (! empty($manifest['client']) && $manifest['client'] !== $connection->client_key) {
            $fail('This extension belongs to another client and was refused.');
        }
        if ($manifest['type'] === 'extension' && empty($manifest['client'])) {
            $fail('A client extension must be bound to a client.');
        }

        if ($manifest['type'] === 'core') {
            V::compare($manifest['version'], $core) > 0 || $fail("This website already runs core {$core}.");
            foreach ($modules as $m) {
                if (! empty($m['requires']['core']) && ! V::satisfies($manifest['version'], $m['requires']['core'])) {
                    $fail("{$m['slug']} {$m['version']} needs core {$m['requires']['core']}; update it first.");
                }
            }
            $current = $core;
        } else {
            $installed = $modules[$manifest['slug']] ?? null;
            if ($installed && $installed['type'] !== $manifest['type']) {
                $fail("{$manifest['slug']} is installed as a {$installed['type']}, not a {$manifest['type']}.");
            }
            if ($installed && V::compare($manifest['version'], $installed['version']) <= 0) {
                $fail("{$manifest['slug']} is already on {$installed['version']}.");
            }
            $current = $installed['version'] ?? null;
            if (! empty($requires['core']) && ! V::satisfies($core, $requires['core'])) {
                $fail("Needs core {$requires['core']} (this website has {$core}).");
            }
        }
        if ($current && ! empty($from['min']) && V::compare($current, $from['min']) < 0) {
            $fail("Needs {$from['min']} or newer first (this website has {$current}).");
        }
        if ($current && ! empty($from['max']) && V::compare($current, $from['max']) > 0) {
            $fail("Only upgrades from {$from['max']} or older (this website has {$current}).");
        }
        if (! empty($requires['php']) && ! V::satisfies(PHP_VERSION, $requires['php'])) {
            $fail("Needs PHP {$requires['php']} (this website has ".PHP_VERSION.').');
        }
        foreach ((array) ($requires['modules'] ?? []) as $slug => $constraint) {
            if (! isset($modules[$slug]) || ! V::satisfies($modules[$slug]['version'], $constraint)) {
                $fail("Needs module {$slug} {$constraint}.");
            }
        }
    }

    /**
     * Core files that differ from the signed file list of the installed version.
     * Returns null when no signed list is available to compare with.
     */
    public function modifiedCoreFiles(AgentConnection $connection): ?array
    {
        $baseline = null;
        $local = $this->installedManifestPath('core', 'core');
        try {
            $stored = is_file($local) ? json_decode((string) file_get_contents($local), true) : null;
            $source = $stored ?: $this->client($connection)->get('api/v1/manifests/core/core/'.$this->updater->currentVersion());
            $baseline = $this->verifyManifest($connection, (string) $source['manifest'], (string) $source['signature'], $source['key_id'] ?? null);
        } catch (AgentException $e) {
            if ($e->status !== 404 && ! in_array($e->errorCode, ['bad_signature', 'untrusted_key'], true)) {
                throw $e;
            }

            return null;
        }
        if (V::compare($baseline['version'], $this->updater->currentVersion()) !== 0) {
            return null;
        }

        // Generated or client-owned paths are never compared.
        $skip = ['public/', 'storage/', 'bootstrap/cache/', 'modules/', 'extensions/', '.env'];
        $changed = [];
        foreach ((array) $baseline['files'] as $path => $hash) {
            foreach ($skip as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }
            $file = $this->updater->basePath().'/'.$path;
            if (! is_file($file)) {
                $changed[] = "{$path} (missing)";
            } elseif (! hash_equals($hash, hash_file('sha256', $file))) {
                $changed[] = $path;
            }
        }

        return $changed;
    }

    protected function checkoutBusy(): bool
    {
        try {
            return Order::query()->where('created_at', '>=', now()->subMinutes(config('agent.quiet_minutes')))->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function assertServerReady(?int $size): void
    {
        foreach ($this->updater->preflight($size) as $check) {
            if (! $check['ok']) {
                throw new AgentException("{$check['label']}: {$check['detail']}", 'server_not_ready', false);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Reporting                                                           */
    /* ------------------------------------------------------------------ */

    protected function progress(CentralClient $client, int $target, string $step, string $message): void
    {
        AgentLog::write('info', $message, $target);
        try {
            $client->post("api/v1/deployments/{$target}/progress", ['step' => $step, 'message' => $message]);
        } catch (Throwable) {
            // Progress is informational; the final report is what counts (and is queued if needed).
        }
    }

    /** Send a final result; if the server can't be reached, keep it and send it on the next run. */
    protected function report(AgentConnection $connection, int $target, array $report): void
    {
        $report['log'] = array_values(array_map(fn ($l) => mb_substr((string) $l, 0, 500), array_slice((array) ($report['log'] ?? []), -100)));
        try {
            $this->client($connection)->post("api/v1/deployments/{$target}/complete", $report);
        } catch (AgentException $e) {
            if ($e->retryable) {
                $outbox = $this->readJson('outbox.json');
                $outbox[$target] = $report;
                $this->writeJson('outbox.json', $outbox);
                AgentLog::write('warning', 'Could not send the result to the update server; it will be sent on the next run.', $target);
            } else {
                AgentLog::write('error', 'The update server did not accept the result: '.$e->getMessage(), $target);
            }
        }
    }

    protected function flushOutbox(AgentConnection $connection): void
    {
        $outbox = $this->readJson('outbox.json');
        foreach ($outbox as $target => $report) {
            try {
                $this->client($connection)->post("api/v1/deployments/{$target}/complete", $report);
                unset($outbox[$target]);
            } catch (AgentException $e) {
                if (! $e->retryable) {
                    unset($outbox[$target]);
                }
                break;
            }
        }
        $this->writeJson('outbox.json', $outbox);
    }

    protected function abandon(AgentConnection $connection, array $job, string $why): void
    {
        try {
            $this->updater->cancel();
        } catch (Throwable) {
        }
        $this->clearJob();
        if (! empty($job['target'])) {
            AgentLog::write('error', $why, (int) $job['target']);
            $this->report($connection, (int) $job['target'], ['result' => 'failed', 'error' => $why, 'error_code' => 'interrupted', 'retryable' => true]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Local state                                                         */
    /* ------------------------------------------------------------------ */

    public function job(): ?array
    {
        return $this->readJson('job.json') ?: null;
    }

    protected function saveJob(array $job): void
    {
        $this->writeJson('job.json', $job);
    }

    protected function clearJob(): void
    {
        @unlink($this->dir.'/job.json');
    }

    /** Which backup belongs to which deployment (for rollbacks). */
    public function history(): array
    {
        return $this->readJson('history.json');
    }

    protected function remember(int $target, array $entry): void
    {
        $history = $this->history();
        $history[$target] = $entry + ['at' => now()->toIso8601String()];
        $this->writeJson('history.json', array_slice($history, -50, null, true));
    }

    protected function forget(int $target): void
    {
        $history = $this->history();
        unset($history[$target]);
        $this->writeJson('history.json', $history);
    }

    protected function installedManifestPath(string $type, string $slug): string
    {
        return $this->dir.'/installed/'.($type === 'core' ? 'core' : $slug).'.json';
    }

    /** Keep the signed manifest of what is installed, to detect hand-edited files before the next update. */
    protected function storeInstalledManifest(array $manifest, array $job): void
    {
        if (empty($job['signed']['manifest'])) {
            return;
        }
        $path = $this->installedManifestPath($manifest['type'], $manifest['slug']);
        @mkdir(dirname($path), 0755, true);
        // The signed original (re-verified whenever it is used).
        file_put_contents($path, json_encode($job['signed']));
    }

    protected function client(AgentConnection $connection): CentralClient
    {
        return new CentralClient($connection);
    }

    protected function canSpawn(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    protected function serverInfo(): array
    {
        return [
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'zip' => class_exists(\ZipArchive::class),
            'disk_free_mb' => ($free = @disk_free_space(base_path())) ? (int) round($free / 1048576) : null,
            'public_html_layout' => realpath(public_path()) !== realpath(base_path('public')),
            'can_spawn_process' => $this->canSpawn(),
            'database' => config('database.default'),
            'timezone' => config('app.timezone'),
        ];
    }

    protected function readJson(string $file): array
    {
        $data = json_decode((string) @file_get_contents($this->dir.'/'.$file), true);

        return is_array($data) ? $data : [];
    }

    protected function writeJson(string $file, array $data): void
    {
        @mkdir($this->dir, 0755, true);
        if (! $data && $file !== 'history.json') {
            @unlink($this->dir.'/'.$file);

            return;
        }
        file_put_contents($this->dir.'/'.$file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

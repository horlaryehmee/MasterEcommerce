<?php

namespace App\Services\Agent;

use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Services\Updater\DatabaseBackup;
use App\Services\Updater\Updater;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * A backup requested from the central update server (Central → website → Create backup).
 *
 * The site builds one zip: database.sql.gz (importable with phpMyAdmin), the uploaded
 * files if asked (storage/app/public: product photos, banners, logos; plus payment
 * receipts), and backup.json describing it. It then uploads the zip in chunks. Each
 * cron run sends what it can in ~45 seconds and the next run continues, so big
 * backups and flaky connections work on shared hosting. Nothing is ever restored or
 * changed on the site: the shop keeps running while the backup is made.
 *
 * Owners can switch this off with AGENT_ALLOW_BACKUPS=false (the site then stops
 * telling the server it can make backups).
 */
class SiteBackup
{
    /** Seconds of uploading per run before handing over to the next cron run. */
    protected const RUN_BUDGET = 45;

    public function __construct(protected Updater $updater, protected DatabaseBackup $database) {}

    public static function enabled(): bool
    {
        return (bool) config('agent.allow_backups') && class_exists(ZipArchive::class);
    }

    /** Carry out (or continue) backup $id. Returns the agent's usual status array. */
    public function run(AgentConnection $connection, CentralClient $client, int $id): array
    {
        if (! self::enabled()) {
            return $this->failed($client, $id, 'Backups are switched off on this website (AGENT_ALLOW_BACKUPS=false).');
        }

        try {
            $accept = $client->post("api/v1/backups/{$id}/accept");
        } catch (AgentException $e) {
            if (in_array($e->status, [404, 409], true)) {
                $this->discard($id);

                return ['status' => 'ok', 'message' => 'The backup is no longer wanted.'];
            }

            return ['status' => 'offline', 'message' => $e->getMessage()];
        }

        $state = $this->state();
        if (($state['id'] ?? null) !== $id || ! is_file((string) ($state['file'] ?? ''))) {
            $this->discard(); // any older, abandoned backup
            try {
                AgentLog::write('info', 'The update server asked for a backup'.(! empty($accept['include_files']) ? ' (database and files).' : ' (database only).'));
                $state = $this->build($id, (bool) ($accept['include_files'] ?? true), (int) ($accept['max_size'] ?? 0));
            } catch (Throwable $e) {
                report($e);
                $this->discard($id);

                return $this->failed($client, $id, 'The backup could not be created: '.$e->getMessage());
            }
        }

        // Continue from what the server already has (it says so when accepting).
        $state['offset'] = min((int) ($accept['received'] ?? 0), $state['size']);
        $chunk = max(65536, (int) ($accept['chunk_size'] ?? 4194304));
        $deadline = microtime(true) + self::RUN_BUDGET;

        $in = fopen($state['file'], 'rb');
        try {
            while ($state['offset'] < $state['size']) {
                if (microtime(true) > $deadline) {
                    $this->saveState($state);

                    return ['status' => 'ok', 'message' => 'Backup upload continues on the next run ('.round($state['offset'] / max(1, $state['size']) * 100).'%).'];
                }
                fseek($in, $state['offset']);
                $bytes = (string) fread($in, $chunk);
                try {
                    $answer = $client->upload("api/v1/backups/{$id}/chunk", $bytes, ['X-Offset' => (string) $state['offset']]);
                    $state['offset'] = (int) ($answer['received'] ?? $state['offset'] + strlen($bytes));
                } catch (AgentException $e) {
                    if ($e->errorCode === 'offset_mismatch') {
                        $this->saveState($state);

                        return ['status' => 'ok', 'message' => 'Backup upload will continue from where the server is.'];
                    }
                    if (in_array($e->status, [404, 409], true)) {
                        fclose($in);
                        $in = null;
                        $this->discard($id);

                        return ['status' => 'ok', 'message' => 'The backup was cancelled on the update server.'];
                    }
                    if (! $e->retryable && $e->status !== null) {
                        fclose($in);
                        $in = null;
                        $this->discard($id);

                        return $this->failed($client, $id, 'The update server refused the backup: '.$e->getMessage());
                    }
                    $this->saveState($state);

                    return ['status' => 'offline', 'message' => 'Backup upload paused: '.$e->getMessage()];
                }
            }
        } finally {
            if ($in) {
                fclose($in);
            }
        }

        try {
            $result = $client->post("api/v1/backups/{$id}/complete", [
                'result' => 'completed', 'size' => $state['size'], 'sha256' => $state['sha256'],
                'contents' => ['database' => true, 'files' => $state['files'], 'version' => $this->updater->currentVersion()],
            ]);
        } catch (AgentException $e) {
            $this->saveState($state);

            return ['status' => 'offline', 'message' => 'Backup sent; confirming it next run: '.$e->getMessage()];
        }

        $this->discard($id);
        if (! empty($result['ok'])) {
            AgentLog::write('success', 'Backup sent to the update server ('.self::humanBytes($state['size']).').');

            return ['status' => 'ok', 'message' => 'Backup sent to the update server.'];
        }
        AgentLog::write('warning', 'The update server did not accept the backup: '.($result['error'] ?? 'unknown reason').'.');

        return ['status' => 'error', 'message' => 'The update server did not accept the backup.'];
    }

    /** @return array{id: int, file: string, size: int, sha256: string, files: int, offset: int} */
    protected function build(int $id, bool $includeFiles, int $maxSize): array
    {
        @set_time_limit(0);
        $dir = $this->dir();
        File::ensureDirectoryExists($dir, 0700);
        $zipPath = "{$dir}/backup-{$id}.zip";
        $sqlPath = "{$dir}/backup-{$id}.sql.gz";
        @unlink($zipPath);

        $tables = $this->database->dump($sqlPath, 'Backup for the update server');

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file (is the disk full?).');
        }
        $zip->addFile($sqlPath, 'database.sql.gz');
        $zip->setCompressionName('database.sql.gz', ZipArchive::CM_STORE); // already gzipped

        $files = 0;
        if ($includeFiles) {
            foreach ($this->fileRoots() as $root => $prefix) {
                if (! is_dir($root)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }
                    $relative = $prefix.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    $zip->addFile($file->getPathname(), $relative);
                    // Photos and archives are already compressed: store them as they are (much faster).
                    if (preg_match('/\.(webp|jpe?g|png|gif|avif|gz|zip|pdf|mp4)$/i', $relative)) {
                        $zip->setCompressionName($relative, ZipArchive::CM_STORE);
                    }
                    $files++;
                }
            }
        }

        $zip->addFromString('backup.json', json_encode([
            'site' => config('app.url'),
            'version' => $this->updater->currentVersion(),
            'created_at' => now()->toIso8601String(),
            'database' => ['file' => 'database.sql.gz', 'tables' => $tables['tables'], 'rows' => $tables['rows']],
            'files' => $includeFiles ? ['count' => $files, 'folders' => array_values($this->fileRoots())] : null,
            'restore' => 'Import database.sql.gz with phpMyAdmin (cPanel). Copy files/public/* into storage/app/public and files/receipts/* into storage/app/private/receipts.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $zip->close()) {
            throw new RuntimeException('Could not finish the backup file (is the disk full?).');
        }
        @unlink($sqlPath);

        $size = (int) filesize($zipPath);
        if ($maxSize > 0 && $size > $maxSize) {
            @unlink($zipPath);
            throw new RuntimeException('The backup is '.self::humanBytes($size).', more than the update server accepts ('.self::humanBytes($maxSize).'). Try a database-only backup.');
        }

        $state = ['id' => $id, 'file' => $zipPath, 'size' => $size, 'sha256' => hash_file('sha256', $zipPath), 'files' => $files, 'offset' => 0];
        $this->saveState($state);

        return $state;
    }

    /** Folders included with "files": uploads and payment receipts. Never .env, code or logs. */
    protected function fileRoots(): array
    {
        return [
            rtrim(\Illuminate\Support\Facades\Storage::disk('public')->path(''), '/\\') => 'files/public/',
            storage_path('app/private/receipts') => 'files/receipts/',
        ];
    }

    protected function failed(CentralClient $client, int $id, string $error): array
    {
        AgentLog::write('error', $error);
        try {
            $client->post("api/v1/backups/{$id}/complete", ['result' => 'failed', 'error' => mb_substr($error, 0, 500)]);
        } catch (AgentException) {
            // The server marks a silent backup as failed by itself after a while.
        }

        return ['status' => 'error', 'message' => $error];
    }

    /** The backup this site is still sending, if any. */
    public function state(): array
    {
        $data = json_decode((string) @file_get_contents($this->dir().'/backup.json'), true);

        return is_array($data) ? $data : [];
    }

    protected function saveState(array $state): void
    {
        File::ensureDirectoryExists($this->dir(), 0700);
        file_put_contents($this->dir().'/backup.json', json_encode($state));
    }

    /** Remove the local backup file and state (all of them, or only backup $id). */
    public function discard(?int $id = null): void
    {
        $state = $this->state();
        if ($id !== null && ($state['id'] ?? null) !== null && $state['id'] !== $id) {
            return;
        }
        foreach (glob($this->dir().'/backup-*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->dir().'/backup.json');
    }

    protected function dir(): string
    {
        return config('agent.state_path').'/backups';
    }

    public static function humanBytes(int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1).' GB' : ($bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB');
    }
}

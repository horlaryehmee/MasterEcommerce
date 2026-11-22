<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class ReleasePackage extends Command
{
    protected $signature = 'release:package {type : core, module or extension} {slug?} {--out=}';

    protected $description = 'Build an update package (core, module or client extension)';

    public function handle(): int
    {
        $type = $this->argument('type');
        $out = rtrim($this->option('out') ?: storage_path('app/releases'), '/\\');
        if (! is_dir($out) && ! mkdir($out, 0755, true) && ! is_dir($out)) {
            throw new RuntimeException("Cannot create {$out}");
        }

        if ($type === 'core') {
            $root = base_path();
            $version = trim((string) file_get_contents(base_path('VERSION')));
            $zipPath = "{$out}/catalog-v{$version}.zip";
            $exclude = '~^(\.git|\.github|node_modules|tests|modules|extensions|storage|\.env(\..*)?$|public/storage$|public/hot$|bootstrap/cache/.+\.php$|\.phpunit)~';
        } elseif (in_array($type, ['module', 'extension'], true)) {
            $root = base_path(($type === 'module' ? 'modules' : 'extensions').'/'.$this->argument('slug'));
            $meta = json_decode((string) @file_get_contents($root.'/module.json'), true);
            if (! $meta) {
                $this->error("No module.json in {$root}");

                return self::FAILURE;
            }
            $version = $meta['version'];
            $zipPath = "{$out}/{$meta['slug']}-{$version}.zip";
            $exclude = '~^(\.git|node_modules)~';
        } else {
            $this->error('Type must be core, module or extension.');

            return self::FAILURE;
        }

        $temporary = tempnam($out, 'release-');
        if ($temporary === false) {
            throw new RuntimeException("Cannot create a temporary archive in {$out}");
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot open archive in {$out}");
            }
            $count = 0;
            $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
            $temporaryPath = realpath($temporary);
            $entries = new RecursiveCallbackFilterIterator($directory, function ($file) use ($root, $exclude, $type, $temporaryPath) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

                return ! preg_match($exclude, $relative)
                    && ($type !== 'core' || ! str_ends_with(strtolower($relative), '.zip'))
                    && $file->getRealPath() !== $temporaryPath;
            });

            foreach (new RecursiveIteratorIterator($entries) as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($file->isDir()) {
                    continue;
                }
                if (! $zip->addFile($file->getPathname(), $relative)) {
                    throw new RuntimeException("Cannot archive {$relative}");
                }
                $count++;
            }
            if ($type === 'core') {
                foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
                    if (! $zip->addEmptyDir($dir)) {
                        throw new RuntimeException("Cannot archive {$dir}");
                    }
                }
            }
            if (! $zip->close() || ! rename($temporary, $zipPath)) {
                throw new RuntimeException("Cannot finish {$zipPath}");
            }
        } finally {
            unset($zip);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        $this->info("{$zipPath} ({$count} files, ".round(filesize($zipPath) / 1048576, 1).' MB)');

        return self::SUCCESS;
    }
}

<?php

namespace App\Support\Modules;

use App\Services\Updater\Updater;
use App\Support\VersionConstraint;

/**
 * Finds optional modules (modules/<slug>) and client-specific extensions
 * (extensions/<slug>). Each folder has a module.json:
 *
 *   {
 *     "slug": "gift-wrap", "name": "Gift wrapping", "version": "1.0.0",
 *     "type": "module",                       // or "extension"
 *     "namespace": "Modules\\GiftWrap\\",     // PSR-4 root for src/
 *     "provider": "Modules\\GiftWrap\\GiftWrapServiceProvider",
 *     "requires": { "core": "^1.0", "modules": { "other": "^2.0" } },
 *     "enabled": true
 *   }
 *
 * Optional folders: src/, routes/web.php, database/migrations/, resources/views/.
 * Core updates never touch these folders.
 */
class ModuleRegistry
{
    protected ?array $cache = null;

    public function __construct(protected ?string $basePath = null)
    {
        $this->basePath ??= base_path();
    }

    /** @return array<string, array> keyed by slug */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $found = [];
        foreach (['modules' => 'module', 'extensions' => 'extension'] as $dir => $type) {
            foreach (glob($this->basePath.'/'.$dir.'/*/module.json') ?: [] as $file) {
                $path = dirname($file);
                $meta = json_decode((string) file_get_contents($file), true);
                $slug = basename($path);
                if (! is_array($meta) || ($meta['slug'] ?? null) !== $slug || ! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
                    $found[$slug] = ['slug' => $slug, 'type' => $type, 'version' => '0.0.0', 'enabled' => false, 'path' => $path, 'problem' => 'Invalid module.json (the "slug" must match the folder name).'];

                    continue;
                }
                $found[$slug] = [
                    'slug' => $slug,
                    'name' => $meta['name'] ?? $slug,
                    'version' => (string) ($meta['version'] ?? '0.0.0'),
                    'type' => $type,
                    'enabled' => ($meta['enabled'] ?? true) !== false,
                    'requires' => is_array($meta['requires'] ?? null) ? $meta['requires'] : [],
                    'namespace' => $meta['namespace'] ?? null,
                    'provider' => $meta['provider'] ?? null,
                    'path' => $path,
                    'problem' => ($meta['type'] ?? 'module') === $type ? null : "Declared as {$meta['type']} but placed in {$dir}/.",
                ];
            }
        }
        ksort($found);

        // A module whose requirements aren't met is reported but not loaded.
        $core = app(Updater::class)->currentVersion();
        foreach ($found as $slug => $m) {
            if ($m['problem'] ?? null) {
                continue;
            }
            if (! empty($m['requires']['core']) && ! VersionConstraint::satisfies($core, $m['requires']['core'])) {
                $found[$slug]['problem'] = "Needs core {$m['requires']['core']} (this site has {$core}).";
            }
            foreach ($m['requires']['modules'] ?? [] as $dep => $constraint) {
                if (! isset($found[$dep]) || ! VersionConstraint::satisfies($found[$dep]['version'], $constraint)) {
                    $found[$slug]['problem'] = "Needs module {$dep} {$constraint}.";
                }
            }
        }

        return $this->cache = $found;
    }

    /** Modules that will actually be loaded. */
    public function loadable(): array
    {
        return array_filter($this->all(), fn ($m) => $m['enabled'] && empty($m['problem']));
    }

    /** What the update server is told (no file paths). */
    public function report(): array
    {
        return array_values(array_map(fn ($m) => [
            'slug' => $m['slug'],
            'type' => $m['type'],
            'name' => $m['name'] ?? $m['slug'],
            'version' => $m['version'],
            'enabled' => $m['enabled'] && empty($m['problem']),
            'requires' => $m['requires'] ?? [],
        ], $this->all()));
    }

    public function version(string $slug): ?string
    {
        return $this->all()[$slug]['version'] ?? null;
    }

    public function flush(): void
    {
        $this->cache = null;
    }
}

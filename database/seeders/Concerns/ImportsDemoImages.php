<?php

namespace Database\Seeders\Concerns;

use App\Services\ImageStorage;

trait ImportsDemoImages
{
    /** Copy a bundled demo image into storage through the normal upload pipeline. */
    protected function importImage(string $relative, string $profile, string $format = 'webp'): ?string
    {
        $source = database_path('seeders/demo-images/'.$relative);
        if (! is_file($source)) {
            $this->command?->warn("Demo image missing: {$relative}");

            return null;
        }

        return app(ImageStorage::class)->store($source, $profile, $format);
    }
}

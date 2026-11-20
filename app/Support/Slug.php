<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Generate a slug that is unique for the given model's table,
     * appending -2, -3, ... when needed.
     *
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $value, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug($value) ?: 'item';
        $base = Str::limit($base, 150, '');
        $slug = $base;
        $i = 2;

        while ($model::query()
            ->where($column, $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

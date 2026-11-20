<?php

namespace App\Http\Controllers;

use App\Services\ImageStorage;

abstract class Controller
{
    /** Success message, plus how much uploaded images were optimised (if any). */
    protected function savedMessage(string $message): string
    {
        $summary = app(ImageStorage::class)->summary();

        return $summary ? "{$message} {$summary}." : $message;
    }
}

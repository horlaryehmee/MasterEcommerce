<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The About page's new layout has a single story block (the offer and promise
 * points now come from "Why choose us"). Sites still using the untouched starter
 * text get the shorter version; edited stories are never changed.
 */
return new class extends Migration
{
    private const OLD = <<<'MD'
{business_name} was started with a simple idea: buying quality products should be easy, honest and personal.

## Our story

We know how frustrating it can be to shop online and receive something that looks nothing like the picture, or to send a message and never hear back. So we decided to do things differently.

Every product in our catalogue is carefully chosen and checked before it's offered to you, and every customer gets a real conversation with a real person — not a faceless checkout page.

## What we offer

- A carefully selected range of products, with clear photos and descriptions
- Honest, up-to-date prices
- Friendly advice to help you choose the right item
- Convenient delivery and pick-up options

## Our promise

Quality you can trust, prices that are fair and service you can count on. If something isn't right, talk to us — we'll do our best to make it right.

Thank you for choosing {business_name}.
MD;

    public function up(): void
    {
        $new = \Database\Seeders\DefaultPageContent::for('about');

        foreach (DB::table('pages')->where('slug', 'about')->get(['id', 'content']) as $page) {
            if ($this->normalise($page->content) === $this->normalise(self::OLD)) {
                DB::table('pages')->where('id', $page->id)->update(['content' => $new, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Content change only; nothing to undo structurally.
    }

    private function normalise(?string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
    }
};

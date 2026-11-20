<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Optional banner image (used by the About page).
            $table->string('image_path')->nullable()->after('content');
            // Ordered list of {question, answer} pairs (used by the FAQ page).
            $table->json('faq_items')->nullable()->after('image_path');
        });

        $this->convertMarkdownFaq();
    }

    /**
     * Existing installs wrote FAQs as "## Question" headings in the page text.
     * Convert them into structured items so they appear in the new FAQ layout.
     */
    public function convertMarkdownFaq(): void
    {
        $faq = DB::table('pages')->where('slug', 'faq')->whereNull('faq_items')->first();
        if ($faq && $faq->content && str_contains($faq->content, '## ')) {
            $parts = preg_split('/^##\s+/m', $faq->content);
            $intro = trim(array_shift($parts));
            $items = [];
            foreach ($parts as $part) {
                [$question, $answer] = array_pad(explode("\n", $part, 2), 2, '');
                if (trim($question) !== '') {
                    $items[] = ['question' => trim($question), 'answer' => trim($answer)];
                }
            }

            if ($items) {
                DB::table('pages')->where('id', $faq->id)->update([
                    'content' => $intro !== '' ? $intro : null,
                    'faq_items' => json_encode($items),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'faq_items']);
        });
    }
};

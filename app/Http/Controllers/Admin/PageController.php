<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ImageStorage;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Edit the fixed informational pages (About, Contact, FAQ, policies). */
class PageController extends Controller
{
    public function __construct(protected ImageStorage $images) {}

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Content/PageForm', [
            'page' => [
                ...$page->only('id', 'slug', 'title', 'content', 'is_published', 'seo_title', 'seo_description'),
                'url' => $page->url(),
                'faq_items' => $page->faq_items ?? [],
                'stats' => $page->extras['stats'] ?? [],
                'quote' => ($page->extras['quote'] ?? []) + ['text' => '', 'name' => '', 'role' => ''],
                'image_url' => Media::url($page->image_path, 'sm'),
            ],
            // Extra editors shown only where the page layout uses them.
            'supportsImage' => $page->slug === 'about',
            'supportsFaq' => $page->slug === 'faq',
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $request->merge(['is_published' => $request->boolean('is_published')]);

        $rules = [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'string', 'max:50000'],
            'is_published' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ];

        if ($page->slug === 'faq') {
            $rules += [
                'faq_items' => ['nullable', 'array', 'max:50'],
                'faq_items.*.question' => ['required', 'string', 'max:255'],
                'faq_items.*.answer' => ['required', 'string', 'max:5000'],
            ];
        }

        if ($page->slug === 'about') {
            $rules += [
                'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('catalog.max_upload_kb'), 'dimensions:max_width=8000,max_height=8000'],
                'remove_image' => ['nullable', 'boolean'],
                // Headline numbers ("8+ years", "5,000+ customers") and a founder's note.
                'stats' => ['nullable', 'array', 'max:4'],
                'stats.*.value' => ['required', 'string', 'max:20'],
                'stats.*.label' => ['required', 'string', 'max:60'],
                'quote' => ['nullable', 'array'],
                'quote.text' => ['nullable', 'string', 'max:600'],
                'quote.name' => ['nullable', 'string', 'max:80'],
                'quote.role' => ['nullable', 'string', 'max:80'],
            ];
        }

        $data = $request->validate($rules, [
            'faq_items.*.question.required' => 'Each question needs text.',
            'faq_items.*.answer.required' => 'Each question needs an answer.',
            'stats.*.value.required' => 'Enter the number, e.g. 5,000+.',
            'stats.*.label.required' => 'Say what it counts, e.g. Happy customers.',
        ]);

        if ($page->slug === 'about') {
            $data['extras'] = [
                'stats' => collect($data['stats'] ?? [])->map(fn ($s) => ['value' => trim($s['value']), 'label' => trim($s['label'])])->values()->all(),
                'quote' => collect($data['quote'] ?? [])->only('text', 'name', 'role')->map(fn ($v) => trim((string) $v))->all(),
            ];
            unset($data['stats'], $data['quote']);
        }

        if ($page->slug === 'faq') {
            $data['faq_items'] = collect($data['faq_items'] ?? [])
                ->map(fn ($i) => ['question' => trim($i['question']), 'answer' => trim($i['answer'])])
                ->values()->all();
        }

        $oldImage = null;
        unset($data['image'], $data['remove_image']);
        if ($page->slug === 'about') {
            if ($request->hasFile('image')) {
                try {
                    $data['image_path'] = $this->images->store($request->file('image'), 'banners');
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['image' => $e->getMessage()]);
                }
                $oldImage = $page->image_path;
            } elseif ($request->boolean('remove_image')) {
                $data['image_path'] = null;
                $oldImage = $page->image_path;
            }
        }

        $page->update($data);
        $this->images->delete($oldImage);

        return back()->with('success', $this->savedMessage('Page saved.'));
    }
}

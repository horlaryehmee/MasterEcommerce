<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Rules\SafeLink;
use App\Services\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Slides for the homepage banner slider (managed from Website Content → Hero banner). */
class HeroSlideController extends Controller
{
    public const MAX_SLIDES = 8;

    public function __construct(protected ImageStorage $images) {}

    public function store(Request $request): RedirectResponse
    {
        if (HeroSlide::query()->count() >= self::MAX_SLIDES) {
            throw ValidationException::withMessages(['desktop_image' => 'You can have up to '.self::MAX_SLIDES.' slides.']);
        }

        $data = $this->validated($request, creating: true);
        $data['desktop_image_path'] = $this->storeImage($request, 'desktop_image');
        $data['mobile_image_path'] = $request->hasFile('mobile_image') ? $this->storeImage($request, 'mobile_image') : null;
        $data['sort_order'] = (int) HeroSlide::query()->max('sort_order') + 1;

        HeroSlide::query()->create($data);

        return back()->with('success', $this->savedMessage('Slide added.'));
    }

    public function update(Request $request, HeroSlide $slide): RedirectResponse
    {
        $data = $this->validated($request, creating: false);
        $old = [];

        if ($request->hasFile('desktop_image')) {
            $old[] = $slide->desktop_image_path;
            $data['desktop_image_path'] = $this->storeImage($request, 'desktop_image');
        }
        if ($request->hasFile('mobile_image')) {
            $old[] = $slide->mobile_image_path;
            $data['mobile_image_path'] = $this->storeImage($request, 'mobile_image');
        } elseif ($request->boolean('remove_mobile_image')) {
            $old[] = $slide->mobile_image_path;
            $data['mobile_image_path'] = null;
        }

        $slide->update($data);
        array_map(fn ($p) => $this->images->delete($p), array_filter($old));

        return back()->with('success', $this->savedMessage('Slide saved.'));
    }

    public function destroy(HeroSlide $slide): RedirectResponse
    {
        $paths = [$slide->desktop_image_path, $slide->mobile_image_path];
        $slide->delete();
        array_map(fn ($p) => $this->images->delete($p), array_filter($paths));

        return back()->with('success', 'Slide deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('hero_slides', 'id')],
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $order => $id) {
                HeroSlide::query()->whereKey($id)->update(['sort_order' => $order]);
            }
        });
        \App\Support\StorefrontCache::flush();

        return back()->with('success', 'Slide order saved.');
    }

    protected function validated(Request $request, bool $creating): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $image = ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('catalog.max_upload_kb'), 'dimensions:max_width=8000,max_height=8000'];
        $link = ['nullable', 'string', 'max:255', new SafeLink];

        $data = $request->validate([
            'desktop_image' => [$creating ? 'required' : 'nullable', ...$image],
            'mobile_image' => ['nullable', ...$image],
            'remove_mobile_image' => ['nullable', 'boolean'],
            'content_mode' => ['required', Rule::in(array_keys(HeroSlide::MODES))],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'heading' => ['nullable', 'required_if:content_mode,text', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:300'],
            'button_text' => ['nullable', 'string', 'max:40'],
            'button_link' => [...$link, 'required_with:button_text'],
            'secondary_text' => ['nullable', 'string', 'max:40'],
            'secondary_link' => [...$link, 'required_with:secondary_text'],
            'link' => $link,
            'text_position' => ['required', Rule::in(array_keys(HeroSlide::POSITIONS))],
            'text_theme' => ['required', Rule::in(array_keys(HeroSlide::THEMES))],
            'is_active' => ['boolean'],
        ], [
            'desktop_image.required' => 'Upload the desktop image for this slide.',
            'heading.required_if' => 'Add a heading, or choose “Image only” if your design already contains the text.',
        ], [
            'button_link' => 'button link',
            'secondary_link' => 'second button link',
        ]);

        unset($data['desktop_image'], $data['mobile_image'], $data['remove_mobile_image']);

        return $data;
    }

    protected function storeImage(Request $request, string $field): string
    {
        try {
            return $this->images->store($request->file($field), 'banners');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}

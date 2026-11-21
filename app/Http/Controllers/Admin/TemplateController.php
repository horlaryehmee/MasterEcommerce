<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Support\Fonts;
use App\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Admin → Templates: choose the storefront design, preview each one, share preview links. */
class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $s = BusinessSetting::current();
        $current = Themes::exists($s->theme) ? $s->theme : Themes::DEFAULT;

        return Inertia::render('Admin/Templates/Index', [
            'current' => $current,
            'templates' => collect(Themes::ALL)->map(fn (array $t, string $key) => [
                'key' => $key,
                'name' => $t['name'],
                'tagline' => $t['tagline'],
                'best_for' => $t['best_for'],
                'colors' => [$t['style']['primary_color'], $t['style']['secondary_color'], $t['style']['accent_color']],
                'fonts' => [
                    'heading' => $t['style']['heading_font'] ?? Fonts::DEFAULT,
                    'body' => $t['style']['body_font'] ?? Fonts::DEFAULT,
                    'url' => Fonts::stylesheetUrl([$t['style']['heading_font'], $t['style']['body_font']]),
                ],
                'preview_url' => Themes::previewUrl($key),
                'thumbnail_url' => Themes::thumbnailUrl($key),
            ])->values(),
            // "Use this template" from the storefront preview bar opens its confirmation here.
            'apply' => Themes::exists($request->query('apply')) ? $request->query('apply') : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(Themes::ALL))],
            'with_style' => ['boolean'],
        ]);

        Themes::apply(BusinessSetting::current(), $data['theme'], $request->boolean('with_style'));
        $request->session()->forget(Themes::PREVIEW_KEY);

        return redirect()->route('admin.templates.index')->with('success', Themes::ALL[$data['theme']]['name'].' is now your website’s template.');
    }
}

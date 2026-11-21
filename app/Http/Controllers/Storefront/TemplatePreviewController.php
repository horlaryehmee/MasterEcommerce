<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Preview a storefront template on this site without changing anything.
 * The link is signed (shareable but not guessable); the logged-in owner can
 * also open it unsigned. The preview lasts until "Exit preview" or the end of
 * the browser session.
 */
class TemplatePreviewController extends Controller
{
    public function start(Request $request, string $template): RedirectResponse
    {
        abort_unless(Themes::exists($template), 404);
        abort_unless($request->hasValidSignature() || $request->user(), 403, 'This preview link is not valid.');

        $request->session()->put(Themes::PREVIEW_KEY, $template);

        return redirect()->route('home');
    }

    public function exit(Request $request): RedirectResponse
    {
        $request->session()->forget(Themes::PREVIEW_KEY);

        return $request->user() ? redirect()->route('admin.templates.index') : redirect()->route('home');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manual URL redirects (listed in Website Settings → Redirects). */
class RedirectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'from_path' => $request->filled('from_path') ? Redirect::normalizePath((string) $request->input('from_path')) : null,
            'to_path' => trim((string) $request->input('to_path')),
        ]);

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'not_regex:#^/(admin|build|storage)(/|$)#', Rule::unique('redirects', 'from_path')],
            'to_path' => ['required', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'status_code' => ['required', Rule::in(array_keys(Redirect::STATUS_CODES))],
        ], [
            'from_path.unique' => 'There is already a redirect from this address.',
            'from_path.not_regex' => 'Admin and system addresses can’t be redirected.',
            'to_path.regex' => 'Start with / for a page on this site, or https:// for another site.',
        ], ['from_path' => 'old address', 'to_path' => 'new address']);

        if ($data['from_path'] === '/') {
            throw ValidationException::withMessages(['from_path' => 'The homepage can’t be redirected.']);
        }
        if (! preg_match('#^https?://#i', $data['to_path']) && Redirect::normalizePath($data['to_path']) === $data['from_path']) {
            throw ValidationException::withMessages(['to_path' => 'The new address must be different from the old one.']);
        }

        Redirect::query()->create($data + ['is_automatic' => false]);

        return redirect()->route('admin.settings.edit', ['tab' => 'redirects'])->with('success', 'Redirect added.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return redirect()->route('admin.settings.edit', ['tab' => 'redirects'])->with('success', 'Redirect removed.');
    }
}

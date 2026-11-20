/**
 * Send an Inertia form as an update (PUT) with file uploads.
 *
 * PHP only reads uploaded files from POST requests, so the form is posted as
 * multipart data with Laravel's `_method: 'put'` override.
 *
 * Note: `form.transform()` returns nothing in Inertia v2, so it can't be
 * chained (`form.transform(...).post(...)` throws and nothing is sent).
 * Inertia applies the transform synchronously inside `post()`, so it is reset
 * straight after — later submits of the same form aren't affected.
 */
export function putWithFiles(form, url, options = {}) {
    form.transform((data) => ({ ...data, _method: 'put' }));
    try {
        form.post(url, { forceFormData: true, ...options });
    } finally {
        form.transform((data) => data);
    }
}

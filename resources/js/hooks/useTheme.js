import { usePage } from '@inertiajs/react';

const DEFAULT = { key: 'essential', header: 'classic', footer: 'dark', categories: 'cards', headings: 'left', why: 'cards', preview: null };

/** The storefront template's layout choices (Admin → Templates). */
export default function useTheme() {
    return { ...DEFAULT, ...(usePage().props.theme || {}) };
}

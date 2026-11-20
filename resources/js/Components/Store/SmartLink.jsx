import { Link, usePage } from '@inertiajs/react';

/**
 * Renders the right kind of link for admin-entered URLs:
 *  - "whatsapp"      → the business WhatsApp chat (new tab)
 *  - "/path"         → in-app Inertia navigation
 *  - "#anchor"       → same-page anchor
 *  - "https://..."   → external link (new tab)
 */
export default function SmartLink({ href, children, ...props }) {
    const { store } = usePage().props;

    if (!href) {
        return null;
    }

    if (href === 'whatsapp') {
        if (!store?.whatsapp_url) {
            return (
                <Link href="/contact" {...props}>
                    {children}
                </Link>
            );
        }
        return (
            <a href={store.whatsapp_url} target="_blank" rel="noopener noreferrer" {...props}>
                {children}
            </a>
        );
    }

    if (href.startsWith('/')) {
        return (
            <Link href={href} {...props}>
                {children}
            </Link>
        );
    }

    if (href.startsWith('#')) {
        return (
            <a href={href} {...props}>
                {children}
            </a>
        );
    }

    return (
        <a href={href} target="_blank" rel="noopener noreferrer" {...props}>
            {children}
        </a>
    );
}

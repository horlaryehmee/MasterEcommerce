import { Link, usePage } from '@inertiajs/react';
import { WhatsAppIcon } from '@/Components/Icons';

/**
 * WhatsApp call-to-action. Falls back to the Contact page when no WhatsApp
 * number has been configured, so the button never does nothing.
 */
export default function WhatsAppButton({ href, children = 'Chat on WhatsApp', className = '', size = 'md' }) {
    const { store } = usePage().props;
    const url = href ?? store?.whatsapp_url;
    const sizing = size === 'lg' ? 'h-13 px-7 text-base' : '';

    if (!url) {
        return (
            <Link href="/contact" className={`btn-primary ${sizing} ${className}`}>
                Contact us
            </Link>
        );
    }

    return (
        <a href={url} target="_blank" rel="noopener noreferrer" className={`btn-whatsapp ${sizing} ${className}`}>
            <WhatsAppIcon size={size === 'lg' ? 22 : 18} />
            {children}
        </a>
    );
}

/** `desktopOnly`: hidden on phones, where the bottom navigation already has a WhatsApp button. */
export function FloatingWhatsApp({ desktopOnly = false }) {
    const { store } = usePage().props;

    if (!store?.show_floating_whatsapp || !store?.whatsapp_url) {
        return null;
    }

    return (
        <a
            href={store.whatsapp_url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Chat with us on WhatsApp"
            className={`fixed right-4 bottom-4 z-40 ${desktopOnly ? 'hidden lg:flex' : 'flex'} h-14 w-14 items-center justify-center rounded-full bg-whatsapp text-white shadow-lg shadow-black/20 transition-transform hover:scale-105 sm:right-6 sm:bottom-6`}
        >
            <WhatsAppIcon size={28} />
        </a>
    );
}

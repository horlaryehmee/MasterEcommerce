import { Head, usePage } from '@inertiajs/react';
import Analytics from '@/Components/Store/Analytics';
import CartDrawer from '@/Components/Store/CartDrawer';
import Footer from '@/Components/Store/Footer';
import Header from '@/Components/Store/Header';
import MobileBottomNav from '@/Components/Store/MobileBottomNav';
import StoreToast from '@/Components/Store/StoreToast';
import { FloatingWhatsApp } from '@/Components/Store/WhatsAppButton';

/**
 * `hideBottomNav`: for pages with their own sticky bar on phones (e.g. the product
 * page's enquiry bar), which takes the bottom navigation's place.
 */
export default function StoreLayout({ children, hideFloatingWhatsApp = false, hideBottomNav = false }) {
    const { seo } = usePage().props;

    return (
        <div className={`flex min-h-screen flex-col ${hideBottomNav ? '' : 'pb-[calc(4rem+env(safe-area-inset-bottom))] lg:pb-0'}`}>
            {seo?.title && <Head title={seo.title} />}
            <a href="#main" className="sr-only z-50 rounded bg-white px-4 py-2 focus:not-sr-only focus:fixed focus:top-2 focus:left-2">
                Skip to content
            </a>
            <Header />
            <main id="main" className="flex-1">
                {children}
            </main>
            <Footer />
            {!hideFloatingWhatsApp && <FloatingWhatsApp desktopOnly={!hideBottomNav} />}
            {!hideBottomNav && <MobileBottomNav />}
            <StoreToast />
            <CartDrawer />
            <Analytics />
        </div>
    );
}

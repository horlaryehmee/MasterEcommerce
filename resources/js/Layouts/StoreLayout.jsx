import { Head, usePage } from '@inertiajs/react';
import Analytics from '@/Components/Store/Analytics';
import CartDrawer from '@/Components/Store/CartDrawer';
import Footer from '@/Components/Store/Footer';
import Header from '@/Components/Store/Header';
import MobileBottomNav from '@/Components/Store/MobileBottomNav';
import { ShopInstallPrompt } from '@/Components/InstallApp';
import StoreToast from '@/Components/Store/StoreToast';
import { FloatingWhatsApp } from '@/Components/Store/WhatsAppButton';
import useTheme from '@/hooks/useTheme';

/**
 * Shown while previewing a template (Admin → Templates or a shared preview link).
 * Plain links, not Inertia ones: leaving the preview reloads the page's colours and fonts.
 */
function PreviewBar({ preview }) {
    if (!preview) return null;
    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-[45] flex justify-center px-3 lg:bottom-5">
            <div className="pointer-events-auto flex max-w-full items-center gap-2 rounded-full bg-stone-950/95 py-1.5 pr-1.5 pl-4 text-sm text-white shadow-2xl backdrop-blur" role="status">
                <span className="truncate">
                    Previewing <strong className="font-semibold">{preview.name}</strong>
                    <span className="hidden sm:inline"> template</span>
                </span>
                {preview.can_apply && (
                    <a href={preview.apply_url} className="shrink-0 rounded-full bg-white px-3.5 py-1.5 text-[13px] font-semibold text-stone-900 hover:bg-stone-100">
                        Use this template
                    </a>
                )}
                <a href={preview.exit_url} className="shrink-0 rounded-full px-3 py-1.5 text-[13px] font-medium text-white/80 hover:bg-white/10 hover:text-white">
                    Exit
                </a>
            </div>
        </div>
    );
}

/** Signed-in admin: the shop is hidden from visitors behind the coming soon / maintenance page. */
function LaunchBar() {
    const { launch } = usePage().props;
    if (!launch) return null;
    return (
        <div className="bg-amber-400 px-4 py-2 text-center text-[13px] font-medium text-amber-950" role="status">
            Only you can see the shop: visitors see the {launch.mode === 'maintenance' ? 'maintenance' : 'coming soon'} page.{' '}
            <a href="/admin/settings?tab=launch" className="font-semibold underline underline-offset-2">
                Change
            </a>
        </div>
    );
}

/**
 * `hideBottomNav`: for pages with their own sticky bar on phones (e.g. the product
 * page's enquiry bar), which takes the bottom navigation's place.
 */
export default function StoreLayout({ children, hideFloatingWhatsApp = false, hideBottomNav = false }) {
    const { seo, brand } = usePage().props;
    const theme = useTheme();

    return (
        <div data-theme={theme.key} className={`store-shell flex min-h-screen flex-col ${hideBottomNav ? '' : 'pb-[calc(4rem+env(safe-area-inset-bottom))] lg:pb-0'}`}>
            {seo?.title && <Head title={seo.title} />}
            <a href="#main" className="sr-only z-50 rounded bg-white px-4 py-2 focus:not-sr-only focus:fixed focus:top-2 focus:left-2">
                Skip to content
            </a>
            <LaunchBar />
            <Header />
            <main id="main" className="flex-1">
                {children}
            </main>
            <Footer />
            {!hideFloatingWhatsApp && <FloatingWhatsApp desktopOnly={!hideBottomNav} />}
            {!hideBottomNav && <MobileBottomNav />}
            {/* Not on cart/checkout (no bottom bar there), nor while previewing a template. */}
            {!hideBottomNav && !theme.preview && <ShopInstallPrompt name={brand?.name || 'our'} />}
            <StoreToast />
            <PreviewBar preview={theme.preview} />
            <CartDrawer />
            <Analytics />
        </div>
    );
}

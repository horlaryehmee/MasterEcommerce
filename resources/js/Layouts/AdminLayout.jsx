import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    ChevronRight,
    ExternalLink,
    FolderTree,
    LayoutDashboard,
    LayoutGrid,
    LayoutTemplate,
    LogOut,
    Package,
    PanelsTopLeft,
    RefreshCw,
    Settings,
    ShoppingBag,
    TicketPercent,
    UserRound,
    Users,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Sheet } from '@/Components/Admin/ui';

const NAV = [
    { key: 'overview', label: 'Overview', short: 'Home', href: '/admin', icon: LayoutDashboard, exact: true },
    { key: 'analytics', label: 'Analytics', href: '/admin/analytics', icon: BarChart3, hint: 'Visitors, pages and sales' },
    { key: 'orders', label: 'Orders', href: '/admin/orders', icon: ShoppingBag, shopOnly: true },
    { key: 'customers', label: 'Customers', href: '/admin/customers', icon: Users, shopOnly: true, hint: 'Who has ordered and how much' },
    { key: 'coupons', label: 'Coupons', href: '/admin/coupons', icon: TicketPercent, shopOnly: true, hint: 'Discount codes for your shop' },
    { key: 'products', label: 'Products', href: '/admin/products', icon: Package },
    { key: 'categories', label: 'Categories', href: '/admin/categories', icon: FolderTree, hint: 'Group your products' },
    { key: 'templates', label: 'Templates', href: '/admin/templates', icon: LayoutTemplate, hint: 'Choose your website design' },
    { key: 'content', label: 'Website Content', short: 'Content', href: '/admin/content', icon: PanelsTopLeft, also: ['/admin/pages'], hint: 'Homepage, banners and pages' },
    { key: 'settings', label: 'Website Settings', short: 'Settings', href: '/admin/settings', icon: Settings, also: ['/admin/shipping-zones'], hint: 'Business, branding, checkout, SEO' },
    { key: 'updates', label: 'Updates', href: '/admin/updates', icon: RefreshCw, hint: 'Install new versions of your website' },
    { key: 'profile', label: 'Profile', href: '/admin/profile', icon: UserRound, hint: 'Your name, email and password' },
];

/** Small "New" pill for the Updates entry when a newer version is known. */
function UpdateBadge({ inverted = false }) {
    return <span className={`ml-auto shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${inverted ? 'bg-white/20 text-white' : 'bg-sky-100 text-sky-800'}`}>New</span>;
}

function active(url, item) {
    const path = url.split('?')[0];
    if (item.exact) return path === item.href;
    return [item.href, ...(item.also || [])].some((h) => path === h || path.startsWith(h + '/'));
}

function useNav() {
    const { admin } = usePage().props;
    return NAV.filter((item) => !item.shopOnly || admin?.shop_enabled);
}

function Flash() {
    const { flash, errors } = usePage().props;
    const [visible, setVisible] = useState(null);

    useEffect(() => {
        if (flash?.success) setVisible({ type: 'success', text: flash.success });
        else if (flash?.error) setVisible({ type: 'error', text: flash.error });
        else if (errors && Object.keys(errors).length) setVisible({ type: 'error', text: 'Please check the highlighted fields and try again.' });
        else return;
        const t = setTimeout(() => setVisible(null), 4000);
        return () => clearTimeout(t);
    }, [flash, errors]);

    if (!visible) return null;
    const ok = visible.type === 'success';

    return (
        <div className="fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-50 flex justify-center lg:inset-x-auto lg:right-6 lg:bottom-6" role="status" aria-live="polite">
            <div
                className={`flex w-full max-w-md items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-xl transition duration-300 ease-[cubic-bezier(.34,1.3,.64,1)] starting:translate-y-4 starting:opacity-0 lg:w-auto lg:rounded-xl ${ok ? 'bg-zinc-900 text-white' : 'bg-red-600 text-white'}`}
            >
                {ok ? <CheckCircle2 size={18} className="shrink-0 text-emerald-400" /> : <XCircle size={18} className="shrink-0" />}
                <span className="min-w-0 flex-1">{visible.text}</span>
                <button type="button" onClick={() => setVisible(null)} className="-mr-1 rounded-full p-1 opacity-70 hover:opacity-100" aria-label="Dismiss">
                    <X size={16} />
                </button>
            </div>
        </div>
    );
}

function BrandMark({ size = 'h-8' }) {
    const { brand } = usePage().props;
    return brand.logo ? (
        <img src={brand.logo} alt={brand.name} className={`${size} w-auto max-w-[150px] object-contain`} />
    ) : (
        <span className="truncate text-lg font-bold text-zinc-900">{brand.name}</span>
    );
}

/** Desktop sidebar. */
function Sidebar() {
    const { auth, admin } = usePage().props;
    const { url } = usePage();
    const items = useNav();

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center gap-3 border-b border-zinc-100 px-5">
                <BrandMark />
            </div>
            <nav className="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Admin">
                {items.map((item) => {
                    const on = active(url, item);
                    const badge = item.key === 'orders' ? admin?.new_orders : 0;
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${on ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'}`}
                        >
                            <item.icon size={18} />
                            {item.label}
                            {badge > 0 && (
                                <span className={`ml-auto rounded-full px-2 py-0.5 text-xs font-semibold ${on ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'}`} title={`${badge} new`}>
                                    {badge}
                                </span>
                            )}
                            {item.key === 'updates' && admin?.update_available && <UpdateBadge inverted={on} />}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => router.post('/admin/logout')}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-red-50 hover:text-red-700"
                >
                    <LogOut size={18} /> Logout
                </button>
            </nav>
            <div className="border-t border-zinc-100 p-3">
                <a href="/" target="_blank" rel="noopener noreferrer" className="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100">
                    View website <ExternalLink size={15} />
                </a>
                <p className="truncate px-3 pt-2 text-xs text-zinc-400">Signed in as {auth.user?.email}</p>
                {admin?.version && <p className="px-3 pt-0.5 text-xs text-zinc-400">Version {admin.version}</p>}
                <AuthorCredit className="px-3 pt-0.5" />
            </div>
        </div>
    );
}

/** Phones & tablets: the four most-used sections, plus "More" for everything else. */
function mobileTabs(items, shop) {
    const pick = (key) => items.find((i) => i.key === key);
    const main = shop ? ['overview', 'orders', 'products', 'settings'] : ['overview', 'products', 'categories', 'settings'];
    return { tabs: main.map(pick).filter(Boolean), rest: items.filter((i) => !main.includes(i.key)) };
}

function MobileTab({ item, on, badge, ...props }) {
    const Icon = item.icon;
    const Component = item.href ? Link : 'button';
    return (
        <Component
            {...props}
            className="group relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 outline-none select-none focus-visible:bg-zinc-100"
            aria-current={on && item.href ? 'page' : undefined}
        >
            <span className={`relative flex h-8 w-14 items-center justify-center rounded-full transition-all duration-300 group-active:scale-90 ${on ? 'bg-zinc-900 text-white' : 'text-zinc-500'}`}>
                <Icon size={20} strokeWidth={on ? 2.2 : 1.9} />
                {badge === true && <span className="absolute top-0 right-3 h-2.5 w-2.5 rounded-full bg-sky-500 ring-2 ring-white" aria-label="Update available" />}
                {typeof badge === 'number' && badge > 0 && (
                    <span className="absolute -top-1 right-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] leading-none font-bold text-white ring-2 ring-white">
                        {badge > 99 ? '99+' : badge}
                    </span>
                )}
            </span>
            <span className={`max-w-full truncate px-0.5 text-[11px] leading-none ${on ? 'font-semibold text-zinc-900' : 'font-medium text-zinc-500'}`}>{item.short || item.label}</span>
        </Component>
    );
}

function TabBar({ onMore, moreOpen }) {
    const { admin } = usePage().props;
    const { url } = usePage();
    const { tabs, rest } = mobileTabs(useNav(), admin?.shop_enabled);
    const moreActive = moreOpen || rest.some((i) => active(url, i));

    return (
        <nav
            aria-label="Admin sections"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_24px_-14px_rgba(24,24,27,0.25)] backdrop-blur-xl backdrop-saturate-150 lg:hidden"
        >
            <div className="mx-auto flex h-16 max-w-xl">
                {tabs.map((item) => (
                    <MobileTab key={item.key} item={item} href={item.href} on={!moreOpen && active(url, item)} badge={item.key === 'orders' ? admin?.new_orders : 0} />
                ))}
                <MobileTab item={{ label: 'More', icon: LayoutGrid }} type="button" onClick={onMore} on={moreActive} badge={admin?.update_available ? true : 0} aria-haspopup="dialog" aria-expanded={moreOpen} />
            </div>
        </nav>
    );
}

/** "by Bakhtech Solutions", linking to the developer's website. */
export function AuthorCredit({ className = '', as: Tag = 'p' }) {
    const { author } = usePage().props;
    if (!author) return null;
    return (
        <Tag className={`text-xs text-zinc-400 ${className}`}>
            by{' '}
            <a href={author.url} target="_blank" rel="noopener" className="font-medium text-zinc-500 hover:text-zinc-900">
                {author.name}
            </a>
        </Tag>
    );
}

/** "More" sheet: every other section as a big tappable list, then the website link and logout. */
function MoreSheet({ open, onClose }) {
    const { admin, auth, brand } = usePage().props;
    const { url } = usePage();
    const { rest } = mobileTabs(useNav(), admin?.shop_enabled);

    return (
        <Sheet open={open} onClose={onClose} title="More">
            <div className="flex items-center gap-3 rounded-2xl bg-zinc-50 p-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-base font-semibold text-white">{(auth.user?.name || brand.name || 'A').charAt(0).toUpperCase()}</span>
                <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold text-zinc-900">{auth.user?.name || 'Administrator'}</span>
                    <span className="block truncate text-xs text-zinc-500">{auth.user?.email}</span>
                </span>
            </div>
            <ul className="mt-2">
                {rest.map((item) => {
                    const on = active(url, item);
                    return (
                        <li key={item.key}>
                            <Link href={item.href} onClick={onClose} className={`flex items-center gap-3.5 rounded-2xl px-3 py-3 transition active:scale-[0.98] active:bg-zinc-100 ${on ? 'bg-zinc-100' : ''}`}>
                                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${on ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700'}`}>
                                    <item.icon size={19} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-[15px] font-medium text-zinc-900">{item.label}</span>
                                    {item.hint && <span className="block truncate text-xs text-zinc-500">{item.hint}</span>}
                                </span>
                                {item.key === 'updates' && admin?.update_available && <UpdateBadge />}
                                <ChevronRight size={18} className="shrink-0 text-zinc-300" />
                            </Link>
                        </li>
                    );
                })}
            </ul>
            <div className="mt-2 grid grid-cols-2 gap-2 border-t border-zinc-100 pt-3 pb-1">
                <a href="/" target="_blank" rel="noopener noreferrer" className="flex h-12 items-center justify-center gap-2 rounded-xl bg-zinc-100 text-sm font-semibold text-zinc-800 transition active:scale-[0.97]">
                    <ExternalLink size={16} /> View website
                </a>
                <button type="button" onClick={() => router.post('/admin/logout')} className="flex h-12 items-center justify-center gap-2 rounded-xl bg-red-50 text-sm font-semibold text-red-700 transition active:scale-[0.97]">
                    <LogOut size={16} /> Log out
                </button>
            </div>
            <p className="pt-2 text-center text-xs text-zinc-400">
                {admin?.version && <>Version {admin.version} · </>}
                <AuthorCredit as="span" />
            </p>
        </Sheet>
    );
}

/** Phones & tablets: app bar. The compact title fades in once the page's large title scrolls away. */
function AppBar({ title }) {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 56);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <header
            className={`sticky top-0 z-30 flex h-14 items-center gap-3 px-4 pt-[env(safe-area-inset-top)] backdrop-blur-xl backdrop-saturate-150 transition-[background-color,box-shadow,border-color] duration-300 lg:hidden ${
                scrolled ? 'border-b border-zinc-200/80 bg-white/90 shadow-[0_6px_20px_-16px_rgba(24,24,27,0.35)]' : 'border-b border-transparent bg-zinc-50/80'
            }`}
        >
            <Link href="/admin" className="flex shrink-0 items-center" aria-label="Admin home">
                <BrandMark size="h-7" />
            </Link>
            <span className={`min-w-0 flex-1 truncate text-center text-[15px] font-semibold text-zinc-900 transition-all duration-300 ${scrolled ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'}`} aria-hidden={!scrolled}>
                {title}
            </span>
            <a href="/" target="_blank" rel="noopener noreferrer" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-100 active:scale-90" aria-label="View website">
                <ExternalLink size={19} />
            </a>
        </header>
    );
}

export default function AdminLayout({ title, children }) {
    const [moreOpen, setMoreOpen] = useState(false);
    const { brand } = usePage().props;
    const { url } = usePage();
    const path = url.split('?')[0];

    useEffect(() => setMoreOpen(false), [path]);

    return (
        <div className="admin-shell min-h-screen bg-zinc-50">
            <Head title={`${title ? title + ' · ' : ''}Admin · ${brand.name}`} />

            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-zinc-200 bg-white lg:block">
                <Sidebar />
            </aside>

            <div className="lg:pl-64">
                <AppBar title={title || 'Admin'} />
                {/* Re-keyed per page (not per query string) so each page eases in. */}
                <main key={path} className="mx-auto max-w-6xl animate-page-in px-4 pt-3 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:px-6 sm:pt-6 lg:px-8 lg:py-8 motion-reduce:animate-none">
                    {children}
                </main>
            </div>

            <TabBar onMore={() => setMoreOpen((o) => !o)} moreOpen={moreOpen} />
            <MoreSheet open={moreOpen} onClose={() => setMoreOpen(false)} />
            <Flash />
        </div>
    );
}

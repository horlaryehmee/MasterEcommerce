import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, Phone, Search, ShoppingCart, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import useHideOnScroll from '@/hooks/useHideOnScroll';
import { openCart } from '@/lib/cart';
import SearchForm from './SearchForm';
import SmartLink from './SmartLink';

/** Cart button with item count (opens the cart drawer), shown when online ordering is on. */
export function CartLink({ className = '' }) {
    const { cart } = usePage().props;
    if (!cart) return null;
    return (
        <button type="button" onClick={openCart} className={`relative rounded-full p-2.5 text-stone-700 transition hover:bg-stone-100 active:scale-90 ${className}`} aria-label={`Open cart, ${cart.count} ${cart.count === 1 ? 'item' : 'items'}`} aria-haspopup="dialog">
            <ShoppingCart size={20} />
            {cart.count > 0 && (
                <span className="absolute top-0.5 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[10px] leading-none font-bold text-on-accent" aria-hidden>
                    {cart.count > 99 ? '99+' : cart.count}
                </span>
            )}
        </button>
    );
}

export function Logo({ className = '' }) {
    const { brand } = usePage().props;
    return (
        <Link href="/" className={`flex shrink-0 items-center ${className}`} aria-label={`${brand.name} — Home`}>
            {brand.logo ? (
                <img src={brand.logo} alt={brand.name} className="h-9 w-auto max-w-[180px] object-contain sm:h-10" />
            ) : (
                <span className="text-xl font-extrabold tracking-tight text-stone-900">{brand.name}</span>
            )}
        </Link>
    );
}

/** Long messages that don't fit on one line scroll gently sideways instead of being cut off. */
function AnnouncementBar({ announcement }) {
    const box = useRef(null);
    const text = useRef(null);
    const [overflows, setOverflows] = useState(false);

    useEffect(() => {
        if (!box.current || !text.current || typeof ResizeObserver === 'undefined') return;
        const check = () => setOverflows(text.current.scrollWidth > box.current.clientWidth + 1);
        const ro = new ResizeObserver(check);
        ro.observe(box.current);
        check();
        return () => ro.disconnect();
    }, [announcement?.text]);

    if (!announcement) return null;

    // Measured on a hidden single-line copy; when it overflows, two copies loop seamlessly.
    const body = (
        <div ref={box} className="relative overflow-hidden">
            <span ref={text} aria-hidden className="invisible absolute whitespace-nowrap">
                {announcement.text}
            </span>
            {overflows ? (
                <>
                    <span className="sr-only">{announcement.text}</span>
                    <span aria-hidden className="flex w-max motion-safe:animate-marquee motion-reduce:w-full motion-reduce:justify-center">
                        <span className="pr-16 whitespace-nowrap">{announcement.text}</span>
                        <span className="pr-16 whitespace-nowrap motion-reduce:hidden">{announcement.text}</span>
                    </span>
                </>
            ) : (
                <span className="line-clamp-1">{announcement.text}</span>
            )}
        </div>
    );

    return (
        <div style={{ backgroundColor: announcement.bg, color: announcement.color }} className="text-center text-xs font-medium sm:text-sm">
            <div className="container-page flex min-h-9 items-center justify-center py-2">
                {announcement.link ? (
                    <SmartLink href={announcement.link} className="block min-w-0 flex-1 underline-offset-2 hover:underline">
                        {body}
                    </SmartLink>
                ) : (
                    <div className="min-w-0 flex-1">{body}</div>
                )}
            </div>
        </div>
    );
}

function useNavLinks() {
    const { store } = usePage().props;
    const has = (slug) => store?.pages?.some((p) => p.slug === slug);
    return [
        { label: 'Home', href: '/' },
        { label: 'Shop', href: '/products' },
        { label: 'Categories', href: '/categories', dropdown: true },
        has('about') && { label: 'About', href: '/about' },
        has('contact') && { label: 'Contact', href: '/contact' },
    ].filter(Boolean);
}

function isActive(url, href) {
    const path = url.split('?')[0];
    return href === '/' ? path === '/' : path === href || path.startsWith(href + '/');
}

function CategoriesDropdown({ categories, active }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const close = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
        document.addEventListener('click', close);
        return () => document.removeEventListener('click', close);
    }, []);

    return (
        <div ref={ref} className="relative" onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                className={`flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition-colors hover:text-stone-900 ${active ? 'text-stone-900' : 'text-stone-600'}`}
            >
                Categories <ChevronDown size={15} className={`transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="absolute top-full left-1/2 z-50 w-64 -translate-x-1/2 pt-2">
                    <div className="rounded-2xl border border-stone-200 bg-white p-2 shadow-xl shadow-stone-900/10">
                        {categories.map((c) => (
                            <Link key={c.url} href={c.url} onClick={() => setOpen(false)} className="block rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-100 hover:text-stone-900">
                                {c.name}
                            </Link>
                        ))}
                        <Link href="/categories" onClick={() => setOpen(false)} className="mt-1 block rounded-xl border-t border-stone-100 px-3 py-2.5 text-sm font-semibold text-stone-900 hover:bg-stone-100">
                            View all categories →
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}

function MobileMenu({ open, onClose, links }) {
    const page = usePage();
    const store = page.props.store;
    const pageUrl = page.url;

    useEffect(() => {
        document.body.style.overflow = open ? 'hidden' : '';
        const esc = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', esc);
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
            <div className="absolute inset-0 bg-stone-900/40 backdrop-blur-[2px] transition-opacity duration-300 starting:opacity-0" onClick={onClose} />
            <div className="absolute inset-y-0 right-0 flex w-[88%] max-w-sm flex-col rounded-l-3xl bg-white shadow-2xl transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] starting:translate-x-full">
                <div className="flex h-16 items-center justify-between border-b border-stone-100 px-4">
                    <Logo />
                    <button type="button" onClick={onClose} className="rounded-full p-2 text-stone-600 hover:bg-stone-100" aria-label="Close menu">
                        <X size={22} />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-5">
                    <SearchForm onDone={onClose} />
                    <nav className="mt-6 flex flex-col">
                        {links
                            .filter((l) => !l.dropdown)
                            .map((l) => (
                                <Link key={l.href} href={l.href} onClick={onClose} className={`rounded-xl px-3 py-3 text-base font-medium transition active:scale-[0.98] active:bg-stone-100 ${isActive(pageUrl, l.href) ? 'bg-stone-100 text-stone-900' : 'text-stone-700'}`}>
                                    {l.label}
                                </Link>
                            ))}
                    </nav>
                    {store?.categories?.length > 0 && (
                        <div className="mt-6">
                            <p className="px-3 text-xs font-semibold tracking-wider text-stone-400 uppercase">Categories</p>
                            <div className="mt-2 flex flex-col">
                                {store.categories.map((c) => (
                                    <Link key={c.url} href={c.url} onClick={onClose} className="rounded-xl px-3 py-2.5 text-[15px] text-stone-700 transition hover:bg-stone-50 active:scale-[0.98] active:bg-stone-100">
                                        {c.name}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
                <div className="space-y-2 border-t border-stone-100 p-4">
                    {store?.whatsapp_url && (
                        <a href={store.whatsapp_url} target="_blank" rel="noopener noreferrer" className="btn-whatsapp w-full">
                            <WhatsAppIcon size={18} /> Chat on WhatsApp
                        </a>
                    )}
                    {store?.phone && (
                        <a href={`tel:${store.phone.replace(/[^\d+]/g, '')}`} className="btn-outline w-full">
                            <Phone size={16} /> {store.phone}
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Header() {
    const page = usePage();
    const store = page.props.store;
    const url = page.url;
    const links = useNavLinks();
    const [menuOpen, setMenuOpen] = useState(false);
    const closeMenu = useCallback(() => setMenuOpen(false), []);
    const [searchOpen, setSearchOpen] = useState(false);
    const currentSearch = new URLSearchParams(url.split('?')[1] || '').get('search') || '';
    // Phones: slides away while scrolling down, back on scroll up (desktop stays put).
    const { hidden, scrolled } = useHideOnScroll(menuOpen || searchOpen);

    // Lets other sticky bars (e.g. the shop filters) sit under the header, or at the top while it's away.
    useEffect(() => {
        document.documentElement.toggleAttribute('data-header-hidden', hidden);
        return () => document.documentElement.removeAttribute('data-header-hidden');
    }, [hidden]);

    return (
        <>
            <AnnouncementBar announcement={store?.announcement} />
            <header
                className={`sticky top-0 z-40 border-b bg-white/90 backdrop-blur-md backdrop-saturate-150 transition-[transform,box-shadow,border-color] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] ${
                    scrolled ? 'border-stone-200/40 shadow-[0_6px_24px_-14px_rgba(28,25,23,0.25)]' : 'border-stone-200/70'
                } ${hidden ? 'max-lg:-translate-y-full max-lg:shadow-none' : ''}`}
            >
                <div className="container-page flex h-16 items-center gap-4 lg:h-[72px]">
                    <Logo />

                    <nav className="mx-auto hidden items-center gap-1 lg:flex" aria-label="Main">
                        {links.map((l) =>
                            l.dropdown && store?.categories?.length ? (
                                <CategoriesDropdown key={l.href} categories={store.categories} active={isActive(url, l.href)} />
                            ) : (
                                <Link
                                    key={l.href}
                                    href={l.href}
                                    className={`rounded-full px-3 py-2 text-sm font-medium transition-colors hover:text-stone-900 ${isActive(url, l.href) ? 'text-stone-900' : 'text-stone-600'}`}
                                >
                                    {l.label}
                                </Link>
                            ),
                        )}
                    </nav>

                    <div className="ml-auto flex items-center gap-1 lg:ml-0">
                        <button
                            type="button"
                            onClick={() => setSearchOpen((o) => !o)}
                            className="rounded-full p-2.5 text-stone-700 transition hover:bg-stone-100 active:scale-90"
                            aria-label="Search products"
                            aria-expanded={searchOpen}
                        >
                            {searchOpen ? <X size={20} /> : <Search size={20} />}
                        </button>
                        <CartLink />
                        {store?.whatsapp_url && (
                            <a href={store.whatsapp_url} target="_blank" rel="noopener noreferrer" className="btn-whatsapp ml-1 hidden h-10 px-4 sm:inline-flex">
                                <WhatsAppIcon size={18} />
                                <span>Chat with us</span>
                            </a>
                        )}
                        <button type="button" onClick={() => setMenuOpen(true)} className="rounded-full p-2.5 text-stone-700 transition hover:bg-stone-100 active:scale-90 lg:hidden" aria-label="Open menu">
                            <Menu size={22} />
                        </button>
                    </div>
                </div>

                {searchOpen && (
                    <div className="border-t border-stone-100 bg-white">
                        <div className="container-page py-3">
                            <SearchForm initial={currentSearch} autoFocus onDone={() => setSearchOpen(false)} className="mx-auto max-w-2xl" />
                        </div>
                    </div>
                )}
            </header>
            <MobileMenu open={menuOpen} onClose={closeMenu} links={links} />
        </>
    );
}

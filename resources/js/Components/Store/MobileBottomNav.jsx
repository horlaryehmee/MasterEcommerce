import { Link, usePage } from '@inertiajs/react';
import { House, LayoutGrid, MessageCircle, Search, ShoppingBag, ShoppingCart } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import { onCartState, openCart } from '@/lib/cart';
import useHideOnScroll from '@/hooks/useHideOnScroll';
import SearchForm from './SearchForm';

function isActive(url, href) {
    const path = url.split('?')[0];
    return href === '/' ? path === '/' : path === href || path.startsWith(href + '/');
}

/** Cart count that pops whenever it goes up. */
function Badge({ count }) {
    const previous = useRef(count);
    const [bump, setBump] = useState(0);
    useEffect(() => {
        if (count > previous.current) setBump((b) => b + 1);
        previous.current = count;
    }, [count]);

    if (!count) return null;
    return (
        <span key={bump} className={`absolute -top-1 left-[calc(50%+4px)] flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[10px] leading-none font-bold text-on-accent ring-2 ring-white ${bump ? 'animate-pop' : ''}`} aria-hidden>
            {count > 99 ? '99+' : count}
        </span>
    );
}

/** One tab: icon with the label underneath. `as` is Link, 'a' or 'button'. */
function Tab({ as: Component = Link, icon: Icon, label, active, badge, iconClass = '', ...props }) {
    return (
        <Component className="group/tab relative z-10 flex h-full min-w-0 flex-1 flex-col items-center justify-center gap-1 outline-none select-none focus-visible:bg-stone-100" {...props}>
            <span className="relative flex h-7 w-12 items-center justify-center transition-transform duration-150 group-active/tab:scale-90">
                {/* Re-keyed on activation so the icon pops each time its tab is chosen; active icons are filled. */}
                <span key={active ? 'on' : 'off'} className={`flex transition-colors duration-300 ${active ? 'animate-pop text-brand' : 'text-stone-500 group-hover/tab:text-stone-800'} ${iconClass}`}>
                    <Icon size={23} strokeWidth={active ? 2.2 : 1.8} fill={active ? 'currentColor' : 'none'} fillOpacity={active ? 0.16 : 0} aria-hidden />
                </span>
                <Badge count={badge} />
            </span>
            <span className={`max-w-full truncate px-0.5 text-[11px] leading-none transition-colors duration-300 ${active ? 'font-semibold text-brand' : 'font-medium text-stone-500'}`}>{label}</span>
        </Component>
    );
}

function SearchSheet({ open, onClose }) {
    useEffect(() => {
        if (!open) return;
        const esc = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Search products">
            <div className="absolute inset-0 bg-stone-950/40 backdrop-blur-[2px] transition-opacity starting:opacity-0" onClick={onClose} />
            <div className="absolute inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] mx-auto max-w-md rounded-3xl bg-white p-4 shadow-2xl transition duration-200 starting:translate-y-3 starting:opacity-0">
                <p className="mb-3 px-1 text-sm font-semibold text-stone-900">Search products</p>
                <SearchForm autoFocus onDone={onClose} />
            </div>
        </div>
    );
}

/**
 * Native-app style tab bar docked to the bottom of the screen on phones and small
 * tablets (hidden from lg up, where the header shows the full menu). Every tab has
 * a label; an indicator line slides to the active tab, and the bar hides while
 * scrolling down.
 */
export default function MobileBottomNav() {
    const { url, props } = usePage();
    const { store, cart } = props;
    const [searchOpen, setSearchOpen] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);
    const { hidden } = useHideOnScroll(searchOpen || cartOpen);

    useEffect(() => setSearchOpen(false), [url]);
    useEffect(() => onCartState(setCartOpen), []);

    const overlay = searchOpen || cartOpen;
    const routeActive = (href) => !overlay && isActive(url, href);
    const hasContact = store?.pages?.some((p) => p.slug === 'contact');

    const tabs = [
        { key: 'home', label: 'Home', icon: House, href: '/', active: routeActive('/') },
        { key: 'shop', label: 'Shop', icon: ShoppingBag, href: '/products', active: routeActive('/products') },
        // With online ordering on, Cart takes Categories' place (the Shop page has category filters).
        cart
            ? {
                  key: 'cart',
                  as: 'button',
                  type: 'button',
                  label: 'Cart',
                  icon: ShoppingCart,
                  badge: cart.count,
                  active: cartOpen || (!searchOpen && isActive(url, '/cart')),
                  onClick: () => {
                      setSearchOpen(false);
                      openCart();
                  },
                  'aria-label': `Cart, ${cart.count} ${cart.count === 1 ? 'item' : 'items'}`,
                  'aria-haspopup': 'dialog',
              }
            : { key: 'categories', label: 'Categories', icon: LayoutGrid, href: '/categories', active: routeActive('/categories') },
        {
            key: 'search',
            as: 'button',
            type: 'button',
            label: 'Search',
            icon: Search,
            active: searchOpen,
            onClick: () => setSearchOpen((o) => !o),
            'aria-expanded': searchOpen,
        },
        store?.whatsapp_url
            ? { key: 'chat', as: 'a', label: 'Chat', icon: WhatsAppIcon, iconClass: 'text-whatsapp!', href: store.whatsapp_url, target: '_blank', rel: 'noopener noreferrer', active: false, 'aria-label': 'Chat with us on WhatsApp' }
            : hasContact && { key: 'contact', label: 'Contact', icon: MessageCircle, href: '/contact', active: routeActive('/contact') },
    ].filter(Boolean);

    const activeIndex = tabs.findIndex((t) => t.active);

    return (
        <>
            <SearchSheet open={searchOpen} onClose={() => setSearchOpen(false)} />
            <nav
                aria-label="Quick navigation"
                className={`fixed inset-x-0 bottom-0 z-40 border-t border-stone-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_24px_-12px_rgba(28,25,23,0.18)] backdrop-blur-xl backdrop-saturate-150 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] motion-reduce:transition-none lg:hidden ${
                    hidden ? 'translate-y-full' : 'translate-y-0'
                }`}
            >
                <div className="relative mx-auto flex h-16 max-w-xl">
                    {/* Indicator line that slides to the active tab. */}
                    <span
                        aria-hidden
                        className={`pointer-events-none absolute top-0 left-0 flex justify-center transition-[transform,opacity] duration-400 ease-[cubic-bezier(.34,1.25,.64,1)] motion-reduce:transition-none ${activeIndex < 0 ? 'opacity-0' : 'opacity-100'}`}
                        style={{ width: `${100 / tabs.length}%`, transform: `translateX(${Math.max(activeIndex, 0) * 100}%)` }}
                    >
                        <span className="h-[3px] w-10 rounded-b-full bg-brand" />
                    </span>

                    {tabs.map(({ key, active, ...tab }) => (
                        <Tab key={key} active={active} aria-current={active && !tab.as ? 'page' : undefined} {...tab} />
                    ))}
                </div>
            </nav>
        </>
    );
}

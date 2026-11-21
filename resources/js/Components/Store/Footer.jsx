import { Link, usePage } from '@inertiajs/react';
import { Clock, Mail, MapPin, Phone } from 'lucide-react';
import { SOCIAL_ICONS, WhatsAppIcon } from '@/Components/Icons';
import useTheme from '@/hooks/useTheme';

/** Footer colours per template: dark (Essential, Volt), light (Atelier), tinted with the brand's secondary colour (Glow, Bloom). */
const PALETTES = {
    dark: { shell: 'bg-stone-950 text-white', title: 'text-white', link: 'text-white/65 transition-colors hover:text-white', muted: 'text-white/65', icon: 'text-white/40', social: 'border-white/15 text-white/80 hover:border-white/40 hover:text-white', rule: 'border-white/10', small: 'text-white/50', strong: 'text-white/70 hover:text-white', logo: 'bg-white' },
    light: { shell: 'border-t border-stone-200 bg-white text-stone-900', title: 'text-stone-900 tracking-[0.12em] uppercase text-xs', link: 'text-stone-600 transition-colors hover:text-stone-950', muted: 'text-stone-600', icon: 'text-stone-400', social: 'border-stone-300 text-stone-700 hover:border-stone-900 hover:text-stone-950', rule: 'border-stone-200', small: 'text-stone-500', strong: 'text-stone-700 hover:text-stone-950', logo: '' },
    tinted: { shell: 'bg-surface text-stone-900', title: 'text-stone-900', link: 'text-stone-600 transition-colors hover:text-stone-950', muted: 'text-stone-600', icon: 'text-accent', social: 'border-stone-900/15 bg-white/60 text-stone-700 hover:border-stone-900 hover:text-stone-950', rule: 'border-stone-900/10', small: 'text-stone-500', strong: 'text-stone-700 hover:text-stone-950', logo: '' },
};

export function SocialLinks({ socials, className = '', tone = PALETTES.dark.social }) {
    if (!socials?.length) return null;
    return (
        <div className={`flex flex-wrap gap-2 ${className}`}>
            {socials.map((s) => {
                const Icon = SOCIAL_ICONS[s.platform];
                return (
                    <a
                        key={s.platform}
                        href={s.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={s.label}
                        className={`flex h-10 w-10 items-center justify-center rounded-full border transition-colors ${tone}`}
                    >
                        {Icon ? <Icon size={18} /> : s.label[0]}
                    </a>
                );
            })}
        </div>
    );
}

function Column({ title, children, className = 'text-white' }) {
    return (
        <div>
            <h2 className={`text-sm font-semibold ${className}`}>{title}</h2>
            <ul className="mt-4 space-y-3 text-sm">{children}</ul>
        </div>
    );
}

export default function Footer() {
    const { store, brand, author } = usePage().props;
    const theme = useTheme();
    if (!store) return null;
    const t = PALETTES[theme.footer] || PALETTES.dark;
    const linkClass = t.link;

    return (
        <footer className={t.shell}>
            <div className="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:py-16">
                <div className="sm:col-span-2 lg:col-span-4">
                    <Link href="/" className="inline-block text-xl font-extrabold tracking-tight">
                        {brand.logo ? (
                            <span className={`inline-flex rounded-xl px-3 py-2 ${t.logo}`}>
                                <img src={brand.logo} alt={brand.name} className="h-8 w-auto max-w-[160px] object-contain" loading="lazy" />
                            </span>
                        ) : (
                            brand.name
                        )}
                    </Link>
                    {store.footer_description && <p className={`mt-5 max-w-sm text-sm leading-relaxed ${t.muted}`}>{store.footer_description}</p>}
                    <SocialLinks socials={store.socials} className="mt-6" tone={t.social} />
                </div>

                <div className="lg:col-span-2">
                    <Column title="Shop" className={t.title}>
                        <li>
                            <Link href="/products" className={linkClass}>
                                All Products
                            </Link>
                        </li>
                        {store.categories.slice(0, 5).map((c) => (
                            <li key={c.url}>
                                <Link href={c.url} className={linkClass}>
                                    {c.name}
                                </Link>
                            </li>
                        ))}
                        {store.categories.length > 5 && (
                            <li>
                                <Link href="/categories" className={linkClass}>
                                    All Categories
                                </Link>
                            </li>
                        )}
                    </Column>
                </div>

                <div className="lg:col-span-2">
                    <Column title="Information" className={t.title}>
                        {store.pages.map((p) => (
                            <li key={p.slug}>
                                <Link href={p.url} className={linkClass}>
                                    {p.title}
                                </Link>
                            </li>
                        ))}
                    </Column>
                </div>

                <div className="lg:col-span-4">
                    <Column title="Get in touch" className={t.title}>
                        {store.phone && (
                            <li className="flex gap-3">
                                <Phone size={16} className={`mt-0.5 shrink-0 ${t.icon}`} />
                                <a href={`tel:${store.phone.replace(/[^\d+]/g, '')}`} className={linkClass}>
                                    {store.phone}
                                </a>
                            </li>
                        )}
                        {store.email && (
                            <li className="flex gap-3">
                                <Mail size={16} className={`mt-0.5 shrink-0 ${t.icon}`} />
                                <a href={`mailto:${store.email}`} className={`${linkClass} break-all`}>
                                    {store.email}
                                </a>
                            </li>
                        )}
                        {store.address && (
                            <li className={`flex gap-3 ${t.muted}`}>
                                <MapPin size={16} className={`mt-0.5 shrink-0 ${t.icon}`} />
                                <span className="whitespace-pre-line">{store.address}</span>
                            </li>
                        )}
                        {store.opening_hours && (
                            <li className={`flex gap-3 ${t.muted}`}>
                                <Clock size={16} className={`mt-0.5 shrink-0 ${t.icon}`} />
                                <span className="whitespace-pre-line">{store.opening_hours}</span>
                            </li>
                        )}
                        {store.whatsapp_url && (
                            <li className="pt-2">
                                <a href={store.whatsapp_url} target="_blank" rel="noopener noreferrer" className="btn-whatsapp h-10">
                                    <WhatsAppIcon size={18} /> Chat on WhatsApp
                                </a>
                            </li>
                        )}
                    </Column>
                </div>
            </div>
            <div className={`border-t ${t.rule}`}>
                <div className={`container-page flex flex-col gap-2 py-6 text-xs sm:flex-row sm:items-center sm:justify-between ${t.small}`}>
                    <span>{store.copyright}</span>
                    {author && (
                        <span>
                            Website by{' '}
                            <a href={author.url} target="_blank" rel="noopener" className={`font-medium ${t.strong}`}>
                                {author.name}
                            </a>
                        </span>
                    )}
                </div>
            </div>
        </footer>
    );
}

import { Link, usePage } from '@inertiajs/react';
import { Clock, Mail, MapPin, Phone } from 'lucide-react';
import { SOCIAL_ICONS, WhatsAppIcon } from '@/Components/Icons';

export function SocialLinks({ socials, className = '' }) {
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
                        className="flex h-10 w-10 items-center justify-center rounded-full border border-white/15 text-white/80 transition-colors hover:border-white/40 hover:text-white"
                    >
                        {Icon ? <Icon size={18} /> : s.label[0]}
                    </a>
                );
            })}
        </div>
    );
}

function Column({ title, children }) {
    return (
        <div>
            <h2 className="text-sm font-semibold text-white">{title}</h2>
            <ul className="mt-4 space-y-3 text-sm">{children}</ul>
        </div>
    );
}

const linkClass = 'text-white/65 transition-colors hover:text-white';

export default function Footer() {
    const { store, brand } = usePage().props;
    if (!store) return null;

    return (
        <footer className="bg-stone-950 text-white">
            <div className="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:py-16">
                <div className="sm:col-span-2 lg:col-span-4">
                    <Link href="/" className="inline-block text-xl font-extrabold tracking-tight">
                        {brand.logo ? (
                            <span className="inline-flex rounded-xl bg-white px-3 py-2">
                                <img src={brand.logo} alt={brand.name} className="h-8 w-auto max-w-[160px] object-contain" loading="lazy" />
                            </span>
                        ) : (
                            brand.name
                        )}
                    </Link>
                    {store.footer_description && <p className="mt-5 max-w-sm text-sm leading-relaxed text-white/65">{store.footer_description}</p>}
                    <SocialLinks socials={store.socials} className="mt-6" />
                </div>

                <div className="lg:col-span-2">
                    <Column title="Shop">
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
                    <Column title="Information">
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
                    <Column title="Get in touch">
                        {store.phone && (
                            <li className="flex gap-3">
                                <Phone size={16} className="mt-0.5 shrink-0 text-white/40" />
                                <a href={`tel:${store.phone.replace(/[^\d+]/g, '')}`} className={linkClass}>
                                    {store.phone}
                                </a>
                            </li>
                        )}
                        {store.email && (
                            <li className="flex gap-3">
                                <Mail size={16} className="mt-0.5 shrink-0 text-white/40" />
                                <a href={`mailto:${store.email}`} className={`${linkClass} break-all`}>
                                    {store.email}
                                </a>
                            </li>
                        )}
                        {store.address && (
                            <li className="flex gap-3 text-white/65">
                                <MapPin size={16} className="mt-0.5 shrink-0 text-white/40" />
                                <span className="whitespace-pre-line">{store.address}</span>
                            </li>
                        )}
                        {store.opening_hours && (
                            <li className="flex gap-3 text-white/65">
                                <Clock size={16} className="mt-0.5 shrink-0 text-white/40" />
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
            <div className="border-t border-white/10">
                <div className="container-page py-6 text-xs text-white/50">{store.copyright}</div>
            </div>
        </footer>
    );
}

import { usePage } from '@inertiajs/react';
import { ArrowUpRight, Clock, Mail, MapPin, Phone } from 'lucide-react';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';

export default function Contact({ page, contact }) {
    const { store, brand } = usePage().props;
    const { address, opening_hours: hours, email, phone } = contact;
    const hasVisitDetails = Boolean(address || hours);
    const channels = [
        { label: 'Phone', value: phone, href: phone && `tel:${phone.replace(/[^\d+]/g, '')}`, icon: Phone },
        { label: 'Email', value: email, href: `mailto:${email}`, icon: Mail },
    ].filter(channel => channel.value);

    return (
        <StoreLayout hideFloatingWhatsApp>
            <article className="mx-auto w-full max-w-[74rem] px-5 py-9 text-stone-900 [overflow-wrap:anywhere] sm:px-8 sm:py-14 lg:py-20">
                <div className={hasVisitDetails ? 'grid items-start gap-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:gap-20' : 'max-w-2xl'}>
                    <div>
                        <h1 className="text-[clamp(2.6rem,5.5vw,4.75rem)] leading-[1.05] font-semibold tracking-[-0.055em]">{page.title}</h1>
                        {page.content_html && (
                            <div className="rich-text mt-5 max-w-[52ch] text-base leading-relaxed text-stone-600 [&_p]:my-2"
                                dangerouslySetInnerHTML={{ __html: page.content_html }} />
                        )}
                        {store?.whatsapp_url && <WhatsAppButton className="mt-5">Chat on WhatsApp</WhatsAppButton>}

                        {channels.length > 0 && (
                            <dl className="mt-8 max-w-xl divide-y divide-stone-200 border-y border-stone-200">
                                {channels.map(({ label, value, href, icon: Icon }) => (
                                    <div key={label} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
                                        <dt className="inline-flex items-center gap-2.5 text-sm font-medium text-stone-600">
                                            <Icon size={17} strokeWidth={1.6} aria-hidden />{label}
                                        </dt>
                                        <dd className="min-w-0">
                                            <a href={href} className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline">
                                                <span>{value}</span><ArrowUpRight size={15} className="shrink-0" aria-hidden />
                                            </a>
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>

                    {hasVisitDetails && (
                        <div className="rounded-2xl bg-surface p-6 text-on-surface sm:p-8">
                            {address && (
                                <section aria-labelledby="contact-address">
                                    <div className="flex items-center gap-3">
                                        <MapPin size={23} strokeWidth={1.5} aria-hidden />
                                        <h2 id="contact-address" className="text-2xl font-semibold tracking-tight">Visit us</h2>
                                    </div>
                                    <address className="mt-5 text-base leading-relaxed not-italic">
                                        {brand.name && <strong className="mb-1 block font-semibold">{brand.name}</strong>}
                                        <span className="whitespace-pre-line">{address}</span>
                                    </address>
                                    <a href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(address)}`}
                                        target="_blank" rel="noopener noreferrer" className="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-semibold underline underline-offset-4">
                                        Get directions <ArrowUpRight size={15} aria-hidden />
                                    </a>
                                </section>
                            )}
                            {hours && (
                                <section aria-labelledby="contact-hours" className={address ? 'mt-4 border-t border-current/20 pt-5' : ''}>
                                    <h2 id="contact-hours" className="flex items-center gap-2.5 text-lg font-semibold tracking-tight">
                                        <Clock size={18} strokeWidth={1.5} aria-hidden />Opening hours
                                    </h2>
                                    <p className="mt-3 text-sm leading-7 whitespace-pre-line">{hours}</p>
                                </section>
                            )}
                        </div>
                    )}
                </div>
            </article>
        </StoreLayout>
    );
}

import { Clock, Mail, MapPin, Phone } from 'lucide-react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';

function ContactCard({ icon: Icon, title, children }) {
    return (
        <div className="rounded-2xl border border-stone-200 p-5">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-stone-700">
                <Icon size={19} />
            </span>
            <h2 className="mt-4 text-sm font-semibold text-stone-900">{title}</h2>
            <div className="mt-1 text-sm whitespace-pre-line text-stone-600">{children}</div>
        </div>
    );
}

export default function Page({ page, contact }) {
    return (
        <StoreLayout>
            <div className="border-b border-stone-100 bg-stone-50/70">
                <div className="container-page py-8 sm:py-12">
                    <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: page.title }]} />
                    <h1 className="mt-4 text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{page.title}</h1>
                </div>
            </div>

            <div className="container-page py-10 sm:py-14">
                <div className={contact ? 'grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-16' : 'max-w-3xl'}>
                    <div>
                        {page.content_html && <div className="rich-text" dangerouslySetInnerHTML={{ __html: page.content_html }} />}
                        {contact && (
                            <div className="mt-8">
                                <WhatsAppButton size="lg">Chat with us on WhatsApp</WhatsAppButton>
                            </div>
                        )}
                    </div>

                    {contact && (
                        <div className="grid content-start gap-4 sm:grid-cols-2">
                            {contact.phone && (
                                <ContactCard icon={Phone} title="Phone">
                                    <a href={`tel:${contact.phone.replace(/[^\d+]/g, '')}`} className="hover:text-stone-900">
                                        {contact.phone}
                                    </a>
                                </ContactCard>
                            )}
                            {contact.email && (
                                <ContactCard icon={Mail} title="Email">
                                    <a href={`mailto:${contact.email}`} className="break-all hover:text-stone-900">
                                        {contact.email}
                                    </a>
                                </ContactCard>
                            )}
                            {contact.address && (
                                <ContactCard icon={MapPin} title="Address">
                                    {contact.address}
                                </ContactCard>
                            )}
                            {contact.opening_hours && (
                                <ContactCard icon={Clock} title="Opening hours">
                                    {contact.opening_hours}
                                </ContactCard>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Clock, Mail, MapPin, PackageCheck, Phone, Search } from 'lucide-react';
import { WhatsAppIcon } from '@/Components/Icons';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import CategoryCard from '@/Components/Store/CategoryCard';
import { WHY_ICONS } from '@/Components/Store/Home/WhyChooseUs';
import Img from '@/Components/Store/Img';
import SectionHeading from '@/Components/Store/SectionHeading';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';

const STEPS = [
    { icon: Search, title: 'Browse our catalogue', text: 'Explore our products with clear photos, descriptions and up-to-date prices.' },
    { icon: WhatsAppIcon, title: 'Enquire on WhatsApp', text: 'Tap the WhatsApp button on any product. We reply to confirm availability and answer your questions.' },
    { icon: PackageCheck, title: 'Get your order', text: 'Agree on payment and delivery or pick-up directly with us — simple, personal and secure.' },
];

function ContactItem({ icon: Icon, label, children }) {
    return (
        <div className="flex gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">
                <Icon size={18} />
            </span>
            <div className="min-w-0">
                <p className="text-xs font-semibold tracking-wider uppercase opacity-60">{label}</p>
                <div className="mt-0.5 text-sm whitespace-pre-line opacity-90">{children}</div>
            </div>
        </div>
    );
}

export default function About({ page, values, categories, contact }) {
    const { brand } = usePage().props;
    const hasContact = contact.phone || contact.email || contact.address || contact.opening_hours;

    return (
        <StoreLayout>
            {/* Hero */}
            <section className="bg-surface text-on-surface">
                <div className={`container-page grid items-center gap-10 py-10 sm:py-14 lg:gap-16 lg:py-20 ${page.image ? 'lg:grid-cols-2' : ''}`}>
                    <div className={page.image ? '' : 'max-w-3xl'}>
                        <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: page.title }]} />
                        <p className="mt-6 text-xs font-semibold tracking-[0.18em] text-accent uppercase">{brand.name}</p>
                        <h1 className="mt-3 text-4xl leading-[1.1] font-extrabold tracking-tight text-balance text-stone-950 sm:text-5xl">{page.title}</h1>
                        {page.intro && <p className="mt-5 max-w-xl text-lg leading-relaxed text-stone-600">{page.intro}</p>}
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link href="/products" className="btn-primary h-12 px-6">
                                Browse products <ArrowRight size={17} />
                            </Link>
                            <WhatsAppButton className="h-12 px-6">Chat with us</WhatsAppButton>
                        </div>
                    </div>
                    {page.image && (
                        <div className="aspect-[6/5] overflow-hidden rounded-[2rem] bg-white/50">
                            <Img image={page.image} eager sizes="(min-width: 1024px) 50vw, 100vw" className="h-full w-full object-cover" />
                        </div>
                    )}
                </div>
            </section>

            {/* Story */}
            {page.content_html && (
                <section className="py-14 sm:py-20">
                    <div className="container-page">
                        <div className="rich-text mx-auto max-w-3xl sm:prose-lg [&>p:first-child]:text-xl [&>p:first-child]:leading-relaxed [&>p:first-child]:font-medium [&>p:first-child]:text-stone-800" dangerouslySetInnerHTML={{ __html: page.content_html }} />
                    </div>
                </section>
            )}

            {/* How it works */}
            <section className="border-y border-stone-100 bg-stone-50 py-14 sm:py-20">
                <div className="container-page">
                    <SectionHeading title="How shopping with us works" subtitle="No accounts, no complicated checkout — just a friendly conversation." align="center" />
                    <ol className="grid gap-4 sm:gap-6 md:grid-cols-3">
                        {STEPS.map((step, i) => (
                            <li key={step.title} className="relative rounded-3xl bg-white p-6 sm:p-8">
                                <span className="absolute top-6 right-6 text-5xl font-extrabold text-stone-100 sm:top-8 sm:right-8" aria-hidden>
                                    {i + 1}
                                </span>
                                <span className="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-brand text-on-brand">
                                    <step.icon size={22} />
                                </span>
                                <h3 className="relative mt-5 text-lg font-semibold text-stone-900">
                                    <span className="sr-only">Step {i + 1}: </span>
                                    {step.title}
                                </h3>
                                <p className="relative mt-2 text-sm leading-relaxed text-stone-600">{step.text}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {/* Values (from "Why choose us") */}
            {values && (
                <section className="py-14 sm:py-20">
                    <div className="container-page">
                        <SectionHeading title={values.heading} subtitle={values.subheading} align="center" />
                        <div className={`grid gap-x-8 gap-y-10 sm:grid-cols-2 ${values.items.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
                            {values.items.map((item, i) => {
                                const Icon = WHY_ICONS[item.icon] || WHY_ICONS['badge-check'];
                                return (
                                    <div key={i} className="text-center sm:text-left">
                                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent/10 text-accent sm:mx-0">
                                            <Icon size={24} strokeWidth={1.8} />
                                        </span>
                                        <h3 className="mt-4 text-base font-semibold text-stone-900">{item.title}</h3>
                                        {item.text && <p className="mt-2 text-sm leading-relaxed text-stone-600">{item.text}</p>}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>
            )}

            {/* Collections */}
            {categories.length > 0 && (
                <section className={`py-14 sm:py-20 ${values ? 'border-t border-stone-100' : ''}`}>
                    <div className="container-page">
                        <SectionHeading title="Explore our collections" link="/categories" linkLabel="All categories" />
                        <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                            {categories.map((c) => (
                                <CategoryCard key={c.id} category={c} />
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Contact band */}
            <section className="pb-14 sm:pb-20">
                <div className="container-page">
                    <div className={`grid gap-10 overflow-hidden rounded-[2rem] bg-brand p-8 text-on-brand sm:p-12 ${hasContact ? 'lg:grid-cols-2 lg:items-center' : ''}`}>
                        <div>
                            <h2 className="text-3xl font-extrabold tracking-tight text-balance">We'd love to hear from you</h2>
                            <p className="mt-3 max-w-md opacity-80">Questions about a product, delivery or anything else? Send us a message and a real person will get back to you.</p>
                            <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                                <WhatsAppButton className="h-12 px-6">Chat on WhatsApp</WhatsAppButton>
                                <Link href="/contact" className="btn h-12 border border-white/25 px-6 hover:bg-white/10">
                                    Contact details
                                </Link>
                            </div>
                        </div>
                        {hasContact && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                {contact.phone && (
                                    <ContactItem icon={Phone} label="Call us">
                                        <a href={`tel:${contact.phone.replace(/[^\d+]/g, '')}`} className="hover:underline">
                                            {contact.phone}
                                        </a>
                                    </ContactItem>
                                )}
                                {contact.email && (
                                    <ContactItem icon={Mail} label="Email">
                                        <a href={`mailto:${contact.email}`} className="break-all hover:underline">
                                            {contact.email}
                                        </a>
                                    </ContactItem>
                                )}
                                {contact.address && (
                                    <ContactItem icon={MapPin} label="Visit us">
                                        {contact.address}
                                    </ContactItem>
                                )}
                                {contact.opening_hours && (
                                    <ContactItem icon={Clock} label="Opening hours">
                                        {contact.opening_hours}
                                    </ContactItem>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </section>
        </StoreLayout>
    );
}

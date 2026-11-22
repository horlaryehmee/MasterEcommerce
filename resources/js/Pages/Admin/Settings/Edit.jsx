import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowRightLeft, BadgeDollarSign, Building2, ChevronDown, ChevronRight, ChevronUp, Code2, ExternalLink, Mail as MailIcon, Palette, Pencil, Plus, Search as SearchIcon, Share2, ShoppingCart, Target, Trash2, Truck } from 'lucide-react';
import { SOCIAL_ICONS, WhatsAppIcon } from '@/Components/Icons';
import ProductCard from '@/Components/Store/ProductCard';
import FontPicker, { fontStack } from '@/Components/Admin/FontPicker';
import MultiSelect from '@/Components/Admin/MultiSelect';
import { Button, Card, ConfirmModal, Field, ColorInput, FormActions, ImageField, PageHeader, Select, Textarea, TextInput, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { putWithFiles } from '@/lib/forms';

const TABS = [
    ['business', 'Business information'],
    ['branding', 'Branding'],
    ['currency', 'Currency'],
    ['whatsapp', 'WhatsApp'],
    ['shop', 'Shop & checkout'],
    ['email', 'Email'],
    ['shipping', 'Shipping'],
    ['social', 'Social media'],
    ['seo', 'SEO'],
    ['tracking', 'Tracking & ads'],
    ['code', 'Custom code'],
    ['redirects', 'Redirects'],
];

/** Phones & tablets: settings as a grouped, app-style menu. */
const MENU = [
    ['Your business', [
        ['business', Building2, 'bg-sky-500', 'Name, logo, contact details, hours'],
        ['branding', Palette, 'bg-violet-500', 'Colours and product card design'],
        ['currency', BadgeDollarSign, 'bg-emerald-500', 'Currency symbol and price format'],
        ['whatsapp', null, 'bg-whatsapp', 'Number and enquiry messages'],
        ['social', Share2, 'bg-pink-500', 'Instagram, Facebook, TikTok…'],
    ]],
    ['Selling', [
        ['shop', ShoppingCart, 'bg-amber-500', 'Cart, payments and order emails'],
        ['email', MailIcon, 'bg-blue-500', 'SMTP or PHP mail, sender, test email'],
        ['shipping', Truck, 'bg-orange-500', 'Where you deliver and delivery fees'],
    ]],
    ['Marketing', [
        ['seo', SearchIcon, 'bg-indigo-500', 'Google search title, description, image'],
        ['tracking', Target, 'bg-rose-500', 'Analytics, Meta Pixel, TikTok'],
    ]],
    ['Advanced', [
        ['code', Code2, 'bg-zinc-700', 'Extra scripts in head and body'],
        ['redirects', ArrowRightLeft, 'bg-zinc-500', 'Send old links to new pages'],
    ]],
];

const tabLabel = (key) => TABS.find(([k]) => k === key)?.[1] || 'Settings';

function SettingsMenu() {
    return (
        <div className="space-y-6 lg:hidden">
            {MENU.map(([group, rows]) => (
                <section key={group}>
                    <h2 className="mb-2 px-1 text-xs font-semibold tracking-wider text-zinc-500 uppercase">{group}</h2>
                    <ul className="divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-xs">
                        {rows.map(([key, Icon, color, hint]) => (
                            <li key={key}>
                                <Link href={`/admin/settings?tab=${key}`} className="flex items-center gap-3.5 px-4 py-3.5 transition active:bg-zinc-50">
                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-white ${color}`}>{Icon ? <Icon size={18} /> : <WhatsAppIcon size={18} />}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[15px] font-medium text-zinc-900">{tabLabel(key)}</span>
                                        <span className="block truncate text-[13px] text-zinc-500">{hint}</span>
                                    </span>
                                    <ChevronRight size={18} className="shrink-0 text-zinc-300" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

function SaveBar({ form, label = 'Save changes' }) {
    return (
        <FormActions dirty={form.isDirty}>
            <Button loading={form.processing}>{label}</Button>
        </FormActions>
    );
}

const bind = (form, key) => ({ value: form.data[key], onChange: (e) => form.setData(key, e.target.value), error: form.errors[key] });

function submitWithFiles(form, section) {
    putWithFiles(form, `/admin/settings/${section}`, { preserveScroll: true });
}

function Business({ s }) {
    const form = useForm({
        business_name: s.business_name ?? '',
        business_description: s.business_description ?? '',
        email: s.email ?? '',
        phone: s.phone ?? '',
        address: s.address ?? '',
        opening_hours: s.opening_hours ?? '',
        logo: null,
        favicon: null,
        remove_logo: false,
        remove_favicon: false,
    });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                submitWithFiles(form, 'business');
            }}
        >
            <Card title="Business information" description="Shown in your header, footer, contact page and search results.">
                <div className="space-y-5">
                    <TextInput label="Business name" maxLength={120} required {...bind(form, 'business_name')} />
                    <Textarea label="Business description" rows={3} maxLength={1000} help="A short summary of what you sell." {...bind(form, 'business_description')} />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <ImageField label="Logo" help="PNG with a transparent background works best." aspect="aspect-[3/1]" currentUrl={s.logo_url} file={form.data.logo} onFile={(f) => form.setData('logo', f)} removed={form.data.remove_logo} onRemove={(v) => form.setData('remove_logo', v)} error={form.errors.logo} />
                        <ImageField label="Favicon" help="Small square icon for browser tabs (at least 192 × 192 px)." aspect="aspect-[3/1]" currentUrl={s.favicon_url} file={form.data.favicon} onFile={(f) => form.setData('favicon', f)} removed={form.data.remove_favicon} onRemove={(v) => form.setData('remove_favicon', v)} error={form.errors.favicon} />
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextInput label="Email address" type="email" maxLength={160} optional {...bind(form, 'email')} />
                        <TextInput label="Phone number" type="tel" maxLength={40} optional {...bind(form, 'phone')} />
                    </div>
                    <Textarea label="Address" rows={2} maxLength={500} optional {...bind(form, 'address')} />
                    <Textarea label="Opening hours" rows={3} maxLength={500} help="One line per day or group of days." optional {...bind(form, 'opening_hours')} />
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

// Used for the card previews when the catalogue is still empty.
const PLACEHOLDER_PRODUCT = { id: 0, name: 'Sample product name', url: '#', price: '₦25,000', compare_at_price: '₦30,000', discount_percent: 17, is_new_arrival: true, category: 'Category', availability: 'in_stock', purchasable: true, image: null };

/** Pick a product card design, each previewed with a real product. */
function CardStylePicker({ styles, value, onChange, product, shape, corners }) {
    const sample = product || PLACEHOLDER_PRODUCT;
    return (
        <div className="grid gap-4 sm:grid-cols-3" role="radiogroup" aria-label="Product card design">
            {styles.map((style) => {
                const on = value === style.value;
                return (
                    <button
                        key={style.value}
                        type="button"
                        role="radio"
                        aria-checked={on}
                        onClick={() => onChange(style.value)}
                        className={`flex flex-col rounded-2xl border p-3 text-left transition-colors ${on ? 'border-zinc-900 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-400'}`}
                    >
                        {/* Live preview; not interactive. */}
                        <div className="pointer-events-none mx-auto w-full max-w-[200px] select-none" inert>
                            <ProductCard product={sample} variant={style.value} shape={shape} corners={corners} quickAdd preview />
                        </div>
                        <span className="mt-4 flex items-center gap-2 text-sm font-semibold text-zinc-900">
                            <span className={`flex h-4 w-4 items-center justify-center rounded-full border ${on ? 'border-zinc-900 bg-zinc-900' : 'border-zinc-300'}`}>{on && <span className="h-1.5 w-1.5 rounded-full bg-white" />}</span>
                            {style.label}
                        </span>
                        <span className="mt-1 text-xs text-zinc-500">{style.description}</span>
                    </button>
                );
            })}
        </div>
    );
}

/** Two or three choices side by side, e.g. image shape. */
function Segmented({ label, help, options, value, onChange }) {
    return (
        <div>
            <p className="text-sm font-medium text-zinc-900">{label}</p>
            {help && <p className="mt-0.5 text-xs text-zinc-500">{help}</p>}
            <div className="mt-2 grid grid-cols-3 gap-1 rounded-xl bg-zinc-100 p-1" role="radiogroup" aria-label={label}>
                {Object.entries(options).map(([key, text]) => (
                    <button
                        key={key}
                        type="button"
                        role="radio"
                        aria-checked={value === key}
                        onClick={() => onChange(key)}
                        className={`min-h-10 rounded-lg px-2 py-2 text-[13px] font-medium transition ${value === key ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200' : 'text-zinc-600 hover:text-zinc-900'}`}
                    >
                        {text}
                    </button>
                ))}
            </div>
        </div>
    );
}

const BUTTON_RADIUS = { pill: '9999px', rounded: '0.5rem', square: '0' };

function Branding({ s, fonts, cardStyles, cardShapes, cardCorners, sampleProduct }) {
    const form = useForm({
        primary_color: s.primary_color,
        secondary_color: s.secondary_color,
        accent_color: s.accent_color,
        product_card_style: s.product_card_style || 'modern',
        product_card_shape: s.product_card_shape || 'auto',
        product_card_corners: s.product_card_corners || 'auto',
        heading_font: s.heading_font ?? '',
        body_font: s.body_font ?? '',
        heading_weight: s.heading_weight ?? '',
        button_shape: s.button_shape || 'pill',
    });
    const c = form.data;
    const known = (name) => !name || fonts.families.some((f) => f.value === name);
    const [customHeading, setCustomHeading] = useState(!known(s.heading_font));
    const [customBody, setCustomBody] = useState(!known(s.body_font));
    // Weights the chosen title font has (a typed font: regular and bold, which browsers can draw).
    const headingWeights = fonts.families.find((f) => f.value === c.heading_font)?.weights ?? (c.heading_font ? [400, 700] : [400, 500, 600, 700, 800]);
    const headingStyle = { fontFamily: fontStack(c.heading_font, fonts.families, fonts.default), fontWeight: c.heading_weight || 700 };
    const bodyStyle = { fontFamily: fontStack(c.body_font, fonts.families, fonts.default) };
    const radius = BUTTON_RADIUS[c.button_shape];
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/branding', { preserveScroll: true });
            }}
        >
            <Card title="Brand colours" description="Used for buttons, highlights and backgrounds across your storefront.">
                <div className="grid gap-5 sm:grid-cols-3">
                    <ColorInput label="Primary" help="Main buttons, header accents and the promo banner." value={c.primary_color} onChange={(v) => form.setData('primary_color', v)} error={form.errors.primary_color} />
                    <ColorInput label="Secondary" help="Soft background for the hero and highlight sections." value={c.secondary_color} onChange={(v) => form.setData('secondary_color', v)} error={form.errors.secondary_color} />
                    <ColorInput label="Accent" help="Discount badges, icons and small highlights." value={c.accent_color} onChange={(v) => form.setData('accent_color', v)} error={form.errors.accent_color} />
                </div>
                <div className="mt-8 border-t border-zinc-100 pt-6">
                    <h3 className="text-sm font-semibold text-zinc-900">Fonts & buttons</h3>
                    <p className="mt-0.5 mb-4 text-sm text-zinc-500">Pick fonts that match your brand. Fonts other than the built-in one load from Google Fonts.</p>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FontPicker
                            label="Title font"
                            help="Page titles, banner headings and section headings."
                            value={c.heading_font}
                            onChange={(v) => {
                                const weights = fonts.families.find((f) => f.value === v)?.weights;
                                // Drop a weight the new font doesn't have.
                                form.setData((d) => ({ ...d, heading_font: v, heading_weight: weights && d.heading_weight && !weights.includes(Number(d.heading_weight)) ? '' : d.heading_weight }));
                            }}
                            error={form.errors.heading_font}
                            families={fonts.families}
                            builtIn={fonts.default}
                            custom={customHeading}
                            onCustom={setCustomHeading}
                        />
                        <FontPicker
                            label="Body font"
                            help="Everything else: menus, product names, prices and text."
                            value={c.body_font}
                            onChange={(v) => form.setData('body_font', v)}
                            error={form.errors.body_font}
                            families={fonts.families}
                            builtIn={fonts.default}
                            custom={customBody}
                            onCustom={setCustomBody}
                        />
                        <Select
                            label="Title weight"
                            help="How bold titles are. As designed keeps each title's own weight."
                            options={[{ value: '', label: 'As designed' }, ...fonts.weights.filter((w) => headingWeights.includes(w.value))]}
                            {...bind(form, 'heading_weight')}
                        />
                        <Field label="Button shape">
                            <div className="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Button shape">
                                {fonts.buttonShapes.map((shape) => (
                                    <button
                                        key={shape.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={c.button_shape === shape.value}
                                        onClick={() => form.setData('button_shape', shape.value)}
                                        className={`flex h-11 items-center justify-center rounded-lg border text-sm font-medium transition-colors ${c.button_shape === shape.value ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:border-zinc-400'}`}
                                    >
                                        <span className={`mr-2 inline-block h-3.5 w-6 border-2 border-current ${shape.value === 'pill' ? 'rounded-full' : shape.value === 'rounded' ? 'rounded-[4px]' : ''}`} aria-hidden />
                                        {shape.label}
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </div>
                </div>
                <div className="mt-6 overflow-hidden rounded-xl border border-zinc-200">
                    <div className="p-5 sm:p-6" style={{ backgroundColor: c.secondary_color, ...bodyStyle }}>
                        <p className="text-xs font-semibold tracking-wider uppercase" style={{ color: c.accent_color }}>
                            Preview
                        </p>
                        <p className="mt-1.5 text-2xl leading-tight text-zinc-900 sm:text-3xl" style={headingStyle}>
                            Quality products for everyday living
                        </p>
                        <p className="mt-2 max-w-md text-sm text-zinc-600">Fresh arrivals every week, delivered to your door. Order online or chat with us on WhatsApp.</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <span className="px-5 py-2.5 text-sm font-semibold text-white" style={{ backgroundColor: c.primary_color, borderRadius: radius }}>
                                Shop now
                            </span>
                            <span className="border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900" style={{ borderRadius: radius }}>
                                Learn more
                            </span>
                            <span className="self-center rounded-full px-3 py-1 text-xs font-bold text-white" style={{ backgroundColor: c.accent_color }}>
                                -15%
                            </span>
                        </div>
                    </div>
                </div>
                <div className="mt-8 border-t border-zinc-100 pt-6">
                    <h3 className="text-sm font-semibold text-zinc-900">Product cards</h3>
                    <p className="mt-0.5 mb-4 text-sm text-zinc-500">How products look in the shop, category pages and homepage sections. The preview uses your saved colours.</p>
                    <div className="mb-5 grid gap-4 sm:grid-cols-2">
                        <Segmented label="Image shape" help="Square suits most products. Portrait (tall) suits clothing and bottles." options={cardShapes} value={form.data.product_card_shape} onChange={(v) => form.setData('product_card_shape', v)} />
                        <Segmented label="Corners" help="Rounded looks soft and friendly. Sharp looks crisp and premium." options={cardCorners} value={form.data.product_card_corners} onChange={(v) => form.setData('product_card_corners', v)} />
                    </div>
                    <CardStylePicker styles={cardStyles} value={form.data.product_card_style} onChange={(v) => form.setData('product_card_style', v)} product={sampleProduct} shape={form.data.product_card_shape} corners={form.data.product_card_corners} />
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

function Currency({ s, presets, pricePreview }) {
    const form = useForm({
        currency_code: s.currency_code,
        currency_symbol: s.currency_symbol ?? '',
        currency_position: s.currency_position,
        decimal_places: s.decimal_places,
        thousands_separator: s.thousands_separator ?? '',
        decimal_separator: s.decimal_separator,
    });

    const applyPreset = (code) => {
        const p = presets[code];
        if (!p) return form.setData('currency_code', code);
        form.setData({ currency_code: code, currency_symbol: p.symbol, currency_position: p.position, decimal_places: p.decimals, thousands_separator: p.thousands, decimal_separator: p.decimal });
    };

    // Live preview mirrors App\Support\Money::format().
    const d = form.data;
    const sample = (() => {
        const decimals = Number(d.decimal_places) || 0;
        const [int, frac] = (1234567.5).toFixed(decimals).split('.');
        const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, d.thousands_separator);
        const num = frac ? `${grouped}${d.decimal_separator}${frac}` : grouped;
        if (!d.currency_symbol) return `${num} ${d.currency_code}`;
        return d.currency_position === 'after' ? `${num} ${d.currency_symbol}` : `${d.currency_symbol}${d.currency_symbol.length > 1 ? ' ' : ''}${num}`;
    })();

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/currency', { preserveScroll: true });
            }}
        >
            <Card title="Currency" description="How prices are displayed across the website.">
                <div className="space-y-5">
                    <div>
                        <p className="mb-2 text-sm font-medium text-zinc-800">Quick setup</p>
                        <div className="flex flex-wrap gap-2">
                            {Object.keys(presets).map((code) => (
                                <button key={code} type="button" onClick={() => applyPreset(code)} className={`rounded-lg border px-3 py-1.5 text-sm font-medium ${d.currency_code === code ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50'}`}>
                                    {code} {presets[code].symbol}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-3">
                        <TextInput label="Currency code" maxLength={3} help="ISO code, e.g. NGN" value={d.currency_code} onChange={(e) => form.setData('currency_code', e.target.value.toUpperCase())} error={form.errors.currency_code} />
                        <TextInput label="Symbol" maxLength={8} {...bind(form, 'currency_symbol')} />
                        <Select label="Symbol position" options={[{ value: 'before', label: 'Before amount (₦100)' }, { value: 'after', label: 'After amount (100 €)' }]} {...bind(form, 'currency_position')} />
                        <Select label="Decimal places" options={[0, 1, 2, 3].map((n) => ({ value: n, label: String(n) }))} {...bind(form, 'decimal_places')} />
                        <Select label="Thousands separator" options={[{ value: ',', label: 'Comma (1,000)' }, { value: '.', label: 'Dot (1.000)' }, { value: ' ', label: 'Space (1 000)' }, { value: '', label: 'None (1000)' }]} {...bind(form, 'thousands_separator')} />
                        <Select label="Decimal separator" options={[{ value: '.', label: 'Dot (0.50)' }, { value: ',', label: 'Comma (0,50)' }]} {...bind(form, 'decimal_separator')} />
                    </div>
                    <div className="rounded-xl bg-zinc-50 px-4 py-3 text-sm">
                        Example price: <strong className="text-zinc-900">{sample}</strong>
                        <span className="ml-2 text-xs text-zinc-400">(saved: {pricePreview})</span>
                    </div>
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

function WhatsApp({ s, whatsappPreview }) {
    const form = useForm({
        whatsapp_number: s.whatsapp_number ?? '',
        whatsapp_general_message: s.whatsapp_general_message ?? '',
        whatsapp_product_message: s.whatsapp_product_message ?? '',
        show_floating_whatsapp: s.show_floating_whatsapp,
    });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/whatsapp', { preserveScroll: true });
            }}
        >
            <Card title="WhatsApp" description="Every WhatsApp button on your website opens a chat with this number.">
                <div className="space-y-5">
                    <TextInput label="WhatsApp number" type="tel" placeholder="2348012345678" help="International format with country code and no leading 0 — e.g. 2348012345678 for 0801 234 5678 in Nigeria." {...bind(form, 'whatsapp_number')} />
                    <Textarea label="General enquiry message" rows={3} maxLength={1000} help="Used by the header, footer and floating buttons. You can use {business_name}." {...bind(form, 'whatsapp_general_message')} />
                    <Textarea
                        label="Product enquiry message"
                        rows={4}
                        maxLength={1000}
                        help="Sent from product pages. Placeholders: {business_name}, {product_name}, {product_price}, {product_url}."
                        {...bind(form, 'whatsapp_product_message')}
                    />
                    <Toggle label="Show floating WhatsApp button" description="A round WhatsApp button in the corner of every page." checked={form.data.show_floating_whatsapp} onChange={(v) => form.setData('show_floating_whatsapp', v)} />
                    {whatsappPreview ? (
                        <a href={whatsappPreview} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 hover:underline">
                            <WhatsAppIcon size={16} /> Test your saved WhatsApp link <ExternalLink size={14} />
                        </a>
                    ) : (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">No valid WhatsApp number saved yet — WhatsApp buttons currently link to your Contact page.</p>
                    )}
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

function Shop({ s, hasWhatsapp, mail, paystack }) {
    const form = useForm({
        shop_enabled: s.shop_enabled,
        pay_whatsapp_enabled: s.pay_whatsapp_enabled,
        pay_on_delivery_enabled: s.pay_on_delivery_enabled,
        pay_bank_transfer_enabled: s.pay_bank_transfer_enabled,
        pay_paystack_enabled: s.pay_paystack_enabled,
        paystack_mode: paystack.mode,
        paystack_test_secret_key: '',
        paystack_live_secret_key: '',
        paystack_test_public_key: paystack.keys.test.public ?? '',
        paystack_live_public_key: paystack.keys.live.public ?? '',
        bank_name: s.bank_name ?? '',
        bank_account_name: s.bank_account_name ?? '',
        bank_account_number: s.bank_account_number ?? '',
        order_notification_email: s.order_notification_email ?? '',
        email_owner_new_order: s.email_owner_new_order,
        email_customer_new_order: s.email_customer_new_order,
        email_customer_updates: s.email_customer_updates,
    });
    const d = form.data;
    const toggle = (key) => ({ checked: d[key], onChange: (v) => form.setData(key, v), error: form.errors[key] });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/shop', { preserveScroll: true, onSuccess: () => form.setData((data) => ({ ...data, paystack_test_secret_key: '', paystack_live_secret_key: '' })) });
            }}
            className="space-y-6"
        >
            <Card title="Online ordering" description="Let customers add products to a cart and place orders on your website. Delivery options are set up in the Shipping tab.">
                <Toggle label="Turn on cart & checkout" description="When off, your website works as a catalogue and customers enquire on WhatsApp. Products marked Out of Stock or Available on Request are always enquiry-only." {...toggle('shop_enabled')} />
            </Card>

            <Card title="Order emails" description="Sent automatically. Customers only get emails when they enter an email address at checkout.">
                <div className="space-y-5">
                    {!mail.delivers && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Emails aren’t being sent yet.{' '}
                            <Link href="/admin/settings?tab=email" className="font-semibold underline">
                                Set up email sending
                            </Link>{' '}
                            (SMTP or PHP mail), then send a test email.
                        </p>
                    )}
                    <Toggle label="Email me about new orders" description="An order summary with the customer’s contact details and a link to the order. Reply to it to email the customer." {...toggle('email_owner_new_order')} />
                    {d.email_owner_new_order && (
                        <TextInput label="Send new-order emails to" type="email" maxLength={160} optional placeholder={s.email || 'you@example.com'} help={s.email ? `Leave blank to use your business email (${s.email}).` : 'Add an address to get an email for every new order.'} {...bind(form, 'order_notification_email')} />
                    )}
                    <Toggle label="Send customers an order confirmation" description="Their order summary, delivery details, how to pay (with your bank details for bank transfer) and a link to view the order." {...toggle('email_customer_new_order')} />
                    <Toggle label="Email customers when their order changes" description="When you mark an order Confirmed, Out for delivery, Completed or Cancelled, or mark the payment Paid or Refunded." {...toggle('email_customer_updates')} />
                    {mail.delivers && (
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                            <span className="min-w-0">
                                Sending from <strong className="break-all text-zinc-900">{mail.from}</strong>.
                            </span>
                            <Link href="/admin/settings?tab=email" className="font-medium text-zinc-900 underline">
                                Email settings & test
                            </Link>
                        </p>
                    )}
                </div>
            </Card>

            <Card title="Payment options" description="Shown to customers at checkout. Switch on at least one.">
                <div className="space-y-5">
                    <Toggle label="Confirm on WhatsApp" description={hasWhatsapp ? 'The order is saved, then the customer sends it to you on WhatsApp so you can agree payment.' : 'Add your WhatsApp number in the WhatsApp tab to use this option.'} {...toggle('pay_whatsapp_enabled')} />
                    <Toggle label="Pay on delivery" description="The customer pays with cash or bank transfer when the order arrives (or on pickup). Your bank details below are shown to them so they can transfer." {...toggle('pay_on_delivery_enabled')} />
                    <Toggle label="Bank transfer" description="Your account details are shown after the order is placed and in the confirmation email. Mark the order as paid once the money arrives." {...toggle('pay_bank_transfer_enabled')} />
                    {d.pay_bank_transfer_enabled && (
                        <div className="grid gap-4 rounded-xl bg-zinc-50 p-4 sm:grid-cols-3">
                            <TextInput label="Bank name" maxLength={120} {...bind(form, 'bank_name')} />
                            <TextInput label="Account name" maxLength={120} {...bind(form, 'bank_account_name')} />
                            <TextInput label="Account number" maxLength={40} inputMode="numeric" {...bind(form, 'bank_account_number')} />
                        </div>
                    )}
                    <Toggle
                        label="Pay online with Paystack"
                        description="Customers pay by card, bank transfer or USSD on Paystack’s secure page. Orders are marked Paid automatically, only after this website has verified the payment with Paystack."
                        {...toggle('pay_paystack_enabled')}
                    />
                    {d.pay_paystack_enabled && <PaystackKeys form={form} paystack={paystack} />}
                </div>
            </Card>

            <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                <Button loading={form.processing}>Save shop settings</Button>
            </FormActions>
        </form>
    );
}

function CopyField({ label, value, help }) {
    const [copied, setCopied] = useState(false);
    return (
        <div>
            <span className="mb-1.5 block text-sm font-medium text-zinc-800">{label}</span>
            <div className="flex gap-2">
                <input readOnly value={value} className="input min-w-0 flex-1 font-mono text-xs" onFocus={(e) => e.target.select()} />
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                        navigator.clipboard?.writeText(value).then(() => {
                            setCopied(true);
                            setTimeout(() => setCopied(false), 1500);
                        })
                    }
                >
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
            {help && <p className="mt-1.5 text-xs text-zinc-500">{help}</p>}
        </div>
    );
}

/** The secret and public key for one mode. The secret key is write-only: only a hint of the saved one is shown. */
function PaystackModeKeys({ form, paystack, mode }) {
    const saved = paystack.keys[mode];
    const live = mode === 'live';
    const Name = live ? 'Live' : 'Test';
    const envNote = (hint) => (
        <p className="text-sm text-zinc-600">
            Set in the server’s <code>.env</code> file{hint ? ` (${hint})` : ''}. It can’t be viewed or changed here.
        </p>
    );

    return (
        <div className={`space-y-4 rounded-xl border-2 bg-white p-4 ${live ? 'border-emerald-200' : 'border-amber-200'}`}>
            <p className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                <span className={`h-2.5 w-2.5 rounded-full ${live ? 'bg-emerald-600' : 'bg-amber-500'}`} />
                {Name} keys
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                {saved.from_env ? (
                    <div>
                        <span className="mb-1.5 block text-sm font-medium text-zinc-800">{Name} secret key</span>
                        {envNote(saved.hint)}
                    </div>
                ) : (
                    <TextInput
                        label={`${Name} secret key`}
                        type="password"
                        placeholder={saved.hint ? `Saved: ${saved.hint}. Paste a new key to replace it` : `sk_${mode}_…`}
                        autoComplete="new-password"
                        spellCheck={false}
                        maxLength={200}
                        help={saved.hint ? `Saved and checked with Paystack (${saved.hint}). Never shown again.` : `Starts with sk_${mode}_. Checked with Paystack when you save. Keep it private.`}
                        {...bind(form, `paystack_${mode}_secret_key`)}
                    />
                )}
                {saved.public_from_env ? (
                    <div>
                        <span className="mb-1.5 block text-sm font-medium text-zinc-800">{Name} public key</span>
                        {envNote(saved.public)}
                    </div>
                ) : (
                    <TextInput
                        label={`${Name} public key`}
                        placeholder={`pk_${mode}_…`}
                        autoComplete="off"
                        spellCheck={false}
                        maxLength={200}
                        help={`Starts with pk_${mode}_. Safe to share: it’s the key used on payment pages.`}
                        {...bind(form, `paystack_${mode}_public_key`)}
                    />
                )}
            </div>
        </div>
    );
}

/** Paystack: test/live switch, a secret key for each mode, and the webhook address. */
function PaystackKeys({ form, paystack }) {
    const mode = form.data.paystack_mode;
    const live = mode === 'live';
    const options = [
        ['test', 'Test mode', 'No real money. Use Paystack’s test cards.'],
        ['live', 'Live mode', 'Customers are charged real money.'],
    ];
    return (
        <div className="space-y-4 rounded-xl bg-zinc-50 p-4">
            {!paystack.currency_supported && (
                <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Paystack can’t charge in {paystack.currency}. Change your currency (Currency tab) to NGN, GHS, ZAR, KES, USD, XOF, EGP or RWF.</p>
            )}

            <div role="radiogroup" aria-label="Paystack mode" className="grid grid-cols-2 gap-2">
                {options.map(([value, title, text]) => {
                    const on = mode === value;
                    return (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={on}
                            disabled={paystack.mode_from_env}
                            onClick={() => form.setData('paystack_mode', value)}
                            className={`rounded-xl border-2 p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-60 ${on ? (value === 'live' ? 'border-emerald-600 bg-emerald-50' : 'border-amber-500 bg-amber-50') : 'border-zinc-200 bg-white hover:border-zinc-300'}`}
                        >
                            <span className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                <span className={`h-2.5 w-2.5 rounded-full ${on ? (value === 'live' ? 'bg-emerald-600' : 'bg-amber-500') : 'bg-zinc-300'}`} />
                                {title}
                            </span>
                            <span className="mt-0.5 block text-xs text-zinc-600">{text}</span>
                        </button>
                    );
                })}
            </div>
            {paystack.mode_from_env && <p className="text-xs text-zinc-500">The mode is set in the server’s .env file (PAYSTACK_MODE).</p>}
            {form.errors.paystack_mode && <p className="text-xs font-medium text-red-600">{form.errors.paystack_mode}</p>}

            <p className={`rounded-lg px-3 py-2 text-sm ${live ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-100 text-amber-900'}`}>
                {live
                    ? 'Live mode: payments are real. Make sure the webhook below is also added in Paystack’s Live Mode settings.'
                    : 'Test mode: nothing is really charged, and a warning shows on every admin page. Switch to Live mode when you’re ready to take real payments.'}
            </p>

            {/* Only the selected mode's keys are shown; the other mode's keys stay saved. */}
            <PaystackModeKeys key={mode} form={form} paystack={paystack} mode={mode} />

            <CopyField label="Webhook URL" value={paystack.webhook_url} help="In Paystack → Settings → API Keys & Webhooks, paste this as the Webhook URL, in both Test Mode and Live Mode. Payments are then confirmed even if the customer closes the page." />
            <p className="text-xs text-zinc-500">Copy your keys from Paystack → Settings → API Keys & Webhooks. Paystack shows test and live keys separately: use the Test Mode / Live Mode switch at the top of its dashboard.</p>
        </div>
    );
}

const DEFAULT_PORTS = { ssl: 465, tls: 587, none: 25 };

/** How the website sends email: SMTP, the server's PHP mail, off, or the server's .env file. */
function Email({ s, mail, mailers, encryptions, serverMailer, adminEmail }) {
    const serverDelivers = !['log', 'array', ''].includes(serverMailer);
    const initialMailer = s.mail_mailer === 'server' && !serverDelivers ? 'log' : s.mail_mailer;
    const form = useForm({
        mail_mailer: initialMailer,
        mail_host: s.mail_host ?? '',
        mail_port: s.mail_port ?? '',
        mail_encryption: s.mail_encryption ?? 'ssl',
        mail_username: s.mail_username ?? '',
        mail_password: '',
        remove_mail_password: false,
        mail_from_address: s.mail_from_address ?? '',
        mail_from_name: s.mail_from_name ?? '',
    });
    const d = form.data;
    const [testTo, setTestTo] = useState(s.order_notification_email || s.email || adminEmail || '');
    const [testing, setTesting] = useState(false);
    const sendTest = () => router.post('/admin/settings/email/test', { to: testTo }, { preserveScroll: true, onStart: () => setTesting(true), onFinish: () => setTesting(false) });

    const choices = Object.entries(mailers).filter(([key]) => key !== 'server' || serverDelivers || s.mail_mailer === 'server');
    const hints = {
        smtp: 'Recommended. Send through an email account (your cPanel email, Gmail, Zoho, Brevo…). Best for reaching inboxes.',
        sendmail: 'No setup: uses the hosting server’s own mail. Easiest, but emails are more likely to land in spam, and some hosts turn it off.',
        log: 'No emails are sent. Order emails are written to the log file instead.',
        server: `Use the MAIL_ settings in the server’s .env file (currently ${serverMailer.toUpperCase()}).`,
    };
    const setEncryption = (value) => {
        const standard = d.mail_port === '' || Object.values(DEFAULT_PORTS).includes(Number(d.mail_port));
        form.setData((data) => ({ ...data, mail_encryption: value, mail_port: standard ? DEFAULT_PORTS[value] : data.mail_port }));
    };

    return (
        <div className="space-y-6">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/admin/settings/email', { preserveScroll: true, onSuccess: () => form.setData((data) => ({ ...data, mail_password: '', remove_mail_password: false })) });
                }}
                className="space-y-6"
            >
                <Card title="How emails are sent" description="Used for order emails to you and your customers, and for password reset links.">
                    <fieldset className="space-y-2.5">
                        <legend className="sr-only">Sending method</legend>
                        {choices.map(([key, label]) => (
                            <label key={key} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition ${d.mail_mailer === key ? 'border-zinc-900 bg-zinc-50 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-300'}`}>
                                <input type="radio" name="mail_mailer" value={key} checked={d.mail_mailer === key} onChange={() => form.setData('mail_mailer', key)} className="mt-0.5 h-4 w-4 shrink-0 accent-zinc-900" />
                                <span className="min-w-0">
                                    <span className="block text-[15px] font-medium text-zinc-900 sm:text-sm">{label}</span>
                                    <span className="mt-0.5 block text-[13px] text-zinc-500">{hints[key]}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>
                    {form.errors.mail_mailer && <p className="mt-2 text-sm text-red-600">{form.errors.mail_mailer}</p>}
                </Card>

                {d.mail_mailer === 'smtp' && (
                    <Card title="SMTP server" description="On cPanel: Email Accounts → your address → Connect Devices shows these details (use the “Secure SSL/TLS” settings).">
                        <div className="space-y-5">
                            <div className="grid gap-5 sm:grid-cols-[1fr_8rem]">
                                <TextInput label="SMTP server" placeholder="mail.yourdomain.com" autoComplete="off" maxLength={190} {...bind(form, 'mail_host')} />
                                <TextInput label="Port" type="number" inputMode="numeric" min={1} max={65535} {...bind(form, 'mail_port')} />
                            </div>
                            <Select label="Encryption" options={Object.entries(encryptions).map(([value, label]) => ({ value, label }))} value={d.mail_encryption} onChange={(e) => setEncryption(e.target.value)} error={form.errors.mail_encryption} />
                            <div className="grid gap-5 sm:grid-cols-2">
                                <TextInput label="Username" placeholder="orders@yourdomain.com" autoComplete="off" maxLength={190} help="Usually the full email address." optional {...bind(form, 'mail_username')} />
                                <TextInput
                                    label="Password"
                                    type="password"
                                    autoComplete="new-password"
                                    maxLength={500}
                                    placeholder={s.has_mail_password && !d.remove_mail_password ? '•••••••• (saved)' : ''}
                                    help={s.has_mail_password ? 'Leave blank to keep the saved password. Stored encrypted.' : 'The email account’s password. Stored encrypted.'}
                                    optional
                                    {...bind(form, 'mail_password')}
                                />
                            </div>
                            {s.has_mail_password && !d.mail_password && (
                                <Toggle label="Remove the saved password" checked={d.remove_mail_password} onChange={(v) => form.setData('remove_mail_password', v)} />
                            )}
                        </div>
                    </Card>
                )}

                {d.mail_mailer !== 'log' && (
                    <Card title="Sender" description="Who your emails come from.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextInput label="From email" type="email" maxLength={160} placeholder={s.email || 'orders@yourdomain.com'} help={d.mail_mailer === 'smtp' ? 'Use the same address as the SMTP account, or it may be refused or marked as spam.' : s.email ? `Leave blank to use your business email (${s.email}).` : 'An address on your own domain works best.'} optional {...bind(form, 'mail_from_address')} />
                            <TextInput label="From name" maxLength={120} placeholder={s.business_name} help="Leave blank to use your business name." optional {...bind(form, 'mail_from_name')} />
                        </div>
                    </Card>
                )}

                <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                    <Button loading={form.processing}>Save email settings</Button>
                </FormActions>
            </form>

            <Card title="Send a test email" description="Uses the saved settings. Save your changes first.">
                <div className="space-y-4">
                    <p className={`rounded-lg px-3 py-2 text-sm ${mail.delivers ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'}`}>
                        {mail.delivers ? (
                            <>
                                Sending {mail.from ? <>from <strong>{mail.from}</strong> </> : ''}via <strong>{mail.mailer === 'sendmail' ? 'PHP mail' : mail.mailer.toUpperCase()}</strong>
                                {mail.source === 'server' ? ' (server .env settings)' : ''}.
                            </>
                        ) : (
                            'Email sending is off: emails are only written to the log file.'
                        )}
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <TextInput label="Send to" type="email" className="min-w-0 flex-1" value={testTo} onChange={(e) => setTestTo(e.target.value)} />
                        <Button type="button" variant="secondary" loading={testing} disabled={!testTo || form.isDirty} onClick={sendTest}>
                            Send test email
                        </Button>
                    </div>
                    {form.isDirty && <p className="text-xs text-zinc-500">Save your changes to test them.</p>}
                </div>
            </Card>
        </div>
    );
}

function Shipping({ s, countries, shipTo, zones }) {
    const form = useForm({
        default_country: s.default_country || 'NG',
        ship_to: s.ship_to || 'specific',
        ship_to_countries: s.ship_to_countries ?? [],
        ship_except_countries: s.ship_except_countries ?? [],
    });
    const d = form.data;
    const countryOptions = countries.map((c) => ({ value: c.code, label: c.name }));
    const regular = zones.filter((z) => !z.is_fallback);
    const fallback = zones.find((z) => z.is_fallback);
    const [deleting, setDeleting] = useState(null);

    const move = (index, delta) => {
        const ids = regular.map((z) => z.id);
        const [id] = ids.splice(index, 1);
        ids.splice(index + delta, 0, id);
        router.post('/admin/shipping-zones/reorder', { ids }, { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/admin/settings/shipping', { preserveScroll: true });
                }}
            >
                <Card title="Shipping locations" description="Which countries customers can choose at checkout.">
                    <div className="space-y-5">
                        <Select label="Store country" help="Pre-selected at checkout, and used for the free-delivery hint in the cart." options={countries.map((c) => ({ value: c.code, label: c.name }))} {...bind(form, 'default_country')} />
                        <div>
                            <p className="mb-2 text-sm font-medium text-zinc-800">Sell and ship to</p>
                            <div className="flex flex-wrap gap-2">
                                {Object.entries(shipTo).map(([value, label]) => (
                                    <button key={value} type="button" onClick={() => form.setData('ship_to', value)} aria-pressed={d.ship_to === value} className={`rounded-lg border px-3 py-1.5 text-sm font-medium ${d.ship_to === value ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50'}`}>
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {d.ship_to === 'specific' && <MultiSelect label="Countries" options={countryOptions} value={d.ship_to_countries} onChange={(v) => form.setData('ship_to_countries', v)} error={form.errors.ship_to_countries} help="Only these countries appear in the checkout country list." />}
                        {d.ship_to === 'all_except' && <MultiSelect label="Except these countries" options={countryOptions} value={d.ship_except_countries} onChange={(v) => form.setData('ship_except_countries', v)} error={form.errors.ship_except_countries} />}
                    </div>
                    <SaveBar form={form} />
                </Card>
            </form>

            <Card
                title="Shipping zones"
                description="A zone is a group of places with the same delivery options. Customers get the first zone that matches their address, from top to bottom."
                actions={
                    <Button href="/admin/shipping-zones/create" className="shrink-0 whitespace-nowrap">
                        <Plus size={16} /> Add zone
                    </Button>
                }
                padded={false}
            >
                <ul className="divide-y divide-zinc-100">
                    {regular.length === 0 && <li className="px-5 py-6 text-center text-sm text-zinc-500">No zones yet. Add one for each area you deliver to, e.g. “Lagos” (Lagos state) and “Rest of Nigeria” (Nigeria).</li>}
                    {regular.map((z, i) => (
                        <li key={z.id} className="flex items-start gap-3 px-5 py-4">
                            <div className="flex flex-col">
                                <button type="button" onClick={() => move(i, -1)} disabled={i === 0} className="rounded p-0.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-30" aria-label={`Move ${z.name} up`}>
                                    <ChevronUp size={16} />
                                </button>
                                <button type="button" onClick={() => move(i, 1)} disabled={i === regular.length - 1} className="rounded p-0.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-30" aria-label={`Move ${z.name} down`}>
                                    <ChevronDown size={16} />
                                </button>
                            </div>
                            <ZoneSummary zone={z} />
                            <div className="flex shrink-0 items-center gap-1">
                                <Link href={`/admin/shipping-zones/${z.id}/edit`} className="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" aria-label={`Edit ${z.name}`}>
                                    <Pencil size={16} />
                                </Link>
                                <button type="button" onClick={() => setDeleting(z)} className="rounded-lg p-2 text-zinc-500 hover:bg-red-50 hover:text-red-600" aria-label={`Delete ${z.name}`}>
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        </li>
                    ))}
                    {fallback && (
                        <li className="flex items-start gap-3 bg-zinc-50/60 px-5 py-4">
                            <span className="w-[22px]" />
                            <ZoneSummary zone={fallback} />
                            <Link href={`/admin/shipping-zones/${fallback.id}/edit`} className="shrink-0 rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" aria-label="Edit methods for other locations">
                                <Pencil size={16} />
                            </Link>
                        </li>
                    )}
                </ul>
            </Card>

            <ConfirmModal
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => router.delete(`/admin/shipping-zones/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                title="Delete shipping zone?"
                message={
                    <>
                        <strong className="text-zinc-900">{deleting?.name}</strong> and its shipping methods will be deleted. Addresses in it will use the next matching zone. Past orders are not affected.
                    </>
                }
            />
        </div>
    );
}

function ZoneSummary({ zone }) {
    return (
        <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-zinc-900">{zone.name}</p>
            <p className="mt-0.5 text-xs text-zinc-500">{zone.regions}</p>
            <div className="mt-2 flex flex-wrap gap-1.5">
                {zone.methods.length === 0 && <span className="text-xs text-amber-700">No shipping methods: customers here can’t check out.</span>}
                {zone.methods.map((m, i) => (
                    <span key={i} className={`rounded-md px-2 py-0.5 text-xs ${m.enabled ? 'bg-zinc-100 text-zinc-700' : 'bg-zinc-50 text-zinc-400 line-through'}`} title={m.summary}>
                        {m.title} <span className="text-zinc-400">· {m.summary.replace(/^(Flat rate|Free shipping|Local pickup)\s?·?\s?/, '') || m.summary}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}

function Social({ socials }) {
    const form = useForm({ links: Object.fromEntries(socials.map((s) => [s.platform, s.url])) });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/social', { preserveScroll: true });
            }}
        >
            <Card title="Social media" description="Only platforms with a link are shown on your website. Leave blank to hide.">
                <div className="space-y-4">
                    {socials.map((s) => {
                        const Icon = SOCIAL_ICONS[s.platform];
                        return (
                            <div key={s.platform} className="flex items-start gap-3">
                                <span className="mt-7 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700">{Icon && <Icon size={18} />}</span>
                                <TextInput
                                    className="flex-1"
                                    label={s.label}
                                    type="url"
                                    placeholder={`https://${s.platform === 'x' ? 'x' : s.platform}.com/yourpage`}
                                    value={form.data.links[s.platform]}
                                    onChange={(e) => form.setData('links', { ...form.data.links, [s.platform]: e.target.value })}
                                    error={form.errors[`links.${s.platform}`]}
                                />
                            </div>
                        );
                    })}
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

/** Live Google-style preview of the homepage result. */
function SearchPreview({ title, description, url }) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <p className="text-xs text-zinc-500">Google preview</p>
            <p className="mt-2 truncate text-xs text-zinc-600">{url}</p>
            <p className="mt-0.5 truncate text-lg leading-snug text-[#1a0dab]">{title}</p>
            <p className="mt-1 line-clamp-2 text-sm text-zinc-600">{description}</p>
        </div>
    );
}

const VERIFY = [
    ['verify_google', 'Google Search Console', 'google-site-verification'],
    ['verify_bing', 'Bing Webmaster Tools', 'msvalidate.01'],
    ['verify_yandex', 'Yandex Webmaster', 'yandex-verification'],
    ['verify_pinterest', 'Pinterest', 'p:domain_verify'],
    ['verify_facebook', 'Facebook / Meta domain', 'facebook-domain-verification'],
];

function Seo({ s, separators, isProduction }) {
    const form = useForm({
        seo_title: s.seo_title ?? '',
        seo_description: s.seo_description ?? '',
        og_image: null,
        remove_og_image: false,
        search_engines_visible: s.search_engines_visible,
        title_separator: s.title_separator || '|',
        twitter_handle: s.twitter_handle ? `@${s.twitter_handle}` : '',
        robots_extra: s.robots_extra ?? '',
        ...Object.fromEntries(VERIFY.map(([k]) => [k, s[k] ?? ''])),
        custom_meta: (s.custom_meta ?? []).map((m, i) => ({ ...m, _key: i })),
    });
    const d = form.data;
    const setMeta = (i, changes) => form.setData('custom_meta', d.custom_meta.map((m, j) => (j === i ? { ...m, ...changes } : m)));
    const siteTitle = d.seo_title || s.business_name;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                submitWithFiles(form, 'seo');
            }}
            className="space-y-6"
        >
            <Card title="Search appearance" description="How your website appears in Google and when links are shared.">
                <div className="space-y-5">
                    <Toggle
                        label="Allow search engines to index this website"
                        description={isProduction ? 'Turn off while building the site or for a private store. Every page then tells Google not to list it.' : 'This copy is not in production mode, so search engines are always blocked (robots.txt). The setting applies on the live site.'}
                        checked={d.search_engines_visible}
                        onChange={(v) => form.setData('search_engines_visible', v)}
                    />
                    {!d.search_engines_visible && <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Search engines are discouraged: your pages won’t appear in Google.</p>}
                    <TextInput label="Homepage title" maxLength={160} help="Leave blank to use your business name. About 50–60 characters works best." optional {...bind(form, 'seo_title')} />
                    <Textarea label="Meta description" rows={3} maxLength={320} help="About 150–160 characters. Used for the homepage and any page without its own description." optional {...bind(form, 'seo_description')} />
                    <SearchPreview title={siteTitle} description={d.seo_description || s.business_description || 'Add a meta description to control this text.'} url={window.location.origin} />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Select label="Title separator" help={`Product pages read “Product name ${d.title_separator} ${s.business_name}”.`} options={separators.map((x) => ({ value: x, label: x }))} {...bind(form, 'title_separator')} />
                        <TextInput label="X (Twitter) username" placeholder="@yourstore" maxLength={16} optional help="Shown on link previews shared on X." {...bind(form, 'twitter_handle')} />
                    </div>
                    <ImageField label="Social sharing image" help="Shown when your website is shared on WhatsApp, Facebook or X. Recommended 1200 × 630 px." aspect="aspect-[1.91/1]" currentUrl={s.og_image_url} file={form.data.og_image} onFile={(f) => form.setData('og_image', f)} removed={form.data.remove_og_image} onRemove={(v) => form.setData('remove_og_image', v)} error={form.errors.og_image} />
                    <p className="text-xs text-zinc-500">
                        Products, categories and pages have their own SEO title and description fields. Your sitemap is at{' '}
                        <a href="/sitemap.xml" target="_blank" rel="noopener noreferrer" className="font-medium underline">
                            /sitemap.xml
                        </a>{' '}
                        (submit it in Google Search Console) and robots rules at{' '}
                        <a href="/robots.txt" target="_blank" rel="noopener noreferrer" className="font-medium underline">
                            /robots.txt
                        </a>
                        .
                    </p>
                </div>
            </Card>

            <Card title="Site verification" description="Prove you own the website to search engines and ad platforms. Paste the code, or the whole meta tag they give you.">
                <div className="grid gap-5 sm:grid-cols-2">
                    {VERIFY.map(([key, label, tag]) => (
                        <TextInput key={key} label={label} placeholder={`content of ${tag}`} maxLength={400} optional {...bind(form, key)} />
                    ))}
                </div>
            </Card>

            <Card title="Custom meta tags" description="Extra <meta> tags added to every page, e.g. for other verification services or app links.">
                <div className="space-y-3">
                    {d.custom_meta.length === 0 && <p className="text-sm text-zinc-500">No custom meta tags.</p>}
                    {d.custom_meta.map((m, i) => (
                        <div key={m._key} className="grid grid-cols-[7.5rem_1fr_auto] items-start gap-2 sm:grid-cols-[8.5rem_1fr_1.5fr_auto]">
                            <select className="input" value={m.attribute} onChange={(e) => setMeta(i, { attribute: e.target.value })} aria-label="Attribute">
                                <option value="name">name</option>
                                <option value="property">property</option>
                                <option value="http-equiv">http-equiv</option>
                            </select>
                            <TextInput aria-label="Tag name" placeholder="e.g. author" value={m.key} onChange={(e) => setMeta(i, { key: e.target.value })} error={form.errors[`custom_meta.${i}.key`]} />
                            <TextInput className="col-span-3 row-start-2 sm:col-span-1 sm:row-start-auto" aria-label="Content" placeholder="content" value={m.content} onChange={(e) => setMeta(i, { content: e.target.value })} error={form.errors[`custom_meta.${i}.content`]} />
                            <button type="button" onClick={() => form.setData('custom_meta', d.custom_meta.filter((_, j) => j !== i))} className="flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600" aria-label="Remove meta tag">
                                <Trash2 size={16} />
                            </button>
                        </div>
                    ))}
                    <button type="button" onClick={() => form.setData('custom_meta', [...d.custom_meta, { attribute: 'name', key: '', content: '', _key: Date.now() }])} className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50">
                        <Plus size={16} /> Add meta tag
                    </button>
                </div>
            </Card>

            <Card title="robots.txt" description="Admin, cart, checkout and order pages are always blocked, and your sitemap is listed automatically.">
                <Textarea label="Extra rules" rows={4} maxLength={2000} optional placeholder={'User-agent: GPTBot\nDisallow: /'} help="Added to robots.txt as written, one rule per line." {...bind(form, 'robots_extra')} />
            </Card>

            <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                <Button loading={form.processing}>Save SEO settings</Button>
            </FormActions>
        </form>
    );
}

const PIXELS = [
    ['ga4_id', 'Google Analytics 4', 'Measurement ID', 'G-XXXXXXXXXX', 'Admin → Data streams → your web stream.'],
    ['gtm_id', 'Google Tag Manager', 'Container ID', 'GTM-XXXXXXX', 'Use instead of (not as well as) the GA4 and Ads fields if you manage tags in GTM.'],
    ['google_ads_id', 'Google Ads', 'Google tag ID', 'AW-123456789', 'Enables remarketing and conversion tracking.'],
    ['google_ads_purchase_label', 'Google Ads purchase conversion', 'Conversion label', 'AbC-D_efG123', 'From the conversion action’s tag setup: the part after the slash in send_to.'],
    ['meta_pixel_id', 'Meta (Facebook & Instagram) Pixel', 'Pixel ID', '123456789012345', 'Events Manager → Data sources → your pixel.'],
    ['tiktok_pixel_id', 'TikTok Pixel', 'Pixel ID', 'C4ABCDEFGH1234567890', 'TikTok Ads Manager → Assets → Events.'],
];

function Tracking({ s, timezones }) {
    const form = useForm({ ...Object.fromEntries(PIXELS.map(([k]) => [k, s[k] ?? ''])), track_admins: s.track_admins, stats_enabled: s.stats_enabled, timezone: s.timezone || 'Africa/Lagos' });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/tracking', { preserveScroll: true });
            }}
        >
            <Card title="Built-in visitor statistics" description="Your own analytics in Admin → Analytics: visitors, countries, pages, traffic sources, devices and shop performance. First-party, no third-party scripts." className="mb-6">
                <div className="space-y-5">
                    <Toggle label="Collect visitor statistics" description="Bots, crawlers and automated browsers are filtered out. IP addresses are never stored." checked={form.data.stats_enabled} onChange={(v) => form.setData('stats_enabled', v)} />
                    <Select label="Store timezone" help="Days and hours in Analytics follow this timezone." options={(timezones ?? [form.data.timezone]).map((t) => ({ value: t, label: t.replaceAll('_', ' ') }))} {...bind(form, 'timezone')} />
                </div>
            </Card>

            <Card title="Analytics & ad pixels" description="Paste only the ID. Page views and shop events are sent automatically: view product, add to cart, begin checkout, search and purchase (when an order is placed).">
                <div className="space-y-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        {PIXELS.map(([key, label, field, placeholder, help]) => (
                            <TextInput key={key} label={`${label} · ${field}`} placeholder={placeholder} maxLength={80} optional help={help} {...bind(form, key)} />
                        ))}
                    </div>
                    <Toggle label="Track my own visits" description="Off: nothing is tracked while you are logged in to this admin (pixels or built-in statistics), so your testing doesn’t skew reports." checked={form.data.track_admins} onChange={(v) => form.setData('track_admins', v)} />
                    <p className="rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600">Depending on where your customers are, privacy laws (such as GDPR or Nigeria’s NDPA) may require a cookie consent notice before tracking. You can add a consent tool in Custom code.</p>
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

function CustomCode({ s }) {
    const form = useForm({ head_code: s.head_code ?? '', body_code: s.body_code ?? '', footer_code: s.footer_code ?? '' });
    const area = (key, label, help) => (
        <Field label={label} help={help} error={form.errors[key]} optional>
            <textarea rows={6} spellCheck={false} className={`input font-mono text-xs leading-relaxed ${form.errors[key] ? 'input-error' : ''}`} placeholder="<!-- paste code here -->" value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
        </Field>
    );
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/code', { preserveScroll: true });
            }}
        >
            <Card title="Custom code" description="Add scripts and markup to public storefront pages. Checkout, payments, private orders and admin pages are excluded.">
                <div className="space-y-5">
                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Only paste code from sources you trust. Broken code can break your website’s layout or behaviour; remove it here to undo.</p>
                    {area('head_code', 'Header code (inside <head>)', 'For <meta>, <link>, <style> and most tracking scripts.')}
                    {area('body_code', 'Body code (right after <body>)', 'For <noscript> fallbacks some tag managers ask for.')}
                    {area('footer_code', 'Footer code (before </body>)', 'For chat widgets and scripts that should load last.')}
                </div>
                <SaveBar form={form} />
            </Card>
        </form>
    );
}

function Redirects({ redirects }) {
    const form = useForm({ from_path: '', to_path: '', status_code: 301 });
    const [deleting, setDeleting] = useState(null);
    return (
        <div className="space-y-6">
            <Card title="Add a redirect" description="Send visitors and search engines from an old address to a new one, e.g. after renaming or removing a page. Product and category address changes are redirected automatically.">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/redirects', { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                    className="grid items-start gap-3 sm:grid-cols-[1fr_1fr_11rem_auto]"
                >
                    <TextInput label="Old address" placeholder="/old-page" {...bind(form, 'from_path')} />
                    <TextInput label="New address" placeholder="/products/new-page or https://…" {...bind(form, 'to_path')} />
                    <Select label="Type" options={[{ value: 301, label: 'Permanent (301)' }, { value: 302, label: 'Temporary (302)' }]} {...bind(form, 'status_code')} />
                    <Button loading={form.processing} className="sm:mt-7">
                        Add
                    </Button>
                </form>
            </Card>

            <Card title={`Redirects (${redirects.length})`} padded={false}>
                {redirects.length === 0 ? (
                    <p className="px-5 py-6 text-center text-sm text-zinc-500">No redirects yet.</p>
                ) : (
                    <ul className="divide-y divide-zinc-100">
                        {redirects.map((r) => (
                            <li key={r.id} className="flex items-center gap-3 px-5 py-3">
                                <div className="min-w-0 flex-1 text-sm">
                                    <p className="truncate">
                                        <span className="font-mono text-zinc-900">{r.from_path}</span>
                                        <span className="mx-2 text-zinc-400">→</span>
                                        <span className="font-mono text-zinc-600">{r.to_path}</span>
                                    </p>
                                    <p className="mt-0.5 text-xs text-zinc-500">
                                        {r.status_code} · {r.is_automatic ? 'Automatic (address changed)' : 'Added manually'} · {r.hits} {r.hits === 1 ? 'visit' : 'visits'}
                                        {r.last_hit ? `, last ${r.last_hit}` : ''}
                                    </p>
                                </div>
                                <button type="button" onClick={() => setDeleting(r)} className="rounded-lg p-2 text-zinc-500 hover:bg-red-50 hover:text-red-600" aria-label={`Remove redirect from ${r.from_path}`}>
                                    <Trash2 size={16} />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <ConfirmModal
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => router.delete(`/admin/redirects/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                title="Remove redirect?"
                confirmLabel="Remove"
                message={
                    <>
                        Visitors to <strong className="font-mono text-zinc-900">{deleting?.from_path}</strong> will no longer be redirected and may see a “page not found” error.
                    </>
                }
            />
        </div>
    );
}

export default function SettingsEdit({ tab, tabChosen, settings, paystack, socials, countries, shipTo, shippingZones, hasWhatsapp, titleSeparators, timezones, redirects, appIsProduction, mail, mailers, mailEncryptions, serverMailer, adminEmail, fonts, cardStyles, cardShapes, cardCorners, sampleProduct, currencyPresets, pricePreview, whatsappPreview }) {
    const choose = (key) => router.get('/admin/settings', { tab: key }, { preserveScroll: true, preserveState: false, replace: true });

    const panels = {
        business: <Business s={settings} />,
        branding: <Branding s={settings} fonts={fonts} cardStyles={cardStyles} cardShapes={cardShapes} cardCorners={cardCorners} sampleProduct={sampleProduct} />,
        currency: <Currency s={settings} presets={currencyPresets} pricePreview={pricePreview} />,
        whatsapp: <WhatsApp s={settings} whatsappPreview={whatsappPreview} />,
        shop: <Shop s={settings} hasWhatsapp={hasWhatsapp} mail={mail} paystack={paystack} />,
        email: <Email s={settings} mail={mail} mailers={mailers} encryptions={mailEncryptions} serverMailer={serverMailer} adminEmail={adminEmail} />,
        shipping: <Shipping s={settings} countries={countries} shipTo={shipTo} zones={shippingZones} />,
        social: <Social socials={socials} />,
        seo: <Seo s={settings} separators={titleSeparators} isProduction={appIsProduction} />,
        tracking: <Tracking s={settings} timezones={timezones} />,
        code: <CustomCode s={settings} />,
        redirects: <Redirects redirects={redirects} />,
    };

    return (
        <AdminLayout title={tabChosen ? tabLabel(tab) : 'Website Settings'}>
            {/* Phones & tablets: a settings menu first; each section opens as its own screen. */}
            {tabChosen ? (
                <div className="lg:hidden">
                    <PageHeader title={tabLabel(tab)} back={{ href: '/admin/settings', label: 'Settings' }} />
                </div>
            ) : (
                <div className="lg:hidden">
                    <PageHeader title="Settings" description="Your business details, branding, checkout, shipping, SEO and tracking." />
                    <SettingsMenu />
                </div>
            )}
            <div className="hidden lg:block">
                <PageHeader title="Website Settings" description="Your business details, branding, checkout, email, shipping, SEO and tracking settings." />
            </div>
            <div className={`grid gap-6 lg:grid-cols-[220px_1fr] ${tabChosen ? '' : 'max-lg:hidden'}`}>
                <div className="hidden lg:block">
                    <nav className="flex flex-col gap-0.5" aria-label="Settings sections">
                        {TABS.map(([k, l]) => (
                            <button key={k} type="button" onClick={() => choose(k)} className={`rounded-lg px-3 py-2 text-left text-sm font-medium ${tab === k ? 'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' : 'text-zinc-600 hover:text-zinc-900'}`}>
                                {l}
                            </button>
                        ))}
                    </nav>
                </div>
                <div key={tab}>{panels[tab]}</div>
            </div>
        </AdminLayout>
    );
}

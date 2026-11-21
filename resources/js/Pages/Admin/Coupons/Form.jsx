import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Shuffle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CouponStatusBadge, OrderStatusBadge } from '@/Components/Admin/OrderBadges';
import MultiSelect from '@/Components/Admin/MultiSelect';
import { Button, Card, ConfirmModal, FormActions, PageHeader, Select, TextInput, Textarea, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

// No 0/O or 1/I, so codes are easy to read out and type.
const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const randomCode = () => Array.from(crypto.getRandomValues(new Uint32Array(8)), (n) => CODE_CHARS[n % CODE_CHARS.length]).join('');

const AMOUNT_HELP = {
    percent: 'Percentage off each eligible item, e.g. 10 for 10% off.',
    fixed_cart: 'Amount off the whole order (only the eligible items).',
    fixed_product: 'Amount off every eligible item in the cart (per unit).',
};

export default function CouponForm({ coupon, types, products, categories, currencySymbol, freeShippingReady }) {
    const editing = !!coupon.id;
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const form = useForm({
        code: coupon.code ?? '',
        description: coupon.description ?? '',
        discount_type: coupon.discount_type ?? 'percent',
        amount: coupon.amount ?? '',
        free_shipping: coupon.free_shipping ?? false,
        is_active: coupon.is_active ?? true,
        starts_on: coupon.starts_on ?? '',
        expires_on: coupon.expires_on ?? '',
        minimum_spend: coupon.minimum_spend ?? '',
        maximum_spend: coupon.maximum_spend ?? '',
        exclude_sale_items: coupon.exclude_sale_items ?? false,
        product_ids: coupon.product_ids ?? [],
        excluded_product_ids: coupon.excluded_product_ids ?? [],
        category_ids: coupon.category_ids ?? [],
        excluded_category_ids: coupon.excluded_category_ids ?? [],
        allowed_emails: coupon.allowed_emails ?? '',
        usage_limit: coupon.usage_limit ?? '',
        usage_limit_per_customer: coupon.usage_limit_per_customer ?? '',
        limit_usage_to_items: coupon.limit_usage_to_items ?? '',
    });
    const d = form.data;
    const err = form.errors;
    const field = (key) => ({ value: d[key], onChange: (e) => form.setData(key, e.target.value), error: err[key] });
    const money = currencySymbol.length <= 2 ? currencySymbol : undefined;
    const percent = d.discount_type === 'percent';

    const submit = (e) => {
        e.preventDefault();
        // Empty strings mean "no limit"; send them as null.
        form.transform((data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])));
        if (editing) form.put(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
        else form.post('/admin/coupons');
    };

    const destroy = () =>
        router.delete(`/admin/coupons/${coupon.id}`, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });

    return (
        <AdminLayout title={editing ? `Coupon ${coupon.code}` : 'Add coupon'}>
            <PageHeader
                back={{ href: '/admin/coupons', label: 'Coupons' }}
                title={editing ? coupon.code : 'Add coupon'}
                actions={
                    editing && (
                        <Button type="button" variant="secondary" onClick={() => setDeleting(true)}>
                            <Trash2 size={16} /> Delete
                        </Button>
                    )
                }
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Coupon">
                        <div className="space-y-5">
                            <div>
                                <div className="flex items-end gap-2">
                                    <TextInput
                                    label="Coupon code"
                                    className="flex-1"
                                    value={d.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase().replace(/\s+/g, ''))}
                                    maxLength={40}
                                    autoCapitalize="characters"
                                    spellCheck={false}
                                    required
                                    />
                                    <Button type="button" variant="secondary" onClick={() => form.setData('code', randomCode())} title="Generate a random code">
                                        <Shuffle size={16} /> <span className="hidden sm:inline">Generate</span>
                                    </Button>
                                </div>
                                {err.code ? <p className="mt-1.5 text-xs font-medium text-red-600">{err.code}</p> : <p className="mt-1.5 text-xs text-zinc-500">What customers type. Letters, numbers, dashes and underscores; not case-sensitive.</p>}
                            </div>
                            <TextInput label="Description" optional maxLength={255} placeholder="For your own reference, e.g. “Instagram giveaway”" {...field('description')} />
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Select label="Discount type" options={types} {...field('discount_type')} />
                                <TextInput
                                    label={percent ? 'Percentage off' : 'Amount off'}
                                    type="number"
                                    min="0"
                                    max={percent ? 100 : undefined}
                                    step="any"
                                    inputMode="decimal"
                                    prefix={percent ? undefined : money}
                                    placeholder="0"
                                    help={AMOUNT_HELP[d.discount_type]}
                                    {...field('amount')}
                                />
                            </div>
                            <Toggle label="Allow free shipping" description="Unlocks free shipping methods set to need “a valid free shipping coupon” (Website Settings → Shipping)." checked={d.free_shipping} onChange={(v) => form.setData('free_shipping', v)} />
                            {d.free_shipping && !freeShippingReady && (
                                <p className="flex items-start gap-2 rounded-xl bg-amber-50 px-3.5 py-3 text-sm text-amber-900">
                                    <AlertTriangle size={17} className="mt-0.5 shrink-0" />
                                    <span>
                                        None of your shipping zones has a free shipping method that accepts coupons yet. Add one in{' '}
                                        <Link href="/admin/settings?tab=shipping" className="font-semibold underline">
                                            Website Settings → Shipping
                                        </Link>{' '}
                                        and set “Free shipping requires” to “A valid free shipping coupon”.
                                    </span>
                                </p>
                            )}
                            <div className="grid gap-5 sm:grid-cols-2">
                                <TextInput label="Starts on" type="date" optional help="Leave blank to start now." {...field('starts_on')} />
                                <TextInput label="Expires on" type="date" optional help="Last day it can be used." min={d.starts_on || undefined} {...field('expires_on')} />
                            </div>
                        </div>
                    </Card>

                    <Card title="Usage restrictions" description="Optional. Leave blank for a coupon that works on any order.">
                        <div className="space-y-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <TextInput label="Minimum spend" type="number" min="0" step="any" inputMode="decimal" prefix={money} optional placeholder="No minimum" help="Cart subtotal before discounts." {...field('minimum_spend')} />
                                <TextInput label="Maximum spend" type="number" min="0" step="any" inputMode="decimal" prefix={money} optional placeholder="No maximum" {...field('maximum_spend')} />
                            </div>
                            <Toggle label="Exclude sale items" description="Products with a “compare at” price don’t get the discount." checked={d.exclude_sale_items} onChange={(v) => form.setData('exclude_sale_items', v)} />
                            <MultiSelect label="Products" help="Only these products get the discount. Leave empty for all." options={products} value={d.product_ids} onChange={(v) => form.setData('product_ids', v)} error={err.product_ids} placeholder="Search products…" />
                            <MultiSelect label="Exclude products" options={products} value={d.excluded_product_ids} onChange={(v) => form.setData('excluded_product_ids', v)} error={err.excluded_product_ids} placeholder="Search products…" />
                            <MultiSelect label="Categories" help="Only products in these categories get the discount." options={categories} value={d.category_ids} onChange={(v) => form.setData('category_ids', v)} error={err.category_ids} placeholder="Search categories…" />
                            <MultiSelect label="Exclude categories" options={categories} value={d.excluded_category_ids} onChange={(v) => form.setData('excluded_category_ids', v)} error={err.excluded_category_ids} placeholder="Search categories…" />
                            <Textarea label="Allowed emails" optional rows={2} maxLength={5000} placeholder="ada@example.com, *@company.com" help="Only customers who check out with one of these emails can use it. Separate with commas; * is a wildcard." {...field('allowed_emails')} />
                        </div>
                    </Card>

                    <Card title="Usage limits" description="Uses are counted from orders; cancelling an order gives its use back.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextInput label="Total uses" type="number" min="1" step="1" inputMode="numeric" optional placeholder="Unlimited" help="How many orders can use this coupon." {...field('usage_limit')} />
                            <TextInput label="Uses per customer" type="number" min="1" step="1" inputMode="numeric" optional placeholder="Unlimited" help="Customers are matched by email or phone number." {...field('usage_limit_per_customer')} />
                            {d.discount_type !== 'fixed_cart' && <TextInput label="Limit to items" type="number" min="1" step="1" inputMode="numeric" optional placeholder="All eligible items" help="Most items the discount covers per order (most expensive first)." {...field('limit_usage_to_items')} />}
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Status">
                        <div className="space-y-4">
                            {editing && (
                                <div className="flex items-center gap-2 text-sm text-zinc-600">
                                    Now: <CouponStatusBadge status={coupon.status} label={coupon.status_label} />
                                </div>
                            )}
                            <Toggle label="Coupon is on" description="Switch off to pause it without deleting it." checked={d.is_active} onChange={(v) => form.setData('is_active', v)} />
                        </div>
                    </Card>

                    {editing && (
                        <Card title="Performance" padded={false}>
                            <dl className="grid grid-cols-2 divide-x divide-zinc-100 border-b border-zinc-100">
                                <div className="p-4">
                                    <dt className="text-xs text-zinc-500">Orders</dt>
                                    <dd className="mt-0.5 text-xl font-semibold text-zinc-900">
                                        {coupon.used}
                                        {coupon.usage_limit ? <span className="text-sm font-normal text-zinc-500"> / {coupon.usage_limit}</span> : null}
                                    </dd>
                                </div>
                                <div className="p-4">
                                    <dt className="text-xs text-zinc-500">Discount given</dt>
                                    <dd className="mt-0.5 text-xl font-semibold text-zinc-900">{coupon.discount_given}</dd>
                                </div>
                            </dl>
                            {coupon.orders.length > 0 ? (
                                <ul className="divide-y divide-zinc-100">
                                    {coupon.orders.map((o) => (
                                        <li key={o.id}>
                                            <Link href={`/admin/orders/${o.id}`} className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-zinc-50">
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium text-zinc-900">
                                                        #{o.number} · {o.customer_name}
                                                    </span>
                                                    <span className="text-xs text-zinc-500">
                                                        {o.created_at} · −{o.discount}
                                                    </span>
                                                </span>
                                                <OrderStatusBadge status={o.status} label={o.status_label} />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="p-4 text-sm text-zinc-500">No orders have used this coupon yet.</p>
                            )}
                        </Card>
                    )}

                    <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                        <Button type="button" variant="secondary" href="/admin/coupons">
                            Cancel
                        </Button>
                        <Button loading={form.processing}>{editing ? 'Save changes' : 'Create coupon'}</Button>
                    </FormActions>
                </div>
            </form>

            <ConfirmModal
                open={deleting}
                onClose={() => setDeleting(false)}
                onConfirm={destroy}
                processing={processing}
                title="Delete coupon?"
                confirmLabel="Delete coupon"
                message={coupon.used > 0 ? `${coupon.code} will stop working. The ${coupon.used} order${coupon.used === 1 ? '' : 's'} that used it keep their discount. To pause it instead, switch it off.` : `${coupon.code} will be permanently deleted.`}
            />
        </AdminLayout>
    );
}

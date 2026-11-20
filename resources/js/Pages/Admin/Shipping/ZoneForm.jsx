import { router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, MapPin, Package, Plus, Store, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
import MultiSelect from '@/Components/Admin/MultiSelect';
import { Button, Card, ConfirmModal, FormActions, PageHeader, Select, TextInput, Textarea, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const TYPE_ICONS = { flat_rate: Truck, free_shipping: Package, local_pickup: Store };
const DEFAULT_TITLES = { flat_rate: 'Standard delivery', free_shipping: 'Free delivery', local_pickup: 'Pickup in store' };

function MethodEditor({ method, index, count, errors, symbol, onChange, onRemove, onMove }) {
    const [open, setOpen] = useState(!method.id);
    const Icon = TYPE_ICONS[method.type];
    const err = (key) => errors[`methods.${index}.${key}`];
    const hasError = Object.keys(errors).some((k) => k.startsWith(`methods.${index}.`));
    const prefix = symbol.length <= 2 ? symbol : undefined;
    const field = (key) => ({ value: method[key] ?? '', onChange: (e) => onChange({ [key]: e.target.value }), error: err(key) });

    return (
        <li className={`rounded-xl border ${hasError ? 'border-red-300' : 'border-zinc-200'} bg-white`}>
            <div className="flex items-center gap-3 px-4 py-3">
                <div className="flex flex-col">
                    <button type="button" onClick={() => onMove(-1)} disabled={index === 0} className="rounded p-0.5 text-zinc-400 hover:text-zinc-800 disabled:opacity-30" aria-label="Move up">
                        <ChevronUp size={15} />
                    </button>
                    <button type="button" onClick={() => onMove(1)} disabled={index === count - 1} className="rounded p-0.5 text-zinc-400 hover:text-zinc-800 disabled:opacity-30" aria-label="Move down">
                        <ChevronDown size={15} />
                    </button>
                </div>
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700">
                    <Icon size={17} />
                </span>
                <button type="button" onClick={() => setOpen((o) => !o)} className="min-w-0 flex-1 text-left" aria-expanded={open}>
                    <span className={`block truncate text-sm font-semibold ${method.is_enabled ? 'text-zinc-900' : 'text-zinc-400 line-through'}`}>{method.title || 'Untitled method'}</span>
                    <span className="block text-xs text-zinc-500">
                        {{ flat_rate: 'Flat rate', free_shipping: 'Free shipping', local_pickup: 'Local pickup' }[method.type]}
                        {method.type === 'flat_rate' && method.cost !== '' && ` · ${symbol}${Number(method.cost).toLocaleString()}`}
                        {method.type === 'free_shipping' && method.requires === 'min_amount' && method.min_amount !== '' && ` · over ${symbol}${Number(method.min_amount).toLocaleString()}`}
                    </span>
                </button>
                <span className="hidden text-xs text-zinc-400 sm:inline">{open ? 'Hide' : 'Edit'}</span>
                <button type="button" onClick={onRemove} className="rounded-lg p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600" aria-label={`Remove ${method.title || 'method'}`}>
                    <Trash2 size={16} />
                </button>
            </div>

            {(open || hasError) && (
                <div className="space-y-4 border-t border-zinc-100 px-4 py-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextInput label="Name shown to customers" maxLength={120} {...field('title')} />
                        <div className="sm:pt-7">
                            <Toggle label="Enabled" checked={method.is_enabled} onChange={(v) => onChange({ is_enabled: v })} />
                        </div>
                    </div>

                    {method.type === 'flat_rate' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextInput label="Cost" type="number" min="0" step="any" inputMode="decimal" prefix={prefix} placeholder="0" {...field('cost')} />
                            <TextInput label="Extra cost per item" type="number" min="0" step="any" inputMode="decimal" prefix={prefix} placeholder="0" optional help="Added for every item in the cart." {...field('per_item')} />
                        </div>
                    )}

                    {method.type === 'free_shipping' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Select
                                label="Free shipping requires"
                                options={[
                                    { value: 'none', label: 'Nothing (always free)' },
                                    { value: 'min_amount', label: 'A minimum order amount' },
                                ]}
                                {...field('requires')}
                            />
                            {method.requires === 'min_amount' && <TextInput label="Minimum order amount" type="number" min="0" step="any" inputMode="decimal" prefix={prefix} help="Hidden until the cart subtotal reaches this." {...field('min_amount')} />}
                        </div>
                    )}

                    {method.type === 'local_pickup' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Textarea label="Pickup address" rows={2} maxLength={500} help="Shown at checkout, on the order page and in the email." {...field('address')} />
                            <TextInput label="Cost" type="number" min="0" step="any" inputMode="decimal" prefix={prefix} placeholder="0" optional help="Usually free." {...field('cost')} />
                        </div>
                    )}
                </div>
            )}
        </li>
    );
}

export default function ZoneForm({ zone, regionOptions, methodTypes, currencySymbol }) {
    const editing = !!zone.id;
    const form = useForm({
        name: zone.name,
        regions: zone.regions,
        postcodes: zone.postcodes,
        methods: zone.methods.map((m, i) => ({ ...m, key: m.id ?? `m${i}` })),
    });
    const d = form.data;
    const [showPostcodes, setShowPostcodes] = useState(!!zone.postcodes);
    const [adding, setAdding] = useState('flat_rate');
    const [deleting, setDeleting] = useState(false);

    const setMethod = (i, changes) => form.setData('methods', d.methods.map((m, j) => (j === i ? { ...m, ...changes } : m)));
    const addMethod = () =>
        form.setData('methods', [...d.methods, { id: null, key: `new-${Date.now()}`, type: adding, title: DEFAULT_TITLES[adding], is_enabled: true, cost: adding === 'flat_rate' ? '' : '0', per_item: '', requires: 'none', min_amount: '', address: '' }]);
    const moveMethod = (i, delta) => {
        const list = [...d.methods];
        const [m] = list.splice(i, 1);
        list.splice(i + delta, 0, m);
        form.setData('methods', list);
    };

    const submit = (e) => {
        e.preventDefault();
        const payload = { preserveScroll: true };
        editing ? form.put(`/admin/shipping-zones/${zone.id}`, payload) : form.post('/admin/shipping-zones', payload);
    };

    return (
        <AdminLayout title={editing ? zone.name : 'Add shipping zone'}>
            <PageHeader back={{ href: '/admin/settings?tab=shipping', label: 'Shipping' }} title={editing ? zone.name : 'Add shipping zone'} description={zone.is_fallback ? 'Shipping methods for addresses that don’t match any of your zones.' : 'Where this zone applies and the delivery options offered there.'} />

            <form onSubmit={submit} className="space-y-6">
                {!zone.is_fallback && (
                    <Card title="Zone details">
                        <div className="space-y-5">
                            <TextInput label="Zone name" maxLength={120} placeholder="e.g. Lagos, Rest of Nigeria, West Africa" help="For your reference; customers don’t see it." value={d.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                            <MultiSelect
                                label="Zone regions"
                                options={regionOptions}
                                value={d.regions}
                                onChange={(v) => form.setData('regions', v)}
                                error={form.errors.regions || Object.entries(form.errors).find(([k]) => k.startsWith('regions.'))?.[1]}
                                help="Countries and/or states. A whole country covers all its states; put more specific zones (e.g. Lagos) above broader ones (e.g. Nigeria)."
                                placeholder="Search countries or states, e.g. Nigeria or Lagos"
                            />
                            {showPostcodes ? (
                                <Textarea
                                    label="Limit to specific postcodes"
                                    rows={4}
                                    optional
                                    help="One per line. Use * as a wildcard (100*) and ... for numeric ranges (100001...100099). Customers must then enter a matching postcode."
                                    value={d.postcodes}
                                    onChange={(e) => form.setData('postcodes', e.target.value)}
                                    error={form.errors.postcodes}
                                />
                            ) : (
                                <button type="button" onClick={() => setShowPostcodes(true)} className="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                                    <MapPin size={15} /> Limit to specific postcodes
                                </button>
                            )}
                        </div>
                    </Card>
                )}

                <Card title="Shipping methods" description="Customers in this zone choose one of these at checkout. Free shipping with a minimum only appears once the cart reaches it.">
                    <ul className="space-y-3">
                        {d.methods.length === 0 && <li className="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">No methods yet. Customers in this zone won’t be able to check out until you add one.</li>}
                        {d.methods.map((m, i) => (
                            <MethodEditor key={m.key} method={m} index={i} count={d.methods.length} errors={form.errors} symbol={currencySymbol} onChange={(c) => setMethod(i, c)} onRemove={() => form.setData('methods', d.methods.filter((_, j) => j !== i))} onMove={(delta) => moveMethod(i, delta)} />
                        ))}
                    </ul>
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <select value={adding} onChange={(e) => setAdding(e.target.value)} className="input min-w-0 flex-1 sm:w-auto sm:flex-none" aria-label="Method type to add">
                            {Object.entries(methodTypes).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                        <Button type="button" variant="secondary" onClick={addMethod}>
                            <Plus size={16} /> Add method
                        </Button>
                    </div>
                </Card>

                <FormActions dirty={form.isDirty} className="justify-between lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                    {editing && !zone.is_fallback ? (
                        <Button type="button" variant="ghost" className="text-red-600 hover:bg-red-50 max-lg:flex-none! max-lg:px-3" onClick={() => setDeleting(true)} aria-label="Delete zone">
                            <Trash2 size={16} /> <span className="max-sm:hidden">Delete zone</span>
                        </Button>
                    ) : (
                        <span className="lg:flex-1" />
                    )}
                    <Button loading={form.processing}>{editing ? 'Save zone' : 'Create zone'}</Button>
                </FormActions>
            </form>

            <ConfirmModal open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(`/admin/shipping-zones/${zone.id}`)} title="Delete shipping zone?" message={<>This zone and its shipping methods will be deleted. Past orders are not affected.</>} />
        </AdminLayout>
    );
}

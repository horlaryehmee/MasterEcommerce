import { Link, usePage } from '@inertiajs/react';
import { Eye, Loader2, Phone, ShieldCheck, ShoppingCart, Tag } from 'lucide-react';
import { useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import { AvailabilityLabel, PriceTag, ProductGrid } from '@/Components/Store/ProductCard';
import ProductGallery from '@/Components/Store/ProductGallery';
import QuantityStepper from '@/Components/Store/QuantityStepper';
import ShareButtons from '@/Components/Store/ShareButtons';
import VariantPicker, { useVariantSelection } from '@/Components/Store/VariantPicker';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';
import { useAddToCart } from '@/lib/cart';

const PICKER_ID = 'product-options';

function paymentNote(methods) {
    if (!methods?.length) return '';
    const names = methods.map((m) => m.charAt(0).toLowerCase() + m.slice(1));
    const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} or ${names.at(-1)}` : names[0];
    return `Order online, then ${list}. No card needed.`;
}

/** The WhatsApp enquiry mentions the chosen variation, so the business knows exactly which one. */
function withSelection(url, product, selection) {
    if (!url || !selection.hasVariants) return url;
    const chosen = selection.options.map((o, i) => (selection.selected[i] ? `${o.name}: ${selection.selected[i]}` : null)).filter(Boolean);
    if (!chosen.length) return url;
    try {
        const u = new URL(url);
        u.searchParams.set('text', `${u.searchParams.get('text') || product.name}\n${chosen.join(', ')}`);
        return u.toString();
    } catch {
        return url;
    }
}

export default function ProductShow({ product, whatsappUrl, related, isPreview }) {
    const { store } = usePage().props;
    const selection = useVariantSelection(product);
    const { variant, missing, hasVariants } = selection;
    const [quantity, setQuantity] = useState(1);
    const [nudge, setNudge] = useState(0);
    const { add, busy } = useAddToCart();

    // What the page shows: the chosen variation, or the product (with "From" when variation prices differ).
    const shown = variant
        ? {
              price: variant.price_label,
              compareAt: variant.compare_at_price,
              discount: variant.discount_percent,
              availability: variant.availability,
              label: variant.availability_label,
              purchasable: variant.purchasable,
          }
        : {
              price: product.price,
              compareAt: hasVariants ? null : product.compare_at_price,
              discount: hasVariants ? null : product.discount_percent,
              availability: product.availability,
              label: product.availability_label,
              purchasable: product.purchasable,
          };
    const priceFrom = hasVariants && !variant && product.price_from;

    const shopOn = store?.shop_enabled && !isPreview;
    // A variable product can be bought until the chosen variation turns out to be sold out.
    const canBuy = shopOn && product.purchasable && (!variant || variant.purchasable);
    const soldOut = shown.availability === 'out_of_stock' || (variant && !variant.purchasable);
    const enquiryLabel = soldOut ? 'Ask about availability' : 'Enquire on WhatsApp';
    const enquiryUrl = withSelection(whatsappUrl, product, selection);

    const addToCart = (checkout = false) => {
        if (hasVariants && !variant) {
            // Point at what's missing instead of failing.
            setNudge((n) => n + 1);
            document.getElementById(PICKER_ID)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        add(product.id, {
            variantId: variant?.id ?? null,
            quantity,
            checkout,
            onSuccess: () => !checkout && setQuantity(1),
        });
    };
    const chooseLabel = missing.length ? `Choose ${missing.map((m) => m.toLowerCase()).join(' & ')}` : null;

    const crumbs = [
        { label: 'Home', href: '/' },
        { label: 'Products', href: '/products' },
    ];
    if (product.category)
        crumbs.push({
            label: product.category.name,
            href: product.category.url,
        });
    crumbs.push({ label: product.name });

    return (
        <StoreLayout hideFloatingWhatsApp hideBottomNav>
            {isPreview && (
                <div className="bg-amber-100 text-amber-900">
                    <div className="container-page flex items-center gap-2 py-2.5 text-sm font-medium">
                        <Eye size={16} /> Preview — this product is not visible to customers (draft or hidden category).
                    </div>
                </div>
            )}

            <div className="container-page pt-6 pb-28 sm:pt-8 lg:pb-16">
                <Breadcrumbs items={crumbs} />

                <div className="mt-6 grid gap-8 lg:mt-8 lg:grid-cols-2 lg:gap-14">
                    <ProductGallery images={product.images} name={product.name} activeIndex={selection.imageIndex} />

                    <div className="lg:sticky lg:top-28 lg:self-start">
                        {product.category && (
                            <Link href={product.category.url} className="text-xs font-semibold tracking-wider text-stone-500 uppercase hover:text-stone-900">
                                {product.category.name}
                            </Link>
                        )}
                        <h1 className="mt-2 text-2xl leading-tight font-bold tracking-tight text-balance text-stone-950 sm:text-3xl lg:text-4xl">{product.name}</h1>

                        <div className="mt-5 flex flex-wrap items-center gap-3" aria-live="polite">
                            <PriceTag price={shown.price} compareAt={shown.compareAt} from={priceFrom} size="lg" />
                            {shown.discount > 0 && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-3 py-1 text-sm font-semibold text-accent">
                                    <Tag size={14} /> Save {shown.discount}%
                                </span>
                            )}
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <AvailabilityLabel
                                value={variant && !variant.purchasable && variant.availability !== 'on_request' ? 'out_of_stock' : shown.availability}
                                label={shown.label}
                                className="rounded-full bg-stone-100 px-3 py-1.5 text-sm"
                            />
                            {variant?.sku && <span className="text-xs text-stone-400">SKU {variant.sku}</span>}
                        </div>

                        {product.short_description && <p className="mt-6 text-base leading-relaxed text-stone-600">{product.short_description}</p>}

                        {hasVariants && (
                            <div className="mt-7">
                                <VariantPicker selection={selection} highlightMissing={nudge} id={PICKER_ID} />
                            </div>
                        )}

                        {canBuy ? (
                            <div className="mt-8 space-y-3">
                                <div className="flex gap-3">
                                    <QuantityStepper value={quantity} onChange={setQuantity} />
                                    <button type="button" onClick={() => addToCart(false)} disabled={!!busy} className="btn-primary h-12 flex-1 text-base">
                                        {busy === 'cart' ? <Loader2 size={18} className="animate-spin" /> : <ShoppingCart size={18} />}
                                        {chooseLabel || 'Add to cart'}
                                    </button>
                                </div>
                                <button type="button" onClick={() => addToCart(true)} disabled={!!busy} className="btn-outline h-12 w-full text-base">
                                    {busy === 'checkout' && <Loader2 size={18} className="animate-spin" />}
                                    Buy now
                                </button>
                                {enquiryUrl && (
                                    <a
                                        href={enquiryUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-center gap-2 py-1 text-sm font-semibold text-whatsapp-dark hover:underline"
                                    >
                                        <WhatsAppIcon size={17} /> Questions? Ask us on WhatsApp
                                    </a>
                                )}
                            </div>
                        ) : (
                            <div className="mt-8 flex flex-col gap-3">
                                {variant && !variant.purchasable && shopOn && (
                                    <p className="rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700">
                                        {variant.label} is not available to order online right now. Choose another option, or ask us about it.
                                    </p>
                                )}
                                <WhatsAppButton href={enquiryUrl} size="lg" className="w-full">
                                    {enquiryLabel}
                                </WhatsAppButton>
                                {store?.phone && (
                                    <a href={`tel:${store.phone.replace(/[^\d+]/g, '')}`} className="btn-outline h-12 w-full">
                                        <Phone size={17} /> Call {store.phone}
                                    </a>
                                )}
                            </div>
                        )}

                        <p className="mt-4 flex items-start gap-2 text-sm text-stone-500">
                            <ShieldCheck size={17} className="mt-0.5 shrink-0" />
                            {canBuy
                                ? paymentNote(store.payment_methods)
                                : store?.shop_enabled
                                  ? 'This item is not available to order online right now. Send us a message and we will help.'
                                  : 'Send us a message to confirm availability, delivery and payment options. No online payment required.'}
                        </p>

                        <div className="mt-8 border-t border-stone-100 pt-6">
                            <ShareButtons url={product.url} title={product.name} />
                        </div>
                    </div>
                </div>

                {product.description_html && (
                    <section className="mt-14 max-w-3xl border-t border-stone-100 pt-10 sm:mt-16">
                        <h2 className="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Product details</h2>
                        <div
                            className="rich-text mt-5"
                            dangerouslySetInnerHTML={{
                                __html: product.description_html,
                            }}
                        />
                    </section>
                )}

                {related.length > 0 && (
                    <section className="mt-16 border-t border-stone-100 pt-12 sm:mt-20">
                        <h2 className="mb-8 text-2xl font-bold tracking-tight text-stone-900">You may also like</h2>
                        <ProductGrid products={related} />
                    </section>
                )}
            </div>

            {/* Sticky bar on phones */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 backdrop-blur lg:hidden">
                <div className="container-page flex items-center gap-3 py-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-xs text-stone-500">{variant ? `${product.name} · ${variant.label}` : product.name}</p>
                        <p className="text-base font-bold text-stone-900">
                            {priceFrom && <span className="mr-1 text-xs font-medium text-stone-500">From</span>}
                            {shown.price}
                        </p>
                    </div>
                    {canBuy ? (
                        <button type="button" onClick={() => addToCart(false)} disabled={!!busy} className="btn-primary h-12 max-w-[60%] px-5">
                            {busy === 'cart' ? <Loader2 size={18} className="animate-spin" /> : <ShoppingCart size={18} className="shrink-0" />}
                            <span className="truncate">{chooseLabel ? 'Choose options' : 'Add to cart'}</span>
                        </button>
                    ) : (
                        <WhatsAppButton href={enquiryUrl} className="h-12 px-5">
                            Enquire
                        </WhatsAppButton>
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

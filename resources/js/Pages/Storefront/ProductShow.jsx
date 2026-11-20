import { Link, usePage } from '@inertiajs/react';
import { Eye, Loader2, Phone, ShieldCheck, ShoppingCart, Tag } from 'lucide-react';
import { useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import { AvailabilityLabel, PriceTag, ProductGrid } from '@/Components/Store/ProductCard';
import ProductGallery from '@/Components/Store/ProductGallery';
import QuantityStepper from '@/Components/Store/QuantityStepper';
import ShareButtons from '@/Components/Store/ShareButtons';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';
import { useAddToCart } from '@/lib/cart';

/** Add to cart / Buy now with a quantity. */
function useProductCart(product) {
    const [quantity, setQuantity] = useState(1);
    const { add, busy } = useAddToCart();
    return { quantity, setQuantity, busy, add: (checkout = false) => add(product.id, { quantity, checkout, onSuccess: () => !checkout && setQuantity(1) }) };
}

function paymentNote(methods) {
    if (!methods?.length) return '';
    const names = methods.map((m) => m.charAt(0).toLowerCase() + m.slice(1));
    const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} or ${names.at(-1)}` : names[0];
    return `Order online, then ${list}. No card needed.`;
}

export default function ProductShow({ product, whatsappUrl, related, isPreview }) {
    const { store } = usePage().props;
    const canBuy = store?.shop_enabled && product.purchasable && !isPreview;
    const cart = useProductCart(product);
    const crumbs = [{ label: 'Home', href: '/' }, { label: 'Products', href: '/products' }];
    if (product.category) crumbs.push({ label: product.category.name, href: product.category.url });
    crumbs.push({ label: product.name });

    const soldOut = product.availability === 'out_of_stock';
    const enquiryLabel = soldOut ? 'Ask about availability' : 'Enquire on WhatsApp';

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
                    <ProductGallery images={product.images} name={product.name} />

                    <div className="lg:sticky lg:top-28 lg:self-start">
                        {product.category && (
                            <Link href={product.category.url} className="text-xs font-semibold tracking-wider text-stone-500 uppercase hover:text-stone-900">
                                {product.category.name}
                            </Link>
                        )}
                        <h1 className="mt-2 text-2xl leading-tight font-bold tracking-tight text-balance text-stone-950 sm:text-3xl lg:text-4xl">{product.name}</h1>

                        <div className="mt-5 flex flex-wrap items-center gap-3">
                            <PriceTag price={product.price} compareAt={product.compare_at_price} size="lg" />
                            {product.discount_percent > 0 && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-3 py-1 text-sm font-semibold text-accent">
                                    <Tag size={14} /> Save {product.discount_percent}%
                                </span>
                            )}
                        </div>

                        <div className="mt-4">
                            <AvailabilityLabel value={product.availability} label={product.availability_label} className="rounded-full bg-stone-100 px-3 py-1.5 text-sm" />
                        </div>

                        {product.short_description && <p className="mt-6 text-base leading-relaxed text-stone-600">{product.short_description}</p>}

                        {canBuy ? (
                            <div className="mt-8 space-y-3">
                                <div className="flex gap-3">
                                    <QuantityStepper value={cart.quantity} onChange={cart.setQuantity} />
                                    <button type="button" onClick={() => cart.add(false)} disabled={!!cart.busy} className="btn-primary h-12 flex-1 text-base">
                                        {cart.busy === 'cart' ? <Loader2 size={18} className="animate-spin" /> : <ShoppingCart size={18} />}
                                        Add to cart
                                    </button>
                                </div>
                                <button type="button" onClick={() => cart.add(true)} disabled={!!cart.busy} className="btn-outline h-12 w-full text-base">
                                    {cart.busy === 'checkout' && <Loader2 size={18} className="animate-spin" />}
                                    Buy now
                                </button>
                                {whatsappUrl && (
                                    <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="flex items-center justify-center gap-2 py-1 text-sm font-semibold text-whatsapp-dark hover:underline">
                                        <WhatsAppIcon size={17} /> Questions? Ask us on WhatsApp
                                    </a>
                                )}
                            </div>
                        ) : (
                            <div className="mt-8 flex flex-col gap-3">
                                <WhatsAppButton href={whatsappUrl} size="lg" className="w-full">
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
                        <div className="rich-text mt-5" dangerouslySetInnerHTML={{ __html: product.description_html }} />
                    </section>
                )}

                {related.length > 0 && (
                    <section className="mt-16 border-t border-stone-100 pt-12 sm:mt-20">
                        <h2 className="mb-8 text-2xl font-bold tracking-tight text-stone-900">You may also like</h2>
                        <ProductGrid products={related} />
                    </section>
                )}
            </div>

            {/* Sticky enquiry bar on phones */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 backdrop-blur lg:hidden">
                <div className="container-page flex items-center gap-3 py-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-xs text-stone-500">{product.name}</p>
                        <p className="text-base font-bold text-stone-900">{product.price}</p>
                    </div>
                    {canBuy ? (
                        <button type="button" onClick={() => cart.add(false)} disabled={!!cart.busy} className="btn-primary h-12 px-5">
                            {cart.busy === 'cart' ? <Loader2 size={18} className="animate-spin" /> : <ShoppingCart size={18} />}
                            Add to cart
                        </button>
                    ) : (
                        <WhatsAppButton href={whatsappUrl} className="h-12 px-5">
                            Enquire
                        </WhatsAppButton>
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

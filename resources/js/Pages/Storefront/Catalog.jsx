import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import EmptyState from '@/Components/Store/EmptyState';
import GridLoading from '@/Components/Store/GridLoading';
import Pagination from '@/Components/Store/Pagination';
import { ProductGrid } from '@/Components/Store/ProductCard';
import SearchForm from '@/Components/Store/SearchForm';
import SortSelect from '@/Components/Store/SortSelect';
import ResultCount, { cleanParams } from '@/Components/Store/ResultCount';
import StoreLayout from '@/Layouts/StoreLayout';

function Chip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            data-active={active || undefined}
            className={`h-10 shrink-0 rounded-full border px-4 text-sm font-medium transition active:scale-95 ${active ? 'border-brand bg-brand text-on-brand' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-400'}`}
        >
            {children}
        </button>
    );
}

export default function Catalog({ heading, products, filters, categories, sorts }) {
    const [loading, setLoading] = useState(false);
    const chips = useRef(null);

    const visit = (changes) =>
        router.get('/products', cleanParams({ ...filters, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });

    // Keep the chosen category chip in view on phones (the row scrolls sideways).
    useEffect(() => {
        const active = chips.current?.querySelector('[data-active]');
        active?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    }, [filters.category]);

    const activeCategory = categories.find((c) => c.slug === filters.category);
    const hasFilters = filters.search || filters.category;

    const crumbs = [{ label: 'Home', href: '/' }, { label: 'Products', href: '/products' }];
    if (filters.search) crumbs.push({ label: 'Search' });
    else if (activeCategory) crumbs.push({ label: activeCategory.name });

    return (
        <StoreLayout>
            <div className="border-b border-stone-100 bg-stone-50/70">
                <div className="container-page py-6 sm:py-10">
                    <Breadcrumbs items={crumbs} />
                    <h1 className="mt-3 text-[1.75rem] leading-tight font-bold tracking-tight text-stone-900 sm:mt-4 sm:text-4xl">{activeCategory && !filters.search ? activeCategory.name : heading}</h1>
                    <div className="mt-4 max-w-xl sm:mt-6">
                        <SearchForm initial={filters.search} extraParams={cleanParams({ category: filters.category, sort: filters.sort })} />
                    </div>
                </div>
            </div>

            {/* Phones: category chips and sort stay pinned under the header while browsing. */}
            <div className="sticky-below-header sticky z-30 border-b border-stone-200/60 bg-white/90 backdrop-blur-md backdrop-saturate-150 sm:static sm:border-0 sm:bg-transparent sm:backdrop-blur-none">
                <div className="container-page flex items-center gap-2 py-2.5 sm:pt-10 sm:pb-0">
                    <div ref={chips} className="no-scrollbar -ml-4 flex min-w-0 flex-1 gap-2 overflow-x-auto pl-4 [mask-image:linear-gradient(to_right,black_calc(100%-24px),transparent)] sm:ml-0 sm:flex-wrap sm:overflow-visible sm:pl-0 sm:[mask-image:none]" role="group" aria-label="Filter by category">
                        <Chip active={!filters.category} onClick={() => visit({ category: '' })}>
                            All
                        </Chip>
                        {categories.map((c) => (
                            <Chip key={c.slug} active={filters.category === c.slug} onClick={() => visit({ category: c.slug })}>
                                {c.name} <span className="opacity-60">({c.count})</span>
                            </Chip>
                        ))}
                        <span className="w-4 shrink-0 sm:hidden" aria-hidden />
                    </div>
                    <SortSelect value={filters.sort} options={sorts} onChange={(sort) => visit({ sort })} className="shrink-0 sm:hidden" />
                </div>
            </div>

            <div className="container-page pt-4 pb-8 sm:py-0 sm:pb-10">
                <div className="flex items-center justify-between gap-4 sm:mt-6 sm:border-b sm:border-stone-100 sm:pb-6">
                    <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                        <ResultCount products={products} />
                        {filters.search && (
                            <button type="button" onClick={() => visit({ search: '' })} className="inline-flex items-center gap-1 rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-700 transition hover:bg-stone-200 active:scale-95">
                                “{filters.search}” <X size={14} aria-label="Clear search" />
                            </button>
                        )}
                    </div>
                    <SortSelect value={filters.sort} options={sorts} onChange={(sort) => visit({ sort })} className="hidden sm:block" />
                </div>

                <div className="mt-5 sm:mt-8">
                    {products.data.length > 0 ? (
                        <GridLoading loading={loading}>
                            <ProductGrid products={products.data} eagerCount={4} />
                            <Pagination links={products.links} currentPage={products.current_page} lastPage={products.last_page} />
                        </GridLoading>
                    ) : (
                        <EmptyState
                            title={filters.search ? `No results for “${filters.search}”` : 'No products here yet'}
                            message={
                                filters.search
                                    ? 'Try a different spelling or a more general word. You can also ask us directly — we may have what you need.'
                                    : 'There are no products in this selection yet. Check back soon or ask us on WhatsApp.'
                            }
                            clearHref={hasFilters ? '/products' : null}
                            clearLabel="View all products"
                        />
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

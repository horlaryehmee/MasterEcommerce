import { router } from '@inertiajs/react';
import { useState } from 'react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import EmptyState from '@/Components/Store/EmptyState';
import GridLoading from '@/Components/Store/GridLoading';
import Img from '@/Components/Store/Img';
import Pagination from '@/Components/Store/Pagination';
import { ProductGrid } from '@/Components/Store/ProductCard';
import SortSelect from '@/Components/Store/SortSelect';
import ResultCount, { cleanParams } from '@/Components/Store/ResultCount';
import StoreLayout from '@/Layouts/StoreLayout';

export default function CategoryShow({ category, products, filters, sorts }) {
    const [loading, setLoading] = useState(false);
    const sort = (value) =>
        router.get(category.url, cleanParams({ sort: value, search: filters.search }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });

    return (
        <StoreLayout>
            <div className="bg-surface text-on-surface">
                <div className={`container-page grid items-center gap-6 py-6 sm:py-10 ${category.image ? 'md:grid-cols-[1fr_auto]' : ''}`}>
                    <div>
                        <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Categories', href: '/categories' }, { label: category.name }]} />
                        <h1 className="mt-3 text-[1.75rem] leading-tight font-bold tracking-tight text-stone-900 sm:mt-4 sm:text-4xl">{category.name}</h1>
                        {category.description && <p className="mt-3 max-w-2xl text-[15px] leading-relaxed text-stone-600">{category.description}</p>}
                    </div>
                    {category.image && (
                        <div className="hidden h-40 w-40 overflow-hidden rounded-3xl bg-white/60 md:block lg:h-48 lg:w-48">
                            <Img image={category.image} eager sizes="192px" className="h-full w-full object-cover" />
                        </div>
                    )}
                </div>
            </div>

            <div className="container-page py-5 sm:py-10">
                <div className="flex items-center justify-between gap-4 border-b border-stone-100 pb-4 sm:pb-6">
                    <ResultCount products={products} />
                    {products.total > 0 && <SortSelect value={filters.sort} options={sorts} onChange={sort} />}
                </div>
                <div className="mt-6 sm:mt-8">
                    {products.data.length > 0 ? (
                        <GridLoading loading={loading}>
                            <ProductGrid products={products.data} eagerCount={4} />
                            <Pagination links={products.links} currentPage={products.current_page} lastPage={products.last_page} />
                        </GridLoading>
                    ) : (
                        <EmptyState
                            title="No products in this category yet"
                            message="We're stocking up. Browse our other products or ask us on WhatsApp about what you need."
                            clearHref="/products"
                            clearLabel="Browse all products"
                        />
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

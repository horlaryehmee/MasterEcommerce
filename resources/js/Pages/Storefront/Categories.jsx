import { FolderOpen } from 'lucide-react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import CategoryCard from '@/Components/Store/CategoryCard';
import EmptyState from '@/Components/Store/EmptyState';
import StoreLayout from '@/Layouts/StoreLayout';

export default function Categories({ categories }) {
    return (
        <StoreLayout>
            <div className="border-b border-stone-100 bg-stone-50/70">
                <div className="container-page py-8 sm:py-10">
                    <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Categories' }]} />
                    <h1 className="mt-4 text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">Shop by Category</h1>
                    <p className="mt-2 text-stone-600">Browse our collections and find exactly what you need.</p>
                </div>
            </div>
            <div className="container-page py-10 sm:py-14">
                {categories.length > 0 ? (
                    <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4">
                        {categories.map((c) => (
                            <CategoryCard key={c.id} category={c} />
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={FolderOpen} title="No categories yet" message="We're organising our products. Please check back soon." clearHref="/products" clearLabel="Browse all products" />
                )}
            </div>
        </StoreLayout>
    );
}

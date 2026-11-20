import { Link } from '@inertiajs/react';
import Img from './Img';

export default function CategoryCard({ category, className = '' }) {
    return (
        <Link href={category.url} className={`group block transition-transform duration-200 [@media(hover:none)]:active:scale-[0.97] ${className}`} prefetch="hover">
            <div className="aspect-square overflow-hidden rounded-2xl bg-surface">
                <Img
                    image={category.image}
                    sizes="(min-width: 1024px) 25vw, (min-width: 768px) 33vw, 45vw"
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                />
            </div>
            <div className="mt-3 flex items-baseline justify-between gap-2">
                <h3 className="text-sm font-semibold text-stone-900 sm:text-base">{category.name}</h3>
                {category.products_count != null && <span className="shrink-0 text-xs text-stone-500">{category.products_count} items</span>}
            </div>
        </Link>
    );
}

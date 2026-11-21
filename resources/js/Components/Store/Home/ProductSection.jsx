import useTheme from '@/hooks/useTheme';
import { ProductGrid, ProductRail } from '../ProductCard';
import SectionHeading from '../SectionHeading';

export default function ProductSection({ section, link = '/products', tinted = false }) {
    const theme = useTheme();
    if (!section) return null;
    return (
        <section className={`py-14 sm:py-20 ${tinted ? 'bg-stone-50' : ''}`}>
            <div className="container-page">
                <SectionHeading title={section.heading} subtitle={section.subheading} link={link} linkLabel="Shop all" />
                {/* Phones: a 2-column grid or a swipeable row, as the template prefers (same grid on larger screens). */}
                {theme.products === 'grid' ? <ProductGrid products={section.items} /> : <ProductRail products={section.items} label={section.heading} />}
            </div>
        </section>
    );
}

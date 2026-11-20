import { ProductRail } from '../ProductCard';
import SectionHeading from '../SectionHeading';

export default function ProductSection({ section, link = '/products', tinted = false }) {
    if (!section) return null;
    return (
        <section className={`py-14 sm:py-20 ${tinted ? 'bg-stone-50' : ''}`}>
            <div className="container-page">
                <SectionHeading title={section.heading} subtitle={section.subheading} link={link} linkLabel="Shop all" />
                <ProductRail products={section.items} label={section.heading} />
            </div>
        </section>
    );
}

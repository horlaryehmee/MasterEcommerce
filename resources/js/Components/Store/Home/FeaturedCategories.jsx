import CategoryCard from '../CategoryCard';
import Reveal from '../Reveal';
import SectionHeading from '../SectionHeading';

export default function FeaturedCategories({ section }) {
    if (!section) return null;
    return (
        <section className="py-12 sm:py-20">
            <div className="container-page">
                <SectionHeading title={section.heading} subtitle={section.subheading} link="/categories" linkLabel="All categories" />
            </div>
            {/* Swipeable row on phones (the next card peeks in), grid on larger screens. */}
            <div className="no-scrollbar flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:hidden">
                {section.items.map((c, i) => (
                    <Reveal key={c.id} delay={Math.min(i, 3) * 60} className="w-[40%] shrink-0 snap-start">
                        <CategoryCard category={c} />
                    </Reveal>
                ))}
            </div>
            <div className="container-page hidden gap-6 sm:grid sm:grid-cols-3 lg:grid-cols-4">
                {section.items.map((c, i) => (
                    <Reveal key={c.id} delay={(i % 4) * 70}>
                        <CategoryCard category={c} />
                    </Reveal>
                ))}
            </div>
        </section>
    );
}

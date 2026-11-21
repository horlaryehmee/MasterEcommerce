import { PackageOpen } from 'lucide-react';
import { Fragment } from 'react';
import EmptyState from '@/Components/Store/EmptyState';
import AboutSection from '@/Components/Store/Home/AboutSection';
import FeaturedCategories from '@/Components/Store/Home/FeaturedCategories';
import Hero from '@/Components/Store/Home/Hero';
import Marquee from '@/Components/Store/Home/Marquee';
import ProductSection from '@/Components/Store/Home/ProductSection';
import PromoBanner from '@/Components/Store/Home/PromoBanner';
import WhyChooseUs from '@/Components/Store/Home/WhyChooseUs';
import StoreLayout from '@/Layouts/StoreLayout';

const DEFAULT_ORDER = ['hero', 'marquee', 'categories', 'featured', 'promo', 'new_arrivals', 'why', 'about'];

/** Homepage sections, in the order the template (Admin → Templates) lays them out. */
export default function Home({ hero, marquee, categories, featured, newArrivals, promo, about, why, order }) {
    const isEmpty = !hero && !marquee && !categories && !featured && !newArrivals && !promo && !about && !why;
    const sections = {
        hero: <Hero hero={hero} />,
        marquee: <Marquee marquee={marquee} />,
        categories: <FeaturedCategories section={categories} />,
        featured: <ProductSection section={featured} tinted />,
        promo: <PromoBanner promo={promo} />,
        new_arrivals: <ProductSection section={newArrivals} link="/products?sort=latest" />,
        why: <WhyChooseUs why={why} />,
        about: <AboutSection about={about} />,
    };

    return (
        <StoreLayout>
            {(order?.length ? order : DEFAULT_ORDER).map((key) => (
                <Fragment key={key}>{sections[key]}</Fragment>
            ))}
            {isEmpty && (
                <div className="container-page py-20">
                    <EmptyState icon={PackageOpen} title="Our store is coming soon" message="We're adding our products right now. In the meantime, chat with us on WhatsApp — we're happy to help." />
                </div>
            )}
        </StoreLayout>
    );
}

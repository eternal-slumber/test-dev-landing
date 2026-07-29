import { Head } from '@inertiajs/react';

import ArchitectureSection from '@/components/landing/architecture-section';
import ContactSection from '@/components/landing/contact-section';
import HeroSection from '@/components/landing/hero-section';
import ProjectsSection from '@/components/landing/projects-section';
import SiteFooter from '@/components/landing/site-footer';
import SiteHeader from '@/components/landing/site-header';

export default function Welcome() {
    return (
        <>
            <Head title="Backend-разработчик" />

            <SiteHeader />

            <main className="bg-[#f4f2ed] text-slate-950 dark:bg-[#080b12] dark:text-white">
                <HeroSection />
                <ProjectsSection />
                <ArchitectureSection />
                <ContactSection />
            </main>

            <SiteFooter />
        </>
    );
}

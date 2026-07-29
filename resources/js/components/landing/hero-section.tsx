import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';

const heroItems = [
    'API',
    'AI-интеграций',
    'веб-сервисов',
    'бизнес-систем',
] as const;

export default function HeroSection() {
    const [heroItemIndex, setHeroItemIndex] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const interval = window.setInterval(() => {
            setHeroItemIndex(
                (currentIndex) => (currentIndex + 1) % heroItems.length,
            );
        }, 2800);

        return () => window.clearInterval(interval);
    }, []);

    return (
        <section
            id="top"
            className="relative isolate flex min-h-screen min-h-svh overflow-hidden px-6 py-20 sm:px-10 sm:py-24 lg:py-28"
        >
            <div
                className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_50%_0%,rgba(99,102,241,0.07),transparent_38%),radial-gradient(circle_at_90%_80%,rgba(14,165,233,0.05),transparent_32%)] dark:bg-[radial-gradient(circle_at_50%_0%,rgba(129,140,248,0.09),transparent_38%),radial-gradient(circle_at_90%_80%,rgba(14,165,233,0.06),transparent_32%)]"
                aria-hidden="true"
            />

            <div className="mx-auto flex w-full max-w-6xl flex-col items-start justify-center text-left">
                <h1 className="flex flex-col items-start text-[clamp(3.5rem,7vw,6rem)] leading-[0.93] font-semibold tracking-[-0.055em] md:flex-row md:items-baseline md:gap-x-5">
                    <span className="shrink-0">Разработка</span>
                    <span className="relative mt-2 block min-h-[1.15em] overflow-hidden md:mt-0">
                        <span className="sr-only">API</span>
                        <span className="invisible block" aria-hidden="true">
                            бизнес-систем
                        </span>
                        <span
                            key={heroItems[heroItemIndex]}
                            className="hero-word-enter absolute top-0 left-0 block bg-linear-to-r from-indigo-600 to-sky-500 bg-clip-text text-transparent dark:from-indigo-400 dark:to-sky-300"
                            aria-hidden="true"
                        >
                            {heroItems[heroItemIndex]}
                        </span>
                    </span>
                </h1>

                <p className="mt-6 max-w-3xl text-lg leading-8 text-pretty text-slate-600 sm:text-xl lg:text-2xl lg:leading-9 dark:text-slate-300">
                    Backend на Laravel: от бизнес-логики и PostgreSQL до
                    очередей, внешних интеграций и production-деплоя.
                </p>

                <p className="mt-6 text-base tracking-wide text-slate-500 sm:text-lg dark:text-slate-400">
                    Laravel · PostgreSQL · Redis · Docker · React · AI
                </p>

                <Button
                    asChild
                    size="lg"
                    className="mt-6 h-12 rounded-full px-7"
                >
                    <a href="#projects">Смотреть проекты</a>
                </Button>
            </div>
        </section>
    );
}

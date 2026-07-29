import { Button } from '@/components/ui/button';

export default function SiteHeader() {
    return (
        <header className="fixed top-3 left-1/2 z-50 flex w-[calc(100%-1.5rem)] -translate-x-1/2 items-center gap-2 rounded-2xl border border-slate-950/10 bg-[#f4f2ed]/85 px-3 py-3 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.28)] backdrop-blur-xl sm:w-auto sm:gap-3 sm:px-4 dark:border-white/10 dark:bg-[#080b12]/85">
            <nav
                aria-label="Навигация по странице"
                className="flex flex-1 items-center justify-center gap-2 text-[11px] font-medium sm:flex-none sm:gap-5 sm:text-sm"
            >
                <a
                    href="#projects"
                    className="text-slate-600 transition-colors hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
                >
                    Проекты
                </a>
                <a
                    href="#architecture"
                    className="text-slate-600 transition-colors hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
                >
                    Архитектура
                </a>
                <a
                    href="https://github.com/eternal-slumber"
                    target="_blank"
                    rel="noreferrer"
                    className="text-slate-600 transition-colors hover:text-slate-950 md:rounded-lg md:border md:border-slate-950/10 md:px-3 md:py-1.5 dark:text-slate-300 dark:hover:text-white md:dark:border-white/10"
                >
                    GitHub
                </a>
            </nav>

            <Button asChild size="sm" className="rounded-xl px-3 sm:px-5">
                <a href="#contact">Связаться</a>
            </Button>
        </header>
    );
}

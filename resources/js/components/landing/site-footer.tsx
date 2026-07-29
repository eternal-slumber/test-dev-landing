import { Link } from '@inertiajs/react';

import { status } from '@/routes';

export default function SiteFooter() {
    return (
        <footer className="border-t border-slate-950/10 bg-[#f4f2ed] px-6 py-6 text-slate-950 sm:px-10 dark:border-white/10 dark:bg-[#080b12] dark:text-white">
            <div className="mx-auto flex max-w-6xl items-center justify-center">
                <nav
                    className="flex flex-wrap items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                    aria-label="Технические ссылки"
                >
                    <a
                        href="https://github.com/eternal-slumber"
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-sm transition-colors hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:hover:text-white"
                    >
                        GitHub
                    </a>
                    <span aria-hidden="true">·</span>
                    <a
                        href="/openapi.yaml"
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-sm transition-colors hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:hover:text-white"
                    >
                        API Docs
                    </a>
                    <span aria-hidden="true">·</span>
                    <Link
                        href={status()}
                        className="rounded-sm transition-colors hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:hover:text-white"
                    >
                        System status
                    </Link>
                </nav>
            </div>
        </footer>
    );
}

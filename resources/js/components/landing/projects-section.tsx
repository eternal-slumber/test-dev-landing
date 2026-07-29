import { Button } from '@/components/ui/button';

export default function ProjectsSection() {
    return (
        <section
            id="projects"
            aria-labelledby="projects-title"
            className="scroll-mt-24 border-t border-slate-950/10 bg-white/35 px-6 py-24 sm:px-10 sm:py-32 dark:border-white/10 dark:bg-white/[0.02]"
        >
            <div className="mx-auto max-w-6xl">
                <div className="max-w-2xl">
                    <h2
                        id="projects-title"
                        className="text-4xl font-semibold tracking-[-0.04em] sm:text-6xl"
                    >
                        Проекты
                    </h2>
                    <p className="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">
                        Веб-продукты, API и AI-интеграции.
                    </p>
                </div>

                <div className="mt-12 grid gap-6 lg:grid-cols-2">
                    <article className="reveal-on-scroll rounded-[2rem] border border-slate-950/10 bg-white/70 p-6 shadow-[0_18px_45px_-36px_rgba(15,23,42,0.3)] transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-0.5 hover:border-slate-950/15 hover:shadow-[0_24px_60px_-42px_rgba(15,23,42,0.45)] motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-9 lg:col-span-2 lg:grid lg:grid-cols-[1.15fr_0.85fr] lg:gap-12 dark:border-white/10 dark:bg-white/[0.04] dark:hover:border-white/15 dark:hover:shadow-[0_24px_60px_-42px_rgba(0,0,0,0.65)]">
                        <div>
                            <p className="text-sm font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                                Laravel · AI · Production
                            </p>
                            <h3 className="mt-5 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                Developer Landing
                            </h3>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">
                                Надёжный backend формы обратной связи с
                                AI-анализом, очередями и гарантированной фоновой
                                обработкой.
                            </p>
                            <p className="mt-6 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Laravel · PostgreSQL · Redis · React · Docker ·
                                OpenRouter
                            </p>
                        </div>

                        <div className="mt-8 flex flex-col justify-between gap-8 lg:mt-0">
                            <ul className="grid gap-3 text-sm text-slate-700 sm:grid-cols-2 lg:grid-cols-1 dark:text-slate-200">
                                <li className="flex items-center gap-3">
                                    <span
                                        className="size-1.5 rounded-full bg-indigo-400/70"
                                        aria-hidden="true"
                                    />
                                    Transactional outbox
                                </li>
                                <li className="flex items-center gap-3">
                                    <span
                                        className="size-1.5 rounded-full bg-indigo-400/70"
                                        aria-hidden="true"
                                    />
                                    Idempotency
                                </li>
                                <li className="flex items-center gap-3">
                                    <span
                                        className="size-1.5 rounded-full bg-indigo-400/70"
                                        aria-hidden="true"
                                    />
                                    AI summary и черновик ответа
                                </li>
                                <li className="flex items-center gap-3">
                                    <span
                                        className="size-1.5 rounded-full bg-indigo-400/70"
                                        aria-hidden="true"
                                    />
                                    Email workflow с retry
                                </li>
                            </ul>

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button
                                    asChild
                                    size="lg"
                                    className="h-11 rounded-full px-6"
                                >
                                    <a
                                        href="/openapi.yaml"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        API-документация
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </article>

                    <article className="reveal-on-scroll flex flex-col rounded-[2rem] border border-slate-950/10 bg-white/60 p-6 shadow-[0_18px_45px_-38px_rgba(15,23,42,0.28)] transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-0.5 hover:border-slate-950/15 hover:shadow-[0_24px_60px_-42px_rgba(15,23,42,0.45)] motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8 dark:border-white/10 dark:bg-white/[0.035] dark:hover:border-white/15 dark:hover:shadow-[0_24px_60px_-42px_rgba(0,0,0,0.65)]">
                        <p className="text-sm font-medium tracking-widest text-sky-600 uppercase dark:text-sky-300">
                            Telegram Mini App
                        </p>
                        <h3 className="mt-4 text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">
                            Calorie Tracker
                        </h3>
                        <p className="mt-4 leading-7 text-slate-600 dark:text-slate-300">
                            Telegram Mini App для ведения питания, распознавания
                            еды по фотографиям и анализа КБЖУ.
                        </p>
                        <p className="mt-5 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            PHP · Slim · MySQL · Telegram WebApp · AI
                        </p>

                        <ul className="mt-7 grid gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-sky-500"
                                    aria-hidden="true"
                                />
                                Распознавание блюд по фото
                            </li>
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-sky-500"
                                    aria-hidden="true"
                                />
                                История и аналитика питания
                            </li>
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-sky-500"
                                    aria-hidden="true"
                                />
                                Персональные рекомендации
                            </li>
                        </ul>

                        <div className="mt-auto pt-9">
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-11 w-full rounded-full border-slate-950/15 bg-transparent px-6 sm:w-auto dark:border-white/15 dark:hover:bg-white/10"
                            >
                                <a
                                    href="https://github.com/eternal-slumber/FoodTrackerPHP"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    GitHub
                                </a>
                            </Button>
                        </div>
                    </article>

                    <article className="reveal-on-scroll flex flex-col rounded-[2rem] border border-slate-950/10 bg-white/60 p-6 shadow-[0_18px_45px_-38px_rgba(15,23,42,0.28)] transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-0.5 hover:border-slate-950/15 hover:shadow-[0_24px_60px_-42px_rgba(15,23,42,0.45)] motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8 dark:border-white/10 dark:bg-white/[0.035] dark:hover:border-white/15 dark:hover:shadow-[0_24px_60px_-42px_rgba(0,0,0,0.65)]">
                        <p className="text-sm font-medium tracking-widest text-emerald-600 uppercase dark:text-emerald-300">
                            Laravel · AI
                        </p>
                        <h3 className="mt-4 text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">
                            Coach Workspace
                        </h3>
                        <p className="mt-4 leading-7 text-slate-600 dark:text-slate-300">
                            Рабочее пространство тренера с расписанием,
                            конструктором тренировок и AI-генерацией планов.
                        </p>
                        <p className="mt-5 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            Laravel · PostgreSQL · Redis · React · AI
                        </p>

                        <ul className="mt-7 grid gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-emerald-500"
                                    aria-hidden="true"
                                />
                                Клиенты и группы
                            </li>
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-emerald-500"
                                    aria-hidden="true"
                                />
                                Расписание тренировок
                            </li>
                            <li className="flex items-center gap-3">
                                <span
                                    className="size-1.5 rounded-full bg-emerald-500"
                                    aria-hidden="true"
                                />
                                AI-планы и память агента
                            </li>
                        </ul>

                        <div className="mt-auto pt-9">
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-11 w-full rounded-full border-slate-950/15 bg-transparent px-6 sm:w-auto dark:border-white/15 dark:hover:bg-white/10"
                            >
                                <a
                                    href="https://github.com/eternal-slumber/trainer-workspace"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    GitHub
                                </a>
                            </Button>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    );
}

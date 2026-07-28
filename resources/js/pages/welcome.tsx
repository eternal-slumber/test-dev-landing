import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { SubmitEvent } from 'react';

import { store } from '@/actions/App/Http/Controllers/Api/ContactController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { health } from '@/routes/api';

type ContactForm = {
    name: string;
    phone: string;
    email: string;
    comment: string;
};

type ContactResponse = {
    data: {
        id: number;
        status: 'accepted';
    };
    message: string;
};

const heroItems = [
    'надёжных API',
    'AI-интеграций',
    'веб-сервисов',
    'бизнес-систем',
] as const;

const architectureSteps = [
    {
        title: 'Запрос',
        description:
            'Пользователь отправляет форму с Idempotency-Key. Данные проходят валидацию и rate limiting.',
    },
    {
        title: 'PostgreSQL',
        description:
            'Обращение, шаги обработки и outbox-сообщение сохраняются атомарно в одной транзакции.',
    },
    {
        title: 'Outbox',
        description:
            'Outbox гарантирует, что фоновая обработка не потеряется после успешного сохранения обращения.',
    },
    {
        title: 'Redis',
        description:
            'Relay публикует задачу в Redis, где worker выполняет её с retry и backoff.',
    },
    {
        title: 'AI-анализ',
        description:
            'AI определяет категорию, формирует краткое содержание и предлагает черновик ответа.',
    },
    {
        title: 'Email',
        description:
            'Владельцу и отправителю уходят независимые письма. При сбое задачи повторяются.',
    },
] as const;

export default function Welcome() {
    const idempotencyKey = useRef(crypto.randomUUID());
    const isSubmitting = useRef(false);
    const {
        data,
        setData,
        post,
        processing,
        errors,
        wasSuccessful,
        reset,
        setError,
        clearErrors,
    } = useHttp<ContactForm, ContactResponse>({
        name: '',
        phone: '',
        email: '',
        comment: '',
    });
    const [hasRequestError, setHasRequestError] = useState(false);
    const [heroItemIndex, setHeroItemIndex] = useState(0);
    const [activeArchitectureStepIndex, setActiveArchitectureStepIndex] =
        useState(0);
    const activeArchitectureStep =
        architectureSteps[activeArchitectureStepIndex];

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

    function submit(event: SubmitEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (isSubmitting.current) {
            return;
        }

        isSubmitting.current = true;
        setHasRequestError(false);

        void post(store.url(), {
            headers: {
                'Idempotency-Key': idempotencyKey.current,
            },
            onSuccess: () => {
                reset();
                idempotencyKey.current = crypto.randomUUID();
            },
        })
            .catch(() => {
                setHasRequestError(true);
            })
            .finally(() => {
                isSubmitting.current = false;
            });
    }

    return (
        <>
            <Head title="Backend-разработчик" />

            <header className="fixed top-3 right-3 left-3 z-50 mx-auto flex max-w-6xl items-center gap-2 rounded-2xl border border-slate-950/10 bg-[#f4f2ed]/85 px-3 py-3 shadow-[0_16px_50px_-24px_rgba(15,23,42,0.35)] backdrop-blur-xl sm:gap-3 sm:px-5 dark:border-white/10 dark:bg-[#080b12]/85">
                <a
                    href="#top"
                    className="shrink-0 text-2xl font-semibold tracking-[-0.05em]"
                    aria-label="В начало страницы"
                >
                    A<span className="text-sky-500">.</span>
                </a>

                <nav
                    aria-label="Навигация по странице"
                    className="ml-auto flex items-center gap-2 text-[11px] font-medium sm:gap-5 sm:text-sm"
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
                        href="#contact"
                        className="text-slate-600 transition-colors hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
                    >
                        Контакты
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

                <Button
                    asChild
                    size="sm"
                    className="hidden rounded-xl px-5 lg:inline-flex"
                >
                    <a href="#contact">Связаться</a>
                </Button>
            </header>

            <main className="bg-[#f4f2ed] text-slate-950 dark:bg-[#080b12] dark:text-white">
                <section
                    id="top"
                    className="relative isolate flex min-h-screen min-h-svh overflow-hidden py-20 sm:py-24 lg:py-28"
                >
                    <div
                        className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_50%_0%,rgba(99,102,241,0.14),transparent_38%),radial-gradient(circle_at_90%_80%,rgba(14,165,233,0.1),transparent_32%)] dark:bg-[radial-gradient(circle_at_50%_0%,rgba(129,140,248,0.18),transparent_38%),radial-gradient(circle_at_90%_80%,rgba(14,165,233,0.12),transparent_32%)]"
                        aria-hidden="true"
                    />

                    <div className="mx-auto flex w-full max-w-7xl flex-col items-start justify-center px-6 text-left sm:px-10">
                        <h1 className="flex flex-col items-start text-[clamp(3.5rem,7vw,6rem)] leading-[0.93] font-semibold tracking-[-0.055em] md:flex-row md:items-baseline md:gap-x-5">
                            <span className="shrink-0">Разработка</span>
                            <span className="relative mt-2 block min-h-[1.15em] overflow-hidden md:mt-0">
                                <span className="sr-only">надёжных API</span>
                                <span
                                    className="invisible block"
                                    aria-hidden="true"
                                >
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

                        <p className="mt-8 max-w-3xl text-lg leading-8 text-pretty text-slate-600 sm:text-xl lg:text-2xl lg:leading-9 dark:text-slate-300">
                            Проектирую backend на Laravel: от бизнес-логики и
                            PostgreSQL до очередей, внешних интеграций и
                            production-деплоя.
                        </p>

                        <p className="mt-10 text-base tracking-wide text-slate-500 sm:text-lg dark:text-slate-400">
                            Laravel · PostgreSQL · Redis · Docker · React · AI
                        </p>

                        <Button
                            asChild
                            size="lg"
                            className="mt-8 h-12 rounded-full px-7"
                        >
                            <a href="#projects">Подробнее</a>
                        </Button>
                    </div>
                </section>

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
                                Не только учебные задачи — работающие
                                веб-продукты, API и AI-интеграции.
                            </p>
                        </div>

                        <div className="mt-12 grid gap-6 lg:grid-cols-2">
                            <article className="reveal-on-scroll rounded-[2rem] border border-indigo-500/20 bg-white/70 p-6 shadow-[0_24px_80px_-48px_rgba(79,70,229,0.6)] transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-1 hover:border-indigo-500/35 hover:shadow-[0_28px_90px_-48px_rgba(79,70,229,0.7)] motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-9 lg:col-span-2 lg:grid lg:grid-cols-[1.15fr_0.85fr] lg:gap-12 dark:border-indigo-400/20 dark:bg-white/[0.04]">
                                <div>
                                    <p className="text-sm font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                                        Laravel · AI · Production
                                    </p>
                                    <h3 className="mt-5 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                        Developer Landing
                                    </h3>
                                    <p className="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">
                                        Надёжный backend формы обратной связи с
                                        AI-анализом, очередями и гарантированной
                                        фоновой обработкой.
                                    </p>
                                    <p className="mt-6 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        Laravel · PostgreSQL · Redis · React ·
                                        Docker · OpenRouter
                                    </p>
                                </div>

                                <div className="mt-8 flex flex-col justify-between gap-8 lg:mt-0">
                                    <ul className="grid gap-3 text-sm text-slate-700 sm:grid-cols-2 lg:grid-cols-1 dark:text-slate-200">
                                        <li className="flex items-center gap-3">
                                            <span
                                                className="size-1.5 rounded-full bg-indigo-500"
                                                aria-hidden="true"
                                            />
                                            Transactional outbox
                                        </li>
                                        <li className="flex items-center gap-3">
                                            <span
                                                className="size-1.5 rounded-full bg-indigo-500"
                                                aria-hidden="true"
                                            />
                                            Idempotency
                                        </li>
                                        <li className="flex items-center gap-3">
                                            <span
                                                className="size-1.5 rounded-full bg-indigo-500"
                                                aria-hidden="true"
                                            />
                                            AI summary и черновик ответа
                                        </li>
                                        <li className="flex items-center gap-3">
                                            <span
                                                className="size-1.5 rounded-full bg-indigo-500"
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

                            <article className="reveal-on-scroll flex flex-col rounded-[2rem] border border-slate-950/10 bg-white/60 p-6 transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-1 hover:border-sky-500/30 hover:shadow-lg motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8 dark:border-white/10 dark:bg-white/[0.035]">
                                <p className="text-sm font-medium tracking-widest text-sky-600 uppercase dark:text-sky-300">
                                    Telegram Mini App
                                </p>
                                <h3 className="mt-4 text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">
                                    Calorie Tracker
                                </h3>
                                <p className="mt-4 leading-7 text-slate-600 dark:text-slate-300">
                                    Telegram Mini App для ведения питания,
                                    распознавания еды по фотографиям и анализа
                                    КБЖУ.
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

                            <article className="reveal-on-scroll flex flex-col rounded-[2rem] border border-slate-950/10 bg-white/60 p-6 transition-[transform,border-color,box-shadow] duration-300 ease-out hover:-translate-y-1 hover:border-emerald-500/30 hover:shadow-lg motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8 dark:border-white/10 dark:bg-white/[0.035]">
                                <p className="text-sm font-medium tracking-widest text-emerald-600 uppercase dark:text-emerald-300">
                                    Laravel · AI
                                </p>
                                <h3 className="mt-4 text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">
                                    Coach Workspace
                                </h3>
                                <p className="mt-4 leading-7 text-slate-600 dark:text-slate-300">
                                    Рабочее пространство тренера с расписанием,
                                    конструктором тренировок и AI-генерацией
                                    планов.
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

                <section
                    id="architecture"
                    aria-labelledby="architecture-title"
                    className="scroll-mt-24 border-y border-slate-950/10 px-6 py-20 sm:px-10 lg:py-24 dark:border-white/10"
                >
                    <div className="mx-auto max-w-6xl">
                        <div className="mx-auto max-w-3xl text-center">
                            <h2
                                id="architecture-title"
                                className="text-4xl font-semibold tracking-[-0.04em] sm:text-6xl"
                            >
                                Как работает форма
                            </h2>
                            <p className="mt-4 text-lg leading-8 text-slate-600 dark:text-slate-300">
                                Обращение сначала надёжно сохраняется, а затем
                                независимо проходит AI-анализ и email-обработку.
                            </p>
                        </div>

                        <div className="reveal-on-scroll mt-10 rounded-3xl border border-slate-950/10 bg-white/60 p-6 shadow-[0_24px_80px_-56px_rgba(15,23,42,0.45)] sm:p-8 lg:px-10 dark:border-white/10 dark:bg-white/[0.035]">
                            <ol
                                className="flex flex-col items-stretch gap-3 lg:flex-row lg:items-center"
                                aria-label="Путь обработки обращения"
                            >
                                {architectureSteps.map((step, stepIndex) => {
                                    const isActive =
                                        stepIndex ===
                                        activeArchitectureStepIndex;

                                    return (
                                        <li
                                            key={step.title}
                                            className="contents"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setActiveArchitectureStepIndex(
                                                        stepIndex,
                                                    )
                                                }
                                                aria-pressed={isActive}
                                                aria-controls="architecture-step-details"
                                                className={`flex min-h-18 flex-1 cursor-pointer items-center justify-center rounded-2xl border px-4 text-center font-medium transition-[background-color,border-color,color,box-shadow,transform] duration-200 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 motion-reduce:transition-none motion-reduce:hover:translate-y-0 ${
                                                    isActive
                                                        ? 'border-indigo-500/40 bg-indigo-500/10 text-indigo-700 shadow-[0_12px_35px_-22px_rgba(79,70,229,0.9)] dark:border-indigo-400/40 dark:bg-indigo-400/15 dark:text-indigo-200'
                                                        : 'border-slate-950/10 bg-white/70 text-slate-700 hover:border-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-indigo-400/30'
                                                }`}
                                            >
                                                {step.title}
                                            </button>

                                            {stepIndex <
                                                architectureSteps.length -
                                                    1 && (
                                                <span
                                                    className="rotate-90 self-center text-2xl text-indigo-500 lg:rotate-0 dark:text-indigo-300"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>

                            <div
                                id="architecture-step-details"
                                className="mt-6 flex min-h-40 items-start justify-between gap-8 border-t border-slate-950/10 pt-6 dark:border-white/10"
                            >
                                <div
                                    className="max-w-3xl"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                                        Этап {activeArchitectureStepIndex + 1}{' '}
                                        из {architectureSteps.length}
                                    </p>
                                    <h3 className="mt-2 text-2xl font-semibold tracking-[-0.025em]">
                                        {activeArchitectureStep.title}
                                    </h3>
                                    <p className="mt-3 text-base leading-7 text-slate-600 dark:text-slate-300">
                                        {activeArchitectureStep.description}
                                    </p>
                                </div>

                                <div className="hidden shrink-0 gap-2 lg:flex">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            activeArchitectureStepIndex === 0
                                        }
                                        onClick={() =>
                                            setActiveArchitectureStepIndex(
                                                (currentIndex) =>
                                                    Math.max(
                                                        0,
                                                        currentIndex - 1,
                                                    ),
                                            )
                                        }
                                        className="rounded-full"
                                    >
                                        ← Назад
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            activeArchitectureStepIndex ===
                                            architectureSteps.length - 1
                                        }
                                        onClick={() =>
                                            setActiveArchitectureStepIndex(
                                                (currentIndex) =>
                                                    Math.min(
                                                        architectureSteps.length -
                                                            1,
                                                        currentIndex + 1,
                                                    ),
                                            )
                                        }
                                        className="rounded-full"
                                    >
                                        Далее →
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="contact"
                    aria-labelledby="contact-title"
                    className="scroll-mt-24 bg-white/35 px-6 py-24 sm:px-10 sm:py-32 dark:bg-white/[0.02]"
                >
                    <div className="mx-auto grid max-w-6xl gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
                        <div className="max-w-xl">
                            <h2
                                id="contact-title"
                                className="text-4xl font-semibold tracking-[-0.04em] sm:text-6xl"
                            >
                                Обсудить проект
                            </h2>
                            <p className="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">
                                Расскажите о задаче и оставьте удобные контакты.
                                Обращение сохранится сразу, а подтверждение
                                придёт на почту.
                            </p>
                        </div>

                        <form
                            className="reveal-on-scroll grid gap-5 rounded-[2rem] border border-slate-950/10 bg-white/70 p-6 shadow-[0_24px_80px_-56px_rgba(15,23,42,0.45)] sm:p-8 dark:border-white/10 dark:bg-white/[0.04]"
                            onSubmit={submit}
                        >
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Имя</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(event) => {
                                            setData('name', event.target.value);
                                            clearErrors('name');
                                        }}
                                        onInvalid={(event) => {
                                            event.preventDefault();
                                            setError(
                                                'name',
                                                'Введите имя из 2–100 букв.',
                                            );
                                        }}
                                        minLength={2}
                                        maxLength={100}
                                        pattern="(?=.*[\p{L}\p{M}])[\p{L}\p{M}\s'’\-]{2,100}"
                                        required
                                        autoComplete="name"
                                        title="От 2 до 100 букв; допустимы пробелы, дефисы и апострофы"
                                        placeholder="Как к вам обращаться"
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={
                                            errors.name
                                                ? 'name-error'
                                                : undefined
                                        }
                                        className="h-12 rounded-xl border-slate-950/10 bg-white/70 px-4 dark:border-white/10 dark:bg-white/5"
                                    />
                                    <InputError
                                        id="name-error"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Телефон</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={(event) => {
                                            setData(
                                                'phone',
                                                event.target.value,
                                            );
                                            clearErrors('phone');
                                        }}
                                        onInvalid={(event) => {
                                            event.preventDefault();
                                            setError(
                                                'phone',
                                                'Введите номер телефона из 10–15 цифр.',
                                            );
                                        }}
                                        maxLength={30}
                                        pattern="\+?(?=(?:\D*\d){10,15}\D*$)[0-9\s\(\)\-]+"
                                        required
                                        autoComplete="tel"
                                        inputMode="tel"
                                        title="От 10 до 15 цифр; допустимы пробелы, скобки и дефисы"
                                        placeholder="+7 999 123-45-67"
                                        aria-invalid={Boolean(errors.phone)}
                                        aria-describedby={
                                            errors.phone
                                                ? 'phone-error'
                                                : undefined
                                        }
                                        className="h-12 rounded-xl border-slate-950/10 bg-white/70 px-4 dark:border-white/10 dark:bg-white/5"
                                    />
                                    <InputError
                                        id="phone-error"
                                        message={errors.phone}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    maxLength={255}
                                    required
                                    autoComplete="email"
                                    placeholder="name@example.com"
                                    aria-invalid={Boolean(errors.email)}
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
                                    className="h-12 rounded-xl border-slate-950/10 bg-white/70 px-4 dark:border-white/10 dark:bg-white/5"
                                />
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="comment">
                                    Расскажите о задаче
                                </Label>
                                <textarea
                                    id="comment"
                                    value={data.comment}
                                    onChange={(event) =>
                                        setData('comment', event.target.value)
                                    }
                                    minLength={10}
                                    maxLength={3000}
                                    required
                                    rows={6}
                                    placeholder="Что нужно разработать, какие сроки и ограничения важны?"
                                    aria-invalid={Boolean(errors.comment)}
                                    aria-describedby={
                                        errors.comment
                                            ? 'comment-error'
                                            : undefined
                                    }
                                    className="min-h-36 w-full resize-y rounded-xl border border-slate-950/10 bg-white/70 px-4 py-3 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-slate-400 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 sm:text-sm dark:border-white/10 dark:bg-white/5 dark:aria-invalid:ring-destructive/40"
                                />
                                <InputError
                                    id="comment-error"
                                    message={errors.comment}
                                />
                            </div>

                            {wasSuccessful && (
                                <div
                                    className="grid gap-1 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-900 dark:text-emerald-200"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <p className="font-semibold">
                                        Обращение принято
                                    </p>
                                    <p className="text-sm leading-6">
                                        Мы сохранили заявку и поставили
                                        уведомления в обработку. Подтверждение
                                        придёт на указанную почту.
                                    </p>
                                </div>
                            )}

                            {hasRequestError && (
                                <div
                                    className="grid gap-1 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-red-900 dark:text-red-200"
                                    role="alert"
                                >
                                    <p className="font-semibold">
                                        Не удалось связаться с сервером
                                    </p>
                                    <p className="text-sm leading-6">
                                        Проверьте соединение и повторите
                                        отправку. Введённые данные остаются в
                                        форме.
                                    </p>
                                </div>
                            )}

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                                className="mt-1 h-12 rounded-full px-7 sm:justify-self-start"
                            >
                                {processing && (
                                    <Spinner
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                )}
                                {processing
                                    ? 'Отправляем…'
                                    : 'Отправить обращение'}
                            </Button>
                        </form>
                    </div>
                </section>
            </main>

            <footer className="border-t border-slate-950/10 bg-[#f4f2ed] px-6 py-6 text-slate-950 sm:px-10 dark:border-white/10 dark:bg-[#080b12] dark:text-white">
                <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 sm:flex-row sm:justify-between">
                    <p className="text-sm font-medium">
                        SanDex · Backend Developer
                    </p>

                    <nav
                        className="flex flex-wrap items-center justify-center gap-2 text-sm text-slate-500 sm:justify-end dark:text-slate-400"
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
                        <a
                            href={health.url()}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-sm transition-colors hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:hover:text-white"
                        >
                            System status
                        </a>
                    </nav>
                </div>
            </footer>
        </>
    );
}

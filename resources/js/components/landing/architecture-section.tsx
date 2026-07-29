import { useEffect, useRef, useState } from 'react';

import ArchitectureStepVisual from '@/components/landing/architecture-step-visual';
import type { EmailPreview } from '@/components/landing/architecture-step-visual';
import { Button } from '@/components/ui/button';

const architectureSteps = [
    {
        label: 'Запрос',
        title: 'Запрос и валидация',
        description:
            'API принимает контактные данные, проверяет поля и ограничивает частоту запросов.',
        guarantee:
            'Ошибки валидации возвращаются с кодом 422, превышение лимита — с 429, а каждый ответ содержит X-Request-ID.',
    },
    {
        label: 'PostgreSQL',
        title: 'PostgreSQL',
        description:
            'Обращение, шаги обработки и outbox-сообщение сохраняются атомарно в одной транзакции.',
        guarantee:
            'Либо сохраняется весь набор данных, либо транзакция полностью откатывается.',
    },
    {
        label: 'Outbox',
        title: 'Outbox',
        description:
            'Задача для фоновой обработки сначала записывается в базу, а затем relay публикует её в очередь.',
        guarantee:
            'Успешно сохранённое обращение не зависит от доступности Redis в момент HTTP-запроса.',
    },
    {
        label: 'Redis',
        title: 'Redis',
        description:
            'Worker обрабатывает задачу с повторными попытками, задержками и восстановлением зависших шагов.',
        guarantee:
            'Lease не даёт двум worker одновременно выполнять один шаг, а scheduler возвращает зависшие задачи в обработку.',
    },
    {
        label: 'AI-анализ',
        title: 'AI-анализ',
        description:
            'OpenRouter получает текст обращения и возвращает только структурированные данные, которые использует приложение.',
        guarantee:
            'После исчерпания попыток срабатывает fallback, и email-обработка всё равно продолжается.',
    },
    {
        label: 'Email',
        title: 'Email',
        description:
            'Владельцу и отправителю уходят независимые письма. При сбое задачи повторяются.',
        guarantee:
            'Сбой одного письма не блокирует второе. Ответ API означает, что обращение принято, а не что вся фоновая обработка завершена.',
    },
] as const;

export default function ArchitectureSection() {
    const architectureTransitionTimer = useRef<number | null>(null);
    const [activeArchitectureStepIndex, setActiveArchitectureStepIndex] =
        useState(0);
    const [isArchitectureStepVisible, setIsArchitectureStepVisible] =
        useState(true);
    const [emailPreview, setEmailPreview] = useState<EmailPreview>('owner');
    const activeArchitectureStep =
        architectureSteps[activeArchitectureStepIndex];

    useEffect(() => {
        return () => {
            if (architectureTransitionTimer.current !== null) {
                window.clearTimeout(architectureTransitionTimer.current);
            }
        };
    }, []);

    function selectArchitectureStep(stepIndex: number): void {
        const nextStepIndex = Math.max(
            0,
            Math.min(architectureSteps.length - 1, stepIndex),
        );

        if (
            nextStepIndex === activeArchitectureStepIndex &&
            isArchitectureStepVisible
        ) {
            return;
        }

        if (architectureTransitionTimer.current !== null) {
            window.clearTimeout(architectureTransitionTimer.current);
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setActiveArchitectureStepIndex(nextStepIndex);
            setIsArchitectureStepVisible(true);

            return;
        }

        setIsArchitectureStepVisible(false);
        architectureTransitionTimer.current = window.setTimeout(() => {
            setActiveArchitectureStepIndex(nextStepIndex);
            setIsArchitectureStepVisible(true);
            architectureTransitionTimer.current = null;
        }, 180);
    }

    return (
        <section
            id="architecture"
            aria-labelledby="architecture-title"
            className="scroll-mt-24 border-y border-slate-950/10 px-6 py-20 sm:px-10 lg:py-24 dark:border-white/10"
        >
            <div className="mx-auto max-w-6xl">
                <div className="max-w-3xl">
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

                <div className="reveal-on-scroll mt-10 rounded-3xl border border-slate-950/10 bg-white/60 p-6 shadow-[0_18px_45px_-38px_rgba(15,23,42,0.28)] sm:p-8 lg:px-10 dark:border-white/10 dark:bg-white/[0.035]">
                    <ol
                        className="flex flex-col items-stretch gap-3 lg:flex-row lg:items-center"
                        aria-label="Путь обработки обращения"
                    >
                        {architectureSteps.map((step, stepIndex) => {
                            const isActive =
                                stepIndex === activeArchitectureStepIndex;

                            return (
                                <li key={step.title} className="contents">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            selectArchitectureStep(stepIndex)
                                        }
                                        aria-pressed={isActive}
                                        aria-controls="architecture-step-details"
                                        className={`flex min-h-20 flex-1 cursor-pointer items-center justify-center gap-2 rounded-2xl border px-3 text-center text-sm font-medium transition-[background-color,border-color,color,box-shadow,transform] duration-200 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 motion-reduce:transition-none motion-reduce:hover:translate-y-0 ${
                                            isActive
                                                ? 'border-indigo-500/30 bg-indigo-500/[0.07] text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-400/10 dark:text-indigo-200'
                                                : 'border-slate-950/10 bg-white/70 text-slate-700 hover:border-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-indigo-400/30'
                                        }`}
                                    >
                                        <span
                                            className={`flex size-6 shrink-0 items-center justify-center rounded-full text-xs ${
                                                isActive
                                                    ? 'bg-indigo-600 text-white dark:bg-indigo-400 dark:text-slate-950'
                                                    : 'bg-slate-950/5 text-slate-500 dark:bg-white/10 dark:text-slate-300'
                                            }`}
                                            aria-hidden="true"
                                        >
                                            {stepIndex + 1}
                                        </span>
                                        {step.label}
                                    </button>

                                    {stepIndex <
                                        architectureSteps.length - 1 && (
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
                        className="mt-5 flex min-h-[40rem] flex-col border-t border-slate-950/10 pt-5 sm:min-h-[38rem] lg:min-h-[34rem] dark:border-white/10"
                    >
                        <div
                            className={`transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-none ${
                                isArchitectureStepVisible
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-1 opacity-0'
                            }`}
                        >
                            <div role="status" aria-live="polite">
                                <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                                    Этап {activeArchitectureStepIndex + 1} из{' '}
                                    {architectureSteps.length}
                                </p>
                                <h3 className="mt-2 text-2xl font-semibold tracking-[-0.025em]">
                                    {activeArchitectureStep.title}
                                </h3>
                                <p className="mt-3 text-base leading-7 text-slate-600 dark:text-slate-300">
                                    {activeArchitectureStep.description}
                                </p>
                            </div>

                            <ArchitectureStepVisual
                                stepIndex={activeArchitectureStepIndex}
                                emailPreview={emailPreview}
                                onEmailPreviewChange={setEmailPreview}
                            />

                            <div className="mt-5 border-l-2 border-indigo-500/70 pl-4">
                                <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                                    Гарантия
                                </p>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {activeArchitectureStep.guarantee}
                                </p>
                            </div>
                        </div>

                        <nav
                            className="mt-auto flex items-center justify-between gap-3 pt-6"
                            aria-label="Навигация по шагам"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                disabled={activeArchitectureStepIndex === 0}
                                onClick={() =>
                                    selectArchitectureStep(
                                        activeArchitectureStepIndex - 1,
                                    )
                                }
                                className="rounded-full px-4 sm:px-6"
                            >
                                ← Назад
                            </Button>
                            <p
                                className="hidden text-xs text-slate-500 sm:block dark:text-slate-400"
                                aria-hidden="true"
                            >
                                {activeArchitectureStepIndex + 1} /{' '}
                                {architectureSteps.length}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    activeArchitectureStepIndex ===
                                    architectureSteps.length - 1
                                }
                                onClick={() =>
                                    selectArchitectureStep(
                                        activeArchitectureStepIndex + 1,
                                    )
                                }
                                className="rounded-full px-4 sm:px-6"
                            >
                                Далее →
                            </Button>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    );
}

import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { health } from '@/routes/api';

type HealthResponse = {
    status: 'ok' | 'degraded';
    services: {
        database: 'ok' | 'error';
        redis: 'ok' | 'error';
        ai: 'configured' | 'not_configured';
        mail: 'configured' | 'not_configured';
    };
    timestamp: string;
};

type StatusTone = 'success' | 'warning' | 'danger' | 'neutral';

const toneClasses: Record<StatusTone, string> = {
    success:
        'border-emerald-500/25 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
    warning:
        'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:text-amber-200',
    danger: 'border-red-500/25 bg-red-500/10 text-red-800 dark:text-red-200',
    neutral:
        'border-slate-950/10 bg-white/60 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200',
};

function formattedTimestamp(timestamp: string): string {
    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(timestamp));
}

export default function Status() {
    const initialRequestStarted = useRef(false);
    const [healthResponse, setHealthResponse] = useState<HealthResponse | null>(
        null,
    );
    const [httpStatus, setHttpStatus] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [hasNetworkError, setHasNetworkError] = useState(false);

    const refreshStatus = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        setHasNetworkError(false);

        try {
            const response = await fetch(health.url(), {
                headers: {
                    Accept: 'application/json',
                },
                cache: 'no-store',
            });
            const payload = (await response.json()) as HealthResponse;

            setHealthResponse(payload);
            setHttpStatus(response.status);
        } catch {
            setHealthResponse(null);
            setHttpStatus(null);
            setHasNetworkError(true);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (initialRequestStarted.current) {
            return;
        }

        initialRequestStarted.current = true;
        void refreshStatus();
    }, [refreshStatus]);

    const allChecksOperational =
        httpStatus === 200 &&
        healthResponse?.status === 'ok' &&
        healthResponse.services.database === 'ok' &&
        healthResponse.services.redis === 'ok' &&
        healthResponse.services.ai === 'configured' &&
        healthResponse.services.mail === 'configured';

    let summaryTitle = 'Проверяем состояние';
    let summaryText = 'Получаем актуальные данные от API.';
    let summaryTone: StatusTone = 'neutral';

    if (hasNetworkError) {
        summaryTitle = 'Не удалось получить статус';
        summaryText =
            'API не ответил или вернул данные в неожиданном формате. Попробуйте обновить проверку.';
        summaryTone = 'danger';
    } else if (httpStatus === 503) {
        summaryTitle = 'Есть проблемы с инфраструктурой';
        summaryText =
            'API доступен, но PostgreSQL, Redis или обязательная конфигурация требуют внимания.';
        summaryTone = 'danger';
    } else if (healthResponse && allChecksOperational) {
        summaryTitle = 'Сервис работает';
        summaryText =
            'API отвечает, PostgreSQL и Redis доступны, обязательная конфигурация найдена.';
        summaryTone = 'success';
    } else if (healthResponse) {
        summaryTitle = 'Частичная деградация';
        summaryText = 'Один или несколько checks требуют внимания.';
        summaryTone = 'warning';
    }

    const configurationReady =
        healthResponse?.services.ai === 'configured' &&
        healthResponse.services.mail === 'configured';

    const serviceCards = [
        {
            name: 'API',
            value: healthResponse
                ? 'Работает'
                : isLoading
                  ? 'Проверяется'
                  : 'Недоступен',
            tone: healthResponse
                ? ('success' as const)
                : isLoading
                  ? ('neutral' as const)
                  : ('danger' as const),
        },
        {
            name: 'PostgreSQL',
            value:
                healthResponse?.services.database === 'ok'
                    ? 'Доступен'
                    : healthResponse
                      ? 'Недоступен'
                      : 'Нет данных',
            tone:
                healthResponse?.services.database === 'ok'
                    ? ('success' as const)
                    : healthResponse
                      ? ('danger' as const)
                      : ('neutral' as const),
        },
        {
            name: 'Redis',
            value:
                healthResponse?.services.redis === 'ok'
                    ? 'Доступен'
                    : healthResponse
                      ? 'Недоступен'
                      : 'Нет данных',
            tone:
                healthResponse?.services.redis === 'ok'
                    ? ('success' as const)
                    : healthResponse
                      ? ('danger' as const)
                      : ('neutral' as const),
        },
        {
            name: 'Конфигурация',
            value: configurationReady
                ? 'Настроена'
                : healthResponse
                  ? 'Требует настройки'
                  : 'Нет данных',
            tone: configurationReady
                ? ('success' as const)
                : healthResponse
                  ? ('warning' as const)
                  : ('neutral' as const),
        },
    ];

    return (
        <>
            <Head title="System status" />

            <main className="min-h-screen bg-[#f4f2ed] px-6 py-6 text-slate-950 sm:px-10 sm:py-8 dark:bg-[#080b12] dark:text-white">
                <div className="mx-auto max-w-4xl">
                    <header>
                        <Link
                            href={home()}
                            className="text-sm text-slate-500 transition-colors hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:text-slate-400 dark:hover:text-white"
                        >
                            ← На главную
                        </Link>
                    </header>

                    <section
                        className="py-10 sm:py-14"
                        aria-labelledby="status-title"
                    >
                        <p className="text-sm font-medium tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-300">
                            Infrastructure
                        </p>
                        <h1
                            id="status-title"
                            className="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl"
                        >
                            System status
                        </h1>
                        <p className="mt-4 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                            Техническое состояние API и его обязательных
                            зависимостей.
                        </p>

                        <div
                            className={`mt-8 rounded-2xl border p-4 sm:p-5 ${toneClasses[summaryTone]}`}
                            role="status"
                            aria-live="polite"
                        >
                            <div className="flex items-start gap-3">
                                <span
                                    className="mt-1.5 size-2 shrink-0 rounded-full bg-current"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        {summaryTitle}
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 opacity-75">
                                        {summaryText}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {serviceCards.map((service) => (
                                <article
                                    key={service.name}
                                    className="rounded-xl border border-slate-950/10 bg-white/60 p-4 dark:border-white/10 dark:bg-white/5"
                                >
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        {service.name}
                                    </p>
                                    <p className="mt-2 flex items-center gap-2 font-semibold">
                                        <span
                                            className={`size-2 rounded-full ${
                                                service.tone === 'success'
                                                    ? 'bg-emerald-500'
                                                    : service.tone === 'warning'
                                                      ? 'bg-amber-500'
                                                      : service.tone ===
                                                          'danger'
                                                        ? 'bg-red-500'
                                                        : 'bg-slate-400'
                                            }`}
                                            aria-hidden="true"
                                        />
                                        {service.value}
                                    </p>
                                </article>
                            ))}
                        </div>

                        <section className="mt-4 rounded-2xl border border-slate-950/10 bg-white/60 p-5 dark:border-white/10 dark:bg-white/5">
                            <h2 className="text-lg font-semibold">
                                Границы проверки
                            </h2>
                            <dl className="mt-3 divide-y divide-slate-950/10 text-sm dark:divide-white/10">
                                <div className="grid gap-1 py-3 first:pt-0 sm:grid-cols-[10rem_1fr] sm:gap-6">
                                    <dt className="font-medium">
                                        Queue worker
                                    </dt>
                                    <dd className="text-slate-600 dark:text-slate-300">
                                        Не проверяется этим endpoint.
                                    </dd>
                                </div>
                                <div className="grid gap-1 py-3 sm:grid-cols-[10rem_1fr] sm:gap-6">
                                    <dt className="font-medium">AI provider</dt>
                                    <dd className="text-slate-600 dark:text-slate-300">
                                        {healthResponse?.services.ai ===
                                        'configured'
                                            ? 'Конфигурация найдена; доступность проверяется при обработке обращения.'
                                            : 'Конфигурация не найдена.'}
                                    </dd>
                                </div>
                                <div className="grid gap-1 py-3 last:pb-0 sm:grid-cols-[10rem_1fr] sm:gap-6">
                                    <dt className="font-medium">
                                        Email provider
                                    </dt>
                                    <dd className="text-slate-600 dark:text-slate-300">
                                        {healthResponse?.services.mail ===
                                        'configured'
                                            ? 'Конфигурация найдена; доступность проверяется при отправке письма.'
                                            : 'Конфигурация не найдена.'}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <div className="mt-5 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-slate-500 dark:text-slate-400">
                                {healthResponse ? (
                                    <p>
                                        Последняя проверка:{' '}
                                        {formattedTimestamp(
                                            healthResponse.timestamp,
                                        )}
                                    </p>
                                ) : (
                                    <p>Время проверки пока недоступно.</p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <a
                                    href={health.url()}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200"
                                >
                                    Посмотреть raw JSON
                                </a>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={isLoading}
                                    onClick={() => void refreshStatus()}
                                    className="rounded-full"
                                >
                                    {isLoading && (
                                        <Spinner
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Обновить
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}

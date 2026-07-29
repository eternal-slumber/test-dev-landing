import { useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { SubmitEvent } from 'react';

import { store } from '@/actions/App/Http/Controllers/Api/ContactController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatPhoneInput } from '@/lib/phone';

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

type ContactRequestError = {
    title: string;
    message: string;
};

const aiNoticeStorageKey = 'ai-notice-dismissed-v1';

function contactRequestError(
    status: number,
    retryAfter?: string,
): ContactRequestError {
    if (status === 409) {
        return {
            title: 'Запрос уже использован',
            message:
                'Данные формы отличаются от первой отправки. Обновите страницу и отправьте обращение заново.',
        };
    }

    if (status === 429) {
        const seconds = Number.parseInt(retryAfter ?? '', 10);

        return {
            title: 'Слишком много попыток',
            message:
                Number.isNaN(seconds) || seconds < 1
                    ? 'Повторите отправку немного позже.'
                    : `Повторите отправку через ${seconds} с.`,
        };
    }

    if (status === 500) {
        return {
            title: 'Сервис временно недоступен',
            message:
                'Попробуйте отправить форму позже. Введённые данные остаются в форме.',
        };
    }

    return {
        title: `Ошибка сервера (${status})`,
        message:
            'Попробуйте отправить форму позже. Введённые данные остаются в форме.',
    };
}

export default function ContactSection() {
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
    const [requestError, setRequestError] =
        useState<ContactRequestError | null>(null);
    const [showAiNotice, setShowAiNotice] = useState(false);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            try {
                setShowAiNotice(
                    window.localStorage.getItem(aiNoticeStorageKey) !== 'true',
                );
            } catch {
                setShowAiNotice(true);
            }
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    function dismissAiNotice(): void {
        setShowAiNotice(false);

        try {
            window.localStorage.setItem(aiNoticeStorageKey, 'true');
        } catch {
            // Storage may be blocked; dismissal still works for this page.
        }
    }

    function submit(event: SubmitEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (isSubmitting.current) {
            return;
        }

        isSubmitting.current = true;
        setRequestError(null);

        void post(store.url(), {
            headers: {
                'Idempotency-Key': idempotencyKey.current,
            },
            onSuccess: () => {
                reset();
                idempotencyKey.current = crypto.randomUUID();
            },
            onError: () => {
                setRequestError({
                    title: 'Проверьте данные',
                    message: 'Исправьте выделенные поля и повторите отправку.',
                });
            },
            onHttpException: (response) => {
                setRequestError(
                    contactRequestError(
                        response.status,
                        response.headers['retry-after'],
                    ),
                );
            },
            onNetworkError: () => {
                setRequestError({
                    title: 'Нет соединения с сервером',
                    message:
                        'Проверьте интернет и повторите отправку. Введённые данные остаются в форме.',
                });
            },
            onFinish: () => {
                isSubmitting.current = false;
            },
        }).catch(() => undefined);
    }

    return (
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
                        Обращение сохранится сразу, а подтверждение придёт на
                        почту.
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
                                        'Введите имя длиной от 2 до 100 символов.',
                                    );
                                }}
                                minLength={2}
                                maxLength={100}
                                required
                                autoComplete="name"
                                title="От 2 до 100 символов"
                                placeholder="Как к вам обращаться"
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                                className="h-12 rounded-xl border-slate-950/10 bg-white/70 px-4 dark:border-white/10 dark:bg-white/5"
                            />
                            <InputError id="name-error" message={errors.name} />
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
                                        formatPhoneInput(event.target.value),
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
                                    errors.phone ? 'phone-error' : undefined
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
                            onChange={(event) => {
                                setData('email', event.target.value);
                                clearErrors('email');
                            }}
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
                        <InputError id="email-error" message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="comment">Расскажите о задаче</Label>
                        <textarea
                            id="comment"
                            value={data.comment}
                            onChange={(event) => {
                                setData('comment', event.target.value);
                                clearErrors('comment');
                            }}
                            minLength={10}
                            maxLength={3000}
                            required
                            rows={6}
                            placeholder="Что нужно разработать, какие сроки и ограничения важны?"
                            aria-invalid={Boolean(errors.comment)}
                            aria-describedby={
                                errors.comment
                                    ? 'comment-ai-caption comment-error'
                                    : 'comment-ai-caption'
                            }
                            className="min-h-36 w-full resize-y rounded-xl border border-slate-950/10 bg-white/70 px-4 py-3 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-slate-400 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 sm:text-sm dark:border-white/10 dark:bg-white/5 dark:aria-invalid:ring-destructive/40"
                        />
                        <InputError
                            id="comment-error"
                            message={errors.comment}
                        />
                        <p
                            id="comment-ai-caption"
                            className={
                                showAiNotice
                                    ? 'sr-only'
                                    : 'text-xs leading-5 text-slate-500 dark:text-slate-400'
                            }
                        >
                            Комментарий может обрабатываться внешним
                            AI-провайдером.
                        </p>
                        <div
                            className={`grid transition-[grid-template-rows,opacity] duration-200 ease-out motion-reduce:transition-none ${
                                showAiNotice
                                    ? 'grid-rows-[1fr] opacity-100'
                                    : 'grid-rows-[0fr] opacity-0'
                            }`}
                            aria-hidden={!showAiNotice}
                        >
                            <div className="overflow-hidden">
                                <div className="flex flex-col items-start gap-3 rounded-xl border border-amber-700/10 bg-amber-500/[0.06] px-4 py-3 text-sm leading-6 text-slate-600 sm:flex-row sm:items-center dark:border-amber-200/10 dark:bg-amber-200/[0.04] dark:text-slate-300">
                                    <p className="flex-1">
                                        Текст сообщения может обрабатываться
                                        внешним AI-провайдером для классификации
                                        обращения. Не указывайте пароли,
                                        платёжные данные и другую
                                        конфиденциальную информацию.
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!showAiNotice}
                                        tabIndex={showAiNotice ? 0 : -1}
                                        onClick={dismissAiNotice}
                                        className="h-9 shrink-0 rounded-full border-slate-950/10 bg-transparent px-4 dark:border-white/10 dark:hover:bg-white/5"
                                    >
                                        Понятно
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {wasSuccessful && (
                        <div
                            className="grid gap-1 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-900 dark:text-emerald-200"
                            role="status"
                            aria-live="polite"
                        >
                            <p className="font-semibold">Обращение принято</p>
                            <p className="text-sm leading-6">
                                Мы сохранили заявку и поставили уведомления в
                                обработку. Подтверждение придёт на указанную
                                почту.
                            </p>
                        </div>
                    )}

                    {requestError && (
                        <div
                            className="grid gap-1 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-red-900 dark:text-red-200"
                            role="alert"
                        >
                            <p className="font-semibold">
                                {requestError.title}
                            </p>
                            <p className="text-sm leading-6">
                                {requestError.message}
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
                            <Spinner aria-hidden="true" className="size-4" />
                        )}
                        {processing ? 'Отправляем…' : 'Отправить обращение'}
                    </Button>
                </form>
            </div>
        </section>
    );
}

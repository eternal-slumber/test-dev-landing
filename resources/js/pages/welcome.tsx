import { Head, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import type { SubmitEvent } from 'react';

import { store } from '@/actions/App/Http/Controllers/Api/ContactController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

export default function Welcome() {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        response,
        wasSuccessful,
        reset,
    } = useHttp<ContactForm, ContactResponse>({
        name: '',
        phone: '',
        email: '',
        comment: '',
    });
    const [requestError, setRequestError] = useState<string | null>(null);

    function submit(event: SubmitEvent<HTMLFormElement>): void {
        event.preventDefault();
        setRequestError(null);

        void post(store.url(), {
            onSuccess: () => reset(),
        }).catch(() => {
            setRequestError(
                'Не удалось отправить обращение. Попробуйте ещё раз.',
            );
        });
    }

    return (
        <>
            <Head title="Проверка" />

            <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
                <Card className="w-full max-w-xl">
                    <CardHeader>
                        <CardTitle>Форма обращения</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <form className="grid gap-5" onSubmit={submit}>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Имя</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    minLength={2}
                                    maxLength={100}
                                    required
                                    autoComplete="name"
                                    aria-invalid={Boolean(errors.name)}
                                    aria-describedby={
                                        errors.name ? 'name-error' : undefined
                                    }
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
                                    onChange={(event) =>
                                        setData('phone', event.target.value)
                                    }
                                    maxLength={30}
                                    required
                                    autoComplete="tel"
                                    placeholder="+7 999 123-45-67"
                                    aria-invalid={Boolean(errors.phone)}
                                    aria-describedby={
                                        errors.phone ? 'phone-error' : undefined
                                    }
                                />
                                <InputError
                                    id="phone-error"
                                    message={errors.phone}
                                />
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
                                    aria-invalid={Boolean(errors.email)}
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
                                />
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="comment">Комментарий</Label>
                                <textarea
                                    id="comment"
                                    value={data.comment}
                                    onChange={(event) =>
                                        setData('comment', event.target.value)
                                    }
                                    minLength={10}
                                    maxLength={3000}
                                    required
                                    rows={5}
                                    aria-invalid={Boolean(errors.comment)}
                                    aria-describedby={
                                        errors.comment
                                            ? 'comment-error'
                                            : undefined
                                    }
                                    className="min-h-28 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                />
                                <InputError
                                    id="comment-error"
                                    message={errors.comment}
                                />
                            </div>

                            {wasSuccessful && response && (
                                <p
                                    className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    role="status"
                                >
                                    {response.message}. Номер обращения:{' '}
                                    {response.data.id}.
                                </p>
                            )}

                            {requestError && (
                                <p
                                    className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300"
                                    role="alert"
                                >
                                    {requestError}
                                </p>
                            )}

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Отправляем…'
                                    : 'Отправить обращение'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

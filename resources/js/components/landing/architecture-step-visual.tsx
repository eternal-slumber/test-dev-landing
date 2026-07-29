export type EmailPreview = 'owner' | 'sender';

export default function ArchitectureStepVisual({
    stepIndex,
    emailPreview,
    onEmailPreviewChange,
}: {
    stepIndex: number;
    emailPreview: EmailPreview;
    onEmailPreviewChange: (preview: EmailPreview) => void;
}) {
    if (stepIndex === 0) {
        return (
            <div className="mt-5 grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
                <pre className="overflow-x-auto rounded-2xl bg-slate-950 p-5 font-mono text-xs leading-6 text-slate-200">
                    {`POST /api/contact
Idempotency-Key: 3d1f3c42-...

{
  "name": "Алексей",
  "phone": "+7 (999) 123-45-67",
  "email": "alex@example.com",
  "comment": "Нужна интеграция с CRM"
}`}
                </pre>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div className="rounded-2xl border border-slate-950/10 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                            Валидация
                        </p>
                        <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Имя, телефон, email и сообщение проверяются до
                            сохранения.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-slate-950/10 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                            Idempotency
                        </p>
                        <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            UUID можно передать в заголовке. Без него backend
                            создаст ключ сам.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    if (stepIndex === 1) {
        return (
            <div className="mt-5 rounded-2xl border border-slate-950/10 bg-slate-950/[0.03] p-5 dark:border-white/10 dark:bg-white/5">
                <p className="font-mono text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                    database transaction
                </p>
                <pre className="mt-4 overflow-x-auto font-mono text-sm leading-7 text-slate-700 dark:text-slate-200">
                    {`contact
├── contact_steps × 3
│   ├── AI classification
│   ├── owner email
│   └── confirmation email
└── outbox_messages
    └── process contact`}
                </pre>
            </div>
        );
    }

    if (stepIndex === 2) {
        return (
            <div className="mt-5">
                <div className="flex flex-col items-stretch gap-3 lg:flex-row lg:items-center">
                    {[
                        'Транзакция завершена',
                        'Сообщение не опубликовано',
                        'Relay забирает запись',
                        'Задача опубликована',
                    ].map((item, itemIndex, items) => (
                        <div key={item} className="contents">
                            <div className="flex min-h-20 flex-1 items-center justify-center rounded-2xl border border-slate-950/10 bg-white/70 px-4 text-center text-sm font-medium dark:border-white/10 dark:bg-white/5">
                                {item}
                            </div>
                            {itemIndex < items.length - 1 && (
                                <span
                                    className="rotate-90 self-center text-xl text-indigo-500 lg:rotate-0 dark:text-indigo-300"
                                    aria-hidden="true"
                                >
                                    →
                                </span>
                            )}
                        </div>
                    ))}
                </div>
                <p className="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Если публикация не удалась, relay повторит её с задержкой.
                    Outbox обеспечивает надёжную доставку задачи, но не обещает
                    exactly once.
                </p>
            </div>
        );
    }

    if (stepIndex === 3) {
        return (
            <div className="mt-5">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Попытка 1', 'Сразу'],
                        ['Попытка 2', 'Через 10 сек'],
                        ['Попытка 3', 'Через 60 сек'],
                        ['Попытка 4', 'Через 300 сек'],
                    ].map(([attempt, delay]) => (
                        <div
                            key={attempt}
                            className="rounded-2xl border border-slate-950/10 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <p className="font-medium">{attempt}</p>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {delay}
                            </p>
                        </div>
                    ))}
                </div>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl bg-indigo-500/10 p-4 text-sm leading-6 text-indigo-800 dark:text-indigo-200">
                        После четвёртой ошибки шаг переходит в fallback или
                        получает статус failed.
                    </div>
                    <div className="rounded-2xl bg-slate-950/[0.04] p-4 text-sm leading-6 text-slate-600 dark:bg-white/5 dark:text-slate-300">
                        Lease действует 120 секунд. Scheduler находит зависшие
                        шаги и возвращает их в очередь.
                    </div>
                </div>
            </div>
        );
    }

    if (stepIndex === 4) {
        return (
            <div className="mt-5">
                <div className="grid gap-3 lg:grid-cols-[1fr_auto_1fr] lg:items-stretch">
                    <div className="rounded-2xl border border-slate-950/10 bg-white/70 p-5 dark:border-white/10 dark:bg-white/5">
                        <p className="text-xs font-medium tracking-widest text-indigo-600 uppercase dark:text-indigo-300">
                            Вход
                        </p>
                        <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            «Нужен backend формы с очередями, AI-анализом и
                            отправкой писем».
                        </p>
                    </div>
                    <span
                        className="rotate-90 self-center text-xl text-indigo-500 lg:rotate-0 dark:text-indigo-300"
                        aria-hidden="true"
                    >
                        →
                    </span>
                    <pre className="overflow-x-auto rounded-2xl bg-slate-950 p-5 font-mono text-xs leading-6 text-slate-200">
                        {`{
  "category": "project",
  "summary": "Разработка формы...",
  "suggested_reply": "Готов обсудить..."
}`}
                    </pre>
                </div>
                <p className="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    В интерфейсе показаны только реально сохраняемые поля:
                    категория, summary и черновик ответа.
                </p>
            </div>
        );
    }

    return (
        <div className="mt-5">
            <div
                className="inline-flex rounded-full border border-slate-950/10 bg-white/70 p-1 dark:border-white/10 dark:bg-white/5"
                role="group"
                aria-label="Получатель письма"
            >
                <button
                    type="button"
                    aria-pressed={emailPreview === 'owner'}
                    onClick={() => onEmailPreviewChange('owner')}
                    className={`cursor-pointer rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 motion-reduce:transition-none ${
                        emailPreview === 'owner'
                            ? 'bg-indigo-600 text-white'
                            : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white'
                    }`}
                >
                    Владельцу
                </button>
                <button
                    type="button"
                    aria-pressed={emailPreview === 'sender'}
                    onClick={() => onEmailPreviewChange('sender')}
                    className={`cursor-pointer rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 motion-reduce:transition-none ${
                        emailPreview === 'sender'
                            ? 'bg-indigo-600 text-white'
                            : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white'
                    }`}
                >
                    Отправителю
                </button>
            </div>

            <div
                id="email-preview"
                aria-live="polite"
                className="mt-4 max-h-64 overflow-y-auto rounded-2xl border border-slate-950/10 bg-white/70 p-5 dark:border-white/10 dark:bg-white/5"
            >
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-slate-500 dark:text-slate-400">Кому</dt>
                    <dd>
                        {emailPreview === 'owner'
                            ? 'owner@example.com'
                            : 'alexey@example.com'}
                    </dd>
                    <dt className="text-slate-500 dark:text-slate-400">Тема</dt>
                    <dd>
                        {emailPreview === 'owner'
                            ? 'Новое обращение #18'
                            : 'Ваше обращение получено'}
                    </dd>
                </dl>
                <article className="mt-4 border-t border-slate-950/10 pt-4 text-sm leading-6 text-slate-600 dark:border-white/10 dark:text-slate-300">
                    {emailPreview === 'owner' ? (
                        <div className="grid gap-4">
                            <h4 className="text-lg font-semibold text-slate-950 dark:text-white">
                                Новое обращение #18
                            </h4>
                            <p>
                                <strong>Категория:</strong> Проект
                            </p>
                            <div>
                                <h5 className="font-semibold text-slate-950 dark:text-white">
                                    Краткое содержание
                                </h5>
                                <p className="mt-1">
                                    Команде нужен надёжный backend формы
                                    обратной связи с очередями,
                                    AI-классификацией и email-уведомлениями.
                                </p>
                            </div>
                            <div>
                                <h5 className="font-semibold text-slate-950 dark:text-white">
                                    Контакты
                                </h5>
                                <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3">
                                    <dt>Имя:</dt>
                                    <dd>Алексей Смирнов</dd>
                                    <dt>Email:</dt>
                                    <dd>alexey@example.com</dd>
                                    <dt>Телефон:</dt>
                                    <dd>+7 (999) 123-45-67</dd>
                                </dl>
                            </div>
                            <div>
                                <h5 className="font-semibold text-slate-950 dark:text-white">
                                    Предложенный ответ
                                </h5>
                                <p className="mt-2 rounded-xl bg-slate-950/[0.04] p-3 dark:bg-white/5">
                                    Здравствуйте, Алексей! Спасибо за подробное
                                    описание. Могу спроектировать API, надёжную
                                    очередь обработки и AI-интеграцию. Предлагаю
                                    обсудить объём работ и требования к
                                    уведомлениям.
                                </p>
                                <span className="mt-3 inline-flex rounded-full bg-indigo-600 px-4 py-2 font-medium text-white">
                                    Ответить отправителю
                                </span>
                            </div>
                            <div>
                                <h5 className="font-semibold text-slate-950 dark:text-white">
                                    Полный текст обращения
                                </h5>
                                <p className="mt-2 rounded-xl bg-slate-950/[0.04] p-3 dark:bg-white/5">
                                    Нужно разработать backend формы обратной
                                    связи: сохранить обращения в PostgreSQL,
                                    добавить очереди, AI-классификацию и
                                    email-уведомления. Важно исключить дубликаты
                                    при повторной отправке.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            <h4 className="text-lg font-semibold text-slate-950 dark:text-white">
                                Мы получили ваше обращение
                            </h4>
                            <p>Здравствуйте, Алексей!</p>
                            <p>
                                Спасибо за обращение. Мы получили ваше сообщение
                                и свяжемся с вами.
                            </p>
                            <div>
                                <h5 className="font-semibold text-slate-950 dark:text-white">
                                    Ваш комментарий
                                </h5>
                                <p className="mt-2 rounded-xl bg-slate-950/[0.04] p-3 dark:bg-white/5">
                                    Нужно разработать backend формы обратной
                                    связи: сохранить обращения в PostgreSQL,
                                    добавить очереди, AI-классификацию и
                                    email-уведомления. Важно исключить дубликаты
                                    при повторной отправке.
                                </p>
                            </div>
                        </div>
                    )}
                </article>
            </div>
        </div>
    );
}

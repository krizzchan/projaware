import SmsReport, { type SmsResults } from '@/components/sms-report';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Pages<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null };
type Broadcast = {
    id: number;
    title: string;
    message: string;
    target: string;
    recipient_count: number;
    is_test: boolean;
    created_at: string;
    accepted_count: number;
    simulated_count: number;
    queued_count: number;
    processing_count: number;
    unknown_count: number;
    failed_count: number;
    skipped_count: number;
    cancelled_count: number;
    can_cancel: boolean;
};
interface Props {
    smsRecipients: SmsResults;
    smsStatus: string;
    smsSummary: Record<string, number>;
    reportDates: { date_from: string; date_to: string };
    reportSearch: { recipient_search: string; message_search: string };
    reportAudience: { category_id: string; barangay_id: string };
    categories: { id: number; name: string }[];
    barangays: { id: number; name: string }[];
    broadcasts: Pages<Broadcast>;
    auditLogs: { id: number; action: string; subject_type: string; subject_id: number | null; created_at: string }[];
}
const input =
    'h-10 w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
function Pagination({ pages }: { pages: { prev_page_url: string | null; next_page_url: string | null } }) {
    return (
        <nav aria-label="Pagination" className="mt-4 flex justify-end gap-3 text-sm">
            {pages.prev_page_url && (
                <Link className="text-blue-700" href={pages.prev_page_url}>
                    Previous
                </Link>
            )}
            {pages.next_page_url && (
                <Link className="text-blue-700" href={pages.next_page_url}>
                    Next
                </Link>
            )}
        </nav>
    );
}
export default function Reports({
    broadcasts,
    auditLogs,
    smsRecipients,
    smsStatus,
    smsSummary,
    reportDates,
    reportSearch,
    reportAudience,
    categories,
    barangays,
}: Props) {
    const [cancellingBroadcastId, setCancellingBroadcastId] = useState<number | null>(null);
    return (
        <AppLayout>
            <Head title="Reports & Broadcast History" />
            <div className="space-y-6">
                <header className="flex flex-wrap justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Reports & Broadcast History</h1>
                        <p className="mt-2 text-sm text-slate-500">Track SMS results, broadcast history, and operational activity.</p>
                    </div>
                </header>
                <form
                    key={JSON.stringify([reportDates, reportSearch, reportAudience])}
                    onSubmit={(event) => {
                        event.preventDefault();
                        const dates = new FormData(event.currentTarget);
                        router.get(
                            route('reports.index'),
                            {
                                sms_status: smsStatus,
                                date_from: String(dates.get('date_from') ?? ''),
                                date_to: String(dates.get('date_to') ?? ''),
                                recipient_search: String(dates.get('recipient_search') ?? ''),
                                message_search: String(dates.get('message_search') ?? ''),
                                category_id: String(dates.get('category_id') ?? ''),
                                barangay_id: String(dates.get('barangay_id') ?? ''),
                            },
                            { preserveScroll: true },
                        );
                    }}
                    className="grid grid-cols-1 items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-12"
                    aria-label="Report filters"
                >
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">Recipient</span>
                        <input
                            type="search"
                            name="recipient_search"
                            maxLength={100}
                            defaultValue={reportSearch.recipient_search}
                            placeholder="Search contact name"
                            className={input}
                        />
                    </label>
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">Message title</span>
                        <input
                            type="search"
                            name="message_search"
                            maxLength={150}
                            defaultValue={reportSearch.message_search}
                            placeholder="Search broadcast title"
                            className={input}
                        />
                    </label>
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">Category</span>
                        <select name="category_id" defaultValue={reportAudience.category_id} className={input}>
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">Barangay</span>
                        <select name="barangay_id" defaultValue={reportAudience.barangay_id} className={input}>
                            <option value="">All barangays</option>
                            <option value="none">Not applicable</option>
                            {barangays.map((barangay) => (
                                <option key={barangay.id} value={barangay.id}>
                                    {barangay.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">From</span>
                        <input type="date" name="date_from" defaultValue={reportDates.date_from} className={input} />
                    </label>
                    <label className="min-w-0 space-y-1.5 text-xs font-medium text-slate-600 xl:col-span-3">
                        <span className="block">To</span>
                        <input type="date" name="date_to" defaultValue={reportDates.date_to} className={input} />
                    </label>
                    <div className="flex min-w-0 gap-2 sm:col-span-2 xl:col-span-6 xl:justify-end">
                        <button
                            type="submit"
                            className="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-blue-600 px-3 text-xs font-semibold whitespace-nowrap text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 xl:min-w-32 xl:flex-none"
                        >
                            Apply filters
                        </button>
                        {(reportDates.date_from ||
                            reportDates.date_to ||
                            reportSearch.recipient_search ||
                            reportSearch.message_search ||
                            reportAudience.category_id ||
                            reportAudience.barangay_id) && (
                            <Link
                                href={route('reports.index', { sms_status: smsStatus })}
                                preserveScroll
                                className="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
                            >
                                Clear
                            </Link>
                        )}
                    </div>
                    <p className="col-span-full text-xs leading-5 text-slate-500">
                        Dates use Philippine time. Category and barangay reflect the contact’s current directory details.
                    </p>
                </form>
                <SmsReport
                    results={smsRecipients}
                    status={smsStatus}
                    summary={smsSummary}
                    dates={reportDates}
                    search={reportSearch}
                    audience={reportAudience}
                />
                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold">SMS broadcast results</h2>
                    <p className="mt-2 text-xs leading-5 text-slate-500">
                        Accepted means the provider accepted the SMS request. Handset delivery is unverified. Check unknown results with the provider
                        before resending.
                    </p>
                    <div className="mt-5 space-y-4">
                        {broadcasts.data.length ? (
                            broadcasts.data.map((item) => (
                                <article key={item.id} className="rounded-lg border border-slate-200 p-4">
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <h3 className="font-medium">
                                            {item.title}{' '}
                                            {item.is_test && (
                                                <span className="ml-2 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700">TEST</span>
                                            )}
                                        </h3>
                                        <span className="text-xs text-slate-500">
                                            {new Intl.DateTimeFormat('en-PH', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                                timeZone: 'Asia/Manila',
                                            }).format(new Date(item.created_at))}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {item.target} · {item.recipient_count} recipients
                                    </p>
                                    <p className="mt-3 text-sm whitespace-pre-wrap">{item.message}</p>
                                    <div className="mt-4 flex flex-wrap gap-2 text-xs">
                                        {[
                                            ['Queued', item.queued_count],
                                            ['Processing', item.processing_count],
                                            ['Accepted', item.accepted_count],
                                            ['Simulated', item.simulated_count],
                                            ['Failed', item.failed_count],
                                            ['Unknown', item.unknown_count],
                                            ['Skipped', item.skipped_count],
                                            ['Cancelled', item.cancelled_count],
                                        ].map(([label, value]) => (
                                            <span key={label} className="rounded-md bg-slate-50 px-3 py-2 text-slate-700">
                                                {label}: {value}
                                            </span>
                                        ))}
                                    </div>
                                    {item.can_cancel && (
                                        <button
                                            type="button"
                                            disabled={cancellingBroadcastId !== null}
                                            onClick={() => {
                                                if (
                                                    !window.confirm(
                                                        'Cancel all remaining queued messages in this broadcast? Messages already processing or sent cannot be recalled.',
                                                    )
                                                )
                                                    return;
                                                router.post(
                                                    route('reports.broadcasts.cancel', item.id),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        onStart: () => setCancellingBroadcastId(item.id),
                                                        onFinish: () => setCancellingBroadcastId(null),
                                                    },
                                                );
                                            }}
                                            className="mt-4 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
                                        >
                                            {cancellingBroadcastId === item.id ? 'Cancelling…' : 'Cancel remaining messages'}
                                        </button>
                                    )}
                                </article>
                            ))
                        ) : (
                            <p className="py-6 text-sm text-slate-500">No SMS broadcasts recorded yet.</p>
                        )}
                    </div>
                    <Pagination pages={broadcasts} />
                </section>
                {auditLogs.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold">Recent audit activity</h2>
                        <ul className="mt-4 divide-y divide-slate-100">
                            {auditLogs.map((log) => (
                                <li key={log.id} className="flex flex-wrap justify-between gap-2 py-3 text-xs">
                                    <span>
                                        {log.action} · {log.subject_type}
                                        {log.subject_id ? ` #${log.subject_id}` : ''}
                                    </span>
                                    <span className="text-slate-500">
                                        {new Intl.DateTimeFormat('en-PH', {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                            timeZone: 'Asia/Manila',
                                        }).format(new Date(log.created_at))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

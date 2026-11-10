import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export type SmsResult = {
    id: number;
    broadcast_id: number;
    contact_name: string;
    category: string;
    barangay: string;
    phone: string;
    status: string;
    error: string | null;
    processed_at: string | null;
    created_at: string;
    title: string;
    message: string;
    is_test: boolean;
    can_cancel: boolean;
};
export type SmsResults = { data: SmsResult[]; prev_page_url: string | null; next_page_url: string | null };
const labels: Record<string, string> = {
    accepted: 'Sent (provider accepted)',
    failed: 'Failed',
    rejected: 'Rejected',
    queued: 'Queued',
    processing: 'Processing',
    unknown: 'Unknown',
    simulated: 'Simulated',
    skipped: 'Skipped',
    cancelled: 'Cancelled',
};
function reportDate(value: string) {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(value));
}
export default function SmsReport({
    results,
    status,
    summary,
    dates,
    search,
    audience,
}: {
    results: SmsResults;
    status: string;
    summary: Record<string, number>;
    dates: { date_from: string; date_to: string };
    search: { recipient_search: string; message_search: string };
    audience: { category_id: string; barangay_id: string };
}) {
    const { permissions } = usePage<{ permissions: { administer: boolean } }>().props;
    const [cancellingId, setCancellingId] = useState<number | null>(null);
    const cancel = (id: number) => {
        if (!window.confirm('Cancel this queued message? Messages already processing or sent cannot be recalled.')) return;
        router.post(
            route('reports.sms.cancel', id),
            {},
            {
                preserveScroll: true,
                onStart: () => setCancellingId(id),
                onFinish: () => setCancellingId(null),
            },
        );
    };
    const filter = (value: string) =>
        router.get(route('reports.index'), { sms_status: value, ...dates, ...search, ...audience }, { preserveScroll: true, preserveState: true });
    return (
        <section className="rounded-xl border border-blue-100 bg-white p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold">Sent & failed SMS report</h2>
                {permissions.administer && (
                    <a
                        href={route('reports.sms.export', { sms_status: status, ...dates, ...search, ...audience })}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Download Excel
                    </a>
                )}
            </div>
            <p className="mt-2 text-xs leading-5 text-slate-500">
                Sent means accepted by the SMS provider; handset delivery is unverified. Test messages appear as simulated. Totals cover recipients in
                the selected filters.
            </p>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                {Object.entries(summary).map(([key, count]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => filter(key)}
                        className={`min-w-0 rounded-lg border p-3 text-left ${status === key ? 'border-blue-600 bg-blue-50' : 'border-slate-200 bg-white hover:bg-blue-50'}`}
                    >
                        <span className="block text-xs font-medium text-slate-600">
                            {key === 'sent' ? 'Sent (accepted)' : key.charAt(0).toUpperCase() + key.slice(1)}
                        </span>
                        <span className="mt-1 block text-2xl font-bold text-blue-700">{count}</span>
                    </button>
                ))}
            </div>
            <label className="mt-4 block w-full space-y-1.5 text-xs font-medium text-slate-600 sm:max-w-xs">
                <span className="block">Result</span>
                <select
                    aria-label="Filter SMS results"
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                    value={status}
                    onChange={(event) => filter(event.target.value)}
                >
                    <option value="all">All results</option>
                    <option value="sent">Sent (provider accepted)</option>
                    <option value="failed">Failed / rejected</option>
                    <option value="pending">Pending</option>
                    <option value="unknown">Unknown</option>
                    <option value="simulated">Simulated</option>
                    <option value="skipped">Skipped</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            <div className="mt-5 overflow-x-auto">
                <table className="w-full min-w-[1000px] text-left text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                        <tr>
                            <th scope="col" className="p-3">
                                Recipient
                            </th>
                            <th scope="col" className="p-3">
                                Category
                            </th>
                            <th scope="col" className="p-3">
                                Barangay
                            </th>
                            <th scope="col" className="p-3">
                                Message
                            </th>
                            <th scope="col" className="p-3">
                                Result
                            </th>
                            <th scope="col" className="p-3">
                                Processed / queued
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {results.data.map((result) => (
                            <tr key={result.id} className="align-top">
                                <td className="p-3">
                                    <p className="font-medium">{result.contact_name}</p>
                                    <p className="mt-1 font-mono text-xs text-slate-500">{result.phone}</p>
                                </td>
                                <td className="p-3 text-xs text-slate-600">{result.category}</td>
                                <td className="p-3 text-xs text-slate-600">{result.barangay}</td>
                                <td className="max-w-sm p-3">
                                    <p className="font-medium">
                                        #{result.broadcast_id} · {result.title}
                                        {result.is_test ? ' · TEST' : ''}
                                    </p>
                                    <details className="mt-2 text-xs">
                                        <summary className="cursor-pointer text-blue-700">View message</summary>
                                        <p className="mt-2 whitespace-pre-wrap text-slate-700">{result.message}</p>
                                    </details>
                                </td>
                                <td className="max-w-xs p-3">
                                    <span
                                        className={`inline-block rounded-md px-2 py-1 text-xs font-semibold ${['failed', 'rejected'].includes(result.status) ? 'bg-red-50 text-red-700' : result.status === 'accepted' ? 'bg-green-50 text-green-700' : 'bg-blue-50 text-blue-700'}`}
                                    >
                                        {labels[result.status] ?? result.status}
                                    </span>
                                    {result.error && <p className="mt-2 text-xs text-slate-600">{result.error}</p>}
                                    {result.can_cancel && (
                                        <button
                                            type="button"
                                            disabled={cancellingId !== null}
                                            onClick={() => cancel(result.id)}
                                            className="mt-2 block rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
                                        >
                                            {cancellingId === result.id ? 'Cancelling…' : 'Cancel sending'}
                                        </button>
                                    )}
                                </td>
                                <td className="p-3 text-xs text-slate-500">
                                    {reportDate(result.processed_at ?? result.created_at)}
                                    {!result.processed_at && <p className="mt-1">Not processed yet</p>}
                                </td>
                            </tr>
                        ))}
                        {!results.data.length && (
                            <tr>
                                <td colSpan={6} className="p-6 text-center text-slate-500">
                                    No messages match this result.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <nav aria-label="SMS report pagination" className="mt-4 flex justify-end gap-3 text-sm">
                {results.prev_page_url && (
                    <Link className="text-blue-700" href={results.prev_page_url} preserveScroll>
                        Previous
                    </Link>
                )}
                {results.next_page_url && (
                    <Link className="text-blue-700" href={results.next_page_url} preserveScroll>
                        Next
                    </Link>
                )}
            </nav>
        </section>
    );
}

import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    logs: {
        data: { id: number; action: string; subject_type: string; subject_id: number | null; actor_name: string | null; created_at: string }[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { action: string; subject: string };
    actions: string[];
    subjects: string[];
};
const readable = (value: string) => value.replace(/[._]/g, ' ');
export default function AuditLogs({ logs, filters, actions, subjects }: Props) {
    const [action, setAction] = useState(filters.action);
    const [subject, setSubject] = useState(filters.subject);
    const [loading, setLoading] = useState(false);
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('audit-logs.index'),
            { action, subject },
            { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) },
        );
    };
    const input =
        'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    return (
        <AppLayout>
            <Head title="Audit Logs | Project AWARE" />
            <div className="space-y-6 text-slate-900">
                <header className="flex items-center gap-3">
                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <History className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Audit Logs</h1>
                        <p className="mt-1 text-sm text-slate-500">Review recorded actions across Project AWARE.</p>
                    </div>
                </header>
                <form
                    onSubmit={apply}
                    className="grid items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3"
                    aria-busy={loading}
                >
                    <label className="space-y-2 text-xs font-medium text-slate-600">
                        <span className="block">Action</span>
                        <select className={input} value={action} onChange={(e) => setAction(e.target.value)}>
                            <option value="">All actions</option>
                            {actions.map((value) => (
                                <option key={value} value={value}>
                                    {readable(value)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-2 text-xs font-medium text-slate-600">
                        <span className="block">Record type</span>
                        <select className={input} value={subject} onChange={(e) => setSubject(e.target.value)}>
                            <option value="">All records</option>
                            {subjects.map((value) => (
                                <option key={value} value={value}>
                                    {readable(value)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-center gap-4">
                        <button
                            disabled={loading}
                            className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {loading ? 'Loading…' : 'Apply filters'}
                        </button>
                        <Link href={route('audit-logs.index')} className="text-sm font-medium text-blue-600">
                            Clear
                        </Link>
                    </div>
                </form>
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Recorded activity">
                    <div className="flex justify-between gap-3 p-5">
                        <h2 className="text-sm font-semibold">Activity history</h2>
                        <span className="text-xs text-slate-500">{logs.total.toLocaleString()} matching records</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="border-y border-slate-100 bg-blue-50/50 text-xs text-slate-500">
                                <tr>
                                    {['Date & time', 'Performed by', 'Action', 'Record'].map((label) => (
                                        <th key={label} scope="col" className="px-5 py-3 font-medium">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50">
                                        <td className="px-5 py-4 text-xs text-slate-500">
                                            {new Intl.DateTimeFormat('en-PH', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                                timeZone: 'Asia/Manila',
                                            }).format(new Date(log.created_at))}
                                        </td>
                                        <td className="px-5 py-4 font-medium">{log.actor_name ?? 'System / unavailable account'}</td>
                                        <td className="px-5 py-4">
                                            <span className="rounded-lg bg-blue-50 px-2 py-1 text-xs text-blue-700">{readable(log.action)}</span>
                                        </td>
                                        <td className="px-5 py-4 text-xs text-slate-600">
                                            {readable(log.subject_type)}
                                            {log.subject_id !== null ? ` #${log.subject_id}` : ''}
                                        </td>
                                    </tr>
                                ))}
                                {!logs.data.length && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-12 text-center text-sm text-slate-500">
                                            No recorded actions match these filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 p-5 text-xs">
                        <span className="text-slate-500">
                            Page {logs.current_page} of {logs.last_page}
                        </span>
                        <div className="flex gap-4">
                            {logs.prev_page_url && (
                                <Link href={logs.prev_page_url} className="font-medium text-blue-600">
                                    Previous
                                </Link>
                            )}
                            {logs.next_page_url && (
                                <Link href={logs.next_page_url} className="font-medium text-blue-600">
                                    Next
                                </Link>
                            )}
                        </div>
                    </div>
                </section>
                <p className="text-xs text-slate-500">Times shown in Philippine time. Audit history is read-only.</p>
            </div>
        </AppLayout>
    );
}

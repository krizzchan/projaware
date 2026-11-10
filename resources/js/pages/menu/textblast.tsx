import AppLayout from '@/layouts/app-layout';
import { estimateSmsSegments } from '@/lib/sms';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Send, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Template = { id: number; title: string; message: string; severity: string };
type Group = { id: number; name: string };
type Broadcast = {
    id: number;
    title: string;
    target: string;
    recipient_count: number;
    is_test: boolean;
    accepted_count: number;
    simulated_count: number;
    queued_count: number;
    failed_count: number;
};
interface Props {
    selectableContacts: {
        data: { id: number; first_name: string; last_name: string; phone_number: string }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    templates: { data: Template[]; total: number; prev_page_url: string | null; next_page_url: string | null };
    initialTemplate: Template | null;
    selectedAdvisoryId: number | null;
    categories: Group[];
    barangays: Group[];
    totalContactsCount: number;
    audienceCounts: { category_id: number; barangay_id: number | null; total: number }[];
    requestId: string;
    recentBroadcasts: Broadcast[];
}
const input =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
const primary =
    'inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50';

export default function TextBlast({
    categories,
    barangays,
    audienceCounts,
    requestId,
    recentBroadcasts,
    templates,
    selectedAdvisoryId,
    initialTemplate,
    selectableContacts,
}: Props) {
    const { smsMode, smsProvider } = usePage<{ smsMode: string; smsProvider: string }>().props;
    const [confirming, setConfirming] = useState(false);
    const [contactSearch, setContactSearch] = useState('');
    const selected = initialTemplate ?? templates.data.find((template) => template.id === selectedAdvisoryId);
    const [templateSearch, setTemplateSearch] = useState('');
    const form = useForm({
        title: selected?.title ?? '',
        severity: selected?.severity ?? 'info',
        message: selected?.message ?? '',
        audience_mode: 'all',
        selected_contact_ids: [] as number[],
        barangay_id: 'all',
        category_id: 'all',
        request_id: requestId,
    });
    const individualMode = ['single', 'multiple'].includes(form.data.audience_mode);
    const visibleContacts = selectableContacts.data;
    const recipientCount =
        (form.data.audience_mode === 'barangay' && form.data.barangay_id === 'all') ||
        (form.data.audience_mode === 'category' && form.data.category_id === 'all')
            ? 0
            : individualMode
              ? form.data.selected_contact_ids.length
              : audienceCounts
                    .filter(
                        (row) =>
                            (form.data.barangay_id === 'all' || String(row.barangay_id) === form.data.barangay_id) &&
                            (form.data.category_id === 'all' || String(row.category_id) === form.data.category_id),
                    )
                    .reduce((total, row) => total + Number(row.total), 0);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        setConfirming(true);
    };
    const send = () =>
        form.post(route('menu.sendTextBlast'), {
            onSuccess: (page) => {
                form.setData({ ...form.data, message: '', title: '', severity: 'info', request_id: page.props.requestId as string });
                form.setData('request_id', page.props.requestId as string);
                setConfirming(false);
            },
            onError: () => setConfirming(false),
        });

    return (
        <AppLayout>
            <Head title="SMS Text Blast" />
            <div className="space-y-6">
                <header>
                    <p className="text-xs font-semibold tracking-widest text-blue-600 uppercase">CDRRMO City of Ilagan</p>
                    <h1 className="mt-2 text-2xl font-bold">SMS Text Blast</h1>
                    <p className="mt-2 text-sm text-slate-500">Compose approved emergency alerts and choose the intended recipients.</p>
                </header>
                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                    {smsMode === 'test'
                        ? 'TEST MODE: broadcasts are simulated. No SMS is sent and no credits are used.'
                        : smsMode === 'configured'
                          ? `${smsProvider} credentials configured. Provider acceptance will be recorded separately from handset delivery.`
                          : `Live SMS is unavailable. An administrator must configure ${smsProvider} on the server.`}
                </div>
                <div className="grid gap-6 lg:grid-cols-3">
                    <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
                        <h2 className="font-semibold">Compose alert</h2>
                        <label className="block space-y-2 text-sm font-medium">
                            Send to
                            <select
                                className={input}
                                value={form.data.audience_mode}
                                onChange={(event) => {
                                    form.clearErrors();
                                    form.setData({
                                        ...form.data,
                                        audience_mode: event.target.value,
                                        selected_contact_ids: [],
                                        barangay_id: 'all',
                                        category_id: 'all',
                                    });
                                }}
                            >
                                <option value="all">All active contacts</option>
                                <option value="single">Single contact</option>
                                <option value="multiple">Multiple contacts</option>
                                <option value="barangay">Barangay</option>
                                <option value="category">Recipient category</option>
                            </select>
                        </label>
                        {individualMode && (
                            <fieldset className="rounded-lg border border-slate-200 p-4">
                                <legend className="px-1 text-xs text-slate-500">
                                    {form.data.audience_mode === 'single' ? 'Choose one contact' : 'Choose contacts'}
                                </legend>
                                <input
                                    aria-label="Search contacts"
                                    placeholder="Find by name or mobile number…"
                                    className={input}
                                    value={contactSearch}
                                    onChange={(event) => setContactSearch(event.target.value)}
                                />
                                <button
                                    type="button"
                                    className="mt-2 text-xs font-medium text-blue-600"
                                    onClick={() =>
                                        router.get(
                                            route('menu.textblast'),
                                            { contact_search: contactSearch, advisory: selectedAdvisoryId ?? undefined },
                                            { only: ['selectableContacts'], preserveState: true, preserveScroll: true, replace: true },
                                        )
                                    }
                                >
                                    Search directory
                                </button>
                                <div className="mt-3 max-h-56 space-y-2 overflow-y-auto">
                                    {visibleContacts.map((contact) => (
                                        <label key={contact.id} className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-blue-50">
                                            <input
                                                type={form.data.audience_mode === 'single' ? 'radio' : 'checkbox'}
                                                name="sms-contact"
                                                className="accent-blue-600"
                                                checked={form.data.selected_contact_ids.includes(contact.id)}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'selected_contact_ids',
                                                        form.data.audience_mode === 'single'
                                                            ? [contact.id]
                                                            : event.target.checked
                                                              ? [...form.data.selected_contact_ids, contact.id]
                                                              : form.data.selected_contact_ids.filter((id) => id !== contact.id),
                                                    )
                                                }
                                            />
                                            <span className="text-sm">
                                                {contact.first_name} {contact.last_name}
                                                <span className="ml-2 text-xs text-slate-500">{contact.phone_number}</span>
                                            </span>
                                        </label>
                                    ))}
                                    {!visibleContacts.length && <p className="py-3 text-sm text-slate-500">No matching active contacts.</p>}
                                </div>
                                <div className="mt-3 flex justify-between gap-3 text-xs text-slate-500">
                                    <span>{selectableContacts.total} matching contacts</span>
                                    <div className="flex gap-3">
                                        {selectableContacts.prev_page_url && (
                                            <Link
                                                href={selectableContacts.prev_page_url}
                                                only={['selectableContacts']}
                                                preserveState
                                                preserveScroll
                                                className="text-blue-600"
                                            >
                                                Previous
                                            </Link>
                                        )}
                                        {selectableContacts.next_page_url && (
                                            <Link
                                                href={selectableContacts.next_page_url}
                                                only={['selectableContacts']}
                                                preserveState
                                                preserveScroll
                                                className="text-blue-600"
                                            >
                                                Next
                                            </Link>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-3 flex justify-between text-xs text-slate-500">
                                    <span>{form.data.selected_contact_ids.length} selected</span>
                                    <button type="button" className="text-blue-700" onClick={() => form.setData('selected_contact_ids', [])}>
                                        Clear selection
                                    </button>
                                </div>
                            </fieldset>
                        )}
                        {form.data.audience_mode === 'barangay' && (
                            <label className="block space-y-2 text-sm font-medium">
                                Barangay
                                <select
                                    required
                                    className={input}
                                    value={form.data.barangay_id}
                                    onChange={(event) => form.setData('barangay_id', event.target.value)}
                                >
                                    <option value="all">Choose barangay</option>
                                    {barangays.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {form.data.audience_mode === 'category' && (
                            <label className="block space-y-2 text-sm font-medium">
                                Recipient category
                                <select
                                    required
                                    className={input}
                                    value={form.data.category_id}
                                    onChange={(event) => form.setData('category_id', event.target.value)}
                                >
                                    <option value="all">Choose category</option>
                                    {categories.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <label className="block space-y-2 text-sm font-medium">
                            Message
                            <textarea
                                required
                                maxLength={670}
                                rows={7}
                                className={input}
                                placeholder="Enter the approved notice, affected area, effective time, and safety instructions..."
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                            />
                        </label>
                        <div className="flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                            <span>
                                {form.data.message.length}/670 characters · Estimated {estimateSmsSegments(form.data.message)} SMS segments per
                                recipient
                            </span>
                            <span className="flex items-center gap-1">
                                <Users className="h-4 w-4" />
                                {recipientCount} active recipients
                            </span>
                        </div>
                        {Object.values(form.errors).map((error, index) => (
                            <p key={index} role="alert" className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                onClick={() => form.setData({ ...form.data, message: '', title: '', severity: 'info' })}
                                className="rounded-lg border border-blue-200 px-4 py-2.5 text-sm font-medium text-blue-700"
                            >
                                Clear message
                            </button>
                            <button
                                type="submit"
                                className={primary}
                                disabled={
                                    form.processing ||
                                    !form.data.message.trim() ||
                                    !recipientCount ||
                                    (form.data.audience_mode === 'barangay' && form.data.barangay_id === 'all') ||
                                    (form.data.audience_mode === 'category' && form.data.category_id === 'all') ||
                                    smsMode === 'unconfigured'
                                }
                            >
                                <Send className="h-4 w-4" />
                                {smsMode === 'test' ? 'Review simulation' : 'Review broadcast'}
                            </button>
                        </div>
                    </form>
                    <section className="self-start overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="flex items-center justify-between gap-3 border-b border-slate-100 p-5">
                            <h2 className="text-sm font-semibold">
                                Draft templates <span className="ml-1 text-xs font-normal text-slate-400">{templates.total}</span>
                            </h2>
                            <Link href={route('menu.quick-advisory')} className="text-xs font-medium text-blue-700 hover:underline">
                                Manage
                            </Link>
                        </div>
                        <div className="px-5 pt-4">
                            <input
                                aria-label="Search draft templates"
                                placeholder="Find a template by title…"
                                value={templateSearch}
                                onChange={(event) => setTemplateSearch(event.target.value)}
                                className={input}
                            />
                            <button
                                type="button"
                                className="mt-2 text-xs font-medium text-blue-600"
                                onClick={() =>
                                    router.get(
                                        route('menu.textblast'),
                                        { template_search: templateSearch, advisory: selectedAdvisoryId ?? undefined },
                                        { only: ['templates'], preserveState: true, preserveScroll: true, replace: true },
                                    )
                                }
                            >
                                Search templates
                            </button>
                            <p className="mt-2 text-xs leading-5 text-slate-500">Choose a saved advisory to fill your message.</p>
                        </div>
                        <div className="max-h-[480px] divide-y divide-slate-100 overflow-y-auto px-5 pb-3">
                            {templates.data
                                .filter((template) => template.title.toLowerCase().includes(templateSearch.toLowerCase()))
                                .map((template) => (
                                    <button
                                        key={template.id}
                                        type="button"
                                        disabled={form.processing}
                                        onClick={() =>
                                            form.setData({
                                                ...form.data,
                                                title: template.title,
                                                severity: template.severity,
                                                message: template.message,
                                            })
                                        }
                                        className="block w-full py-4 text-left disabled:opacity-50"
                                    >
                                        <span className="block text-sm font-medium text-blue-700">{template.title}</span>
                                        <span className="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500">{template.message}</span>
                                    </button>
                                ))}
                            {!templates.total && (
                                <p className="py-6 text-sm text-slate-500">No saved templates. Create one in Advisories & Alerts.</p>
                            )}
                            {templates.total > 0 &&
                                !templates.data.some((template) => template.title.toLowerCase().includes(templateSearch.toLowerCase())) && (
                                    <p className="py-6 text-sm text-slate-500">No matching templates.</p>
                                )}
                        </div>
                        <div className="flex gap-4 border-t border-slate-100 px-5 py-3 text-xs text-blue-600">
                            {templates.prev_page_url && (
                                <Link href={templates.prev_page_url} only={['templates']} preserveState preserveScroll>
                                    Previous
                                </Link>
                            )}
                            {templates.next_page_url && (
                                <Link href={templates.next_page_url} only={['templates']} preserveState preserveScroll>
                                    Next
                                </Link>
                            )}
                        </div>
                    </section>
                </div>
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div className="flex justify-between p-5">
                        <h2 className="font-semibold">Recent broadcasts</h2>
                        <Link href={route('reports.index')} className="text-sm font-medium text-blue-700">
                            Full history
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    {['Alert / audience', 'Recipients', 'Queued', 'Accepted', 'Simulated', 'Needs review'].map((title) => (
                                        <th key={title} className="px-5 py-3">
                                            {title}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentBroadcasts.length ? (
                                    recentBroadcasts.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-5 py-4">
                                                <p className="font-medium">
                                                    {item.title}
                                                    {item.is_test ? ' · Test' : ''}
                                                </p>
                                                <p className="text-xs text-slate-500">{item.target}</p>
                                            </td>
                                            {[
                                                item.recipient_count,
                                                item.queued_count,
                                                item.accepted_count,
                                                item.simulated_count,
                                                item.failed_count,
                                            ].map((value, index) => (
                                                <td key={index} className="px-5 py-4">
                                                    {value}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-slate-500">
                                            No broadcasts recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            {confirming && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <section
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="confirm-sms"
                        className="w-full max-w-lg rounded-xl bg-white p-6 text-slate-900 shadow-xl"
                    >
                        <h2 id="confirm-sms" className="text-lg font-semibold">
                            {smsMode === 'test' ? 'Confirm test simulation' : 'Confirm emergency broadcast'}
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Audience: {recipientCount} active contacts. Review the message and recipient filters before proceeding.
                        </p>
                        <p className="mt-4 max-h-64 overflow-y-auto rounded-lg bg-slate-50 p-4 text-sm whitespace-pre-wrap">{form.data.message}</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                disabled={form.processing}
                                onClick={() => setConfirming(false)}
                                className="rounded-lg border border-blue-200 px-4 py-2 text-sm text-blue-700"
                            >
                                Cancel
                            </button>
                            <button type="button" disabled={form.processing} onClick={send} className={primary}>
                                {form.processing ? 'Submitting...' : smsMode === 'test' ? 'Run simulation' : 'Queue SMS'}
                            </button>
                        </div>
                    </section>
                </div>
            )}
        </AppLayout>
    );
}

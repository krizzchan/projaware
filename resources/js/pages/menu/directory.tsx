import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Record = { id: number; name: string; phone_number?: string; description?: string | null; contacts_count?: number };
interface Props {
    kind: 'barangays' | 'hotlines';
    title: string;
    records: { data: Record[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    filters: { search?: string };
}
const input =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
export default function Directory({ kind, title, records, filters }: Props) {
    const { permissions } = usePage<{ permissions: { administer: boolean } }>().props;
    const [editing, setEditing] = useState<number | null>(null);
    const [opened, setOpened] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const form = useForm({ name: '', phone_number: '', description: '' });
    const edit = (record?: Record) => {
        form.clearErrors();
        form.setData({ name: record?.name ?? '', phone_number: record?.phone_number ?? '', description: record?.description ?? '' });
        setEditing(record?.id ?? null);
        setOpened(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                setOpened(false);
                form.reset();
            },
        };
        if (editing) form.put(route(`${kind}.update`, editing), options);
        else form.post(route(`${kind}.store`), options);
    };
    return (
        <AppLayout>
            <Head title={title} />
            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="mt-2 text-sm text-slate-500">
                            {kind === 'barangays'
                                ? 'Organize the notification directory by barangay.'
                                : 'Maintain verified numbers for emergency coordination.'}
                        </p>
                    </div>
                    {permissions.administer && (
                        <button onClick={() => edit()} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">
                            Add {kind === 'barangays' ? 'barangay' : 'hotline'}
                        </button>
                    )}
                </header>
                {kind === 'barangays' && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(route('barangays.index'), { search }, { preserveState: true });
                        }}
                        className="flex max-w-lg gap-2"
                    >
                        <input
                            aria-label="Search barangays"
                            placeholder="Search barangays..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className={input}
                        />
                        <button className="rounded-lg border border-blue-200 bg-white px-4 text-sm text-blue-700">Search</button>
                    </form>
                )}
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th className="px-5 py-3">Name</th>
                                <th className="px-5 py-3">{kind === 'barangays' ? 'Registered contacts' : 'Phone number'}</th>
                                <th className="px-5 py-3">{kind === 'barangays' ? 'Directory' : 'Description'}</th>
                                {permissions.administer && <th className="px-5 py-3">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.length ? (
                                records.data.map((record) => (
                                    <tr key={record.id}>
                                        <td className="px-5 py-4 font-medium">{record.name}</td>
                                        <td className="px-5 py-4">
                                            {kind === 'barangays' ? (
                                                record.contacts_count
                                            ) : (
                                                <a href={`tel:${record.phone_number?.replace(/[^+0-9]/g, '')}`} className="text-blue-700">
                                                    {record.phone_number}
                                                </a>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            {kind === 'barangays' ? (
                                                <Link className="text-blue-700" href={route('menu.contacts.index', { barangay_id: record.id })}>
                                                    View contacts
                                                </Link>
                                            ) : (
                                                record.description || '—'
                                            )}
                                        </td>
                                        {permissions.administer && (
                                            <td className="px-5 py-4">
                                                <div className="flex gap-3">
                                                    <button className="text-blue-700" onClick={() => edit(record)}>
                                                        Edit
                                                    </button>
                                                    <button
                                                        className="text-red-700"
                                                        onClick={() => {
                                                            if (confirm(`Delete ${record.name}?`)) router.delete(route(`${kind}.destroy`, record.id));
                                                        }}
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={4} className="p-10 text-center text-slate-500">
                                        No records found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Pagination" className="flex items-center justify-between text-sm">
                    <span className="text-slate-500">
                        Page {records.current_page} of {records.last_page}
                    </span>
                    <div className="flex gap-3">
                        {records.prev_page_url && (
                            <Link className="rounded-lg border border-blue-200 bg-white px-4 py-2 text-blue-700" href={records.prev_page_url}>
                                Previous
                            </Link>
                        )}
                        {records.next_page_url && (
                            <Link className="rounded-lg border border-blue-200 bg-white px-4 py-2 text-blue-700" href={records.next_page_url}>
                                Next
                            </Link>
                        )}
                    </div>
                </nav>
            </div>
            {opened && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <form
                        onSubmit={submit}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="directory-title"
                        className="w-full max-w-md space-y-4 rounded-xl bg-white p-6"
                    >
                        <h2 id="directory-title" className="text-lg font-semibold">
                            {editing ? 'Edit record' : 'Add record'}
                        </h2>
                        <label className="block space-y-2 text-sm font-medium">
                            Name
                            <input
                                required
                                maxLength={100}
                                className={input}
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                        </label>
                        {kind === 'hotlines' && (
                            <>
                                <label className="block space-y-2 text-sm font-medium">
                                    Phone number
                                    <input
                                        required
                                        maxLength={30}
                                        className={input}
                                        value={form.data.phone_number}
                                        onChange={(e) => form.setData('phone_number', e.target.value)}
                                    />
                                </label>
                                <label className="block space-y-2 text-sm font-medium">
                                    Description
                                    <input
                                        maxLength={255}
                                        className={input}
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                    />
                                </label>
                            </>
                        )}
                        {Object.values(form.errors).map((error, index) => (
                            <p key={index} className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setOpened(false)}
                                className="rounded-lg border border-blue-200 px-4 py-2 text-sm text-blue-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={form.processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {form.processing ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AppLayout>
    );
}

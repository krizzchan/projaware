import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Advisory = { id: number; title: string; severity: string; message: string };
type Props = { advisories: { data: Advisory[]; prev_page_url: string | null; next_page_url: string | null; total: number } };
const input =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
const primary = 'rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50';
const secondary = 'rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50';

export default function QuickAdvisory({ advisories }: Props) {
    const [opened, setOpened] = useState(false);
    const [editing, setEditing] = useState<Advisory | null>(null);
    const [deleting, setDeleting] = useState<Advisory | null>(null);
    const form = useForm({ title: '', severity: 'warning', message: '' });
    const deletion = useForm({});
    const open = (advisory?: Advisory) => {
        form.clearErrors();
        form.setData({ title: advisory?.title ?? '', severity: advisory?.severity ?? 'warning', message: advisory?.message ?? '' });
        setEditing(advisory ?? null);
        setOpened(true);
    };
    const close = () => {
        if (form.processing) return;
        setOpened(false);
        form.reset();
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpened(false);
                form.reset();
            },
        };
        if (editing) form.put(route('menu.advisories.update', editing.id), options);
        else form.post(route('menu.advisories.store'), options);
    };
    const remove = () => {
        if (deleting) deletion.delete(route('menu.advisories.destroy', deleting.id), { preserveScroll: true, onSuccess: () => setDeleting(null) });
    };
    return (
        <AppLayout>
            <Head title="Advisories & Alerts" />
            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Advisories & Alerts</h1>
                        <p className="mt-1 text-sm text-slate-500">Reusable draft templates for SMS. Saving a template sends no messages.</p>
                    </div>
                    <button type="button" onClick={() => open()} className={primary}>
                        Add template
                    </button>
                </header>
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 className="text-sm font-medium">
                            Saved templates <span className="ml-2 text-xs text-slate-400">{advisories.total}</span>
                        </h2>
                        <Link href={route('menu.textblast')} className="text-xs text-blue-700 hover:underline">
                            Open SMS Text Blast
                        </Link>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {advisories.data.map((advisory) => (
                            <article key={advisory.id} className="flex flex-wrap items-center justify-between gap-4 px-5 py-5">
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-medium text-slate-900">{advisory.title}</h3>
                                    <p className="mt-1.5 line-clamp-2 text-xs leading-5 text-slate-500">{advisory.message}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Link
                                        href={route('menu.textblast', { advisory: advisory.id })}
                                        className="rounded-md bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-100"
                                    >
                                        Use in SMS
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => open(advisory)}
                                        className="rounded-md px-3 py-2 text-xs text-slate-600 hover:bg-slate-50 hover:text-blue-700"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            deletion.clearErrors();
                                            setDeleting(advisory);
                                        }}
                                        className="rounded-md px-3 py-2 text-xs text-slate-500 hover:bg-red-50 hover:text-red-700"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </article>
                        ))}
                        {!advisories.data.length && (
                            <div className="px-5 py-12 text-center">
                                <p className="text-sm font-medium text-slate-700">No saved templates yet</p>
                                <p className="mt-2 text-xs text-slate-500">Add a template to make it available in SMS Text Blast.</p>
                            </div>
                        )}
                    </div>
                    {(advisories.prev_page_url || advisories.next_page_url) && (
                        <nav aria-label="Template pages" className="flex justify-end gap-4 border-t border-slate-100 px-5 py-3 text-sm text-blue-700">
                            {advisories.prev_page_url && <Link href={advisories.prev_page_url}>Previous</Link>}
                            {advisories.next_page_url && <Link href={advisories.next_page_url}>Next</Link>}
                        </nav>
                    )}
                </section>
            </div>
            <Dialog
                open={opened}
                onOpenChange={(value) => {
                    if (!value) close();
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto border-slate-200 bg-white text-slate-900">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit template' : 'Add template'}</DialogTitle>
                        <DialogDescription className="text-slate-500">Saved templates appear in the SMS Text Blast draft panel.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <label className="block space-y-2 text-sm font-medium">
                            Title
                            <input
                                required
                                maxLength={150}
                                className={input}
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                placeholder="e.g. Flood advisory"
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Priority
                            <select className={input} value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}>
                                <option value="info">Information</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                            </select>
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            SMS message
                            <textarea
                                required
                                maxLength={670}
                                rows={6}
                                className={input}
                                value={form.data.message}
                                onChange={(event) => form.setData('message', event.target.value)}
                            />
                        </label>
                        <p className="text-right text-xs text-slate-400">{form.data.message.length}/670 characters</p>
                        {Object.values(form.errors).map((error, index) => (
                            <p key={index} role="alert" className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <DialogFooter className="gap-2">
                            <button type="button" disabled={form.processing} onClick={close} className={secondary}>
                                Cancel
                            </button>
                            <button type="submit" disabled={form.processing} className={primary}>
                                {form.processing ? 'Saving…' : editing ? 'Update template' : 'Save template'}
                            </button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!deleting}
                onOpenChange={(value) => {
                    if (!value && !deletion.processing) setDeleting(null);
                }}
            >
                <DialogContent className="border-slate-200 bg-white text-slate-900">
                    <DialogHeader>
                        <DialogTitle>Delete template?</DialogTitle>
                        <DialogDescription className="text-slate-500">
                            Remove “{deleting?.title}” from saved templates? Sent-message history is preserved.
                        </DialogDescription>
                    </DialogHeader>
                    {Object.values(deletion.errors).map((error, index) => (
                        <p key={index} className="text-sm text-red-700">
                            {String(error)}
                        </p>
                    ))}
                    <DialogFooter className="gap-2">
                        <button type="button" disabled={deletion.processing} onClick={() => setDeleting(null)} className={secondary}>
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={deletion.processing}
                            onClick={remove}
                            className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {deletion.processing ? 'Deleting…' : 'Delete template'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { type FormEvent, useState } from 'react';

type Category = { id: number; name: string; description: string | null };
type Props = { categories: Category[]; filters: { search?: string } };
const input =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';

export default function CategoryIndex({ categories, filters }: Props) {
    const { permissions } = usePage<{ permissions: { administer: boolean } }>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [deleting, setDeleting] = useState<Category | null>(null);
    const [deletingBusy, setDeletingBusy] = useState(false);
    const [deleteError, setDeleteError] = useState('');
    const form = useForm({ name: '', description: '' });
    const closeForm = () => {
        if (form.processing) return;
        setOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };
    const openForm = (category?: Category) => {
        form.clearErrors();
        setEditing(category ?? null);
        form.setData({ name: category?.name ?? '', description: category?.description ?? '' });
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                form.reset();
            },
        };
        if (editing) form.put(route('menu.categories.update', editing.id), options);
        else form.post(route('menu.categories.store'), options);
    };
    const searchCategories = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('menu.categories.index'), { search }, { preserveState: true, replace: true });
    };
    const remove = () => {
        if (!deleting || deletingBusy) return;
        router.delete(route('menu.categories.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => {
                setDeletingBusy(true);
                setDeleteError('');
            },
            onSuccess: () => setDeleting(null),
            onError: (errors) => setDeleteError(errors.delete ?? 'Unable to delete this category.'),
            onFinish: () => setDeletingBusy(false),
        });
    };
    return (
        <AppLayout>
            <Head title="Categories" />
            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Categories</h1>
                        <p className="mt-1 text-sm text-slate-500">Organize your emergency contact groups.</p>
                    </div>
                    {permissions.administer && (
                        <Button type="button" onClick={() => openForm()} className="rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                            <Plus aria-hidden="true" />
                            Add category
                        </Button>
                    )}
                </header>
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 sm:px-5">
                        <form onSubmit={searchCategories} className="flex w-full max-w-md gap-2">
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-slate-400" aria-hidden="true" />
                                <input
                                    aria-label="Search categories"
                                    maxLength={100}
                                    placeholder="Search categories"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    className={`${input} pl-9`}
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-10 border-blue-200 bg-white text-blue-700 hover:bg-blue-50 hover:text-blue-800"
                            >
                                Search
                            </Button>
                            {(filters.search || search) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(route('menu.categories.index'), {}, { preserveState: true, replace: true });
                                    }}
                                    className="rounded-lg px-2 text-sm text-slate-500 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600"
                                >
                                    Reset
                                </button>
                            )}
                        </form>
                        <p className="text-xs text-slate-500">
                            {categories.length} {filters.search ? 'results' : categories.length === 1 ? 'category' : 'categories'}
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[540px] text-left text-sm">
                            <thead className="border-b border-slate-100 bg-slate-50/70 text-xs font-medium text-slate-500">
                                <tr>
                                    <th scope="col" className="px-5 py-3">
                                        Category
                                    </th>
                                    <th scope="col" className="px-5 py-3">
                                        Description
                                    </th>
                                    {permissions.administer && (
                                        <th scope="col" className="px-5 py-3 text-right">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {categories.map((category) => (
                                    <tr key={category.id} className="hover:bg-blue-50/30">
                                        <td className="px-5 py-4 font-medium text-slate-900">
                                            <span className="block max-w-xs break-words">{category.name}</span>
                                        </td>
                                        <td className="px-5 py-4 text-slate-500">
                                            <p className="max-w-xl break-words whitespace-pre-wrap">{category.description || '—'}</p>
                                        </td>
                                        {permissions.administer && (
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        aria-label={`Edit ${category.name}`}
                                                        onClick={() => openForm(category)}
                                                        className="rounded-md px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-label={`Delete ${category.name}`}
                                                        onClick={() => {
                                                            setDeleteError('');
                                                            setDeleting(category);
                                                        }}
                                                        className="rounded-md px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-red-50 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-red-600"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {!categories.length && (
                                    <tr>
                                        <td colSpan={permissions.administer ? 3 : 2} className="px-5 py-14 text-center">
                                            <p className="font-medium text-slate-700">
                                                {filters.search ? 'No matching categories' : 'No categories yet'}
                                            </p>
                                            <p className="mt-2 text-sm text-slate-500">
                                                {filters.search
                                                    ? 'Try a different search.'
                                                    : permissions.administer
                                                      ? 'Add a category to organize your contacts.'
                                                      : 'Your administrator can add contact categories.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
                {!permissions.administer && <p className="text-xs text-slate-500">Category changes are managed by your administrator.</p>}
            </div>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!value) closeForm();
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto rounded-xl border-slate-200 bg-white text-slate-900 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit category' : 'Add category'}</DialogTitle>
                        <DialogDescription className="text-slate-500">Use a clear name for this contact group.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label htmlFor="category-name" className="mb-2 block text-sm font-medium">
                                Name
                            </label>
                            <input
                                id="category-name"
                                required
                                maxLength={100}
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                placeholder="e.g. Emergency responders"
                                className={input}
                                aria-invalid={!!form.errors.name}
                            />
                            {form.errors.name && <p className="mt-2 text-xs text-red-700">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label htmlFor="category-description" className="mb-2 block text-sm font-medium">
                                Description <span className="font-normal text-slate-400">(optional)</span>
                            </label>
                            <textarea
                                id="category-description"
                                rows={3}
                                maxLength={1000}
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                className={input}
                                aria-invalid={!!form.errors.description}
                            />
                            {form.errors.description && <p className="mt-2 text-xs text-red-700">{form.errors.description}</p>}
                        </div>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                disabled={form.processing}
                                variant="outline"
                                onClick={closeForm}
                                className="border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || !permissions.administer}
                                className="bg-blue-600 text-white hover:bg-blue-700"
                            >
                                {form.processing ? 'Saving…' : 'Save category'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!deleting}
                onOpenChange={(value) => {
                    if (!value && !deletingBusy) setDeleting(null);
                }}
            >
                <DialogContent className="rounded-xl border-slate-200 bg-white text-slate-900 sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete category?</DialogTitle>
                        <DialogDescription className="text-slate-500">
                            Remove “{deleting?.name}”? Categories with linked contacts cannot be deleted.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteError && (
                        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                            {deleteError}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            disabled={deletingBusy}
                            variant="outline"
                            onClick={() => setDeleting(null)}
                            className="border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={remove}
                            disabled={deletingBusy || !permissions.administer}
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            {deletingBusy ? 'Deleting…' : 'Delete category'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

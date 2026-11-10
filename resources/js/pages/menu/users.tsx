import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type User = { id: number; name: string; email: string; role: string; is_active: boolean };
const input =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
type UserPage = { data: User[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
export default function Users({ users, filters }: { users: UserPage; filters: { search: string } }) {
    const [search, setSearch] = useState(filters.search);
    const [opened, setOpened] = useState(false);
    const [editing, setEditing] = useState<User | null>(null);
    const [resetting, setResetting] = useState<User | null>(null);
    const passwordReset = useForm({ password: '', password_confirmation: '', current_password: '' });
    const create = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'staff', current_password: '' });
    const access = useForm<{ name: string; email: string; role: string; is_active: boolean; current_password: string }>({
        name: '',
        email: '',
        role: 'staff',
        is_active: true,
        current_password: '',
    });
    const openEdit = (user: User) => {
        access.clearErrors();
        access.setData({ name: user.name, email: user.email, role: user.role, is_active: user.is_active, current_password: '' });
        setEditing(user);
    };
    const save = (event: FormEvent) => {
        event.preventDefault();
        create.post(route('users.store'), {
            onSuccess: () => {
                create.reset();
                setOpened(false);
            },
            onFinish: () => create.reset('password', 'password_confirmation', 'current_password'),
        });
    };
    const saveAccess = (event: FormEvent) => {
        event.preventDefault();
        if (editing)
            access.patch(route('users.update', editing.id), { onSuccess: () => setEditing(null), onFinish: () => access.reset('current_password') });
    };
    return (
        <AppLayout>
            <Head title="Account Access" />
            <div className="space-y-6">
                <header className="flex flex-wrap justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Account Access</h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Administrators manage accounts and directories. Staff manage contacts, incidents, and SMS alerts.
                        </p>
                    </div>
                    <button
                        onClick={() => {
                            create.reset();
                            create.clearErrors();
                            setOpened(true);
                        }}
                        className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white"
                    >
                        Create account
                    </button>
                </header>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(route('users.index'), { search }, { preserveState: true, replace: true });
                    }}
                    className="flex flex-wrap gap-3"
                >
                    <input
                        aria-label="Search accounts"
                        placeholder="Search name or email…"
                        className={input + ' max-w-sm'}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <button className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Search</button>
                    <Link href={route('users.index')} className="self-center text-sm text-blue-600">
                        Clear
                    </Link>
                </form>
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                {['Name', 'Email', 'Role', 'Status', 'Actions'].map((label) => (
                                    <th key={label} className="px-5 py-3">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-5 py-4 font-medium">{user.name}</td>
                                    <td className="px-5 py-4">{user.email}</td>
                                    <td className="px-5 py-4">{user.role === 'admin' ? 'Administrator' : 'Staff'}</td>
                                    <td className="px-5 py-4">{user.is_active ? 'Enabled' : 'Disabled'}</td>
                                    <td className="px-5 py-4">
                                        <button onClick={() => openEdit(user)} className="font-medium text-blue-700">
                                            Edit user
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                passwordReset.reset();
                                                passwordReset.clearErrors();
                                                setResetting(user);
                                            }}
                                            className="ml-4 font-medium text-blue-700"
                                        >
                                            Reset password
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 p-5 text-xs text-slate-500">
                        <span>
                            {users.total} accounts · Page {users.current_page} of {users.last_page}
                        </span>
                        <div className="flex gap-4">
                            {users.prev_page_url && (
                                <Link href={users.prev_page_url} className="text-blue-600">
                                    Previous
                                </Link>
                            )}
                            {users.next_page_url && (
                                <Link href={users.next_page_url} className="text-blue-600">
                                    Next
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {opened && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <form
                        onSubmit={save}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="create-user-title"
                        className="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-xl bg-white p-6"
                    >
                        <h2 id="create-user-title" className="text-lg font-semibold">
                            Create account
                        </h2>
                        <label className="block space-y-2 text-sm font-medium">
                            Name
                            <input
                                required
                                maxLength={100}
                                className={input}
                                value={create.data.name}
                                onChange={(e) => create.setData('name', e.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Email
                            <input
                                required
                                type="email"
                                autoComplete="off"
                                className={input}
                                value={create.data.email}
                                onChange={(e) => create.setData('email', e.target.value.toLowerCase())}
                            />
                        </label>
                        <p className="text-xs text-slate-500">
                            Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.
                        </p>
                        <label className="block space-y-2 text-sm font-medium">
                            New password
                            <input
                                required
                                type="password"
                                autoComplete="new-password"
                                className={input}
                                value={create.data.password}
                                onChange={(e) => create.setData('password', e.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Confirm new password
                            <input
                                required
                                type="password"
                                autoComplete="new-password"
                                className={input}
                                value={create.data.password_confirmation}
                                onChange={(e) => create.setData('password_confirmation', e.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Role
                            <select className={input} value={create.data.role} onChange={(e) => create.setData('role', e.target.value)}>
                                <option value="staff">Staff</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Your current password
                            <input
                                required
                                type="password"
                                autoComplete="current-password"
                                className={input}
                                value={create.data.current_password}
                                onChange={(e) => create.setData('current_password', e.target.value)}
                            />
                        </label>
                        {Object.values(create.errors).map((error, index) => (
                            <p key={index} className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    create.reset('password', 'password_confirmation', 'current_password');
                                    setOpened(false);
                                }}
                                className="rounded-lg border border-blue-200 px-4 py-2 text-blue-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={create.processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-50"
                            >
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            )}
            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <form
                        onSubmit={saveAccess}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="access-title"
                        className="max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-xl bg-white p-6"
                    >
                        <h2 id="access-title" className="text-lg font-semibold">
                            Edit {editing.name}
                        </h2>
                        <label className="block space-y-2 text-sm font-medium">
                            Name
                            <input
                                required
                                maxLength={100}
                                className={input}
                                value={access.data.name}
                                onChange={(event) => access.setData('name', event.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Email
                            <input
                                required
                                type="email"
                                maxLength={255}
                                className={input}
                                value={access.data.email}
                                onChange={(event) => access.setData('email', event.target.value.toLowerCase())}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Role
                            <select className={input} value={access.data.role} onChange={(e) => access.setData('role', e.target.value)}>
                                <option value="staff">Staff</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={access.data.is_active} onChange={(e) => access.setData('is_active', e.target.checked)} />
                            Account enabled
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Your current password
                            <input
                                required
                                type="password"
                                autoComplete="current-password"
                                className={input}
                                value={access.data.current_password}
                                onChange={(e) => access.setData('current_password', e.target.value)}
                            />
                        </label>
                        {Object.values(access.errors).map((error, index) => (
                            <p key={index} className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    access.reset('current_password');
                                    setEditing(null);
                                }}
                                className="rounded-lg border border-blue-200 px-4 py-2 text-blue-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={access.processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-50"
                            >
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            )}
            {resetting && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <form
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="reset-user-title"
                        className="max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-xl bg-white p-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            passwordReset.patch(route('users.password.reset', resetting.id), {
                                preserveScroll: true,
                                onSuccess: () => setResetting(null),
                                onFinish: () => passwordReset.reset(),
                            });
                        }}
                    >
                        <h2 id="reset-user-title" className="text-lg font-semibold">
                            Reset password
                        </h2>
                        <p className="text-sm text-slate-500">
                            Set a new password for {resetting.name} ({resetting.email}). Their other signed-in sessions will be invalidated.
                        </p>
                        <p className="text-xs text-slate-500">
                            Use at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.
                        </p>
                        <label className="block space-y-2 text-sm font-medium">
                            New password
                            <input
                                required
                                minLength={12}
                                type="password"
                                autoComplete="new-password"
                                className={input}
                                value={passwordReset.data.password}
                                onChange={(event) => passwordReset.setData('password', event.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Confirm new password
                            <input
                                required
                                type="password"
                                autoComplete="new-password"
                                className={input}
                                value={passwordReset.data.password_confirmation}
                                onChange={(event) => passwordReset.setData('password_confirmation', event.target.value)}
                            />
                        </label>
                        <label className="block space-y-2 text-sm font-medium">
                            Your administrator password
                            <input
                                required
                                type="password"
                                autoComplete="current-password"
                                className={input}
                                value={passwordReset.data.current_password}
                                onChange={(event) => passwordReset.setData('current_password', event.target.value)}
                            />
                        </label>
                        {Object.values(passwordReset.errors).map((error, index) => (
                            <p key={index} className="text-sm text-red-700">
                                {error}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                disabled={passwordReset.processing}
                                onClick={() => {
                                    passwordReset.reset();
                                    setResetting(null);
                                }}
                                className="rounded-lg border border-blue-200 px-4 py-2 text-blue-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={passwordReset.processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-50"
                            >
                                {passwordReset.processing ? 'Resetting…' : 'Reset password'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AppLayout>
    );
}

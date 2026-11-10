import SemaphoreCreditBalance from '@/components/semaphore-credit-balance';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, CheckCircle2, FileText, Layers, MapPin, RefreshCw, Send, Shield, Users } from 'lucide-react';
import { useState } from 'react';

type Group = { id: number; name: string; contacts_count: number; active_contacts_count: number };
type Contact = {
    id: number;
    first_name: string;
    last_name: string;
    is_active: boolean | number;
    category: { name: string } | null;
    barangay: { name: string } | null;
};
interface Props {
    stats: { totalContacts: number; activeContacts: number; totalBarangays: number; coveredBarangays: number; totalCategories: number };
    categories: Group[];
    barangays: Group[];
    recentContacts: Contact[];
    updatedAt: string;
    analytics: {
        daily: { date: string; label: string; live: number; test: number }[];
        liveBroadcasts: number;
        testBroadcasts: number;
        outcomes: { accepted: number; failed: number; pending: number; unknown: number; simulated: number; skipped: number; cancelled: number };
    };
}

export default function Dashboard({ stats, categories, barangays, recentContacts, updatedAt, analytics }: Props) {
    const { auth, smsMode, smsProvider, permissions } = usePage<{
        auth: { user: { name: string } };
        smsMode: string;
        smsProvider: string;
        permissions: { administer: boolean };
    }>().props;
    const showCredits = permissions.administer && smsProvider === 'Semaphore';
    const [refreshing, setRefreshing] = useState(false);
    const [barangaySearch, setBarangaySearch] = useState('');
    const coverage = stats.totalBarangays ? Math.round((stats.coveredBarangays / stats.totalBarangays) * 100) : 0;
    const date = new Date(updatedAt);
    const cards = [
        {
            label: 'Registered contacts',
            value: stats.totalContacts.toLocaleString(),
            detail: 'Emergency contact directory',
            icon: Users,
            accent: 'bg-blue-500',
            iconTone: 'bg-blue-50 text-blue-600',
            valueTone: 'text-blue-700',
            href: route('menu.contacts.index'),
        },
        {
            label: 'Active recipients',
            value: stats.activeContacts.toLocaleString(),
            detail: `${stats.totalContacts - stats.activeContacts} inactive contacts`,
            icon: CheckCircle2,
            accent: 'bg-emerald-500',
            iconTone: 'bg-emerald-50 text-emerald-600',
            valueTone: 'text-emerald-700',
            href: route('menu.contacts.index'),
        },
        {
            label: 'Barangay coverage',
            value: `${stats.coveredBarangays} / ${stats.totalBarangays}`,
            detail: 'Barangays with active contacts',
            icon: MapPin,
            accent: 'bg-violet-500',
            iconTone: 'bg-violet-50 text-violet-600',
            valueTone: 'text-violet-700',
            href: route('barangays.index'),
        },
        {
            label: 'Recipient categories',
            value: stats.totalCategories.toLocaleString(),
            detail: 'Groups for targeted messages',
            icon: Layers,
            accent: 'bg-amber-500',
            iconTone: 'bg-amber-50 text-amber-600',
            valueTone: 'text-amber-700',
            href: route('menu.categories.index'),
        },
    ];
    const shortcuts = [
        { title: 'Contact directory', description: 'Keep your recipients up to date', icon: Users, href: route('menu.contacts.index') },
        { title: 'Advisory templates', description: 'Prepare a reusable SMS notice', icon: FileText, href: route('menu.quick-advisory') },
        { title: 'Message reports', description: 'Review sent and failed results', icon: Send, href: route('reports.index') },
    ];
    const peak = Math.max(1, ...analytics.daily.map((day) => day.live + day.test));
    const outcomes = [
        { label: 'Gateway accepted', count: analytics.outcomes.accepted, color: 'bg-emerald-500' },
        { label: 'Failed / rejected', count: analytics.outcomes.failed, color: 'bg-rose-400' },
        { label: 'Queued / processing', count: analytics.outcomes.pending, color: 'bg-amber-400' },
        { label: 'Unconfirmed', count: analytics.outcomes.unknown, color: 'bg-slate-400' },
        { label: 'Test simulations', count: analytics.outcomes.simulated, color: 'bg-violet-400' },
        { label: 'Skipped', count: analytics.outcomes.skipped, color: 'bg-slate-300' },
        { label: 'Cancelled', count: analytics.outcomes.cancelled, color: 'bg-slate-400' },
    ];
    const resultTotal = outcomes.reduce((sum, outcome) => sum + outcome.count, 0);
    const panel = 'rounded-2xl border border-slate-200/80 bg-white shadow-sm';
    const categoryColors = ['bg-blue-500', 'bg-violet-500', 'bg-cyan-500', 'bg-emerald-500', 'bg-amber-500'];
    const focus = 'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600';
    return (
        <AppLayout>
            <Head title="Dashboard | Project AWARE" />
            <div className="space-y-6 text-slate-900">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-medium text-slate-500">
                            {new Intl.DateTimeFormat('en-PH', { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'Asia/Manila' }).format(
                                date,
                            )}
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900">
                            Welcome, {auth.user.name.split(' ')[0]}
                            <span className="text-blue-600">.</span>
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">Your emergency communications overview.</p>
                    </div>
                    <button
                        type="button"
                        disabled={refreshing}
                        onClick={() =>
                            router.reload({
                                only: ['stats', 'categories', 'barangays', 'recentContacts', 'updatedAt', 'analytics'],
                                onStart: () => setRefreshing(true),
                                onFinish: () => setRefreshing(false),
                            })
                        }
                        className={`inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 ${focus}`}
                    >
                        <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} aria-hidden="true" />
                        {refreshing ? 'Refreshing…' : 'Refresh'}
                    </button>
                </header>
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-600 to-indigo-600 p-6 text-white shadow-lg shadow-blue-200/50 sm:p-8">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -top-36 -right-20 h-96 w-96 rounded-full bg-cyan-300/10 blur-3xl"
                    />
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -right-4 -bottom-52 h-96 w-96 rounded-full border border-white/10"
                    />
                    <div className="relative flex flex-wrap items-center justify-between gap-8">
                        <div className="max-w-lg">
                            <p className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.16em] text-blue-100 uppercase">
                                <Shield className="h-4 w-4" aria-hidden="true" />
                                Project AWARE · Ilagan CDRRMO
                            </p>
                            <h2 className="mt-4 text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
                                Clear messages.
                                <br />
                                <span className="text-cyan-200">Connected communities.</span>
                            </h2>
                            <p className="mt-3 max-w-md text-sm leading-6 text-blue-100">
                                Prepare your next alert and reach the right contacts, barangays, or response teams.
                            </p>
                            <div className="mt-5 flex flex-wrap items-center gap-4">
                                <Link
                                    href={route('menu.textblast')}
                                    className={`inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50 ${focus}`}
                                >
                                    <Send className="h-4 w-4" aria-hidden="true" />
                                    Compose SMS
                                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                </Link>
                                <span className="inline-flex items-center gap-2 text-xs text-blue-100">
                                    <span className="h-1.5 w-1.5 rounded-full bg-white" />
                                    {smsMode === 'test'
                                        ? 'Test mode · SMS simulated'
                                        : smsMode === 'configured'
                                          ? `${smsProvider} configured`
                                          : 'SMS configuration needed'}
                                </span>
                            </div>
                        </div>
                        <div className="flex w-full items-center gap-5 rounded-2xl border border-white/20 bg-white/10 p-5 backdrop-blur-sm sm:w-auto">
                            <div className="relative h-24 w-24">
                                <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90" aria-hidden="true">
                                    <circle cx="50" cy="50" r="42" fill="none" stroke="white" strokeOpacity="0.15" strokeWidth="6" />
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="42"
                                        fill="none"
                                        stroke="#a5f3fc"
                                        strokeWidth="6"
                                        strokeLinecap="round"
                                        pathLength="100"
                                        strokeDasharray={`${coverage} 100`}
                                    />
                                </svg>
                                <div className="absolute inset-0 flex items-center justify-center text-2xl font-semibold tabular-nums">
                                    {coverage}
                                    <span className="text-sm">%</span>
                                </div>
                            </div>
                            <div>
                                <p className="text-sm font-medium">Directory coverage</p>
                                <p className="mt-1 text-xs leading-5 text-blue-100">
                                    {stats.coveredBarangays} of {stats.totalBarangays} barangays
                                    <br />
                                    have active recipients
                                </p>
                                <Link
                                    href={route('barangays.index')}
                                    className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-white hover:underline"
                                >
                                    View barangays
                                    <ArrowUpRight className="h-3 w-3" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    aria-label="Directory statistics and SMS credits"
                    className={`grid gap-4 sm:grid-cols-2 ${showCredits ? 'xl:grid-cols-5' : 'xl:grid-cols-4'}`}
                >
                    {cards.map(({ label, value, detail, icon: Icon, href, accent, iconTone, valueTone }) => (
                        <Link
                            key={label}
                            href={href}
                            className={`${panel} group relative overflow-hidden p-5 transition duration-200 hover:border-blue-200 hover:shadow-md motion-safe:hover:-translate-y-1 ${focus}`}
                        >
                            <div className={`absolute inset-x-0 top-0 h-1 ${accent}`} aria-hidden="true" />
                            <div className="flex items-center justify-between">
                                <span className={`flex h-11 w-11 items-center justify-center rounded-xl ${iconTone}`}>
                                    <Icon className="h-[18px] w-[18px]" aria-hidden="true" />
                                </span>
                                <ArrowUpRight className="h-4 w-4 text-slate-300 group-hover:text-blue-600" aria-hidden="true" />
                            </div>
                            <p className={`mt-5 text-3xl font-semibold tracking-tight tabular-nums ${valueTone}`}>{value}</p>
                            <p className="mt-1 text-sm font-medium text-slate-700">{label}</p>
                            <p className="mt-1 text-xs text-slate-500">{detail}</p>
                        </Link>
                    ))}
                    {showCredits && <SemaphoreCreditBalance refreshedAt={updatedAt} />}
                </section>
                <section aria-label="Quick actions" className="grid gap-3 md:grid-cols-3">
                    {shortcuts.map(({ title, description, icon: Icon, href }) => (
                        <Link
                            key={title}
                            href={href}
                            className={`${panel} group flex items-center gap-3 p-4 transition duration-200 hover:border-blue-200 hover:bg-blue-50/50 ${focus}`}
                        >
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <Icon className="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h2 className="text-sm font-medium">{title}</h2>
                                <p className="mt-0.5 text-xs text-slate-500">{description}</p>
                            </div>
                            <ArrowRight className="h-4 w-4 shrink-0 text-slate-300 group-hover:text-blue-600" aria-hidden="true" />
                        </Link>
                    ))}
                </section>
                <section aria-label="SMS analytics for the last seven days" className="grid gap-6 lg:grid-cols-2">
                    <div className={`${panel} p-5 sm:p-6`}>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Broadcast activity</h2>
                                <p className="mt-1 text-xs text-slate-500">Last 7 days · Philippine time</p>
                            </div>
                            <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                {analytics.liveBroadcasts + analytics.testBroadcasts} broadcasts
                            </span>
                        </div>
                        <div className="mt-5 flex gap-5 text-xs text-slate-500">
                            <span className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-blue-600" />
                                Live · {analytics.liveBroadcasts}
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-cyan-200" />
                                Test · {analytics.testBroadcasts}
                            </span>
                        </div>
                        <div className="mt-6 grid grid-cols-7 gap-2 rounded-xl bg-slate-50/80 px-3 py-4 sm:gap-3">
                            {analytics.daily.map((day) => (
                                <div
                                    key={day.date}
                                    className="text-center"
                                    aria-label={`${day.label}: ${day.live} live broadcasts, ${day.test} test broadcasts`}
                                >
                                    <p className="mb-2 text-xs font-medium text-slate-600 tabular-nums">{day.live + day.test}</p>
                                    <div className="flex h-28 items-end justify-center border-b border-slate-100">
                                        <div
                                            className="flex w-full max-w-10 flex-col overflow-hidden rounded-t-md"
                                            style={{ height: `${((day.live + day.test) / peak) * 100}%` }}
                                        >
                                            <div className="bg-cyan-200" style={{ flex: day.test }} />
                                            <div className="bg-gradient-to-t from-blue-600 to-blue-400" style={{ flex: day.live }} />
                                        </div>
                                    </div>
                                    <p className="mt-3 text-[10px] text-slate-500">{day.label}</p>
                                </div>
                            ))}
                        </div>
                        <p className="mt-5 text-xs text-slate-400">
                            {analytics.liveBroadcasts + analytics.testBroadcasts
                                ? 'Each bar counts broadcasts, not individual recipients.'
                                : 'No broadcasts in this period. Your activity will appear here.'}
                        </p>
                    </div>
                    <div className={`${panel} p-5 sm:p-6`}>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">SMS recipient results</h2>
                                <p className="mt-1 text-xs text-slate-500">Recipients of broadcasts created in the last 7 days</p>
                            </div>
                            <Link href={route('reports.index')} className="shrink-0 text-xs font-medium text-blue-700 hover:underline">
                                View reports
                            </Link>
                        </div>
                        <div className="mt-5 space-y-3">
                            {outcomes.map((outcome) => (
                                <div key={outcome.label} className="grid grid-cols-[130px_1fr_40px] items-center gap-3 text-xs">
                                    <span className="text-slate-600">{outcome.label}</span>
                                    <div className="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                        <div
                                            className={`h-full rounded-full ${outcome.color}`}
                                            style={{ width: `${resultTotal ? (outcome.count / resultTotal) * 100 : 0}%` }}
                                        />
                                    </div>
                                    <span className="text-right font-medium text-slate-700 tabular-nums">{outcome.count.toLocaleString()}</span>
                                </div>
                            ))}
                        </div>
                        <p className="mt-5 border-t border-slate-100 pt-3 text-[11px] leading-5 text-slate-400">
                            Gateway acceptance does not confirm handset delivery. Simulations send no SMS.
                        </p>
                    </div>
                </section>
                <div className="grid gap-6 lg:grid-cols-3">
                    <section className={`${panel} p-5 sm:p-6 lg:col-span-2`}>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Recipients by category</h2>
                                <p className="mt-1 text-xs text-slate-500">Share of active recipients in each group</p>
                            </div>
                            <Link href={route('menu.categories.index')} className="text-xs font-medium text-blue-700 hover:underline">
                                View categories
                            </Link>
                        </div>
                        <div className="mt-6 max-h-72 space-y-5 overflow-y-auto">
                            {categories.length ? (
                                categories.map((category, index) => (
                                    <div key={category.id}>
                                        <div className="mb-2 flex justify-between gap-3 text-sm">
                                            <Link
                                                href={route('menu.contacts.index', { category_id: category.id })}
                                                className="font-medium hover:text-blue-700"
                                            >
                                                {category.name}
                                            </Link>
                                            <span className="text-xs text-slate-500">
                                                {category.active_contacts_count} active{' '}
                                                <span className="text-slate-300">/ {category.contacts_count}</span>
                                            </span>
                                        </div>
                                        <div
                                            role="meter"
                                            aria-label={`${category.name} active recipients`}
                                            aria-valuemin={0}
                                            aria-valuemax={stats.activeContacts || 1}
                                            aria-valuenow={category.active_contacts_count}
                                            className="h-1.5 overflow-hidden rounded-full bg-slate-100"
                                        >
                                            <div
                                                className={`h-full rounded-full ${categoryColors[index % categoryColors.length]}`}
                                                style={{
                                                    width: `${stats.activeContacts ? (category.active_contacts_count / stats.activeContacts) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="py-12 text-center text-sm text-slate-500">Add categories to organize your recipient groups.</p>
                            )}
                        </div>
                    </section>
                    <section className={`${panel} p-5 sm:p-6`}>
                        <h2 className="text-sm font-semibold">Barangay directory</h2>
                        <p className="mt-1 text-xs text-slate-500">Browse active recipient coverage</p>
                        <input
                            aria-label="Find barangay"
                            placeholder="Find barangay…"
                            value={barangaySearch}
                            onChange={(event) => setBarangaySearch(event.target.value)}
                            className="mt-4 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                        />
                        <div className="mt-3 max-h-52 divide-y divide-slate-100 overflow-y-auto">
                            {barangays
                                .filter((group) => group.name.toLowerCase().includes(barangaySearch.toLowerCase()))
                                .map((group) => (
                                    <Link
                                        key={group.id}
                                        href={route('menu.contacts.index', { barangay_id: group.id })}
                                        className="flex items-center justify-between gap-3 py-3 text-xs hover:text-blue-700"
                                    >
                                        <span className="min-w-0 truncate">{group.name}</span>
                                        <span className={`shrink-0 tabular-nums ${group.active_contacts_count ? 'text-blue-600' : 'text-slate-400'}`}>
                                            {group.active_contacts_count} active
                                        </span>
                                    </Link>
                                ))}
                            {!barangays.some((group) => group.name.toLowerCase().includes(barangaySearch.toLowerCase())) && (
                                <p className="py-6 text-xs text-slate-500">No matching barangays.</p>
                            )}
                        </div>
                        <p className="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                            {stats.totalBarangays - stats.coveredBarangays} barangays have no active contacts.
                        </p>
                    </section>
                </div>
                <section className={`${panel} overflow-hidden`}>
                    <div className="flex items-center justify-between gap-3 p-5 sm:px-6">
                        <div>
                            <h2 className="text-sm font-semibold">Recent contacts</h2>
                            <p className="mt-1 text-xs text-slate-500">Latest additions to your directory</p>
                        </div>
                        <Link href={route('menu.contacts.index')} className="text-xs font-medium text-blue-700 hover:underline">
                            View all
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[540px] text-left text-sm">
                            <thead className="border-y border-slate-100 bg-blue-50/60 text-xs text-slate-600">
                                <tr>
                                    {['Contact', 'Barangay', 'Category', 'Status'].map((label) => (
                                        <th key={label} scope="col" className="px-6 py-3 font-medium">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentContacts.map((contact) => (
                                    <tr key={contact.id} className="hover:bg-blue-50/20">
                                        <td className="px-6 py-4">
                                            <span className="flex items-center gap-3">
                                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[10px] font-semibold text-blue-700">
                                                    {contact.first_name.charAt(0)}
                                                    {contact.last_name.charAt(0)}
                                                </span>
                                                <span className="font-medium">
                                                    {contact.first_name} {contact.last_name}
                                                </span>
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-xs text-slate-500">{contact.barangay?.name ?? 'Not applicable'}</td>
                                        <td className="px-6 py-4 text-xs text-slate-500">{contact.category?.name ?? 'Unassigned'}</td>
                                        <td className="px-6 py-4">
                                            <span
                                                className={`inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-medium ${contact.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                            >
                                                <span className="h-1 w-1 rounded-full bg-current" />
                                                {contact.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                                {!recentContacts.length && (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-10 text-center text-sm text-slate-500">
                                            No contacts yet. Add your first recipient in the contact directory.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
                <footer className="flex flex-wrap justify-between gap-2 text-[10px] text-slate-400">
                    <span>Project AWARE · City of Ilagan CDRRMO</span>
                    <span>
                        Updated {new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(date)}{' '}
                        · Philippine time
                    </span>
                </footer>
            </div>
        </AppLayout>
    );
}

import { Link, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

const tabs = [
    { label: 'Profile', href: '/settings/profile' },
    { label: 'Password', href: '/settings/password' },
    { label: 'Appearance', href: '/settings/appearance' },
];

export default function SettingsLayout({ children }: { children: ReactNode }) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Console Settings</h1>
                <p className="mt-1 text-sm text-slate-500">Manage your profile, password, and preferences.</p>
            </header>
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <nav aria-label="Settings navigation" className="flex gap-6 overflow-x-auto border-b border-slate-100 px-5 sm:px-6">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            aria-current={currentPath === tab.href ? 'page' : undefined}
                            className={`shrink-0 border-b-2 py-4 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-blue-600 ${currentPath === tab.href ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-blue-700'}`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
                <section className="text-foreground [&_h3]:text-foreground [&_header_p]:text-muted-foreground space-y-8 p-5 sm:p-6">
                    {children}
                </section>
            </div>
        </div>
    );
}

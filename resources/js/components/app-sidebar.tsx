import ProjectAwareLogo from '@/components/project-aware-logo';
import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    FileText,
    History,
    LayoutDashboard,
    LogOut,
    MapPin,
    Megaphone,
    PanelLeftClose,
    PanelLeftOpen,
    PhoneCall,
    Radio,
    Send,
    Settings,
    Tags,
    UserRoundCog,
    Users,
    X,
} from 'lucide-react';
import { useEffect } from 'react';

interface SidebarProps {
    isCollapsed: boolean;
    setIsCollapsed: (value: boolean) => void;
    isMobileOpen: boolean;
    setIsMobileOpen: (value: boolean) => void;
}

export function AppSidebar({ isCollapsed, setIsCollapsed, isMobileOpen, setIsMobileOpen }: SidebarProps) {
    const { url, props } = usePage<{
        contactCount: number;
        smsMode: string;
        smsProvider: string;
        permissions: { administer: boolean };
        auth: { user: { name: string; role: string } };
    }>();
    const expanded = !isCollapsed || isMobileOpen;
    const user = props.auth.user;
    const initials = user.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    const focus = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2';
    const safeRoute = (name: string, fallback: string) => {
        try {
            return route().has(name) ? route(name) : fallback;
        } catch {
            return fallback;
        }
    };
    useEffect(() => {
        if (!isMobileOpen) return;
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setIsMobileOpen(false);
        };
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, [isMobileOpen, setIsMobileOpen]);

    const groups = [
        {
            title: 'Operations',
            items: [
                { name: 'Dashboard', href: safeRoute('dashboard', '/dashboard'), icon: LayoutDashboard, active: url.startsWith('/dashboard') },
                {
                    name: 'SMS Text Blast',
                    href: safeRoute('menu.textblast', '/menu/textblast'),
                    icon: Send,
                    active: url.startsWith('/menu/textblast'),
                },
                {
                    name: 'Advisories & Alerts',
                    href: safeRoute('menu.quick-advisory', '/menu/quick-advisory'),
                    icon: Megaphone,
                    active: url.startsWith('/menu/quick-advisory'),
                },
                { name: 'Reports & SMS History', href: safeRoute('reports.index', '/reports'), icon: FileText, active: url.startsWith('/reports') },
            ],
        },
        {
            title: 'Directory',
            items: [
                {
                    name: 'Emergency Contacts',
                    href: safeRoute('menu.contacts.index', '/menu/contacts'),
                    icon: Users,
                    active: url.startsWith('/menu/contacts'),
                    badge: new Intl.NumberFormat('en-PH').format(props.contactCount ?? 0),
                },
                {
                    name: 'Categories',
                    href: safeRoute('menu.categories.index', '/menu/categories'),
                    icon: Tags,
                    active: url.startsWith('/menu/categories'),
                },
                { name: 'Barangay Sectors', href: safeRoute('barangays.index', '/barangays'), icon: MapPin, active: url.startsWith('/barangays') },
                { name: 'Emergency Hotlines', href: safeRoute('hotlines.index', '/hotlines'), icon: PhoneCall, active: url.startsWith('/hotlines') },
            ],
        },
        {
            title: 'Administration',
            items: [
                ...(props.permissions.administer
                    ? [
                          { name: 'Account Access', href: safeRoute('users.index', '/users'), icon: UserRoundCog, active: url.startsWith('/users') },
                          {
                              name: 'Audit Logs',
                              href: safeRoute('audit-logs.index', '/audit-logs'),
                              icon: History,
                              active: url.startsWith('/audit-logs'),
                          },
                      ]
                    : []),
                {
                    name: 'Console Settings',
                    href: safeRoute('profile.edit', '/settings/profile'),
                    icon: Settings,
                    active: url.startsWith('/settings'),
                },
            ],
        },
    ];
    const smsStatus =
        props.smsMode === 'test'
            ? { label: 'SMS test mode', detail: 'Messages are simulated', color: 'bg-blue-50 text-blue-700 border-blue-100' }
            : props.smsMode === 'configured'
              ? {
                    label: `${props.smsProvider} configured`,
                    detail: 'Live SMS is enabled',
                    color: 'bg-emerald-50 text-emerald-700 border-emerald-100',
                }
              : { label: 'SMS setup needed', detail: 'Configure your gateway', color: 'bg-amber-50 text-amber-800 border-amber-100' };

    return (
        <>
            {isMobileOpen && (
                <button
                    type="button"
                    aria-label="Close navigation"
                    onClick={() => setIsMobileOpen(false)}
                    className="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden"
                />
            )}
            <aside
                aria-label="Main sidebar"
                className={`fixed inset-y-0 left-0 z-50 flex flex-col border-r border-slate-200 bg-white text-slate-900 shadow-xl shadow-slate-900/5 transition-[width,transform] duration-200 motion-reduce:transition-none ${isMobileOpen ? 'w-64 translate-x-0' : 'w-64 -translate-x-full'} ${isCollapsed ? 'md:w-16' : 'md:w-64'} md:translate-x-0`}
            >
                <div
                    className={`flex h-20 shrink-0 items-center border-b border-slate-100 ${expanded ? 'justify-between gap-2 px-4' : 'justify-center px-2'}`}
                >
                    {expanded && (
                        <Link
                            href={safeRoute('dashboard', '/dashboard')}
                            onClick={() => setIsMobileOpen(false)}
                            aria-label="Project AWARE dashboard"
                            className={`flex min-w-0 items-center gap-2.5 rounded-lg ${focus}`}
                        >
                            <ProjectAwareLogo />
                        </Link>
                    )}
                    <button
                        type="button"
                        aria-label="Close navigation"
                        onClick={() => setIsMobileOpen(false)}
                        className={`rounded-lg p-2 text-slate-500 hover:bg-slate-100 md:hidden ${focus}`}
                    >
                        <X className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        aria-expanded={!isCollapsed}
                        title={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        onClick={() => setIsCollapsed(!isCollapsed)}
                        className={`hidden rounded-lg p-2 text-slate-500 hover:bg-blue-50 hover:text-blue-700 md:block ${focus}`}
                    >
                        {isCollapsed ? <PanelLeftOpen className="h-5 w-5" /> : <PanelLeftClose className="h-4 w-4" />}
                    </button>
                </div>
                <div className={expanded ? 'px-4 pt-4' : 'px-2 pt-4'}>
                    <div
                        title={`${smsStatus.label}: ${smsStatus.detail}`}
                        className={`flex items-center rounded-lg border ${smsStatus.color} ${expanded ? 'gap-2.5 px-3 py-2.5' : 'justify-center py-2.5'}`}
                    >
                        <Radio className="h-4 w-4 shrink-0" aria-hidden="true" />
                        {expanded ? (
                            <div>
                                <p className="text-xs font-semibold">{smsStatus.label}</p>
                                <p className="mt-0.5 text-[10px] opacity-80">{smsStatus.detail}</p>
                            </div>
                        ) : (
                            <span className="sr-only">{smsStatus.label}</span>
                        )}
                    </div>
                </div>
                <nav aria-label="Main navigation" className={`min-h-0 flex-1 overflow-y-auto py-5 ${expanded ? 'px-3' : 'px-2'}`}>
                    {groups.map((group, index) => (
                        <div key={group.title} className={index > 0 ? 'mt-5' : ''}>
                            {expanded ? (
                                <h2 className="mb-2 px-3 text-[10px] font-semibold tracking-[0.14em] text-slate-400 uppercase">{group.title}</h2>
                            ) : (
                                index > 0 && <div className="mx-2 mb-3 border-t border-slate-100" />
                            )}
                            <ul className="space-y-1">
                                {group.items.map((item) => (
                                    <li key={item.name}>
                                        <Link
                                            href={item.href}
                                            onClick={() => setIsMobileOpen(false)}
                                            aria-label={item.name}
                                            aria-current={item.active ? 'page' : undefined}
                                            title={!expanded ? item.name : undefined}
                                            className={`group flex min-h-11 items-center rounded-lg text-[13px] font-medium transition-colors ${expanded ? 'gap-3 px-3' : 'justify-center px-2'} ${item.active ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/15' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'} ${focus}`}
                                        >
                                            <item.icon
                                                className={`h-[18px] w-[18px] shrink-0 ${item.active ? 'text-white' : 'text-slate-400 group-hover:text-blue-600'}`}
                                                aria-hidden="true"
                                            />
                                            {expanded && (
                                                <>
                                                    <span className="min-w-0 flex-1 truncate">{item.name}</span>
                                                    {'badge' in item ? (
                                                        <span
                                                            className={`rounded-md px-1.5 py-0.5 text-[10px] tabular-nums ${item.active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'}`}
                                                        >
                                                            {item.badge}
                                                        </span>
                                                    ) : (
                                                        item.active && <ChevronRight className="h-3.5 w-3.5 text-blue-100" aria-hidden="true" />
                                                    )}
                                                </>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>
                <div className={`shrink-0 border-t border-slate-100 ${expanded ? 'p-4' : 'px-2 py-3'}`}>
                    <div className={`flex ${expanded ? 'items-center gap-2.5' : 'flex-col items-center gap-3'}`}>
                        <Link
                            href={safeRoute('profile.edit', '/settings/profile')}
                            title={user.name}
                            aria-label="Open your profile"
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700 ${focus}`}
                        >
                            {initials || 'U'}
                        </Link>
                        {expanded && (
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-xs font-semibold" title={user.name}>
                                    {user.name}
                                </p>
                                <p className="mt-0.5 text-[11px] text-slate-500">{user.role === 'admin' ? 'Administrator' : 'Staff'}</p>
                            </div>
                        )}
                        <Link
                            href={safeRoute('logout', '/logout')}
                            method="post"
                            as="button"
                            aria-label="Sign out"
                            title="Sign out"
                            className={`rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 ${focus}`}
                        >
                            <LogOut className="h-4 w-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </aside>
        </>
    );
}

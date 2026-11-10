import { AppSidebar } from '@/components/app-sidebar';
import { usePage } from '@inertiajs/react';
import { Menu, Shield } from 'lucide-react';
import { ReactNode, useState } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: { title: string; href: string }[];
}

export default function AppLayout({ children }: AppLayoutProps) {
    const { flash, errors, smsMode } = usePage<{ flash: { success?: string; error?: string }; errors: Record<string, string>; smsMode: string }>()
        .props;
    const [isCollapsed, setIsCollapsed] = useState(false);
    const [isMobileOpen, setIsMobileOpen] = useState(false);

    return (
        <div className="aware-console bg-background text-foreground min-h-screen w-full font-sans antialiased selection:bg-blue-500 selection:text-white">
            {/* Sidebar Component */}
            <AppSidebar isCollapsed={isCollapsed} setIsCollapsed={setIsCollapsed} isMobileOpen={isMobileOpen} setIsMobileOpen={setIsMobileOpen} />

            {/* Mobile Header Top Navigation */}
            <header className="sticky top-0 z-30 flex h-14 w-full items-center justify-between border-b border-blue-900/20 bg-[#0343E3] px-4 text-white md:hidden">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => setIsMobileOpen(true)}
                        className="rounded-lg p-1.5 text-white/90 transition-transform hover:bg-white/10 active:scale-95"
                        aria-label="Open Navigation"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    <div className="flex items-center gap-2">
                        <Shield className="h-4 w-4 text-amber-300" />
                        <span className="text-xs font-black tracking-wider uppercase">CDRRMO ILAGAN</span>
                    </div>
                </div>
                <span className="rounded-full border border-emerald-400/30 bg-emerald-400/20 px-2.5 py-0.5 text-[10px] font-bold text-emerald-300">
                    {smsMode === 'test' ? 'SMS TEST MODE' : 'CDRRMO CONSOLE'}
                </span>
            </header>

            {/* Main Content Area - Expands to 100% width cleanly */}
            <main className={`min-h-screen w-full transition-all duration-300 ease-in-out ${isCollapsed ? 'md:pl-16' : 'md:pl-64'}`}>
                <div className="mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
                    {flash?.success && (
                        <div role="status" className="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}
                    {Object.keys(errors ?? {}).length > 0 && (
                        <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            {Object.values(errors).map((error, index) => (
                                <p key={index}>{error}</p>
                            ))}
                        </div>
                    )}
                    {children}
                </div>
            </main>
        </div>
    );
}

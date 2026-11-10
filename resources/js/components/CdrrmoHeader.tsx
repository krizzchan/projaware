import { Link } from '@inertiajs/react';
import { Bell, ChevronDown, Menu, PhoneCall, User, X } from 'lucide-react';
import { useState } from 'react';

// shadcn UI Components
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface HeaderProps {
    user?: {
        name: string;
        email: string;
        role?: string;
    };
    alertLevel?: 'Normal' | 'Advisory' | 'Warning' | 'Critical';
}

export default function CdrrmoHeader({ user, alertLevel = 'Normal' }: HeaderProps) {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <header className="bg-background/95 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-40 w-full border-b backdrop-blur">
            {/* Top Operational Status Bar */}
            <div className="bg-primary text-primary-foreground px-4 py-1.5 text-xs shadow-inner">
                <div className="mx-auto flex max-w-7xl items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span className="font-medium tracking-wide uppercase">EOC Status: Active Operations</span>
                    </div>

                    <div className="flex items-center gap-4">
                        <span className="hidden font-medium sm:inline">
                            Alert Status: <strong className="font-semibold text-amber-300">{alertLevel}</strong>
                        </span>
                        <span className="text-primary-foreground/70 hidden md:inline">|</span>
                        <a href="tel:911" className="flex items-center gap-1 font-bold text-amber-300 transition-colors hover:text-white">
                            <PhoneCall className="h-3 w-3" /> Hotline: (078) 624-0000
                        </a>
                    </div>
                </div>
            </div>

            {/* Main Header / Navigation */}
            <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 md:px-6">
                {/* Brand Logo & Title */}
                <Link href={route('dashboard')} className="flex items-center gap-3 transition hover:opacity-95">
                    <img src="/images/ilagan-seal.png" alt="City of Ilagan Seal" className="h-11 w-11 object-contain drop-shadow-sm" />
                    <div className="flex flex-col">
                        <span className="text-muted-foreground text-[11px] leading-tight font-bold tracking-widest uppercase">
                            Republic of the Philippines
                        </span>
                        <h1 className="text-primary text-lg leading-tight font-black tracking-tight">CDRRMO ILAGAN</h1>
                        <span className="text-[10px] leading-tight font-semibold text-amber-600 dark:text-amber-400">
                            City Disaster Risk Reduction & Management Office
                        </span>
                    </div>
                </Link>

                {/* Desktop Navigation */}
                <nav className="hidden items-center gap-1 text-sm font-medium md:flex">
                    <Link
                        href={route('dashboard')}
                        className="text-foreground/80 hover:bg-secondary hover:text-primary rounded-md px-3 py-2 transition"
                    >
                        Dashboard
                    </Link>
                    <Link
                        href={route('menu.contacts.index')}
                        className="text-foreground/80 hover:bg-secondary hover:text-primary rounded-md px-3 py-2 transition"
                    >
                        Directory
                    </Link>
                    <Link href="#" className="text-foreground/80 hover:bg-secondary hover:text-primary rounded-md px-3 py-2 transition">
                        Incidents
                    </Link>
                    <Link href="#" className="text-foreground/80 hover:bg-secondary hover:text-primary rounded-md px-3 py-2 transition">
                        Resources
                    </Link>
                </nav>

                {/* Right Controls (Notifications & User Profile) */}
                <div className="hidden items-center gap-2 md:flex">
                    <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-primary relative">
                        <Bell className="h-5 w-5" />
                        <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-600"></span>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="flex items-center gap-2 border-slate-200 dark:border-slate-800">
                                <div className="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-full">
                                    <User className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-xs font-semibold">{user?.name || 'Duty Officer'}</span>
                                <ChevronDown className="text-muted-foreground h-3.5 w-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel className="font-normal">
                                <div className="flex flex-col space-y-1">
                                    <p className="text-sm leading-none font-medium">{user?.name || 'Duty Officer'}</p>
                                    <p className="text-muted-foreground text-xs leading-none">{user?.email || 'eoc@ilagan.gov.ph'}</p>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={route('profile.edit')}>Account Settings</Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild className="cursor-pointer text-red-600">
                                <Link href={route('logout')} method="post" as="button" className="w-full text-left">
                                    Log Out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Mobile Menu Toggle */}
                <div className="flex items-center gap-2 md:hidden">
                    <Button variant="ghost" size="icon" onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                        {isMobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </Button>
                </div>
            </div>

            {/* Mobile Dropdown Navigation */}
            {isMobileMenuOpen && (
                <div className="bg-background space-y-3 border-t px-4 pt-3 pb-6 md:hidden">
                    <nav className="flex flex-col space-y-1">
                        <Link href={route('dashboard')} className="hover:bg-secondary hover:text-primary rounded-md px-3 py-2 text-sm font-medium">
                            Dashboard
                        </Link>
                        <Link
                            href={route('menu.contacts.index')}
                            className="hover:bg-secondary hover:text-primary rounded-md px-3 py-2 text-sm font-medium"
                        >
                            Directory
                        </Link>
                        <Link href="#" className="hover:bg-secondary hover:text-primary rounded-md px-3 py-2 text-sm font-medium">
                            Incidents
                        </Link>
                    </nav>

                    <div className="flex items-center justify-between border-t pt-3">
                        <div className="flex items-center gap-2">
                            <div className="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-full">
                                <User className="h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold">{user?.name || 'Duty Officer'}</p>
                                <p className="text-muted-foreground text-[10px]">{user?.email || 'eoc@ilagan.gov.ph'}</p>
                            </div>
                        </div>

                        <Link href={route('logout')} method="post" as="button" className="text-xs font-semibold text-red-600">
                            Log Out
                        </Link>
                    </div>
                </div>
            )}
        </header>
    );
}

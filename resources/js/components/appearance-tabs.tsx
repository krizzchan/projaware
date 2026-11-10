import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { LucideIcon, Monitor, Moon, Sun } from 'lucide-react';
import { HTMLAttributes } from 'react';

export default function AppearanceToggleTab({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string; description: string }[] = [
        { value: 'light', icon: Sun, label: 'Light', description: 'A bright, clean workspace.' },
        { value: 'dark', icon: Moon, label: 'Dark', description: 'A softer view in low light.' },
        { value: 'system', icon: Monitor, label: 'System', description: 'Match your device settings.' },
    ];

    return (
        <div role="group" aria-label="Appearance theme" className={cn('grid grid-cols-1 gap-3 sm:grid-cols-3', className)} {...props}>
            {tabs.map(({ value, icon: Icon, label, description }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-w-0 flex-col items-start rounded-xl border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
                        appearance === value
                            ? 'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200'
                            : 'border-border bg-card text-card-foreground hover:border-blue-400',
                    )}
                >
                    <Icon className="mb-3 h-5 w-5" aria-hidden="true" />
                    <span className="text-sm font-semibold">{label}</span>
                    <span className="text-muted-foreground mt-1 text-xs leading-5">{description}</span>
                </button>
            ))}
        </div>
    );
}

import { Coins } from 'lucide-react';
import { useEffect, useState } from 'react';

type Balance = { status: string; credit_balance: number | null; checked_at: string | null };

export default function SemaphoreCreditBalance({ refreshedAt }: { refreshedAt: string }) {
    const [balance, setBalance] = useState<Balance>({ status: 'loading', credit_balance: null, checked_at: null });
    useEffect(() => {
        const controller = new AbortController();
        let retry: ReturnType<typeof setTimeout> | undefined;
        let attempts = 0;
        const load = () =>
            fetch(route('dashboard.sms-balance'), { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) throw new Error('Unavailable');
                    return response.json();
                })
                .then((data: Balance) => {
                    if (controller.signal.aborted) return;
                    if (data.status === 'loading' && ++attempts < 5) {
                        retry = setTimeout(load, 2000);
                    } else {
                        setBalance(data.status === 'loading' ? { status: 'unavailable', credit_balance: null, checked_at: null } : data);
                    }
                })
                .catch(() => {
                    if (!controller.signal.aborted) setBalance({ status: 'unavailable', credit_balance: null, checked_at: null });
                });
        void load();
        return () => {
            controller.abort();
            clearTimeout(retry);
        };
    }, [refreshedAt]);
    const available = balance.status === 'ok' && typeof balance.credit_balance === 'number';
    return (
        <article
            aria-label="Semaphore credit balance"
            className="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1"
        >
            <div className="absolute inset-x-0 top-0 h-1 bg-cyan-500" aria-hidden="true" />
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <Coins className="h-5 w-5" aria-hidden="true" />
            </span>
            <p aria-live="polite" className="mt-5 text-3xl font-semibold tracking-tight text-blue-700 tabular-nums">
                {available
                    ? new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(balance.credit_balance!)
                    : balance.status === 'loading'
                      ? '…'
                      : '—'}
            </p>
            <h2 className="mt-1 text-sm font-medium text-slate-700">Semaphore credits</h2>
            <p className="mt-1 text-xs leading-5 text-slate-500">
                {available
                    ? 'Balance cached for up to 5 minutes.'
                    : balance.status === 'loading'
                      ? 'Checking account balance…'
                      : balance.status === 'unconfigured'
                        ? 'Configure the Semaphore API key.'
                        : 'Balance unavailable. Try refreshing later.'}
            </p>
            {available && balance.checked_at && (
                <p className="mt-1 text-xs text-slate-500">
                    Checked{' '}
                    {new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' }).format(
                        new Date(balance.checked_at),
                    )}
                </p>
            )}
        </article>
    );
}

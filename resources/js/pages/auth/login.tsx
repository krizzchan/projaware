import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ClipboardList, Eye, EyeOff, LoaderCircle, LockKeyhole, Mail, Send, Users } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type LoginForm = { email: string; password: string; remember: boolean };
interface LoginProps {
    status?: string;
    canResetPassword?: boolean;
}

export default function Login({ status, canResetPassword = true }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({ email: '', password: '', remember: false });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), {
            onFinish: () => {
                reset('password');
                setShowPassword(false);
            },
        });
    };
    const focus = 'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600';
    const inputClass =
        'h-12 rounded-xl border-slate-200 bg-white pl-10 text-sm text-slate-900 shadow-none placeholder:text-slate-400 focus-visible:border-blue-500 focus-visible:ring-blue-100';
    return (
        <div className="aware-index flex min-h-screen flex-col bg-white text-slate-900">
            <Head title="SMS Text Blast Sign in | Project AWARE" />
            <header className="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-6 py-6 sm:px-10 lg:px-16">
                <Link href={route('home')} className={`text-base font-semibold tracking-tight ${focus}`}>
                    Project <span className="text-blue-600">AWARE</span>
                    <span className="mt-1 block text-[10px] font-normal tracking-wide text-slate-500">Ilagan CDRRMO · SMS Text Blast</span>
                </Link>
                <Link
                    href={route('home')}
                    className={`inline-flex min-h-11 items-center gap-2 text-xs font-medium text-slate-500 hover:text-blue-700 ${focus}`}
                >
                    <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                    Back to home
                </Link>
            </header>
            <main className="mx-auto grid w-full max-w-7xl flex-1 items-center gap-12 px-6 py-8 sm:px-10 sm:py-12 lg:grid-cols-2 lg:gap-20 lg:px-16">
                <section
                    aria-label="About Project AWARE"
                    className="aware-enter hidden rounded-3xl border border-blue-100 p-10 lg:block"
                    style={{ background: 'linear-gradient(145deg, #f6faff, #edf2ff)' }}
                >
                    <p className="text-[10px] font-medium tracking-widest text-blue-700 uppercase">Ilagan CDRRMO · SMS Text Blast</p>
                    <h2 className="mt-6 text-4xl leading-tight font-semibold tracking-tight">
                        Emergency alerts.
                        <br />
                        <span className="text-blue-600">Connected communities.</span>
                    </h2>
                    <p className="mt-5 max-w-sm text-sm leading-7 text-slate-500">
                        Prepare SMS advisories for residents, responders, and partner agencies. Choose recipients by contact, barangay, or category,
                        then review your broadcast results.
                    </p>
                    <div className="mt-9 space-y-5 border-t border-blue-100 pt-7">
                        {[
                            { icon: Users, label: 'Single, multiple, barangay, or category recipients' },
                            { icon: Send, label: 'Reusable advisory templates for text blasts' },
                            { icon: ClipboardList, label: 'Accepted, failed, and simulated SMS reports' },
                        ].map(({ icon: Icon, label }) => (
                            <div key={label} className="flex items-center gap-3 text-xs text-slate-600">
                                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white">
                                    <Icon className="h-4 w-4 text-blue-600" strokeWidth={1.5} aria-hidden="true" />
                                </span>
                                {label}
                            </div>
                        ))}
                    </div>
                    <p className="mt-10 text-[10px] text-slate-500">City Disaster Risk Reduction and Management Office</p>
                </section>
                <section aria-labelledby="login-heading" className="aware-enter mx-auto w-full max-w-sm" style={{ animationDelay: '80ms' }}>
                    <div aria-label="City of Ilagan and CDRRMO" className="mb-7 grid w-full max-w-sm grid-cols-3 items-center gap-3">
                        <img
                            src="/images/city-of-ilagan-seal.png"
                            alt="City of Ilagan, Isabela official seal"
                            width={128}
                            height={128}
                            className="aspect-square w-full max-w-32 object-contain"
                        />
                        <img
                            src="/images/ilagan-2030.png"
                            alt="City of Ilagan 2030 — Towards a Liveable City"
                            width={128}
                            height={128}
                            className="aspect-square w-full max-w-32 object-contain"
                        />
                        <img
                            src="/images/ilagan-cdrrmo.png?v=2"
                            alt="City of Ilagan Disaster Risk Reduction and Management Council"
                            width={128}
                            height={128}
                            className="aspect-square w-full max-w-32 rounded-full object-contain"
                        />
                    </div>
                    <h1 id="login-heading" className="mt-5 text-3xl font-semibold tracking-tight">
                        CDRRMO Text Blast
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-slate-500">Sign in to prepare advisories and manage SMS broadcasts.</p>
                    {status && (
                        <div role="status" className="mt-5 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800">
                            {status}
                        </div>
                    )}
                    <form onSubmit={submit} className="mt-7 space-y-5" aria-busy={processing}>
                        <div>
                            <Label htmlFor="email" className="text-xs font-medium text-slate-700">
                                Email address
                            </Label>
                            <div className="relative mt-2">
                                <Mail className="pointer-events-none absolute top-4 left-3.5 h-4 w-4 text-slate-400" aria-hidden="true" />
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="username"
                                    required
                                    autoFocus
                                    disabled={processing}
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    placeholder="you@example.com"
                                    aria-invalid={Boolean(errors.email)}
                                    aria-describedby={errors.email ? 'email-error' : undefined}
                                    className={inputClass}
                                />
                            </div>
                            <InputError id="email-error" message={errors.email} className="mt-2" />
                        </div>
                        <div>
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor="password" className="text-xs font-medium text-slate-700">
                                    Password
                                </Label>
                                {canResetPassword && (
                                    <Link
                                        href={route('password.request')}
                                        className={`text-xs font-medium text-blue-600 hover:text-blue-800 ${focus}`}
                                    >
                                        Forgot password?
                                    </Link>
                                )}
                            </div>
                            <div className="relative mt-2">
                                <LockKeyhole className="pointer-events-none absolute top-4 left-3.5 h-4 w-4 text-slate-400" aria-hidden="true" />
                                <Input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    required
                                    disabled={processing}
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    placeholder="Enter your password"
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={errors.password ? 'password-error' : undefined}
                                    className={`${inputClass} pr-12`}
                                />
                                <button
                                    type="button"
                                    disabled={processing}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                    aria-pressed={showPassword}
                                    aria-controls="password"
                                    onClick={() => setShowPassword((value) => !value)}
                                    className={`absolute top-0 right-0 flex h-12 w-12 items-center justify-center rounded-xl text-slate-400 hover:text-blue-600 disabled:opacity-50 ${focus}`}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" aria-hidden="true" />
                                    ) : (
                                        <Eye className="h-4 w-4" aria-hidden="true" />
                                    )}
                                </button>
                            </div>
                            <InputError id="password-error" message={errors.password} className="mt-2" />
                        </div>
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="remember"
                                name="remember"
                                checked={data.remember}
                                disabled={processing}
                                onCheckedChange={(checked) => setData('remember', checked === true)}
                                className="border-slate-300 data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white"
                            />
                            <Label htmlFor="remember" className="cursor-pointer text-xs font-normal text-slate-600">
                                Remember me
                            </Label>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="h-12 w-full rounded-xl bg-blue-600 text-sm font-medium text-white shadow-none transition-colors hover:bg-blue-700 motion-reduce:transition-none"
                        >
                            {processing ? (
                                <>
                                    <LoaderCircle className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                                    Signing in…
                                </>
                            ) : (
                                <>
                                    Sign in
                                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                </>
                            )}
                        </Button>
                    </form>
                    <p className="mt-6 border-t border-slate-100 pt-5 text-center text-[11px] leading-6 text-slate-500">
                        Authorized personnel only.
                        <br />
                        For account access, contact your administrator.
                    </p>
                </section>
            </main>
            <footer className="mx-auto w-full max-w-7xl px-6 py-5 text-[10px] text-slate-500 sm:px-10 lg:px-16">
                Project AWARE · City Government of Ilagan · CDRRMO
            </footer>
        </div>
    );
}

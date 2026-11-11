import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, ClipboardList, LockKeyhole, Send, Users } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const destination = route(auth.user ? 'dashboard' : 'login');
    const features = [
        { icon: Users, title: 'Flexible recipients', description: 'Select one contact, multiple contacts, a barangay, or a recipient category.' },
        { icon: Send, title: 'Advisory templates', description: 'Save reusable SMS drafts and update each advisory before sending.' },
        {
            icon: ClipboardList,
            title: 'SMS results & reports',
            description: 'Review gateway-accepted, failed, and simulated messages, and export reports to Excel.',
        },
    ];
    const focus = 'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600';
    return (
        <div className="aware-index flex min-h-screen flex-col bg-white text-slate-900">
            <Head title="Project AWARE SMS Text Blast | Ilagan CDRRMO" />
            <header className="aware-enter mx-auto w-full max-w-7xl px-6 sm:px-10 lg:px-16">
                <nav aria-label="Main navigation" className="flex items-center justify-between gap-4 border-b border-slate-100 py-6">
                    <Link href={route('home')} aria-label="Project AWARE home" className={`text-base font-semibold tracking-tight ${focus}`}>
                        Project <span className="text-blue-600">AWARE</span>
                        <span className="mt-1 block text-[10px] font-normal tracking-wide text-slate-500">Ilagan CDRRMO · SMS Text Blast</span>
                    </Link>
                    <Link
                        href={destination}
                        className={`inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-200 px-4 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 motion-reduce:transition-none ${focus}`}
                    >
                        {auth.user ? 'Dashboard' : 'Sign in'}
                        <ArrowUpRight className="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </nav>
            </header>
            <main className="mx-auto w-full max-w-7xl flex-1 px-6 sm:px-10 lg:px-16">
                <section className="grid items-center gap-12 py-14 sm:py-20 lg:grid-cols-2 lg:gap-16">
                    <div>
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
                        <p className="flex items-center gap-3 text-[10px] font-medium tracking-widest text-blue-700 uppercase">
                            <span className="h-px w-6 bg-blue-500" aria-hidden="true" />
                            CDRRMO emergency SMS Text Blast
                        </p>
                        <h1 className="mt-6 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                            Project AWARE
                            <br />
                            <span className="text-blue-600">SMS Text Blast.</span>
                        </h1>
                        <p className="mt-5 max-w-md text-sm leading-7 text-slate-500">
                            Prepare emergency SMS advisories for residents, responders, and partner agencies. A focused text blast workspace for the
                            City Disaster Risk Reduction and Management Office. John Xedric Alejo
                        </p>
                        <div className="mt-8 flex flex-wrap items-center gap-5">
                            <Link
                                href={destination}
                                className={`group inline-flex min-h-12 items-center gap-3 rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-700 motion-reduce:transition-none ${focus}`}
                            >
                                {auth.user ? 'Open dashboard' : 'Access SMS Text Blast'}
                                <ArrowRight
                                    className="h-4 w-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                href="#workspace"
                                className={`inline-flex min-h-11 items-center gap-2 text-xs font-medium text-slate-600 hover:text-blue-700 ${focus}`}
                            >
                                Explore the workspace
                                <ArrowUpRight className="h-3.5 w-3.5" aria-hidden="true" />
                            </a>
                        </div>
                        <p className="mt-5 flex items-center gap-2 text-[11px] text-slate-500">
                            <LockKeyhole className="h-3 w-3" aria-hidden="true" />
                            For authorized personnel
                        </p>
                    </div>
                    <aside
                        aria-label="Advisory workflow"
                        className="aware-enter relative isolate overflow-hidden rounded-3xl border border-blue-100 p-6 sm:p-8"
                        style={{ animationDelay: '160ms', background: 'linear-gradient(145deg, #f5f9ff 0%, #edf2ff 55%, #fafcff 100%)' }}
                    >
                        <div className="flex items-center justify-between gap-3 text-[10px] font-medium text-slate-500">
                            <span>PROJECT AWARE</span>
                            <span>PREPARE · COORDINATE · INFORM</span>
                        </div>
                        <div className="aware-float relative mt-7 rounded-2xl border border-white bg-white/95 p-5 shadow-xl shadow-blue-900/5 sm:p-6">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white">
                                <Send className="h-5 w-5" strokeWidth={1.5} aria-hidden="true" />
                            </span>
                            <h2 className="mt-5 text-xl font-semibold tracking-tight">
                                The right advisory.
                                <br />
                                The right recipients.
                            </h2>
                            <p className="mt-3 text-xs leading-6 text-slate-500">
                                Use a saved template or compose an advisory. Select individual contacts, multiple recipients, a barangay, or a
                                category, then review before dispatch.
                            </p>
                            <ol className="mt-6 space-y-4 border-t border-slate-100 pt-5">
                                {['Prepare your advisory', 'Choose the right recipients', 'Review and dispatch'].map((step, index) => (
                                    <li key={step} className="flex items-center gap-3 text-xs text-slate-600">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-[10px] font-medium text-blue-600">
                                            0{index + 1}
                                        </span>
                                        {step}
                                    </li>
                                ))}
                            </ol>
                        </div>
                        <p className="mt-5 text-center text-[10px] text-slate-500">Gateway acceptance does not confirm handset delivery.</p>
                    </aside>
                </section>
                <section id="workspace" aria-label="Workspace features" className="scroll-mt-6 border-t border-slate-100 py-8 sm:py-10">
                    <div className="mb-7 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold tracking-tight">Prepare. Reach. Review.</h2>
                        <span className="text-[11px] text-slate-500">City of Ilagan · Isabela</span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3 md:gap-5">
                        {features.map(({ icon: Icon, title, description }, index) => (
                            <article
                                key={title}
                                className="aware-enter aware-feature flex items-start gap-3 rounded-2xl border border-slate-100 bg-white p-5"
                                style={{ animationDelay: `${240 + index * 70}ms` }}
                            >
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50">
                                    <Icon className="h-4 w-4 text-blue-600" strokeWidth={1.5} aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-medium">{title}</h3>
                                    <p className="mt-1.5 text-xs leading-6 text-slate-500">{description}</p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            </main>
            <footer className="mx-auto w-full max-w-7xl px-6 sm:px-10 lg:px-16">
                <div className="flex flex-wrap justify-between gap-2 border-t border-slate-100 py-5 text-[10px] text-slate-500">
                    <span>Project AWARE · City Government of Ilagan · CDRRMO</span>
                    <span>Need access? Contact your administrator.</span>
                </div>
            </footer>
        </div>
    );
}

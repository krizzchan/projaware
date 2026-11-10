export default function ProjectAwareLogo() {
    return (
        <span className="flex min-w-0 items-center gap-2.5 text-left">
            <img
                src="/images/ilagan-cdrrmo.png?v=2"
                alt="Ilagan CDRRMO logo"
                width={44}
                height={44}
                className="h-11 w-11 shrink-0 rounded-full object-contain"
            />
            <span className="min-w-0">
                <span className="block text-[15px] leading-5 tracking-tight">
                    <span className="font-medium text-slate-500">Project </span>
                    <span className="font-bold text-blue-700">AWARE</span>
                </span>
                <span className="mt-1 block text-[10px] leading-3 text-slate-500">Ilagan CDRRMO</span>
            </span>
        </span>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ClipboardCheck,
    MessageSquareText,
    Users,
} from 'lucide-react';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { PRODUCT_NAME, PRODUCT_TAGLINE } from '@/lib/brand';
import { dashboard, login } from '@/routes';

const capabilities = [
    {
        title: 'Workforce Management',
        detail: 'Employees, DSPs, supervisors, and client assignments in one place.',
        icon: Users,
    },
    {
        title: 'Scheduling & Visits',
        detail: 'Plan shifts, run EVV-style visits, and complete care-plan tasks.',
        icon: CalendarClock,
    },
    {
        title: 'Attendance & Compliance',
        detail: 'Track exceptions, credentials, and operational follow-up.',
        icon: ClipboardCheck,
    },
    {
        title: 'Messaging & Reporting',
        detail: 'Internal messages, announcements, and payroll-hour exports.',
        icon: MessageSquareText,
    },
];

const roles = ['Admin', 'Supervisor', 'DSP'];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-svh flex-col">
                <header className="surface-frosted sticky top-0 z-20 border-b border-border/70">
                    <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-4 px-5">
                        <Link href="/" className="flex min-w-0 items-center">
                            <BrandMark
                                variant="horizontal"
                                className="h-9 sm:h-10"
                            />
                        </Link>
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href={login()}>Log In</Link>
                            </Button>
                        )}
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-10 px-5 py-10 md:py-14">
                    <section className="mx-auto max-w-2xl text-center">
                        <BrandMark
                            variant="horizontal"
                            className="mx-auto mb-6 h-12 sm:h-14"
                        />
                        <p className="text-muted-foreground text-xs font-medium tracking-[0.18em] uppercase">
                            Healthcare workforce operations
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                            {PRODUCT_NAME}
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-4 max-w-xl text-sm leading-relaxed sm:text-base">
                            A calm, commercial workspace for workforce
                            operations, EVV workflow, compliance, and agency
                            oversight.
                        </p>
                        <div className="mt-7 flex flex-col items-center gap-3">
                            <Button asChild size="lg" className="min-w-40">
                                <Link href={login()}>Log In</Link>
                            </Button>
                            <p className="text-muted-foreground text-sm">
                                Accounts are managed by your organization
                                administrator.
                            </p>
                        </div>
                    </section>

                    <section className="grid gap-3 sm:grid-cols-2">
                        {capabilities.map((item) => (
                            <article
                                key={item.title}
                                className="surface-panel p-4 text-left"
                            >
                                <item.icon
                                    className="text-primary mb-3 size-5"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-semibold tracking-tight">
                                    {item.title}
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                    {item.detail}
                                </p>
                            </article>
                        ))}
                    </section>

                    <section className="flex flex-wrap items-center justify-center gap-2">
                        {roles.map((role) => (
                            <span
                                key={role}
                                className="rounded-full border border-border/80 bg-card/70 px-3 py-1 text-xs font-medium"
                            >
                                {role}
                            </span>
                        ))}
                    </section>
                </main>

                <footer className="border-t border-border/70">
                    <div className="text-muted-foreground mx-auto flex w-full max-w-5xl flex-col items-center gap-1 px-5 py-6 text-center text-xs">
                        <span className="text-foreground font-medium">
                            {PRODUCT_NAME}
                        </span>
                        <span>{PRODUCT_TAGLINE}</span>
                    </div>
                </footer>
            </div>
        </>
    );
}

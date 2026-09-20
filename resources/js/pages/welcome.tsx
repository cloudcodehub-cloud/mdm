import { Head, Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-svh flex-col">
                <main className="flex flex-1 items-center justify-center px-5 py-10">
                    <section className="surface-panel w-full max-w-[34rem] px-6 py-10 text-center sm:px-10 sm:py-12">
                        <BrandMark
                            variant="icon"
                            alt=""
                            className="mx-auto mb-6 size-24 sm:size-28"
                        />
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-[2rem]">
                            Magic Data Management
                        </h1>
                        <p className="text-primary mt-2 text-sm font-medium tracking-wide sm:text-base">
                            Workforce & Care Operations
                        </p>
                        <p className="text-muted-foreground mx-auto mt-4 max-w-md text-sm leading-relaxed sm:text-base">
                            Secure workforce, scheduling, care and compliance
                            management for your organization.
                        </p>
                        <div className="mt-8 flex flex-col items-center gap-3">
                            <Button
                                asChild
                                size="lg"
                                className="w-full max-w-xs"
                            >
                                <Link href={login()}>Sign In to MDM</Link>
                            </Button>
                            <p className="text-muted-foreground max-w-sm text-xs leading-relaxed sm:text-sm">
                                Authorized users only. Accounts are managed by
                                your organization.
                            </p>
                        </div>
                    </section>
                </main>
                <footer className="px-5 py-6 text-center">
                    <p className="text-muted-foreground text-xs">
                        Secure Workforce & Care Operations
                    </p>
                </footer>
            </div>
        </>
    );
}

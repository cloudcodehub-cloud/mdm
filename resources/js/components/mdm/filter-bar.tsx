import type { FormEvent, ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function FilterBar({
    action,
    children,
    resetHref,
    activeCount = 0,
    className,
}: {
    action: string;
    children: ReactNode;
    resetHref: string;
    activeCount?: number;
    className?: string;
}) {
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const query: Record<string, string> = {};

        data.forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        router.get(action, query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <section className="surface-panel p-4 md:p-5">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-tight">
                    Search and filters
                </h2>
                {activeCount > 0 ? (
                    <p className="text-muted-foreground text-xs">
                        {activeCount} filter{activeCount === 1 ? '' : 's'} on
                    </p>
                ) : null}
            </div>
            <form
                action={action}
                method="get"
                onSubmit={submit}
                className={cn(
                    'grid gap-3 sm:grid-cols-2 xl:grid-cols-6',
                    className,
                )}
            >
                {children}
                <div className="flex flex-wrap items-center gap-2 sm:col-span-2 xl:col-span-2">
                    <Button type="submit" variant="secondary">
                        Apply
                    </Button>
                    <Button type="button" variant="ghost" asChild>
                        <Link href={resetHref}>
                            Reset
                        </Link>
                    </Button>
                </div>
            </form>
        </section>
    );
}

export function countActiveFilters(
    filters: Record<string, string | undefined>,
    ignore: string[] = [],
): number {
    return Object.entries(filters).filter(
        ([key, value]) =>
            !ignore.includes(key) &&
            value !== undefined &&
            value !== '',
    ).length;
}

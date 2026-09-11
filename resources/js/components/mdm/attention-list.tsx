import { Link } from '@inertiajs/react';
import { EmptyState } from '@/components/mdm/stat-card';
import { cn } from '@/lib/utils';
import type { DashboardAttentionItem } from '@/types/dashboard';

export function AttentionList({
    items,
    empty = 'No operational attention items right now.',
}: {
    items: DashboardAttentionItem[];
    empty?: string;
}) {
    if (items.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="space-y-3">
            {items.map((item) => {
                const content = (
                    <>
                        <span
                            className={cn(
                                'mt-1 size-2 shrink-0 rounded-full',
                                item.tone === 'danger' && 'bg-red-500',
                                item.tone === 'warning' && 'bg-amber-500',
                                item.tone === 'neutral' && 'bg-slate-400',
                            )}
                        />
                        <div className="min-w-0">
                            <p className="text-sm font-medium">{item.title}</p>
                            <p className="text-muted-foreground truncate text-xs">
                                {item.detail}
                            </p>
                        </div>
                    </>
                );

                return (
                    <li key={item.id}>
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="hover:bg-muted/40 -mx-1 flex gap-3 rounded-md px-1 py-0.5"
                            >
                                {content}
                            </Link>
                        ) : (
                            <div className="flex gap-3">{content}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

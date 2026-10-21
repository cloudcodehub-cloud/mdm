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
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium break-words">
                                {item.title}
                            </p>
                            <p className="text-muted-foreground line-clamp-2 text-xs break-words">
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
                                className="interactive-row -mx-1 flex min-h-11 gap-3 rounded-md px-1 py-2"
                            >
                                {content}
                            </Link>
                        ) : (
                            <div className="flex min-h-11 gap-3 py-2">
                                {content}
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

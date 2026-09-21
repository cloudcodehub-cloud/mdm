import type { ReactNode } from 'react';
import { RelativeTime } from '@/components/mdm/relative-time';
import { cn } from '@/lib/utils';

export type TimelineItem = {
    id: string;
    title: string;
    detail?: ReactNode;
    at?: string | null;
    at_label?: string | null;
};

export function ActivityTimeline({
    items,
    empty = 'No recorded activity yet.',
}: {
    items: TimelineItem[];
    empty?: string;
}) {
    if (items.length === 0) {
        return <p className="text-muted-foreground text-sm">{empty}</p>;
    }

    return (
        <ol className="space-y-0">
            {items.map((item, index) => (
                <li key={item.id} className="flex gap-3">
                    <div className="flex w-4 flex-col items-center">
                        <span className="bg-primary mt-1 size-2.5 rounded-full" />
                        {index < items.length - 1 ? (
                            <span className="bg-border mt-1 w-px flex-1" />
                        ) : null}
                    </div>
                    <div className={cn('min-w-0 pb-4', index === items.length - 1 && 'pb-0')}>
                        <p className="text-sm font-medium">{item.title}</p>
                        {item.detail ? (
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                {item.detail}
                            </p>
                        ) : null}
                        {(item.at || item.at_label) && (
                            <div className="text-muted-foreground mt-1 text-xs">
                                <RelativeTime iso={item.at} exact={item.at_label} />
                            </div>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

import { Link } from '@inertiajs/react';
import { EmptyState } from '@/components/mdm/stat-card';
import { index as announcementsIndex, read } from '@/routes/announcements';
import type { AnnouncementRecord } from '@/types/messaging';
import { cn } from '@/lib/utils';

export function AnnouncementList({
    announcements,
    empty = 'No announcements right now.',
}: {
    announcements: AnnouncementRecord[];
    empty?: string;
}) {
    if (announcements.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="space-y-3">
            {announcements.map((item) => (
                <li key={item.id}>
                    <Link
                        href={read.url(item.id)}
                        method="post"
                        className={cn(
                            'hover:bg-muted/40 -mx-1 block rounded-md px-1 py-1',
                            !item.is_read && 'bg-muted/30',
                        )}
                    >
                        <p className="text-sm font-medium">{item.title}</p>
                        <p className="text-muted-foreground line-clamp-2 text-xs">
                            {item.body}
                        </p>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            {item.author_name} · {item.audience_label} ·{' '}
                            {item.published_at}
                        </p>
                    </Link>
                </li>
            ))}
            <li>
                <Link
                    href={announcementsIndex()}
                    className="text-sm font-medium hover:underline"
                >
                    All announcements
                </Link>
            </li>
        </ul>
    );
}

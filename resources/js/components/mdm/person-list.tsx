import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { RecordPreviewRow } from '@/components/mdm/record-preview-row';
import { EmptyState } from '@/components/mdm/stat-card';

export function PersonList({
    people,
    empty,
}: {
    people: Array<{
        id: number;
        name: string;
        detail?: string;
        href?: string;
        photo_url?: string | null;
        initials?: string;
    }>;
    empty: string;
}) {
    if (people.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="space-y-2">
            {people.map((person) => (
                <li
                    key={person.id}
                    className="rounded-lg"
                >
                    {person.href ? (
                        <RecordPreviewRow href={person.href} className="flex items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-2">
                                <ProfilePhoto
                                    name={person.name}
                                    photoUrl={person.photo_url}
                                    initials={person.initials}
                                    size="sm"
                                />
                                <span className="truncate text-sm font-medium">
                                    {person.name}
                                </span>
                            </div>
                            {person.detail && (
                                <span className="text-muted-foreground shrink-0 text-xs">
                                    {person.detail}
                                </span>
                            )}
                        </RecordPreviewRow>
                    ) : (
                        <div className="flex items-center justify-between gap-3 px-1 py-1.5">
                            <div className="flex min-w-0 items-center gap-2">
                                <ProfilePhoto
                                    name={person.name}
                                    photoUrl={person.photo_url}
                                    initials={person.initials}
                                    size="sm"
                                />
                                <span className="truncate text-sm font-medium">
                                    {person.name}
                                </span>
                            </div>
                            {person.detail && (
                                <span className="text-muted-foreground shrink-0 text-xs">
                                    {person.detail}
                                </span>
                            )}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

export function ActivityList({
    items,
    empty = 'No recent scheduled activity.',
}: {
    items: Array<{
        id: string;
        title: string;
        detail: string;
        occurred_on: string;
    }>;
    empty?: string;
}) {
    if (items.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="space-y-3">
            {items.map((item) => (
                <li key={item.id}>
                    <p className="text-sm font-medium">{item.title}</p>
                    <p className="text-muted-foreground text-xs">
                        {item.detail} · {item.occurred_on}
                    </p>
                </li>
            ))}
        </ul>
    );
}

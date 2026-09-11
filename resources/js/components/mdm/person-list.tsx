import { Link } from '@inertiajs/react';
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
                    className="flex items-center justify-between gap-3 rounded-lg px-1 py-1.5"
                >
                    {person.href ? (
                        <Link
                            href={person.href}
                            className="hover:text-primary truncate text-sm font-medium underline-offset-4 hover:underline"
                        >
                            {person.name}
                        </Link>
                    ) : (
                        <span className="truncate text-sm font-medium">
                            {person.name}
                        </span>
                    )}
                    {person.detail && (
                        <span className="text-muted-foreground shrink-0 text-xs">
                            {person.detail}
                        </span>
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

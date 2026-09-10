import { EmptyState } from '@/components/mdm/stat-card';
import { Badge } from '@/components/ui/badge';
import type { DashboardVisit } from '@/types/dashboard';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

export function VisitList({
    visits,
    showEmployee = false,
    empty = 'No scheduled visits in this window.',
}: {
    visits: DashboardVisit[];
    showEmployee?: boolean;
    empty?: string;
}) {
    if (visits.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="divide-y divide-border/70">
            {visits.map((visit) => (
                <li
                    key={visit.id}
                    className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {visit.client.name}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {visit.service_type}
                            {showEmployee ? ` · ${visit.employee.name}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="tabular-nums">
                            {formatDate(visit.service_date)}
                        </span>
                        <span className="text-muted-foreground">
                            {visit.time_label}
                        </span>
                        {visit.shift_name && (
                            <Badge variant="secondary">{visit.shift_name}</Badge>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

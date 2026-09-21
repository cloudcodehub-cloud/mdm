import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { RecordPreviewRow } from '@/components/mdm/record-preview-row';
import { RelativeTime } from '@/components/mdm/relative-time';
import { EmptyState } from '@/components/mdm/stat-card';
import { index as attendanceIndex } from '@/routes/attendance';
import type { DashboardCompletedVisit } from '@/types/dashboard';
import { cn } from '@/lib/utils';

export function RecentCompletedVisits({
    visits,
}: {
    visits: DashboardCompletedVisit[];
}) {
    if (visits.length === 0) {
        return (
            <EmptyState
                compact
                message="No recently completed visits."
                action={{ href: attendanceIndex(), label: 'Open attendance' }}
            />
        );
    }

    return (
        <ul className="divide-border/70 divide-y">
            {visits.map((visit) => (
                <li key={visit.id}>
                    <RecordPreviewRow
                        href={visit.href}
                        className="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {visit.client.name}
                            </p>
                            <p className="text-muted-foreground truncate text-xs">
                                {visit.employee.name} · {visit.service_type}
                            </p>
                        </div>
                        <div className="flex min-w-0 flex-wrap items-center gap-2 text-xs">
                            <span className="text-muted-foreground">
                                Completed{' '}
                                <RelativeTime
                                    iso={visit.completed_at}
                                    exact={visit.completed_at_label}
                                    compact
                                    className="inline"
                                />
                            </span>
                            <span className="tabular-nums">
                                {visit.task_summary.completed}/
                                {visit.task_summary.total} tasks
                            </span>
                            {visit.has_high_priority_open ? (
                                <span className="inline-flex items-center gap-1 text-destructive">
                                    <HighPriorityIndicator
                                        active
                                        label="High-priority review needed"
                                    />
                                    <span>{visit.attention_label ?? 'Needs review'}</span>
                                </span>
                            ) : visit.needs_review ? (
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-300',
                                    )}
                                >
                                    <span
                                        className="bg-status-warning size-2 rounded-full"
                                        aria-hidden="true"
                                    />
                                    {visit.attention_label ?? 'Needs review'}
                                </span>
                            ) : null}
                        </div>
                    </RecordPreviewRow>
                </li>
            ))}
        </ul>
    );
}

import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/mdm/directory';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { cn } from '@/lib/utils';

export type CompactTask = {
    id?: number;
    key?: string;
    title: string;
    status?: string;
    status_label?: string;
    is_required?: boolean;
    is_critical?: boolean;
    skip_reason_name?: string | null;
    skip_comment?: string | null;
    completion_note?: string | null;
    source?: string;
    source_label?: string | null;
};

export function CompactTaskList({
    tasks,
    empty,
    renderFollowUp,
    exceptionByTaskId,
}: {
    tasks: CompactTask[];
    empty: string;
    renderFollowUp?: (task: CompactTask) => ReactNode;
    exceptionByTaskId?: Map<
        number,
        { is_high_priority_open?: boolean; status?: string }
    >;
}) {
    if (tasks.length === 0) {
        return <p className="text-muted-foreground text-sm">{empty}</p>;
    }

    return (
        <ul className="divide-border/70 divide-y">
            {tasks.map((task) => {
                const related = task.id
                    ? exceptionByTaskId?.get(task.id)
                    : undefined;
                const planned = !task.status;
                const badges = plannedBadges(task);
                const statusLabel = [
                    task.status_label,
                    task.is_critical && task.status === 'pending'
                        ? 'Critical'
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ');

                return (
                    <li key={task.id ?? task.key ?? task.title} className="py-2.5 first:pt-0 last:pb-0">
                        <div className="flex items-start justify-between gap-3">
                            <p className="min-w-0 text-sm font-medium">
                                {task.title}
                            </p>
                            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                <HighPriorityIndicator
                                    active={Boolean(
                                        related?.is_high_priority_open,
                                    )}
                                    label="High-priority task exception"
                                />
                                {planned ? (
                                    badges.map((badge) => (
                                        <span
                                            key={badge}
                                            className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase"
                                        >
                                            {badge}
                                        </span>
                                    ))
                                ) : (
                                    <StatusBadge
                                        status={task.status ?? 'pending'}
                                        label={statusLabel}
                                    />
                                )}
                            </div>
                        </div>
                        {task.status === 'skipped' &&
                        (task.skip_reason_name || task.skip_comment) ? (
                            <p className="text-muted-foreground mt-1 text-xs">
                                Reason:{' '}
                                {[task.skip_reason_name, task.skip_comment]
                                    .filter(Boolean)
                                    .join(' — ')}
                            </p>
                        ) : null}
                        {task.completion_note && task.status !== 'skipped' ? (
                            <p className="text-muted-foreground mt-1 text-xs">
                                {task.completion_note}
                            </p>
                        ) : null}
                        {task.is_required && task.status === 'pending' && !planned ? (
                            <p className="text-muted-foreground mt-1 text-xs">
                                Required
                            </p>
                        ) : null}
                        {renderFollowUp ? (
                            <div
                                className={cn(
                                    'text-muted-foreground mt-1 text-xs',
                                )}
                            >
                                {renderFollowUp(task)}
                            </div>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}

function plannedBadges(task: CompactTask): string[] {
    const badges: string[] = [];

    if (task.source === 'one_off' || task.source_label === 'One-off') {
        badges.push('One-off');
    }

    if (task.is_critical) {
        badges.push('Critical');
    } else if (task.is_required) {
        badges.push('Required');
    }

    return badges;
}

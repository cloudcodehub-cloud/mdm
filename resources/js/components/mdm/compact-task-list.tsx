import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/mdm/directory';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { cn } from '@/lib/utils';

export type CompactTask = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    is_required?: boolean;
    is_critical?: boolean;
    skip_reason_name?: string | null;
    skip_comment?: string | null;
    completion_note?: string | null;
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
                const related = exceptionByTaskId?.get(task.id);
                const secondary = [
                    task.is_critical ? 'Critical' : null,
                    task.is_required ? 'Required' : null,
                    task.skip_reason_name,
                    task.skip_comment,
                    task.completion_note
                        ? `DSP note: ${task.completion_note}`
                        : null,
                ].filter(Boolean);

                return (
                    <li key={task.id} className="py-2.5 first:pt-0 last:pb-0">
                        <div className="flex items-start justify-between gap-3">
                            <p className="min-w-0 text-sm font-medium">
                                {task.title}
                            </p>
                            <div className="flex shrink-0 items-center gap-2">
                                <HighPriorityIndicator
                                    active={Boolean(
                                        related?.is_high_priority_open,
                                    )}
                                    label="High-priority task exception"
                                />
                                <StatusBadge
                                    status={task.status}
                                    label={task.status_label}
                                />
                            </div>
                        </div>
                        {secondary.length > 0 ? (
                            <p
                                className={cn(
                                    'text-muted-foreground mt-1 text-xs whitespace-pre-wrap',
                                )}
                            >
                                {secondary.join(' · ')}
                            </p>
                        ) : null}
                        {renderFollowUp?.(task)}
                    </li>
                );
            })}
        </ul>
    );
}

import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ActiveVisitRecord } from '@/types/visit';

export function VisitWorkflow({ visit }: { visit: ActiveVisitRecord }) {
    const tasksDone =
        visit.task_summary.completed + visit.task_summary.skipped;
    const tasksTotal = visit.task_summary.total;
    const notesReady = Boolean(visit.visit_notes || visit.handover_note);
    const reviewReady =
        visit.task_summary.pending_required === 0 ||
        visit.unfinished_required_acknowledged;
    const clockedOut = visit.status === 'completed';

    const steps = [
        { id: 'clocked', label: 'Clocked In', complete: true },
        {
            id: 'tasks',
            label:
                tasksTotal === 0
                    ? 'Tasks'
                    : `Tasks ${tasksDone}/${tasksTotal}`,
            complete: tasksTotal === 0 || visit.task_summary.pending === 0,
        },
        { id: 'notes', label: 'Notes', complete: notesReady },
        { id: 'review', label: 'Review', complete: reviewReady && notesReady },
        { id: 'out', label: 'Clock Out', complete: clockedOut },
    ];

    const currentIndex = steps.findIndex((step) => !step.complete);

    return (
        <ol className="flex gap-2 overflow-x-auto pb-1 md:flex-wrap">
            {steps.map((step, index) => {
                const current = currentIndex === index;
                return (
                    <li
                        key={step.id}
                        className={cn(
                            'flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium',
                            step.complete
                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                : current
                                  ? 'border-primary/40 bg-primary/10 text-primary'
                                  : 'border-border/80 text-muted-foreground',
                        )}
                    >
                        {step.complete ? (
                            <Check className="size-3.5" aria-hidden="true" />
                        ) : (
                            <span aria-hidden="true">{index + 1}</span>
                        )}
                        <span>{step.label}</span>
                        {index < steps.length - 1 ? (
                            <span className="text-muted-foreground ml-1 hidden sm:inline">
                                →
                            </span>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

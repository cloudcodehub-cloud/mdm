import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/mdm/directory';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { complete, skip } from '@/routes/visits/tasks';
import type { ActiveVisitTask, SkipReasonOption } from '@/types/visit';

export function VisitTaskCard({
    visitId,
    task,
    skipReasons,
    canRecord,
}: {
    visitId: number;
    task: ActiveVisitTask;
    skipReasons: SkipReasonOption[];
    canRecord: boolean;
}) {
    const [completeOpen, setCompleteOpen] = useState(false);
    const [skipOpen, setSkipOpen] = useState(false);
    const [note, setNote] = useState('');
    const [reasonId, setReasonId] = useState('');
    const [comment, setComment] = useState('');
    const [processing, setProcessing] = useState(false);
    const pageErrors = usePage().props.errors;
    const selected = skipReasons.find(
        (reason) => String(reason.id) === reasonId,
    );

    const pending = task.status === 'pending';

    const submitComplete = () => {
        setProcessing(true);
        router.post(
            complete.url([visitId, task.id]),
            { completion_note: note || null },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setCompleteOpen(false),
            },
        );
    };

    const submitSkip = () => {
        setProcessing(true);
        router.post(
            skip.url([visitId, task.id]),
            {
                skip_reason_id: reasonId,
                skip_comment: comment || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setSkipOpen(false),
            },
        );
    };

    return (
        <li className="rounded-xl border border-border/70 p-3">
            <div className="flex items-start justify-between gap-3">
                <p className="font-medium">{task.title}</p>
                <StatusBadge status={task.status} label={task.status_label} />
            </div>
            <p className="text-muted-foreground mt-1 text-xs">
                {task.recurrence_label}
                {task.is_required ? ' · Required' : ''}
            </p>
            {task.instructions && (
                <p className="mt-2 text-sm">{task.instructions}</p>
            )}
            {task.completion_note && (
                <p className="mt-2 text-sm">Note: {task.completion_note}</p>
            )}
            {task.status === 'completed' && task.completed_at_label && (
                <p className="text-muted-foreground mt-2 text-xs">
                    Completed at {task.completed_at_label}
                </p>
            )}
            {task.status === 'skipped' && (
                <p className="text-muted-foreground mt-2 text-xs">
                    Skipped
                    {task.skip_reason_name
                        ? ` · ${task.skip_reason_name}`
                        : ''}
                    {task.skip_comment ? ` · ${task.skip_comment}` : ''}
                </p>
            )}
            {canRecord && pending && (
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setCompleteOpen(true)}
                    >
                        Complete
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => setSkipOpen(true)}
                    >
                        Skip
                    </Button>
                </div>
            )}

            <Dialog open={completeOpen} onOpenChange={setCompleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete task</DialogTitle>
                        <DialogDescription>
                            Record completion for {task.title}. A note is
                            optional.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`completion-note-${task.id}`}>
                            Completion note
                        </Label>
                        <textarea
                            id={`completion-note-${task.id}`}
                            rows={3}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={submitComplete}
                            disabled={processing}
                        >
                            Mark complete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={skipOpen} onOpenChange={setSkipOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Skip task</DialogTitle>
                        <DialogDescription>
                            Choose a skip reason. Client refused requires an
                            explanation.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`skip-reason-${task.id}`}>
                                Skip reason
                            </Label>
                            <select
                                id={`skip-reason-${task.id}`}
                                required
                                value={reasonId}
                                onChange={(event) =>
                                    setReasonId(event.target.value)
                                }
                                className="border-input h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="">Select a reason</option>
                                {skipReasons.map((reason) => (
                                    <option key={reason.id} value={reason.id}>
                                        {reason.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={pageErrors.skip_reason_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`skip-comment-${task.id}`}>
                                {selected?.requires_explanation
                                    ? 'Explanation'
                                    : 'Comment'}
                            </Label>
                            <textarea
                                id={`skip-comment-${task.id}`}
                                required={Boolean(
                                    selected?.requires_explanation,
                                )}
                                rows={3}
                                value={comment}
                                onChange={(event) =>
                                    setComment(event.target.value)
                                }
                                className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError message={pageErrors.skip_comment} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={submitSkip}
                            disabled={processing || reasonId === ''}
                        >
                            Skip task
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </li>
    );
}

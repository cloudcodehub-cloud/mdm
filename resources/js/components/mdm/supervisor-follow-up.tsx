import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { VisitExceptionRecord } from '@/types/operations';

export function SupervisorFollowUp({
    exception,
    canFollowUp,
}: {
    exception: Pick<
        VisitExceptionRecord,
        | 'id'
        | 'review_notes'
        | 'resolution_notes'
        | 'reviewed_by_name'
        | 'resolved_by_name'
        | 'reviewed_at_label'
        | 'resolved_at_label'
        | 'status_history'
        | 'status'
    >;
    canFollowUp: boolean;
}) {
    const form = useForm({ notes: '' });
    const history = (exception.status_history ?? []).filter((entry) => entry.notes);

    return (
        <div className="mt-2 space-y-2">
            {exception.review_notes && (
                <p className="text-sm whitespace-pre-wrap">
                    <span className="text-muted-foreground text-xs">
                        Review
                        {exception.reviewed_by_name
                            ? ` · ${exception.reviewed_by_name}`
                            : ''}
                        {exception.reviewed_at_label
                            ? ` · ${exception.reviewed_at_label}`
                            : ''}
                    </span>
                    <span className="mt-0.5 block">{exception.review_notes}</span>
                </p>
            )}
            {exception.resolution_notes && (
                <p className="text-sm whitespace-pre-wrap">
                    <span className="text-muted-foreground text-xs">
                        Resolution
                        {exception.resolved_by_name
                            ? ` · ${exception.resolved_by_name}`
                            : ''}
                        {exception.resolved_at_label
                            ? ` · ${exception.resolved_at_label}`
                            : ''}
                    </span>
                    <span className="mt-0.5 block">
                        {exception.resolution_notes}
                    </span>
                </p>
            )}
            {history.length > 0 && (
                <ul className="space-y-1.5 text-sm">
                    {history.map((entry, index) => (
                        <li key={`${entry.at}-${index}`}>
                            <p className="text-muted-foreground text-xs">
                                {entry.user_name}
                                {entry.at ? ` · ${entry.at}` : ''}
                            </p>
                            <p className="whitespace-pre-wrap">{entry.notes}</p>
                        </li>
                    ))}
                </ul>
            )}
            {canFollowUp && exception.status !== 'resolved' && (
                <form
                    className="space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/visit-exceptions/${exception.id}/follow-up`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Label htmlFor={`follow-up-${exception.id}`}>
                        Supervisor follow-up
                    </Label>
                    <textarea
                        id={`follow-up-${exception.id}`}
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Document follow-up. DSP notes and skip reasons stay unchanged."
                        className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    />
                    <InputError message={form.errors.notes} />
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing || form.data.notes.trim() === ''}
                    >
                        Save follow-up
                    </Button>
                </form>
            )}
        </div>
    );
}

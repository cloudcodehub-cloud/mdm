import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { notes } from '@/routes/visits';

export type VisitNoteHistoryRecord = {
    id: number;
    field_key: string;
    field_label: string;
    previous_content: string | null;
    new_content: string | null;
    reason: string | null;
    actor_name: string | null;
    occurred_at: string | null;
    occurred_at_label: string | null;
};

export function VisitNotesForm({
    visitId,
    visitNotes,
    handoverNote,
    canUpdate,
    noteHistory = [],
    canViewHistory = false,
}: {
    visitId: number;
    visitNotes: string | null;
    handoverNote: string | null;
    canUpdate: boolean;
    noteHistory?: VisitNoteHistoryRecord[];
    canViewHistory?: boolean;
}) {
    const form = useForm({
        visit_notes: visitNotes ?? '',
        handover_note: handoverNote ?? '',
        notes_correction_reason: '',
    });
    const amendingExistingVisitNote =
        Boolean(visitNotes) &&
        form.data.visit_notes.trim() !== (visitNotes ?? '').trim();

    if (!canUpdate && !visitNotes && !handoverNote && noteHistory.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                No visit notes or handover have been recorded.
            </p>
        );
    }

    if (!canUpdate) {
        return (
            <div className="space-y-4">
                <dl className="grid gap-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Visit notes
                        </dt>
                        <dd className="mt-0.5 whitespace-pre-wrap">
                            {visitNotes || 'None'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">Handover</dt>
                        <dd className="mt-0.5 whitespace-pre-wrap">
                            {handoverNote || 'None'}
                        </dd>
                    </div>
                </dl>
                {canViewHistory ? (
                    <VisitNoteHistoryList events={noteHistory} />
                ) : null}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(notes.url(visitId), {
                        preserveScroll: true,
                        onSuccess: () =>
                            form.setData('notes_correction_reason', ''),
                    });
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="visit_notes">Visit notes</Label>
                    <textarea
                        id="visit_notes"
                        rows={4}
                        value={form.data.visit_notes}
                        onChange={(event) =>
                            form.setData('visit_notes', event.target.value)
                        }
                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    />
                    <InputError message={form.errors.visit_notes} />
                </div>
                {amendingExistingVisitNote || visitNotes ? (
                    <div className="grid gap-2">
                        <Label htmlFor="notes_correction_reason">
                            Visit note correction reason
                        </Label>
                        <textarea
                            id="notes_correction_reason"
                            rows={2}
                            value={form.data.notes_correction_reason}
                            onChange={(event) =>
                                form.setData(
                                    'notes_correction_reason',
                                    event.target.value,
                                )
                            }
                            placeholder="Required when correcting or removing an existing Visit Note"
                            className="border-input flex min-h-14 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                        <p className="text-muted-foreground text-xs">
                            Original and amended Visit Note content stay in
                            history with actor and timestamp.
                        </p>
                        <InputError
                            message={form.errors.notes_correction_reason}
                        />
                    </div>
                ) : null}
                <div className="grid gap-2">
                    <Label htmlFor="handover_note">
                        Wrap-up / handover note (required to clock out)
                    </Label>
                    <textarea
                        id="handover_note"
                        rows={4}
                        value={form.data.handover_note}
                        onChange={(event) =>
                            form.setData('handover_note', event.target.value)
                        }
                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    />
                    <InputError message={form.errors.handover_note} />
                </div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save notes'}
                </Button>
            </form>
            {canViewHistory ? (
                <VisitNoteHistoryList events={noteHistory} />
            ) : null}
        </div>
    );
}

function VisitNoteHistoryList({
    events,
}: {
    events: VisitNoteHistoryRecord[];
}) {
    if (events.length === 0) {
        return null;
    }

    return (
        <div className="border-t pt-3">
            <p className="text-sm font-medium">Note history</p>
            <p className="text-muted-foreground mt-0.5 text-xs">
                In-visit Visit Note and wrap-up changes with original content
                retained.
            </p>
            <ul className="mt-3 space-y-3">
                {events.map((event) => (
                    <li
                        key={event.id}
                        className="rounded-md border px-3 py-2 text-xs"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <p className="font-medium">{event.field_label}</p>
                            <p className="text-muted-foreground">
                                {event.occurred_at_label ?? '—'}
                            </p>
                        </div>
                        <p className="text-muted-foreground mt-1">
                            {event.actor_name ?? 'Unknown actor'}
                            {event.reason ? ` · Reason: ${event.reason}` : ''}
                        </p>
                        {(event.previous_content || event.new_content) && (
                            <p className="mt-2 whitespace-pre-wrap">
                                <span className="text-muted-foreground">
                                    {event.previous_content ?? '(empty)'}
                                </span>
                                {' → '}
                                <span>{event.new_content ?? '(removed)'}</span>
                            </p>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { notes } from '@/routes/visits';

export function VisitNotesForm({
    visitId,
    visitNotes,
    handoverNote,
    canUpdate,
}: {
    visitId: number;
    visitNotes: string | null;
    handoverNote: string | null;
    canUpdate: boolean;
}) {
    const form = useForm({
        visit_notes: visitNotes ?? '',
        handover_note: handoverNote ?? '',
    });

    if (!canUpdate && !visitNotes && !handoverNote) {
        return (
            <p className="text-muted-foreground text-sm">
                No visit notes or handover have been recorded.
            </p>
        );
    }

    if (!canUpdate) {
        return (
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
        );
    }

    return (
        <form
            className="space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(notes.url(visitId), { preserveScroll: true });
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
            <div className="grid gap-2">
                <Label htmlFor="handover_note">Handover note</Label>
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
                Save notes
            </Button>
        </form>
    );
}

import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { administrativeClose } from '@/routes/visits';
import type { ActiveVisitRecord } from '@/types/visit';

export function VisitAdministrativeCloseForm({
    visit,
}: {
    visit: ActiveVisitRecord;
}) {
    const form = useForm({
        effective_ended_at: visit.administrative_close_default_end ?? '',
        reason: '',
    });

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(administrativeClose.url(visit.id), {
                    preserveScroll: true,
                });
            }}
        >
            <p className="text-muted-foreground text-sm">
                Establishes an effective service end without creating a DSP
                clock-out or GPS evidence. Original EVV, tasks, notes, and
                exceptions stay intact.
            </p>
            <Field
                label="Effective service end"
                htmlFor="effective_ended_at"
                error={form.errors.effective_ended_at}
            >
                <Input
                    id="effective_ended_at"
                    type="datetime-local"
                    className={controlClassName}
                    value={form.data.effective_ended_at}
                    onChange={(event) =>
                        form.setData('effective_ended_at', event.target.value)
                    }
                    required
                />
            </Field>
            <Field
                label="Reason"
                htmlFor="administrative_close_reason"
                error={form.errors.reason}
            >
                <textarea
                    id="administrative_close_reason"
                    className={controlClassName}
                    rows={3}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    required
                />
                <InputError message={form.errors.reason} />
            </Field>
            <div>
                <Button type="submit" disabled={form.processing}>
                    Administratively close visit
                </Button>
            </div>
        </form>
    );
}

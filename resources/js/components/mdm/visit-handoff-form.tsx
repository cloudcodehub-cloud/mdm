import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DspScheduleOption } from '@/types/directory';
import type { ActiveVisitRecord } from '@/types/visit';

export function VisitHandoffForm({
    visit,
    dsps,
}: {
    visit: ActiveVisitRecord;
    dsps: DspScheduleOption[];
}) {
    const form = useForm({
        employee_id: '',
        effective_ended_at: visit.administrative_close_default_end ?? '',
        reason: '',
        override_reason: '',
        mark_call_off: false as boolean,
    });

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    employee_id: Number(data.employee_id),
                    mark_call_off: Boolean(data.mark_call_off),
                    override_reason: data.override_reason || null,
                }));
                form.post(`/visits/${visit.id}/handoff`, {
                    preserveScroll: true,
                });
            }}
        >
            <p className="text-muted-foreground text-sm">
                Ends the outgoing DSP segment without fabricating clock-out or
                GPS evidence, then assigns a replacement. The replacement clocks
                in separately to create a linked Visit. Uncovered time stays
                unserved.
            </p>
            <Field
                label="Replacement DSP"
                htmlFor="handoff_employee_id"
                error={form.errors.employee_id}
            >
                <select
                    id="handoff_employee_id"
                    className={controlClassName}
                    value={form.data.employee_id}
                    onChange={(event) =>
                        form.setData('employee_id', event.target.value)
                    }
                    required
                >
                    <option value="">Select replacement DSP</option>
                    {dsps
                        .filter((dsp) => dsp.id !== visit.employee.id)
                        .map((dsp) => (
                            <option key={dsp.id} value={dsp.id}>
                                {dsp.name}
                            </option>
                        ))}
                </select>
                <InputError message={form.errors.employee_id} />
            </Field>
            <Field
                label="Outgoing segment effective end"
                htmlFor="handoff_effective_ended_at"
                error={form.errors.effective_ended_at}
            >
                <Input
                    id="handoff_effective_ended_at"
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
                label="Handoff reason"
                htmlFor="handoff_reason"
                error={form.errors.reason}
            >
                <textarea
                    id="handoff_reason"
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
            {(form.errors.override_reason ||
                (form.errors as Record<string, string>)
                    .availability_override_required) && (
                <Field
                    label="Availability override reason"
                    htmlFor="handoff_override_reason"
                    error={
                        form.errors.override_reason ??
                        (form.errors as Record<string, string>)
                            .availability_override_required
                    }
                >
                    <textarea
                        id="handoff_override_reason"
                        className={controlClassName}
                        rows={3}
                        value={form.data.override_reason}
                        onChange={(event) =>
                            form.setData('override_reason', event.target.value)
                        }
                        required
                    />
                </Field>
            )}
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.mark_call_off}
                    onChange={(event) =>
                        form.setData('mark_call_off', event.target.checked)
                    }
                />
                Mark original DSP unavailable for this window
            </label>
            <div>
                <Button type="submit" disabled={form.processing}>
                    Record mid-service handoff
                </Button>
            </div>
        </form>
    );
}

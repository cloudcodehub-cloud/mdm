import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Input } from '@/components/ui/input';
import type {
    ClientScheduleOption,
    DspScheduleOption,
    OptionItem,
    ShiftTemplateOption,
    VisitRecord,
} from '@/types/directory';

const statuses = [
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'completed', label: 'Completed' },
];

export function ScheduledVisitForm({
    action,
    method,
    visit,
    clients,
    dsps,
    supervisors,
    shiftTemplates,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put' | 'patch';
    visit?: VisitRecord;
    clients: ClientScheduleOption[];
    dsps: DspScheduleOption[];
    supervisors: OptionItem[];
    shiftTemplates: ShiftTemplateOption[];
    submitLabel: string;
}) {
    const [clientId, setClientId] = useState(
        visit?.client_id ? String(visit.client_id) : '',
    );
    const [timingMode, setTimingMode] = useState<'template' | 'custom'>(
        visit?.shift_template_id ? 'template' : visit ? 'custom' : 'template',
    );

    const assignedDsps = useMemo(
        () =>
            dsps.filter((dsp) =>
                dsp.assigned_client_ids.includes(Number(clientId)),
            ),
        [clientId, dsps],
    );
    const otherDsps = useMemo(
        () =>
            dsps.filter(
                (dsp) => !dsp.assigned_client_ids.includes(Number(clientId)),
            ),
        [clientId, dsps],
    );

    const selectedClient = clients.find(
        (client) => String(client.id) === clientId,
    );

    return (
        <Form action={action} method={method} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Assignment
                        </h2>
                        <Field
                            label="Client"
                            htmlFor="client_id"
                            error={errors.client_id}
                        >
                            <select
                                id="client_id"
                                name="client_id"
                                required
                                value={clientId}
                                onChange={(event) =>
                                    setClientId(event.target.value)
                                }
                                className={controlClassName}
                            >
                                <option value="">Select client</option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name} ({client.client_number})
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="DSP"
                            htmlFor="employee_id"
                            error={errors.employee_id}
                        >
                            <select
                                id="employee_id"
                                name="employee_id"
                                required
                                defaultValue={visit?.employee_id ?? ''}
                                className={controlClassName}
                            >
                                <option value="">Select DSP</option>
                                {assignedDsps.length > 0 && (
                                    <optgroup label="Assigned to this client">
                                        {assignedDsps.map((dsp) => (
                                            <option key={dsp.id} value={dsp.id}>
                                                {dsp.name} ({dsp.employee_number}
                                                )
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                                {otherDsps.length > 0 && (
                                    <optgroup
                                        label={
                                            assignedDsps.length > 0
                                                ? 'Other DSPs'
                                                : 'Active DSPs'
                                        }
                                    >
                                        {otherDsps.map((dsp) => (
                                            <option key={dsp.id} value={dsp.id}>
                                                {dsp.name} ({dsp.employee_number}
                                                )
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                            </select>
                        </Field>
                        <Field
                            label="Supervisor"
                            htmlFor="supervisor_id"
                            error={errors.supervisor_id}
                        >
                            <select
                                id="supervisor_id"
                                name="supervisor_id"
                                key={clientId}
                                defaultValue={
                                    visit?.supervisor_id ??
                                    selectedClient?.supervisor_id ??
                                    ''
                                }
                                className={controlClassName}
                            >
                                <option value="">None</option>
                                {supervisors.map((supervisor) => (
                                    <option
                                        key={supervisor.id}
                                        value={supervisor.id}
                                    >
                                        {supervisor.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Service"
                            htmlFor="service_type"
                            error={errors.service_type}
                        >
                            <Input
                                id="service_type"
                                name="service_type"
                                required
                                placeholder="Residential Habilitation"
                                defaultValue={visit?.service_type}
                            />
                        </Field>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Timing and status
                        </h2>
                        <Field
                            label="Service date"
                            htmlFor="service_date"
                            error={errors.service_date}
                        >
                            <Input
                                id="service_date"
                                name="service_date"
                                type="date"
                                required
                                defaultValue={visit?.service_date ?? ''}
                            />
                        </Field>
                        <Field
                            label="Status"
                            htmlFor="status"
                            error={errors.status}
                        >
                            <select
                                id="status"
                                name="status"
                                defaultValue={visit?.status ?? 'scheduled'}
                                className={controlClassName}
                            >
                                {statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Time source"
                            htmlFor="timing_mode"
                            error={errors.shift_template_id}
                        >
                            <select
                                id="timing_mode"
                                name="timing_mode"
                                value={timingMode}
                                onChange={(event) =>
                                    setTimingMode(
                                        event.target.value as
                                            | 'template'
                                            | 'custom',
                                    )
                                }
                                className={controlClassName}
                            >
                                <option value="template">Shift template</option>
                                <option value="custom">
                                    Custom start and end
                                </option>
                            </select>
                        </Field>
                        {timingMode === 'template' ? (
                            <Field
                                label="Shift template"
                                htmlFor="shift_template_id"
                                error={errors.shift_template_id}
                            >
                                <select
                                    id="shift_template_id"
                                    name="shift_template_id"
                                    required
                                    defaultValue={visit?.shift_template_id ?? ''}
                                    className={controlClassName}
                                >
                                    <option value="">Select template</option>
                                    {shiftTemplates.map((template) => (
                                        <option
                                            key={template.id}
                                            value={template.id}
                                        >
                                            {template.name} ({template.starts_at}{' '}
                                            – {template.ends_at}
                                            {template.spans_overnight
                                                ? ', overnight'
                                                : ''}
                                            )
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        ) : (
                            <>
                                <input
                                    type="hidden"
                                    name="shift_template_id"
                                    value=""
                                />
                                <Field
                                    label="Starts at"
                                    htmlFor="starts_at"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="time"
                                        required
                                        defaultValue={visit?.starts_at ?? ''}
                                    />
                                </Field>
                                <Field
                                    label="Ends at"
                                    htmlFor="ends_at"
                                    error={errors.ends_at}
                                >
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="time"
                                        required
                                        defaultValue={visit?.ends_at ?? ''}
                                    />
                                </Field>
                                <p className="text-muted-foreground text-xs md:col-span-2">
                                    If the end time is earlier than the start
                                    time, the visit is treated as overnight and
                                    ends the next calendar day.
                                </p>
                            </>
                        )}
                        <Field
                            label="Notes"
                            htmlFor="notes"
                            error={errors.notes}
                        >
                            <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                defaultValue={visit?.notes ?? ''}
                                className={`${controlClassName} h-auto py-2`}
                            />
                        </Field>
                    </section>

                    <div className="sticky-form-actions">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-md px-4 text-sm font-medium shadow-xs disabled:opacity-50"
                        >
                            {submitLabel}
                        </button>
                    </div>
                </>
            )}
        </Form>
    );
}

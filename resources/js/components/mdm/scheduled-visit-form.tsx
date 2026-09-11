import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AvailabilityStrip } from '@/components/mdm/availability-strip';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    ClientScheduleOption,
    DspScheduleOption,
    OptionItem,
    ShiftTemplateOption,
    VisitRecord,
} from '@/types/directory';
import type { AvailabilityBoard, AvailabilityDspRow } from '@/types/scheduling';

const statuses = [
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'completed', label: 'Completed' },
];

type PreviewTask = {
    key: string;
    title: string;
    recurrence_label: string;
    due: boolean;
    due_label: string;
    source: string;
};

type OneOffDraft = {
    id?: number;
    title: string;
    instructions: string;
    note_required: boolean;
};

export function ScheduledVisitForm({
    action,
    method,
    visit,
    clients,
    dsps,
    supervisors,
    shiftTemplates,
    carePreviewUrl,
    availabilityBoardUrl,
    isAdmin = false,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put' | 'patch';
    visit?: VisitRecord;
    clients: ClientScheduleOption[];
    dsps: DspScheduleOption[];
    supervisors: OptionItem[];
    shiftTemplates: ShiftTemplateOption[];
    catalogServices?: OptionItem[];
    carePreviewUrl?: string;
    availabilityBoardUrl?: string;
    isAdmin?: boolean;
    submitLabel: string;
}) {
    const [clientId, setClientId] = useState(
        visit?.client_id ? String(visit.client_id) : '',
    );
    const [serviceDate, setServiceDate] = useState(visit?.service_date ?? '');
    const [serviceType, setServiceType] = useState(visit?.service_type ?? '');
    const [timingMode, setTimingMode] = useState<'template' | 'custom'>(
        visit?.shift_template_id ? 'template' : 'custom',
    );
    const [startsAt, setStartsAt] = useState(visit?.starts_at ?? '15:00');
    const [endsAt, setEndsAt] = useState(visit?.ends_at ?? '23:00');
    const [templateId, setTemplateId] = useState(
        visit?.shift_template_id ? String(visit.shift_template_id) : '',
    );
    const [employeeId, setEmployeeId] = useState(
        visit?.employee_id ? String(visit.employee_id) : '',
    );
    const [supervisorOverride, setSupervisorOverride] = useState(
        visit?.supervisor_id ? String(visit.supervisor_id) : '',
    );
    const [repeat, setRepeat] = useState(false);
    const [preview, setPreview] = useState<PreviewTask[]>([]);
    const [board, setBoard] = useState<AvailabilityBoard | null>(null);
    const [oneOffs, setOneOffs] = useState<OneOffDraft[]>(
        (visit?.one_off_tasks ?? []).map((task) => ({
            id: task.id,
            title: task.title,
            instructions: task.instructions ?? '',
            note_required: Boolean(task.note_required),
        })),
    );

    const selectedClient = clients.find(
        (client) => String(client.id) === clientId,
    );
    const clientServices = selectedClient?.services ?? [];
    const supervisorName =
        selectedClient?.supervisor_name ??
        supervisors.find(
            (row) => String(row.id) === String(selectedClient?.supervisor_id),
        )?.name;
    const supervisorId = isAdmin
        ? supervisorOverride || String(selectedClient?.supervisor_id ?? '')
        : String(selectedClient?.supervisor_id ?? visit?.supervisor_id ?? '');

    const selectedDsp: AvailabilityDspRow | undefined = board?.dsps.find(
        (row) => String(row.id) === employeeId,
    );
    const otherDsps = (board?.dsps ?? []).filter(
        (row) => String(row.id) !== employeeId,
    );

    const requestTimes = useMemo(() => {
        if (timingMode === 'template') {
            const template = shiftTemplates.find(
                (row) => String(row.id) === templateId,
            );
            return {
                starts: template?.starts_at ?? '',
                ends: template?.ends_at ?? '',
            };
        }
        return { starts: startsAt, ends: endsAt };
    }, [timingMode, templateId, shiftTemplates, startsAt, endsAt]);

    useEffect(() => {
        if (!carePreviewUrl || clientId === '' || serviceDate === '') {
            setPreview([]);
            return;
        }

        const params = new URLSearchParams({
            client_id: clientId,
            service_date: serviceDate,
        });

        if (visit?.id) {
            params.set('scheduled_visit_id', String(visit.id));
        }

        const controller = new AbortController();

        fetch(`${carePreviewUrl}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload: { tasks?: PreviewTask[] } | null) => {
                setPreview(payload?.tasks ?? []);
            })
            .catch(() => {
                setPreview([]);
            });

        return () => controller.abort();
    }, [carePreviewUrl, clientId, serviceDate, visit?.id]);

    useEffect(() => {
        if (
            !availabilityBoardUrl ||
            clientId === '' ||
            serviceDate === '' ||
            requestTimes.starts === '' ||
            requestTimes.ends === ''
        ) {
            setBoard(null);
            return;
        }

        const params = new URLSearchParams({
            client_id: clientId,
            service_date: serviceDate,
            service_type: serviceType,
            starts_at: requestTimes.starts,
            ends_at: requestTimes.ends,
        });

        if (timingMode === 'template' && templateId !== '') {
            params.set('shift_template_id', templateId);
        }

        if (visit?.id) {
            params.set('scheduled_visit_id', String(visit.id));
        }

        const controller = new AbortController();

        fetch(`${availabilityBoardUrl}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload: AvailabilityBoard | null) => {
                setBoard(payload);
                if (
                    payload &&
                    employeeId === '' &&
                    payload.dsps.some((row) => !row.hard_blocked)
                ) {
                    const best = payload.dsps.find((row) => !row.hard_blocked);
                    if (best) {
                        setEmployeeId(String(best.id));
                    }
                }
            })
            .catch(() => {
                setBoard(null);
            });

        return () => controller.abort();
    }, [
        availabilityBoardUrl,
        clientId,
        serviceDate,
        serviceType,
        requestTimes.starts,
        requestTimes.ends,
        timingMode,
        templateId,
        visit?.id,
    ]);

    return (
        <Form action={action} method={method} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Client and service
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
                                onChange={(event) => {
                                    setClientId(event.target.value);
                                    setServiceType('');
                                    setEmployeeId('');
                                }}
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
                            label="Service"
                            htmlFor="service_type"
                            error={errors.service_type}
                        >
                            <select
                                id="service_type"
                                name="service_type"
                                required
                                value={serviceType}
                                onChange={(event) =>
                                    setServiceType(event.target.value)
                                }
                                className={controlClassName}
                                disabled={clientId === ''}
                            >
                                <option value="">Select service</option>
                                {clientServices.map((service) => (
                                    <option
                                        key={service.id}
                                        value={service.name}
                                    >
                                        {service.name}
                                    </option>
                                ))}
                                {visit?.service_type &&
                                    !clientServices.some(
                                        (service) =>
                                            service.name === visit.service_type,
                                    ) && (
                                        <option value={visit.service_type}>
                                            {visit.service_type}
                                        </option>
                                    )}
                            </select>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {clientId === ''
                                    ? 'Select a client to load assigned services.'
                                    : clientServices.length === 0
                                      ? 'This client has no active assigned services.'
                                      : 'Only services assigned to this client.'}
                            </p>
                        </Field>
                        <div className="md:col-span-2">
                            <p className="text-muted-foreground text-xs">
                                Supervisor
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {supervisorName ?? 'Not assigned on client profile'}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Assigned through client profile
                            </p>
                            <input
                                type="hidden"
                                name="supervisor_id"
                                value={supervisorId}
                            />
                            {isAdmin && (
                                <Field
                                    label="Admin override"
                                    htmlFor="supervisor_override"
                                    error={errors.supervisor_id}
                                >
                                    <select
                                        id="supervisor_override"
                                        value={supervisorId}
                                        onChange={(event) =>
                                            setSupervisorOverride(
                                                event.target.value,
                                            )
                                        }
                                        className={controlClassName}
                                    >
                                        <option value="">Use client supervisor</option>
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
                            )}
                        </div>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Requested window
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
                                value={serviceDate}
                                onChange={(event) =>
                                    setServiceDate(event.target.value)
                                }
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
                                <option value="custom">
                                    Requested start and end
                                </option>
                                <option value="template">Shift template</option>
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
                                    value={templateId}
                                    onChange={(event) =>
                                        setTemplateId(event.target.value)
                                    }
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
                                    label="Requested start"
                                    htmlFor="starts_at"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="time"
                                        required
                                        value={startsAt}
                                        onChange={(event) =>
                                            setStartsAt(event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Requested end"
                                    htmlFor="ends_at"
                                    error={errors.ends_at}
                                >
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="time"
                                        required
                                        value={endsAt}
                                        onChange={(event) =>
                                            setEndsAt(event.target.value)
                                        }
                                    />
                                </Field>
                            </>
                        )}
                    </section>

                    {board?.authorization && (
                        <p className="border-warning/40 bg-warning/10 rounded-md border px-3 py-2 text-sm">
                            {board.authorization.message}
                        </p>
                    )}

                    {board && (
                        <section className="surface-panel space-y-4 p-4 md:p-5">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    DSP availability
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Eligible DSPs for this client’s supervisor.
                                    Select a row to assign. Ranked by assignment,
                                    availability, continuity, and workload — not
                                    AI.
                                </p>
                            </div>
                            <input
                                type="hidden"
                                name="employee_id"
                                value={employeeId}
                            />
                            {errors.employee_id && (
                                <p className="text-destructive text-sm">
                                    {errors.employee_id}
                                </p>
                            )}
                            {selectedDsp && (
                                <button
                                    type="button"
                                    className="border-primary/40 bg-primary/5 w-full rounded-lg border p-3 text-left"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-semibold">
                                                {selectedDsp.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {selectedDsp.reason_label}
                                            </p>
                                        </div>
                                        <p className="text-xs">
                                            {selectedDsp.workload.day_hours}h today
                                            · {selectedDsp.workload.week_hours}h
                                            week · {selectedDsp.workload.visit_count}{' '}
                                            visits
                                        </p>
                                    </div>
                                    <div className="mt-3">
                                        <AvailabilityStrip
                                            segments={selectedDsp.timeline}
                                        />
                                    </div>
                                    {selectedDsp.warnings.map((warning) => (
                                        <p
                                            key={warning}
                                            className="text-warning mt-2 text-xs"
                                        >
                                            {warning}
                                        </p>
                                    ))}
                                    {selectedDsp.block_reason && (
                                        <p className="text-destructive mt-2 text-xs">
                                            {selectedDsp.block_reason}
                                        </p>
                                    )}
                                </button>
                            )}
                            <div className="space-y-2">
                                {otherDsps.map((row) => (
                                    <button
                                        key={row.id}
                                        type="button"
                                        onClick={() =>
                                            setEmployeeId(String(row.id))
                                        }
                                        className="hover:bg-muted/40 w-full rounded-md p-2 text-left"
                                    >
                                        <div className="mb-1 flex justify-between gap-2 text-xs">
                                            <span className="font-medium">
                                                {row.name}
                                                {row.assigned_to_client
                                                    ? ' · assigned'
                                                    : ''}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {row.workload.week_hours}h week
                                            </span>
                                        </div>
                                        <AvailabilityStrip
                                            compact
                                            segments={row.timeline}
                                        />
                                    </button>
                                ))}
                            </div>
                            {board.coverage && (
                                <div className="border-border rounded-md border p-3 text-sm">
                                    <p>{board.coverage.message}</p>
                                    <ul className="mt-2 space-y-1 text-xs">
                                        {board.coverage.options.map((option) => (
                                            <li key={option.label}>
                                                <button
                                                    type="button"
                                                    className="hover:text-foreground"
                                                    onClick={() =>
                                                        setEmployeeId(
                                                            String(
                                                                option.employee_id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {option.label}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {dsps.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No DSPs in directory.
                                </p>
                            )}
                        </section>
                    )}

                    {clientId !== '' && serviceDate !== '' && (
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">
                                Care-plan preview
                            </h2>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Tasks do not control DSP availability. Visit
                                tasks are created at clock-in.
                            </p>
                            {preview.length === 0 ? (
                                <p className="text-muted-foreground mt-3 text-sm">
                                    No care-plan tasks are configured for this
                                    date.
                                </p>
                            ) : (
                                <ul className="mt-3 space-y-1 text-sm">
                                    {preview.map((task) => (
                                        <li
                                            key={task.key}
                                            className="flex justify-between gap-3"
                                        >
                                            <span>
                                                {task.title} —{' '}
                                                {task.recurrence_label}
                                            </span>
                                            <span
                                                className={
                                                    task.due
                                                        ? 'text-primary text-xs font-medium'
                                                        : 'text-muted-foreground text-xs'
                                                }
                                            >
                                                {task.due_label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}

                    <section className="surface-panel p-4 md:p-5">
                        <h2 className="text-sm font-semibold">
                            One-off visit tasks
                        </h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Applies only to this scheduled visit.
                        </p>
                        <ul className="mt-3 space-y-3">
                            {oneOffs.map((task, index) => (
                                <li
                                    key={task.id ?? `new-${index}`}
                                    className="grid gap-2 md:grid-cols-[1fr_auto]"
                                >
                                    {task.id && (
                                        <input
                                            type="hidden"
                                            name={`one_off_tasks[${index}][id]`}
                                            value={task.id}
                                        />
                                    )}
                                    <Input
                                        name={`one_off_tasks[${index}][title]`}
                                        value={task.title}
                                        placeholder="Pick up prescription before returning home."
                                        onChange={(event) => {
                                            const next = [...oneOffs];
                                            next[index] = {
                                                ...task,
                                                title: event.target.value,
                                            };
                                            setOneOffs(next);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setOneOffs(
                                                oneOffs.filter(
                                                    (_, item) => item !== index,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                    <textarea
                                        name={`one_off_tasks[${index}][instructions]`}
                                        value={task.instructions}
                                        placeholder="Optional instructions"
                                        className={`${controlClassName} h-auto py-2 md:col-span-2`}
                                        rows={2}
                                        onChange={(event) => {
                                            const next = [...oneOffs];
                                            next[index] = {
                                                ...task,
                                                instructions:
                                                    event.target.value,
                                            };
                                            setOneOffs(next);
                                        }}
                                    />
                                </li>
                            ))}
                        </ul>
                        <Button
                            type="button"
                            variant="secondary"
                            className="mt-3"
                            onClick={() =>
                                setOneOffs([
                                    ...oneOffs,
                                    {
                                        title: '',
                                        instructions: '',
                                        note_required: false,
                                    },
                                ])
                            }
                        >
                            Add visit-specific task
                        </Button>
                        {visit && oneOffs.length === 0 && (
                            <input
                                type="hidden"
                                name="one_off_tasks[0][title]"
                                value=""
                            />
                        )}
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Notes and recurrence
                        </h2>
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
                        {visit?.series_id && (
                            <Field label="Series change" htmlFor="series_scope">
                                <select
                                    id="series_scope"
                                    name="series_scope"
                                    defaultValue="this"
                                    className={controlClassName}
                                >
                                    <option value="this">This visit</option>
                                    <option value="future">
                                        This and future
                                    </option>
                                    <option value="series">Entire series</option>
                                </select>
                            </Field>
                        )}
                        {method === 'post' && (
                            <>
                                <input
                                    type="hidden"
                                    name="repeat"
                                    value={repeat ? '1' : '0'}
                                />
                                {repeat && (
                                    <>
                                        <Field
                                            label="Repeat"
                                            htmlFor="repeat_pattern"
                                        >
                                            <select
                                                id="repeat_pattern"
                                                name="repeat_pattern"
                                                defaultValue="weekly"
                                                className={controlClassName}
                                            >
                                                <option value="daily">Daily</option>
                                                <option value="weekdays">
                                                    Weekdays
                                                </option>
                                                <option value="weekly">Weekly</option>
                                                <option value="biweekly">
                                                    Biweekly
                                                </option>
                                                <option value="custom">Custom</option>
                                            </select>
                                        </Field>
                                        <Field
                                            label="Repeat until"
                                            htmlFor="repeat_ends_on"
                                        >
                                            <Input
                                                id="repeat_ends_on"
                                                name="repeat_ends_on"
                                                type="date"
                                            />
                                        </Field>
                                    </>
                                )}
                            </>
                        )}
                        {method !== 'post' && (
                            <Field
                                label="Cancellation reason"
                                htmlFor="cancellation_reason"
                            >
                                <Input
                                    id="cancellation_reason"
                                    name="cancellation_reason"
                                />
                            </Field>
                        )}
                    </section>

                    <div className="sticky-form-actions flex flex-wrap gap-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-md px-4 text-sm font-medium shadow-xs disabled:opacity-50"
                            onClick={() => setRepeat(false)}
                        >
                            {submitLabel}
                        </button>
                        {method === 'post' && (
                            <button
                                type="submit"
                                disabled={processing}
                                className="border-border inline-flex h-9 items-center rounded-md border px-4 text-sm font-medium disabled:opacity-50"
                                onClick={() => setRepeat(true)}
                            >
                                Schedule & Repeat
                            </button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}

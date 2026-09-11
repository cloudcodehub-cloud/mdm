import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { DspAvailabilityBoard } from '@/components/mdm/dsp-availability-board';
import { Field, controlClassName } from '@/components/mdm/directory';
import {
    VisitCarePlanEditor,
    type OneOffDraft,
    type VisitCareTask,
    type VisitCatalogOption,
} from '@/components/mdm/visit-care-plan-editor';
import { Input } from '@/components/ui/input';
import type {
    ClientScheduleOption,
    DspScheduleOption,
    OptionItem,
    ShiftTemplateOption,
    VisitRecord,
} from '@/types/directory';
import type { AvailabilityBoard } from '@/types/scheduling';

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
    const [serviceIds, setServiceIds] = useState<number[]>(
        (visit?.services ?? []).map((service) => service.id),
    );
    const [serviceDate, setServiceDate] = useState(visit?.service_date ?? '');
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
    const [showSupervisorOverride, setShowSupervisorOverride] = useState(
        Boolean(
            isAdmin &&
                visit?.supervisor_id &&
                visit.supervisor_id !==
                    clients.find((client) => client.id === visit.client_id)
                        ?.supervisor_id,
        ),
    );
    const [repeat, setRepeat] = useState(false);
    const [preview, setPreview] = useState<VisitCareTask[]>([]);
    const [catalog, setCatalog] = useState<VisitCatalogOption[]>([]);
    const [board, setBoard] = useState<AvailabilityBoard | null>(null);
    const [oneOffs, setOneOffs] = useState<OneOffDraft[]>(
        (visit?.one_off_tasks ?? []).map((task) => ({
            id: task.id,
            catalog_item_id: task.catalog_item_id ?? null,
            title: task.title,
            instructions: task.instructions ?? '',
            note_required: Boolean(task.note_required),
            is_required: task.is_required !== false,
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
    const selectedDspName =
        board?.dsps.find((row) => String(row.id) === employeeId)?.name ??
        visit?.dsp_name ??
        dsps.find((row) => String(row.id) === employeeId)?.name;

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

    const serviceTypeLabel = clientServices
        .filter((service) => serviceIds.includes(service.id))
        .map((service) => service.name)
        .join(' · ');

    useEffect(() => {
        if (!carePreviewUrl || clientId === '' || serviceDate === '') {
            setPreview([]);
            setCatalog([]);
            return;
        }

        const params = new URLSearchParams({
            client_id: clientId,
            service_date: serviceDate,
        });
        serviceIds.forEach((id) => params.append('service_ids[]', String(id)));

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
            .then(
                (
                    payload: {
                        tasks?: VisitCareTask[];
                        catalog?: VisitCatalogOption[];
                    } | null,
                ) => {
                    setPreview(payload?.tasks ?? []);
                    setCatalog(payload?.catalog ?? []);
                },
            )
            .catch(() => {
                setPreview([]);
                setCatalog([]);
            });

        return () => controller.abort();
    }, [carePreviewUrl, clientId, serviceDate, serviceIds.join(','), visit?.id]);

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
            service_type: serviceTypeLabel,
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
        serviceTypeLabel,
        requestTimes.starts,
        requestTimes.ends,
        timingMode,
        templateId,
        visit?.id,
    ]);

    const toggleService = (id: number) => {
        setServiceIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
        setEmployeeId('');
    };

    return (
        <Form action={action} method={method} className="space-y-4">
            {({ processing, errors }) => (
                <>
                    <section className="surface-panel grid gap-3 p-4 md:grid-cols-2 md:p-5">
                        <div className="md:col-span-2">
                            <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                                1 · Client & Services
                            </p>
                            <h2 className="text-sm font-semibold">
                                Client and services
                            </h2>
                        </div>
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
                                    setServiceIds([]);
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
                        <div>
                            <p className="mb-2 text-sm font-medium">Services</p>
                            {serviceIds.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="service_ids[]"
                                    value={id}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="service_type"
                                value={serviceTypeLabel}
                            />
                            {clientId === '' ? (
                                <p className="text-muted-foreground text-xs">
                                    Select a client to load assigned services.
                                </p>
                            ) : clientServices.length === 0 ? (
                                <p className="text-muted-foreground text-xs">
                                    This client has no active assigned
                                    services.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {clientServices.map((service) => {
                                        const active = serviceIds.includes(
                                            service.id,
                                        );
                                        return (
                                            <button
                                                key={service.id}
                                                type="button"
                                                onClick={() =>
                                                    toggleService(service.id)
                                                }
                                                className={
                                                    active
                                                        ? 'bg-primary text-primary-foreground inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium'
                                                        : 'bg-muted/70 hover:bg-muted inline-flex items-center rounded-full px-3 py-1 text-xs font-medium'
                                                }
                                            >
                                                {service.name}
                                                {active ? ' ×' : ''}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            {errors.service_ids && (
                                <p className="text-destructive mt-1 text-sm">
                                    {errors.service_ids}
                                </p>
                            )}
                            {errors.service_type && (
                                <p className="text-destructive mt-1 text-sm">
                                    {errors.service_type}
                                </p>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <p className="text-sm">
                                Supervisor:{' '}
                                <span className="font-medium">
                                    {supervisorName ??
                                        'Not assigned on client profile'}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · Assigned from client profile
                                </span>
                            </p>
                            <input
                                type="hidden"
                                name="supervisor_id"
                                value={supervisorId}
                            />
                            {isAdmin && (
                                <div className="mt-2">
                                    {!showSupervisorOverride ? (
                                        <button
                                            type="button"
                                            className="text-muted-foreground text-xs underline-offset-4 hover:underline"
                                            onClick={() =>
                                                setShowSupervisorOverride(true)
                                            }
                                        >
                                            Override supervisor
                                        </button>
                                    ) : (
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
                                                <option value="">
                                                    Use client supervisor
                                                </option>
                                                {supervisors.map(
                                                    (supervisor) => (
                                                        <option
                                                            key={supervisor.id}
                                                            value={
                                                                supervisor.id
                                                            }
                                                        >
                                                            {supervisor.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </Field>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="surface-panel grid gap-3 p-4 md:grid-cols-2 md:p-5">
                        <div className="md:col-span-2">
                            <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                                2 · Date & Requested Time
                            </p>
                            <h2 className="text-sm font-semibold">
                                Service date and requested window
                            </h2>
                        </div>
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
                                            {template.name} (
                                            {template.starts_at} –{' '}
                                            {template.ends_at}
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

                    {clientId !== '' && serviceDate !== '' && (
                        <VisitCarePlanEditor
                            tasks={preview}
                            onChange={setPreview}
                            catalog={catalog}
                            oneOffs={oneOffs}
                            onOneOffsChange={setOneOffs}
                            errors={errors}
                        />
                    )}

                    {board?.authorization && (
                        <p className="border-warning/40 bg-warning/10 rounded-md border px-3 py-2 text-sm">
                            {board.authorization.message}
                        </p>
                    )}

                    {board && (
                        <DspAvailabilityBoard
                            board={board}
                            employeeId={employeeId}
                            onSelect={setEmployeeId}
                            error={errors.employee_id}
                        />
                    )}

                    <section className="surface-panel grid gap-3 p-4 md:grid-cols-2 md:p-5">
                        <div className="md:col-span-2">
                            <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                                5 · Notes & Repeat
                            </p>
                            <h2 className="text-sm font-semibold">
                                Notes and recurrence
                            </h2>
                        </div>
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
                                                <option value="daily">
                                                    Daily
                                                </option>
                                                <option value="weekdays">
                                                    Weekdays
                                                </option>
                                                <option value="weekly">
                                                    Weekly
                                                </option>
                                                <option value="biweekly">
                                                    Biweekly
                                                </option>
                                                <option value="custom">
                                                    Custom
                                                </option>
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

                    <section className="surface-panel space-y-2 p-4 md:p-5">
                        <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            6 · Review / Schedule
                        </p>
                        <dl className="grid gap-1 text-sm md:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Client
                                </dt>
                                <dd>{selectedClient?.name ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Services
                                </dt>
                                <dd>{serviceTypeLabel || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Window
                                </dt>
                                <dd>
                                    {serviceDate || '—'} ·{' '}
                                    {requestTimes.starts || '—'}–
                                    {requestTimes.ends || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    DSP
                                </dt>
                                <dd>{selectedDspName ?? '—'}</dd>
                            </div>
                        </dl>
                    </section>

                    {visit && oneOffs.length === 0 && (
                        <input
                            type="hidden"
                            name="one_off_tasks[0][title]"
                            value=""
                        />
                    )}

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

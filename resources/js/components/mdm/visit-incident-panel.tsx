import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Field, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { discard, show as showIncident, update } from '@/routes/incidents';
import { store } from '@/routes/visits/incidents';
import type { VisitIncidentsPayload } from '@/types/incident';

export function VisitIncidentPanel({
    visitId,
    incidents,
    canReport,
}: {
    visitId: number;
    incidents: VisitIncidentsPayload;
    canReport: boolean;
}) {
    const [open, setOpen] = useState(Boolean(incidents.draft) || canReport);
    const draft = incidents.draft;
    const form = useForm({
        occurred_at: draft?.occurred_at
            ? draft.occurred_at.slice(0, 16)
            : new Date().toISOString().slice(0, 16),
        original_category: draft?.original_category ?? '',
        original_subcategory: draft?.original_subcategory ?? '',
        summary: draft?.summary ?? '',
        immediate_action: draft?.immediate_action ?? '',
        injured: draft?.injured ?? false,
        injury_type: draft?.injury_type ?? '',
        injury_body_location: draft?.injury_body_location ?? '',
        injury_medical_check: draft?.injury_medical_check ?? '',
        injury_notes: draft?.injury_notes ?? '',
        intervention: draft?.intervention ?? false,
        intervention_type: draft?.intervention_type ?? '',
        intervention_started_at: draft?.intervention_started_at?.slice(0, 16) ?? '',
        intervention_stopped_at: draft?.intervention_stopped_at?.slice(0, 16) ?? '',
        intervention_notes: draft?.intervention_notes ?? '',
        supervisor_notified: draft?.supervisor_notified ?? false,
        additional_notes: draft?.additional_notes ?? '',
        submit: false,
    });

    const category = incidents.categories.find(
        (item) => item.value === form.data.original_category,
    );

    const save = (submit: boolean) => {
        form.transform((data) => ({ ...data, submit }));
        if (draft) {
            form.patch(update.url(draft.id), { preserveScroll: true });
        } else {
            form.post(store.url(visitId), { preserveScroll: true });
        }
    };

    const discardDraft = () => {
        if (!draft) {
            return;
        }

        if (!window.confirm('Discard this Incident draft? It will not be formally reported.')) {
            return;
        }

        router.post(discard.url(draft.id), { confirm: true }, { preserveScroll: true });
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold tracking-tight">
                        Report Incident
                    </h3>
                    <p className="text-muted-foreground text-xs">
                        Available during the Active Visit. Submitting does not end the visit.
                    </p>
                </div>
                {canReport ? (
                    <Button
                        type="button"
                        size="sm"
                        variant={open ? 'secondary' : 'default'}
                        onClick={() => setOpen((value) => !value)}
                    >
                        {open ? 'Hide form' : 'Report Incident'}
                    </Button>
                ) : null}
            </div>

            {incidents.submitted.length > 0 ? (
                <ul className="space-y-2 text-sm">
                    {incidents.submitted.map((item) => (
                        <li
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-2 border-t pt-2"
                        >
                            <div>
                                <Link
                                    href={showIncident.url(item.id)}
                                    className="hover:text-foreground font-medium"
                                >
                                    {item.tracking_number ?? `Incident #${item.id}`}
                                </Link>
                                <p className="text-muted-foreground text-xs">
                                    {item.original_category}
                                    {item.original_subcategory
                                        ? ` / ${item.original_subcategory}`
                                        : ''}
                                    {item.submitted_at_label
                                        ? ` · ${item.submitted_at_label}`
                                        : ''}
                                </p>
                            </div>
                            <StatusBadge
                                status={item.status}
                                label={item.status_label}
                            />
                        </li>
                    ))}
                </ul>
            ) : null}

            {open && canReport ? (
                <div className="grid gap-3 rounded-md border p-3">
                    {draft ? (
                        <p className="text-muted-foreground text-xs">
                            Draft in progress. Discard requires confirmation. Clock-out will ask you to resolve this draft.
                        </p>
                    ) : null}
                    <Field label="Incident date/time" htmlFor="occurred_at" error={form.errors.occurred_at}>
                        <Input
                            id="occurred_at"
                            type="datetime-local"
                            value={form.data.occurred_at}
                            onChange={(e) => form.setData('occurred_at', e.target.value)}
                        />
                    </Field>
                    <Field label="Category" htmlFor="original_category" error={form.errors.original_category}>
                        <select
                            id="original_category"
                            className={controlClassName}
                            value={form.data.original_category}
                            onChange={(e) => {
                                form.setData('original_category', e.target.value);
                                form.setData('original_subcategory', '');
                            }}
                        >
                            <option value="">Select category</option>
                            {incidents.categories.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Subcategory"
                        htmlFor="original_subcategory"
                        error={form.errors.original_subcategory}
                    >
                        <select
                            id="original_subcategory"
                            className={controlClassName}
                            value={form.data.original_subcategory}
                            onChange={(e) =>
                                form.setData('original_subcategory', e.target.value)
                            }
                            disabled={!category}
                        >
                            <option value="">Optional</option>
                            {(category?.subcategories ?? []).map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Short description" htmlFor="summary" error={form.errors.summary}>
                        <textarea
                            id="summary"
                            className={controlClassName}
                            rows={3}
                            value={form.data.summary}
                            onChange={(e) => form.setData('summary', e.target.value)}
                        />
                    </Field>
                    <Field
                        label="Immediate action taken"
                        htmlFor="immediate_action"
                        error={form.errors.immediate_action}
                    >
                        <textarea
                            id="immediate_action"
                            className={controlClassName}
                            rows={3}
                            value={form.data.immediate_action}
                            onChange={(e) =>
                                form.setData('immediate_action', e.target.value)
                            }
                        />
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="injured"
                            checked={form.data.injured}
                            onCheckedChange={(checked) =>
                                form.setData('injured', Boolean(checked))
                            }
                        />
                        <Label htmlFor="injured">Injured?</Label>
                    </div>
                    {form.data.injured ? (
                        <div className="grid gap-3 md:grid-cols-2">
                            <Field label="Injury type" htmlFor="injury_type">
                                <Input
                                    id="injury_type"
                                    value={form.data.injury_type}
                                    onChange={(e) =>
                                        form.setData('injury_type', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Body / location" htmlFor="injury_body_location">
                                <Input
                                    id="injury_body_location"
                                    value={form.data.injury_body_location}
                                    onChange={(e) =>
                                        form.setData(
                                            'injury_body_location',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="md:col-span-2">
                                <Field
                                    label="Nurse / medical check"
                                    htmlFor="injury_medical_check"
                                >
                                    <Input
                                        id="injury_medical_check"
                                        value={form.data.injury_medical_check}
                                        onChange={(e) =>
                                            form.setData(
                                                'injury_medical_check',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="md:col-span-2">
                                <Field label="Injury notes" htmlFor="injury_notes">
                                    <textarea
                                        id="injury_notes"
                                        className={controlClassName}
                                        rows={2}
                                        value={form.data.injury_notes}
                                        onChange={(e) =>
                                            form.setData('injury_notes', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    ) : null}
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="supervisor_notified"
                            checked={form.data.supervisor_notified}
                            onCheckedChange={(checked) =>
                                form.setData('supervisor_notified', Boolean(checked))
                            }
                        />
                        <Label htmlFor="supervisor_notified">Supervisor notified?</Label>
                    </div>
                    <Field
                        label="Additional notes"
                        htmlFor="additional_notes"
                        error={form.errors.additional_notes}
                    >
                        <textarea
                            id="additional_notes"
                            className={controlClassName}
                            rows={2}
                            value={form.data.additional_notes}
                            onChange={(e) =>
                                form.setData('additional_notes', e.target.value)
                            }
                        />
                    </Field>
                    <InputError
                        message={
                            (form.errors as Record<string, string | undefined>)
                                .incident
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={form.processing}
                            onClick={() => save(false)}
                        >
                            Save draft
                        </Button>
                        <Button
                            type="button"
                            disabled={form.processing}
                            onClick={() => save(true)}
                        >
                            Submit Incident
                        </Button>
                        {draft ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={discardDraft}
                            >
                                Discard draft
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

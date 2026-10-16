import { Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import InputError from "@/components/input-error";
import {
    Field,
    StatusBadge,
    controlClassName,
} from "@/components/mdm/directory";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { discard, show as showIncident, update } from "@/routes/incidents";
import { store } from "@/routes/visits/incidents";
import type {
    IncidentPartyKind,
    VisitIncidentsPayload,
} from "@/types/incident";

type PartyRow = {
    party_kind: Exclude<IncidentPartyKind, "primary_client">;
    client_id: string;
    employee_id: string;
    name: string;
    organization: string;
    involvement: string;
};

const emptyParty = (): PartyRow => ({
    party_kind: "client",
    client_id: "",
    employee_id: "",
    name: "",
    organization: "",
    involvement: "",
});

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
        original_category: draft?.original_category ?? "",
        original_subcategory: draft?.original_subcategory ?? "",
        summary: draft?.summary ?? "",
        immediate_action: draft?.immediate_action ?? "",
        injured: draft?.injured ?? false,
        injury_type: draft?.injury_type ?? "",
        injury_body_location: draft?.injury_body_location ?? "",
        injury_medical_check: draft?.injury_medical_check ?? "",
        injury_notes: draft?.injury_notes ?? "",
        injury_occurred_at: draft?.injury_occurred_at?.slice(0, 16) ?? "",
        intervention: draft?.intervention ?? false,
        intervention_type: draft?.intervention_type ?? "",
        intervention_started_at:
            draft?.intervention_started_at?.slice(0, 16) ?? "",
        intervention_stopped_at:
            draft?.intervention_stopped_at?.slice(0, 16) ?? "",
        intervention_notes: draft?.intervention_notes ?? "",
        supervisor_notified: draft?.supervisor_notified ?? false,
        additional_notes: draft?.additional_notes ?? "",
        parties: (draft?.parties ?? [])
            .filter((party) => party.party_kind !== "primary_client")
            .map((party): PartyRow => ({
                party_kind: party.party_kind as PartyRow["party_kind"],
                client_id: party.client_id ? String(party.client_id) : "",
                employee_id: party.employee_id ? String(party.employee_id) : "",
                name: party.name ?? "",
                organization: party.organization ?? "",
                involvement: party.involvement ?? "",
            })),
        submit: false,
    });

    const partyClients = incidents.party_clients ?? [];
    const partyEmployees = incidents.party_employees ?? [];
    const partyErrors = form.errors as Record<string, string | undefined>;

    const updateParty = (index: number, patch: Partial<PartyRow>) => {
        form.setData(
            "parties",
            form.data.parties.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );
    };

    const removeParty = (index: number) => {
        form.setData(
            "parties",
            form.data.parties.filter((_, i) => i !== index),
        );
    };

    const category = incidents.categories.find(
        (item) => item.value === form.data.original_category,
    );

    const save = (submit: boolean) => {
        form.transform((data) => ({
            ...data,
            submit,
            parties: data.parties.map((row) => ({
                party_kind: row.party_kind,
                client_id:
                    row.party_kind === "client" && row.client_id
                        ? Number(row.client_id)
                        : null,
                employee_id:
                    row.party_kind === "employee" && row.employee_id
                        ? Number(row.employee_id)
                        : null,
                name: row.party_kind === "outside" ? row.name : null,
                organization:
                    row.party_kind === "outside" ? row.organization : null,
                involvement: row.involvement,
            })),
        }));
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

        if (
            !window.confirm(
                "Discard this Incident draft? It will not be formally reported.",
            )
        ) {
            return;
        }

        router.post(
            discard.url(draft.id),
            { confirm: true },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold tracking-tight">
                        Report Incident
                    </h3>
                    <p className="text-muted-foreground text-xs">
                        Available during the Active Visit. Submitting does not
                        end the visit.
                    </p>
                </div>
                {canReport ? (
                    <Button
                        type="button"
                        size="sm"
                        variant={open ? "secondary" : "default"}
                        onClick={() => setOpen((value) => !value)}
                    >
                        {open ? "Hide form" : "Report Incident"}
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
                                    {item.tracking_number ??
                                        `Incident #${item.id}`}
                                </Link>
                                <p className="text-muted-foreground text-xs">
                                    {item.original_category}
                                    {item.original_subcategory
                                        ? ` / ${item.original_subcategory}`
                                        : ""}
                                    {item.submitted_at_label
                                        ? ` · ${item.submitted_at_label}`
                                        : ""}
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
                            Draft in progress. Discard requires confirmation.
                            Clock-out will ask you to resolve this draft.
                        </p>
                    ) : null}
                    <Field
                        label="Incident date/time"
                        htmlFor="occurred_at"
                        error={form.errors.occurred_at}
                    >
                        <Input
                            id="occurred_at"
                            type="datetime-local"
                            value={form.data.occurred_at}
                            onChange={(e) =>
                                form.setData("occurred_at", e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Category"
                        htmlFor="original_category"
                        error={form.errors.original_category}
                    >
                        <select
                            id="original_category"
                            className={controlClassName}
                            value={form.data.original_category}
                            onChange={(e) => {
                                form.setData(
                                    "original_category",
                                    e.target.value,
                                );
                                form.setData("original_subcategory", "");
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
                                form.setData(
                                    "original_subcategory",
                                    e.target.value,
                                )
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
                    <Field
                        label="Short description"
                        htmlFor="summary"
                        error={form.errors.summary}
                    >
                        <textarea
                            id="summary"
                            className={controlClassName}
                            rows={3}
                            value={form.data.summary}
                            onChange={(e) =>
                                form.setData("summary", e.target.value)
                            }
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
                                form.setData("immediate_action", e.target.value)
                            }
                        />
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="injured"
                            checked={form.data.injured}
                            onCheckedChange={(checked) =>
                                form.setData("injured", Boolean(checked))
                            }
                        />
                        <Label htmlFor="injured">Injured?</Label>
                    </div>
                    {form.data.injured ? (
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Field
                                    label="Injury date/time"
                                    htmlFor="injury_occurred_at"
                                    error={form.errors.injury_occurred_at}
                                    required
                                >
                                    <Input
                                        id="injury_occurred_at"
                                        type="datetime-local"
                                        value={form.data.injury_occurred_at}
                                        onChange={(e) =>
                                            form.setData(
                                                "injury_occurred_at",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Injury type"
                                htmlFor="injury_type"
                                error={form.errors.injury_type}
                            >
                                <Input
                                    id="injury_type"
                                    value={form.data.injury_type}
                                    onChange={(e) =>
                                        form.setData(
                                            "injury_type",
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Body / location"
                                htmlFor="injury_body_location"
                            >
                                <Input
                                    id="injury_body_location"
                                    value={form.data.injury_body_location}
                                    onChange={(e) =>
                                        form.setData(
                                            "injury_body_location",
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
                                                "injury_medical_check",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="md:col-span-2">
                                <Field
                                    label="Injury notes"
                                    htmlFor="injury_notes"
                                >
                                    <textarea
                                        id="injury_notes"
                                        className={controlClassName}
                                        rows={2}
                                        value={form.data.injury_notes}
                                        onChange={(e) =>
                                            form.setData(
                                                "injury_notes",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    ) : null}
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="intervention"
                            checked={form.data.intervention}
                            onCheckedChange={(checked) =>
                                form.setData("intervention", Boolean(checked))
                            }
                        />
                        <Label htmlFor="intervention">Intervention used?</Label>
                    </div>
                    {form.data.intervention ? (
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Field
                                    label="Intervention type"
                                    htmlFor="intervention_type"
                                    error={form.errors.intervention_type}
                                >
                                    <Input
                                        id="intervention_type"
                                        value={form.data.intervention_type}
                                        onChange={(e) =>
                                            form.setData(
                                                "intervention_type",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Started"
                                htmlFor="intervention_started_at"
                                error={form.errors.intervention_started_at}
                            >
                                <Input
                                    id="intervention_started_at"
                                    type="datetime-local"
                                    value={form.data.intervention_started_at}
                                    onChange={(e) =>
                                        form.setData(
                                            "intervention_started_at",
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Stopped"
                                htmlFor="intervention_stopped_at"
                                error={form.errors.intervention_stopped_at}
                            >
                                <Input
                                    id="intervention_stopped_at"
                                    type="datetime-local"
                                    value={form.data.intervention_stopped_at}
                                    onChange={(e) =>
                                        form.setData(
                                            "intervention_stopped_at",
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="md:col-span-2">
                                <Field
                                    label="Intervention notes"
                                    htmlFor="intervention_notes"
                                    error={form.errors.intervention_notes}
                                >
                                    <textarea
                                        id="intervention_notes"
                                        className={controlClassName}
                                        rows={2}
                                        value={form.data.intervention_notes}
                                        onChange={(e) =>
                                            form.setData(
                                                "intervention_notes",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    ) : null}
                    <div className="grid gap-2 rounded-md border p-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-medium">
                                    Additional parties
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    The visit client is added automatically.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    form.setData("parties", [
                                        ...form.data.parties,
                                        emptyParty(),
                                    ])
                                }
                            >
                                Add party
                            </Button>
                        </div>
                        {form.data.parties.map((row, index) => (
                            <div
                                key={index}
                                className="grid gap-2 border-t pt-2 md:grid-cols-2"
                            >
                                <Field
                                    label="Party type"
                                    htmlFor={`party_kind_${index}`}
                                    error={
                                        partyErrors[
                                            `parties.${index}.party_kind`
                                        ]
                                    }
                                >
                                    <select
                                        id={`party_kind_${index}`}
                                        className={controlClassName}
                                        value={row.party_kind}
                                        onChange={(e) =>
                                            updateParty(index, {
                                                party_kind: e.target
                                                    .value as PartyRow["party_kind"],
                                            })
                                        }
                                    >
                                        <option value="client">
                                            Another client
                                        </option>
                                        <option value="employee">
                                            Employee / staff
                                        </option>
                                        <option value="outside">
                                            Outside person
                                        </option>
                                    </select>
                                </Field>
                                {row.party_kind === "client" ? (
                                    <Field
                                        label="Client"
                                        htmlFor={`party_client_${index}`}
                                        error={
                                            partyErrors[
                                                `parties.${index}.client_id`
                                            ]
                                        }
                                    >
                                        <select
                                            id={`party_client_${index}`}
                                            className={controlClassName}
                                            value={row.client_id}
                                            onChange={(e) =>
                                                updateParty(index, {
                                                    client_id: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Select client
                                            </option>
                                            {partyClients.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                ) : null}
                                {row.party_kind === "employee" ? (
                                    <Field
                                        label="Employee"
                                        htmlFor={`party_employee_${index}`}
                                        error={
                                            partyErrors[
                                                `parties.${index}.employee_id`
                                            ]
                                        }
                                    >
                                        <select
                                            id={`party_employee_${index}`}
                                            className={controlClassName}
                                            value={row.employee_id}
                                            onChange={(e) =>
                                                updateParty(index, {
                                                    employee_id: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Select employee
                                            </option>
                                            {partyEmployees.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                ) : null}
                                {row.party_kind === "outside" ? (
                                    <>
                                        <Field
                                            label="Name"
                                            htmlFor={`party_name_${index}`}
                                            error={
                                                partyErrors[
                                                    `parties.${index}.name`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`party_name_${index}`}
                                                value={row.name}
                                                onChange={(e) =>
                                                    updateParty(index, {
                                                        name: e.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Organization"
                                            htmlFor={`party_org_${index}`}
                                            error={
                                                partyErrors[
                                                    `parties.${index}.organization`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`party_org_${index}`}
                                                value={row.organization}
                                                onChange={(e) =>
                                                    updateParty(index, {
                                                        organization:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </>
                                ) : null}
                                <div className="md:col-span-2">
                                    <Field
                                        label="Involvement"
                                        htmlFor={`party_involvement_${index}`}
                                        error={
                                            partyErrors[
                                                `parties.${index}.involvement`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`party_involvement_${index}`}
                                            value={row.involvement}
                                            onChange={(e) =>
                                                updateParty(index, {
                                                    involvement: e.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                </div>
                                <div className="md:col-span-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => removeParty(index)}
                                    >
                                        Remove party
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="supervisor_notified"
                            checked={form.data.supervisor_notified}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    "supervisor_notified",
                                    Boolean(checked),
                                )
                            }
                        />
                        <Label htmlFor="supervisor_notified">
                            Supervisor notified?
                        </Label>
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
                                form.setData("additional_notes", e.target.value)
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

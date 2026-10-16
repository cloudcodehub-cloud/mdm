import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import type { ReactNode } from "react";
import InputError from "@/components/input-error";
import {
    Field,
    StatusBadge,
    controlClassName,
} from "@/components/mdm/directory";
import { EmptyState, Panel } from "@/components/mdm/stat-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { dashboard } from "@/routes";
import {
    acknowledge,
    close as closeIncident,
    index as incidentsIndex,
    reopen as reopenIncident,
} from "@/routes/incidents";
import { store as storeAmendment } from "@/routes/incidents/amendments";
import { update as updateClassification } from "@/routes/incidents/classification";
import {
    complete as completeFollowUp,
    store as storeFollowUp,
} from "@/routes/incidents/follow-ups";
import { update as updateInvestigation } from "@/routes/incidents/investigation";
import { store as storeReportingRecord } from "@/routes/incidents/reporting-records";
import { store as storeReview } from "@/routes/incidents/reviews";
import { show as showVisit } from "@/routes/visits";
import type {
    IncidentCategoryOption,
    IncidentDetail,
    IncidentFollowUp,
} from "@/types/incident";

type AssigneeOption = { id: number; name: string };

type IncidentCan = {
    acknowledge?: boolean;
    manage?: boolean;
    amend?: boolean;
    close?: boolean;
    reopen?: boolean;
};

const AMENDABLE_FIELDS: Array<{ value: string; label: string }> = [
    { value: "summary", label: "Summary" },
    { value: "immediate_action", label: "Immediate action" },
    { value: "additional_notes", label: "Additional notes" },
    { value: "original_category", label: "Category" },
    { value: "original_subcategory", label: "Subcategory" },
];

const fieldLabel = (key: string): string =>
    AMENDABLE_FIELDS.find((item) => item.value === key)?.label ?? key;

const formatIso = (value: string | null): string => {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

export default function IncidentsShow({
    incident,
    can,
    categories = [],
    assignee_options: assigneeOptions = [],
}: {
    incident: IncidentDetail;
    can?: IncidentCan;
    categories?: IncidentCategoryOption[];
    assignee_options?: AssigneeOption[];
}) {
    const showManagement =
        Boolean(can?.manage) && !incident.management_layer_hidden;

    return (
        <>
            <Head
                title={
                    incident.tracking_number
                        ? `Incident ${incident.tracking_number}`
                        : "Incident"
                }
            />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            <Link
                                href={incidentsIndex()}
                                className="hover:text-foreground"
                            >
                                Incident register
                            </Link>
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {incident.tracking_number ??
                                `Incident #${incident.id}`}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {incident.client.name} · submitted{" "}
                            {incident.submitted_at_label ?? "—"}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge
                            status={incident.status}
                            label={incident.status_label}
                        />
                        {incident.overdue_for_acknowledgment ? (
                            <StatusBadge
                                status="attention"
                                label="Overdue for acknowledgment"
                            />
                        ) : null}
                        {can?.acknowledge ? (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.post(acknowledge.url(incident.id))
                                }
                            >
                                Acknowledge Incident
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-12">
                    <Panel
                        title="Reporter submission"
                        description="Original DSP factual report. Not silently rewritten after submit."
                        className="lg:col-span-8"
                    >
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <Item label="Occurred">
                                {incident.occurred_at_label ?? "—"}
                            </Item>
                            <Item label="Category">
                                {incident.original_category}
                                {incident.original_subcategory
                                    ? ` / ${incident.original_subcategory}`
                                    : ""}
                            </Item>
                            <Item label="Summary" wide>
                                <span className="whitespace-pre-wrap">
                                    {incident.summary}
                                </span>
                            </Item>
                            <Item label="Immediate action" wide>
                                <span className="whitespace-pre-wrap">
                                    {incident.immediate_action}
                                </span>
                            </Item>
                            <Item label="Injured">
                                {incident.injured ? "Yes" : "No"}
                            </Item>
                            <Item label="Supervisor notified">
                                {incident.supervisor_notified ? "Yes" : "No"}
                            </Item>
                            {incident.injured ? (
                                <>
                                    <Item label="Injury date/time">
                                        {incident.injury_occurred_at_label ??
                                            "—"}
                                    </Item>
                                    <Item label="Injury type">
                                        {incident.injury_type ?? "—"}
                                    </Item>
                                    <Item label="Body / location">
                                        {incident.injury_body_location ?? "—"}
                                    </Item>
                                    <Item label="Nurse / medical check">
                                        {incident.injury_medical_check ?? "—"}
                                    </Item>
                                    {incident.injury_notes ? (
                                        <Item label="Injury notes" wide>
                                            <span className="whitespace-pre-wrap">
                                                {incident.injury_notes}
                                            </span>
                                        </Item>
                                    ) : null}
                                </>
                            ) : null}
                            <Item label="Intervention">
                                {incident.intervention ? "Yes" : "No"}
                            </Item>
                            {incident.intervention ? (
                                <>
                                    <Item label="Intervention type">
                                        {incident.intervention_type ?? "—"}
                                    </Item>
                                    <Item label="Started">
                                        {formatIso(
                                            incident.intervention_started_at,
                                        )}
                                    </Item>
                                    <Item label="Stopped">
                                        {formatIso(
                                            incident.intervention_stopped_at,
                                        )}
                                    </Item>
                                    {incident.intervention_notes ? (
                                        <Item label="Intervention notes" wide>
                                            <span className="whitespace-pre-wrap">
                                                {incident.intervention_notes}
                                            </span>
                                        </Item>
                                    ) : null}
                                </>
                            ) : null}
                            {incident.additional_notes ? (
                                <Item label="Additional notes" wide>
                                    <span className="whitespace-pre-wrap">
                                        {incident.additional_notes}
                                    </span>
                                </Item>
                            ) : null}
                        </dl>

                        <div className="mt-4 border-t pt-3">
                            <p className="text-sm font-medium">Parties</p>
                            {incident.parties.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No parties recorded.
                                </p>
                            ) : (
                                <ul className="mt-1 space-y-1 text-sm">
                                    {incident.parties.map((party) => (
                                        <li key={party.id}>
                                            <span className="font-medium">
                                                {party.client_name ??
                                                    party.employee_name ??
                                                    party.name ??
                                                    "—"}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {" · "}
                                                {party.party_kind_label}
                                                {party.organization
                                                    ? ` · ${party.organization}`
                                                    : ""}
                                                {party.involvement
                                                    ? ` · ${party.involvement}`
                                                    : ""}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="mt-4 border-t pt-3">
                            <p className="text-sm font-medium">Amendments</p>
                            {incident.amendments.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No amendments. Original content is
                                    preserved.
                                </p>
                            ) : (
                                <ul className="mt-1 space-y-2 text-sm">
                                    {incident.amendments.map((amendment) => (
                                        <li key={amendment.id}>
                                            <p className="font-medium">
                                                {fieldLabel(
                                                    amendment.field_key,
                                                )}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {amendment.created_at_label}
                                                {amendment.author_name
                                                    ? ` · ${amendment.author_name}`
                                                    : ""}
                                            </p>
                                            <p className="whitespace-pre-wrap">
                                                <span className="text-muted-foreground">
                                                    Was:{" "}
                                                </span>
                                                {amendment.original_content ??
                                                    "—"}
                                            </p>
                                            <p className="whitespace-pre-wrap">
                                                <span className="text-muted-foreground">
                                                    Now:{" "}
                                                </span>
                                                {amendment.new_content ?? "—"}
                                            </p>
                                            <p className="text-muted-foreground">
                                                Reason: {amendment.reason}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {incident.documents.submitted ||
                        incident.documents.final ? (
                            <div className="mt-4 flex flex-wrap gap-2 border-t pt-3">
                                {incident.documents.submitted ? (
                                    <Button
                                        asChild
                                        variant="secondary"
                                        size="sm"
                                    >
                                        <a
                                            href={incident.documents.submitted}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Submitted report
                                        </a>
                                    </Button>
                                ) : null}
                                {incident.documents.final ? (
                                    <Button
                                        asChild
                                        variant="secondary"
                                        size="sm"
                                    >
                                        <a
                                            href={incident.documents.final}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Final report
                                        </a>
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </Panel>

                    <Panel title="Context" className="lg:col-span-4">
                        <dl className="grid gap-3 text-sm">
                            <Item label="Reporter">
                                {incident.reporter.name}
                            </Item>
                            <Item label="Responsible supervisor">
                                {incident.responsible_supervisor?.name ?? "—"}
                            </Item>
                            <Item label="Official classification">
                                {incident.official_category
                                    ? `${incident.official_category}${
                                          incident.official_subcategory
                                              ? ` / ${incident.official_subcategory}`
                                              : ""
                                      }`
                                    : "—"}
                            </Item>
                            <Item label="Acknowledgment">
                                {incident.is_acknowledged
                                    ? `${incident.acknowledged_at_label}${
                                          incident.acknowledged_by_name
                                              ? ` · ${incident.acknowledged_by_name}`
                                              : ""
                                      }`
                                    : incident.acknowledgment_condition}
                            </Item>
                            {incident.closed_at_label ? (
                                <Item label="Closed">
                                    {incident.closed_at_label}
                                    {incident.closed_by_name
                                        ? ` · ${incident.closed_by_name}`
                                        : ""}
                                    {incident.closure_note ? (
                                        <span className="block whitespace-pre-wrap">
                                            {incident.closure_note}
                                        </span>
                                    ) : null}
                                </Item>
                            ) : null}
                            {incident.reopened_at_label ? (
                                <Item label="Reopened">
                                    {incident.reopened_at_label}
                                    {incident.reopen_reason ? (
                                        <span className="block whitespace-pre-wrap">
                                            {incident.reopen_reason}
                                        </span>
                                    ) : null}
                                </Item>
                            ) : null}
                            <div>
                                <Button asChild variant="secondary" size="sm">
                                    <Link
                                        href={showVisit.url(incident.visit_id)}
                                    >
                                        Open related visit
                                    </Link>
                                </Button>
                            </div>
                        </dl>
                    </Panel>

                    {can?.amend ? (
                        <Panel
                            title="Amend report"
                            description="Amendments are recorded alongside the original content."
                            className="lg:col-span-12"
                        >
                            <AmendmentForm incident={incident} />
                        </Panel>
                    ) : null}

                    {showManagement ? (
                        <>
                            <Panel
                                title="Official classification"
                                className="lg:col-span-6"
                            >
                                <ClassificationForm
                                    incident={incident}
                                    categories={categories}
                                />
                            </Panel>

                            <Panel
                                title="Management reviews"
                                className="lg:col-span-6"
                            >
                                <ReviewsSection incident={incident} />
                            </Panel>

                            <Panel
                                title="Investigation"
                                description="Finding, cause / contributing factors and prevention plan."
                                className="lg:col-span-12"
                            >
                                <InvestigationForm incident={incident} />
                            </Panel>

                            <Panel title="Follow-ups" className="lg:col-span-6">
                                <FollowUpsSection
                                    incident={incident}
                                    assigneeOptions={assigneeOptions}
                                />
                            </Panel>

                            <Panel
                                title="Reporting records"
                                description="External or internal reporting of this Incident."
                                className="lg:col-span-6"
                            >
                                <ReportingRecordsSection
                                    incident={incident}
                                    assigneeOptions={assigneeOptions}
                                />
                            </Panel>

                            {can?.close || can?.reopen ? (
                                <Panel
                                    title="Closure"
                                    className="lg:col-span-12"
                                >
                                    {can?.close ? (
                                        <CloseForm incident={incident} />
                                    ) : null}
                                    {can?.reopen ? (
                                        <ReopenForm incident={incident} />
                                    ) : null}
                                </Panel>
                            ) : null}
                        </>
                    ) : null}

                    <Panel
                        title="Audit history"
                        description="Meaningful Incident actions remain historically recoverable."
                        className="lg:col-span-12"
                    >
                        {incident.history.length === 0 ? (
                            <EmptyState compact message="No history entries." />
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {incident.history.map((event) => (
                                    <li
                                        key={event.id}
                                        className="border-t pt-2"
                                    >
                                        <p className="font-medium">
                                            {event.action_label}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {event.created_at_label}
                                            {event.actor_name
                                                ? ` · ${event.actor_name}`
                                                : ""}
                                        </p>
                                        <p>{event.summary}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

function Item({
    label,
    children,
    wide = false,
}: {
    label: string;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <div className={wide ? "md:col-span-2" : undefined}>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function AmendmentForm({ incident }: { incident: IncidentDetail }) {
    const form = useForm({
        field_key: "summary",
        new_content: "",
        reason: "",
    });

    return (
        <form
            className="grid gap-3 md:max-w-2xl"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(storeAmendment.url(incident.id), {
                    preserveScroll: true,
                    onSuccess: () => form.reset("new_content", "reason"),
                });
            }}
        >
            <Field
                label="Field"
                htmlFor="amend_field_key"
                error={form.errors.field_key}
            >
                <select
                    id="amend_field_key"
                    className={controlClassName}
                    value={form.data.field_key}
                    onChange={(e) => form.setData("field_key", e.target.value)}
                >
                    {AMENDABLE_FIELDS.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                label="New content"
                htmlFor="amend_new_content"
                error={form.errors.new_content}
            >
                <textarea
                    id="amend_new_content"
                    className={controlClassName}
                    rows={3}
                    value={form.data.new_content}
                    onChange={(e) =>
                        form.setData("new_content", e.target.value)
                    }
                />
            </Field>
            <Field
                label="Reason for amendment"
                htmlFor="amend_reason"
                error={form.errors.reason}
            >
                <textarea
                    id="amend_reason"
                    className={controlClassName}
                    rows={2}
                    value={form.data.reason}
                    onChange={(e) => form.setData("reason", e.target.value)}
                />
            </Field>
            <div>
                <Button type="submit" disabled={form.processing}>
                    Submit amendment
                </Button>
            </div>
        </form>
    );
}

function ClassificationForm({
    incident,
    categories,
}: {
    incident: IncidentDetail;
    categories: IncidentCategoryOption[];
}) {
    const form = useForm({
        official_category:
            incident.official_category ?? incident.original_category ?? "",
        official_subcategory:
            incident.official_subcategory ??
            incident.original_subcategory ??
            "",
    });
    const category = categories.find(
        (item) => item.value === form.data.official_category,
    );

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(updateClassification.url(incident.id), {
                    preserveScroll: true,
                });
            }}
        >
            <Field
                label="Category"
                htmlFor="official_category"
                error={form.errors.official_category}
            >
                <select
                    id="official_category"
                    className={controlClassName}
                    value={form.data.official_category}
                    onChange={(e) => {
                        form.setData("official_category", e.target.value);
                        form.setData("official_subcategory", "");
                    }}
                >
                    <option value="">Select category</option>
                    {categories.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                label="Subcategory"
                htmlFor="official_subcategory"
                error={form.errors.official_subcategory}
            >
                <select
                    id="official_subcategory"
                    className={controlClassName}
                    value={form.data.official_subcategory}
                    onChange={(e) =>
                        form.setData("official_subcategory", e.target.value)
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
            <div>
                <Button type="submit" disabled={form.processing}>
                    Save classification
                </Button>
            </div>
        </form>
    );
}

function ReviewsSection({ incident }: { incident: IncidentDetail }) {
    const form = useForm({ note: "" });
    const reviews = incident.reviews ?? [];

    return (
        <div className="grid gap-3">
            {reviews.length === 0 ? (
                <EmptyState compact message="No reviews yet." />
            ) : (
                <ul className="space-y-2 text-sm">
                    {reviews.map((review) => (
                        <li key={review.id} className="border-t pt-2">
                            <p className="text-muted-foreground text-xs">
                                {review.created_at_label}
                                {review.reviewer_name
                                    ? ` · ${review.reviewer_name}`
                                    : ""}
                            </p>
                            <p className="whitespace-pre-wrap">{review.note}</p>
                        </li>
                    ))}
                </ul>
            )}
            <form
                className="grid gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeReview.url(incident.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <Field
                    label="Add review"
                    htmlFor="review_note"
                    error={form.errors.note}
                >
                    <textarea
                        id="review_note"
                        className={controlClassName}
                        rows={3}
                        value={form.data.note}
                        onChange={(e) => form.setData("note", e.target.value)}
                    />
                </Field>
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Add review
                    </Button>
                </div>
            </form>
        </div>
    );
}

function InvestigationForm({ incident }: { incident: IncidentDetail }) {
    const form = useForm({
        finding: incident.finding ?? "",
        cause_contributing_factors: incident.cause_contributing_factors ?? "",
        prevention_plan: incident.prevention_plan ?? "",
    });

    return (
        <form
            className="grid gap-3 md:grid-cols-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(updateInvestigation.url(incident.id), {
                    preserveScroll: true,
                });
            }}
        >
            <Field
                label="Finding"
                htmlFor="finding"
                error={form.errors.finding}
            >
                <textarea
                    id="finding"
                    className={controlClassName}
                    rows={4}
                    value={form.data.finding}
                    onChange={(e) => form.setData("finding", e.target.value)}
                />
            </Field>
            <Field
                label="Cause / contributing factors"
                htmlFor="cause_contributing_factors"
                error={form.errors.cause_contributing_factors}
            >
                <textarea
                    id="cause_contributing_factors"
                    className={controlClassName}
                    rows={4}
                    value={form.data.cause_contributing_factors}
                    onChange={(e) =>
                        form.setData(
                            "cause_contributing_factors",
                            e.target.value,
                        )
                    }
                />
            </Field>
            <Field
                label="Prevention plan"
                htmlFor="prevention_plan"
                error={form.errors.prevention_plan}
            >
                <textarea
                    id="prevention_plan"
                    className={controlClassName}
                    rows={4}
                    value={form.data.prevention_plan}
                    onChange={(e) =>
                        form.setData("prevention_plan", e.target.value)
                    }
                />
            </Field>
            <div className="md:col-span-3">
                <Button type="submit" disabled={form.processing}>
                    Save investigation
                </Button>
            </div>
        </form>
    );
}

function FollowUpsSection({
    incident,
    assigneeOptions,
}: {
    incident: IncidentDetail;
    assigneeOptions: AssigneeOption[];
}) {
    const followUps = incident.follow_ups ?? [];
    const form = useForm({
        action_type: "",
        assigned_employee_id: "",
        due_on: "",
    });

    return (
        <div className="grid gap-3">
            {followUps.length === 0 ? (
                <EmptyState compact message="No follow-ups yet." />
            ) : (
                <ul className="space-y-2 text-sm">
                    {followUps.map((followUp) => (
                        <FollowUpRow key={followUp.id} followUp={followUp} />
                    ))}
                </ul>
            )}
            <form
                className="grid gap-2 border-t pt-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeFollowUp.url(incident.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <Field
                    label="Follow-up action"
                    htmlFor="follow_up_action_type"
                    error={form.errors.action_type}
                >
                    <Input
                        id="follow_up_action_type"
                        value={form.data.action_type}
                        onChange={(e) =>
                            form.setData("action_type", e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Assigned to"
                    htmlFor="follow_up_assignee"
                    error={form.errors.assigned_employee_id}
                >
                    <select
                        id="follow_up_assignee"
                        className={controlClassName}
                        value={form.data.assigned_employee_id}
                        onChange={(e) =>
                            form.setData("assigned_employee_id", e.target.value)
                        }
                    >
                        <option value="">Select employee</option>
                        {assigneeOptions.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Due"
                    htmlFor="follow_up_due_on"
                    error={form.errors.due_on}
                >
                    <Input
                        id="follow_up_due_on"
                        type="date"
                        value={form.data.due_on}
                        onChange={(e) => form.setData("due_on", e.target.value)}
                    />
                </Field>
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Add follow-up
                    </Button>
                </div>
            </form>
        </div>
    );
}

function FollowUpRow({ followUp }: { followUp: IncidentFollowUp }) {
    const [note, setNote] = useState("");
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const isOpen = followUp.status === "open";

    return (
        <li className="border-t pt-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{followUp.action_type}</p>
                <StatusBadge
                    status={followUp.status}
                    label={followUp.status_label}
                />
            </div>
            <p className="text-muted-foreground text-xs">
                {followUp.assignee_name ?? "Unassigned"}
                {followUp.due_on ? ` · due ${followUp.due_on}` : ""}
                {followUp.completed_at_label
                    ? ` · completed ${followUp.completed_at_label}`
                    : ""}
            </p>
            {followUp.completion_note ? (
                <p className="whitespace-pre-wrap">
                    {followUp.completion_note}
                </p>
            ) : null}
            {isOpen ? (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <Input
                        aria-label="Completion note"
                        placeholder="Completion note (optional)"
                        className="max-w-xs"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        disabled={processing}
                        onClick={() =>
                            router.post(
                                completeFollowUp.url(followUp.id),
                                { completion_note: note },
                                {
                                    preserveScroll: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                    onError: (errors) =>
                                        setError(
                                            errors.completion_note ??
                                                Object.values(errors)[0],
                                        ),
                                    onSuccess: () => {
                                        setNote("");
                                        setError(undefined);
                                    },
                                },
                            )
                        }
                    >
                        Complete
                    </Button>
                </div>
            ) : null}
            <InputError message={error} />
        </li>
    );
}

function ReportingRecordsSection({
    incident,
    assigneeOptions,
}: {
    incident: IncidentDetail;
    assigneeOptions: AssigneeOption[];
}) {
    const records = incident.reporting_records ?? [];
    const form = useForm({
        reported_to: "",
        person_organization: "",
        reported_at: "",
        reported_by_employee_id: "",
        category: "",
        method: "",
        reference_number: "",
        note: "",
    });

    return (
        <div className="grid gap-3">
            {records.length === 0 ? (
                <EmptyState compact message="No reporting records yet." />
            ) : (
                <ul className="space-y-2 text-sm">
                    {records.map((record) => (
                        <li key={record.id} className="border-t pt-2">
                            <p className="font-medium">
                                {record.reported_to}
                                {record.person_organization
                                    ? ` · ${record.person_organization}`
                                    : ""}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {record.reported_at_label ?? "—"}
                                {record.reported_by_name
                                    ? ` · by ${record.reported_by_name}`
                                    : ""}
                                {record.category ? ` · ${record.category}` : ""}
                                {record.method ? ` · ${record.method}` : ""}
                                {record.reference_number
                                    ? ` · ref ${record.reference_number}`
                                    : ""}
                            </p>
                            {record.note ? (
                                <p className="whitespace-pre-wrap">
                                    {record.note}
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            <form
                className="grid gap-2 border-t pt-3 md:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeReportingRecord.url(incident.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <Field
                    label="Reported to"
                    htmlFor="rr_reported_to"
                    error={form.errors.reported_to}
                >
                    <Input
                        id="rr_reported_to"
                        value={form.data.reported_to}
                        onChange={(e) =>
                            form.setData("reported_to", e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Person / organization"
                    htmlFor="rr_person_organization"
                    error={form.errors.person_organization}
                >
                    <Input
                        id="rr_person_organization"
                        value={form.data.person_organization}
                        onChange={(e) =>
                            form.setData("person_organization", e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Reported at"
                    htmlFor="rr_reported_at"
                    error={form.errors.reported_at}
                >
                    <Input
                        id="rr_reported_at"
                        type="datetime-local"
                        value={form.data.reported_at}
                        onChange={(e) =>
                            form.setData("reported_at", e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Reported by"
                    htmlFor="rr_reported_by"
                    error={form.errors.reported_by_employee_id}
                >
                    <select
                        id="rr_reported_by"
                        className={controlClassName}
                        value={form.data.reported_by_employee_id}
                        onChange={(e) =>
                            form.setData(
                                "reported_by_employee_id",
                                e.target.value,
                            )
                        }
                    >
                        <option value="">Optional</option>
                        {assigneeOptions.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Category"
                    htmlFor="rr_category"
                    error={form.errors.category}
                >
                    <Input
                        id="rr_category"
                        value={form.data.category}
                        onChange={(e) =>
                            form.setData("category", e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Method"
                    htmlFor="rr_method"
                    error={form.errors.method}
                >
                    <Input
                        id="rr_method"
                        value={form.data.method}
                        onChange={(e) => form.setData("method", e.target.value)}
                    />
                </Field>
                <Field
                    label="Reference number"
                    htmlFor="rr_reference_number"
                    error={form.errors.reference_number}
                >
                    <Input
                        id="rr_reference_number"
                        value={form.data.reference_number}
                        onChange={(e) =>
                            form.setData("reference_number", e.target.value)
                        }
                    />
                </Field>
                <Field label="Note" htmlFor="rr_note" error={form.errors.note}>
                    <Input
                        id="rr_note"
                        value={form.data.note}
                        onChange={(e) => form.setData("note", e.target.value)}
                    />
                </Field>
                <div className="md:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        Add reporting record
                    </Button>
                </div>
            </form>
        </div>
    );
}

function CloseForm({ incident }: { incident: IncidentDetail }) {
    const form = useForm({ closure_note: "" });
    const blockers = incident.closure_blockers ?? [];

    return (
        <form
            className="grid gap-3 md:max-w-2xl"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(closeIncident.url(incident.id), {
                    preserveScroll: true,
                });
            }}
        >
            {blockers.length > 0 ? (
                <div className="rounded-md border border-amber-500/30 bg-amber-50/70 p-3 text-sm dark:bg-amber-950/20">
                    <p className="font-medium">Cannot close yet:</p>
                    <ul className="mt-1 list-disc pl-5">
                        {blockers.map((blocker) => (
                            <li key={blocker}>{blocker}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
            <Field
                label="Closure note"
                htmlFor="closure_note"
                error={form.errors.closure_note}
            >
                <textarea
                    id="closure_note"
                    className={controlClassName}
                    rows={3}
                    value={form.data.closure_note}
                    onChange={(e) =>
                        form.setData("closure_note", e.target.value)
                    }
                />
            </Field>
            <div>
                <Button
                    type="submit"
                    disabled={form.processing || blockers.length > 0}
                >
                    Close Incident
                </Button>
            </div>
        </form>
    );
}

function ReopenForm({ incident }: { incident: IncidentDetail }) {
    const form = useForm({ reopen_reason: "" });

    return (
        <form
            className="grid gap-3 md:max-w-2xl"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(reopenIncident.url(incident.id), {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <Field
                label="Reason for reopening"
                htmlFor="reopen_reason"
                error={form.errors.reopen_reason}
            >
                <textarea
                    id="reopen_reason"
                    className={controlClassName}
                    rows={2}
                    value={form.data.reopen_reason}
                    onChange={(e) =>
                        form.setData("reopen_reason", e.target.value)
                    }
                />
            </Field>
            <div>
                <Button
                    type="submit"
                    variant="secondary"
                    disabled={form.processing}
                >
                    Reopen Incident
                </Button>
            </div>
        </form>
    );
}

IncidentsShow.layout = {
    breadcrumbs: [
        { title: "Dashboard", href: dashboard() },
        { title: "Incidents", href: incidentsIndex() },
    ],
};

import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ComplianceEligibilityPanel } from '@/components/mdm/compliance-eligibility-panel';
import { ConfirmAction } from '@/components/mdm/confirm-action';
import {
    ModuleTabs,
    StatusBadge,
    controlClassName,
} from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { ProfileCompletionMeter } from '@/components/mdm/profile-completion-meter';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    edit,
    index as employeesIndex,
    show,
    status,
} from '@/routes/employees';
import type { ComplianceEligibility } from '@/types/compliance';
import type {
    ActivityRecord,
    CredentialRecord,
    EmployeeDetail,
    ProfileCompletion,
    TrainingRecord,
} from '@/types/directory';

type LifecyclePreview = {
    effective_on: string;
    future_scheduled_count: number;
    will_release_to_staffing_needed?: boolean;
    active_visit?: { warning: string } | null;
    login_consequence?: string;
    consequences: string[];
};

type AdminHistoryRecord = {
    id: number;
    category: string;
    category_label: string;
    summary: string;
    old_value: string | null;
    new_value: string | null;
    reason: string | null;
    effective_on: string | null;
    actor_name: string | null;
    occurred_at_label: string | null;
};

export default function EmployeesShow({
    employee,
    credentials,
    trainings,
    activity,
    admin_history = [],
    profile_completion,
    compliance_eligibility,
    credential_type_options = [],
    lifecycle_previews = null,
    can,
}: {
    employee: EmployeeDetail;
    credentials: CredentialRecord[];
    trainings: TrainingRecord[];
    activity: ActivityRecord[];
    admin_history?: AdminHistoryRecord[];
    profile_completion: ProfileCompletion;
    compliance_eligibility: ComplianceEligibility;
    credential_type_options?: Array<{ value: string; label: string }>;
    lifecycle_previews?: Record<string, LifecyclePreview | null> | null;
    can: {
        update: boolean;
        manage_lifecycle?: boolean;
        archive?: boolean;
        view_sensitive: boolean;
        override_compliance: boolean;
        verify_compliance: boolean;
        submit_compliance_evidence: boolean;
    };
}) {
    const [tab, setTab] = useState('profile');
    const role = usePage().props.auth.user.role;
    const complianceLabel = compliance_eligibility.hard_stop
        ? 'Hard Stop'
        : compliance_eligibility.warnings.length > 0
          ? 'Warning'
          : 'Eligible';

    return (
        <>
            <Head title={employee.name} />
            <div className="page-shell">
                <IdentityHeader
                    leading={
                        <ProfilePhoto
                            name={employee.name}
                            photoUrl={employee.photo_url}
                            initials={employee.initials}
                            size="lg"
                        />
                    }
                    eyebrow={
                        role === 'DSP' ? (
                            <BackLink href={dashboard()}>Back to Dashboard</BackLink>
                        ) : (
                            <BackLink href={employeesIndex()}>
                                Back to Employees
                            </BackLink>
                        )
                    }
                    title={employee.name}
                    meta={
                        <>
                            <StatusBadge
                                status={employee.employment_status}
                                label={employee.employment_status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {employee.employee_number}
                            </span>
                            <span className="text-muted-foreground text-sm">
                                Role: {employee.job_type_label}
                            </span>
                        </>
                    }
                    actions={
                        <>
                            <ProfileCompletionMeter
                                completion={profile_completion}
                                complianceLabel={complianceLabel}
                            />
                            {can.update ? (
                                <Button asChild variant="secondary">
                                    <Link href={edit(employee.id)}>Edit</Link>
                                </Button>
                            ) : null}
                            {can.manage_lifecycle ? (
                                <>
                                    {employee.employment_status !== 'active' && (
                                        <StatusForm
                                            employeeId={employee.id}
                                            employeeName={employee.name}
                                            statusValue="active"
                                            label="Return to Active"
                                            preview={lifecycle_previews?.active}
                                        />
                                    )}
                                    {(employee.employment_status === 'active' ||
                                        employee.employment_status ===
                                            'suspended') && (
                                        <>
                                            {employee.employment_status ===
                                                'active' && (
                                                <StatusForm
                                                    employeeId={employee.id}
                                                    employeeName={employee.name}
                                                    statusValue="suspended"
                                                    label="Suspend"
                                                    preview={
                                                        lifecycle_previews?.suspended
                                                    }
                                                    requireReason
                                                />
                                            )}
                                            <StatusForm
                                                employeeId={employee.id}
                                                employeeName={employee.name}
                                                statusValue="employment_ended"
                                                label="End Employment"
                                                preview={
                                                    lifecycle_previews?.employment_ended
                                                }
                                                requireReason
                                                destructive
                                            />
                                        </>
                                    )}
                                    {can.archive &&
                                        employee.employment_status !==
                                            'archived' && (
                                            <StatusForm
                                                employeeId={employee.id}
                                                employeeName={employee.name}
                                                statusValue="archived"
                                                label="Archive"
                                                preview={
                                                    lifecycle_previews?.archived
                                                }
                                                requireReason
                                                destructive
                                            />
                                        )}
                                </>
                            ) : null}
                        </>
                    }
                />

                <ModuleTabs
                    tabs={[
                        { id: 'profile', label: 'Profile' },
                        { id: 'background', label: 'Background' },
                        { id: 'credentials', label: 'Credentials' },
                        { id: 'training', label: 'Training' },
                        { id: 'compliance', label: 'Compliance' },
                        { id: 'activity', label: 'Activity' },
                        ...(can.manage_lifecycle
                            ? [{ id: 'lifecycle', label: 'Lifecycle' }]
                            : []),
                        ...(can.view_sensitive
                            ? [{ id: 'security', label: 'Security' }]
                            : []),
                    ]}
                    value={tab}
                    onChange={setTab}
                />

                {tab === 'profile' && <ProfileTab employee={employee} />}
                {tab === 'lifecycle' && can.manage_lifecycle && (
                    <LifecycleHistoryPanel
                        employee={employee}
                        history={admin_history}
                    />
                )}
                {tab === 'background' && <BackgroundTab employee={employee} />}
                {tab === 'security' && can.view_sensitive && (
                    <SecurityTab employee={employee} />
                )}
                {tab === 'compliance' && (
                    <ComplianceEligibilityPanel
                        eligibility={compliance_eligibility}
                        employeeId={employee.id}
                        canOverride={can.override_compliance}
                    />
                )}
                {tab === 'credentials' && (
                    <div className="grid gap-4">
                        <Panel title="Credentials">
                            {credentials.length === 0 ? (
                                <EmptyState message="No credentials on file." />
                            ) : (
                                <ul className="space-y-4">
                                    {credentials.map((credential) => (
                                        <li
                                            key={credential.id}
                                            className="border-border/60 border-b pb-4 last:border-0 last:pb-0"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {credential.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {credential.type}
                                                        {credential.issuer
                                                            ? ` · ${credential.issuer}`
                                                            : ''}
                                                        {credential.expires_on
                                                            ? ` · Expires ${credential.expires_on}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <StatusBadge
                                                        status={credential.status}
                                                        label={
                                                            credential.status_label
                                                        }
                                                    />
                                                    <StatusBadge
                                                        status={
                                                            credential.verification_status ??
                                                            'pending'
                                                        }
                                                        label={
                                                            credential.verification_status_label ??
                                                            'Verification'
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            {credential.verification_note && (
                                                <p className="text-muted-foreground mt-2 text-xs">
                                                    Note: {credential.verification_note}
                                                </p>
                                            )}
                                            {can.verify_compliance &&
                                                credential.pending_review && (
                                                    <EvidenceReviewForm
                                                        action={`/compliance-credentials/${credential.id}/review`}
                                                    />
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                        {can.submit_compliance_evidence && (
                            <Panel
                                title="Submit credential for review"
                                description="Pending Review does not change eligibility until Admin verifies it."
                            >
                                <Form
                                    action={`/employees/${employee.id}/compliance-evidence`}
                                    method="post"
                                    encType="multipart/form-data"
                                    className="grid gap-3 md:grid-cols-2"
                                >
                                    <input type="hidden" name="kind" value="credential" />
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Type</span>
                                        <select
                                            name="type"
                                            required
                                            className={controlClassName}
                                        >
                                            {credential_type_options.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Name</span>
                                        <Input name="name" required />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Issuer</span>
                                        <Input name="issuer" />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Expires on</span>
                                        <Input type="date" name="expires_on" />
                                    </label>
                                    <label className="grid gap-1 text-sm md:col-span-2">
                                        <span className="font-medium">Supporting document</span>
                                        <Input
                                            type="file"
                                            name="document"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        />
                                    </label>
                                    <div className="md:col-span-2">
                                        <Button type="submit">Submit for review</Button>
                                    </div>
                                </Form>
                            </Panel>
                        )}
                    </div>
                )}
                {tab === 'training' && (
                    <div className="grid gap-4">
                        <Panel title="Training">
                            {trainings.length === 0 ? (
                                <EmptyState message="No training records on file." />
                            ) : (
                                <ul className="space-y-4">
                                    {trainings.map((training) => (
                                        <li
                                            key={training.id}
                                            className="border-border/60 border-b pb-4 last:border-0 last:pb-0"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {training.title}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {training.provider ?? 'No provider'}
                                                        {training.expires_on
                                                            ? ` · Expires ${training.expires_on}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <StatusBadge
                                                        status={training.status}
                                                        label={training.status_label}
                                                    />
                                                    <StatusBadge
                                                        status={
                                                            training.verification_status ??
                                                            'pending'
                                                        }
                                                        label={
                                                            training.verification_status_label ??
                                                            'Verification'
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            {can.verify_compliance &&
                                                training.pending_review && (
                                                    <EvidenceReviewForm
                                                        action={`/compliance-trainings/${training.id}/review`}
                                                    />
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                        {can.submit_compliance_evidence && (
                            <Panel title="Submit training for review">
                                <Form
                                    action={`/employees/${employee.id}/compliance-evidence`}
                                    method="post"
                                    encType="multipart/form-data"
                                    className="grid gap-3 md:grid-cols-2"
                                >
                                    <input type="hidden" name="kind" value="training" />
                                    <label className="grid gap-1 text-sm md:col-span-2">
                                        <span className="font-medium">Title</span>
                                        <Input name="title" required />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Provider</span>
                                        <Input name="provider" />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">Expires on</span>
                                        <Input type="date" name="expires_on" />
                                    </label>
                                    <label className="grid gap-1 text-sm md:col-span-2">
                                        <span className="font-medium">Supporting document</span>
                                        <Input
                                            type="file"
                                            name="document"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        />
                                    </label>
                                    <div className="md:col-span-2">
                                        <Button type="submit">Submit for review</Button>
                                    </div>
                                </Form>
                            </Panel>
                        )}
                    </div>
                )}
                {tab === 'activity' && (
                    <Panel title="Activity">
                        {activity.length === 0 ? (
                            <EmptyState message="No recorded activity." />
                        ) : (
                            <ul className="space-y-3">
                                {activity.map((item) => (
                                    <li key={item.id}>
                                        <p className="text-sm font-medium">{item.title}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {item.detail} · {item.occurred_on}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                )}
            </div>
        </>
    );
}

function ProfileTab({ employee }: { employee: EmployeeDetail }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="Contact">
                <dl className="grid gap-3 text-sm">
                    <Item label="Email" value={employee.email} />
                    <Item label="Phone" value={employee.phone} />
                    <Item
                        label="Address"
                        value={[
                            employee.address_line_1,
                            employee.address_line_2,
                            [employee.city, employee.state, employee.postal_code]
                                .filter(Boolean)
                                .join(', '),
                        ]
                            .filter(Boolean)
                            .join(', ')}
                    />
                    <Item label="Date of birth" value={employee.date_of_birth} />
                </dl>
            </Panel>
            <Panel title="Employment">
                <dl className="grid gap-3 text-sm">
                    <Item label="Supervisor" value={employee.supervisor_name} />
                    <Item label="Hired on" value={employee.hired_on} />
                    <Item label="Terminated on" value={employee.terminated_on} />
                    <Item
                        label="Login"
                        value={
                            employee.has_login
                                ? `${employee.login_email} (${employee.login_role})`
                                : 'No login account'
                        }
                    />
                    <Item label="Notes" value={employee.notes} />
                </dl>
            </Panel>
            <Panel title="Emergency contact" className="lg:col-span-2">
                <dl className="grid gap-3 text-sm md:grid-cols-3">
                    <Item label="Name" value={employee.emergency_contact_name} />
                    <Item
                        label="Relationship"
                        value={employee.emergency_contact_relationship}
                    />
                    <Item label="Phone" value={employee.emergency_contact_phone} />
                </dl>
            </Panel>
        </div>
    );
}

function BackgroundTab({ employee }: { employee: EmployeeDetail }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="Education">
                {(employee.educations ?? []).length === 0 ? (
                    <EmptyState message="No education records." />
                ) : (
                    <ul className="space-y-2 text-sm">
                        {(employee.educations ?? []).map((row, index) => (
                            <li key={index}>
                                <p className="font-medium">
                                    {row.institution_name || 'School'}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {row.level === 'college' ? 'College' : 'High School'}
                                    {row.degree ? ` · ${row.degree}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel title="References">
                {(employee.references ?? []).length === 0 ? (
                    <EmptyState message="No references on file." />
                ) : (
                    <ul className="space-y-2 text-sm">
                        {(employee.references ?? []).map((row, index) => (
                            <li key={index}>
                                <p className="font-medium">{row.name}</p>
                                <p className="text-muted-foreground text-xs">
                                    {row.relationship} · {row.home_phone}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel title="Work history" className="lg:col-span-2">
                {(employee.work_histories ?? []).length === 0 ? (
                    <EmptyState message="No work history on file." />
                ) : (
                    <ul className="space-y-2 text-sm">
                        {(employee.work_histories ?? []).map((row, index) => (
                            <li key={index}>
                                <p className="font-medium">
                                    {row.job_title} · {row.employer}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {row.started_on} – {row.ended_on ?? 'Present'}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}

function SecurityTab({ employee }: { employee: EmployeeDetail }) {
    return (
        <Panel title="Security / background">
            <dl className="grid gap-3 text-sm md:grid-cols-2">
                <Item
                    label="Ohio resident 5 years"
                    value={
                        employee.ohio_resident_5_years === null
                            ? null
                            : employee.ohio_resident_5_years
                              ? 'Yes'
                              : 'No'
                    }
                />
                <Item label="Residence history" value={employee.residence_history} />
                <Item label="SSN" value={employee.ssn_masked} />
                <Item
                    label="Conviction disclosure"
                    value={
                        employee.has_conviction === null
                            ? null
                            : employee.has_conviction
                              ? 'Yes'
                              : 'No'
                    }
                />
                <Item label="Comments" value={employee.security_comments} />
            </dl>
        </Panel>
    );
}

function EvidenceReviewForm({ action }: { action: string }) {
    return (
        <Form
            action={action}
            method="post"
            className="mt-3 grid gap-2 md:grid-cols-[1fr_auto_auto]"
        >
            <textarea
                name="note"
                placeholder="Review note (required to reject)"
                className={`${controlClassName} min-h-16`}
            />
            <Button
                type="submit"
                name="decision"
                value="verify"
                size="sm"
            >
                Verify
            </Button>
            <Button
                type="submit"
                name="decision"
                value="reject"
                size="sm"
                variant="secondary"
            >
                Reject
            </Button>
        </Form>
    );
}

function Item({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs uppercase">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

function RecordTable({
    rows,
}: {
    rows: Array<{
        id: number;
        title: string;
        detail: string;
        status: string;
        statusLabel: string;
        extra: string;
    }>;
}) {
    return (
        <ul className="space-y-3">
            {rows.map((row) => (
                <li key={row.id} className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium">{row.title}</p>
                        <p className="text-muted-foreground text-xs">
                            {row.detail} · {row.extra}
                        </p>
                    </div>
                    <StatusBadge status={row.status} label={row.statusLabel} />
                </li>
            ))}
        </ul>
    );
}

function LifecycleHistoryPanel({
    employee,
    history,
}: {
    employee: EmployeeDetail;
    history: AdminHistoryRecord[];
}) {
    return (
        <div className="grid gap-4">
            <Panel title="Lifecycle status">
                <dl className="grid gap-3 sm:grid-cols-2">
                    <Item
                        label="Status"
                        value={employee.employment_status_label}
                    />
                    <Item label="Reason" value={employee.status_reason} />
                    <Item
                        label="Changed by"
                        value={employee.status_changed_by}
                    />
                    <Item
                        label="Employment ended on"
                        value={employee.terminated_on}
                    />
                </dl>
                <p className="text-muted-foreground mt-3 text-sm">
                    Suspension is not account deactivation. Employment Ended is
                    not Archive. Leave and compliance remain separate
                    dimensions.
                </p>
            </Panel>
            <Panel title="Lifecycle audit history">
                {history.length === 0 ? (
                    <EmptyState message="No lifecycle history recorded yet." />
                ) : (
                    <ul className="space-y-3">
                        {history.map((event) => (
                            <li
                                key={event.id}
                                className="border-border/60 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <p className="text-sm font-medium">
                                    {event.summary}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {event.category_label}
                                    {event.old_value && event.new_value
                                        ? ` · ${event.old_value} → ${event.new_value}`
                                        : ''}
                                    {event.actor_name
                                        ? ` · ${event.actor_name}`
                                        : ''}
                                    {event.occurred_at_label
                                        ? ` · ${event.occurred_at_label}`
                                        : ''}
                                </p>
                                {event.reason ? (
                                    <p className="mt-1 text-sm">{event.reason}</p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}

function StatusForm({
    employeeId,
    employeeName,
    statusValue,
    label,
    preview,
    requireReason = false,
    destructive = false,
}: {
    employeeId: number;
    employeeName: string;
    statusValue:
        | 'active'
        | 'suspended'
        | 'employment_ended'
        | 'archived';
    label: string;
    preview?: LifecyclePreview | null;
    requireReason?: boolean;
    destructive?: boolean;
}) {
    const impactLines = [
        preview
            ? `Effective ${preview.effective_on}. ${preview.future_scheduled_count} future scheduled visit(s) affected.`
            : null,
        preview?.will_release_to_staffing_needed
            ? 'Affected future assignments will move to Staffing Needed; history is preserved.'
            : null,
        preview?.active_visit?.warning ?? null,
        preview?.login_consequence ?? null,
        ...(preview?.consequences ?? []),
    ].filter(Boolean);

    return (
        <ConfirmAction
            triggerLabel={label}
            triggerVariant={destructive ? 'destructive' : 'outline'}
            title={`${label} ${employeeName}?`}
            description={
                impactLines.length > 0
                    ? impactLines.join(' ')
                    : `Update lifecycle status for ${employeeName}. Historical visits and attendance stay in MDM.`
            }
            confirmLabel={label}
            destructive={destructive}
        >
            <Form
                action={status.url(employeeId)}
                method="patch"
                className="space-y-3"
            >
                <input
                    type="hidden"
                    name="employment_status"
                    value={statusValue}
                />
                {statusValue === 'employment_ended' && (
                    <input
                        type="hidden"
                        name="terminated_on"
                        value={new Date().toISOString().slice(0, 10)}
                    />
                )}
                {requireReason ? (
                    <div>
                        <label
                            className="text-sm font-medium"
                            htmlFor={`lifecycle-reason-${statusValue}`}
                        >
                            Reason
                        </label>
                        <textarea
                            id={`lifecycle-reason-${statusValue}`}
                            name="reason"
                            required
                            rows={2}
                            className={`${controlClassName} mt-1 h-auto py-2`}
                        />
                    </div>
                ) : null}
                <Button
                    type="submit"
                    variant={destructive ? 'destructive' : 'default'}
                >
                    {label}
                </Button>
            </Form>
        </ConfirmAction>
    );
}

EmployeesShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeesIndex() },
        { title: 'Employee', href: show.url(0) },
    ],
};

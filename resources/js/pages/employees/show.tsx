import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/mdm/confirm-action';
import { ModuleTabs, StatusBadge } from '@/components/mdm/directory';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { ProfileCompletionMeter } from '@/components/mdm/profile-completion-meter';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    edit,
    index as employeesIndex,
    show,
    status,
} from '@/routes/employees';
import type {
    ActivityRecord,
    CredentialRecord,
    EmployeeDetail,
    ProfileCompletion,
    TrainingRecord,
} from '@/types/directory';

export default function EmployeesShow({
    employee,
    credentials,
    trainings,
    activity,
    profile_completion,
    can,
}: {
    employee: EmployeeDetail;
    credentials: CredentialRecord[];
    trainings: TrainingRecord[];
    activity: ActivityRecord[];
    profile_completion: ProfileCompletion;
    can: { update: boolean; view_sensitive: boolean };
}) {
    const [tab, setTab] = useState('profile');
    const expiredCredentials = credentials.filter(
        (item) => item.status === 'expired' || item.status === 'revoked',
    ).length;
    const complianceLabel =
        expiredCredentials > 0 ? 'Attention required' : 'Tracked separately';

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
                    eyebrow={employee.employee_number}
                    title={employee.name}
                    meta={
                        <>
                            <StatusBadge
                                status={employee.employment_status}
                                label={employee.employment_status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {employee.job_title ?? employee.job_type_label}
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
                            <>
                                <Button asChild variant="secondary">
                                    <Link href={edit(employee.id)}>Edit</Link>
                                </Button>
                                {employee.employment_status !== 'active' && (
                                    <StatusForm
                                        employeeId={employee.id}
                                        employeeName={employee.name}
                                        statusValue="active"
                                        label="Activate"
                                    />
                                )}
                                {employee.employment_status === 'active' && (
                                    <StatusForm
                                        employeeId={employee.id}
                                        employeeName={employee.name}
                                        statusValue="inactive"
                                        label="Inactivate"
                                    />
                                )}
                                {employee.employment_status !== 'terminated' && (
                                    <StatusForm
                                        employeeId={employee.id}
                                        employeeName={employee.name}
                                        statusValue="terminated"
                                        label="Terminate"
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
                        { id: 'activity', label: 'Activity' },
                        ...(can.view_sensitive
                            ? [{ id: 'security', label: 'Security' }]
                            : []),
                    ]}
                    value={tab}
                    onChange={setTab}
                />

                {tab === 'profile' && <ProfileTab employee={employee} />}
                {tab === 'background' && <BackgroundTab employee={employee} />}
                {tab === 'security' && can.view_sensitive && (
                    <SecurityTab employee={employee} />
                )}
                {tab === 'credentials' && (
                    <Panel title="Credentials">
                        {credentials.length === 0 ? (
                            <EmptyState message="No credentials on file." />
                        ) : (
                            <RecordTable
                                rows={credentials.map((credential) => ({
                                    id: credential.id,
                                    title: credential.name,
                                    detail: `${credential.type}${credential.issuer ? ` · ${credential.issuer}` : ''}`,
                                    status: credential.status,
                                    statusLabel: credential.status_label,
                                    extra: credential.expires_on
                                        ? `Expires ${credential.expires_on}`
                                        : 'No expiry',
                                }))}
                            />
                        )}
                    </Panel>
                )}
                {tab === 'training' && (
                    <Panel title="Training">
                        {trainings.length === 0 ? (
                            <EmptyState message="No training records on file." />
                        ) : (
                            <RecordTable
                                rows={trainings.map((training) => ({
                                    id: training.id,
                                    title: training.title,
                                    detail: training.provider ?? 'No provider',
                                    status: training.status,
                                    statusLabel: training.status_label,
                                    extra: training.completed_on
                                        ? `Completed ${training.completed_on}`
                                        : 'Not completed',
                                }))}
                            />
                        )}
                    </Panel>
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

function StatusForm({
    employeeId,
    employeeName,
    statusValue,
    label,
    destructive = false,
}: {
    employeeId: number;
    employeeName: string;
    statusValue: 'active' | 'inactive' | 'terminated';
    label: string;
    destructive?: boolean;
}) {
    const description =
        statusValue === 'terminated'
            ? `This will terminate ${employeeName}. Records stay in MDM, but they will no longer be able to sign in or start visits.`
            : statusValue === 'inactive'
              ? `This will inactivate ${employeeName}. They will no longer appear as available for scheduling.`
              : `This will set ${employeeName} back to active.`;

    return (
        <ConfirmAction
            triggerLabel={label}
            triggerVariant={destructive ? 'destructive' : 'outline'}
            title={`${label} ${employeeName}?`}
            description={description}
            confirmLabel={label}
            destructive={destructive || statusValue === 'inactive'}
        >
            <Form action={status.url(employeeId)} method="patch">
                <input
                    type="hidden"
                    name="employment_status"
                    value={statusValue}
                />
                {statusValue === 'terminated' && (
                    <input
                        type="hidden"
                        name="terminated_on"
                        value={new Date().toISOString().slice(0, 10)}
                    />
                )}
                <Button
                    type="submit"
                    variant={
                        destructive || statusValue === 'inactive'
                            ? 'destructive'
                            : 'default'
                    }
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

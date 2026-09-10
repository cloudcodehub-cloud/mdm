import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    Field,
    ModuleTabs,
    StatusBadge,
    controlClassName,
} from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { deactivate } from '@/routes/assignments';
import {
    edit,
    index as clientsIndex,
    show,
    status,
} from '@/routes/clients';
import { store as storeAssignment } from '@/routes/clients/assignments';
import { show as showVisit } from '@/routes/scheduled-visits';
import type {
    AssignmentRecord,
    AuthorizationRecord,
    CarePlanRecord,
    ClientDetail,
    OptionItem,
    VisitRecord,
} from '@/types/directory';

export default function ClientsShow({
    client,
    authorizations,
    carePlans,
    assignments,
    scheduledVisits,
    dspOptions,
    can,
}: {
    client: ClientDetail;
    authorizations: AuthorizationRecord[];
    carePlans: CarePlanRecord[];
    assignments: AssignmentRecord[];
    scheduledVisits: VisitRecord[];
    dspOptions: OptionItem[];
    can: { update: boolean; manageAssignments: boolean };
}) {
    const [tab, setTab] = useState('profile');
    const currentAssignments = assignments.filter((assignment) => assignment.is_active);
    const historicalAssignments = assignments.filter((assignment) => !assignment.is_active);

    return (
        <>
            <Head title={client.name} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            {client.client_number}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {client.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge status={client.status} label={client.status_label} />
                            <span className="text-muted-foreground text-sm">
                                Supervisor: {client.supervisor_name ?? 'Unassigned'}
                            </span>
                        </div>
                    </div>
                    {can.update && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="secondary">
                                <Link href={edit(client.id)}>Edit</Link>
                            </Button>
                            {client.status !== 'active' && (
                                <StatusForm clientId={client.id} statusValue="active" label="Activate" />
                            )}
                            {client.status === 'active' && (
                                <StatusForm clientId={client.id} statusValue="inactive" label="Set inactive" />
                            )}
                        </div>
                    )}
                </div>

                <ModuleTabs
                    tabs={[
                        { id: 'profile', label: 'Profile' },
                        { id: 'authorizations', label: 'Authorizations' },
                        { id: 'care-plan', label: 'Care Plan' },
                        { id: 'assigned-dsps', label: 'Assigned DSPs' },
                        { id: 'scheduled-visits', label: 'Scheduled Visits' },
                    ]}
                    value={tab}
                    onChange={setTab}
                />

                {tab === 'profile' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel title="Contact">
                            <dl className="grid gap-3 text-sm">
                                <Item label="Email" value={client.email} />
                                <Item label="Phone" value={client.phone} />
                                <Item label="Date of birth" value={client.date_of_birth} />
                                <Item
                                    label="Address"
                                    value={[
                                        client.address_line_1,
                                        client.address_line_2,
                                        [client.city, client.state, client.postal_code]
                                            .filter(Boolean)
                                            .join(', '),
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                            </dl>
                        </Panel>
                        <Panel title="Emergency contact">
                            <dl className="grid gap-3 text-sm">
                                <Item label="Name" value={client.emergency_contact_name} />
                                <Item
                                    label="Relationship"
                                    value={client.emergency_contact_relationship}
                                />
                                <Item label="Phone" value={client.emergency_contact_phone} />
                                <Item label="Notes" value={client.notes} />
                            </dl>
                        </Panel>
                    </div>
                )}

                {tab === 'authorizations' && (
                    <Panel title="Authorizations">
                        {authorizations.length === 0 ? (
                            <EmptyState message="No authorizations on file." />
                        ) : (
                            <ul className="space-y-3">
                                {authorizations.map((authorization) => (
                                    <li key={authorization.id} className="flex justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {authorization.authorization_number}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {authorization.payer} · {authorization.service_type}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={authorization.status}
                                            label={authorization.status_label}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                )}

                {tab === 'care-plan' && (
                    <div className="space-y-4">
                        {carePlans.length === 0 ? (
                            <Panel title="Care Plan">
                                <EmptyState message="No care plans on file." />
                            </Panel>
                        ) : (
                            carePlans.map((plan) => (
                                <Panel
                                    key={plan.id}
                                    title={plan.title}
                                    description={`${plan.status_label} · ${plan.starts_on ?? 'No start'}`}
                                >
                                    {plan.tasks.length === 0 ? (
                                        <EmptyState message="No task templates on this plan." />
                                    ) : (
                                        <ul className="space-y-2 text-sm">
                                            {plan.tasks.map((task) => (
                                                <li key={task.id}>
                                                    {task.title}
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · {task.recurrence}
                                                        {task.is_required ? ' · required' : ''}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Panel>
                            ))
                        )}
                    </div>
                )}

                {tab === 'assigned-dsps' && (
                    <div className="space-y-4">
                        {can.manageAssignments && (
                            <Panel title="Assign DSP">
                                <Form
                                    action={storeAssignment.url(client.id)}
                                    method="post"
                                    className="grid gap-3 md:grid-cols-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Field label="DSP" htmlFor="employee_id" error={errors.employee_id}>
                                                <select
                                                    id="employee_id"
                                                    name="employee_id"
                                                    required
                                                    className={controlClassName}
                                                >
                                                    <option value="">Select DSP</option>
                                                    {dspOptions.map((dsp) => (
                                                        <option key={dsp.id} value={dsp.id}>
                                                            {dsp.name}
                                                            {dsp.employee_number
                                                                ? ` (${dsp.employee_number})`
                                                                : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </Field>
                                            <Field label="Started on" htmlFor="started_on" error={errors.started_on}>
                                                <Input
                                                    id="started_on"
                                                    name="started_on"
                                                    type="date"
                                                    required
                                                    defaultValue={new Date().toISOString().slice(0, 10)}
                                                />
                                            </Field>
                                            <Field label="Notes" htmlFor="notes" error={errors.notes}>
                                                <Input id="notes" name="notes" />
                                            </Field>
                                            <div className="flex items-end">
                                                <Button type="submit" disabled={processing}>
                                                    Assign DSP
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </Panel>
                        )}

                        <Panel title="Current assignments">
                            {currentAssignments.length === 0 ? (
                                <EmptyState message="No active DSP assignments." />
                            ) : (
                                <AssignmentList
                                    assignments={currentAssignments}
                                    canManage={can.manageAssignments}
                                />
                            )}
                        </Panel>

                        <Panel title="Assignment history">
                            {historicalAssignments.length === 0 ? (
                                <EmptyState message="No previous assignments." />
                            ) : (
                                <AssignmentList
                                    assignments={historicalAssignments}
                                    canManage={false}
                                />
                            )}
                        </Panel>
                    </div>
                )}

                {tab === 'scheduled-visits' && (
                    <Panel title="Scheduled visits">
                        {scheduledVisits.length === 0 ? (
                            <EmptyState message="No scheduled visits on file." />
                        ) : (
                            <ul className="space-y-3">
                                {scheduledVisits.map((visit) => (
                                    <li key={visit.id} className="flex justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                <Link
                                                    href={showVisit(visit.id)}
                                                    className="hover:text-foreground"
                                                >
                                                    {visit.service_type}
                                                </Link>
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {visit.service_date} · {visit.time_label} · {visit.dsp_name}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={visit.status}
                                            label={visit.status_label}
                                        />
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

function AssignmentList({
    assignments,
    canManage,
}: {
    assignments: AssignmentRecord[];
    canManage: boolean;
}) {
    return (
        <ul className="space-y-3">
            {assignments.map((assignment) => (
                <li key={assignment.id} className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium">{assignment.dsp_name}</p>
                        <p className="text-muted-foreground text-xs">
                            {assignment.dsp_number} · {assignment.started_on}
                            {assignment.ended_on ? ` to ${assignment.ended_on}` : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge
                            status={assignment.status}
                            label={assignment.status_label}
                        />
                        {canManage && assignment.is_active && (
                            <Form action={deactivate.url(assignment.id)} method="patch">
                                <Button type="submit" variant="outline" size="sm">
                                    Deactivate
                                </Button>
                            </Form>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function StatusForm({
    clientId,
    statusValue,
    label,
}: {
    clientId: number;
    statusValue: 'active' | 'inactive';
    label: string;
}) {
    return (
        <Form action={status.url(clientId)} method="patch">
            <input type="hidden" name="status" value={statusValue} />
            <Button type="submit" variant="outline">
                {label}
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

ClientsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
        { title: 'Client', href: show.url(0) },
    ],
};

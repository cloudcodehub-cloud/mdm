import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/mdm/confirm-action';
import {
    Field,
    ModuleTabs,
    StatusBadge,
    controlClassName,
} from '@/components/mdm/directory';
import { IdentityHeader } from '@/components/mdm/identity-header';
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
import { show as showVisit } from '@/routes/visits';
import { show as showScheduledVisit } from '@/routes/scheduled-visits';
import type { DashboardActiveVisit } from '@/types/dashboard';
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
    today_visit = null,
    dspOptions,
    can,
}: {
    client: ClientDetail;
    authorizations: AuthorizationRecord[];
    carePlans: CarePlanRecord[];
    assignments: AssignmentRecord[];
    scheduledVisits: VisitRecord[];
    today_visit?: (VisitRecord & {
        can_start?: boolean;
        active_visit_id?: number | null;
    }) | null;
    dspOptions: OptionItem[];
    can: { update: boolean; manageAssignments: boolean };
}) {
    const [tab, setTab] = useState('profile');
    const role = usePage().props.auth.user.role;
    const activeWork = usePage().props.activeWork as DashboardActiveVisit | null;
    const currentAssignments = assignments.filter((assignment) => assignment.is_active);
    const historicalAssignments = assignments.filter((assignment) => !assignment.is_active);
    const isDsp = role === 'DSP';
    const activeForThisClient =
        activeWork && activeWork.client.id === client.id ? activeWork : null;

    return (
        <>
            <Head title={client.name} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <IdentityHeader
                    eyebrow={client.client_number}
                    title={client.name}
                    meta={
                        <>
                            <StatusBadge
                                status={client.status}
                                label={client.status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                Supervisor:{' '}
                                {client.supervisor_name ?? 'Unassigned'}
                            </span>
                            {isDsp && currentAssignments[0] && (
                                <span className="text-muted-foreground text-sm">
                                    Assigned DSP coverage
                                </span>
                            )}
                            {activeForThisClient && (
                                <span className="text-primary text-sm font-medium">
                                    Visit in progress
                                </span>
                            )}
                        </>
                    }
                    actions={
                        can.update ? (
                            <>
                                <Button asChild variant="secondary">
                                    <Link href={edit(client.id)}>Edit</Link>
                                </Button>
                                {client.status !== 'active' && (
                                    <StatusForm
                                        clientId={client.id}
                                        clientName={client.name}
                                        statusValue="active"
                                        label="Activate"
                                    />
                                )}
                                {client.status === 'active' && (
                                    <StatusForm
                                        clientId={client.id}
                                        clientName={client.name}
                                        statusValue="inactive"
                                        label="Set inactive"
                                    />
                                )}
                            </>
                        ) : undefined
                    }
                />

                {isDsp && (today_visit || activeForThisClient) && (
                    <TodayVisitCard
                        todayVisit={today_visit}
                        activeVisit={activeForThisClient}
                    />
                )}

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

function TodayVisitCard({
    todayVisit,
    activeVisit,
}: {
    todayVisit: (VisitRecord & {
        can_start?: boolean;
        active_visit_id?: number | null;
    }) | null;
    activeVisit: DashboardActiveVisit | null;
}) {
    const visit = todayVisit;
    const continueVisitId = activeVisit?.id ?? visit?.active_visit_id ?? null;
    const action = activeVisit
        ? {
              href: showVisit(activeVisit.id),
              label: 'Continue Visit',
          }
        : visit?.can_start
          ? {
                href: showScheduledVisit(visit.id),
                label: 'Start Visit',
            }
          : continueVisitId
            ? {
                  href: showVisit(continueVisitId),
                  label: "View Today's Visit",
              }
            : visit
              ? {
                    href: showScheduledVisit(visit.id),
                    label: "View Today's Visit",
                }
              : null;

    return (
        <section className="surface-panel border-module-accent/25 from-brand-coral/12 via-card to-brand-peach/10 bg-gradient-to-br p-4 md:p-5">
            <p className="text-primary text-xs font-medium tracking-wide uppercase">
                Today's Visit
            </p>
            <div className="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-xs">Time</dt>
                        <dd className="font-medium">
                            {visit?.time_label ?? 'In progress'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Service
                        </dt>
                        <dd>
                            {visit?.service_type ??
                                activeVisit?.service_type ??
                                '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Supervisor
                        </dt>
                        <dd>{visit?.supervisor_name ?? 'Unassigned'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">Status</dt>
                        <dd>
                            {visit ? (
                                <StatusBadge
                                    status={visit.status}
                                    label={visit.status_label}
                                />
                            ) : (
                                <span className="text-sm font-medium">
                                    Visit in progress
                                </span>
                            )}
                        </dd>
                    </div>
                </dl>
                {action ? (
                    <Button className="min-h-11 w-full sm:w-auto" asChild>
                        <Link href={action.href}>{action.label}</Link>
                    </Button>
                ) : null}
            </div>
        </section>
    );
}

function StatusForm({
    clientId,
    clientName,
    statusValue,
    label,
}: {
    clientId: number;
    clientName: string;
    statusValue: 'active' | 'inactive';
    label: string;
}) {
    const inactivate = statusValue === 'inactive';

    return (
        <ConfirmAction
            triggerLabel={label}
            triggerVariant={inactivate ? 'destructive' : 'outline'}
            title={`${label} ${clientName}?`}
            description={
                inactivate
                    ? `This will inactivate ${clientName}. Historical visits, assignments, and care records stay in MDM.`
                    : `This will set ${clientName} back to active.`
            }
            confirmLabel={label}
            destructive={inactivate}
        >
            <Form action={status.url(clientId)} method="patch">
                <input type="hidden" name="status" value={statusValue} />
                <Button
                    type="submit"
                    variant={inactivate ? 'destructive' : 'default'}
                >
                    {label}
                </Button>
            </Form>
        </ConfirmAction>
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

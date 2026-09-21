import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { OperationsBoardView } from '@/components/mdm/operations-board';
import { SupervisorManagement } from '@/components/mdm/supervisor-management';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as showEmployee } from '@/routes/employees';
import { index as supervisorsIndex, show } from '@/routes/supervisors';
import type {
    EmployeeDetail,
    OptionItem,
    SupervisorResponsibilities,
    SupervisorRoleEvent,
} from '@/types/directory';
import type { OperationsBoard } from '@/types/operations';

export default function SupervisorsShow({
    supervisor,
    operations,
    responsibilities,
    replacements,
    role_change_history,
    can,
}: {
    supervisor: EmployeeDetail & {
        assigned_dsp_count: number;
        assigned_client_count: number;
    };
    operations: OperationsBoard;
    responsibilities: SupervisorResponsibilities | null;
    replacements: OptionItem[];
    role_change_history: SupervisorRoleEvent[];
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title={supervisor.name} />
            <div className="flex flex-1 flex-col gap-5 pb-6">
                <div className="px-4 pt-4 md:px-6">
                    <IdentityHeader
                        leading={
                            <ProfilePhoto
                                name={supervisor.name}
                                photoUrl={supervisor.photo_url}
                                initials={supervisor.initials}
                                size="lg"
                            />
                        }
                        eyebrow={
                            <Link
                                href={supervisorsIndex()}
                                className="hover:text-foreground"
                            >
                                Supervisors
                            </Link>
                        }
                        title={supervisor.name}
                        meta={
                            <>
                                <StatusBadge
                                    status={supervisor.employment_status}
                                    label={supervisor.employment_status_label}
                                />
                                <span className="text-muted-foreground text-sm">
                                    {supervisor.assigned_dsp_count} DSPs ·{' '}
                                    {supervisor.assigned_client_count} clients
                                </span>
                            </>
                        }
                        actions={
                            <Button asChild variant="secondary">
                                <Link href={showEmployee.url(supervisor.id)}>
                                    Employee record
                                </Link>
                            </Button>
                        }
                    />
                </div>

                <div className="grid gap-4 px-4 md:grid-cols-12 md:px-6">
                    <Panel title="Identity" className="md:col-span-6">
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Employee number
                                </dt>
                                <dd>{supervisor.employee_number}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Email</dt>
                                <dd>{supervisor.email ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    System Role
                                </dt>
                                <dd>
                                    {supervisor.login_role ??
                                        supervisor.job_type_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Job title
                                </dt>
                                <dd>{supervisor.job_title ?? '—'}</dd>
                            </div>
                        </dl>
                    </Panel>
                    <Panel title="Workload" className="md:col-span-6">
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Assigned DSPs
                                </dt>
                                <dd>{supervisor.assigned_dsp_count}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Assigned clients
                                </dt>
                                <dd>{supervisor.assigned_client_count}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Visits today
                                </dt>
                                <dd>{operations.today_visits.length}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Open exceptions
                                </dt>
                                <dd>{operations.exceptions.open.length}</dd>
                            </div>
                        </dl>
                    </Panel>
                </div>

                {can.manage && responsibilities && (
                    <SupervisorManagement
                        supervisorId={supervisor.id}
                        responsibilities={responsibilities}
                        replacements={replacements}
                        history={role_change_history}
                    />
                )}

                <OperationsBoardView
                    operations={operations}
                    heading="Caseload"
                    description={`Operational board for ${supervisor.name} on ${operations.today} (${operations.timezone_label}).`}
                />
            </div>
        </>
    );
}

SupervisorsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Supervisors', href: supervisorsIndex() },
        { title: 'Supervisor', href: show.url(0) },
    ],
};

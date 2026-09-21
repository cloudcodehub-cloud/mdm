import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { create, index as supervisorsIndex, show } from '@/routes/supervisors';
import type { EmployeeSummary, Paginated } from '@/types/directory';

type SupervisorDirectoryRow = EmployeeSummary & {
    assigned_dsp_count: number;
    assigned_client_count: number;
    visits_today: number;
    active_visits: number;
    open_exceptions: number;
};

export default function SupervisorsIndex({
    supervisors,
    filters,
    can,
}: {
    supervisors: Paginated<SupervisorDirectoryRow>;
    filters: { search: string; employment_status: string };
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title="Supervisors" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Supervisors
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Appoint Supervisors from existing employees. Job
                            title stays separate from System Role.
                        </p>
                    </div>
                    {can.manage && (
                        <Button asChild>
                            <Link href={create()}>Add Supervisor</Link>
                        </Button>
                    )}
                </div>

                <Panel title="Search and filters">
                    <Form
                        action={supervisorsIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-3"
                    >
                        <Input
                            name="search"
                            placeholder="Search name, number, or email"
                            defaultValue={filters.search}
                        />
                        <select
                            name="employment_status"
                            defaultValue={filters.employment_status}
                            className={controlClassName}
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                <Panel title="Supervisor directory">
                    {supervisors.data.length === 0 ? (
                        <EmptyState message="No supervisors match these filters." />
                    ) : (
                        <div className="space-y-3">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="pb-2 font-medium">
                                                Supervisor
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Status
                                            </th>
                                            <th className="pb-2 font-medium">
                                                DSPs
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Clients
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Today
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Open exceptions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {supervisors.data.map((supervisor) => (
                                            <tr
                                                key={supervisor.id}
                                                className="hover:bg-muted/40 cursor-pointer border-t"
                                                onClick={() =>
                                                    router.visit(
                                                        show.url(supervisor.id),
                                                    )
                                                }
                                            >
                                                <td className="py-3">
                                                    <p className="font-medium">
                                                        {supervisor.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {
                                                            supervisor.employee_number
                                                        }
                                                        {supervisor.job_title
                                                            ? ` · ${supervisor.job_title}`
                                                            : ''}
                                                    </p>
                                                </td>
                                                <td className="py-3">
                                                    <StatusBadge
                                                        status={
                                                            supervisor.employment_status
                                                        }
                                                        label={
                                                            supervisor.employment_status_label
                                                        }
                                                    />
                                                </td>
                                                <td className="py-3">
                                                    {supervisor.assigned_dsp_count}
                                                </td>
                                                <td className="py-3">
                                                    {
                                                        supervisor.assigned_client_count
                                                    }
                                                </td>
                                                <td className="py-3">
                                                    {supervisor.visits_today}{' '}
                                                    visits
                                                    {supervisor.active_visits >
                                                    0
                                                        ? ` · ${supervisor.active_visits} active`
                                                        : ''}
                                                </td>
                                                <td className="py-3">
                                                    {
                                                        supervisor.open_exceptions
                                                    }
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                meta={supervisors.meta}
                                links={supervisors.links}
                            />
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

SupervisorsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Supervisors', href: supervisorsIndex() },
    ],
};

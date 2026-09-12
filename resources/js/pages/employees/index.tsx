import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    create,
    index as employeesIndex,
    show,
} from '@/routes/employees';
import type { EmployeeSummary, Paginated } from '@/types/directory';

export default function EmployeesIndex({
    employees,
    filters,
    can,
}: {
    employees: Paginated<EmployeeSummary>;
    filters: {
        search: string;
        employment_status: string;
        job_type: string;
    };
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Employees
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Workforce profiles, employment status, and supervisor assignment.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={create()}>Add Employee</Link>
                        </Button>
                    )}
                </div>

                <Panel title="Search and filters">
                    <Form
                        action={employeesIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-4"
                    >
                        <Input
                            name="search"
                            placeholder="Search name, number, or email"
                            defaultValue={filters.search}
                        />
                        <select
                            name="job_type"
                            defaultValue={filters.job_type}
                            className={controlClassName}
                        >
                            <option value="">All job types</option>
                            <option value="dsp">DSP</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                            <option value="other">Other</option>
                        </select>
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

                <Panel title="Employee directory">
                    {employees.data.length === 0 ? (
                        <EmptyState message="No employees match these filters." />
                    ) : (
                        <div className="space-y-3">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="pb-2 font-medium">Employee</th>
                                            <th className="pb-2 font-medium">Role</th>
                                            <th className="pb-2 font-medium">Supervisor</th>
                                            <th className="pb-2 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.data.map((employee) => (
                                            <tr
                                                key={employee.id}
                                                className="hover:bg-muted/40 cursor-pointer border-t"
                                                onClick={() =>
                                                    router.visit(show.url(employee.id))
                                                }
                                            >
                                                <td className="py-3">
                                                    <div className="flex items-center gap-2">
                                                        <ProfilePhoto
                                                            name={employee.name}
                                                            photoUrl={employee.photo_url}
                                                            initials={employee.initials}
                                                            size="sm"
                                                        />
                                                        <div>
                                                            <p className="font-medium">{employee.name}</p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {employee.employee_number}
                                                                {employee.email ? ` · ${employee.email}` : ''}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-3">
                                                    {employee.job_title ?? employee.job_type_label}
                                                </td>
                                                <td className="py-3">
                                                    {employee.supervisor_name ?? 'Unassigned'}
                                                </td>
                                                <td className="py-3">
                                                    <StatusBadge
                                                        status={employee.employment_status}
                                                        label={employee.employment_status_label}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination meta={employees.meta} links={employees.links} />
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeesIndex() },
    ],
};

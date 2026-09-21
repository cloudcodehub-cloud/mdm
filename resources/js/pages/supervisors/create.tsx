import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Pagination, StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { dashboard } from '@/routes';
import {
    create,
    index as supervisorsIndex,
    store,
} from '@/routes/supervisors';
import type { Paginated, SupervisorCandidate } from '@/types/directory';

export default function SupervisorsCreate({
    employees,
    filters,
    selected,
}: {
    employees: Paginated<SupervisorCandidate>;
    filters: { search: string; employee_id: string };
    selected: SupervisorCandidate | null;
}) {
    const page = usePage();
    const errors = page.props.errors as Record<string, string>;

    return (
        <>
            <Head title="Add Supervisor" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={supervisorsIndex()}
                            className="hover:text-foreground"
                        >
                            Supervisors
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add Supervisor
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Appoint an existing active DSP employee. This changes
                        System Role only — Job Title is not updated.
                    </p>
                </div>

                <Panel title="Search employee">
                    <Form
                        action={create.url()}
                        method="get"
                        className="flex flex-col gap-3 md:flex-row"
                    >
                        <Input
                            name="search"
                            placeholder="Search name, number, or email"
                            defaultValue={filters.search}
                            className="md:max-w-sm"
                        />
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </Form>
                </Panel>

                <Panel title="Eligible employees">
                    {employees.data.length === 0 ? (
                        <EmptyState message="No eligible active DSP employees match this search." />
                    ) : (
                        <div className="space-y-3">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="pb-2 font-medium">
                                                Employee
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Job title
                                            </th>
                                            <th className="pb-2 font-medium">
                                                System Role
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Status
                                            </th>
                                            <th className="pb-2 font-medium" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.data.map((employee) => (
                                            <tr
                                                key={employee.id}
                                                className="border-t"
                                            >
                                                <td className="py-3">
                                                    <p className="font-medium">
                                                        {employee.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {employee.employee_number}
                                                    </p>
                                                </td>
                                                <td className="py-3">
                                                    {employee.job_title ?? '—'}
                                                </td>
                                                <td className="py-3">
                                                    {employee.login_role_label ??
                                                        employee.job_type_label}
                                                </td>
                                                <td className="py-3">
                                                    <StatusBadge
                                                        status={
                                                            employee.employment_status
                                                        }
                                                        label={
                                                            employee.employment_status_label
                                                        }
                                                    />
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Button asChild variant="secondary" size="sm">
                                                        <Link
                                                            href={create.url({
                                                                query: {
                                                                    search: filters.search,
                                                                    employee_id:
                                                                        employee.id,
                                                                },
                                                            })}
                                                        >
                                                            Select
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                meta={employees.meta}
                                links={employees.links}
                            />
                        </div>
                    )}
                </Panel>

                {selected && (
                    <Panel
                        title="Confirm Supervisor assignment"
                        description="The same employee record is kept. The linked login is promoted from DSP to SUPERVISOR."
                    >
                        <dl className="mb-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">Employee</dt>
                                <dd className="font-medium">{selected.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Employee number
                                </dt>
                                <dd>{selected.employee_number}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Job title</dt>
                                <dd>{selected.job_title ?? 'Unchanged / none'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Current System Role
                                </dt>
                                <dd>
                                    {selected.login_role_label ??
                                        selected.job_type_label}
                                </dd>
                            </div>
                        </dl>
                        <p className="text-muted-foreground mb-4 text-sm">
                            After confirmation, {selected.name} can use Supervisor
                            tools. Job title remains “{selected.job_title ?? 'blank'}”.
                        </p>
                        <Form action={store.url()} method="post" className="space-y-3">
                            <input
                                type="hidden"
                                name="employee_id"
                                value={selected.id}
                            />
                            <InputError message={errors.employee_id} />
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit">
                                    Confirm Supervisor assignment
                                </Button>
                                <Button asChild variant="secondary">
                                    <Link href={create.url()}>Clear selection</Link>
                                </Button>
                            </div>
                        </Form>
                    </Panel>
                )}
            </div>
        </>
    );
}

SupervisorsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Supervisors', href: supervisorsIndex() },
        { title: 'Add Supervisor', href: create() },
    ],
};

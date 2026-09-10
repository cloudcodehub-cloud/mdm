import { Head, Link } from '@inertiajs/react';
import { EmployeeForm } from '@/components/mdm/employee-form';
import { dashboard } from '@/routes';
import { create, index as employeesIndex, store } from '@/routes/employees';
import type { OptionItem } from '@/types/directory';

export default function EmployeesCreate({
    supervisors,
    linkableUsers,
}: {
    supervisors: OptionItem[];
    linkableUsers: OptionItem[];
}) {
    return (
        <>
            <Head title="Add Employee" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link href={employeesIndex()} className="hover:text-foreground">
                            Employees
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add Employee
                    </h1>
                </div>
                <EmployeeForm
                    action={store.url()}
                    method="post"
                    supervisors={supervisors}
                    linkableUsers={linkableUsers}
                    submitLabel="Create employee"
                />
            </div>
        </>
    );
}

EmployeesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeesIndex() },
        { title: 'Add Employee', href: create() },
    ],
};

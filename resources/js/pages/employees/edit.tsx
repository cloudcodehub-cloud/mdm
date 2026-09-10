import { Head, Link } from '@inertiajs/react';
import { EmployeeForm } from '@/components/mdm/employee-form';
import { dashboard } from '@/routes';
import { edit, index as employeesIndex, show, update } from '@/routes/employees';
import type { EmployeeDetail, OptionItem } from '@/types/directory';

export default function EmployeesEdit({
    employee,
    supervisors,
    linkableUsers,
}: {
    employee: EmployeeDetail;
    supervisors: OptionItem[];
    linkableUsers: OptionItem[];
}) {
    return (
        <>
            <Head title={`Edit ${employee.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link href={show(employee.id)} className="hover:text-foreground">
                            {employee.name}
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Edit employee
                    </h1>
                </div>
                <EmployeeForm
                    action={update.url(employee.id)}
                    method="put"
                    employee={employee}
                    supervisors={supervisors}
                    linkableUsers={linkableUsers}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EmployeesEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeesIndex() },
        { title: 'Edit', href: edit.url(0) },
    ],
};

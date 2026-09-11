import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel, StatCard } from '@/components/mdm/stat-card';
import { dashboard } from '@/routes';
import { index as complianceIndex } from '@/routes/compliance';
import { show as showEmployee } from '@/routes/employees';
import type {
    ComplianceEmployeeAttention,
    ComplianceItem,
    ComplianceSummary,
} from '@/types/attendance';

export default function ComplianceIndex({
    summary,
    expiring_soon_days,
    employees,
    items,
}: {
    summary: ComplianceSummary;
    expiring_soon_days: number;
    timezone: string;
    employees: ComplianceEmployeeAttention[];
    items: ComplianceItem[];
}) {
    return (
        <>
            <Head title="Compliance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Compliance
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Credential and training status from recorded dates and
                        the organization expiring-soon threshold (
                        {expiring_soon_days} days). Missing is shown only when
                        the current records can prove a requirement is absent.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Valid"
                        value={summary.valid}
                        hint="Currently valid credentials and completed training"
                    />
                    <StatCard
                        label="Expiring soon"
                        value={summary.expiring_soon}
                        hint={`Expires within ${expiring_soon_days} days`}
                    />
                    <StatCard
                        label="Expired"
                        value={summary.expired}
                        hint="Past expiry date, regardless of stored label"
                    />
                    <StatCard
                        label="Missing"
                        value={summary.missing}
                        hint="Not inferred without a required-item catalog"
                    />
                </div>

                <Panel
                    title="Employees needing attention"
                    description="Open the employee record for credentials and training."
                >
                    {employees.length === 0 ? (
                        <EmptyState message="No employees currently need compliance attention." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">
                                            Employee
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Expired
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Expiring soon
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Other
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            className="border-t"
                                        >
                                            <td className="py-3">
                                                <Link
                                                    href={showEmployee.url(
                                                        employee.id,
                                                    )}
                                                    className="hover:text-foreground font-medium"
                                                >
                                                    {employee.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {employee.employee_number}
                                                </p>
                                            </td>
                                            <td className="py-3">
                                                {employee.expired}
                                            </td>
                                            <td className="py-3">
                                                {employee.expiring_soon}
                                            </td>
                                            <td className="py-3">
                                                {employee.other}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                <Panel title="Credential and training drill-down">
                    {items.length === 0 ? (
                        <EmptyState message="No credential or training records in scope." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">
                                            Employee
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Record
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Expires
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item) => (
                                        <tr key={item.id} className="border-t">
                                            <td className="py-3">
                                                <Link
                                                    href={showEmployee.url(
                                                        item.employee_id,
                                                    )}
                                                    className="hover:text-foreground font-medium"
                                                >
                                                    {item.employee_name}
                                                </Link>
                                            </td>
                                            <td className="py-3">
                                                <p>{item.title}</p>
                                                <p className="text-muted-foreground text-xs capitalize">
                                                    {item.kind.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </p>
                                            </td>
                                            <td className="py-3">
                                                {item.expires_on ?? 'No expiry'}
                                            </td>
                                            <td className="py-3">
                                                <StatusBadge
                                                    status={item.status}
                                                    label={item.status_label}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

ComplianceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Compliance', href: complianceIndex() },
    ],
};

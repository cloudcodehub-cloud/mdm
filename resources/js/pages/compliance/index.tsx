import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import {
    ProgressRing,
    SegmentedStatusBar,
} from '@/components/mdm/visual-summaries';
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
    const tracked =
        summary.valid + summary.expiring_soon + summary.expired;
    const validPercent =
        tracked === 0 ? 0 : Math.round((100 * summary.valid) / tracked);

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

                <Panel
                    title="Compliance health"
                    description={`Valid, expiring, and expired records using the ${expiring_soon_days}-day expiring-soon threshold. Missing is shown only when the current records can prove a requirement is absent.`}
                >
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-center">
                        <ProgressRing
                            value={validPercent}
                            label={`${validPercent}%`}
                            detail="Currently valid"
                        />
                        <div className="min-w-0 flex-1">
                            <SegmentedStatusBar
                                segments={[
                                    {
                                        key: 'valid',
                                        label: 'Valid',
                                        value: summary.valid,
                                        tone: 'success',
                                    },
                                    {
                                        key: 'expiring',
                                        label: 'Expiring soon',
                                        value: summary.expiring_soon,
                                        tone: 'warning',
                                    },
                                    {
                                        key: 'expired',
                                        label: 'Expired',
                                        value: summary.expired,
                                        tone: 'critical',
                                    },
                                    ...(summary.missing > 0
                                        ? [
                                              {
                                                  key: 'missing',
                                                  label: 'Missing',
                                                  value: summary.missing,
                                                  tone: 'neutral' as const,
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        </div>
                    </div>
                </Panel>

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

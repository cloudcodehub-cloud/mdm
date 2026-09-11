import { Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/mdm/stat-card';
import { dashboard } from '@/routes';
import { index as reportsIndex, show } from '@/routes/reports';
import type { ReportSummary } from '@/types/reports';

export default function ReportsIndex({
    reports,
    timezone,
}: {
    reports: ReportSummary[];
    timezone: string;
}) {
    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Reports
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Operational reports from recorded visits, attendance,
                        compliance, and exceptions in {timezone}.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {reports.map((report) => (
                        <Panel
                            key={report.key}
                            title={report.title}
                            description={report.description}
                        >
                            <Link
                                href={show.url(report.key)}
                                className="text-sm font-medium hover:underline"
                            >
                                Open report
                            </Link>
                        </Panel>
                    ))}
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reports', href: reportsIndex() },
    ],
};

import { Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
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
            <div className="page-shell">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Reports
                    </h1>
                    <p className="text-muted-foreground text-sm break-words">
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
                            <Button
                                asChild
                                variant="secondary"
                                className="min-h-11 w-full sm:min-h-9 sm:w-auto"
                            >
                                <Link href={show.url(report.key)}>
                                    Open report
                                </Link>
                            </Button>
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

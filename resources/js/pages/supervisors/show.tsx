import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { OperationsBoardView } from '@/components/mdm/operations-board';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as showEmployee } from '@/routes/employees';
import { index as supervisorsIndex, show } from '@/routes/supervisors';
import type { EmployeeDetail } from '@/types/directory';
import type { OperationsBoard } from '@/types/operations';

export default function SupervisorsShow({
    supervisor,
    operations,
}: {
    supervisor: EmployeeDetail & {
        assigned_dsp_count: number;
        assigned_client_count: number;
    };
    operations: OperationsBoard;
}) {
    return (
        <>
            <Head title={supervisor.name} />
            <div className="flex flex-1 flex-col">
                <div className="flex flex-wrap items-start justify-between gap-3 px-4 pt-4 md:px-6">
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
                            {supervisor.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge
                                status={supervisor.employment_status}
                                label={supervisor.employment_status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {supervisor.assigned_dsp_count} DSPs ·{' '}
                                {supervisor.assigned_client_count} clients
                            </span>
                        </div>
                    </div>
                    <Button asChild variant="secondary">
                        <Link href={showEmployee.url(supervisor.id)}>
                            Employee record
                        </Link>
                    </Button>
                </div>
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

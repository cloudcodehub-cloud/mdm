import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as operationsIndex } from '@/routes/operations';
import {
    index as exceptionsIndex,
    show as showException,
} from '@/routes/visit-exceptions';
import type { Paginated } from '@/types/directory';
import type { VisitExceptionRecord } from '@/types/operations';

export default function VisitExceptionsIndex({
    exceptions,
    filters,
}: {
    exceptions: Paginated<VisitExceptionRecord>;
    filters: { status: string; type: string };
}) {
    return (
        <>
            <Head title="Exception review" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={operationsIndex()}
                            className="hover:text-foreground"
                        >
                            Operations
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Exception review
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Open and reviewed exceptions on your permitted caseload.
                    </p>
                </div>

                <Panel title="Filters">
                    <Form
                        action={exceptionsIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-3"
                    >
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className={controlClassName}
                        >
                            <option value="">Open and reviewed</option>
                            <option value="open">Open</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="resolved">Resolved</option>
                        </select>
                        <select
                            name="type"
                            defaultValue={filters.type}
                            className={controlClassName}
                        >
                            <option value="">All types</option>
                            <option value="gps_unavailable">
                                GPS / location
                            </option>
                            <option value="client_refusal">
                                Client refusal
                            </option>
                            <option value="critical_task_skipped">
                                Critical task skipped
                            </option>
                            <option value="other_visit_exception">
                                Unfinished-task exception
                            </option>
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                <Panel title="Exceptions">
                    {exceptions.data.length === 0 ? (
                        <EmptyState message="No exceptions match these filters." />
                    ) : (
                        <div className="space-y-3">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="pb-2 font-medium">
                                                Type
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Visit
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {exceptions.data.map((exception) => (
                                            <tr
                                                key={exception.id}
                                                className="hover:bg-muted/40 cursor-pointer border-t"
                                                onClick={() =>
                                                    router.visit(
                                                        showException.url(
                                                            exception.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <td className="py-3">
                                                    <div className="flex items-center gap-2">
                                                        <HighPriorityIndicator
                                                            active={Boolean(
                                                                exception.is_high_priority_open,
                                                            )}
                                                        />
                                                        <p className="font-medium">
                                                            {exception.type_label}
                                                        </p>
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">
                                                        {exception.message}
                                                    </p>
                                                </td>
                                                <td className="py-3">
                                                    <p>
                                                        {exception.client_name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {exception.dsp_name}
                                                    </p>
                                                </td>
                                                <td className="py-3">
                                                    <StatusBadge
                                                        status={
                                                            exception.status
                                                        }
                                                        label={
                                                            exception.status_label
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                meta={exceptions.meta}
                                links={exceptions.links}
                            />
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

VisitExceptionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Operations', href: operationsIndex() },
        { title: 'Exceptions', href: exceptionsIndex() },
    ],
};

import { Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create as createTicket,
    show as showTicket,
} from '@/routes/support-tickets';
import type { Paginated } from '@/types/directory';

type TicketListItem = {
    id: number;
    ticket_number: string;
    status: string;
    status_label: string;
    category_label: string;
    subject: string;
    urgency_label: string;
    work_impact_label: string;
    requester_name: string | null;
    assignee_name: string | null;
    age_label: string | null;
    created_at_label: string | null;
    summary_only: boolean;
};

type Metrics = {
    total: number;
    open: number;
    resolved_closed: number;
    average_resolution_hours: number | null;
    by_category: Array<{ category: string; label: string; count: number }>;
};

export default function SupportTicketsIndex({
    tickets,
    filters,
    categories,
    metrics,
    can,
}: {
    tickets: Paginated<TicketListItem>;
    filters: {
        tab: string | null;
        search: string | null;
        status: string | null;
        category: string | null;
        urgency: string | null;
    };
    categories: Array<{ value: string; label: string }>;
    metrics: Metrics | null;
    can: { create: boolean; it_tech: boolean; admin: boolean };
}) {
    const tabs = can.it_tech || can.admin
        ? [
              { id: '', label: 'All visible' },
              { id: 'unassigned', label: 'Unassigned' },
              { id: 'mine', label: 'My Tickets' },
              { id: 'resolved', label: 'Resolved' },
              { id: 'closed', label: 'Closed' },
          ]
        : [
              { id: 'open', label: 'My Open Tickets' },
              { id: 'history', label: 'Ticket History' },
              { id: '', label: 'All mine' },
          ];

    return (
        <>
            <Head title="Support Tickets" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Support Tickets
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Internal Agency IT/technical support. Do not enter
                            passwords or authentication secrets.
                        </p>
                    </div>
                    {can.create ? (
                        <Button asChild>
                            <Link href={createTicket()}>New ticket</Link>
                        </Button>
                    ) : null}
                </div>

                {metrics ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard label="Total" value={String(metrics.total)} />
                        <MetricCard label="Open" value={String(metrics.open)} />
                        <MetricCard
                            label="Resolved / closed"
                            value={String(metrics.resolved_closed)}
                        />
                        <MetricCard
                            label="Avg resolution (hrs)"
                            value={
                                metrics.average_resolution_hours !== null
                                    ? String(metrics.average_resolution_hours)
                                    : '—'
                            }
                        />
                    </div>
                ) : null}

                <Panel title="Tickets">
                    <div className="mb-4 flex flex-wrap gap-2">
                        {tabs.map((tab) => (
                            <Button
                                key={tab.id || 'all'}
                                size="sm"
                                variant={
                                    (filters.tab ?? '') === tab.id
                                        ? 'default'
                                        : 'secondary'
                                }
                                onClick={() =>
                                    router.get(
                                        '/support-tickets',
                                        {
                                            tab: tab.id || undefined,
                                            search: filters.search || undefined,
                                            status: filters.status || undefined,
                                            category: filters.category || undefined,
                                            urgency: filters.urgency || undefined,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>

                    <form
                        className="mb-4 flex flex-wrap gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const form = event.currentTarget;
                            const data = new FormData(form);
                            router.get(
                                '/support-tickets',
                                {
                                    tab: filters.tab || undefined,
                                    search:
                                        String(data.get('search') || '') ||
                                        undefined,
                                    status:
                                        String(data.get('status') || '') ||
                                        undefined,
                                    category:
                                        String(data.get('category') || '') ||
                                        undefined,
                                    urgency:
                                        String(data.get('urgency') || '') ||
                                        undefined,
                                },
                                { preserveState: true },
                            );
                        }}
                    >
                        <Input
                            name="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Search number, subject, requester"
                            className="max-w-xs"
                        />
                        <select
                            name="category"
                            defaultValue={filters.category ?? ''}
                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.value} value={category.value}>
                                    {category.label}
                                </option>
                            ))}
                        </select>
                        <select
                            name="urgency"
                            defaultValue={filters.urgency ?? ''}
                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">All urgency</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <Button type="submit" size="sm" variant="secondary">
                            Apply
                        </Button>
                    </form>

                    {tickets.data.length === 0 ? (
                        <EmptyState message="No support tickets match this view." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">Ticket</th>
                                        <th className="pb-2 font-medium">Requester</th>
                                        <th className="pb-2 font-medium">Category</th>
                                        <th className="pb-2 font-medium">Subject</th>
                                        <th className="pb-2 font-medium">Urgency</th>
                                        <th className="pb-2 font-medium">Status</th>
                                        <th className="pb-2 font-medium">Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tickets.data.map((ticket) => (
                                        <tr
                                            key={ticket.id}
                                            className="hover:bg-muted/40 cursor-pointer border-t"
                                            onClick={() =>
                                                router.visit(showTicket.url(ticket.id))
                                            }
                                        >
                                            <td className="py-3 font-medium">
                                                {ticket.ticket_number}
                                            </td>
                                            <td className="py-3">
                                                {ticket.requester_name}
                                            </td>
                                            <td className="py-3">
                                                {ticket.category_label}
                                            </td>
                                            <td className="py-3">{ticket.subject}</td>
                                            <td className="py-3">
                                                {ticket.urgency_label}
                                            </td>
                                            <td className="py-3">
                                                <StatusBadge
                                                    status={ticket.status}
                                                    label={ticket.status_label}
                                                />
                                            </td>
                                            <td className="py-3">
                                                {ticket.age_label}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="mt-4">
                        <Pagination links={tickets.links} meta={tickets.meta} />
                    </div>
                </Panel>
            </div>
        </>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-border/70 rounded-lg border p-3">
            <p className="text-muted-foreground text-xs uppercase">{label}</p>
            <p className="mt-1 text-lg font-semibold">{value}</p>
        </div>
    );
}

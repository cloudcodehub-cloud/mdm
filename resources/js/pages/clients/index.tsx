import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { create, index as clientsIndex, show } from '@/routes/clients';
import type { ClientSummary, OptionItem, Paginated } from '@/types/directory';

export default function ClientsIndex({
    clients,
    filters,
    supervisors,
    can,
}: {
    clients: Paginated<ClientSummary>;
    filters: {
        search: string;
        status: string;
        supervisor_id: string;
    };
    supervisors: OptionItem[];
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Clients" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Clients
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            People receiving services, caseload status, and assigned DSPs.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={create()}>Add Client</Link>
                        </Button>
                    )}
                </div>

                <Panel title="Search and filters">
                    <Form
                        action={clientsIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-4"
                    >
                        <Input
                            name="search"
                            placeholder="Search name or client number"
                            defaultValue={filters.search}
                        />
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className={controlClassName}
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="discharged">Discharged</option>
                        </select>
                        <select
                            name="supervisor_id"
                            defaultValue={filters.supervisor_id}
                            className={controlClassName}
                        >
                            <option value="">All supervisors</option>
                            {supervisors.map((supervisor) => (
                                <option key={supervisor.id} value={supervisor.id}>
                                    {supervisor.name}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                {clients.data.length === 0 ? (
                    <Panel title="Client directory">
                        <EmptyState message="No clients match these filters." />
                    </Panel>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {clients.data.map((client) => (
                                <button
                                    key={client.id}
                                    type="button"
                                    onClick={() => router.visit(show.url(client.id))}
                                    className="surface-panel hover:bg-muted/30 p-4 text-left transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">{client.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {client.client_number}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={client.status}
                                            label={client.status_label}
                                        />
                                    </div>
                                    <p className="text-muted-foreground mt-3 text-sm">
                                        Supervisor: {client.supervisor_name ?? 'Unassigned'}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        Active DSPs: {client.active_dsp_count}
                                    </p>
                                    {(client.city || client.state) && (
                                        <p className="text-muted-foreground text-sm">
                                            {[client.city, client.state]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </p>
                                    )}
                                </button>
                            ))}
                        </div>
                        <Pagination meta={clients.meta} links={clients.links} />
                    </>
                )}
            </div>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
    ],
};

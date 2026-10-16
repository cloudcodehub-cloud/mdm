import { Head, Link, router } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    acknowledge,
    index as incidentsIndex,
    show as showIncident,
} from '@/routes/incidents';
import { show as showVisit } from '@/routes/visits';
import type { IncidentDetail } from '@/types/incident';

export default function IncidentsShow({
    incident,
    can,
}: {
    incident: IncidentDetail;
    can?: {
        acknowledge?: boolean;
        manage?: boolean;
    };
}) {
    return (
        <>
            <Head
                title={
                    incident.tracking_number
                        ? `Incident ${incident.tracking_number}`
                        : 'Incident'
                }
            />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            <Link
                                href={incidentsIndex()}
                                className="hover:text-foreground"
                            >
                                Incident register
                            </Link>
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {incident.tracking_number ?? `Incident #${incident.id}`}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {incident.client.name} · submitted{' '}
                            {incident.submitted_at_label ?? '—'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge
                            status={incident.status}
                            label={incident.status_label}
                        />
                        {incident.overdue_for_acknowledgment ? (
                            <StatusBadge
                                status="attention"
                                label="Overdue for acknowledgment"
                            />
                        ) : null}
                        {can?.acknowledge ? (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.post(acknowledge.url(incident.id))
                                }
                            >
                                Acknowledge Incident
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-12">
                    <Panel
                        title="Reporter submission"
                        description="Original DSP factual report. Not silently rewritten after submit."
                        className="lg:col-span-8"
                    >
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Occurred
                                </dt>
                                <dd>{incident.occurred_at_label ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Category
                                </dt>
                                <dd>
                                    {incident.original_category}
                                    {incident.original_subcategory
                                        ? ` / ${incident.original_subcategory}`
                                        : ''}
                                </dd>
                            </div>
                            <div className="md:col-span-2">
                                <dt className="text-muted-foreground text-xs">
                                    Summary
                                </dt>
                                <dd className="whitespace-pre-wrap">
                                    {incident.summary}
                                </dd>
                            </div>
                            <div className="md:col-span-2">
                                <dt className="text-muted-foreground text-xs">
                                    Immediate action
                                </dt>
                                <dd className="whitespace-pre-wrap">
                                    {incident.immediate_action}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Injured
                                </dt>
                                <dd>{incident.injured ? 'Yes' : 'No'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Supervisor notified
                                </dt>
                                <dd>
                                    {incident.supervisor_notified ? 'Yes' : 'No'}
                                </dd>
                            </div>
                            {incident.additional_notes ? (
                                <div className="md:col-span-2">
                                    <dt className="text-muted-foreground text-xs">
                                        Additional notes
                                    </dt>
                                    <dd className="whitespace-pre-wrap">
                                        {incident.additional_notes}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    </Panel>

                    <Panel title="Context" className="lg:col-span-4">
                        <dl className="grid gap-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Reporter
                                </dt>
                                <dd>{incident.reporter.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Responsible supervisor
                                </dt>
                                <dd>
                                    {incident.responsible_supervisor?.name ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Acknowledgment
                                </dt>
                                <dd>
                                    {incident.is_acknowledged
                                        ? `${incident.acknowledged_at_label}${
                                              incident.acknowledged_by_name
                                                  ? ` · ${incident.acknowledged_by_name}`
                                                  : ''
                                          }`
                                        : incident.acknowledgment_condition}
                                </dd>
                            </div>
                            <div>
                                <Button asChild variant="secondary" size="sm">
                                    <Link href={showVisit.url(incident.visit_id)}>
                                        Open related visit
                                    </Link>
                                </Button>
                            </div>
                        </dl>
                    </Panel>

                    <Panel
                        title="Audit history"
                        description="Meaningful Incident actions remain historically recoverable."
                        className="lg:col-span-12"
                    >
                        {incident.history.length === 0 ? (
                            <EmptyState compact message="No history entries." />
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {incident.history.map((event) => (
                                    <li key={event.id} className="border-t pt-2">
                                        <p className="font-medium">
                                            {event.action_label}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {event.created_at_label}
                                            {event.actor_name
                                                ? ` · ${event.actor_name}`
                                                : ''}
                                        </p>
                                        <p>{event.summary}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

IncidentsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentsIndex() },
    ],
};

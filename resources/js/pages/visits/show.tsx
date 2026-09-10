import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { dashboard } from '@/routes';
import { show as showScheduled } from '@/routes/scheduled-visits';
import { show } from '@/routes/visits';
import type { ActiveVisitRecord } from '@/types/visit';

export default function VisitsShow({ visit }: { visit: ActiveVisitRecord }) {
    return (
        <>
            <Head title={`Active visit · ${visit.client.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={showScheduled(visit.scheduled_visit.id)}
                            className="hover:text-foreground"
                        >
                            Scheduled visit
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {visit.client.name}
                    </h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={visit.status}
                            label={visit.status_label}
                        />
                        <span className="text-muted-foreground text-sm">
                            {visit.service_type}
                        </span>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Visit">
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Client" value={visit.client.name} />
                            <Detail label="Service" value={visit.service_type} />
                            <Detail
                                label="Scheduled shift"
                                value={`${visit.scheduled_visit.service_date} · ${visit.scheduled_visit.time_label}${visit.scheduled_visit.shift_name ? ` · ${visit.scheduled_visit.shift_name}` : ''}`}
                            />
                            <Detail
                                label="Clock-in time"
                                value={visit.clocked_in_at_label}
                            />
                            <Detail
                                label="Location"
                                value={visit.location_status_label}
                            />
                            {visit.unavailable_reason && (
                                <Detail
                                    label="GPS note"
                                    value={visit.unavailable_reason}
                                />
                            )}
                            <Detail
                                label="Elapsed"
                                value={
                                    <ElapsedSince iso={visit.clocked_in_at} />
                                }
                            />
                        </dl>
                    </Panel>
                    <Panel
                        title="Care-plan tasks"
                        description="Task completion will be enabled in a later phase."
                    >
                        {visit.tasks.length === 0 ? (
                            <EmptyState message="No care-plan tasks apply to this visit." />
                        ) : (
                            <ul className="space-y-3">
                                {visit.tasks.map((task) => (
                                    <li
                                        key={task.id}
                                        className="rounded-xl border border-border/70 p-3"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="font-medium">
                                                {task.title}
                                            </p>
                                            <StatusBadge
                                                status={task.status}
                                                label={task.status_label}
                                            />
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {task.recurrence_label}
                                            {task.is_required
                                                ? ' · Required'
                                                : ''}
                                        </p>
                                        {task.instructions && (
                                            <p className="mt-2 text-sm">
                                                {task.instructions}
                                            </p>
                                        )}
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

function Detail({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{value}</dd>
        </div>
    );
}

function ElapsedSince({ iso }: { iso: string }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    const started = new Date(iso).getTime();
    const seconds = Math.max(0, Math.floor((now - started) / 1000));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;

    return (
        <span className="tabular-nums">
            Active · {String(hours).padStart(2, '0')}:
            {String(minutes).padStart(2, '0')}:
            {String(remainder).padStart(2, '0')}
        </span>
    );
}

VisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Active visit', href: show.url(0) },
    ],
};

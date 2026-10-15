import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import type { CareOverview, CareHistoryItem } from '@/types/care';

export function CareOverviewPanels({
    overview,
    onHistory,
    onContactSupervisor,
    onViewOlderHistory,
}: {
    overview: CareOverview;
    onHistory?: (item: CareHistoryItem) => void;
    onContactSupervisor?: () => void;
    onViewOlderHistory?: () => void;
}) {
    const serviceLabel =
        overview.services && overview.services.length > 0
            ? overview.services.map((service) => service.name).join(', ')
            : null;

    return (
        <div className="space-y-3">
            {serviceLabel && (
                <p className="text-muted-foreground text-sm">
                    Services: {serviceLabel}
                </p>
            )}
            <div className="grid gap-4 lg:grid-cols-3">
            <Panel title="Today" description="Expected during today’s visit.">
                {overview.today.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No care tasks are expected today.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {overview.today.map((item) => (
                            <li key={item.id} className="text-sm">
                                <p className="font-medium">{item.title}</p>
                                <p className="text-muted-foreground text-xs">
                                    {item.recurrence_label}
                                    {item.preferred_timing_label
                                        ? ` · ${item.preferred_timing_label}`
                                        : ''}
                                    {item.is_required ? ' · required' : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel
                title="Coming up"
                description="Visible for preparation. Not completable early."
            >
                {overview.upcoming.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No upcoming recurring items in the next year.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {overview.upcoming.map((item) => (
                            <li key={item.id} className="text-sm">
                                <p className="font-medium">{item.title}</p>
                                <p className="text-muted-foreground text-xs">
                                    {item.next_on_label} · {item.recurrence_label}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel
                title="History"
                description={
                    overview.history_scope === 'older'
                        ? 'Older care notes for continuity of care.'
                        : 'Recent care notes (last 30 days).'
                }
            >
                {overview.history.length === 0 ? (
                    <EmptyState compact message="No recent care notes." />
                ) : (
                    <ul className="space-y-3">
                        {overview.history.map((item) => (
                            <li key={item.id} className="text-sm">
                                <p className="font-medium">{item.task_title}</p>
                                <p className="mt-0.5">{item.note}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {item.dsp_name} · {item.occurred_at_label}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {onHistory && (
                                        <button
                                            type="button"
                                            className="text-primary text-xs font-medium"
                                            onClick={() => onHistory(item)}
                                        >
                                            View History
                                        </button>
                                    )}
                                    {item.previous_dsp_available && onHistory && (
                                        <button
                                            type="button"
                                            className="text-primary text-xs font-medium"
                                            onClick={() => onHistory(item)}
                                        >
                                            Message Previous DSP
                                        </button>
                                    )}
                                    {onContactSupervisor && (
                                        <button
                                            type="button"
                                            className="text-primary text-xs font-medium"
                                            onClick={onContactSupervisor}
                                        >
                                            Contact Supervisor
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                {overview.can_view_older_history && onViewOlderHistory && (
                    <Button
                        type="button"
                        variant="secondary"
                        className="mt-3"
                        onClick={onViewOlderHistory}
                    >
                        View Older History
                    </Button>
                )}
            </Panel>
            </div>
        </div>
    );
}

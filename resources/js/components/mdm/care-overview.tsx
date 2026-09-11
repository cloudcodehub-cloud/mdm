import { EmptyState, Panel } from '@/components/mdm/stat-card';
import type { CareOverview, CareHistoryItem } from '@/types/care';

export function CareOverviewPanels({
    overview,
    onHistory,
    onContactSupervisor,
}: {
    overview: CareOverview;
    onHistory?: (item: CareHistoryItem) => void;
    onContactSupervisor?: () => void;
}) {
    return (
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
            <Panel title="History" description="Recent care notes for this client.">
                {overview.history.length === 0 ? (
                    <EmptyState message="No recent care notes." />
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
            </Panel>
        </div>
    );
}

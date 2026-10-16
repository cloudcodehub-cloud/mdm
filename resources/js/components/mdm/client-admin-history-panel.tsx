export type ClientAdminHistoryRecord = {
    id: number;
    category: string;
    category_label: string;
    summary: string;
    old_value: string | null;
    new_value: string | null;
    reason: string | null;
    effective_on: string | null;
    actor_name: string | null;
    occurred_at: string | null;
    occurred_at_label: string | null;
};

export function ClientAdminHistoryPanel({
    events,
}: {
    events: ClientAdminHistoryRecord[];
}) {
    return (
        <section className="surface-panel space-y-4 p-4 md:p-5">
            <div>
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Administrative history
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Material Client, Care Plan, authorization, lifecycle, and
                    safety changes with actor, timestamp, and reason where
                    recorded.
                </p>
            </div>

            {events.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No administrative history events recorded yet.
                </p>
            ) : (
                <ul className="space-y-3">
                    {events.map((event) => (
                        <li
                            key={event.id}
                            className="rounded-md border px-3 py-3 text-sm"
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">{event.summary}</p>
                                <p className="text-muted-foreground text-xs">
                                    {event.occurred_at_label ?? '—'}
                                </p>
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {event.category_label}
                                {event.actor_name
                                    ? ` · ${event.actor_name}`
                                    : ''}
                                {event.effective_on
                                    ? ` · Effective ${event.effective_on}`
                                    : ''}
                            </p>
                            {(event.old_value || event.new_value) && (
                                <p className="mt-2 text-xs">
                                    {event.old_value ? (
                                        <span className="text-muted-foreground">
                                            {event.old_value}
                                        </span>
                                    ) : null}
                                    {event.old_value && event.new_value
                                        ? ' → '
                                        : ''}
                                    {event.new_value ? (
                                        <span>{event.new_value}</span>
                                    ) : null}
                                </p>
                            )}
                            {event.reason ? (
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Reason: {event.reason}
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

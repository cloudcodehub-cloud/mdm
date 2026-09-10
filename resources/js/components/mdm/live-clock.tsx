import { useEffect, useState } from 'react';

export function LiveClock() {
    const [now, setNow] = useState<Date | null>(null);

    useEffect(() => {
        const tick = () => setNow(new Date());
        tick();
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, []);

    if (now === null) {
        return (
            <div className="hidden min-w-[11rem] sm:block">
                <p className="text-muted-foreground text-xs">Local time</p>
                <p className="text-sm font-medium">—</p>
            </div>
        );
    }

    const dateLabel = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(now);

    const timeLabel = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(now);

    return (
        <div className="hidden min-w-[11rem] sm:block">
            <p className="text-muted-foreground text-xs">{dateLabel}</p>
            <p className="text-sm font-medium tabular-nums">{timeLabel}</p>
        </div>
    );
}

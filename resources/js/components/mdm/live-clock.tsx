import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const dateFormatOptions = (
    dateFormat: string,
): Intl.DateTimeFormatOptions => {
    if (dateFormat === 'Y-m-d') {
        return { year: 'numeric', month: '2-digit', day: '2-digit' };
    }

    if (dateFormat === 'd/m/Y') {
        return { day: '2-digit', month: '2-digit', year: 'numeric' };
    }

    return { month: 'short', day: 'numeric', year: 'numeric', weekday: 'short' };
};

export function LiveClock() {
    const organization = usePage().props.organization;
    const timezone = organization?.timezone;
    const timeFormat = organization?.time_format === '24' ? '24' : '12';
    const dateFormat = organization?.date_format ?? 'm/d/Y';
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
                <p className="text-muted-foreground text-xs">
                    {organization?.timezone_label ?? 'Operational time'}
                </p>
                <p className="text-sm font-medium">—</p>
            </div>
        );
    }

    const dateLabel = new Intl.DateTimeFormat(undefined, {
        ...dateFormatOptions(dateFormat),
        timeZone: timezone,
    }).format(now);

    const timeLabel = new Intl.DateTimeFormat(undefined, {
        hour: timeFormat === '24' ? '2-digit' : 'numeric',
        minute: '2-digit',
        hour12: timeFormat !== '24',
        timeZone: timezone,
    }).format(now);

    return (
        <div className="hidden min-w-[11rem] sm:block">
            <p className="text-muted-foreground text-xs">{dateLabel}</p>
            <p className="text-sm font-medium tabular-nums">{timeLabel}</p>
        </div>
    );
}

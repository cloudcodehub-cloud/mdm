import { Moon, Sun } from 'lucide-react';
import { isDaytimeHour } from '@/lib/organization-time';
import { useOrganizationClock } from '@/hooks/use-organization-clock';

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
    const {
        now,
        timezone,
        timezoneLabel,
        timeFormat,
        dateFormat,
        hour,
    } = useOrganizationClock();
    const daytime = isDaytimeHour(hour);
    const TimeOfDayIcon = daytime ? Sun : Moon;

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
        <div className="hidden items-center gap-2 sm:flex">
            <TimeOfDayIcon
                aria-hidden
                className="text-muted-foreground size-4 shrink-0"
            />
            <div className="min-w-[11rem]">
                <p className="text-muted-foreground text-xs">{dateLabel}</p>
                <p className="text-sm font-medium tabular-nums">{timeLabel}</p>
                <span className="sr-only">
                    {daytime ? 'Daytime' : 'Evening'} in {timezoneLabel}
                </span>
            </div>
        </div>
    );
}

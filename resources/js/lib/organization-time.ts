const FALLBACK_AGENCY_NAME = 'Magic Data Management';

export function agencyDisplayName(name: string | null | undefined): string {
    const trimmed = name?.trim();

    return trimmed && trimmed.length > 0 ? trimmed : FALLBACK_AGENCY_NAME;
}

/**
 * Organization-local hour (0–23).
 */
export function hourInTimeZone(date: Date, timeZone: string): number {
    const zone = timeZone.trim() === '' ? 'UTC' : timeZone;
    const parts = new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        hourCycle: 'h23',
        timeZone: zone,
    }).formatToParts(date);
    const hour = Number.parseInt(
        parts.find((part) => part.type === 'hour')?.value ?? '0',
        10,
    );

    return Number.isFinite(hour) ? hour % 24 : 0;
}

/**
 * 05:00–11:59 Good morning
 * 12:00–16:59 Good afternoon
 * 17:00–04:59 Good evening
 */
export function greetingForHour(hour: number): string {
    if (hour >= 5 && hour < 12) {
        return 'Good morning';
    }

    if (hour >= 12 && hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

/**
 * Sun 06:00–17:59, Moon 18:00–05:59.
 */
export function isDaytimeHour(hour: number): boolean {
    return hour >= 6 && hour < 18;
}

export type VisitCountdownState =
    | { kind: 'starts_in'; label: string; hours: number; minutes: number }
    | { kind: 'ready'; label: string }
    | { kind: 'started'; label: string; minutes: number };

export function visitCountdown(
    nowMs: number,
    startIso: string | null | undefined,
    startedIso?: string | null,
): VisitCountdownState {
    if (startedIso) {
        const started = Date.parse(startedIso);
        const origin = Number.isFinite(started) ? started : nowMs;
        const minutes = Math.max(0, Math.floor((nowMs - origin) / 60_000));

        return {
            kind: 'started',
            minutes,
            label: formatStartedAgo(minutes),
        };
    }

    if (!startIso) {
        return { kind: 'ready', label: 'Ready to start' };
    }

    const start = Date.parse(startIso);

    if (!Number.isFinite(start) || start - nowMs <= 0) {
        return { kind: 'ready', label: 'Ready to start' };
    }

    const totalMinutes = Math.max(1, Math.ceil((start - nowMs) / 60_000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    return {
        kind: 'starts_in',
        hours,
        minutes,
        label: formatStartsIn(hours, minutes),
    };
}

export function formatStartsIn(hours: number, minutes: number): string {
    if (hours <= 0) {
        return `Starts in ${minutes}m`;
    }

    return `Starts in ${hours}h ${minutes}m`;
}

export function formatStartedAgo(minutes: number): string {
    if (minutes < 1) {
        return 'Started just now';
    }

    return `Started ${minutes}m ago`;
}

export function formatElapsedClock(nowMs: number, clockedInIso: string): string {
    const started = Date.parse(clockedInIso);
    const origin = Number.isFinite(started) ? started : nowMs;
    const seconds = Math.max(0, Math.floor((nowMs - origin) / 1000));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;

    return [
        String(hours).padStart(2, '0'),
        String(minutes).padStart(2, '0'),
        String(remainder).padStart(2, '0'),
    ].join(':');
}

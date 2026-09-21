import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function RelativeTime({
    iso,
    exact,
    className,
    compact = false,
}: {
    iso?: string | null;
    exact?: string | null;
    className?: string;
    compact?: boolean;
}) {
    const now = usePage().props.organization?.now;
    const relative = iso ? relativeLabel(iso, now, compact) : null;
    const title = exact ?? iso ?? undefined;

    if (!relative && !exact) {
        return <span className={className}>—</span>;
    }

    return (
        <span className={cn(compact ? 'inline' : 'inline-flex flex-col', className)} title={title}>
            {relative ? <span>{relative}</span> : null}
            {!compact && exact ? (
                <span className="text-muted-foreground text-xs">{exact}</span>
            ) : null}
        </span>
    );
}

export function relativeLabel(iso: string, nowIso?: string, compact = false): string | null {
    const then = Date.parse(iso);
    const now = nowIso ? Date.parse(nowIso) : Date.now();

    if (!Number.isFinite(then) || !Number.isFinite(now)) {
        return null;
    }

    const deltaMinutes = Math.round((then - now) / 60000);
    const abs = Math.abs(deltaMinutes);

    if (abs < 1) {
        return then >= now ? 'Starts now' : 'Just now';
    }

    if (abs < 60) {
        if (then >= now) {
            return `Starts in ${abs}m`;
        }

        return compact ? `${abs}m ago` : `${abs} minutes ago`;
    }

    const hours = Math.round(abs / 60);

    if (hours < 24) {
        return then >= now ? `Starts in ${hours}h` : `${hours}h ago`;
    }

    const days = Math.round(hours / 24);

    if (days === 1) {
        return then >= now ? 'Tomorrow' : 'yesterday';
    }

    if (days < 7) {
        return then >= now ? `In ${days} days` : `${days} days ago`;
    }

    return compact && exactFallback(iso) ? exactFallback(iso) : null;
}

function exactFallback(iso: string): string | null {
    const date = new Date(iso);

    if (!Number.isFinite(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString();
}

import { useOrganizationClock } from '@/hooks/use-organization-clock';
import { formatElapsedClock } from '@/lib/visit-timing';

export function VisitElapsedTimer({
    clockedInAt,
}: {
    clockedInAt: string;
}) {
    const { now } = useOrganizationClock();
    const value = formatElapsedClock(now.getTime(), clockedInAt);

    return (
        <p
            className="font-semibold tracking-tight tabular-nums sm:text-3xl text-2xl"
            aria-live="polite"
            aria-label={`Elapsed visit time ${value}`}
        >
            {value}
        </p>
    );
}

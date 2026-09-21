import { useOrganizationClock } from '@/hooks/use-organization-clock';
import { visitCountdown } from '@/lib/visit-timing';

export function VisitCountdown({
    startsAtIso,
    startedAtIso,
}: {
    startsAtIso?: string | null;
    startedAtIso?: string | null;
}) {
    const { now } = useOrganizationClock();
    const state = visitCountdown(now.getTime(), startsAtIso, startedAtIso);

    return (
        <p
            className="text-primary mt-2 text-sm font-medium tabular-nums"
            aria-live="polite"
        >
            {state.label}
        </p>
    );
}

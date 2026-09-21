import { greetingForHour } from '@/lib/organization-time';
import { useOrganizationClock } from '@/hooks/use-organization-clock';

export function DashboardGreeting({ name }: { name: string }) {
    const { hour, organizationName } = useOrganizationClock();
    const greeting = greetingForHour(hour);

    return (
        <div className="space-y-0.5">
            <p className="text-muted-foreground text-sm">
                {greeting}, {name}
            </p>
            <p className="text-muted-foreground text-xs">{organizationName}</p>
        </div>
    );
}

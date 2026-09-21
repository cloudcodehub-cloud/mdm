import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    agencyDisplayName,
    hourInTimeZone,
} from '@/lib/organization-time';

export function useOrganizationClock() {
    const organization = usePage().props.organization;
    const timezone = organization?.timezone?.trim() || 'UTC';
    const organizationName = agencyDisplayName(
        organization?.organization_name,
    );
    const timeFormat = organization?.time_format === '24' ? '24' : '12';
    const dateFormat = organization?.date_format ?? 'm/d/Y';
    const timezoneLabel = organization?.timezone_label ?? 'Operational time';
    const serverNow = organization?.now;

    const [now, setNow] = useState<Date>(() => {
        const parsed = Date.parse(serverNow ?? '');

        return new Date(Number.isFinite(parsed) ? parsed : Date.now());
    });

    useEffect(() => {
        const parsed = Date.parse(serverNow ?? '');
        const originClient = Date.now();
        const originServer = Number.isFinite(parsed) ? parsed : originClient;

        const tick = () => {
            setNow(new Date(originServer + (Date.now() - originClient)));
        };

        tick();
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, [serverNow]);

    return {
        now,
        timezone,
        timezoneLabel,
        organizationName,
        timeFormat,
        dateFormat,
        hour: hourInTimeZone(now, timezone),
    };
}

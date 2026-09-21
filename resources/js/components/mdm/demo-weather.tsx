import { CloudOff, CloudSun } from 'lucide-react';
import type { AgencyWeather } from '@/types/auth';

export function AgencyWeather({ weather }: { weather: AgencyWeather }) {
    const available = weather.available !== false && Boolean(weather.temperature);
    const title = available
        ? 'Current weather for the configured agency location.'
        : 'Weather is temporarily unavailable.';

    return (
        <div
            className="hidden items-center gap-2 rounded-xl border border-border/60 bg-background/50 px-3 py-1.5 backdrop-blur-sm md:flex"
            title={title}
        >
            {available ? (
                <CloudSun className="text-muted-foreground size-4" />
            ) : (
                <CloudOff className="text-muted-foreground size-4" />
            )}
            <div className="leading-tight">
                <p className="text-xs font-medium">
                    {available
                        ? `${weather.temperature} · ${weather.condition}`
                        : 'Weather unavailable'}
                </p>
                {weather.location ? (
                    <p className="text-muted-foreground text-[11px]">
                        {weather.location}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

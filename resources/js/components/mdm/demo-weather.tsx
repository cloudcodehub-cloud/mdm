import { CloudSun } from 'lucide-react';
import type { DemoWeather } from '@/types/auth';

export function DemoWeather({ weather }: { weather: DemoWeather }) {
    return (
        <div
            className="hidden items-center gap-2 rounded-xl border border-border/60 bg-white/50 px-3 py-1.5 md:flex dark:bg-white/5"
            title="Local demo weather. Not connected to a live weather service."
        >
            <CloudSun className="text-muted-foreground size-4" />
            <div className="leading-tight">
                <p className="text-xs font-medium">
                    {weather.temperature} · {weather.condition}
                </p>
                <p className="text-muted-foreground text-[11px]">
                    {weather.location} · {weather.source}
                </p>
            </div>
        </div>
    );
}

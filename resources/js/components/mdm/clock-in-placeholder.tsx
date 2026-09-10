import { MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function ClockInPlaceholder() {
    return (
        <div className="rounded-2xl border border-teal-800/15 bg-teal-50/70 p-4 dark:border-teal-400/20 dark:bg-teal-950/30">
            <p className="text-xs font-medium tracking-wide text-teal-900 uppercase dark:text-teal-200">
                Next visit action
            </p>
            <p className="mt-1 text-sm text-teal-950/80 dark:text-teal-100/80">
                Clock-in will be available in the next phase. This is where you
                will start a visit.
            </p>
            <Button
                className="mt-4 w-full sm:w-auto"
                disabled
                title="Clock-in will be enabled in the next phase."
            >
                <MapPin className="size-4" />
                Start Visit / Clock In
            </Button>
        </div>
    );
}

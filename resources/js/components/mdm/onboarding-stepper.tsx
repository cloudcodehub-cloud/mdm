import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const STEPS = [
    { id: 1, label: 'Profile', shortLabel: 'Profile' },
    { id: 2, label: 'Services & Care Plan', shortLabel: 'Services' },
    { id: 3, label: 'Review', shortLabel: 'Review' },
] as const;

export function OnboardingStepper({ currentStep }: { currentStep: 1 | 2 | 3 }) {
    return (
        <nav aria-label="Client onboarding progress">
            <ol className="flex w-full items-center">
                {STEPS.map((step, index) => {
                    const complete = step.id < currentStep;
                    const current = step.id === currentStep;
                    const last = index === STEPS.length - 1;

                    return (
                        <li
                            key={step.id}
                            className={cn(
                                'flex min-w-0 items-center',
                                last ? 'shrink-0' : 'flex-1',
                            )}
                        >
                            <div className="flex min-w-0 items-center gap-1.5 sm:gap-2">
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold sm:size-7 sm:text-xs',
                                        complete &&
                                            'bg-primary text-primary-foreground',
                                        current &&
                                            'bg-primary text-primary-foreground ring-4 ring-primary/20',
                                        !complete &&
                                            !current &&
                                            'bg-muted text-muted-foreground',
                                    )}
                                    aria-current={current ? 'step' : undefined}
                                >
                                    {complete ? (
                                        <Check
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    ) : (
                                        step.id
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'truncate text-[11px] font-medium sm:text-xs',
                                        current && 'text-foreground',
                                        complete && 'text-foreground/80',
                                        !complete &&
                                            !current &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <span className="sm:hidden">
                                        {step.shortLabel}
                                    </span>
                                    <span className="hidden sm:inline">
                                        {step.label}
                                    </span>
                                </span>
                            </div>
                            {!last && (
                                <span
                                    className={cn(
                                        'mx-2 h-px min-w-4 flex-1 sm:mx-3',
                                        step.id < currentStep
                                            ? 'bg-primary/50'
                                            : 'bg-border',
                                    )}
                                    aria-hidden
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type OnboardingStep = {
    id: number;
    label: string;
    shortLabel: string;
};

const CLIENT_STEPS: OnboardingStep[] = [
    { id: 1, label: 'Profile', shortLabel: 'Profile' },
    { id: 2, label: 'Services & Care Plan', shortLabel: 'Services' },
    { id: 3, label: 'Review', shortLabel: 'Review' },
];

export const EMPLOYEE_ONBOARDING_STEPS: OnboardingStep[] = [
    { id: 1, label: 'Role & Profile', shortLabel: 'Role' },
    { id: 2, label: 'Contact & Address', shortLabel: 'Contact' },
    { id: 3, label: 'Availability', shortLabel: 'Avail.' },
    { id: 4, label: 'Education & Credentials', shortLabel: 'Creds' },
    { id: 5, label: 'References & History', shortLabel: 'Refs' },
    { id: 6, label: 'Security & Compliance', shortLabel: 'Secure' },
    { id: 7, label: 'Account & Review', shortLabel: 'Review' },
];

export function OnboardingStepper({
    currentStep,
    steps = CLIENT_STEPS,
    ariaLabel = 'Client onboarding progress',
}: {
    currentStep: number;
    steps?: readonly OnboardingStep[];
    ariaLabel?: string;
}) {
    return (
        <nav aria-label={ariaLabel}>
            <ol className="flex w-full items-center overflow-x-auto pb-1">
                {steps.map((step, index) => {
                    const complete = step.id < currentStep;
                    const current = step.id === currentStep;
                    const last = index === steps.length - 1;

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
                                        'mx-1.5 h-px min-w-3 flex-1 sm:mx-3',
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

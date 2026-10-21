import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    CriticalAlertRecord,
    VisitSafetyInstructionRecord,
} from '@/types/visit';

export function VisitCriticalSafetyPanel({
    visitId,
    criticalAlerts,
    safetyInstructions,
    canAcknowledge,
    canIssueInstruction,
}: {
    visitId: number;
    criticalAlerts: CriticalAlertRecord[];
    safetyInstructions: VisitSafetyInstructionRecord[];
    canAcknowledge: boolean;
    canIssueInstruction: boolean;
}) {
    const pendingAlerts = criticalAlerts.filter(
        (alert) => alert.requires_acknowledgment,
    );
    const acknowledgedAlerts = criticalAlerts.filter(
        (alert) => alert.acknowledged,
    );
    const pendingInstructions = safetyInstructions.filter(
        (instruction) => instruction.requires_acknowledgment,
    );
    const acknowledgedInstructions = safetyInstructions.filter(
        (instruction) => instruction.acknowledged,
    );

    const hasAnything =
        criticalAlerts.length > 0 ||
        safetyInstructions.length > 0 ||
        canIssueInstruction;

    if (!hasAnything) {
        return null;
    }

    return (
        <section className="border-destructive/40 from-destructive/10 via-card to-amber-500/10 lg:col-span-12 surface-panel bg-gradient-to-br p-4 md:p-5">
            <h2 className="text-sm font-semibold tracking-wide uppercase">
                Critical alerts & urgent safety instructions
            </h2>
            <p className="text-muted-foreground mt-1 text-xs">
                Acknowledge new or updated critical safety information before
                ordinary visit tasks. Clock-in is not blocked.
            </p>

            {pendingAlerts.length > 0 ? (
                <div className="mt-4 space-y-3">
                    {pendingAlerts.map((alert) => (
                        <div
                            key={`pending-alert-${alert.id}-v${alert.version}`}
                            className="border-destructive/50 bg-destructive/10 rounded-md border px-3 py-3"
                        >
                            <p className="text-xs font-semibold tracking-wide uppercase">
                                {alert.category_label}
                                {alert.is_urgent ? ' · Urgent' : ''}
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {alert.title}
                            </p>
                            <p className="mt-1 text-sm whitespace-pre-wrap">
                                {alert.body}
                            </p>
                            {canAcknowledge ? (
                                <Button
                                    type="button"
                                    className="mt-3 min-h-11 w-full sm:w-auto"
                                    onClick={() =>
                                        router.post(
                                            `/visits/${visitId}/critical-alerts/${alert.id}/acknowledge`,
                                        )
                                    }
                                >
                                    Acknowledge
                                </Button>
                            ) : (
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Acknowledgment required for the assigned
                                    DSP.
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            ) : null}

            {pendingInstructions.length > 0 ? (
                <div className="mt-4 space-y-3">
                    {pendingInstructions.map((instruction) => (
                        <div
                            key={`pending-instruction-${instruction.id}`}
                            className="border-destructive/50 bg-destructive/10 rounded-md border px-3 py-3"
                        >
                            <p className="text-xs font-semibold tracking-wide uppercase">
                                Urgent visit safety instruction
                            </p>
                            <p className="mt-1 text-sm whitespace-pre-wrap">
                                {instruction.content}
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Issued {instruction.effective_at_label}
                                {instruction.created_by_name
                                    ? ` by ${instruction.created_by_name}`
                                    : ''}
                            </p>
                            {canAcknowledge ? (
                                <Button
                                    type="button"
                                    className="mt-3 min-h-11 w-full sm:w-auto"
                                    onClick={() =>
                                        router.post(
                                            `/visits/${visitId}/safety-instructions/${instruction.id}/acknowledge`,
                                        )
                                    }
                                >
                                    Acknowledge
                                </Button>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : null}

            {acknowledgedAlerts.length > 0 ||
            acknowledgedInstructions.length > 0 ? (
                <div className="mt-4 space-y-2">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        Acknowledged (still visible)
                    </p>
                    {acknowledgedAlerts.map((alert) => (
                        <div
                            key={`acked-alert-${alert.id}-v${alert.version}`}
                            className="rounded-md border px-3 py-2 text-sm"
                        >
                            <span className="font-medium">{alert.title}</span>
                            {alert.acknowledged_at_label ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · acknowledged {alert.acknowledged_at_label}
                                </span>
                            ) : null}
                        </div>
                    ))}
                    {acknowledgedInstructions.map((instruction) => (
                        <div
                            key={`acked-instruction-${instruction.id}`}
                            className="rounded-md border px-3 py-2 text-sm"
                        >
                            <span className="font-medium">
                                Visit instruction
                            </span>
                            <span className="text-muted-foreground">
                                {' '}
                                · {instruction.content}
                            </span>
                            {instruction.acknowledged_at_label ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · acknowledged{' '}
                                    {instruction.acknowledged_at_label}
                                </span>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : null}

            {canIssueInstruction ? (
                <IssueSafetyInstructionForm visitId={visitId} />
            ) : null}
        </section>
    );
}

function IssueSafetyInstructionForm({ visitId }: { visitId: number }) {
    const form = useForm({
        content: '',
        is_urgent: true,
    });

    return (
        <form
            className="mt-5 space-y-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/visits/${visitId}/safety-instructions`, {
                    preserveScroll: true,
                    onSuccess: () => form.reset('content'),
                });
            }}
        >
            <div>
                <Label htmlFor={`visit-safety-instruction-${visitId}`}>
                    Issue urgent visit safety instruction
                </Label>
                <p className="text-muted-foreground mt-1 text-xs">
                    Applies to this active visit only. Does not rewrite the Care
                    Plan snapshot or visit tasks.
                </p>
                <textarea
                    id={`visit-safety-instruction-${visitId}`}
                    className={`${controlClassName} mt-2 h-auto py-2`}
                    value={form.data.content}
                    onChange={(event) =>
                        form.setData('content', event.target.value)
                    }
                    rows={3}
                />
                <InputError message={form.errors.content} />
            </div>
            <Button type="submit" disabled={form.processing || !form.data.content.trim()}>
                Issue instruction
            </Button>
        </form>
    );
}

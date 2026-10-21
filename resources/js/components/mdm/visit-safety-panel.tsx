import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { ActiveVisitRecord, VisitSafetyCheckRecord } from '@/types/visit';

export function VisitSafetyPanel({
    visit,
    canRecord,
}: {
    visit: ActiveVisitRecord;
    canRecord: boolean;
}) {
    const hasSupervision =
        visit.requires_general_safety_confirmation ||
        (visit.safety_checks?.length ?? 0) > 0 ||
        Boolean(visit.supervision_instruction);

    if (!hasSupervision) {
        return null;
    }

    return (
        <section className="border-primary/30 from-primary/10 via-card to-brand-cyan/10 lg:col-span-12 surface-panel bg-gradient-to-br p-4 md:p-5">
            <h2 className="text-sm font-semibold tracking-wide uppercase">
                Safety / supervision
            </h2>
            <p className="text-muted-foreground mt-1 text-xs">
                Timed checks use the Care Plan interval
                {visit.safety_check_interval_minutes
                    ? ` (every ${visit.safety_check_interval_minutes} minutes)`
                    : ''}
                . A 2-minute grace applies before a check is Late.
            </p>

            {visit.past_scheduled_end ? (
                <div className="border-destructive/40 bg-destructive/10 text-destructive mt-3 rounded-md border px-3 py-2 text-sm">
                    Visit Overdue / Past Scheduled End
                    {visit.minutes_past_scheduled_end != null
                        ? ` · ${visit.minutes_past_scheduled_end} min past end`
                        : ''}
                    . Continue care and clock out when ready — MDM does not
                    auto clock-out.
                </div>
            ) : null}

            {visit.supervision_instruction ? (
                <p className="mt-3 text-sm">
                    <span className="font-medium">Standing instruction: </span>
                    {visit.supervision_instruction}
                </p>
            ) : null}

            {visit.requires_general_safety_confirmation ? (
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    {visit.general_safety_confirmed ? (
                        <p className="text-sm font-medium">
                            Visit-level safety confirmation recorded.
                        </p>
                    ) : canRecord ? (
                        <Button
                            type="button"
                            className="min-h-11 w-full sm:w-auto"
                            onClick={() =>
                                router.post(
                                    `/visits/${visit.id}/safety-confirmation`,
                                )
                            }
                        >
                            Confirm visit safety
                        </Button>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Visit-level safety confirmation still required.
                        </p>
                    )}
                </div>
            ) : null}

            {(visit.safety_checks ?? []).length > 0 ? (
                <ul className="mt-4 space-y-3">
                    {(visit.safety_checks ?? []).map((check) => (
                        <SafetyCheckRow
                            key={check.id}
                            visitId={visit.id}
                            check={check}
                            canRecord={canRecord}
                        />
                    ))}
                </ul>
            ) : visit.safety_check_interval_minutes ? (
                <p className="text-muted-foreground mt-3 text-sm">
                    Timed checks will appear when the first interval is due.
                </p>
            ) : null}
        </section>
    );
}

function SafetyCheckRow({
    visitId,
    check,
    canRecord,
}: {
    visitId: number;
    check: VisitSafetyCheckRecord;
    canRecord: boolean;
}) {
    const form = useForm({
        reason: check.outcome_reason ?? '',
    });
    const needsReason =
        check.status === 'late' ||
        check.status === 'missed' ||
        check.requires_reason;

    if (check.is_documented) {
        return (
            <li className="rounded-md border px-3 py-2 text-sm">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <span className="font-medium">
                        Check #{check.sequence} · {check.status_label}
                    </span>
                    <span className="text-muted-foreground text-xs">
                        Due {check.due_at_label}
                        {check.completed_at_label
                            ? ` · Done ${check.completed_at_label}`
                            : ''}
                    </span>
                </div>
                {check.outcome_reason ? (
                    <p className="text-muted-foreground mt-1 text-xs">
                        {check.outcome_reason}
                    </p>
                ) : null}
            </li>
        );
    }

    return (
        <li className="rounded-md border px-3 py-2 text-sm">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="font-medium">
                    Check #{check.sequence} · {check.status_label}
                </span>
                <span className="text-muted-foreground text-xs">
                    Due {check.due_at_label}
                </span>
            </div>
            {canRecord ? (
                <form
                    className="mt-3 space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/visits/${visitId}/safety-checks/${check.id}/acknowledge`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    {needsReason ? (
                        <div className="space-y-1">
                            <Label htmlFor={`safety-reason-${check.id}`}>
                                Reason
                            </Label>
                            <textarea
                                id={`safety-reason-${check.id}`}
                                className="border-input bg-background min-h-16 w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                    ) : null}
                    <Button
                        type="submit"
                        className="min-h-11 w-full sm:w-auto"
                        disabled={form.processing}
                    >
                        {check.status === 'missed'
                            ? 'Document missed check'
                            : 'Acknowledge check'}
                    </Button>
                </form>
            ) : (
                <p className="text-muted-foreground mt-2 text-xs">
                    Awaiting DSP acknowledgment.
                </p>
            )}
        </li>
    );
}

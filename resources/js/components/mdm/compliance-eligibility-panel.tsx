import { Form } from '@inertiajs/react';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ComplianceEligibility } from '@/types/compliance';

export function ComplianceEligibilityPanel({
    eligibility,
    employeeId,
    canOverride = false,
}: {
    eligibility: ComplianceEligibility;
    employeeId?: number;
    canOverride?: boolean;
}) {
    const toneStatus =
        eligibility.tone === 'green'
            ? 'active'
            : eligibility.tone === 'yellow'
              ? 'pending'
              : 'expired';

    const overrideableHardStops = eligibility.requirements.filter(
        (row) =>
            row.is_hard_stop_condition &&
            row.allows_temporary_exception &&
            !row.exception_active,
    );

    return (
        <div className="grid gap-4">
            <Panel
                title="Service eligibility"
                description="Hard Stops block scheduling and Start Visit. Expiring Soon is a warning only. Temporary exceptions do not change underlying compliance state."
            >
                <div className="flex flex-wrap items-center gap-3">
                    <StatusBadge
                        status={toneStatus}
                        label={eligibility.tone_label}
                    />
                    <p className="text-sm">{eligibility.summary}</p>
                </div>

                {eligibility.requirements.length === 0 ? (
                    <div className="mt-4">
                        <EmptyState message="No active Compliance Requirements are configured for this employee. Expired credentials alone do not create a Hard Stop." />
                    </div>
                ) : (
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground text-xs uppercase">
                                <tr>
                                    <th className="pb-2 font-medium">
                                        Requirement
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Enforcement
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Condition
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Effect
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {eligibility.requirements.map((row) => (
                                    <tr key={row.requirement_id}>
                                        <td className="py-2">
                                            <p className="font-medium">
                                                {row.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {row.evidence_label ??
                                                    'No evidence on file'}
                                                {row.expires_on
                                                    ? ` · Expires ${row.expires_on}`
                                                    : ''}
                                            </p>
                                        </td>
                                        <td className="py-2">
                                            {row.enforcement_label}
                                            <p className="text-muted-foreground text-xs">
                                                {row.override_policy_label}
                                            </p>
                                        </td>
                                        <td className="py-2">
                                            <StatusBadge
                                                status={row.condition}
                                                label={row.condition_label}
                                            />
                                        </td>
                                        <td className="py-2 text-xs">
                                            {row.blocks_service
                                                ? 'Hard Stop — blocks service'
                                                : row.exception_active
                                                  ? 'Temporary exception active'
                                                  : row.is_warning
                                                    ? 'Warning only'
                                                    : 'Satisfied'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Panel>

            {eligibility.active_exceptions.length > 0 && (
                <Panel title="Active temporary exceptions">
                    <ul className="space-y-3 text-sm">
                        {eligibility.active_exceptions.map((exception) => (
                            <li key={exception.id}>
                                <p className="font-medium">
                                    {exception.requirement_name ??
                                        `Requirement #${exception.requirement_id}`}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {exception.reason} · {exception.created_by}{' '}
                                    · {exception.starts_at} → {exception.ends_at}
                                </p>
                                {canOverride && (
                                    <Form
                                        action={`/compliance-exceptions/${exception.id}/revoke`}
                                        method="post"
                                        className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-end"
                                    >
                                        <textarea
                                            name="revoke_reason"
                                            required
                                            placeholder="Revoke reason"
                                            className={cn(controlClassName, 'min-h-16')}
                                        />
                                        <Button type="submit" variant="secondary">
                                            Revoke
                                        </Button>
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            {canOverride && employeeId && overrideableHardStops.length > 0 && (
                <Panel
                    title="Apply temporary exception"
                    description="Admin / HR-compliance only. Supervisors cannot override Hard Stops. Non-Overrideable requirements are rejected."
                >
                    <Form
                        action={`/employees/${employeeId}/compliance-exceptions`}
                        method="post"
                        className="grid gap-3 md:grid-cols-2"
                    >
                        <label className="grid gap-1 text-sm md:col-span-2">
                            <span className="font-medium">Requirement</span>
                            <select
                                name="compliance_requirement_id"
                                required
                                className="border-input h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                {overrideableHardStops.map((row) => (
                                    <option
                                        key={row.requirement_id}
                                        value={row.requirement_id}
                                    >
                                        {row.name} ({row.condition_label})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Starts at</span>
                            <Input
                                type="datetime-local"
                                name="starts_at"
                                required
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Ends at</span>
                            <Input
                                type="datetime-local"
                                name="ends_at"
                                required
                            />
                        </label>
                        <label className="grid gap-1 text-sm md:col-span-2">
                            <span className="font-medium">Reason</span>
                            <textarea
                                name="reason"
                                required
                                minLength={5}
                                placeholder="Required audit reason for the temporary exception"
                                className={cn(controlClassName, 'min-h-24')}
                            />
                        </label>
                        <div className="md:col-span-2">
                            <Button type="submit">Apply exception</Button>
                        </div>
                    </Form>
                </Panel>
            )}
        </div>
    );
}

import { Form, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { caseload, revoke, team } from '@/routes/supervisors';
import type {
    OptionItem,
    SupervisorResponsibilities,
    SupervisorRoleEvent,
} from '@/types/directory';

function actionLabel(action: string): string {
    if (action === 'promoted') {
        return 'Appointed Supervisor';
    }
    if (action === 'revoked') {
        return 'Supervisor duties revoked';
    }
    if (action === 'reassigned_team') {
        return 'DSP team transferred';
    }
    if (action === 'reassigned_caseload') {
        return 'Client caseload transferred';
    }

    return action;
}

export function SupervisorManagement({
    supervisorId,
    responsibilities,
    replacements,
    history,
}: {
    supervisorId: number;
    responsibilities: SupervisorResponsibilities;
    replacements: OptionItem[];
    history: SupervisorRoleEvent[];
}) {
    const page = usePage();
    const errors = page.props.errors as Record<string, string>;
    const [teamIds, setTeamIds] = useState<number[]>([]);
    const [clientIds, setClientIds] = useState<number[]>([]);

    const selectedDsps = useMemo(
        () => responsibilities.dsps.filter((dsp) => teamIds.includes(dsp.id)),
        [responsibilities.dsps, teamIds],
    );
    const selectedClients = useMemo(
        () =>
            responsibilities.clients.filter((client) =>
                clientIds.includes(client.id),
            ),
        [responsibilities.clients, clientIds],
    );

    function toggle(id: number, selected: number[], setSelected: (ids: number[]) => void) {
        setSelected(
            selected.includes(id)
                ? selected.filter((item) => item !== id)
                : [...selected, id],
        );
    }

    return (
        <div className="grid gap-4 px-4 md:grid-cols-12 md:px-6">
            <Panel
                title="DSP team"
                description="Transfer current reports. Historical visits stay with the original Supervisor of record."
                className="md:col-span-6"
            >
                {responsibilities.dsps.length === 0 ? (
                    <EmptyState compact message="No DSPs currently report to this Supervisor." />
                ) : (
                    <Form
                        action={team.url(supervisorId)}
                        method="post"
                        className="space-y-3"
                    >
                        <ul className="max-h-56 space-y-2 overflow-y-auto">
                            {responsibilities.dsps.map((dsp) => (
                                <li key={dsp.id}>
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="employee_ids[]"
                                            value={dsp.id}
                                            checked={teamIds.includes(dsp.id)}
                                            onChange={() =>
                                                toggle(dsp.id, teamIds, setTeamIds)
                                            }
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="font-medium">{dsp.name}</span>
                                            <span className="text-muted-foreground block text-xs">
                                                {dsp.employee_number}
                                                {dsp.job_title ? ` · ${dsp.job_title}` : ''}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                        <ReplacementSelect replacements={replacements} />
                        {selectedDsps.length > 0 && (
                            <p className="text-muted-foreground text-xs">
                                Transfer {selectedDsps.map((dsp) => dsp.name).join(', ')} to
                                the selected Supervisor. They will lose this team membership
                                immediately.
                            </p>
                        )}
                        <InputError message={errors.employee_ids || errors.replacement_employee_id} />
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={teamIds.length === 0 || replacements.length === 0}
                        >
                            Transfer selected DSPs
                        </Button>
                    </Form>
                )}
            </Panel>

            <Panel
                title="Client caseload"
                description="Clients are assigned directly to a Supervisor. Transfer current ownership only."
                className="md:col-span-6"
            >
                {responsibilities.clients.length === 0 ? (
                    <EmptyState compact message="No clients are assigned to this Supervisor." />
                ) : (
                    <Form
                        action={caseload.url(supervisorId)}
                        method="post"
                        className="space-y-3"
                    >
                        <ul className="max-h-56 space-y-2 overflow-y-auto">
                            {responsibilities.clients.map((client) => (
                                <li key={client.id}>
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="client_ids[]"
                                            value={client.id}
                                            checked={clientIds.includes(client.id)}
                                            onChange={() =>
                                                toggle(client.id, clientIds, setClientIds)
                                            }
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="font-medium">{client.name}</span>
                                            <span className="text-muted-foreground block text-xs">
                                                {client.client_number} · {client.status_label}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                        <ReplacementSelect replacements={replacements} />
                        {selectedClients.length > 0 && (
                            <p className="text-muted-foreground text-xs">
                                Move {selectedClients.map((client) => client.name).join(', ')}{' '}
                                to the selected Supervisor. Previous Supervisor access ends
                                when they no longer own these clients.
                            </p>
                        )}
                        <InputError message={errors.client_ids || errors.replacement_employee_id} />
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={clientIds.length === 0 || replacements.length === 0}
                        >
                            Transfer selected clients
                        </Button>
                    </Form>
                )}
            </Panel>

            <Panel
                title="Revoke Supervisor duties"
                description="This changes System Role, not Job Title. The employee record stays in place."
                className="md:col-span-8"
            >
                <div className="text-muted-foreground mb-3 grid gap-1 text-sm">
                    <p>
                        {responsibilities.dsp_count} DSP reports ·{' '}
                        {responsibilities.client_count} assigned clients
                    </p>
                    <p>
                        Pending work that follows this team: {responsibilities.pending_availability_count} availability,{' '}
                        {responsibilities.pending_time_off_count} time off. Attendance correction
                        approval stays with Admin ({responsibilities.pending_attendance_correction_count} pending).
                    </p>
                    {responsibilities.blocks_revocation ? (
                        <p>
                            Active responsibilities must transfer to another Supervisor before
                            duties can be revoked.
                        </p>
                    ) : (
                        <p>
                            No active DSP or client responsibilities. Direct revocation is
                            allowed. System Role will become DSP.
                        </p>
                    )}
                </div>
                <Form action={revoke.url(supervisorId)} method="post" className="space-y-3">
                    {responsibilities.blocks_revocation && (
                        <ReplacementSelect replacements={replacements} required />
                    )}
                    <InputError message={errors.replacement_employee_id || errors.employee} />
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={responsibilities.blocks_revocation && replacements.length === 0}
                    >
                        {responsibilities.blocks_revocation
                            ? 'Transfer responsibilities and revoke'
                            : 'Revoke Supervisor duties'}
                    </Button>
                </Form>
            </Panel>

            <Panel title="Role history" className="md:col-span-4">
                {history.length === 0 ? (
                    <EmptyState compact message="No Supervisor role changes recorded yet." />
                ) : (
                    <ul className="space-y-3 text-sm">
                        {[...history].reverse().map((event, index) => (
                            <li key={`${event.at}-${index}`}>
                                <p className="font-medium">{actionLabel(event.action)}</p>
                                <p className="text-muted-foreground text-xs">
                                    {event.previous_role ?? '—'} → {event.new_role ?? '—'}
                                    {event.replacement_name
                                        ? ` · Replacement: ${event.replacement_name}`
                                        : ''}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {event.actor_name} · {event.at}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}

function ReplacementSelect({
    replacements,
    required = false,
}: {
    replacements: OptionItem[];
    required?: boolean;
}) {
    if (replacements.length === 0) {
        return (
            <p className="text-muted-foreground text-xs">
                Appoint another active Supervisor before transferring or revoking with
                active responsibilities.
            </p>
        );
    }

    return (
        <div className="space-y-1">
            <Label htmlFor="replacement_employee_id">Replacement Supervisor</Label>
            <select
                id="replacement_employee_id"
                name="replacement_employee_id"
                required={required}
                className={controlClassName}
                defaultValue=""
            >
                <option value="">Select Supervisor</option>
                {replacements.map((supervisor) => (
                    <option key={supervisor.id} value={supervisor.id}>
                        {supervisor.name}
                        {supervisor.employee_number ? ` · ${supervisor.employee_number}` : ''}
                    </option>
                ))}
            </select>
        </div>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { OperationsBoardView } from '@/components/mdm/operations-board';
import { SupervisorManagement } from '@/components/mdm/supervisor-management';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { show as showEmployee } from '@/routes/employees';
import { index as supervisorsIndex, show } from '@/routes/supervisors';
import { store as storeTemporaryCoverage } from '@/routes/supervisors/temporary-coverages';
import { revoke as revokeTemporaryCoverage } from '@/routes/temporary-supervisor-coverages';
import type {
    EmployeeDetail,
    OptionItem,
    SupervisorResponsibilities,
    SupervisorRoleEvent,
} from '@/types/directory';
import type { OperationsBoard } from '@/types/operations';

type TemporaryCoverageRow = {
    id: number;
    mode: string;
    mode_label: string;
    status_label: string;
    is_active: boolean;
    covering_supervisor_id: number;
    covering_supervisor_name: string | null;
    client_ids?: number[];
    employee_ids?: number[];
    deferred_remove_client_ids?: number[];
    deferred_remove_employee_ids?: number[];
    starts_at: string | null;
    ends_at: string | null;
    note: string | null;
    revoke_reason: string | null;
};

export default function SupervisorsShow({
    supervisor,
    operations,
    responsibilities,
    replacements,
    role_change_history,
    temporary_coverages = [],
    can,
}: {
    supervisor: EmployeeDetail & {
        assigned_dsp_count: number;
        assigned_client_count: number;
    };
    operations: OperationsBoard;
    responsibilities: SupervisorResponsibilities | null;
    replacements: OptionItem[];
    role_change_history: SupervisorRoleEvent[];
    temporary_coverages?: TemporaryCoverageRow[];
    can: { manage: boolean };
}) {
    const coverageForm = useForm({
        covering_supervisor_id: '',
        mode: 'full_caseload',
        client_ids: [] as number[],
        employee_ids: [] as number[],
        starts_at: '',
        ends_at: '',
        note: '',
    });
    const revokeForm = useForm({
        revoke_reason: '',
    });
    const toggleCoverageId = (
        field: 'client_ids' | 'employee_ids',
        id: number,
        checked: boolean,
    ) => {
        const current = coverageForm.data[field];
        coverageForm.setData(
            field,
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );
    };
    return (
        <>
            <Head title={supervisor.name} />
            <div className="flex flex-1 flex-col gap-5 pb-6">
                <div className="px-4 pt-4 md:px-6">
                    <IdentityHeader
                        leading={
                            <ProfilePhoto
                                name={supervisor.name}
                                photoUrl={supervisor.photo_url}
                                initials={supervisor.initials}
                                size="lg"
                            />
                        }
                        eyebrow={
                            <BackLink href={supervisorsIndex()}>
                                Back to Supervisors
                            </BackLink>
                        }
                        title={supervisor.name}
                        meta={
                            <>
                                <StatusBadge
                                    status={supervisor.employment_status}
                                    label={supervisor.employment_status_label}
                                />
                                <span className="text-muted-foreground text-sm">
                                    {supervisor.assigned_dsp_count} DSPs ·{' '}
                                    {supervisor.assigned_client_count} clients
                                </span>
                            </>
                        }
                        actions={
                            <Button asChild variant="secondary">
                                <Link href={showEmployee.url(supervisor.id)}>
                                    Employee record
                                </Link>
                            </Button>
                        }
                    />
                </div>

                <div className="grid gap-4 px-4 md:grid-cols-12 md:px-6">
                    <Panel title="Identity" className="md:col-span-6">
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Employee number
                                </dt>
                                <dd>{supervisor.employee_number}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Email</dt>
                                <dd>{supervisor.email ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    System Role
                                </dt>
                                <dd>
                                    {supervisor.login_role ??
                                        supervisor.job_type_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Job title
                                </dt>
                                <dd>{supervisor.job_title ?? '—'}</dd>
                            </div>
                        </dl>
                    </Panel>
                    <Panel title="Workload" className="md:col-span-6">
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Assigned DSPs
                                </dt>
                                <dd>{supervisor.assigned_dsp_count}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Assigned clients
                                </dt>
                                <dd>{supervisor.assigned_client_count}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Visits today
                                </dt>
                                <dd>{operations.today_visits.length}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Open exceptions
                                </dt>
                                <dd>{operations.exceptions.open.length}</dd>
                            </div>
                        </dl>
                    </Panel>
                </div>

                {can.manage && responsibilities && (
                    <SupervisorManagement
                        supervisorId={supervisor.id}
                        responsibilities={responsibilities}
                        replacements={replacements}
                        history={role_change_history}
                    />
                )}

                {can.manage ? (
                    <div className="px-4 md:px-6">
                        <Panel
                            title="Temporary Supervisor coverage"
                            description="Admin-controlled, time-bounded coverage. Permanent caseload ownership and private-message access are unchanged. Expired coverage cannot be backdated."
                        >
                            <form
                                className="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    coverageForm.transform((data) => ({
                                        ...data,
                                        covering_supervisor_id: Number(
                                            data.covering_supervisor_id,
                                        ),
                                        client_ids:
                                            data.mode === 'selective'
                                                ? data.client_ids
                                                : [],
                                        employee_ids:
                                            data.mode === 'selective'
                                                ? data.employee_ids
                                                : [],
                                    }));
                                    coverageForm.post(
                                        storeTemporaryCoverage.url(
                                            supervisor.id,
                                        ),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <div className="space-y-1">
                                    <Label htmlFor="covering_supervisor_id">
                                        Covering Supervisor
                                    </Label>
                                    <select
                                        id="covering_supervisor_id"
                                        className={controlClassName}
                                        value={
                                            coverageForm.data
                                                .covering_supervisor_id
                                        }
                                        onChange={(e) =>
                                            coverageForm.setData(
                                                'covering_supervisor_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        <option value="">Select…</option>
                                        {replacements.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            coverageForm.errors
                                                .covering_supervisor_id
                                        }
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="coverage_mode">Mode</Label>
                                    <select
                                        id="coverage_mode"
                                        className={controlClassName}
                                        value={coverageForm.data.mode}
                                        onChange={(e) =>
                                            coverageForm.setData(
                                                'mode',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="full_caseload">
                                            Full caseload
                                        </option>
                                        <option value="selective">
                                            Selective
                                        </option>
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="coverage_starts_at">
                                        Starts
                                    </Label>
                                    <input
                                        id="coverage_starts_at"
                                        type="datetime-local"
                                        className={controlClassName}
                                        value={coverageForm.data.starts_at}
                                        onChange={(e) =>
                                            coverageForm.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={coverageForm.errors.starts_at}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="coverage_ends_at">
                                        Ends
                                    </Label>
                                    <input
                                        id="coverage_ends_at"
                                        type="datetime-local"
                                        className={controlClassName}
                                        value={coverageForm.data.ends_at}
                                        onChange={(e) =>
                                            coverageForm.setData(
                                                'ends_at',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={coverageForm.errors.ends_at}
                                    />
                                </div>
                                <div className="space-y-1 xl:col-span-2">
                                    <Label htmlFor="coverage_note">
                                        Note (optional)
                                    </Label>
                                    <input
                                        id="coverage_note"
                                        className={controlClassName}
                                        value={coverageForm.data.note}
                                        onChange={(e) =>
                                            coverageForm.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                {coverageForm.data.mode === 'selective' &&
                                    responsibilities && (
                                        <div className="space-y-3 xl:col-span-5">
                                            <div className="grid gap-3 md:grid-cols-2">
                                                <fieldset className="space-y-2">
                                                    <legend className="text-sm font-medium">
                                                        Clients
                                                    </legend>
                                                    {responsibilities.clients
                                                        .length === 0 ? (
                                                        <p className="text-muted-foreground text-xs">
                                                            No clients on this
                                                            caseload.
                                                        </p>
                                                    ) : (
                                                        <ul className="max-h-40 space-y-1 overflow-y-auto text-sm">
                                                            {responsibilities.clients.map(
                                                                (client) => (
                                                                    <li
                                                                        key={
                                                                            client.id
                                                                        }
                                                                    >
                                                                        <label className="flex items-center gap-2">
                                                                            <input
                                                                                type="checkbox"
                                                                                checked={coverageForm.data.client_ids.includes(
                                                                                    client.id,
                                                                                )}
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    toggleCoverageId(
                                                                                        'client_ids',
                                                                                        client.id,
                                                                                        e
                                                                                            .target
                                                                                            .checked,
                                                                                    )
                                                                                }
                                                                            />
                                                                            <span>
                                                                                {
                                                                                    client.name
                                                                                }
                                                                            </span>
                                                                        </label>
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    )}
                                                    <InputError
                                                        message={
                                                            coverageForm.errors
                                                                .client_ids
                                                        }
                                                    />
                                                </fieldset>
                                                <fieldset className="space-y-2">
                                                    <legend className="text-sm font-medium">
                                                        DSPs
                                                    </legend>
                                                    {responsibilities.dsps
                                                        .length === 0 ? (
                                                        <p className="text-muted-foreground text-xs">
                                                            No DSPs reporting to
                                                            this Supervisor.
                                                        </p>
                                                    ) : (
                                                        <ul className="max-h-40 space-y-1 overflow-y-auto text-sm">
                                                            {responsibilities.dsps.map(
                                                                (dsp) => (
                                                                    <li
                                                                        key={
                                                                            dsp.id
                                                                        }
                                                                    >
                                                                        <label className="flex items-center gap-2">
                                                                            <input
                                                                                type="checkbox"
                                                                                checked={coverageForm.data.employee_ids.includes(
                                                                                    dsp.id,
                                                                                )}
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    toggleCoverageId(
                                                                                        'employee_ids',
                                                                                        dsp.id,
                                                                                        e
                                                                                            .target
                                                                                            .checked,
                                                                                    )
                                                                                }
                                                                            />
                                                                            <span>
                                                                                {
                                                                                    dsp.name
                                                                                }
                                                                            </span>
                                                                        </label>
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    )}
                                                    <InputError
                                                        message={
                                                            coverageForm.errors
                                                                .employee_ids
                                                        }
                                                    />
                                                </fieldset>
                                            </div>
                                        </div>
                                    )}
                                <div className="xl:col-span-5">
                                    <Button
                                        type="submit"
                                        disabled={coverageForm.processing}
                                    >
                                        {coverageForm.data.mode === 'selective'
                                            ? 'Grant selective coverage'
                                            : 'Grant full caseload coverage'}
                                    </Button>
                                </div>
                            </form>

                            {temporary_coverages.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No temporary coverage grants yet.
                                </p>
                            ) : (
                                <ul className="space-y-3 text-sm">
                                    {temporary_coverages.map((row) => (
                                        <li
                                            key={row.id}
                                            className="border-border rounded-md border p-3"
                                        >
                                            <p className="font-medium">
                                                {row.covering_supervisor_name} ·{' '}
                                                {row.mode_label} ·{' '}
                                                {row.status_label}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {row.starts_at} → {row.ends_at}
                                            </p>
                                            {row.mode === 'selective' ? (
                                                <p className="text-muted-foreground text-xs">
                                                    {(row.client_ids?.length ??
                                                        0) +
                                                        (row
                                                            .deferred_remove_client_ids
                                                            ?.length ??
                                                            0)}{' '}
                                                    clients ·{' '}
                                                    {(row.employee_ids
                                                        ?.length ?? 0) +
                                                        (row
                                                            .deferred_remove_employee_ids
                                                            ?.length ??
                                                            0)}{' '}
                                                    DSPs
                                                    {(row
                                                        .deferred_remove_client_ids
                                                        ?.length ?? 0) +
                                                        (row
                                                            .deferred_remove_employee_ids
                                                            ?.length ?? 0) >
                                                    0
                                                        ? ' (includes deferred active-visit removals)'
                                                        : ''}
                                                </p>
                                            ) : null}
                                            {row.is_active ? (
                                                <form
                                                    className="mt-2 flex flex-wrap items-end gap-2"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        revokeForm.post(
                                                            revokeTemporaryCoverage.url(
                                                                row.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <input
                                                        className={
                                                            controlClassName
                                                        }
                                                        placeholder="Revoke reason"
                                                        value={
                                                            revokeForm.data
                                                                .revoke_reason
                                                        }
                                                        onChange={(e) =>
                                                            revokeForm.setData(
                                                                'revoke_reason',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        disabled={
                                                            revokeForm.processing
                                                        }
                                                    >
                                                        Revoke now
                                                    </Button>
                                                </form>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>
                ) : null}

                <OperationsBoardView
                    operations={operations}
                    heading="Caseload"
                    description={`Operational board for ${supervisor.name} on ${operations.today} (${operations.timezone_label}).`}
                />
            </div>
        </>
    );
}

SupervisorsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Supervisors', href: supervisorsIndex() },
        { title: 'Supervisor', href: show.url(0) },
    ],
};

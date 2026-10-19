import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AuthorizationRecord } from '@/types/directory';

type Option = { value: string; label: string };

export function ClientAuthorizationsPanel({
    clientId,
    authorizations,
    units,
    statuses,
    canManage,
    activeVisitWarning = null,
}: {
    clientId: number;
    authorizations: AuthorizationRecord[];
    units: Option[];
    statuses: Option[];
    canManage: boolean;
    activeVisitWarning?: string | null;
}) {
    const form = useForm({
        authorization_number: '',
        payer: '',
        service_type: '',
        starts_on: '',
        ends_on: '',
        authorized_units: '',
        unit: units[0]?.value ?? 'hour',
        status: 'active',
        notes: '',
    });

    return (
        <section className="surface-panel space-y-4 p-4 md:p-5">
            <div>
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Authorizations
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Operational service authorizations with actual, projected,
                    and remaining usage. Records are retained; hard delete is
                    not available.
                </p>
            </div>

            {activeVisitWarning ? (
                <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {activeVisitWarning}
                </p>
            ) : null}

            {authorizations.length === 0 ? (
                <EmptyState message="No authorizations on file." />
            ) : (
                <ul className="space-y-3">
                    {authorizations.map((authorization) => (
                        <li
                            key={authorization.id}
                            className="space-y-2 rounded-md border px-3 py-3"
                        >
                            <div className="flex justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium">
                                        {authorization.authorization_number}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {authorization.payer} ·{' '}
                                        {authorization.service_type}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {authorization.starts_on} –{' '}
                                        {authorization.ends_on ?? 'open'} ·{' '}
                                        {authorization.authorized_units}{' '}
                                        {authorization.unit}
                                    </p>
                                </div>
                                <StatusBadge
                                    status={authorization.status}
                                    label={authorization.status_label}
                                />
                            </div>
                            {authorization.usage ? (
                                <div className="text-muted-foreground grid gap-1 text-xs sm:grid-cols-4">
                                    <span>
                                        Actual: {authorization.usage.actual_used}
                                    </span>
                                    <span>
                                        Projected:{' '}
                                        {authorization.usage.projected_future}
                                    </span>
                                    <span>
                                        Total:{' '}
                                        {authorization.usage.projected_total}
                                    </span>
                                    <span>
                                        Remaining:{' '}
                                        {authorization.usage.remaining}
                                    </span>
                                </div>
                            ) : null}
                            {authorization.usage?.warnings?.length ? (
                                <ul className="space-y-1 text-xs text-amber-800 dark:text-amber-200">
                                    {authorization.usage.warnings.map(
                                        (warning) => (
                                            <li key={warning}>{warning}</li>
                                        ),
                                    )}
                                </ul>
                            ) : null}
                            {canManage ? (
                                <AuthorizationEditForm
                                    clientId={clientId}
                                    authorization={authorization}
                                    units={units}
                                    statuses={statuses}
                                />
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {canManage ? (
                <form
                    className="grid gap-3 border-t pt-4 sm:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/clients/${clientId}/authorizations`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <p className="text-sm font-medium sm:col-span-2">
                        Add authorization
                    </p>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_number">Authorization number</Label>
                        <Input
                            id="auth_number"
                            value={form.data.authorization_number}
                            onChange={(e) =>
                                form.setData(
                                    'authorization_number',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.authorization_number} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_payer">Payer</Label>
                        <Input
                            id="auth_payer"
                            value={form.data.payer}
                            onChange={(e) =>
                                form.setData('payer', e.target.value)
                            }
                        />
                        <InputError message={form.errors.payer} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_service">Service type</Label>
                        <Input
                            id="auth_service"
                            value={form.data.service_type}
                            onChange={(e) =>
                                form.setData('service_type', e.target.value)
                            }
                        />
                        <InputError message={form.errors.service_type} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_units">Authorized units</Label>
                        <Input
                            id="auth_units"
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.authorized_units}
                            onChange={(e) =>
                                form.setData(
                                    'authorized_units',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.authorized_units} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_starts">Starts on</Label>
                        <Input
                            id="auth_starts"
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                        />
                        <InputError message={form.errors.starts_on} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_ends">Ends on</Label>
                        <Input
                            id="auth_ends"
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                        />
                        <InputError message={form.errors.ends_on} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_unit">Unit</Label>
                        <select
                            id="auth_unit"
                            className={controlClassName}
                            value={form.data.unit}
                            onChange={(e) =>
                                form.setData('unit', e.target.value)
                            }
                        >
                            {units.map((unit) => (
                                <option key={unit.value} value={unit.value}>
                                    {unit.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="auth_status">Status</Label>
                        <select
                            id="auth_status"
                            className={controlClassName}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save authorization
                        </Button>
                    </div>
                </form>
            ) : null}
        </section>
    );
}

function AuthorizationEditForm({
    clientId,
    authorization,
    units,
    statuses,
}: {
    clientId: number;
    authorization: AuthorizationRecord;
    units: Option[];
    statuses: Option[];
}) {
    const form = useForm({
        authorization_number: authorization.authorization_number,
        payer: authorization.payer,
        service_type: authorization.service_type,
        starts_on: authorization.starts_on ?? '',
        ends_on: authorization.ends_on ?? '',
        authorized_units: String(authorization.authorized_units),
        unit: authorization.unit_value ?? 'hour',
        status: authorization.status,
        notes: authorization.notes ?? '',
    });

    return (
        <form
            className="grid gap-2 border-t pt-2 sm:grid-cols-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(
                    `/clients/${clientId}/authorizations/${authorization.id}`,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="space-y-1">
                <Label className="text-xs">Status</Label>
                <select
                    className={controlClassName}
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-1">
                <Label className="text-xs">Ends on</Label>
                <Input
                    type="date"
                    value={form.data.ends_on}
                    onChange={(e) => form.setData('ends_on', e.target.value)}
                />
            </div>
            <div className="space-y-1">
                <Label className="text-xs">Authorized units</Label>
                <Input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.authorized_units}
                    onChange={(e) =>
                        form.setData('authorized_units', e.target.value)
                    }
                />
            </div>
            <div className="sm:col-span-3">
                <Button
                    type="submit"
                    size="sm"
                    variant="secondary"
                    disabled={form.processing}
                >
                    Update authorization
                </Button>
                <InputError
                    message={
                        form.errors.status ||
                        form.errors.ends_on ||
                        form.errors.authorized_units ||
                        form.errors.authorization_number
                    }
                />
            </div>
        </form>
    );
}

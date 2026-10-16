import { Form, Head } from '@inertiajs/react';
import { Field, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ProfileField = {
    field: string;
    label: string;
    current: string | null;
    proposed: string | null;
};

export default function MyProfileChanges({
    employee,
    requests,
    has_pending,
}: {
    employee: {
        id: number;
        name: string;
        phone: string | null;
        cell_phone: string | null;
        home_phone: string | null;
        alternate_phone: string | null;
        address_line_1: string | null;
        address_line_2: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
        emergency_contact_name: string | null;
        emergency_contact_relationship: string | null;
        emergency_contact_phone: string | null;
    };
    requests: Array<{
        id: number;
        status: string;
        reason: string | null;
        submitted_at: string | null;
        review_note: string | null;
        fields: ProfileField[];
    }>;
    has_pending: boolean;
}) {
    return (
        <>
            <Head title="My Personal Information" />
            <div className="page-shell">
                <div>
                    <BackLink href={dashboard()}>Back to Dashboard</BackLink>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        My Personal Information
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Submit phone, address, or emergency-contact changes for
                        supervisor review. Current values stay authoritative until
                        approved. Official employment fields remain
                        management-controlled.
                    </p>
                </div>

                <Panel title="Current authoritative profile">
                    <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                        <Item label="Cell phone" value={employee.cell_phone ?? employee.phone} />
                        <Item label="Home phone" value={employee.home_phone} />
                        <Item label="Address" value={[employee.address_line_1, employee.city, employee.state, employee.postal_code].filter(Boolean).join(', ')} />
                        <Item label="Emergency contact" value={[employee.emergency_contact_name, employee.emergency_contact_phone].filter(Boolean).join(' · ')} />
                    </dl>
                </Panel>

                <Panel
                    title="Request a change"
                    description={
                        has_pending
                            ? 'A pending request already exists. Wait for review before submitting another.'
                            : 'Only changed fields are sent for approval.'
                    }
                >
                    {has_pending ? (
                        <EmptyState message="Resolve the pending request before submitting another change." />
                    ) : (
                        <Form
                            action="/my-profile-changes"
                            method="post"
                            className="grid gap-3 md:grid-cols-2"
                        >
                            <Field label="Cell phone" htmlFor="cell_phone">
                                <Input
                                    id="cell_phone"
                                    name="cell_phone"
                                    defaultValue={employee.cell_phone ?? ''}
                                />
                            </Field>
                            <Field label="Home phone" htmlFor="home_phone">
                                <Input
                                    id="home_phone"
                                    name="home_phone"
                                    defaultValue={employee.home_phone ?? ''}
                                />
                            </Field>
                            <Field label="Alternate phone" htmlFor="alternate_phone">
                                <Input
                                    id="alternate_phone"
                                    name="alternate_phone"
                                    defaultValue={employee.alternate_phone ?? ''}
                                />
                            </Field>
                            <Field label="Address line 1" htmlFor="address_line_1">
                                <Input
                                    id="address_line_1"
                                    name="address_line_1"
                                    defaultValue={employee.address_line_1 ?? ''}
                                />
                            </Field>
                            <Field label="Address line 2" htmlFor="address_line_2">
                                <Input
                                    id="address_line_2"
                                    name="address_line_2"
                                    defaultValue={employee.address_line_2 ?? ''}
                                />
                            </Field>
                            <Field label="City" htmlFor="city">
                                <Input id="city" name="city" defaultValue={employee.city ?? ''} />
                            </Field>
                            <Field label="State" htmlFor="state">
                                <Input id="state" name="state" defaultValue={employee.state ?? ''} />
                            </Field>
                            <Field label="Postal code" htmlFor="postal_code">
                                <Input
                                    id="postal_code"
                                    name="postal_code"
                                    defaultValue={employee.postal_code ?? ''}
                                />
                            </Field>
                            <Field
                                label="Emergency contact name"
                                htmlFor="emergency_contact_name"
                            >
                                <Input
                                    id="emergency_contact_name"
                                    name="emergency_contact_name"
                                    defaultValue={employee.emergency_contact_name ?? ''}
                                />
                            </Field>
                            <Field
                                label="Emergency contact relationship"
                                htmlFor="emergency_contact_relationship"
                            >
                                <Input
                                    id="emergency_contact_relationship"
                                    name="emergency_contact_relationship"
                                    defaultValue={
                                        employee.emergency_contact_relationship ?? ''
                                    }
                                />
                            </Field>
                            <Field
                                label="Emergency contact phone"
                                htmlFor="emergency_contact_phone"
                            >
                                <Input
                                    id="emergency_contact_phone"
                                    name="emergency_contact_phone"
                                    defaultValue={employee.emergency_contact_phone ?? ''}
                                />
                            </Field>
                            <div className="md:col-span-2">
                                <label className="text-sm font-medium" htmlFor="reason">
                                    Reason
                                </label>
                                <textarea
                                    id="reason"
                                    name="reason"
                                    rows={2}
                                    className={`${controlClassName} mt-1 h-auto py-2`}
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Button type="submit">Submit for review</Button>
                            </div>
                        </Form>
                    )}
                </Panel>

                <Panel title="Request history">
                    {requests.length === 0 ? (
                        <EmptyState message="No personal information requests yet." />
                    ) : (
                        <ul className="space-y-3">
                            {requests.map((row) => (
                                <li
                                    key={row.id}
                                    className="border-border/60 border-b pb-3 last:border-0"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge status={row.status} label={row.status} />
                                        <span className="text-muted-foreground text-xs">
                                            {row.submitted_at}
                                        </span>
                                    </div>
                                    <ul className="mt-2 text-sm">
                                        {row.fields.map((field) => (
                                            <li key={field.field}>
                                                {field.label}: {field.current || '—'} →{' '}
                                                {field.proposed || '—'}
                                            </li>
                                        ))}
                                    </ul>
                                    {row.review_note ? (
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Review note: {row.review_note}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </>
    );
}

function Item({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs uppercase">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

MyProfileChanges.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Personal Information', href: '/my-profile-changes' },
    ],
};

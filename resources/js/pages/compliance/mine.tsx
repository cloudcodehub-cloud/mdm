import { Form, Head } from '@inertiajs/react';
import { ComplianceEligibilityPanel } from '@/components/mdm/compliance-eligibility-panel';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type {
    ComplianceEligibility,
    ComplianceEvidenceItem,
} from '@/types/compliance';

export default function MyCompliance({
    employee,
    tone,
    tone_label,
    summary,
    eligible_for_service,
    hard_stop,
    warnings,
    hard_stops,
    requirements,
    active_exceptions,
    block_reason,
    credentials,
    trainings,
    expiring_soon_days,
    reminder_milestones,
    can_submit_evidence = false,
    credential_type_options = [],
}: ComplianceEligibility & {
    employee: { id: number; name: string; employee_number: string };
    credentials: ComplianceEvidenceItem[];
    trainings: ComplianceEvidenceItem[];
    expiring_soon_days: number;
    reminder_milestones: number[];
    read_only: boolean;
    can_submit_evidence?: boolean;
    credential_type_options?: Array<{ value: string; label: string }>;
}) {
    const eligibility: ComplianceEligibility = {
        eligible_for_service,
        hard_stop,
        tone,
        tone_label,
        summary,
        warnings,
        hard_stops,
        requirements,
        active_exceptions,
        block_reason,
    };

    return (
        <>
            <Head title="My Compliance" />
            <div className="page-shell">
                <div>
                    <BackLink href={dashboard()}>Back to Dashboard</BackLink>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        My Compliance
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        View credentials, training, and service eligibility.
                        You may submit evidence for Admin verification. Pending
                        Review does not restore eligibility or override a Hard
                        Stop.
                    </p>
                </div>

                <ComplianceEligibilityPanel eligibility={eligibility} />

                <Panel
                    title="Credentials"
                    description={`Derived from recorded dates and the ${expiring_soon_days}-day expiring-soon threshold. Reminder milestones: ${reminder_milestones.join(' / ')} days.`}
                >
                    <EvidenceTable rows={credentials} empty="No credentials on file." />
                </Panel>

                {can_submit_evidence && (
                    <Panel
                        title="Submit credential evidence"
                        description="Uploaded evidence stays distinct from verified eligibility until Admin verifies it."
                    >
                        <Form
                            action={`/employees/${employee.id}/compliance-evidence`}
                            method="post"
                            encType="multipart/form-data"
                            className="grid gap-3 md:grid-cols-2"
                        >
                            <input type="hidden" name="kind" value="credential" />
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Type</span>
                                <select name="type" required className={controlClassName}>
                                    {credential_type_options.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Name</span>
                                <Input name="name" required />
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Issuer</span>
                                <Input name="issuer" />
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Expires on</span>
                                <Input type="date" name="expires_on" />
                            </label>
                            <label className="grid gap-1 text-sm md:col-span-2">
                                <span className="font-medium">Supporting document</span>
                                <Input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                            </label>
                            <div className="md:col-span-2">
                                <Button type="submit">Submit for review</Button>
                            </div>
                        </Form>
                    </Panel>
                )}

                <Panel title="Training">
                    <EvidenceTable rows={trainings} empty="No training records on file." />
                </Panel>

                {can_submit_evidence && (
                    <Panel title="Submit training evidence">
                        <Form
                            action={`/employees/${employee.id}/compliance-evidence`}
                            method="post"
                            encType="multipart/form-data"
                            className="grid gap-3 md:grid-cols-2"
                        >
                            <input type="hidden" name="kind" value="training" />
                            <label className="grid gap-1 text-sm md:col-span-2">
                                <span className="font-medium">Title</span>
                                <Input name="title" required />
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Provider</span>
                                <Input name="provider" />
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="font-medium">Expires on</span>
                                <Input type="date" name="expires_on" />
                            </label>
                            <label className="grid gap-1 text-sm md:col-span-2">
                                <span className="font-medium">Supporting document</span>
                                <Input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                            </label>
                            <div className="md:col-span-2">
                                <Button type="submit">Submit for review</Button>
                            </div>
                        </Form>
                    </Panel>
                )}
            </div>
        </>
    );
}

function EvidenceTable({
    rows,
    empty,
}: {
    rows: ComplianceEvidenceItem[];
    empty: string;
}) {
    if (rows.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="text-muted-foreground text-xs uppercase">
                    <tr>
                        <th className="pb-2 font-medium">Item</th>
                        <th className="pb-2 font-medium">Status</th>
                        <th className="pb-2 font-medium">Expires</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row) => (
                        <tr key={`${row.kind}-${row.id}`}>
                            <td className="py-2">
                                <p className="font-medium">{row.title}</p>
                                <p className="text-muted-foreground text-xs">
                                    {row.type}
                                    {row.issuer ? ` · ${row.issuer}` : ''}
                                </p>
                            </td>
                            <td className="py-2">
                                <StatusBadge
                                    status={row.status}
                                    label={row.status_label}
                                />
                                {row.is_warning && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Warning only — not a Hard Stop by itself
                                    </p>
                                )}
                            </td>
                            <td className="py-2 text-xs">
                                {row.expires_on ?? 'No expiry'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

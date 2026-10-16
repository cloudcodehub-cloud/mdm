import { Form, Head } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

export default function ProfileChangeRequests({
    requests,
    can_review,
}: {
    requests: Array<{
        id: number;
        employee_name: string;
        status: string;
        reason: string | null;
        requester: string;
        submitted_at: string | null;
        review_note: string | null;
        fields: Array<{
            field: string;
            label: string;
            current: string | null;
            proposed: string | null;
        }>;
    }>;
    can_review: boolean;
}) {
    return (
        <>
            <Head title="Personal Information Requests" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Personal Information Requests
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Approve or reject employee phone, address, and emergency
                        contact change requests. Authoritative values update only
                        after approval.
                    </p>
                </div>

                <Panel title="Requests">
                    {requests.length === 0 ? (
                        <EmptyState message="No personal information requests." />
                    ) : (
                        <div className="space-y-4">
                            {requests.map((row) => (
                                <div
                                    key={row.id}
                                    className="border-border rounded-md border p-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">{row.employee_name}</p>
                                        <StatusBadge
                                            status={row.status}
                                            label={row.status}
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            {row.requester} · {row.submitted_at}
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
                                    {row.reason ? (
                                        <p className="mt-1 text-sm">{row.reason}</p>
                                    ) : null}
                                    {can_review && row.status === 'pending' && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Form
                                                action={`/profile-change-requests/${row.id}/approve`}
                                                method="patch"
                                                className="flex gap-2"
                                            >
                                                <Input
                                                    name="review_note"
                                                    placeholder="Review note"
                                                />
                                                <Button type="submit">Approve</Button>
                                            </Form>
                                            <Form
                                                action={`/profile-change-requests/${row.id}/reject`}
                                                method="patch"
                                            >
                                                <Button type="submit" variant="outline">
                                                    Reject
                                                </Button>
                                            </Form>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

ProfileChangeRequests.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Personal Information Requests',
            href: '/profile-change-requests',
        },
    ],
};

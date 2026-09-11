import { Form, Head } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

export default function AvailabilityRequests({
    requests,
    time_off,
}: {
    requests: Array<{
        id: number;
        dsp_name: string;
        type: string;
        effective_on: string;
        reason: string | null;
        status: string;
        requester: string;
        review_note: string | null;
    }>;
    time_off: Array<{
        id: number;
        dsp_name: string;
        starts_on: string;
        ends_on: string;
        reason: string | null;
        status: string;
        requester: string;
    }>;
}) {
    return (
        <>
            <Head title="Availability Requests" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Availability Requests
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Approve or reject DSP availability and time-off
                        requests. Approvals that conflict with scheduled visits
                        flag those visits instead of changing them.
                    </p>
                </div>

                <Panel title="Availability changes">
                    <div className="space-y-4">
                        {requests.map((row) => (
                            <div
                                key={row.id}
                                className="border-border rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{row.dsp_name}</p>
                                    <StatusBadge status={row.status} label={row.status} />
                                    <span className="text-muted-foreground text-xs">
                                        {row.type} · {row.effective_on}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm">
                                    {row.reason ?? 'No reason provided.'}
                                </p>
                                {row.status === 'pending' && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Form
                                            action={`/availability-requests/${row.id}/approve`}
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
                                            action={`/availability-requests/${row.id}/reject`}
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
                </Panel>

                <Panel title="Time off">
                    <div className="space-y-4">
                        {time_off.map((row) => (
                            <div
                                key={row.id}
                                className="border-border rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{row.dsp_name}</p>
                                    <StatusBadge status={row.status} label={row.status} />
                                </div>
                                <p className="text-sm">
                                    {row.starts_on} – {row.ends_on}
                                </p>
                                {row.status === 'pending' && (
                                    <div className="mt-3 flex gap-2">
                                        <Form
                                            action={`/time-off/${row.id}/approve`}
                                            method="patch"
                                        >
                                            <Button type="submit">Approve</Button>
                                        </Form>
                                        <Form
                                            action={`/time-off/${row.id}/reject`}
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
                </Panel>
            </div>
        </>
    );
}

AvailabilityRequests.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Availability Requests', href: '/availability-requests' },
    ],
};

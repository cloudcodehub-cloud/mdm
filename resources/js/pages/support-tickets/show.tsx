import { Form, Head, Link } from '@inertiajs/react';
import { BackLink } from '@/components/mdm/back-link';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as ticketsIndex } from '@/routes/support-tickets';
import {
    cancel,
    claim,
    duplicate,
    reassign,
    reopen,
    reply,
    resolve,
    takeover,
} from '@/routes/support-tickets';
import { show as attachmentShow } from '@/routes/support-ticket-attachments';

type TicketDetail = {
    id: number;
    ticket_number: string;
    status: string;
    status_label: string;
    category_label: string;
    subject: string;
    description: string | null;
    urgency_label: string;
    work_impact_label: string;
    resolution_note: string | null;
    resolved_at_label: string | null;
    reopen_deadline_label: string | null;
    within_reopen_window: boolean;
    summary_only: boolean;
    can_view_full: boolean;
    requester: {
        name: string | null;
        position?: string | null;
        work_email?: string | null;
        work_phone?: string | null;
    };
    assignee_name: string | null;
    duplicate_of: { id: number; ticket_number: string } | null;
    replies: Array<{
        id: number;
        body: string;
        author_name: string | null;
        created_at_label: string | null;
    }>;
    attachments: Array<{
        id: number;
        original_name: string;
        is_initial: boolean;
        uploaded_by: string | null;
        created_at_label: string | null;
    }>;
    history: Array<{
        id: number;
        action: string;
        actor_name: string | null;
        created_at_label: string | null;
    }>;
};

export default function SupportTicketsShow({
    ticket,
    it_tech_options,
    can,
}: {
    ticket: TicketDetail;
    it_tech_options: Array<{ id: number; name: string }>;
    can: {
        claim: boolean;
        reply: boolean;
        resolve: boolean;
        reopen: boolean;
        cancel: boolean;
        reassign: boolean;
        takeover: boolean;
        mark_duplicate: boolean;
        view_full: boolean;
    };
}) {
    return (
        <>
            <Head title={ticket.ticket_number} />
            <div className="page-shell">
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <BackLink href={ticketsIndex()}>
                            Back to Support Tickets
                        </BackLink>
                        <h1 className="mt-2 text-xl font-semibold tracking-tight break-words">
                            {ticket.ticket_number}
                        </h1>
                        <p className="text-muted-foreground text-sm break-words">
                            {ticket.subject}
                        </p>
                    </div>
                    <StatusBadge status={ticket.status} label={ticket.status_label} />
                </div>

                {ticket.summary_only ? (
                    <Panel title="Queue summary">
                        <p className="text-sm">
                            Claim this ticket to view the full description,
                            conversation, and attachments.
                        </p>
                        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <Item label="Requester" value={ticket.requester.name} />
                            <Item label="Category" value={ticket.category_label} />
                            <Item label="Urgency" value={ticket.urgency_label} />
                            <Item label="Work impact" value={ticket.work_impact_label} />
                        </dl>
                        {can.claim ? (
                            <Form {...claim.form(ticket.id)} className="mt-4">
                                <Button type="submit">Take ticket / Assign to me</Button>
                            </Form>
                        ) : null}
                    </Panel>
                ) : (
                    <>
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Panel title="Submission" className="lg:col-span-2">
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <Item label="Category" value={ticket.category_label} />
                                    <Item label="Urgency" value={ticket.urgency_label} />
                                    <Item
                                        label="Work impact"
                                        value={ticket.work_impact_label}
                                    />
                                    <Item
                                        label="Assigned technician"
                                        value={ticket.assignee_name ?? 'Unassigned'}
                                    />
                                </dl>
                                <p className="mt-4 whitespace-pre-wrap text-sm">
                                    {ticket.description}
                                </p>
                                {ticket.resolution_note ? (
                                    <div className="border-border/60 mt-4 border-t pt-3">
                                        <p className="text-sm font-medium">
                                            Resolution note
                                        </p>
                                        <p className="mt-1 whitespace-pre-wrap text-sm">
                                            {ticket.resolution_note}
                                        </p>
                                        {ticket.reopen_deadline_label ? (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Reopen window ends{' '}
                                                {ticket.reopen_deadline_label}
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
                                {ticket.duplicate_of ? (
                                    <p className="text-muted-foreground mt-3 text-sm">
                                        Duplicate of{' '}
                                        <Link
                                            href={`/support-tickets/${ticket.duplicate_of.id}`}
                                            className="underline"
                                        >
                                            {ticket.duplicate_of.ticket_number}
                                        </Link>
                                    </p>
                                ) : null}
                            </Panel>

                            <Panel title="Requester">
                                <dl className="grid gap-2 text-sm">
                                    <Item label="Name" value={ticket.requester.name} />
                                    <Item
                                        label="Position / role"
                                        value={ticket.requester.position}
                                    />
                                    <Item
                                        label="Work email"
                                        value={ticket.requester.work_email}
                                    />
                                    <Item
                                        label="Work phone"
                                        value={ticket.requester.work_phone}
                                    />
                                </dl>
                            </Panel>
                        </div>

                        <Panel title="Actions">
                            <div className="flex flex-wrap gap-2">
                                {can.claim ? (
                                    <Form {...claim.form(ticket.id)}>
                                        <Button type="submit">Claim ticket</Button>
                                    </Form>
                                ) : null}
                                {can.takeover ? (
                                    <Form {...takeover.form(ticket.id)}>
                                        <Button type="submit" variant="secondary">
                                            Take over
                                        </Button>
                                    </Form>
                                ) : null}
                                {can.reopen ? (
                                    <Form {...reopen.form(ticket.id)}>
                                        <Button type="submit" variant="secondary">
                                            Reopen
                                        </Button>
                                    </Form>
                                ) : null}
                                {can.cancel ? (
                                    <Form {...cancel.form(ticket.id)}>
                                        <Button type="submit" variant="secondary">
                                            Withdraw ticket
                                        </Button>
                                    </Form>
                                ) : null}
                            </div>

                            {can.reassign ? (
                                <Form
                                    {...reassign.form(ticket.id)}
                                    className="mt-4 flex flex-wrap items-end gap-2"
                                >
                                    <label className="grid gap-1 text-sm">
                                        <span>Reassign to</span>
                                        <select
                                            name="assigned_user_id"
                                            required
                                            className="border-input bg-background h-9 rounded-md border px-2"
                                        >
                                            {it_tech_options.map((option) => (
                                                <option key={option.id} value={option.id}>
                                                    {option.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <Button type="submit" variant="secondary">
                                        Reassign
                                    </Button>
                                </Form>
                            ) : null}

                            {can.resolve ? (
                                <Form
                                    {...resolve.form(ticket.id)}
                                    className="mt-4 grid max-w-xl gap-2"
                                >
                                    {({ errors }) => (
                                        <>
                                            <label className="grid gap-1 text-sm">
                                                <span>Resolution note</span>
                                                <textarea
                                                    name="resolution_note"
                                                    required
                                                    rows={3}
                                                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                                />
                                                {errors.resolution_note ? (
                                                    <span className="text-destructive text-xs">
                                                        {errors.resolution_note}
                                                    </span>
                                                ) : null}
                                            </label>
                                            <Button type="submit">Mark resolved</Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}

                            {can.mark_duplicate ? (
                                <Form
                                    {...duplicate.form(ticket.id)}
                                    className="mt-4 flex flex-wrap items-end gap-2"
                                >
                                    <label className="grid gap-1 text-sm">
                                        <span>Canonical ticket ID</span>
                                        <Input
                                            name="duplicate_of_ticket_id"
                                            type="number"
                                            required
                                            className="w-40"
                                        />
                                    </label>
                                    <Button type="submit" variant="secondary">
                                        Mark duplicate
                                    </Button>
                                </Form>
                            ) : null}
                        </Panel>

                        <Panel title="Conversation">
                            {ticket.replies.length === 0 ? (
                                <EmptyState message="No replies yet." />
                            ) : (
                                <ul className="space-y-3">
                                    {ticket.replies.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="border-border/60 border-b pb-3 last:border-0"
                                        >
                                            <p className="text-sm font-medium">
                                                {entry.author_name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {entry.created_at_label}
                                            </p>
                                            <p className="mt-1 whitespace-pre-wrap text-sm">
                                                {entry.body}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {can.reply ? (
                                <Form
                                    {...reply.form(ticket.id)}
                                    encType="multipart/form-data"
                                    className="mt-4 grid max-w-xl gap-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <label className="grid gap-1 text-sm">
                                                <span>Reply</span>
                                                <textarea
                                                    name="body"
                                                    required
                                                    rows={4}
                                                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                                />
                                                {errors.body ? (
                                                    <span className="text-destructive text-xs">
                                                        {errors.body}
                                                    </span>
                                                ) : null}
                                            </label>
                                            <Input type="file" name="attachment" />
                                            <Button type="submit" disabled={processing}>
                                                Post reply
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}
                        </Panel>

                        <Panel title="Attachments">
                            {ticket.attachments.length === 0 ? (
                                <EmptyState message="No attachments." />
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {ticket.attachments.map((file) => (
                                        <li key={file.id}>
                                            <a
                                                href={attachmentShow.url(file.id)}
                                                className="underline"
                                            >
                                                {file.original_name}
                                            </a>
                                            <span className="text-muted-foreground">
                                                {file.is_initial ? ' · initial' : ''}
                                                {file.uploaded_by
                                                    ? ` · ${file.uploaded_by}`
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>

                        <Panel title="History">
                            {ticket.history.length === 0 ? (
                                <EmptyState message="No history yet." />
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {ticket.history.map((event) => (
                                        <li key={event.id}>
                                            <span className="font-medium">
                                                {event.action}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {event.actor_name}
                                                {event.created_at_label
                                                    ? ` · ${event.created_at_label}`
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </>
                )}
            </div>
        </>
    );
}

function Item({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs uppercase">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

import { Form, Head, router } from '@inertiajs/react';
import { BackLink } from '@/components/mdm/back-link';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index as messagesIndex } from '@/routes/messages';

type ReviewResult = {
    id: number;
    type: string;
    name: string | null;
    participant_names: string[];
};

export default function MessagesAdminReview({
    results,
    filters,
    purposes,
    privacy_notice,
}: {
    results: ReviewResult[];
    filters: { search: string };
    purposes: Array<{ value: string; label: string }>;
    privacy_notice: string;
}) {
    return (
        <>
            <Head title="Exceptional message review" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <BackLink href={messagesIndex()}>Back to Messages</BackLink>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        Exceptional Admin review
                    </h1>
                    <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                        Non-participant private-message review is Admin-only and
                        must be used for a legitimate agency purpose. This is not
                        a routine inbox.
                    </p>
                </div>

                <Panel title="Agency messaging notice">
                    <p className="text-sm whitespace-pre-wrap">{privacy_notice}</p>
                </Panel>

                <Panel title="Find a conversation">
                    <form
                        className="flex flex-wrap gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = new FormData(event.currentTarget);
                            router.get('/messages/admin-review', {
                                search: String(data.get('search') || '') || undefined,
                            });
                        }}
                    >
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search by participant or group name"
                            className="max-w-md"
                        />
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>

                    {filters.search === '' ? (
                        <EmptyState message="Enter a search term to locate a conversation for exceptional review. Conversations are not listed by default." />
                    ) : results.length === 0 ? (
                        <EmptyState message="No conversations matched that search." />
                    ) : (
                        <ul className="mt-4 space-y-4">
                            {results.map((item) => (
                                <li
                                    key={item.id}
                                    className="border-border/60 rounded-lg border p-3"
                                >
                                    <p className="text-sm font-medium">
                                        {item.name || `Conversation #${item.id}`}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {item.type} ·{' '}
                                        {item.participant_names.join(', ')}
                                    </p>
                                    <Form
                                        action={`/messages/${item.id}/admin-review`}
                                        method="post"
                                        className="mt-3 grid max-w-xl gap-2"
                                    >
                                        <select
                                            name="purpose"
                                            required
                                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                        >
                                            <option value="">Select purpose</option>
                                            {purposes.map((purpose) => (
                                                <option
                                                    key={purpose.value}
                                                    value={purpose.value}
                                                >
                                                    {purpose.label}
                                                </option>
                                            ))}
                                        </select>
                                        <textarea
                                            name="reason"
                                            required
                                            rows={3}
                                            placeholder="Document the legitimate agency purpose"
                                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                        />
                                        <Button type="submit" size="sm">
                                            Open with audited review access
                                        </Button>
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </>
    );
}

MessagesAdminReview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Messages', href: messagesIndex() },
        { title: 'Admin review', href: '/messages/admin-review' },
    ],
};

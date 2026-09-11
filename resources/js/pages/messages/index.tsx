import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { controlClassName } from '@/components/mdm/directory';
import { dashboard } from '@/routes';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as messagesIndex, show as messagesShow } from '@/routes/messages';
import { store as storeConversation } from '@/routes/conversations';
import { store as storeMessage } from '@/routes/conversations/messages';
import { cn } from '@/lib/utils';
import type {
    ChatMessage,
    ConversationDetail,
    ConversationSummary,
    MessagingUser,
} from '@/types/messaging';

export default function MessagesIndex({
    conversations,
    conversation,
    messages,
    recipients,
    can,
}: {
    conversations: ConversationSummary[];
    conversation: ConversationDetail | null;
    messages: ChatMessage[];
    recipients: MessagingUser[];
    filters: { search: string };
    can: { announce: boolean };
}) {
    const [composerOpen, setComposerOpen] = useState(false);

    return (
        <>
            <Head title="Messages" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Messages
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            One-to-one internal chat for active MDM users.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={announcementsIndex()}>
                                Announcements
                            </Link>
                        </Button>
                        <Button onClick={() => setComposerOpen(true)}>
                            New conversation
                        </Button>
                    </div>
                </div>

                <div className="grid min-h-[28rem] gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <Panel title="Conversations">
                        {conversations.length === 0 ? (
                            <EmptyState message="No conversations yet." />
                        ) : (
                            <ul className="space-y-1">
                                {conversations.map((item) => {
                                    const active = conversation?.id === item.id;

                                    return (
                                        <li key={item.id}>
                                            <Link
                                                href={messagesShow(item.id)}
                                                className={cn(
                                                    'block rounded-md px-2 py-2',
                                                    active
                                                        ? 'bg-muted'
                                                        : 'hover:bg-muted/50',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="text-sm font-medium">
                                                        {item.other_user?.name ??
                                                            'Unknown user'}
                                                    </p>
                                                    {item.unread_count > 0 && (
                                                        <span className="bg-primary text-primary-foreground flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px]">
                                                            {item.unread_count}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    {item.latest_message ??
                                                        'No messages yet'}
                                                </p>
                                                {item.latest_at && (
                                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                                        {item.latest_at}
                                                    </p>
                                                )}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title={
                            conversation?.other_user?.name ?? 'Select a conversation'
                        }
                        description={
                            conversation?.other_user
                                ? conversation.other_user.role
                                : 'Choose someone from the list or start a new conversation.'
                        }
                    >
                        {conversation ? (
                            <Thread
                                conversation={conversation}
                                messages={messages}
                            />
                        ) : (
                            <EmptyState message="Select a conversation to read and reply." />
                        )}
                    </Panel>
                </div>
            </div>

            <NewConversationDialog
                open={composerOpen}
                onOpenChange={setComposerOpen}
                recipients={recipients}
            />
        </>
    );
}

function Thread({
    conversation,
    messages,
}: {
    conversation: ConversationDetail;
    messages: ChatMessage[];
}) {
    const form = useForm({ body: '' });

    return (
        <div className="flex min-h-[22rem] flex-col">
            <div className="max-h-[28rem] flex-1 space-y-3 overflow-y-auto pr-1">
                {messages.length === 0 ? (
                    <EmptyState message="No messages in this conversation yet." />
                ) : (
                    messages.map((message) => (
                        <div key={message.id} className="rounded-md border px-3 py-2">
                            <div className="flex items-baseline justify-between gap-2">
                                <p className="text-sm font-medium">
                                    {message.sender_name}
                                </p>
                                <p className="text-muted-foreground text-[11px]">
                                    {message.created_at}
                                </p>
                            </div>
                            <p className="mt-1 text-sm whitespace-pre-wrap">
                                {message.body}
                            </p>
                        </div>
                    ))
                )}
            </div>
            <form
                className="mt-4 space-y-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeMessage.url(conversation.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset('body'),
                    });
                }}
            >
                <textarea
                    name="body"
                    rows={3}
                    value={form.data.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                    className={cn(controlClassName, 'h-auto min-h-20 py-2')}
                    placeholder="Write a reply"
                    disabled={!conversation.other_user?.can_message}
                />
                <InputError message={form.errors.body} />
                {!conversation.other_user?.can_message && (
                    <p className="text-muted-foreground text-xs">
                        This account is inactive and cannot receive messages.
                    </p>
                )}
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        !conversation.other_user?.can_message
                    }
                >
                    Send
                </Button>
            </form>
        </div>
    );
}

function NewConversationDialog({
    open,
    onOpenChange,
    recipients,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recipients: MessagingUser[];
}) {
    const form = useForm({
        user_id: '',
        body: '',
    });
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return recipients;
        }

        return recipients.filter(
            (user) =>
                user.name.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term) ||
                user.role.toLowerCase().includes(term),
        );
    }, [query, recipients]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New conversation</DialogTitle>
                    <DialogDescription>
                        Message another active user in this organization.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(storeConversation.url(), {
                            onSuccess: () => {
                                form.reset();
                                onOpenChange(false);
                            },
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="recipient-search">Find a user</Label>
                        <Input
                            id="recipient-search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search name, email, or role"
                        />
                        <select
                            name="user_id"
                            value={form.data.user_id}
                            onChange={(event) =>
                                form.setData('user_id', event.target.value)
                            }
                            className={controlClassName}
                        >
                            <option value="">Select a person</option>
                            {filtered.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name} ({user.role})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.user_id} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="first-message">Message</Label>
                        <textarea
                            id="first-message"
                            rows={4}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            className={cn(controlClassName, 'h-auto min-h-20 py-2')}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Send
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

MessagesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Messages', href: messagesIndex() },
    ],
};

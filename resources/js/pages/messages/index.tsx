import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { useInitials } from '@/hooks/use-initials';
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
                            <EmptyState message="No conversations yet. Start one when you need to reach a coworker." />
                        ) : (
                            <ul className="space-y-1">
                                {conversations.map((item) => {
                                    const active = conversation?.id === item.id;

                                    return (
                                        <li key={item.id}>
                                            <Link
                                                href={messagesShow(item.id)}
                                                className={cn(
                                                    'block rounded-lg border border-transparent px-2.5 py-2 transition-colors duration-150',
                                                    active
                                                        ? 'border-primary/20 bg-primary/8'
                                                        : 'hover:bg-muted/50',
                                                    item.unread_count > 0 &&
                                                        !active &&
                                                        'bg-brand-coral/8',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <p
                                                        className={cn(
                                                            'text-sm',
                                                            item.unread_count >
                                                                0
                                                                ? 'font-semibold'
                                                                : 'font-medium',
                                                        )}
                                                    >
                                                        {item.other_user
                                                            ?.name ??
                                                            'Unknown user'}
                                                    </p>
                                                    <div className="flex shrink-0 items-center gap-1.5">
                                                        {item.latest_at && (
                                                            <span className="text-muted-foreground text-[11px]">
                                                                {item.latest_at}
                                                            </span>
                                                        )}
                                                        {item.unread_count >
                                                            0 && (
                                                            <span className="bg-primary text-primary-foreground flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px]">
                                                                {
                                                                    item.unread_count
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    {item.latest_message ??
                                                        'No messages yet'}
                                                </p>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>

                    <section className="surface-panel flex min-h-[28rem] flex-col overflow-hidden p-0">
                        <div className="border-b px-4 py-3">
                            <h2 className="text-sm font-semibold tracking-tight">
                                {conversation?.other_user?.name ??
                                    'Select a conversation'}
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                {conversation?.other_user
                                    ? conversation.other_user.role
                                    : 'Choose someone from the list or start a new conversation.'}
                            </p>
                        </div>
                        {conversation ? (
                            <Thread
                                conversation={conversation}
                                messages={messages}
                            />
                        ) : (
                            <div className="p-4">
                                <EmptyState message="Select a conversation to read and reply." />
                            </div>
                        )}
                    </section>
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
    const currentUserId = usePage().props.auth.user.id;
    const getInitials = useInitials();
    const firstUnreadId = conversation.first_unread_id ?? null;

    const items = useMemo(() => {
        const result: Array<
            | { type: 'date'; key: string; label: string }
            | { type: 'unread'; key: string }
            | { type: 'message'; message: ChatMessage }
        > = [];
        let lastDate: string | null = null;
        let unreadInserted = false;

        for (const message of messages) {
            if (message.created_on && message.created_on !== lastDate) {
                result.push({
                    type: 'date',
                    key: `date-${message.created_on}`,
                    label: message.created_on,
                });
                lastDate = message.created_on;
            }

            if (
                !unreadInserted &&
                firstUnreadId !== null &&
                message.id === firstUnreadId
            ) {
                result.push({ type: 'unread', key: 'unread' });
                unreadInserted = true;
            }

            result.push({ type: 'message', message });
        }

        return result;
    }, [firstUnreadId, messages]);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                {messages.length === 0 ? (
                    <EmptyState message="No messages in this conversation yet." />
                ) : (
                    items.map((item) => {
                        if (item.type === 'date') {
                            return (
                                <p
                                    key={item.key}
                                    className="text-muted-foreground py-1 text-center text-[11px] tracking-wide uppercase"
                                >
                                    {item.label}
                                </p>
                            );
                        }

                        if (item.type === 'unread') {
                            return (
                                <p
                                    key={item.key}
                                    className="text-primary flex items-center gap-2 py-1 text-center text-[11px] font-medium tracking-wide uppercase"
                                >
                                    <span className="bg-primary/30 h-px flex-1" />
                                    New messages
                                    <span className="bg-primary/30 h-px flex-1" />
                                </p>
                            );
                        }

                        const mine =
                            item.message.is_mine ??
                            item.message.sender_id === currentUserId;

                        return (
                            <div
                                key={item.message.id}
                                className={cn(
                                    'flex items-end gap-2',
                                    mine ? 'justify-end' : 'justify-start',
                                )}
                            >
                                {!mine && (
                                    <Avatar className="size-7">
                                        <AvatarFallback className="bg-brand-coral/20 text-[10px] text-foreground">
                                            {getInitials(
                                                item.message.sender_name,
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                )}
                                <div
                                    className={cn(
                                        'max-w-[85%] rounded-2xl px-3 py-2 text-sm',
                                        mine
                                            ? 'bg-primary text-primary-foreground rounded-br-md'
                                            : 'bg-muted rounded-bl-md',
                                    )}
                                >
                                    {!mine && (
                                        <p className="mb-0.5 text-[11px] font-medium opacity-80">
                                            {item.message.sender_name}
                                        </p>
                                    )}
                                    <p className="whitespace-pre-wrap">
                                        {item.message.body}
                                    </p>
                                    {item.message.created_at && (
                                        <p
                                            className={cn(
                                                'mt-1 text-[10px]',
                                                mine
                                                    ? 'text-primary-foreground/75'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {item.message.created_at}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
            <form
                className="sticky bottom-0 space-y-2 border-t bg-card/90 px-4 py-3 backdrop-blur-md"
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
                    className={cn(
                        controlClassName,
                        'h-auto min-h-16 py-2 md:min-h-20',
                    )}
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
                    className="min-h-11 w-full sm:min-h-9 sm:w-auto"
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

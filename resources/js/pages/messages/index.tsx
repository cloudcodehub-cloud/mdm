import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
import { show as attachmentShow } from '@/routes/conversation-attachments';
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
    privacy_notice,
    can,
}: {
    conversations: ConversationSummary[];
    conversation: ConversationDetail | null;
    messages: ChatMessage[];
    recipients: MessagingUser[];
    filters: { search: string };
    privacy_notice: string;
    can: { announce: boolean; admin_review: boolean; create_group: boolean };
}) {
    const [composerOpen, setComposerOpen] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);

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
                            Direct and private group messaging for active MDM
                            users.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={announcementsIndex()}>
                                Announcements
                            </Link>
                        </Button>
                        {can.admin_review ? (
                            <Button variant="secondary" asChild>
                                <Link href="/messages/admin-review">
                                    Exceptional review
                                </Link>
                            </Button>
                        ) : null}
                        {can.create_group ? (
                            <Button
                                variant="secondary"
                                onClick={() => setGroupOpen(true)}
                            >
                                New group
                            </Button>
                        ) : null}
                        <Button onClick={() => setComposerOpen(true)}>
                            New conversation
                        </Button>
                    </div>
                </div>

                <div className="border-border/60 bg-muted/30 rounded-lg border px-3 py-2 text-xs leading-relaxed">
                    {privacy_notice}
                </div>

                <div className="grid min-h-[28rem] gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <Panel title="Conversations">
                        {conversations.length === 0 ? (
                            <EmptyState message="No conversations yet. Start a direct message or private group." />
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
                                                        {item.name}
                                                        {item.type === 'group' ? (
                                                            <span className="text-muted-foreground ml-1 text-[11px] font-normal">
                                                                · group
                                                            </span>
                                                        ) : null}
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
                                {conversation?.name ?? 'Select a conversation'}
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                {conversation?.admin_review
                                    ? 'Exceptional Admin review mode'
                                    : conversation?.type === 'group'
                                      ? `${conversation.participants?.length ?? 0} participants`
                                      : conversation?.other_user
                                        ? conversation.other_user.role
                                        : 'Choose someone from the list or start a new conversation.'}
                            </p>
                        </div>
                        {conversation ? (
                            <Thread
                                conversation={conversation}
                                messages={messages}
                                recipients={recipients}
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
            <NewGroupDialog
                open={groupOpen}
                onOpenChange={setGroupOpen}
                recipients={recipients}
            />
        </>
    );
}

function Thread({
    conversation,
    messages,
    recipients,
}: {
    conversation: ConversationDetail;
    messages: ChatMessage[];
    recipients: MessagingUser[];
}) {
    const form = useForm<{ body: string; attachment: File | null }>({
        body: '',
        attachment: null,
    });
    const currentUserId = usePage().props.auth.user.id;
    const getInitials = useInitials();
    const firstUnreadId = conversation.first_unread_id ?? null;
    const canReply = Boolean(
        conversation.is_active_participant && !conversation.admin_review,
    );

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
            {conversation.type === 'group' && conversation.can_manage ? (
                <GroupManageBar conversation={conversation} recipients={recipients} />
            ) : null}
            {conversation.type === 'group' &&
            conversation.is_active_participant &&
            !conversation.admin_review ? (
                <div className="border-b px-4 py-2">
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                            router.post(`/messages/${conversation.id}/leave`)
                        }
                    >
                        Leave group
                    </Button>
                </div>
            ) : null}
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

                        if (item.message.is_system) {
                            return (
                                <p
                                    key={item.message.id}
                                    className="text-muted-foreground py-1 text-center text-xs"
                                >
                                    {item.message.body}
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
                                    {(item.message.attachments ?? []).map(
                                        (file) => (
                                            <a
                                                key={file.id}
                                                href={attachmentShow.url(file.id)}
                                                className={cn(
                                                    'mt-1 block text-xs underline',
                                                    mine
                                                        ? 'text-primary-foreground/90'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {file.original_name}
                                            </a>
                                        ),
                                    )}
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
            {canReply ? (
                <form
                    className="sticky bottom-0 space-y-2 border-t bg-card/90 px-4 py-3 backdrop-blur-md"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(storeMessage.url(conversation.id), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset('body');
                                form.setData('attachment', null);
                            },
                        });
                    }}
                >
                    <textarea
                        name="body"
                        rows={3}
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        className={cn(
                            controlClassName,
                            'h-auto min-h-16 py-2 md:min-h-20',
                        )}
                        placeholder="Write a reply"
                    />
                    <Input
                        type="file"
                        onChange={(event) =>
                            form.setData(
                                'attachment',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError message={form.errors.body} />
                    <InputError message={form.errors.attachment} />
                    <Button
                        type="submit"
                        className="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        disabled={form.processing}
                    >
                        Send
                    </Button>
                </form>
            ) : (
                <div className="border-t px-4 py-3">
                    <p className="text-muted-foreground text-xs">
                        {conversation.admin_review
                            ? 'Review access is read-only.'
                            : 'You are no longer an active participant and cannot send new messages.'}
                    </p>
                </div>
            )}
        </div>
    );
}

function GroupManageBar({
    conversation,
    recipients,
}: {
    conversation: ConversationDetail;
    recipients: MessagingUser[];
}) {
    const rename = useForm({ name: conversation.name });
    const add = useForm({ participant_ids: [] as string[] });

    return (
        <div className="space-y-2 border-b px-4 py-3">
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    rename.patch(`/messages/${conversation.id}/name`, {
                        preserveScroll: true,
                    });
                }}
            >
                <Input
                    value={rename.data.name}
                    onChange={(event) => rename.setData('name', event.target.value)}
                    className="max-w-xs"
                />
                <Button type="submit" size="sm" variant="secondary">
                    Rename
                </Button>
            </form>
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    add.post(`/messages/${conversation.id}/participants`, {
                        preserveScroll: true,
                        onSuccess: () => add.setData('participant_ids', []),
                    });
                }}
            >
                <select
                    multiple
                    className={cn(controlClassName, 'h-24 max-w-xs')}
                    value={add.data.participant_ids}
                    onChange={(event) =>
                        add.setData(
                            'participant_ids',
                            Array.from(event.target.selectedOptions).map(
                                (option) => option.value,
                            ),
                        )
                    }
                >
                    {recipients.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.name}
                        </option>
                    ))}
                </select>
                <Button type="submit" size="sm" variant="secondary">
                    Add participants
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
    const form = useForm<{
        user_id: string;
        body: string;
        attachment: File | null;
    }>({
        user_id: '',
        body: '',
        attachment: null,
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
                            forceFormData: true,
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
                        <Input
                            type="file"
                            onChange={(event) =>
                                form.setData(
                                    'attachment',
                                    event.target.files?.[0] ?? null,
                                )
                            }
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

function NewGroupDialog({
    open,
    onOpenChange,
    recipients,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recipients: MessagingUser[];
}) {
    const form = useForm<{
        name: string;
        participant_ids: string[];
        body: string;
    }>({
        name: '',
        participant_ids: [],
        body: '',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New private group</DialogTitle>
                    <DialogDescription>
                        Create a named private group with selected participants.
                        New members can see existing history.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/messages/groups', {
                            onSuccess: () => {
                                form.reset();
                                onOpenChange(false);
                            },
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label>Group name</Label>
                        <Input
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label>Participants</Label>
                        <select
                            multiple
                            className={cn(controlClassName, 'h-36')}
                            value={form.data.participant_ids}
                            onChange={(event) =>
                                form.setData(
                                    'participant_ids',
                                    Array.from(event.target.selectedOptions).map(
                                        (option) => option.value,
                                    ),
                                )
                            }
                        >
                            {recipients.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name} ({user.role})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.participant_ids} />
                    </div>
                    <div className="space-y-2">
                        <Label>Optional first message</Label>
                        <textarea
                            rows={3}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            className={cn(controlClassName, 'h-auto min-h-16 py-2')}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create group
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

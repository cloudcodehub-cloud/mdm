import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { store as storeConversation } from '@/routes/conversations';
import { show as messagesShow } from '@/routes/messages';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/messaging';

type PreviewPayload = {
    recipient: { id: number; name: string; role?: string };
    conversation: { id: number } | null;
    messages: ChatMessage[];
};

export function CareMessageDrawer({
    open,
    onOpenChange,
    userId,
    recipientName,
    contextLabel,
    clientId,
    visitId,
    taskTitle,
    variant = 'message',
    roleLabel,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    userId: number | null;
    recipientName: string;
    contextLabel: string;
    clientId?: number;
    visitId?: number;
    taskTitle?: string;
    variant?: 'supervisor' | 'message';
    roleLabel?: string;
}) {
    const [preview, setPreview] = useState<PreviewPayload | null>(null);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [mobile, setMobile] = useState(false);

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const update = () => setMobile(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (!open || userId === null) {
            setPreview(null);
            setBody('');
            return;
        }

        void fetch(`/messages/with/${userId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload: PreviewPayload | null) => setPreview(payload));
    }, [open, userId]);

    const send = () => {
        if (userId === null || body.trim() === '') {
            return;
        }

        setSending(true);
        router.post(
            storeConversation.url(),
            {
                user_id: userId,
                body: body.trim(),
                stay: true,
                care_context_label: contextLabel,
                care_context_client_id: clientId ?? null,
                care_context_visit_id: visitId ?? null,
                care_context_task_title: taskTitle ?? null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSending(false),
                onSuccess: () => {
                    setBody('');
                    onOpenChange(false);
                },
            },
        );
    };

    const conversationId = preview?.conversation?.id;
    const title =
        variant === 'supervisor' ? 'Contact supervisor' : recipientName;
    const description =
        variant === 'supervisor'
            ? `${recipientName}${roleLabel ? ` · ${roleLabel}` : ''}`
            : 'Stay on this care page while you send a message.';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side={mobile ? 'bottom' : 'right'}
                className={cn(
                    'flex flex-col gap-0 p-0',
                    mobile ? 'max-h-[85vh] sm:max-w-none' : 'sm:max-w-md',
                )}
            >
                <SheetHeader className="border-border/70 pr-12 border-b">
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                <div className="px-4">
                    <p className="bg-primary/8 text-primary mt-3 inline-flex max-w-full rounded-full px-2.5 py-1 text-xs font-medium break-words">
                        {contextLabel}
                    </p>
                </div>
                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                    {(preview?.messages ?? []).length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No recent conversation yet.
                        </p>
                    ) : (
                        preview?.messages.slice(-12).map((message) => (
                            <div
                                key={message.id}
                                className={cn(
                                    'max-w-[90%] rounded-xl px-3 py-2 text-sm',
                                    message.is_mine
                                        ? 'bg-primary/12 ml-auto'
                                        : 'bg-muted/60',
                                )}
                            >
                                <p>{message.body}</p>
                                {message.created_at && (
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        {message.created_at}
                                    </p>
                                )}
                            </div>
                        ))
                    )}
                </div>
                <SheetFooter className="border-border/70 gap-3 border-t">
                    <textarea
                        rows={3}
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        placeholder="Write a care-related message"
                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={send}
                            disabled={sending || body.trim() === ''}
                        >
                            Send
                        </Button>
                        {conversationId ? (
                            <Button type="button" variant="secondary" asChild>
                                <a href={messagesShow.url(conversationId)}>
                                    Open Full Conversation
                                </a>
                            </Button>
                        ) : null}
                    </div>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}

import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useInboxActivity } from '@/hooks/use-inbox-activity';
import { Button } from '@/components/ui/button';
import { read, readAll } from '@/routes/notifications';
import { cn } from '@/lib/utils';

export function AlertsMenu() {
    const inbox = useInboxActivity();
    const refresh = inbox.refresh;
    const unread = inbox.unread_notifications;
    const notifications = inbox.notifications ?? [];
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [coords, setCoords] = useState({ top: 0, right: 16 });

    useEffect(() => {
        if (!open) {
            return;
        }

        void refresh();

        const update = () => {
            const rect = wrapRef.current?.getBoundingClientRect();

            if (rect) {
                setCoords({
                    top: rect.bottom + 8,
                    right: Math.max(12, window.innerWidth - rect.right),
                });
            }
        };

        update();

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (
                wrapRef.current?.contains(target) ||
                panelRef.current?.contains(target)
            ) {
                return;
            }

            setOpen(false);
        };

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('resize', update);
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);

        return () => {
            window.removeEventListener('resize', update);
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, refresh]);

    return (
        <div className="relative" ref={wrapRef}>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="relative rounded-full"
                aria-label={
                    unread > 0 ? `Alerts, ${unread} unread` : 'Alerts'
                }
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((value) => !value)}
            >
                <Bell className="size-4" />
                {unread > 0 && (
                    <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </Button>
            {open &&
                createPortal(
                    <div
                        ref={panelRef}
                        role="dialog"
                        aria-label="Alerts"
                        className="surface-frosted fixed z-[80] w-80 overflow-hidden rounded-xl border p-1 shadow-lg"
                        style={{ top: coords.top, right: coords.right }}
                    >
                        <div className="flex items-center justify-between px-2 py-1.5">
                            <p className="text-sm font-medium">Alerts</p>
                            {unread > 0 && (
                                <button
                                    type="button"
                                    className="text-muted-foreground hover:text-foreground text-xs font-medium"
                                    onClick={() =>
                                        router.post(
                                            readAll.url(),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => void refresh(),
                                            },
                                        )
                                    }
                                >
                                    Mark all read
                                </button>
                            )}
                        </div>
                        <div className="max-h-80 overflow-y-auto">
                            {notifications.length === 0 ? (
                                <p className="text-muted-foreground px-2 py-3 text-sm">
                                    No notifications yet.
                                </p>
                            ) : (
                                notifications.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className={cn(
                                            'hover:bg-muted/50 flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-2 text-left',
                                            item.read_at === null &&
                                                'bg-muted/40',
                                        )}
                                        onClick={() => {
                                            setOpen(false);
                                            router.post(read.url(item.id));
                                        }}
                                    >
                                        <span className="flex w-full items-center justify-between gap-2">
                                            <span className="text-sm font-medium">
                                                {item.title}
                                            </span>
                                            {item.read_at === null && (
                                                <span className="bg-primary size-1.5 shrink-0 rounded-full" />
                                            )}
                                        </span>
                                        <span className="text-muted-foreground line-clamp-2 text-xs">
                                            {item.body}
                                        </span>
                                        {item.created_at && (
                                            <span className="text-muted-foreground text-[11px]">
                                                {item.created_at}
                                            </span>
                                        )}
                                    </button>
                                ))
                            )}
                        </div>
                    </div>,
                    document.body,
                )}
        </div>
    );
}

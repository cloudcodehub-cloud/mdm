import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { activity } from '@/routes/inbox';
import type { InboxActivity, InboxNotification } from '@/types/messaging';

const emptyInbox: InboxActivity = {
    unread_messages: 0,
    unread_notifications: 0,
    notifications: [],
};

const InboxActivityContext = createContext<InboxActivity>(emptyInbox);

const toastedKeys = new Set<string>();

export function InboxActivityProvider({ children }: { children: ReactNode }) {
    const { inbox: sharedInbox, auth } = usePage().props;
    const [inbox, setInbox] = useState<InboxActivity>(sharedInbox ?? emptyInbox);
    const afterRef = useRef(new Date().toISOString());

    useEffect(() => {
        setInbox(sharedInbox ?? emptyInbox);
    }, [sharedInbox]);

    const showToasts = useCallback((items: InboxNotification[]) => {
        items.forEach((item) => {
            if (toastedKeys.has(item.source_key)) {
                return;
            }

            toastedKeys.add(item.source_key);

            toast.custom((id) => (
                <button
                    type="button"
                    className="bg-popover text-popover-foreground w-80 rounded-md border p-3 text-left shadow-md"
                    onClick={() => {
                        toast.dismiss(id);
                        if (item.url) {
                            router.visit(item.url);
                        }
                    }}
                >
                    <p className="text-sm font-medium">{item.title}</p>
                    <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">
                        {item.body}
                    </p>
                </button>
            ), { duration: 8000 });
        });
    }, []);

    useEffect(() => {
        if (!auth.user) {
            return;
        }

        let cancelled = false;

        const poll = async () => {
            if (document.hidden) {
                return;
            }

            try {
                const url = new URL(activity.url(), window.location.origin);
                url.searchParams.set('after', afterRef.current);

                const response = await fetch(url.toString(), {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok || cancelled) {
                    return;
                }

                const payload = (await response.json()) as InboxActivity;
                setInbox({
                    unread_messages: payload.unread_messages,
                    unread_notifications: payload.unread_notifications,
                    notifications: payload.notifications,
                });
                showToasts(payload.toasts ?? []);
            } catch {
                // Keep the last known inbox state if a poll fails.
            }
        };

        const first = window.setTimeout(poll, 4000);
        const interval = window.setInterval(poll, 15000);

        return () => {
            cancelled = true;
            window.clearTimeout(first);
            window.clearInterval(interval);
        };
    }, [auth.user, showToasts]);

    const value = useMemo(() => inbox, [inbox]);

    return (
        <InboxActivityContext.Provider value={value}>
            {children}
        </InboxActivityContext.Provider>
    );
}

export function useInboxActivity(): InboxActivity {
    return useContext(InboxActivityContext);
}

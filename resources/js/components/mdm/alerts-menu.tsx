import { Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useInboxActivity } from '@/hooks/use-inbox-activity';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { read, readAll } from '@/routes/notifications';
import { cn } from '@/lib/utils';

export function AlertsMenu() {
    const inbox = useInboxActivity();
    const unread = inbox.unread_notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative rounded-full transition-colors duration-200"
                    aria-label={
                        unread > 0
                            ? `Alerts, ${unread} unread`
                            : 'Alerts'
                    }
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <DropdownMenuLabel className="p-0">Alerts</DropdownMenuLabel>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground text-xs font-medium"
                            onClick={() =>
                                router.post(readAll.url(), {}, { preserveScroll: true })
                            }
                        >
                            Mark all read
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {inbox.notifications.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-3 text-sm">
                        No notifications yet.
                    </p>
                ) : (
                    inbox.notifications.map((item) => (
                        <DropdownMenuItem key={item.id} asChild>
                            <Link
                                href={read.url(item.id)}
                                method="post"
                                className={cn(
                                    'flex cursor-pointer flex-col items-start gap-0.5 whitespace-normal',
                                    item.read_at === null && 'bg-muted/40',
                                )}
                            >
                                <span className="text-sm font-medium">
                                    {item.title}
                                </span>
                                <span className="text-muted-foreground line-clamp-2 text-xs">
                                    {item.body}
                                </span>
                                {item.created_at && (
                                    <span className="text-muted-foreground text-[11px]">
                                        {item.created_at}
                                    </span>
                                )}
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

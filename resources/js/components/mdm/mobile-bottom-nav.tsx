import { Link, router, usePage } from '@inertiajs/react';
import { Home, MoreHorizontal, RotateCcw } from 'lucide-react';
import { useId, useState } from 'react';
import MobileNavShortcutsController from '@/actions/App/Http/Controllers/Settings/MobileNavShortcutsController';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInboxActivity } from '@/hooks/use-inbox-activity';
import { mobileNavIcon } from '@/lib/mobile-navigation-icons';
import { cn } from '@/lib/utils';
import type { MobileNavDestination } from '@/types/mobile-navigation';

export function MobileBottomNav() {
    const { mobileNav } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const inbox = useInboxActivity();
    const [moreOpen, setMoreOpen] = useState(false);
    const [customizing, setCustomizing] = useState(false);
    const [draft, setDraft] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);
    const titleId = useId();

    if (!mobileNav) {
        return null;
    }

    const shortcutKeys = mobileNav.shortcuts.map((item) => item.key);
    const moreIsActive =
        moreOpen ||
        mobileNav.more.some(
            (item) =>
                !shortcutKeys.includes(item.key) &&
                item.key !== mobileNav.home.key &&
                isCurrentOrParentUrl(item.href),
        );

    function openCustomize(): void {
        setDraft(mobileNav!.shortcuts.map((item) => item.key));
        setMoreOpen(false);
        setCustomizing(true);
    }

    function toggleDraft(key: string): void {
        setDraft((current) => {
            if (current.includes(key)) {
                return current.filter((item) => item !== key);
            }

            if (current.length >= 3) {
                return current;
            }

            return [...current, key];
        });
    }

    function saveShortcuts(): void {
        if (draft.length !== 3) {
            return;
        }

        setSaving(true);
        router.patch(
            MobileNavShortcutsController.update.url(),
            { shortcuts: draft },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setCustomizing(false);
                },
            },
        );
    }

    function resetDefaults(): void {
        setDraft([...mobileNav!.defaults]);
    }

    return (
        <>
            <nav
                aria-label="Mobile navigation"
                data-mobile-nav
                className="border-border/80 bg-card/95 supports-[backdrop-filter]:bg-card/90 fixed inset-x-0 bottom-0 z-40 border-t backdrop-blur-md md:hidden"
                style={{
                    paddingBottom: 'env(safe-area-inset-bottom, 0px)',
                }}
            >
                <ul className="grid h-16 grid-cols-5">
                    <MobileNavItem
                        label="Home"
                        href={mobileNav.home.href}
                        icon={Home}
                        active={isCurrentOrParentUrl(mobileNav.home.href)}
                    />
                    {mobileNav.shortcuts.map((item) => {
                        const Icon = mobileNavIcon(item.key);

                        return (
                            <MobileNavItem
                                key={item.key}
                                label={item.title}
                                href={item.href}
                                icon={Icon}
                                active={isCurrentOrParentUrl(item.href)}
                                badge={
                                    item.key === 'messages' &&
                                    inbox.unread_messages > 0
                                        ? inbox.unread_messages > 99
                                            ? '99+'
                                            : String(inbox.unread_messages)
                                        : undefined
                                }
                            />
                        );
                    })}
                    <li className="min-w-0">
                        <button
                            type="button"
                            aria-haspopup="dialog"
                            aria-expanded={moreOpen}
                            aria-controls={titleId}
                            onClick={() => setMoreOpen(true)}
                            className={cn(
                                'text-muted-foreground flex h-full w-full flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-medium tracking-wide transition-colors',
                                moreIsActive && 'text-primary',
                            )}
                        >
                            <MoreHorizontal
                                className={cn(
                                    'size-5 shrink-0',
                                    moreIsActive && 'text-primary',
                                )}
                                aria-hidden="true"
                            />
                            <span className="max-w-full truncate">More</span>
                        </button>
                    </li>
                </ul>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[85dvh] gap-0 rounded-t-2xl p-0 pb-[env(safe-area-inset-bottom,0px)] md:hidden"
                    aria-describedby={undefined}
                >
                    <SheetHeader className="border-border/70 border-b px-4 py-4 text-left">
                        <SheetTitle id={titleId}>More</SheetTitle>
                        <SheetDescription>
                            Open any destination you are authorized to use.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                        <ul className="flex flex-col gap-0.5">
                            {mobileNav.more.map((item) => {
                                const Icon = mobileNavIcon(item.key);
                                const active = isCurrentOrParentUrl(item.href);

                                return (
                                    <li key={item.key}>
                                        <Link
                                            href={item.href}
                                            prefetch
                                            onClick={() => setMoreOpen(false)}
                                            className={cn(
                                                'hover:bg-accent/70 flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                                active &&
                                                    'bg-primary/10 text-primary',
                                            )}
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                        >
                                            <Icon
                                                className="size-5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.title}
                                            </span>
                                            {item.key === 'messages' &&
                                                inbox.unread_messages > 0 && (
                                                    <span className="bg-primary text-primary-foreground rounded-md px-1.5 py-0.5 text-[10px] font-semibold">
                                                        {inbox.unread_messages >
                                                        99
                                                            ? '99+'
                                                            : inbox.unread_messages}
                                                    </span>
                                                )}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    <div className="border-border/70 border-t px-4 py-3">
                        <Button
                            type="button"
                            variant="secondary"
                            className="h-11 w-full"
                            onClick={openCustomize}
                        >
                            Customize shortcuts
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet open={customizing} onOpenChange={setCustomizing}>
                <SheetContent
                    side="bottom"
                    className="max-h-[85dvh] gap-0 rounded-t-2xl p-0 pb-[env(safe-area-inset-bottom,0px)] md:hidden"
                >
                    <SheetHeader className="border-border/70 border-b px-4 py-4 text-left">
                        <SheetTitle>Customize shortcuts</SheetTitle>
                        <SheetDescription>
                            Choose three destinations for the phone navigation
                            bar. This does not change permissions.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                        <p className="text-muted-foreground px-2 pb-2 text-xs">
                            Selected {draft.length} of 3
                        </p>
                        <ul className="flex flex-col gap-0.5">
                            {mobileNav.available.map((item) => {
                                const Icon = mobileNavIcon(item.key);
                                const selected = draft.includes(item.key);
                                const disabled =
                                    !selected && draft.length >= 3;

                                return (
                                    <li key={item.key}>
                                        <button
                                            type="button"
                                            disabled={disabled}
                                            aria-pressed={selected}
                                            onClick={() => toggleDraft(item.key)}
                                            className={cn(
                                                'flex min-h-12 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors',
                                                selected
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'hover:bg-accent/70',
                                                disabled &&
                                                    'cursor-not-allowed opacity-50',
                                            )}
                                        >
                                            <Icon
                                                className="size-5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.title}
                                            </span>
                                            {selected ? (
                                                <span className="text-primary text-xs font-semibold">
                                                    {draft.indexOf(item.key) +
                                                        1}
                                                </span>
                                            ) : null}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    <div className="border-border/70 flex flex-col gap-2 border-t px-4 py-3">
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11 w-full"
                            onClick={resetDefaults}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset to role defaults
                        </Button>
                        <Button
                            type="button"
                            className="h-11 w-full"
                            disabled={draft.length !== 3 || saving}
                            onClick={saveShortcuts}
                        >
                            {saving ? 'Saving…' : 'Save shortcuts'}
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function MobileNavItem({
    label,
    href,
    icon: Icon,
    active,
    badge,
}: {
    label: string;
    href: MobileNavDestination['href'];
    icon: typeof Home;
    active: boolean;
    badge?: string;
}) {
    return (
        <li className="min-w-0">
            <Link
                href={href}
                prefetch
                aria-current={active ? 'page' : undefined}
                className={cn(
                    'text-muted-foreground relative flex h-full w-full flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-medium tracking-wide transition-colors',
                    active && 'text-primary',
                )}
            >
                <span className="relative">
                    <Icon
                        className={cn(
                            'size-5 shrink-0',
                            active && 'text-primary',
                        )}
                        aria-hidden="true"
                    />
                    {badge ? (
                        <span className="bg-primary text-primary-foreground absolute -top-1.5 -right-2 min-w-4 rounded-md px-1 text-[9px] leading-4 font-semibold">
                            {badge}
                        </span>
                    ) : null}
                </span>
                <span className="max-w-full truncate">{label}</span>
            </Link>
        </li>
    );
}

import { useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

export function WorkspaceWithSummary({
    main,
    summary,
}: {
    main: ReactNode;
    summary: ReactNode;
}) {
    return (
        <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-4">
            <div className="min-w-0 space-y-4 pb-20 lg:pb-0">{main}</div>
            {summary}
        </div>
    );
}

export function LiveBuildSummary({
    title,
    compactLine,
    children,
    actions,
    compactActions,
}: {
    title: string;
    compactLine: string;
    children: ReactNode;
    actions?: ReactNode;
    compactActions?: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <aside className="hidden lg:sticky lg:top-20 lg:block">
                <SummaryPanel title={title} actions={actions}>
                    {children}
                </SummaryPanel>
            </aside>
            <div className="lg:hidden">
                <div className="sticky-form-actions z-20 justify-between gap-3">
                    <p className="min-w-0 truncate text-xs font-medium">
                        {compactLine}
                    </p>
                    <div className="flex shrink-0 gap-2">
                        {compactActions}
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => setOpen(true)}
                        >
                            Review
                        </Button>
                    </div>
                </div>
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetContent
                        side="bottom"
                        className="max-h-[85vh] overflow-y-auto rounded-t-2xl p-0"
                    >
                        <SheetHeader>
                            <SheetTitle>{title}</SheetTitle>
                            <SheetDescription>
                                Live snapshot of the visit or care plan being
                                built.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="space-y-3 px-4 pb-4 text-sm">
                            {children}
                        </div>
                        {actions && (
                            <div className="border-border/70 flex flex-col gap-2 border-t p-4">
                                {actions}
                            </div>
                        )}
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}

export function SummaryFact({
    label,
    value,
    onClick,
}: {
    label: string;
    value: ReactNode;
    onClick?: () => void;
}) {
    const content = (
        <>
            <dt className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 text-sm font-medium">{value || '—'}</dd>
        </>
    );

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                className="hover:bg-muted/40 w-full rounded-md px-0.5 py-0.5 text-left"
            >
                {content}
            </button>
        );
    }

    return <div>{content}</div>;
}

export function SummaryChips({
    items,
    empty,
    onSelect,
}: {
    items: string[];
    empty: string;
    onSelect?: (index: number) => void;
}) {
    if (items.length === 0) {
        return <p className="text-muted-foreground text-xs">{empty}</p>;
    }

    return (
        <ul className="flex flex-wrap gap-1.5">
            {items.map((item, index) => (
                <li key={`${item}-${index}`}>
                    <button
                        type="button"
                        disabled={!onSelect}
                        onClick={() => onSelect?.(index)}
                        className={cn(
                            'bg-muted/70 inline-flex max-w-full truncate rounded-full px-2.5 py-0.5 text-[11px] font-medium',
                            onSelect && 'hover:bg-muted',
                        )}
                    >
                        {item}
                    </button>
                </li>
            ))}
        </ul>
    );
}

function SummaryPanel({
    title,
    children,
    actions,
}: {
    title: string;
    children: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section className="surface-panel p-4">
            <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                Live
            </p>
            <h2 className="text-sm font-semibold">{title}</h2>
            <div className="mt-3 space-y-3">{children}</div>
            {actions && (
                <div className="border-border/70 mt-4 flex flex-col gap-2 border-t pt-3">
                    {actions}
                </div>
            )}
        </section>
    );
}

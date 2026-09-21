import { Link } from '@inertiajs/react';
import { AlertTriangle, Check, CircleAlert } from 'lucide-react';
import { useState } from 'react';
import { ProfileHealthBadge } from '@/components/mdm/profile-health';
import { ProgressRing } from '@/components/mdm/visual-summaries';
import { cn } from '@/lib/utils';
import type { ProfileCompletion } from '@/types/directory';

export function ProfileCompletionMeter({
    completion,
    complianceLabel,
}: {
    completion: ProfileCompletion;
    complianceLabel?: string | null;
}) {
    const [open, setOpen] = useState(false);
    const remaining = completion.missing;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="hover:bg-muted/40 flex items-center gap-3 rounded-xl px-2 py-1 text-left"
                aria-expanded={open}
            >
                <ProgressRing
                    value={completion.percent}
                    label={`${completion.percent}%`}
                    detail={
                        remaining === 0
                            ? 'Profile complete'
                            : `${remaining} item${remaining === 1 ? '' : 's'} remaining`
                    }
                />
                <ProfileHealthBadge
                    percent={completion.percent}
                    status={completion.status}
                    statusLabel={completion.status_label}
                    tone={completion.tone}
                />
            </button>
            {complianceLabel ? (
                <p className="text-muted-foreground px-2 text-[11px]">
                    Compliance: {complianceLabel}
                </p>
            ) : null}
            {open && (
                <div className="surface-panel absolute right-0 z-20 mt-2 w-72 p-3 shadow-lg">
                    <p className="mb-2 text-xs font-semibold">Profile checklist</p>
                    <ul className="space-y-1.5">
                        {completion.items.map((item) => (
                            <li key={item.key}>
                                {item.href && !item.complete ? (
                                    <Link
                                        href={item.href}
                                        className="hover:bg-muted/50 flex items-center gap-2 rounded-md px-1 py-0.5 text-sm"
                                    >
                                        <ItemMark item={item} />
                                        <span>{item.label}</span>
                                    </Link>
                                ) : (
                                    <div className="flex items-center gap-2 px-1 py-0.5 text-sm">
                                        <ItemMark item={item} />
                                        <span>{item.label}</span>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                    <p className="text-muted-foreground mt-2 text-[11px]">
                        Profile completion is not the same as credential
                        compliance.
                    </p>
                </div>
            )}
        </div>
    );
}

function ItemMark({
    item,
}: {
    item: ProfileCompletion['items'][number];
}) {
    if (item.complete) {
        return (
            <Check
                className="size-3.5 text-emerald-600"
                aria-label="Complete"
            />
        );
    }

    if (item.severity === 'critical') {
        return (
            <CircleAlert
                className="size-3.5 text-destructive"
                aria-label="Critical missing"
            />
        );
    }

    return (
        <AlertTriangle
            className={cn('size-3.5 text-amber-600')}
            aria-label="Needs attention"
        />
    );
}

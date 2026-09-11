import { Head, router, useForm } from '@inertiajs/react';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { controlClassName } from '@/components/mdm/directory';
import { dashboard } from '@/routes';
import { index as announcementsIndex, store, update } from '@/routes/announcements';
import { index as messagesIndex } from '@/routes/messages';
import { cn } from '@/lib/utils';
import type { AnnouncementRecord } from '@/types/messaging';

export default function AnnouncementsIndex({
    announcements,
    audiences,
    can,
}: {
    announcements: AnnouncementRecord[];
    audiences: { value: string; label: string }[];
    can: { create: boolean };
}) {
    const form = useForm({
        title: '',
        body: '',
        audience: audiences[0]?.value ?? 'everyone',
        published_at: '',
        expires_at: '',
        is_active: true as boolean,
    });

    return (
        <>
            <Head title="Announcements" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Announcements
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Organization and caseload notices for people who should
                        see them.
                    </p>
                </div>

                {can.create && (
                    <Panel title="Publish announcement">
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset(),
                                });
                            }}
                        >
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData('title', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="body">Message</Label>
                                <textarea
                                    id="body"
                                    rows={4}
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData('body', event.target.value)
                                    }
                                    className={cn(
                                        controlClassName,
                                        'h-auto min-h-20 py-2',
                                    )}
                                />
                                <InputError message={form.errors.body} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="audience">Audience</Label>
                                <select
                                    id="audience"
                                    value={form.data.audience}
                                    onChange={(event) =>
                                        form.setData(
                                            'audience',
                                            event.target.value,
                                        )
                                    }
                                    className={controlClassName}
                                >
                                    {audiences.map((audience) => (
                                        <option
                                            key={audience.value}
                                            value={audience.value}
                                        >
                                            {audience.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.audience} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="published_at">
                                    Publish date
                                </Label>
                                <Input
                                    id="published_at"
                                    type="datetime-local"
                                    value={form.data.published_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'published_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.published_at} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="expires_at">
                                    Expiry (optional)
                                </Label>
                                <Input
                                    id="expires_at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'expires_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.expires_at} />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={form.processing}>
                                    Publish
                                </Button>
                            </div>
                        </form>
                    </Panel>
                )}

                <Panel title="Visible announcements">
                    {announcements.length === 0 ? (
                        <EmptyState message="No announcements are visible to you." />
                    ) : (
                        <ul className="space-y-4">
                            {announcements.map((item) => (
                                <li
                                    key={item.id}
                                    className={cn(
                                        'rounded-md border p-3',
                                        !item.is_read && 'bg-muted/30',
                                    )}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium">
                                                {item.title}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {item.author_name} ·{' '}
                                                {item.audience_label} ·{' '}
                                                {item.published_at}
                                                {item.expires_at
                                                    ? ` · expires ${item.expires_at}`
                                                    : ''}
                                                {item.is_active
                                                    ? ''
                                                    : ' · inactive'}
                                            </p>
                                        </div>
                                        {item.can_update && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    router.patch(
                                                        update.url(item.id),
                                                        {
                                                            is_active:
                                                                !item.is_active,
                                                        },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {item.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </Button>
                                        )}
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-wrap">
                                        {item.body}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </>
    );
}

AnnouncementsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Messages', href: messagesIndex() },
        { title: 'Announcements', href: announcementsIndex() },
    ],
};

import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CriticalAlertRecord } from '@/types/visit';

type CategoryOption = { value: string; label: string };

export function ClientCriticalAlertsPanel({
    clientId,
    alerts,
    categories,
    canManage,
}: {
    clientId: number;
    alerts: CriticalAlertRecord[];
    categories: CategoryOption[];
    canManage: boolean;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const createForm = useForm({
        category: categories[0]?.value ?? 'supervisor_designated',
        title: '',
        body: '',
        is_urgent: false,
    });

    return (
        <section className="surface-panel space-y-4 p-4 md:p-5">
            <div>
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Critical client alerts
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Reserved for immediate safety, supervision, or serious
                    service-delivery information. Not a general notice board.
                </p>
            </div>

            {alerts.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No critical alerts recorded.
                </p>
            ) : (
                <ul className="space-y-3">
                    {alerts.map((alert) => (
                        <li
                            key={alert.id}
                            className={`rounded-md border px-3 py-3 text-sm ${
                                alert.is_active
                                    ? 'border-destructive/40 bg-destructive/5'
                                    : 'opacity-70'
                            }`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">{alert.title}</p>
                                <p className="text-muted-foreground text-xs">
                                    {alert.is_active ? 'Active' : 'Inactive'}
                                    {alert.is_urgent ? ' · Urgent' : ''}
                                    {' · v'}
                                    {alert.version}
                                </p>
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {alert.category_label}
                            </p>
                            <p className="mt-2 whitespace-pre-wrap">
                                {alert.body}
                            </p>
                            {canManage &&
                            alert.is_active &&
                            editingId === alert.id ? (
                                <EditAlertForm
                                    clientId={clientId}
                                    alert={alert}
                                    categories={categories}
                                    onDone={() => setEditingId(null)}
                                />
                            ) : null}
                            {canManage && alert.is_active ? (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            setEditingId(
                                                editingId === alert.id
                                                    ? null
                                                    : alert.id,
                                            )
                                        }
                                    >
                                        {editingId === alert.id
                                            ? 'Cancel edit'
                                            : 'Edit'}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/clients/${clientId}/critical-alerts/${alert.id}/deactivate`,
                                            )
                                        }
                                    >
                                        Deactivate
                                    </Button>
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {canManage ? (
                <form
                    className="space-y-3 border-t pt-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        createForm.post(
                            `/clients/${clientId}/critical-alerts`,
                            {
                                preserveScroll: true,
                                onSuccess: () =>
                                    createForm.reset(
                                        'title',
                                        'body',
                                        'is_urgent',
                                    ),
                            },
                        );
                    }}
                >
                    <p className="text-sm font-medium">Add critical alert</p>
                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <Label htmlFor="critical-alert-category">
                                Category
                            </Label>
                            <select
                                id="critical-alert-category"
                                className="border-input bg-background mt-1 flex h-9 w-full rounded-md border px-3 text-sm"
                                value={createForm.data.category}
                                onChange={(event) =>
                                    createForm.setData(
                                        'category',
                                        event.target.value,
                                    )
                                }
                            >
                                {categories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.category} />
                        </div>
                        <div>
                            <Label htmlFor="critical-alert-title">Title</Label>
                            <Input
                                id="critical-alert-title"
                                className="mt-1"
                                value={createForm.data.title}
                                onChange={(event) =>
                                    createForm.setData(
                                        'title',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={createForm.errors.title} />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="critical-alert-body">Details</Label>
                        <textarea
                            id="critical-alert-body"
                            className={`${controlClassName} mt-1 h-auto py-2`}
                            rows={3}
                            value={createForm.data.body}
                            onChange={(event) =>
                                createForm.setData('body', event.target.value)
                            }
                        />
                        <InputError message={createForm.errors.body} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.data.is_urgent}
                            onChange={(event) =>
                                createForm.setData(
                                    'is_urgent',
                                    event.target.checked,
                                )
                            }
                        />
                        Mark urgent (strong in-app notice during active visits)
                    </label>
                    <Button
                        type="submit"
                        disabled={
                            createForm.processing ||
                            !createForm.data.title.trim() ||
                            !createForm.data.body.trim()
                        }
                    >
                        Save critical alert
                    </Button>
                </form>
            ) : null}
        </section>
    );
}

function EditAlertForm({
    clientId,
    alert,
    categories,
    onDone,
}: {
    clientId: number;
    alert: CriticalAlertRecord;
    categories: CategoryOption[];
    onDone: () => void;
}) {
    const form = useForm({
        category: alert.category,
        title: alert.title,
        body: alert.body,
        is_urgent: alert.is_urgent,
    });

    return (
        <form
            className="mt-3 space-y-3 border-t pt-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(
                    `/clients/${clientId}/critical-alerts/${alert.id}`,
                    {
                        preserveScroll: true,
                        onSuccess: () => onDone(),
                    },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-2">
                <div>
                    <Label htmlFor={`edit-alert-category-${alert.id}`}>
                        Category
                    </Label>
                    <select
                        id={`edit-alert-category-${alert.id}`}
                        className="border-input bg-background mt-1 flex h-9 w-full rounded-md border px-3 text-sm"
                        value={form.data.category}
                        onChange={(event) =>
                            form.setData('category', event.target.value)
                        }
                    >
                        {categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <Label htmlFor={`edit-alert-title-${alert.id}`}>
                        Title
                    </Label>
                    <Input
                        id={`edit-alert-title-${alert.id}`}
                        className="mt-1"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                </div>
            </div>
            <textarea
                className={`${controlClassName} h-auto py-2`}
                rows={3}
                value={form.data.body}
                onChange={(event) => form.setData('body', event.target.value)}
            />
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.is_urgent}
                    onChange={(event) =>
                        form.setData('is_urgent', event.target.checked)
                    }
                />
                Mark urgent
            </label>
            <Button type="submit" size="sm" disabled={form.processing}>
                Save changes
            </Button>
        </form>
    );
}

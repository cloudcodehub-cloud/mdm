import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { controlClassName } from '@/components/mdm/directory';
import { cn } from '@/lib/utils';
import type {
    CarePlanTaskDraft,
    TaskCatalogItem,
    TaskCatalogPayload,
} from '@/types/care';

const WEEKDAYS = [
    { value: 0, label: 'Su' },
    { value: 1, label: 'Mo' },
    { value: 2, label: 'Tu' },
    { value: 3, label: 'We' },
    { value: 4, label: 'Th' },
    { value: 5, label: 'Fr' },
    { value: 6, label: 'Sa' },
];

const RECURRENCE = [
    'daily',
    'weekly',
    'biweekly',
    'monthly',
    'quarterly',
    'annual',
    'custom',
];

const TIMING = [
    { value: 'morning', label: 'Morning' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'evening', label: 'Evening' },
    { value: 'during_visit', label: 'During Visit' },
];

export function TaskCatalogBuilder({
    catalog,
    selected,
    onChange,
    suggestedBundleIds = [],
}: {
    catalog: TaskCatalogPayload;
    selected: CarePlanTaskDraft[];
    onChange: (tasks: CarePlanTaskDraft[]) => void;
    suggestedBundleIds?: number[];
}) {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<string>('all');
    const [editingKey, setEditingKey] = useState<string | null>(null);
    const [customTitle, setCustomTitle] = useState('');

    const selectedIds = useMemo(
        () =>
            new Set(
                selected
                    .map((task) => task.catalog_item_id)
                    .filter((id): id is number => typeof id === 'number'),
            ),
        [selected],
    );

    const filtered = catalog.items.filter((item) => {
        const matchesCategory =
            category === 'all' || item.category === category;
        const haystack = `${item.title} ${item.category_label}`.toLowerCase();
        return matchesCategory && haystack.includes(query.trim().toLowerCase());
    });

    const addItem = (item: TaskCatalogItem) => {
        if (selectedIds.has(item.id)) {
            return;
        }

        onChange([...selected, draftFromCatalog(item)]);
    };

    const addBundle = (itemIds: number[]) => {
        const extras = catalog.items.filter(
            (item) => itemIds.includes(item.id) && !selectedIds.has(item.id),
        );
        if (extras.length === 0) {
            return;
        }
        onChange([...selected, ...extras.map(draftFromCatalog)]);
    };

    const addCustom = () => {
        const title = customTitle.trim();
        if (title === '') {
            return;
        }
        onChange([
            ...selected,
            {
                catalog_item_id: null,
                title,
                instructions: null,
                recurrence: 'daily',
                recurrence_detail: null,
                weekdays: null,
                interval_weeks: 1,
                preferred_timing: 'during_visit',
                is_required: true,
                note_required: false,
                can_skip: true,
                is_critical: false,
            },
        ]);
        setCustomTitle('');
    };

    return (
        <div className="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
            <div className="space-y-4">
                <div className="flex flex-wrap gap-2">
                    {catalog.bundles.map((bundle) => (
                        <button
                            key={bundle.id}
                            type="button"
                            onClick={() => addBundle(bundle.item_ids)}
                            className={cn(
                                'border-border/80 hover:border-primary/40 hover:bg-primary/8 rounded-full border px-3 py-1.5 text-xs font-medium',
                                suggestedBundleIds.includes(bundle.id) &&
                                    'border-primary/50 bg-primary/10',
                            )}
                        >
                            {suggestedBundleIds.includes(bundle.id)
                                ? `Suggested · ${bundle.name}`
                                : bundle.name}
                        </button>
                    ))}
                </div>
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search tasks"
                    aria-label="Search catalog tasks"
                />
                <div className="flex flex-wrap gap-2">
                    <CategoryChip
                        active={category === 'all'}
                        onClick={() => setCategory('all')}
                        label="All"
                    />
                    {catalog.categories.map((item) => (
                        <CategoryChip
                            key={item.value}
                            active={category === item.value}
                            onClick={() => setCategory(item.value)}
                            label={item.label}
                        />
                    ))}
                </div>
                <ul className="grid gap-2 sm:grid-cols-2">
                    {filtered.map((item) => {
                        const added = selectedIds.has(item.id);
                        return (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => addItem(item)}
                                    disabled={added}
                                    className={cn(
                                        'surface-panel h-full w-full p-3 text-left transition-colors',
                                        added
                                            ? 'opacity-60'
                                            : 'hover:border-primary/35 hover:bg-primary/6',
                                    )}
                                >
                                    <p className="text-sm font-medium">
                                        {item.title}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {item.category_label} ·{' '}
                                        {item.default_recurrence_label}
                                        {item.default_preferred_timing_label
                                            ? ` · ${item.default_preferred_timing_label}`
                                            : ''}
                                    </p>
                                    {added && (
                                        <p className="text-primary mt-2 text-xs font-medium">
                                            Selected
                                        </p>
                                    )}
                                </button>
                            </li>
                        );
                    })}
                </ul>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        value={customTitle}
                        onChange={(event) => setCustomTitle(event.target.value)}
                        placeholder="Add a custom task"
                        aria-label="Custom task title"
                    />
                    <Button type="button" variant="secondary" onClick={addCustom}>
                        Add custom
                    </Button>
                </div>
            </div>
            <div className="surface-panel p-4">
                <p className="text-sm font-semibold">Selected for this client</p>
                <p className="text-muted-foreground mt-1 text-xs">
                    Defaults are suggestions. Override frequency and
                    instructions before saving.
                </p>
                {selected.length === 0 ? (
                    <p className="text-muted-foreground mt-4 text-sm">
                        Tap catalog cards or a bundle to start.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-3">
                        {selected.map((task, index) => {
                            const key = selectedKey(task, index);
                            const open = editingKey === key;
                            return (
                                <li
                                    key={key}
                                    className="border-border/70 rounded-xl border p-3"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {task.title}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {labelFor(task.recurrence)}
                                                {task.preferred_timing
                                                    ? ` · ${labelFor(task.preferred_timing)}`
                                                    : ''}
                                                {task.is_required
                                                    ? ' · required'
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setEditingKey(
                                                        open ? null : key,
                                                    )
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    onChange(
                                                        selected.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                    {open && (
                                        <TaskEditor
                                            task={task}
                                            onChange={(next) => {
                                                const copy = [...selected];
                                                copy[index] = next;
                                                onChange(copy);
                                            }}
                                        />
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}

function draftFromCatalog(item: TaskCatalogItem): CarePlanTaskDraft {
    return {
        catalog_item_id: item.id,
        title: item.title,
        instructions: item.instructions,
        recurrence: item.default_recurrence,
        recurrence_detail: item.default_recurrence_detail,
        weekdays: item.default_weekdays,
        interval_weeks: item.default_interval_weeks,
        preferred_timing: item.default_preferred_timing,
        is_required: item.default_is_required,
        note_required: item.default_note_required,
        can_skip: item.default_can_skip,
        is_critical: item.default_is_critical,
    };
}

function selectedKey(task: CarePlanTaskDraft, index: number): string {
    return `${task.id ?? 'new'}-${task.catalog_item_id ?? 'custom'}-${index}`;
}

function labelFor(value: string): string {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) =>
        letter.toUpperCase(),
    );
}

function CategoryChip({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-full px-3 py-1 text-xs font-medium',
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted/70 text-foreground hover:bg-muted',
            )}
        >
            {label}
        </button>
    );
}

function TaskEditor({
    task,
    onChange,
}: {
    task: CarePlanTaskDraft;
    onChange: (task: CarePlanTaskDraft) => void;
}) {
    return (
        <div className="mt-3 grid gap-2">
            <textarea
                rows={2}
                value={task.instructions ?? ''}
                onChange={(event) =>
                    onChange({ ...task, instructions: event.target.value })
                }
                placeholder="Client-specific instructions"
                className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
            <div className="grid gap-2 sm:grid-cols-2">
                <select
                    value={task.recurrence}
                    onChange={(event) =>
                        onChange({ ...task, recurrence: event.target.value })
                    }
                    className={controlClassName}
                    aria-label="Frequency"
                >
                    {RECURRENCE.map((value) => (
                        <option key={value} value={value}>
                            {labelFor(value)}
                        </option>
                    ))}
                </select>
                <select
                    value={task.preferred_timing ?? ''}
                    onChange={(event) =>
                        onChange({
                            ...task,
                            preferred_timing: event.target.value || null,
                        })
                    }
                    className={controlClassName}
                    aria-label="Preferred timing"
                >
                    <option value="">Timing</option>
                    {TIMING.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </div>
            {(task.recurrence === 'weekly' ||
                task.recurrence === 'biweekly' ||
                task.recurrence === 'custom') && (
                <div className="flex flex-wrap gap-1">
                    {WEEKDAYS.map((day) => {
                        const active = (task.weekdays ?? []).includes(
                            day.value,
                        );
                        return (
                            <button
                                key={day.value}
                                type="button"
                                onClick={() => {
                                    const current = task.weekdays ?? [];
                                    onChange({
                                        ...task,
                                        weekdays: active
                                            ? current.filter(
                                                  (value) =>
                                                      value !== day.value,
                                              )
                                            : [...current, day.value],
                                    });
                                }}
                                className={cn(
                                    'size-8 rounded-full text-xs font-medium',
                                    active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted/70',
                                )}
                            >
                                {day.label}
                            </button>
                        );
                    })}
                </div>
            )}
            {(task.recurrence === 'weekly' ||
                task.recurrence === 'biweekly') && (
                <label className="text-muted-foreground flex items-center gap-2 text-xs">
                    Every
                    <input
                        type="number"
                        min={1}
                        max={12}
                        value={task.interval_weeks ?? 1}
                        onChange={(event) =>
                            onChange({
                                ...task,
                                interval_weeks: Number(event.target.value),
                            })
                        }
                        className="border-input h-8 w-16 rounded-md border px-2 text-sm"
                    />
                    weeks
                </label>
            )}
            {task.recurrence === 'custom' && (
                <Input
                    value={task.recurrence_detail ?? ''}
                    onChange={(event) =>
                        onChange({
                            ...task,
                            recurrence_detail: event.target.value,
                        })
                    }
                    placeholder="Custom recurrence detail"
                />
            )}
            <div className="flex flex-wrap gap-3 text-xs">
                <Flag
                    label="Required"
                    checked={task.is_required}
                    onChange={(is_required) =>
                        onChange({ ...task, is_required })
                    }
                />
                <Flag
                    label="Note required"
                    checked={task.note_required}
                    onChange={(note_required) =>
                        onChange({ ...task, note_required })
                    }
                />
                <Flag
                    label="Can skip"
                    checked={task.can_skip}
                    onChange={(can_skip) => onChange({ ...task, can_skip })}
                />
                <Flag
                    label="Critical"
                    checked={task.is_critical}
                    onChange={(is_critical) =>
                        onChange({ ...task, is_critical })
                    }
                />
            </div>
        </div>
    );
}

function Flag({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-1.5">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            {label}
        </label>
    );
}

import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { controlClassName } from '@/components/mdm/directory';
import { cn } from '@/lib/utils';

export type VisitCareTask = {
    key: string;
    care_plan_task_template_id?: number;
    catalog_item_id?: number | null;
    one_off_id?: number;
    title: string;
    instructions?: string | null;
    recurrence_label: string;
    preferred_timing_label?: string | null;
    due: boolean;
    due_label: string;
    included: boolean;
    is_required: boolean;
    is_critical: boolean;
    note_required: boolean;
    source: string;
    service_group: string;
    services?: Array<{ id: number; name: string }>;
    exclusion_reason?: string | null;
};

export type VisitCatalogOption = {
    id: number;
    title: string;
    instructions?: string | null;
    is_required: boolean;
    note_required: boolean;
    is_critical: boolean;
    service_group: string;
};

export type OneOffDraft = {
    id?: number;
    catalog_item_id?: number | null;
    title: string;
    instructions: string;
    note_required: boolean;
    is_required: boolean;
};

export function VisitCarePlanEditor({
    tasks,
    onChange,
    catalog,
    oneOffs,
    onOneOffsChange,
    errors,
}: {
    tasks: VisitCareTask[];
    onChange: (tasks: VisitCareTask[]) => void;
    catalog: VisitCatalogOption[];
    oneOffs: OneOffDraft[];
    onOneOffsChange: (tasks: OneOffDraft[]) => void;
    errors?: Record<string, string>;
}) {
    const groups = useMemo(() => groupedTasks(tasks), [tasks]);
    const exclusionIndex = tasks.findIndex(
        (task) =>
            task.source === 'care_plan' &&
            !task.included &&
            task.due &&
            (task.is_required || task.is_critical),
    );

    return (
        <section id="visit-care-plan" className="surface-panel space-y-3 p-4 md:p-5">
            <div>
                <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                    3 · Visit Care Plan / Tasks
                </p>
                <h2 className="text-sm font-semibold">Visit Care Plan</h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Customize tasks for this visit only. The client’s permanent
                    care plan is not changed. DSP availability is driven by
                    date and requested time, not by these tasks.
                </p>
            </div>
            {tasks.length === 0 && oneOffs.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No care-plan tasks match the selected client, services, and
                    date.
                </p>
            ) : (
                <div className="space-y-3">
                    {groups.map((group) => (
                        <ServiceTaskGroup
                            key={group.name}
                            group={group}
                            tasks={tasks}
                            errors={errors}
                            onChange={onChange}
                        />
                    ))}
                </div>
            )}
            {exclusionIndex >= 0 && (
                <p className="border-warning/40 bg-warning/10 rounded-md border px-3 py-2 text-sm">
                    A due required or critical task is excluded. Add a reason
                    before scheduling.
                </p>
            )}
            {previewPlanTasks(tasks).map((task, index) => (
                <span key={`override-${task.key}`}>
                    <input
                        type="hidden"
                        name={`task_overrides[${index}][care_plan_task_template_id]`}
                        value={task.care_plan_task_template_id}
                    />
                    <input
                        type="hidden"
                        name={`task_overrides[${index}][included]`}
                        value={task.included ? '1' : '0'}
                    />
                    {!task.included && (
                        <input
                            type="hidden"
                            name={`task_overrides[${index}][exclusion_reason]`}
                            value={task.exclusion_reason ?? ''}
                        />
                    )}
                </span>
            ))}
            <div className="border-border/60 grid gap-2 border-t pt-3">
                <p className="text-xs font-medium">Add another task</p>
                {catalog.length > 0 && (
                    <select
                        className={controlClassName}
                        defaultValue=""
                        aria-label="Add catalog task"
                        onChange={(event) => {
                            const id = Number(event.target.value);
                            const item = catalog.find((row) => row.id === id);
                            event.target.value = '';
                            if (!item) {
                                return;
                            }
                            onOneOffsChange([
                                ...oneOffs,
                                {
                                    catalog_item_id: item.id,
                                    title: item.title,
                                    instructions: item.instructions ?? '',
                                    note_required: item.note_required,
                                    is_required: item.is_required,
                                },
                            ]);
                        }}
                    >
                        <option value="">Add existing catalog task</option>
                        {catalog.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.title}
                                {item.service_group
                                    ? ` · ${item.service_group}`
                                    : ''}
                            </option>
                        ))}
                    </select>
                )}
                <ul className="space-y-2">
                    {oneOffs.map((task, index) => (
                        <li
                            key={task.id ?? `new-${index}`}
                            className="border-border/70 rounded-xl border p-3"
                        >
                            {task.id && (
                                <input
                                    type="hidden"
                                    name={`one_off_tasks[${index}][id]`}
                                    value={task.id}
                                />
                            )}
                            {task.catalog_item_id && (
                                <input
                                    type="hidden"
                                    name={`one_off_tasks[${index}][catalog_item_id]`}
                                    value={task.catalog_item_id}
                                />
                            )}
                            <div className="flex items-start justify-between gap-2">
                                <Input
                                    name={`one_off_tasks[${index}][title]`}
                                    value={task.title}
                                    placeholder="Visit-specific task"
                                    onChange={(event) => {
                                        const next = [...oneOffs];
                                        next[index] = {
                                            ...task,
                                            title: event.target.value,
                                        };
                                        onOneOffsChange(next);
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        onOneOffsChange(
                                            oneOffs.filter(
                                                (_, item) => item !== index,
                                            ),
                                        )
                                    }
                                >
                                    Remove
                                </Button>
                            </div>
                            <textarea
                                name={`one_off_tasks[${index}][instructions]`}
                                value={task.instructions}
                                placeholder="Instructions for this visit"
                                className={`${controlClassName} mt-2 h-auto py-2`}
                                rows={2}
                                onChange={(event) => {
                                    const next = [...oneOffs];
                                    next[index] = {
                                        ...task,
                                        instructions: event.target.value,
                                    };
                                    onOneOffsChange(next);
                                }}
                            />
                        </li>
                    ))}
                </ul>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="w-fit"
                    onClick={() =>
                        onOneOffsChange([
                            ...oneOffs,
                            {
                                title: '',
                                instructions: '',
                                note_required: false,
                                is_required: true,
                            },
                        ])
                    }
                >
                    Add custom one-off task
                </Button>
            </div>
        </section>
    );
}

function ServiceTaskGroup({
    group,
    tasks,
    errors,
    onChange,
}: {
    group: { name: string; due: VisitCareTask[]; other: VisitCareTask[] };
    tasks: VisitCareTask[];
    errors?: Record<string, string>;
    onChange: (tasks: VisitCareTask[]) => void;
}) {
    const [open, setOpen] = useState(true);
    const [otherOpen, setOtherOpen] = useState(false);
    const includedDue = group.due.filter((task) => task.included).length;

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="border-border/70 rounded-lg border"
        >
            <CollapsibleTrigger className="hover:bg-muted/30 flex w-full items-center justify-between gap-2 px-3 py-2 text-left">
                <span>
                    <span className="text-sm font-medium">{group.name}</span>
                    <span className="text-muted-foreground ml-2 text-[11px]">
                        {includedDue} due included · {group.due.length + group.other.length}{' '}
                        tasks
                    </span>
                </span>
                <span className="text-muted-foreground text-[11px]">
                    {open ? 'Hide' : 'Show'}
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-2 px-3 pb-3">
                {group.due.length === 0 && group.other.length === 0 ? (
                    <p className="text-muted-foreground text-xs">
                        No tasks for this service.
                    </p>
                ) : (
                    <>
                        {group.due.length > 0 && (
                            <ul className="space-y-2">
                                {group.due.map((task) => (
                                    <CareTaskCard
                                        key={task.key}
                                        task={task}
                                        compact={false}
                                        error={overrideError(tasks, task, errors)}
                                        onChange={(next) =>
                                            onChange(
                                                tasks.map((row) =>
                                                    row.key === next.key
                                                        ? next
                                                        : row,
                                                ),
                                            )
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                        {group.other.length > 0 && (
                            <Collapsible
                                open={otherOpen}
                                onOpenChange={setOtherOpen}
                            >
                                <CollapsibleTrigger className="text-muted-foreground hover:text-foreground text-[11px] font-medium">
                                    {otherOpen ? 'Hide' : 'Show'}{' '}
                                    {group.other.length} not due / optional
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 space-y-1.5">
                                    {group.other.map((task) => (
                                        <CareTaskCard
                                            key={task.key}
                                            task={task}
                                            compact
                                            error={overrideError(
                                                tasks,
                                                task,
                                                errors,
                                            )}
                                            onChange={(next) =>
                                                onChange(
                                                    tasks.map((row) =>
                                                        row.key === next.key
                                                            ? next
                                                            : row,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </CollapsibleContent>
                            </Collapsible>
                        )}
                    </>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

function CareTaskCard({
    task,
    onChange,
    error,
    compact,
}: {
    task: VisitCareTask;
    onChange: (task: VisitCareTask) => void;
    error?: string;
    compact: boolean;
}) {
    const sensitive =
        task.due && (task.is_required || task.is_critical) && !task.included;
    const extraServices = (task.services ?? [])
        .map((service) => service.name)
        .filter((name) => name !== task.service_group);

    return (
        <li
            id={`visit-task-${task.key}`}
            className={cn(
                'border-border/70 rounded-xl border',
                compact ? 'px-3 py-2' : 'p-3',
                !task.included && 'bg-muted/20',
                sensitive && 'border-warning/50',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">{task.title}</p>
                    {!compact && (
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            {task.recurrence_label}
                            {task.preferred_timing_label
                                ? ` · ${task.preferred_timing_label}`
                                : ''}
                        </p>
                    )}
                </div>
                <label className="flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={task.included}
                        onChange={(event) =>
                            onChange({
                                ...task,
                                included: event.target.checked,
                            })
                        }
                    />
                    Include
                </label>
            </div>
            <div className="mt-1.5 flex flex-wrap gap-1">
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-[10px] font-medium',
                        task.due
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {task.due_label}
                </span>
                {task.is_required && (
                    <span className="bg-status-warning/10 text-status-warning rounded-full px-2 py-0.5 text-[10px] font-medium">
                        Required
                    </span>
                )}
                {task.is_critical && (
                    <span className="bg-destructive/10 text-destructive rounded-full px-2 py-0.5 text-[10px] font-medium">
                        Critical
                    </span>
                )}
                {task.note_required && (
                    <span className="bg-status-info/10 text-status-info rounded-full px-2 py-0.5 text-[10px] font-medium">
                        Note required
                    </span>
                )}
                {extraServices.map((name) => (
                    <span
                        key={name}
                        className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-medium"
                    >
                        Also {name}
                    </span>
                ))}
            </div>
            {!compact && task.instructions && (
                <p className="mt-2 text-xs leading-5">{task.instructions}</p>
            )}
            {!task.included && (
                <textarea
                    value={task.exclusion_reason ?? ''}
                    placeholder={
                        sensitive
                            ? 'Reason required to exclude this due required/critical task'
                            : 'Optional reason for excluding this visit'
                    }
                    className={`${controlClassName} mt-2 h-auto py-2`}
                    rows={compact ? 1 : 2}
                    onChange={(event) =>
                        onChange({
                            ...task,
                            exclusion_reason: event.target.value,
                        })
                    }
                />
            )}
            {error && <p className="text-destructive mt-1 text-sm">{error}</p>}
        </li>
    );
}

function previewPlanTasks(tasks: VisitCareTask[]): VisitCareTask[] {
    return tasks.filter(
        (task): task is VisitCareTask & { care_plan_task_template_id: number } =>
            typeof task.care_plan_task_template_id === 'number',
    );
}

function groupedTasks(tasks: VisitCareTask[]): Array<{
    name: string;
    due: VisitCareTask[];
    other: VisitCareTask[];
}> {
    const order: string[] = [];
    const buckets = new Map<string, VisitCareTask[]>();

    for (const task of tasks) {
        const name = task.service_group || 'Care plan';
        if (!buckets.has(name)) {
            order.push(name);
            buckets.set(name, []);
        }
        buckets.get(name)?.push(task);
    }

    return order.map((name) => {
        const rows = buckets.get(name) ?? [];
        const due = rows.filter(
            (task) => task.due || task.is_required || task.is_critical,
        );
        const dueKeys = new Set(due.map((task) => task.key));
        const other = rows.filter((task) => !dueKeys.has(task.key));

        return { name, due, other };
    });
}

function overrideError(
    tasks: VisitCareTask[],
    task: VisitCareTask,
    errors?: Record<string, string>,
): string | undefined {
    const index = previewPlanTasks(tasks).findIndex(
        (row) => row.key === task.key,
    );

    return errors?.[`task_overrides.${index}.exclusion_reason`];
}

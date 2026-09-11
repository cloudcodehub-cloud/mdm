import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Field, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type WeeklyDay = {
    weekday: number;
    weekday_label: string;
    is_available: boolean;
    starts_at: string | null;
    ends_at: string | null;
    preferred_daypart: string | null;
    configured: boolean;
};

function requestLabel(status: string): string {
    if (status === 'pending') {
        return 'Pending review';
    }
    if (status === 'approved') {
        return 'Approved';
    }
    if (status === 'rejected') {
        return 'Rejected';
    }
    return status;
}

export default function MyAvailability({
    weekly,
    exceptions,
    requests,
    time_off,
}: {
    weekly: WeeklyDay[];
    exceptions: Array<{
        id: number;
        exception_date: string;
        is_available: boolean;
        starts_at: string | null;
        ends_at: string | null;
        note: string | null;
    }>;
    requests: Array<{
        id: number;
        type: string;
        effective_on: string;
        reason: string | null;
        status: string;
        review_note: string | null;
    }>;
    time_off: Array<{
        id: number;
        starts_on: string;
        ends_on: string;
        reason: string | null;
        status: string;
    }>;
}) {
    const [days, setDays] = useState(weekly);

    const updateDay = (weekday: number, patch: Partial<WeeklyDay>) => {
        setDays((current) =>
            current.map((day) =>
                day.weekday === weekday ? { ...day, ...patch } : day,
            ),
        );
    };

    const copyMondayToWeekdays = () => {
        const monday = days.find((day) => day.weekday === 1);
        if (!monday) {
            return;
        }
        setDays((current) =>
            current.map((day) =>
                day.weekday >= 2 && day.weekday <= 5
                    ? {
                          ...day,
                          is_available: monday.is_available,
                          starts_at: monday.starts_at,
                          ends_at: monday.ends_at,
                          preferred_daypart: monday.preferred_daypart,
                      }
                    : day,
            ),
        );
    };

    return (
        <>
            <Head title="My Availability" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        My Availability
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Approved hours stay in effect until a supervisor reviews
                        a change. Time off is separate from weekly availability.
                    </p>
                </div>

                <Panel
                    title="Approved weekly availability"
                    description="Configured days are confirmed. Unconfigured days are not treated as approved coverage."
                >
                    <div className="mb-3 flex h-3 overflow-hidden rounded-md">
                        {weekly.map((day) => (
                            <span
                                key={day.weekday}
                                title={`${day.weekday_label}: ${day.configured ? (day.is_available ? `${day.starts_at}–${day.ends_at}` : 'Unavailable') : 'Not confirmed'}`}
                                className={cn(
                                    'flex-1',
                                    !day.configured
                                        ? 'bg-amber-400/40'
                                        : day.is_available
                                          ? 'bg-emerald-500/70'
                                          : 'bg-slate-400/40',
                                )}
                            />
                        ))}
                    </div>
                    <div className="grid gap-1 text-sm">
                        {weekly.map((day) => (
                            <div
                                key={day.weekday}
                                className="flex flex-wrap justify-between gap-2"
                            >
                                <span className="font-medium">
                                    {day.weekday_label}
                                </span>
                                <span className="text-muted-foreground">
                                    {!day.configured
                                        ? 'Availability not confirmed'
                                        : day.is_available
                                          ? `${day.starts_at ?? '—'}–${day.ends_at ?? '—'}`
                                          : 'Off'}
                                </span>
                            </div>
                        ))}
                    </div>
                </Panel>

                <Panel title="Request weekly change">
                    <Form
                        action="/my-availability/requests"
                        method="post"
                        className="space-y-3"
                    >
                        <input type="hidden" name="type" value="weekly" />
                        <Field label="Effective date" htmlFor="effective_on">
                            <Input
                                id="effective_on"
                                name="effective_on"
                                type="date"
                                required
                            />
                        </Field>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={copyMondayToWeekdays}
                        >
                            Copy Monday to weekdays
                        </Button>
                        <div className="grid gap-2">
                            {days.map((day, index) => (
                                <div
                                    key={day.weekday}
                                    className="border-border/60 grid items-center gap-2 rounded-md border p-2 md:grid-cols-[7rem_auto_1fr_1fr_8rem]"
                                >
                                    <input
                                        type="hidden"
                                        name={`payload[days][${index}][weekday]`}
                                        value={day.weekday}
                                    />
                                    <label className="flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="hidden"
                                            name={`payload[days][${index}][is_available]`}
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            name={`payload[days][${index}][is_available]`}
                                            checked={day.is_available}
                                            value="1"
                                            onChange={(event) =>
                                                updateDay(day.weekday, {
                                                    is_available:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                        {day.weekday_label}
                                    </label>
                                    <span className="text-muted-foreground text-xs">
                                        {day.is_available ? 'On' : 'Off'}
                                    </span>
                                    <Input
                                        type="time"
                                        name={`payload[days][${index}][starts_at]`}
                                        value={day.starts_at ?? '07:00'}
                                        disabled={!day.is_available}
                                        onChange={(event) =>
                                            updateDay(day.weekday, {
                                                starts_at: event.target.value,
                                            })
                                        }
                                    />
                                    <Input
                                        type="time"
                                        name={`payload[days][${index}][ends_at]`}
                                        value={day.ends_at ?? '23:00'}
                                        disabled={!day.is_available}
                                        onChange={(event) =>
                                            updateDay(day.weekday, {
                                                ends_at: event.target.value,
                                            })
                                        }
                                    />
                                    <select
                                        name={`payload[days][${index}][preferred_daypart]`}
                                        value={day.preferred_daypart ?? ''}
                                        className={controlClassName}
                                        disabled={!day.is_available}
                                        onChange={(event) =>
                                            updateDay(day.weekday, {
                                                preferred_daypart:
                                                    event.target.value || null,
                                            })
                                        }
                                    >
                                        <option value="">Daypart</option>
                                        <option value="morning">Morning</option>
                                        <option value="afternoon">
                                            Afternoon
                                        </option>
                                        <option value="evening">Evening</option>
                                        <option value="overnight">
                                            Overnight
                                        </option>
                                    </select>
                                </div>
                            ))}
                        </div>
                        <Field label="Reason" htmlFor="reason">
                            <Input id="reason" name="reason" />
                        </Field>
                        <Button type="submit">Submit weekly request</Button>
                    </Form>
                </Panel>

                <Panel
                    title="One-off exception request"
                    description="A single-date change to availability. This is not time off."
                >
                    <Form
                        action="/my-availability/requests"
                        method="post"
                        className="grid gap-3 md:grid-cols-2"
                    >
                        <input type="hidden" name="type" value="exception" />
                        <Field label="Effective date" htmlFor="ex_effective">
                            <Input
                                id="ex_effective"
                                name="effective_on"
                                type="date"
                                required
                            />
                        </Field>
                        <Field label="Exception date" htmlFor="exception_date">
                            <Input
                                id="exception_date"
                                name="payload[exception_date]"
                                type="date"
                                required
                            />
                        </Field>
                        <Field label="Unavailable from" htmlFor="ex_start">
                            <Input
                                id="ex_start"
                                name="payload[starts_at]"
                                type="time"
                            />
                        </Field>
                        <Field label="Until" htmlFor="ex_end">
                            <Input
                                id="ex_end"
                                name="payload[ends_at]"
                                type="time"
                            />
                        </Field>
                        <input
                            type="hidden"
                            name="payload[is_available]"
                            value="0"
                        />
                        <Field label="Note" htmlFor="ex_note">
                            <Input id="ex_note" name="reason" />
                        </Field>
                        <div className="md:col-span-2">
                            <Button type="submit">Submit exception</Button>
                        </div>
                    </Form>
                    {exceptions.length > 0 && (
                        <ul className="mt-4 space-y-1 text-sm">
                            {exceptions.map((row) => (
                                <li key={row.id}>
                                    {row.exception_date}:{' '}
                                    {row.is_available
                                        ? 'available'
                                        : 'unavailable'}{' '}
                                    {row.starts_at ?? ''}–{row.ends_at ?? ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel
                    title="Time off"
                    description="Leave requests stay separate from weekly hours and exceptions."
                >
                    <Form
                        action="/my-availability/time-off"
                        method="post"
                        className="grid gap-3 md:grid-cols-2"
                    >
                        <Field label="Starts on" htmlFor="starts_on">
                            <Input
                                id="starts_on"
                                name="starts_on"
                                type="date"
                                required
                            />
                        </Field>
                        <Field label="Ends on" htmlFor="ends_on">
                            <Input
                                id="ends_on"
                                name="ends_on"
                                type="date"
                                required
                            />
                        </Field>
                        <Field label="Reason" htmlFor="to_reason">
                            <Input id="to_reason" name="reason" />
                        </Field>
                        <div className="md:col-span-2">
                            <Button type="submit">Request time off</Button>
                        </div>
                    </Form>
                    <ul className="mt-4 space-y-1 text-sm">
                        {time_off.map((row) => (
                            <li key={row.id} className="flex flex-wrap gap-2">
                                {row.starts_on} – {row.ends_on}
                                <StatusBadge
                                    status={row.status}
                                    label={requestLabel(row.status)}
                                />
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Request history">
                    <ul className="space-y-2 text-sm">
                        {requests.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <StatusBadge
                                    status={row.status}
                                    label={requestLabel(row.status)}
                                />
                                <span>
                                    {row.type} effective {row.effective_on}
                                </span>
                                {row.review_note && (
                                    <span className="text-muted-foreground">
                                        {row.review_note}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
        </>
    );
}

MyAvailability.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Availability', href: '/my-availability' },
    ],
};

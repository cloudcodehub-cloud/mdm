import { Form, Head } from '@inertiajs/react';
import { Field, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

                <Panel title="Approved weekly availability">
                    <div className="grid gap-2">
                        {weekly.map((day) => (
                            <div
                                key={day.weekday}
                                className="flex flex-wrap justify-between gap-2 text-sm"
                            >
                                <span className="font-medium">
                                    {day.weekday_label}
                                </span>
                                <span className="text-muted-foreground">
                                    {day.is_available
                                        ? `${day.starts_at ?? '—'}–${day.ends_at ?? '—'}`
                                        : 'Unavailable'}
                                    {!day.configured ? ' (default)' : ''}
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
                        {weekly.map((day, index) => (
                            <div
                                key={day.weekday}
                                className="grid gap-2 md:grid-cols-4"
                            >
                                <input
                                    type="hidden"
                                    name={`payload[days][${index}][weekday]`}
                                    value={day.weekday}
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name={`payload[days][${index}][is_available]`}
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name={`payload[days][${index}][is_available]`}
                                        defaultChecked={day.is_available}
                                        value="1"
                                    />
                                    {day.weekday_label}
                                </label>
                                <Input
                                    type="time"
                                    name={`payload[days][${index}][starts_at]`}
                                    defaultValue={day.starts_at ?? '07:00'}
                                />
                                <Input
                                    type="time"
                                    name={`payload[days][${index}][ends_at]`}
                                    defaultValue={day.ends_at ?? '23:00'}
                                />
                                <select
                                    name={`payload[days][${index}][preferred_daypart]`}
                                    defaultValue={day.preferred_daypart ?? ''}
                                    className={controlClassName}
                                >
                                    <option value="">Daypart</option>
                                    <option value="morning">Morning</option>
                                    <option value="afternoon">Afternoon</option>
                                    <option value="evening">Evening</option>
                                    <option value="overnight">Overnight</option>
                                </select>
                            </div>
                        ))}
                        <Field label="Reason" htmlFor="reason">
                            <Input id="reason" name="reason" />
                        </Field>
                        <Button type="submit">Submit weekly request</Button>
                    </Form>
                </Panel>

                <Panel title="One-off exception request">
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
                        <input type="hidden" name="payload[is_available]" value="0" />
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
                                    {row.is_available ? 'available' : 'unavailable'}{' '}
                                    {row.starts_at ?? ''}–{row.ends_at ?? ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel title="Time off">
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
                            <li key={row.id} className="flex gap-2">
                                {row.starts_on} – {row.ends_on}
                                <StatusBadge status={row.status} label={row.status} />
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Request history">
                    <ul className="space-y-2 text-sm">
                        {requests.map((row) => (
                            <li key={row.id} className="flex flex-wrap gap-2">
                                <StatusBadge status={row.status} label={row.status} />
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

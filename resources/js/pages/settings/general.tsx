import { Form, Head } from '@inertiajs/react';
import GeneralSettingsController from '@/actions/App/Http/Controllers/Settings/GeneralSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    value: string | number;
    label: string;
    group?: string;
};

type SettingChange = {
    id: number;
    setting_label: string;
    summary: string;
    previous_value?: string | null;
    new_value?: string | null;
    reason?: string | null;
    effective_at_label?: string | null;
    actor_name?: string | null;
    created_at_label?: string | null;
};

type Props = {
    settings: {
        organization_name: string;
        display_name?: string | null;
        address_line_1?: string | null;
        address_line_2?: string | null;
        city?: string | null;
        state?: string | null;
        postal_code?: string | null;
        country?: string | null;
        main_phone?: string | null;
        main_email?: string | null;
        website?: string | null;
        timezone: string;
        timezone_label?: string;
        date_format: string;
        time_format: string;
        first_day_of_week: number;
        credential_expiring_soon_days: number;
        incident_acknowledgment_minutes: number;
        staffing_needed_urgency_hours: number;
        logo_url?: string | null;
        has_logo?: boolean;
        horizontal_logo_url?: string | null;
        has_horizontal_logo?: boolean;
    };
    pendingTimezoneChange?: {
        timezone: string;
        timezone_label: string;
        effective_at_local?: string | null;
        effective_at_label?: string | null;
        reason?: string | null;
    } | null;
    timezoneImpactPreview: {
        future_scheduled_visits: number;
        current_today: string;
        proposed_today: string;
        note: string;
    };
    scheduleMigrationPreview: {
        target_timezone: string;
        target_timezone_label: string;
        eligible_count: number;
        migratable_count: number;
        conflict_count: number;
        note: string;
        conflicts: Array<{
            id: number;
            service_date: string;
            conflict_reason?: string | null;
            local_starts_at?: string;
        }>;
    };
    settingChangeHistory: SettingChange[];
    timezoneOptions: Option[];
    dateFormatOptions: Option[];
    timeFormatOptions: Option[];
    firstDayOfWeekOptions: Option[];
};

export default function General({
    settings,
    pendingTimezoneChange,
    timezoneImpactPreview,
    scheduleMigrationPreview,
    settingChangeHistory,
    timezoneOptions,
    dateFormatOptions,
    timeFormatOptions,
    firstDayOfWeekOptions,
}: Props) {
    const groupedTimezones = timezoneOptions.reduce<Record<string, Option[]>>(
        (groups, option) => {
            const group = option.group ?? 'All timezones';
            groups[group] ??= [];
            groups[group].push(option);

            return groups;
        },
        {},
    );

    return (
        <>
            <Head title="General settings" />

            <h1 className="sr-only">General settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Agency settings"
                    description="Agency Profile, branding, and Agency Operational Timezone. Historical UTC timestamps are never rewritten by a timezone change."
                />

                <Form
                    {...GeneralSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    encType="multipart/form-data"
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4">
                                <h2 className="text-base font-medium">Agency Profile</h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="organization_name">
                                        Official Agency Name
                                    </Label>
                                    <Input
                                        id="organization_name"
                                        name="organization_name"
                                        required
                                        defaultValue={settings.organization_name}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        High-risk. Changing Official Agency Name
                                        requires confirmation and a Reason for
                                        Change.
                                    </p>
                                    <InputError message={errors.organization_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="official_name_change_reason">
                                        Official name — Reason for Change
                                    </Label>
                                    <Input
                                        id="official_name_change_reason"
                                        name="official_name_change_reason"
                                        placeholder="Required only when changing Official Agency Name"
                                    />
                                    <InputError
                                        message={errors.official_name_change_reason}
                                    />
                                </div>

                                <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="confirm_official_name_change"
                                        value="1"
                                    />
                                    I confirm this Official Agency Name change
                                </label>
                                <InputError
                                    message={errors.confirm_official_name_change}
                                />

                                <Field
                                    label="Display Name (optional)"
                                    htmlFor="display_name"
                                    error={errors.display_name}
                                >
                                    <Input
                                        id="display_name"
                                        name="display_name"
                                        defaultValue={settings.display_name ?? ''}
                                        placeholder="Falls back to Official Agency Name"
                                    />
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        label="Address Line 1"
                                        htmlFor="address_line_1"
                                        error={errors.address_line_1}
                                    >
                                        <Input
                                            id="address_line_1"
                                            name="address_line_1"
                                            defaultValue={
                                                settings.address_line_1 ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Address Line 2"
                                        htmlFor="address_line_2"
                                        error={errors.address_line_2}
                                    >
                                        <Input
                                            id="address_line_2"
                                            name="address_line_2"
                                            defaultValue={
                                                settings.address_line_2 ?? ''
                                            }
                                        />
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field
                                        label="City / Locality"
                                        htmlFor="city"
                                        error={errors.city}
                                    >
                                        <Input
                                            id="city"
                                            name="city"
                                            defaultValue={settings.city ?? ''}
                                        />
                                    </Field>
                                    <Field
                                        label="State / Province / Region"
                                        htmlFor="state"
                                        error={errors.state}
                                    >
                                        <Input
                                            id="state"
                                            name="state"
                                            defaultValue={settings.state ?? ''}
                                        />
                                    </Field>
                                    <Field
                                        label="Postal code"
                                        htmlFor="postal_code"
                                        error={errors.postal_code}
                                    >
                                        <Input
                                            id="postal_code"
                                            name="postal_code"
                                            defaultValue={
                                                settings.postal_code ?? ''
                                            }
                                        />
                                    </Field>
                                </div>

                                <Field
                                    label="Country"
                                    htmlFor="country"
                                    error={errors.country}
                                >
                                    <Input
                                        id="country"
                                        name="country"
                                        defaultValue={
                                            settings.country ?? 'United States'
                                        }
                                    />
                                </Field>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field
                                        label="Main Phone"
                                        htmlFor="main_phone"
                                        error={errors.main_phone}
                                    >
                                        <Input
                                            id="main_phone"
                                            name="main_phone"
                                            defaultValue={
                                                settings.main_phone ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Main Email"
                                        htmlFor="main_email"
                                        error={errors.main_email}
                                    >
                                        <Input
                                            id="main_email"
                                            name="main_email"
                                            type="email"
                                            defaultValue={
                                                settings.main_email ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Website"
                                        htmlFor="website"
                                        error={errors.website}
                                    >
                                        <Input
                                            id="website"
                                            name="website"
                                            defaultValue={settings.website ?? ''}
                                        />
                                    </Field>
                                </div>
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-base font-medium">Branding</h2>
                                <p className="text-muted-foreground text-xs">
                                    Two-logo maximum (MDM 11-06). Primary for
                                    documents/general branding; Horizontal preferred
                                    for the application shell. MDM remains the
                                    product brand.
                                </p>
                                <div className="grid gap-2">
                                    <Label htmlFor="logo">Primary Agency logo</Label>
                                    {settings.logo_url ? (
                                        <img
                                            src={settings.logo_url}
                                            alt=""
                                            className="h-12 w-auto object-contain"
                                        />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            Optional. Used for PDFs/documents and as
                                            shell fallback.
                                        </p>
                                    )}
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                    />
                                    {settings.has_logo ? (
                                        <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="remove_logo"
                                                value="1"
                                            />
                                            Remove primary logo
                                        </label>
                                    ) : null}
                                    <InputError message={errors.logo} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="horizontal_logo">
                                        Horizontal Agency logo
                                    </Label>
                                    {settings.horizontal_logo_url ? (
                                        <img
                                            src={settings.horizontal_logo_url}
                                            alt=""
                                            className="h-10 w-auto max-w-xs object-contain"
                                        />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            Optional. Preferred in the application
                                            header when present.
                                        </p>
                                    )}
                                    <Input
                                        id="horizontal_logo"
                                        name="horizontal_logo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                    />
                                    {settings.has_horizontal_logo ? (
                                        <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="remove_horizontal_logo"
                                                value="1"
                                            />
                                            Remove horizontal logo
                                        </label>
                                    ) : null}
                                    <InputError message={errors.horizontal_logo} />
                                </div>
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    Agency Operational Timezone
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Current:{' '}
                                    <span className="text-foreground font-medium">
                                        {settings.timezone_label ??
                                            settings.timezone}
                                    </span>{' '}
                                    ({settings.timezone}). There is no personal or
                                    browser display timezone.
                                </p>

                                {pendingTimezoneChange ? (
                                    <div className="border-border space-y-2 border p-3 text-sm">
                                        <p>
                                            Pending change to{' '}
                                            <strong>
                                                {pendingTimezoneChange.timezone_label}
                                            </strong>{' '}
                                            effective{' '}
                                            {pendingTimezoneChange.effective_at_label}.
                                        </p>
                                        {pendingTimezoneChange.reason ? (
                                            <p className="text-muted-foreground">
                                                Reason: {pendingTimezoneChange.reason}
                                            </p>
                                        ) : null}
                                        <label className="text-muted-foreground flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="cancel_pending_timezone"
                                                value="1"
                                            />
                                            Cancel pending timezone change
                                        </label>
                                    </div>
                                ) : null}

                                <Field
                                    label="New Operational Time Zone (IANA)"
                                    htmlFor="timezone"
                                    error={errors.timezone}
                                >
                                    <select
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={settings.timezone}
                                        className={controlClassName}
                                    >
                                        {Object.entries(groupedTimezones).map(
                                            ([group, options]) => (
                                                <optgroup key={group} label={group}>
                                                    {options.map((option) => (
                                                        <option
                                                            key={String(option.value)}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            ),
                                        )}
                                    </select>
                                </Field>

                                <Field
                                    label="Effective date/time (Agency timezone)"
                                    htmlFor="timezone_effective_at"
                                    error={errors.timezone_effective_at}
                                >
                                    <Input
                                        id="timezone_effective_at"
                                        name="timezone_effective_at"
                                        type="datetime-local"
                                        defaultValue={
                                            pendingTimezoneChange?.effective_at_local ??
                                            ''
                                        }
                                    />
                                </Field>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone_change_reason">
                                        Timezone — Reason for Change
                                    </Label>
                                    <Input
                                        id="timezone_change_reason"
                                        name="timezone_change_reason"
                                        placeholder="Required when scheduling a timezone change"
                                    />
                                    <InputError
                                        message={errors.timezone_change_reason}
                                    />
                                </div>

                                <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="confirm_timezone_change"
                                        value="1"
                                    />
                                    I confirm this Agency Operational Timezone
                                    change
                                </label>
                                <InputError message={errors.confirm_timezone_change} />

                                <div className="text-muted-foreground space-y-1 text-xs">
                                    <p>
                                        Impact preview: {timezoneImpactPreview.future_scheduled_visits}{' '}
                                        future Scheduled Visit(s) retain their stamped
                                        schedule timezone. Operational today under
                                        current TZ: {timezoneImpactPreview.current_today}.
                                    </p>
                                    <p>{timezoneImpactPreview.note}</p>
                                    <p>
                                        Changing Agency timezone does not migrate
                                        schedules. Use the controlled migration action
                                        below when local service-time intent must move.
                                    </p>
                                </div>
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    Formats & operational thresholds
                                </h2>

                                <Field
                                    label="Date Format"
                                    htmlFor="date_format"
                                    error={errors.date_format}
                                >
                                    <select
                                        id="date_format"
                                        name="date_format"
                                        defaultValue={settings.date_format}
                                        className={controlClassName}
                                        required
                                    >
                                        {dateFormatOptions.map((option) => (
                                            <option
                                                key={String(option.value)}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field
                                    label="Time Format"
                                    htmlFor="time_format"
                                    error={errors.time_format}
                                >
                                    <select
                                        id="time_format"
                                        name="time_format"
                                        defaultValue={settings.time_format}
                                        className={controlClassName}
                                        required
                                    >
                                        {timeFormatOptions.map((option) => (
                                            <option
                                                key={String(option.value)}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field
                                    label="First Day of Week"
                                    htmlFor="first_day_of_week"
                                    error={errors.first_day_of_week}
                                >
                                    <select
                                        id="first_day_of_week"
                                        name="first_day_of_week"
                                        defaultValue={settings.first_day_of_week}
                                        className={controlClassName}
                                        required
                                    >
                                        {firstDayOfWeekOptions.map((option) => (
                                            <option
                                                key={String(option.value)}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <div className="grid gap-2">
                                    <Label htmlFor="credential_expiring_soon_days">
                                        Credential Expiring-Soon Threshold (days)
                                    </Label>
                                    <Input
                                        id="credential_expiring_soon_days"
                                        name="credential_expiring_soon_days"
                                        type="number"
                                        min={1}
                                        max={365}
                                        required
                                        defaultValue={
                                            settings.credential_expiring_soon_days
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.credential_expiring_soon_days
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="incident_acknowledgment_minutes">
                                        Incident acknowledgment threshold (minutes)
                                    </Label>
                                    <Input
                                        id="incident_acknowledgment_minutes"
                                        name="incident_acknowledgment_minutes"
                                        type="number"
                                        min={1}
                                        max={1440}
                                        required
                                        defaultValue={
                                            settings.incident_acknowledgment_minutes
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        MDM 07 default is 60 minutes. Changes apply
                                        prospectively and do not rewrite historical
                                        acknowledgment timestamps.
                                    </p>
                                    <InputError
                                        message={
                                            errors.incident_acknowledgment_minutes
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="staffing_needed_urgency_hours">
                                        Staffing Needed urgency threshold (hours
                                        before start)
                                    </Label>
                                    <Input
                                        id="staffing_needed_urgency_hours"
                                        name="staffing_needed_urgency_hours"
                                        type="number"
                                        min={1}
                                        max={720}
                                        required
                                        defaultValue={
                                            settings.staffing_needed_urgency_hours
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Distant future Staffing Needed visits stay
                                        with the owning Supervisor. Once a visit is
                                        within this many hours of its scheduled
                                        start (Agency timezone), it is marked Urgent
                                        and Admins are alerted once.
                                    </p>
                                    <InputError
                                        message={
                                            errors.staffing_needed_urgency_hours
                                        }
                                    />
                                </div>
                            </section>

                            {settingChangeHistory.length > 0 ? (
                                <section className="space-y-3">
                                    <h2 className="text-base font-medium">
                                        Settings audit history
                                    </h2>
                                    <ul className="space-y-2 text-sm">
                                        {settingChangeHistory.map((event) => (
                                            <li
                                                key={event.id}
                                                className="border-border border p-3"
                                            >
                                                <p className="font-medium">
                                                    {event.setting_label}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {event.previous_value ?? '—'} →{' '}
                                                    {event.new_value ?? '—'}
                                                </p>
                                                {event.reason ? (
                                                    <p>Reason: {event.reason}</p>
                                                ) : null}
                                                <p className="text-muted-foreground text-xs">
                                                    {[
                                                        event.actor_name,
                                                        event.created_at_label,
                                                        event.effective_at_label
                                                            ? `Effective ${event.effective_at_label}`
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null}

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} type="submit">
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Form
                    {...GeneralSettingsController.migrateSchedules.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4 border-border border-t pt-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Heading
                                variant="small"
                                title="Controlled schedule timezone migration"
                                description="Separate Admin action. Preserves local service-time intent, recalculates UTC via IANA/DST, and never rewrites completed Visits, EVV, attendance, or assignment history."
                            />

                            <input
                                type="hidden"
                                name="target_timezone"
                                value={scheduleMigrationPreview.target_timezone}
                            />

                            <div className="text-muted-foreground space-y-1 text-sm">
                                <p>
                                    Target:{' '}
                                    <span className="text-foreground font-medium">
                                        {scheduleMigrationPreview.target_timezone_label}
                                    </span>{' '}
                                    ({scheduleMigrationPreview.target_timezone})
                                </p>
                                <p>
                                    Eligible {scheduleMigrationPreview.eligible_count} ·
                                    Migratable {scheduleMigrationPreview.migratable_count} ·
                                    DST conflicts skipped{' '}
                                    {scheduleMigrationPreview.conflict_count}
                                </p>
                                <p className="text-xs">{scheduleMigrationPreview.note}</p>
                            </div>

                            {scheduleMigrationPreview.conflicts.length > 0 ? (
                                <ul className="text-muted-foreground space-y-1 text-xs">
                                    {scheduleMigrationPreview.conflicts
                                        .slice(0, 10)
                                        .map((row) => (
                                            <li key={row.id}>
                                                Visit #{row.id} on {row.service_date}{' '}
                                                {row.local_starts_at ?? ''} —{' '}
                                                {row.conflict_reason}
                                            </li>
                                        ))}
                                </ul>
                            ) : null}

                            <Field
                                label="Migration effective date/time (optional)"
                                htmlFor="migration_effective_at"
                                error={errors.migration_effective_at}
                            >
                                <Input
                                    id="migration_effective_at"
                                    name="migration_effective_at"
                                    type="datetime-local"
                                />
                            </Field>

                            <div className="grid gap-2">
                                <Label htmlFor="schedule_migration_reason">
                                    Reason for Change
                                </Label>
                                <Input
                                    id="schedule_migration_reason"
                                    name="schedule_migration_reason"
                                    required
                                    placeholder="Required for schedule migration"
                                />
                                <InputError
                                    message={errors.schedule_migration_reason}
                                />
                            </div>

                            <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="confirm_schedule_migration"
                                    value="1"
                                    required
                                />
                                I confirm this controlled schedule timezone migration
                            </label>
                            <InputError
                                message={errors.confirm_schedule_migration}
                            />

                            <Button
                                disabled={
                                    processing ||
                                    scheduleMigrationPreview.migratable_count < 1
                                }
                                type="submit"
                            >
                                Migrate future schedules
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

General.layout = {
    breadcrumbs: [
        {
            title: 'General settings',
            href: '/settings/general',
        },
    ],
};

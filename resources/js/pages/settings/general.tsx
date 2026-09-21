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

type Props = {
        settings: {
            organization_name: string;
            city?: string | null;
            state?: string | null;
            postal_code?: string | null;
            timezone: string;
        date_format: string;
        time_format: string;
        first_day_of_week: number;
        credential_expiring_soon_days: number;
        logo_url?: string | null;
        has_logo?: boolean;
    };
    timezoneOptions: Option[];
    dateFormatOptions: Option[];
    timeFormatOptions: Option[];
    firstDayOfWeekOptions: Option[];
};

export default function General({
    settings,
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
                    title="General"
                    description="Agency identity and operational settings. Changing timezone does not rewrite historical timestamps."
                />

                <Form
                    {...GeneralSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="organization_name">
                                    Agency Name
                                </Label>
                                <Input
                                    id="organization_name"
                                    name="organization_name"
                                    required
                                    defaultValue={settings.organization_name}
                                />
                                <InputError message={errors.organization_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">Agency logo</Label>
                                {settings.logo_url ? (
                                    <img
                                        src={settings.logo_url}
                                        alt=""
                                        className="h-12 w-auto object-contain"
                                    />
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        Optional. MDM branding is used when no
                                        logo is uploaded.
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
                                        <input type="checkbox" name="remove_logo" value="1" />
                                        Remove current logo
                                    </label>
                                ) : null}
                                <InputError message={errors.logo} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <Field label="City" htmlFor="city" error={errors.city}>
                                    <Input
                                        id="city"
                                        name="city"
                                        defaultValue={settings.city ?? ''}
                                    />
                                </Field>
                                <Field label="State" htmlFor="state" error={errors.state}>
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
                                        defaultValue={settings.postal_code ?? ''}
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Operational Time Zone"
                                htmlFor="timezone"
                                error={errors.timezone}
                            >
                                <select
                                    id="timezone"
                                    name="timezone"
                                    defaultValue={settings.timezone}
                                    className={controlClassName}
                                    required
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
                                    message={errors.credential_expiring_soon_days}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} type="submit">Save</Button>
                            </div>
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

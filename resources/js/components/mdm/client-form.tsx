import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Field, controlClassName } from '@/components/mdm/directory';
import { FillDemoDataButton } from '@/components/mdm/fill-demo-data-button';
import { Input } from '@/components/ui/input';
import { demoClientFill } from '@/lib/demo-form-fill';
import type { ClientDetail, OptionItem } from '@/types/directory';

const clientStatuses = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'discharged', label: 'Discharged' },
];

export function ClientForm({
    action,
    method,
    client,
    supervisors,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put' | 'patch';
    client?: ClientDetail;
    supervisors: OptionItem[];
    submitLabel: string;
}) {
    const demoEnabled = Boolean(usePage().props.demoTools?.enabled) && !client;
    const [fillKey, setFillKey] = useState(0);
    const [defaults, setDefaults] = useState({
        first_name: client?.first_name ?? '',
        middle_name: client?.middle_name ?? '',
        last_name: client?.last_name ?? '',
        client_number: client?.client_number ?? '',
        email: client?.email ?? '',
        phone: client?.phone ?? '',
        date_of_birth: client?.date_of_birth ?? '',
        status: client?.status ?? 'active',
        supervisor_id: client?.supervisor_id ? String(client.supervisor_id) : '',
        address_line_1: client?.address_line_1 ?? '',
        address_line_2: client?.address_line_2 ?? '',
        city: client?.city ?? '',
        state: client?.state ?? '',
        postal_code: client?.postal_code ?? '',
        emergency_contact_name: client?.emergency_contact_name ?? '',
        emergency_contact_relationship: client?.emergency_contact_relationship ?? '',
        emergency_contact_phone: client?.emergency_contact_phone ?? '',
        notes: client?.notes ?? '',
    });

    return (
        <Form action={action} method={method} encType="multipart/form-data" className="space-y-6" key={fillKey}>
            {({ processing, errors }) => (
                <>
                    {demoEnabled && (
                    <div className="flex justify-end">
                        <FillDemoDataButton
                            enabled={demoEnabled}
                            onFill={() => {
                                setDefaults({
                                    ...defaults,
                                    ...demoClientFill(supervisors),
                                });
                                setFillKey((value) => value + 1);
                            }}
                        />
                    </div>
                    )}
                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Profile
                        </h2>
                        <Field label="Profile photo" htmlFor="profile_photo" error={errors.profile_photo}>
                            <Input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" />
                        </Field>
                        {client?.photo_url ? (
                            <p className="text-muted-foreground text-xs md:col-span-2">
                                A photo is already on file. Upload a new image to replace it.
                            </p>
                        ) : null}
                        <Field label="First name" htmlFor="first_name" error={errors.first_name}>
                            <Input id="first_name" name="first_name" required defaultValue={defaults.first_name} />
                        </Field>
                        <Field label="Middle name" htmlFor="middle_name" error={errors.middle_name}>
                            <Input id="middle_name" name="middle_name" defaultValue={defaults.middle_name} />
                        </Field>
                        <Field label="Last name" htmlFor="last_name" error={errors.last_name}>
                            <Input id="last_name" name="last_name" required defaultValue={defaults.last_name} />
                        </Field>
                        <Field label="Client number" htmlFor="client_number" error={errors.client_number}>
                            <Input
                                id="client_number"
                                name="client_number"
                                placeholder="Auto-assigned if blank"
                                defaultValue={defaults.client_number}
                            />
                        </Field>
                        <Field label="Email" htmlFor="email" error={errors.email}>
                            <Input id="email" name="email" type="email" defaultValue={defaults.email} />
                        </Field>
                        <Field label="Phone" htmlFor="phone" error={errors.phone}>
                            <Input id="phone" name="phone" defaultValue={defaults.phone} />
                        </Field>
                        <Field label="Date of birth" htmlFor="date_of_birth" error={errors.date_of_birth}>
                            <Input id="date_of_birth" name="date_of_birth" type="date" defaultValue={defaults.date_of_birth} />
                        </Field>
                        <Field label="Status" htmlFor="status" error={errors.status}>
                            <select
                                id="status"
                                name="status"
                                defaultValue={defaults.status}
                                className={controlClassName}
                            >
                                {clientStatuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Assigned supervisor" htmlFor="supervisor_id" error={errors.supervisor_id}>
                            <select
                                id="supervisor_id"
                                name="supervisor_id"
                                defaultValue={defaults.supervisor_id}
                                className={controlClassName}
                            >
                                <option value="">Unassigned</option>
                                {supervisors.map((supervisor) => (
                                    <option key={supervisor.id} value={supervisor.id}>
                                        {supervisor.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Address and emergency contact
                        </h2>
                        <Field label="Address line 1" htmlFor="address_line_1" error={errors.address_line_1}>
                            <Input id="address_line_1" name="address_line_1" defaultValue={defaults.address_line_1} />
                        </Field>
                        <Field label="Address line 2" htmlFor="address_line_2" error={errors.address_line_2}>
                            <Input id="address_line_2" name="address_line_2" defaultValue={defaults.address_line_2} />
                        </Field>
                        <Field label="City" htmlFor="city" error={errors.city}>
                            <Input id="city" name="city" defaultValue={defaults.city} />
                        </Field>
                        <Field label="State" htmlFor="state" error={errors.state}>
                            <Input id="state" name="state" defaultValue={defaults.state} />
                        </Field>
                        <Field label="Postal code" htmlFor="postal_code" error={errors.postal_code}>
                            <Input id="postal_code" name="postal_code" defaultValue={defaults.postal_code} />
                        </Field>
                        <Field label="Emergency contact" htmlFor="emergency_contact_name" error={errors.emergency_contact_name}>
                            <Input id="emergency_contact_name" name="emergency_contact_name" defaultValue={defaults.emergency_contact_name} />
                        </Field>
                        <Field label="Relationship" htmlFor="emergency_contact_relationship" error={errors.emergency_contact_relationship}>
                            <Input id="emergency_contact_relationship" name="emergency_contact_relationship" defaultValue={defaults.emergency_contact_relationship} />
                        </Field>
                        <Field label="Emergency phone" htmlFor="emergency_contact_phone" error={errors.emergency_contact_phone}>
                            <Input id="emergency_contact_phone" name="emergency_contact_phone" defaultValue={defaults.emergency_contact_phone} />
                        </Field>
                        <Field label="Notes" htmlFor="notes" error={errors.notes}>
                            <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                defaultValue={defaults.notes}
                                className={`${controlClassName} h-auto py-2`}
                            />
                        </Field>
                    </section>

                    <div className="sticky-form-actions">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-md px-4 text-sm font-medium shadow-xs disabled:opacity-50"
                        >
                            {submitLabel}
                        </button>
                    </div>
                </>
            )}
        </Form>
    );
}

import { Form } from '@inertiajs/react';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Input } from '@/components/ui/input';
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
    return (
        <Form action={action} method={method} encType="multipart/form-data" className="space-y-6">
            {({ processing, errors }) => (
                <>
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
                            <Input id="first_name" name="first_name" required defaultValue={client?.first_name} />
                        </Field>
                        <Field label="Middle name" htmlFor="middle_name" error={errors.middle_name}>
                            <Input id="middle_name" name="middle_name" defaultValue={client?.middle_name ?? ''} />
                        </Field>
                        <Field label="Last name" htmlFor="last_name" error={errors.last_name}>
                            <Input id="last_name" name="last_name" required defaultValue={client?.last_name} />
                        </Field>
                        <Field label="Client number" htmlFor="client_number" error={errors.client_number}>
                            <Input
                                id="client_number"
                                name="client_number"
                                placeholder="Auto-assigned if blank"
                                defaultValue={client?.client_number}
                            />
                        </Field>
                        <Field label="Email" htmlFor="email" error={errors.email}>
                            <Input id="email" name="email" type="email" defaultValue={client?.email ?? ''} />
                        </Field>
                        <Field label="Phone" htmlFor="phone" error={errors.phone}>
                            <Input id="phone" name="phone" defaultValue={client?.phone ?? ''} />
                        </Field>
                        <Field label="Date of birth" htmlFor="date_of_birth" error={errors.date_of_birth}>
                            <Input id="date_of_birth" name="date_of_birth" type="date" defaultValue={client?.date_of_birth ?? ''} />
                        </Field>
                        <Field label="Status" htmlFor="status" error={errors.status}>
                            <select
                                id="status"
                                name="status"
                                defaultValue={client?.status ?? 'active'}
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
                                defaultValue={client?.supervisor_id ?? ''}
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
                            <Input id="address_line_1" name="address_line_1" defaultValue={client?.address_line_1 ?? ''} />
                        </Field>
                        <Field label="Address line 2" htmlFor="address_line_2" error={errors.address_line_2}>
                            <Input id="address_line_2" name="address_line_2" defaultValue={client?.address_line_2 ?? ''} />
                        </Field>
                        <Field label="City" htmlFor="city" error={errors.city}>
                            <Input id="city" name="city" defaultValue={client?.city ?? ''} />
                        </Field>
                        <Field label="State" htmlFor="state" error={errors.state}>
                            <Input id="state" name="state" defaultValue={client?.state ?? ''} />
                        </Field>
                        <Field label="Postal code" htmlFor="postal_code" error={errors.postal_code}>
                            <Input id="postal_code" name="postal_code" defaultValue={client?.postal_code ?? ''} />
                        </Field>
                        <Field label="Emergency contact" htmlFor="emergency_contact_name" error={errors.emergency_contact_name}>
                            <Input id="emergency_contact_name" name="emergency_contact_name" defaultValue={client?.emergency_contact_name ?? ''} />
                        </Field>
                        <Field label="Relationship" htmlFor="emergency_contact_relationship" error={errors.emergency_contact_relationship}>
                            <Input id="emergency_contact_relationship" name="emergency_contact_relationship" defaultValue={client?.emergency_contact_relationship ?? ''} />
                        </Field>
                        <Field label="Emergency phone" htmlFor="emergency_contact_phone" error={errors.emergency_contact_phone}>
                            <Input id="emergency_contact_phone" name="emergency_contact_phone" defaultValue={client?.emergency_contact_phone ?? ''} />
                        </Field>
                        <Field label="Notes" htmlFor="notes" error={errors.notes}>
                            <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                defaultValue={client?.notes ?? ''}
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

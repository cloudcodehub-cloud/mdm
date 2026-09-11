import { Form } from '@inertiajs/react';
import {
    Field,
    controlClassName,
} from '@/components/mdm/directory';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { EmployeeDetail, OptionItem } from '@/types/directory';

const jobTypes = [
    { value: 'dsp', label: 'DSP' },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'other', label: 'Other' },
];

const employmentStatuses = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'terminated', label: 'Terminated' },
];

export function EmployeeForm({
    action,
    method,
    employee,
    supervisors,
    linkableUsers,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put' | 'patch';
    employee?: EmployeeDetail;
    supervisors: OptionItem[];
    linkableUsers: OptionItem[];
    submitLabel: string;
}) {
    return (
        <Form
            action={action}
            method={method}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Profile
                        </h2>
                        <Field label="First name" htmlFor="first_name" error={errors.first_name}>
                            <Input id="first_name" name="first_name" required defaultValue={employee?.first_name} />
                        </Field>
                        <Field label="Middle name" htmlFor="middle_name" error={errors.middle_name}>
                            <Input id="middle_name" name="middle_name" defaultValue={employee?.middle_name ?? ''} />
                        </Field>
                        <Field label="Last name" htmlFor="last_name" error={errors.last_name}>
                            <Input id="last_name" name="last_name" required defaultValue={employee?.last_name} />
                        </Field>
                        <Field label="Employee number" htmlFor="employee_number" error={errors.employee_number}>
                            <Input
                                id="employee_number"
                                name="employee_number"
                                placeholder="Auto-assigned if blank"
                                defaultValue={employee?.employee_number}
                            />
                        </Field>
                        <Field label="Email" htmlFor="email" error={errors.email}>
                            <Input id="email" name="email" type="email" defaultValue={employee?.email ?? ''} />
                        </Field>
                        <Field label="Phone" htmlFor="phone" error={errors.phone}>
                            <Input id="phone" name="phone" defaultValue={employee?.phone ?? ''} />
                        </Field>
                        <Field label="Date of birth" htmlFor="date_of_birth" error={errors.date_of_birth}>
                            <Input id="date_of_birth" name="date_of_birth" type="date" defaultValue={employee?.date_of_birth ?? ''} />
                        </Field>
                        <Field label="Hire date" htmlFor="hired_on" error={errors.hired_on}>
                            <Input id="hired_on" name="hired_on" type="date" defaultValue={employee?.hired_on ?? ''} />
                        </Field>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Address and emergency contact
                        </h2>
                        <Field label="Address line 1" htmlFor="address_line_1" error={errors.address_line_1}>
                            <Input id="address_line_1" name="address_line_1" defaultValue={employee?.address_line_1 ?? ''} />
                        </Field>
                        <Field label="Address line 2" htmlFor="address_line_2" error={errors.address_line_2}>
                            <Input id="address_line_2" name="address_line_2" defaultValue={employee?.address_line_2 ?? ''} />
                        </Field>
                        <Field label="City" htmlFor="city" error={errors.city}>
                            <Input id="city" name="city" defaultValue={employee?.city ?? ''} />
                        </Field>
                        <Field label="State" htmlFor="state" error={errors.state}>
                            <Input id="state" name="state" defaultValue={employee?.state ?? ''} />
                        </Field>
                        <Field label="Postal code" htmlFor="postal_code" error={errors.postal_code}>
                            <Input id="postal_code" name="postal_code" defaultValue={employee?.postal_code ?? ''} />
                        </Field>
                        <Field label="Emergency contact" htmlFor="emergency_contact_name" error={errors.emergency_contact_name}>
                            <Input id="emergency_contact_name" name="emergency_contact_name" defaultValue={employee?.emergency_contact_name ?? ''} />
                        </Field>
                        <Field label="Relationship" htmlFor="emergency_contact_relationship" error={errors.emergency_contact_relationship}>
                            <Input id="emergency_contact_relationship" name="emergency_contact_relationship" defaultValue={employee?.emergency_contact_relationship ?? ''} />
                        </Field>
                        <Field label="Emergency phone" htmlFor="emergency_contact_phone" error={errors.emergency_contact_phone}>
                            <Input id="emergency_contact_phone" name="emergency_contact_phone" defaultValue={employee?.emergency_contact_phone ?? ''} />
                        </Field>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Role and status
                        </h2>
                        <Field label="Job title" htmlFor="job_title" error={errors.job_title}>
                            <Input id="job_title" name="job_title" defaultValue={employee?.job_title ?? ''} />
                        </Field>
                        <Field label="Job type" htmlFor="job_type" error={errors.job_type}>
                            <select
                                id="job_type"
                                name="job_type"
                                defaultValue={employee?.job_type ?? 'dsp'}
                                className={controlClassName}
                            >
                                {jobTypes.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Supervisor" htmlFor="supervisor_id" error={errors.supervisor_id}>
                            <select
                                id="supervisor_id"
                                name="supervisor_id"
                                defaultValue={employee?.supervisor_id ?? ''}
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
                        <Field label="Employment status" htmlFor="employment_status" error={errors.employment_status}>
                            <select
                                id="employment_status"
                                name="employment_status"
                                defaultValue={employee?.employment_status ?? 'active'}
                                className={controlClassName}
                            >
                                {employmentStatuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Terminated on" htmlFor="terminated_on" error={errors.terminated_on}>
                            <Input id="terminated_on" name="terminated_on" type="date" defaultValue={employee?.terminated_on ?? ''} />
                        </Field>
                        <Field label="Notes" htmlFor="notes" error={errors.notes}>
                            <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                defaultValue={employee?.notes ?? ''}
                                className={`${controlClassName} h-auto py-2`}
                            />
                        </Field>
                    </section>

                    <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">
                            Login account
                        </h2>
                        <p className="text-muted-foreground md:col-span-2 text-sm">
                            DSP and Supervisor employees can create or link a
                            login user. Existing accounts stay in place when
                            employment status changes.
                        </p>
                        {employee?.has_login ? (
                            <p className="md:col-span-2 text-sm">
                                Linked login: {employee.login_email} ({employee.login_role})
                            </p>
                        ) : (
                            <>
                                <div className="flex items-center gap-3 md:col-span-2">
                                    <Checkbox
                                        id="create_login"
                                        name="create_login"
                                        value="1"
                                        defaultChecked={!employee}
                                    />
                                    <label htmlFor="create_login" className="text-sm">
                                        Create a login account
                                    </label>
                                </div>
                                <Field label="Link existing user" htmlFor="user_id" error={errors.user_id}>
                                    <select id="user_id" name="user_id" defaultValue="" className={controlClassName}>
                                        <option value="">None</option>
                                        {linkableUsers.map((user) => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} ({user.email})
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Password" htmlFor="password" error={errors.password}>
                                    <Input id="password" name="password" type="password" autoComplete="new-password" />
                                </Field>
                                <Field label="Confirm password" htmlFor="password_confirmation" error={errors.password_confirmation}>
                                    <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" />
                                </Field>
                            </>
                        )}
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

import { useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Field, controlClassName } from '@/components/mdm/directory';
import { FillDemoDataButton } from '@/components/mdm/fill-demo-data-button';
import {
    EMPLOYEE_ONBOARDING_STEPS,
    OnboardingStepper,
} from '@/components/mdm/onboarding-stepper';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { demoEmployeeFill } from '@/lib/demo-form-fill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type {
    AvailabilityDayRecord,
    EducationRecord,
    EmployeeDetail,
    OptionItem,
    ReferenceRecord,
    SecurityIncidentRecord,
    WorkHistoryRecord,
} from '@/types/directory';

const WEEKDAYS = [
    { weekday: 1, label: 'Monday' },
    { weekday: 2, label: 'Tuesday' },
    { weekday: 3, label: 'Wednesday' },
    { weekday: 4, label: 'Thursday' },
    { weekday: 5, label: 'Friday' },
    { weekday: 6, label: 'Saturday' },
    { weekday: 0, label: 'Sunday' },
];

const roleTitles: Record<string, string> = {
    dsp: 'Direct Support Professional',
    supervisor: 'Supervisor',
    admin: 'Administrator',
};

function emptyEducation(level: string): EducationRecord {
    return {
        level,
        institution_name: '',
        city: '',
        state: '',
        country: 'USA',
        graduated: null,
        years_completed: null,
        degree: '',
    };
}

function emptyReference(): ReferenceRecord {
    return {
        name: '',
        address: '',
        home_phone: '',
        work_phone: '',
        relationship: '',
    };
}

function emptyWork(): WorkHistoryRecord {
    return {
        started_on: '',
        ended_on: '',
        job_title: '',
        employer: '',
        employer_phone: '',
        employer_address: '',
        reason_for_leaving: '',
        job_duties: '',
    };
}

function defaultDays(existing?: AvailabilityDayRecord[]) {
    return WEEKDAYS.map((day) => {
        const match = existing?.find((row) => row.weekday === day.weekday);

        return {
            weekday: day.weekday,
            is_available: match?.is_available ?? true,
            starts_at: match?.starts_at ?? '07:00',
            ends_at: match?.ends_at ?? '23:00',
            preferred_daypart: match?.preferred_daypart ?? '',
        };
    });
}

export function EmployeeOnboardingForm({
    action,
    method,
    employee,
    supervisors,
    linkableUsers,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put';
    employee?: EmployeeDetail;
    supervisors: OptionItem[];
    linkableUsers: OptionItem[];
    submitLabel: string;
}) {
    const creating = !employee;
    const demoEnabled = Boolean(usePage().props.demoTools?.enabled) && creating;
    const [step, setStep] = useState(1);
    const form = useForm({
        first_name: employee?.first_name ?? '',
        middle_name: employee?.middle_name ?? '',
        last_name: employee?.last_name ?? '',
        employee_number: employee?.employee_number ?? '',
        job_type: employee?.job_type && employee.job_type !== 'other' ? employee.job_type : 'dsp',
        job_title: employee?.job_title ?? 'Direct Support Professional',
        employment_status: employee?.employment_status ?? 'active',
        employment_type: employee?.employment_type ?? '',
        hired_on: employee?.hired_on ?? '',
        date_of_birth: employee?.date_of_birth ?? '',
        email: employee?.email ?? '',
        home_phone: employee?.home_phone ?? '',
        cell_phone: employee?.cell_phone ?? employee?.phone ?? '',
        alternate_phone: employee?.alternate_phone ?? '',
        supervisor_id: employee?.supervisor_id ? String(employee.supervisor_id) : '',
        profile_photo: null as File | null,
        remove_photo: false,
        address_line_1: employee?.address_line_1 ?? '',
        address_line_2: employee?.address_line_2 ?? '',
        city: employee?.city ?? '',
        state: employee?.state ?? '',
        postal_code: employee?.postal_code ?? '',
        previous_address_line_1: employee?.previous_address_line_1 ?? '',
        previous_city: employee?.previous_city ?? '',
        previous_state: employee?.previous_state ?? '',
        previous_postal_code: employee?.previous_postal_code ?? '',
        emergency_contact_name: employee?.emergency_contact_name ?? '',
        emergency_contact_relationship: employee?.emergency_contact_relationship ?? '',
        emergency_contact_phone: employee?.emergency_contact_phone ?? '',
        how_heard: employee?.how_heard ?? '',
        employment_interest: employee?.employment_interest ?? '',
        preferred_shift_type: employee?.preferred_shift_type ?? '',
        desired_hours_per_week: employee?.desired_hours_per_week ?? '',
        willing_long_term: employee?.willing_long_term ?? false,
        willing_short_term: employee?.willing_short_term ?? false,
        willing_pets: employee?.willing_pets ?? false,
        willing_smoke: employee?.willing_smoke ?? false,
        availability_days: defaultDays(employee?.availability_days),
        has_drivers_license: employee?.has_drivers_license ?? false,
        license_state: employee?.license_state ?? '',
        license_number: employee?.license_number ?? '',
        vehicle_make_year: employee?.vehicle_make_year ?? '',
        insurance_company: employee?.insurance_company ?? '',
        insurance_policy_number: employee?.insurance_policy_number ?? '',
        has_moving_violations: employee?.has_moving_violations ?? false,
        moving_violations_description: employee?.moving_violations_description ?? '',
        educations: employee?.educations?.length
            ? employee.educations
            : [emptyEducation('high_school'), emptyEducation('college')],
        credentials: [
            {
                type: 'professional_license',
                name: '',
                issuer: '',
                credential_number: '',
                expires_on: '',
                status: 'active',
            },
        ],
        license_ever_suspended: employee?.license_ever_suspended ?? false,
        license_suspension_explanation: employee?.license_suspension_explanation ?? '',
        references: employee?.references?.length
            ? employee.references
            : [emptyReference(), emptyReference(), emptyReference()],
        work_histories: employee?.work_histories?.length
            ? employee.work_histories
            : [emptyWork(), emptyWork()],
        may_contact_current_employer: employee?.may_contact_current_employer ?? false,
        ohio_resident_5_years: employee?.ohio_resident_5_years ?? false,
        residence_history: employee?.residence_history ?? '',
        used_other_names: employee?.used_other_names ?? false,
        other_names: employee?.other_names ?? '',
        ssn: '',
        alternate_ssn: '',
        has_conviction: employee?.has_conviction ?? false,
        incidents: employee?.incidents?.length
            ? employee.incidents
            : [{ incident: '', city_state: '', charge: '' } satisfies SecurityIncidentRecord],
        security_comments: employee?.security_comments ?? '',
        tb_name: 'TB Screening',
        tb_expires_on: '',
        physician_name: 'Physician Good-Health Statement',
        physician_expires_on: '',
        create_login: creating,
        user_id: '',
        password: '',
        password_confirmation: '',
        notes: employee?.notes ?? '',
        terminated_on: employee?.terminated_on ?? '',
    });

    const preview = useMemo(() => {
        if (!form.data.profile_photo) {
            return employee?.photo_url ?? null;
        }

        return URL.createObjectURL(form.data.profile_photo);
    }, [form.data.profile_photo, employee?.photo_url]);

    const setRole = (value: string) => {
        form.setData('job_type', value);
        if (!employee?.job_title || Object.values(roleTitles).includes(employee.job_title)) {
            form.setData('job_title', roleTitles[value] ?? '');
        }
    };

    const missing = useMemo(() => {
        const items: string[] = [];
        if (!form.data.first_name || !form.data.last_name) items.push('Name');
        if (!form.data.job_type) items.push('System role');
        if (!form.data.email && form.data.create_login) items.push('Email');
        if (form.data.create_login && creating && !form.data.user_id && !form.data.password) {
            items.push('Password');
        }
        if (form.data.job_type === 'dsp' && !form.data.availability_days.some((day) => day.is_available)) {
            items.push('Weekly availability');
        }

        return items;
    }, [form.data, creating]);

    const goNext = () => setStep((value) => Math.min(7, value + 1));
    const goBack = () => setStep((value) => Math.max(1, value - 1));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const credentials = [
            ...form.data.credentials.filter((row) => row.name.trim() !== ''),
            ...(form.data.tb_expires_on
                ? [{
                    type: 'tb_screening',
                    name: form.data.tb_name || 'TB Screening',
                    issuer: '',
                    credential_number: '',
                    expires_on: form.data.tb_expires_on,
                    status: 'active',
                }]
                : []),
            ...(form.data.physician_expires_on
                ? [{
                    type: 'physician_statement',
                    name: form.data.physician_name || 'Physician Good-Health Statement',
                    issuer: '',
                    credential_number: '',
                    expires_on: form.data.physician_expires_on,
                    status: 'active',
                }]
                : []),
        ];

        form.transform((data) => ({
            ...data,
            supervisor_id: data.supervisor_id || null,
            user_id: data.user_id || null,
            credentials: creating ? credentials.filter((row) => row.name) : [],
            desired_hours_per_week: data.desired_hours_per_week === '' ? null : data.desired_hours_per_week,
        }));

        if (method === 'post') {
            form.post(action, { forceFormData: true });
        } else {
            form.put(action, { forceFormData: true });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <OnboardingStepper
                    currentStep={step}
                    steps={EMPLOYEE_ONBOARDING_STEPS}
                    ariaLabel="Employee onboarding progress"
                />
                <FillDemoDataButton
                    enabled={demoEnabled}
                    onFill={() => {
                        form.setData({
                            ...form.data,
                            ...(demoEmployeeFill(supervisors) as typeof form.data),
                        });
                        setStep(1);
                    }}
                />
            </div>

            {step === 1 && (
                <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                    <h2 className="text-sm font-semibold md:col-span-2">Role & Profile</h2>
                    <p className="text-muted-foreground md:col-span-2 text-sm">
                        System Role controls login permissions. Job Title is the employment label.
                    </p>
                    <div className="md:col-span-2 flex items-center gap-4">
                        <ProfilePhoto
                            name={`${form.data.first_name} ${form.data.last_name}`.trim() || 'New employee'}
                            photoUrl={form.data.remove_photo ? null : preview}
                            size="lg"
                        />
                        <Field label="Profile photo" htmlFor="profile_photo" error={form.errors.profile_photo}>
                            <Input
                                id="profile_photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    form.setData('profile_photo', event.target.files?.[0] ?? null)
                                }
                            />
                        </Field>
                    </div>
                    <Field label="System Role" htmlFor="job_type" error={form.errors.job_type} required>
                        <select
                            id="job_type"
                            value={form.data.job_type}
                            onChange={(event) => setRole(event.target.value)}
                            className={controlClassName}
                        >
                            <option value="dsp">DSP</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </Field>
                    <Field label="Job Title" htmlFor="job_title" error={form.errors.job_title}>
                        <Input
                            id="job_title"
                            value={form.data.job_title}
                            onChange={(event) => form.setData('job_title', event.target.value)}
                        />
                    </Field>
                    <Field label="Employee number" htmlFor="employee_number" error={form.errors.employee_number}>
                        <Input
                            id="employee_number"
                            placeholder="Auto-assigned if blank"
                            value={form.data.employee_number}
                            onChange={(event) => form.setData('employee_number', event.target.value)}
                        />
                    </Field>
                    <Field label="Employment status" htmlFor="employment_status" error={form.errors.employment_status}>
                        <select
                            id="employment_status"
                            value={form.data.employment_status}
                            onChange={(event) => form.setData('employment_status', event.target.value)}
                            className={controlClassName}
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </Field>
                    <Field label="Employment type" htmlFor="employment_type" error={form.errors.employment_type}>
                        <select
                            id="employment_type"
                            value={form.data.employment_type}
                            onChange={(event) => form.setData('employment_type', event.target.value)}
                            className={controlClassName}
                        >
                            <option value="">Select</option>
                            <option value="full_time">Full-Time</option>
                            <option value="part_time">Part-Time</option>
                        </select>
                    </Field>
                    <Field label="Hire / start date" htmlFor="hired_on" error={form.errors.hired_on}>
                        <Input
                            id="hired_on"
                            type="date"
                            value={form.data.hired_on}
                            onChange={(event) => form.setData('hired_on', event.target.value)}
                        />
                    </Field>
                    <Field label="First name" htmlFor="first_name" error={form.errors.first_name} required>
                        <Input
                            id="first_name"
                            value={form.data.first_name}
                            onChange={(event) => form.setData('first_name', event.target.value)}
                            required
                        />
                    </Field>
                    <Field label="Middle name" htmlFor="middle_name" error={form.errors.middle_name}>
                        <Input
                            id="middle_name"
                            value={form.data.middle_name}
                            onChange={(event) => form.setData('middle_name', event.target.value)}
                        />
                    </Field>
                    <Field label="Last name" htmlFor="last_name" error={form.errors.last_name} required>
                        <Input
                            id="last_name"
                            value={form.data.last_name}
                            onChange={(event) => form.setData('last_name', event.target.value)}
                            required
                        />
                    </Field>
                    <Field label="Date of birth" htmlFor="date_of_birth" error={form.errors.date_of_birth}>
                        <Input
                            id="date_of_birth"
                            type="date"
                            value={form.data.date_of_birth}
                            onChange={(event) => form.setData('date_of_birth', event.target.value)}
                        />
                    </Field>
                    <Field label="Email" htmlFor="email" error={form.errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </Field>
                    <Field label="Home phone" htmlFor="home_phone" error={form.errors.home_phone}>
                        <Input
                            id="home_phone"
                            value={form.data.home_phone}
                            onChange={(event) => form.setData('home_phone', event.target.value)}
                        />
                    </Field>
                    <Field label="Cell phone" htmlFor="cell_phone" error={form.errors.cell_phone}>
                        <Input
                            id="cell_phone"
                            value={form.data.cell_phone}
                            onChange={(event) => form.setData('cell_phone', event.target.value)}
                        />
                    </Field>
                    <Field label="Alternate phone" htmlFor="alternate_phone" error={form.errors.alternate_phone}>
                        <Input
                            id="alternate_phone"
                            value={form.data.alternate_phone}
                            onChange={(event) => form.setData('alternate_phone', event.target.value)}
                        />
                    </Field>
                    <Field label="Supervisor" htmlFor="supervisor_id" error={form.errors.supervisor_id}>
                        <select
                            id="supervisor_id"
                            value={form.data.supervisor_id}
                            onChange={(event) => form.setData('supervisor_id', event.target.value)}
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
            )}

            {step === 2 && (
                <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                    <h2 className="text-sm font-semibold md:col-span-2">Current address</h2>
                    <Field label="Address line 1" htmlFor="address_line_1" error={form.errors.address_line_1}>
                        <Input id="address_line_1" value={form.data.address_line_1} onChange={(e) => form.setData('address_line_1', e.target.value)} />
                    </Field>
                    <Field label="Address line 2" htmlFor="address_line_2" error={form.errors.address_line_2}>
                        <Input id="address_line_2" value={form.data.address_line_2} onChange={(e) => form.setData('address_line_2', e.target.value)} />
                    </Field>
                    <Field label="City" htmlFor="city" error={form.errors.city}>
                        <Input id="city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                    </Field>
                    <Field label="State" htmlFor="state" error={form.errors.state}>
                        <Input id="state" value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} />
                    </Field>
                    <Field label="Postal code" htmlFor="postal_code" error={form.errors.postal_code}>
                        <Input id="postal_code" value={form.data.postal_code} onChange={(e) => form.setData('postal_code', e.target.value)} />
                    </Field>
                    <h2 className="text-sm font-semibold md:col-span-2">Previous address</h2>
                    <Field label="Street" htmlFor="previous_address_line_1" error={form.errors.previous_address_line_1}>
                        <Input id="previous_address_line_1" value={form.data.previous_address_line_1} onChange={(e) => form.setData('previous_address_line_1', e.target.value)} />
                    </Field>
                    <Field label="City" htmlFor="previous_city" error={form.errors.previous_city}>
                        <Input id="previous_city" value={form.data.previous_city} onChange={(e) => form.setData('previous_city', e.target.value)} />
                    </Field>
                    <Field label="State" htmlFor="previous_state" error={form.errors.previous_state}>
                        <Input id="previous_state" value={form.data.previous_state} onChange={(e) => form.setData('previous_state', e.target.value)} />
                    </Field>
                    <Field label="Postal code" htmlFor="previous_postal_code" error={form.errors.previous_postal_code}>
                        <Input id="previous_postal_code" value={form.data.previous_postal_code} onChange={(e) => form.setData('previous_postal_code', e.target.value)} />
                    </Field>
                    <h2 className="text-sm font-semibold md:col-span-2">Emergency contact</h2>
                    <Field label="Name" htmlFor="emergency_contact_name" error={form.errors.emergency_contact_name}>
                        <Input id="emergency_contact_name" value={form.data.emergency_contact_name} onChange={(e) => form.setData('emergency_contact_name', e.target.value)} />
                    </Field>
                    <Field label="Relationship" htmlFor="emergency_contact_relationship" error={form.errors.emergency_contact_relationship}>
                        <Input id="emergency_contact_relationship" value={form.data.emergency_contact_relationship} onChange={(e) => form.setData('emergency_contact_relationship', e.target.value)} />
                    </Field>
                    <Field label="Phone" htmlFor="emergency_contact_phone" error={form.errors.emergency_contact_phone}>
                        <Input id="emergency_contact_phone" value={form.data.emergency_contact_phone} onChange={(e) => form.setData('emergency_contact_phone', e.target.value)} />
                    </Field>
                    <Field label="How did you hear about the agency?" htmlFor="how_heard" error={form.errors.how_heard}>
                        <Input id="how_heard" value={form.data.how_heard} onChange={(e) => form.setData('how_heard', e.target.value)} />
                    </Field>
                    <Field label="Why are you interested in employment?" htmlFor="employment_interest" error={form.errors.employment_interest}>
                        <textarea id="employment_interest" rows={3} value={form.data.employment_interest} onChange={(e) => form.setData('employment_interest', e.target.value)} className={`${controlClassName} h-auto py-2`} />
                    </Field>
                </section>
            )}

            {step === 3 && (
                <section className="surface-panel grid gap-4 p-4 md:p-5">
                    <h2 className="text-sm font-semibold">Availability & transportation</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Preferred shift type" htmlFor="preferred_shift_type">
                            <Input id="preferred_shift_type" value={form.data.preferred_shift_type} onChange={(e) => form.setData('preferred_shift_type', e.target.value)} />
                        </Field>
                        <Field label="Desired hours per week" htmlFor="desired_hours_per_week">
                            <Input id="desired_hours_per_week" type="number" min={1} max={80} value={form.data.desired_hours_per_week} onChange={(e) => form.setData('desired_hours_per_week', e.target.value)} />
                        </Field>
                    </div>
                    <div className="grid gap-2 text-sm">
                        <YesNo label="Willing to accept long-term assignments" checked={form.data.willing_long_term} onChange={(value) => form.setData('willing_long_term', value)} />
                        <YesNo label="Willing to accept short-term assignments" checked={form.data.willing_short_term} onChange={(value) => form.setData('willing_short_term', value)} />
                        <YesNo label="Willing to serve clients with pets" checked={form.data.willing_pets} onChange={(value) => form.setData('willing_pets', value)} />
                        <YesNo label="Willing to serve clients who smoke" checked={form.data.willing_smoke} onChange={(value) => form.setData('willing_smoke', value)} />
                    </div>
                    {form.data.job_type === 'dsp' && (
                        <div className="space-y-3">
                            <h3 className="text-sm font-medium">Initial approved weekly availability</h3>
                            <p className="text-muted-foreground text-xs">
                                Uses the existing DSP availability model. Later DSP changes still require supervisor approval.
                            </p>
                            {form.data.availability_days.map((day, index) => {
                                const label = WEEKDAYS.find((item) => item.weekday === day.weekday)?.label ?? `Day ${day.weekday}`;

                                return (
                                    <div key={day.weekday} className="grid items-end gap-2 sm:grid-cols-4">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={day.is_available}
                                                onCheckedChange={(checked) => {
                                                    const days = [...form.data.availability_days];
                                                    days[index] = { ...day, is_available: Boolean(checked) };
                                                    form.setData('availability_days', days);
                                                }}
                                            />
                                            {label}
                                        </label>
                                        <Input
                                            type="time"
                                            disabled={!day.is_available}
                                            value={day.starts_at ?? ''}
                                            onChange={(event) => {
                                                const days = [...form.data.availability_days];
                                                days[index] = { ...day, starts_at: event.target.value };
                                                form.setData('availability_days', days);
                                            }}
                                        />
                                        <Input
                                            type="time"
                                            disabled={!day.is_available}
                                            value={day.ends_at ?? ''}
                                            onChange={(event) => {
                                                const days = [...form.data.availability_days];
                                                days[index] = { ...day, ends_at: event.target.value };
                                                form.setData('availability_days', days);
                                            }}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <YesNo label="Valid driver's license?" checked={form.data.has_drivers_license} onChange={(value) => form.setData('has_drivers_license', value)} />
                    {form.data.has_drivers_license && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Issuing state" htmlFor="license_state" error={form.errors.license_state}>
                                <Input id="license_state" value={form.data.license_state} onChange={(e) => form.setData('license_state', e.target.value)} />
                            </Field>
                            <Field label="License number" htmlFor="license_number" error={form.errors.license_number}>
                                <Input id="license_number" value={form.data.license_number} onChange={(e) => form.setData('license_number', e.target.value)} />
                            </Field>
                            <Field label="Vehicle make / year" htmlFor="vehicle_make_year">
                                <Input id="vehicle_make_year" value={form.data.vehicle_make_year} onChange={(e) => form.setData('vehicle_make_year', e.target.value)} />
                            </Field>
                            <Field label="Auto insurance company" htmlFor="insurance_company">
                                <Input id="insurance_company" value={form.data.insurance_company} onChange={(e) => form.setData('insurance_company', e.target.value)} />
                            </Field>
                            <Field label="Policy number" htmlFor="insurance_policy_number">
                                <Input id="insurance_policy_number" value={form.data.insurance_policy_number} onChange={(e) => form.setData('insurance_policy_number', e.target.value)} />
                            </Field>
                            <YesNo label="Moving violations in last 3 years?" checked={form.data.has_moving_violations} onChange={(value) => form.setData('has_moving_violations', value)} />
                            {form.data.has_moving_violations && (
                                <Field label="Description" htmlFor="moving_violations_description" error={form.errors.moving_violations_description}>
                                    <textarea id="moving_violations_description" rows={2} value={form.data.moving_violations_description} onChange={(e) => form.setData('moving_violations_description', e.target.value)} className={`${controlClassName} h-auto py-2 md:col-span-2`} />
                                </Field>
                            )}
                        </div>
                    )}
                </section>
            )}

            {step === 4 && (
                <section className="space-y-4">
                    <div className="surface-panel grid gap-4 p-4 md:p-5">
                        <h2 className="text-sm font-semibold">Education</h2>
                        {form.data.educations.map((row, index) => (
                            <div key={`${row.level}-${index}`} className="grid gap-3 border-t pt-3 md:grid-cols-2">
                                <p className="text-muted-foreground md:col-span-2 text-xs uppercase">
                                    {row.level === 'college' ? 'College' : 'High School'}
                                </p>
                                <Field label="School name">
                                    <Input value={row.institution_name ?? ''} onChange={(e) => {
                                        const rows = [...form.data.educations];
                                        rows[index] = { ...row, institution_name: e.target.value };
                                        form.setData('educations', rows);
                                    }} />
                                </Field>
                                <Field label="City / state / country">
                                    <Input value={[row.city, row.state, row.country].filter(Boolean).join(', ')} onChange={(e) => {
                                        const [city, state, country] = e.target.value.split(',').map((part) => part.trim());
                                        const rows = [...form.data.educations];
                                        rows[index] = { ...row, city: city ?? '', state: state ?? '', country: country ?? row.country };
                                        form.setData('educations', rows);
                                    }} />
                                </Field>
                                <YesNo label="Graduate?" checked={Boolean(row.graduated)} onChange={(value) => {
                                    const rows = [...form.data.educations];
                                    rows[index] = { ...row, graduated: value };
                                    form.setData('educations', rows);
                                }} />
                                <Field label="Years completed">
                                    <Input type="number" value={row.years_completed ?? ''} onChange={(e) => {
                                        const rows = [...form.data.educations];
                                        rows[index] = { ...row, years_completed: e.target.value === '' ? null : Number(e.target.value) };
                                        form.setData('educations', rows);
                                    }} />
                                </Field>
                                {row.level === 'college' && (
                                    <Field label="Degree / type">
                                        <Input value={row.degree ?? ''} onChange={(e) => {
                                            const rows = [...form.data.educations];
                                            rows[index] = { ...row, degree: e.target.value };
                                            form.setData('educations', rows);
                                        }} />
                                    </Field>
                                )}
                            </div>
                        ))}
                        <Button type="button" variant="secondary" onClick={() => form.setData('educations', [...form.data.educations, emptyEducation('college')])}>
                            Add education
                        </Button>
                    </div>
                    <div className="surface-panel grid gap-4 p-4 md:p-5">
                        <h2 className="text-sm font-semibold">Licenses / certifications</h2>
                        <p className="text-muted-foreground text-sm">Stored in the existing employee credential records.</p>
                        {form.data.credentials.map((row, index) => (
                            <div key={index} className="grid gap-3 md:grid-cols-2">
                                <Field label="Name / type">
                                    <Input value={row.name} onChange={(e) => {
                                        const rows = [...form.data.credentials];
                                        rows[index] = { ...row, name: e.target.value };
                                        form.setData('credentials', rows);
                                    }} />
                                </Field>
                                <Field label="Number">
                                    <Input value={row.credential_number} onChange={(e) => {
                                        const rows = [...form.data.credentials];
                                        rows[index] = { ...row, credential_number: e.target.value };
                                        form.setData('credentials', rows);
                                    }} />
                                </Field>
                                <Field label="Issuing state / issuer">
                                    <Input value={row.issuer} onChange={(e) => {
                                        const rows = [...form.data.credentials];
                                        rows[index] = { ...row, issuer: e.target.value };
                                        form.setData('credentials', rows);
                                    }} />
                                </Field>
                                <Field label="Expiration">
                                    <Input type="date" value={row.expires_on} onChange={(e) => {
                                        const rows = [...form.data.credentials];
                                        rows[index] = { ...row, expires_on: e.target.value };
                                        form.setData('credentials', rows);
                                    }} />
                                </Field>
                            </div>
                        ))}
                        <Button type="button" variant="secondary" onClick={() => form.setData('credentials', [...form.data.credentials, { type: 'professional_license', name: '', issuer: '', credential_number: '', expires_on: '', status: 'active' }])}>
                            Add license
                        </Button>
                        <YesNo label="Has a license or certification ever been suspended or revoked?" checked={form.data.license_ever_suspended} onChange={(value) => form.setData('license_ever_suspended', value)} />
                        {form.data.license_ever_suspended && (
                            <Field label="Explanation" error={form.errors.license_suspension_explanation}>
                                <textarea rows={2} value={form.data.license_suspension_explanation} onChange={(e) => form.setData('license_suspension_explanation', e.target.value)} className={`${controlClassName} h-auto py-2`} />
                            </Field>
                        )}
                    </div>
                </section>
            )}

            {step === 5 && (
                <section className="space-y-4">
                    <div className="surface-panel grid gap-4 p-4 md:p-5">
                        <h2 className="text-sm font-semibold">Personal references</h2>
                        <p className="text-muted-foreground text-sm">References should not be relatives.</p>
                        {form.data.references.map((row, index) => (
                            <div key={index} className="grid gap-3 border-t pt-3 md:grid-cols-2">
                                <Field label="Name"><Input value={row.name ?? ''} onChange={(e) => {
                                    const rows = [...form.data.references];
                                    rows[index] = { ...row, name: e.target.value };
                                    form.setData('references', rows);
                                }} /></Field>
                                <Field label="Relationship"><Input value={row.relationship ?? ''} onChange={(e) => {
                                    const rows = [...form.data.references];
                                    rows[index] = { ...row, relationship: e.target.value };
                                    form.setData('references', rows);
                                }} /></Field>
                                <Field label="Address"><Input value={row.address ?? ''} onChange={(e) => {
                                    const rows = [...form.data.references];
                                    rows[index] = { ...row, address: e.target.value };
                                    form.setData('references', rows);
                                }} /></Field>
                                <Field label="Home phone"><Input value={row.home_phone ?? ''} onChange={(e) => {
                                    const rows = [...form.data.references];
                                    rows[index] = { ...row, home_phone: e.target.value };
                                    form.setData('references', rows);
                                }} /></Field>
                                <Field label="Work / alternate phone"><Input value={row.work_phone ?? ''} onChange={(e) => {
                                    const rows = [...form.data.references];
                                    rows[index] = { ...row, work_phone: e.target.value };
                                    form.setData('references', rows);
                                }} /></Field>
                            </div>
                        ))}
                        <Button type="button" variant="secondary" onClick={() => form.setData('references', [...form.data.references, emptyReference()])}>Add reference</Button>
                    </div>
                    <div className="surface-panel grid gap-4 p-4 md:p-5">
                        <h2 className="text-sm font-semibold">Work experience</h2>
                        {form.data.work_histories.map((row, index) => (
                            <div key={index} className="grid gap-3 border-t pt-3 md:grid-cols-2">
                                <Field label="From"><Input type="date" value={row.started_on ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, started_on: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="To"><Input type="date" value={row.ended_on ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, ended_on: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Job title"><Input value={row.job_title ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, job_title: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Employer"><Input value={row.employer ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, employer: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Employer phone"><Input value={row.employer_phone ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, employer_phone: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Employer address"><Input value={row.employer_address ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, employer_address: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Reason for leaving"><Input value={row.reason_for_leaving ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, reason_for_leaving: e.target.value };
                                    form.setData('work_histories', rows);
                                }} /></Field>
                                <Field label="Job duties"><textarea rows={2} value={row.job_duties ?? ''} onChange={(e) => {
                                    const rows = [...form.data.work_histories];
                                    rows[index] = { ...row, job_duties: e.target.value };
                                    form.setData('work_histories', rows);
                                }} className={`${controlClassName} h-auto py-2`} /></Field>
                            </div>
                        ))}
                        <Button type="button" variant="secondary" onClick={() => form.setData('work_histories', [...form.data.work_histories, emptyWork()])}>Add employment</Button>
                        <YesNo label="May we contact current employer?" checked={form.data.may_contact_current_employer} onChange={(value) => form.setData('may_contact_current_employer', value)} />
                    </div>
                </section>
            )}

            {step === 6 && (
                <section className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                    <h2 className="text-sm font-semibold md:col-span-2">Security / background</h2>
                    <p className="text-muted-foreground md:col-span-2 text-sm">Visible only to Admin and the assigned Supervisor after hire.</p>
                    <YesNo label="Resided in Ohio for the past 5 years?" checked={form.data.ohio_resident_5_years} onChange={(value) => form.setData('ohio_resident_5_years', value)} />
                    <Field label="States / counties of residence for past 7 years" htmlFor="residence_history">
                        <textarea id="residence_history" rows={2} value={form.data.residence_history} onChange={(e) => form.setData('residence_history', e.target.value)} className={`${controlClassName} h-auto py-2`} />
                    </Field>
                    <YesNo label="Used other names?" checked={form.data.used_other_names} onChange={(value) => form.setData('used_other_names', value)} />
                    {form.data.used_other_names && (
                        <Field label="Other names" error={form.errors.other_names}>
                            <Input value={form.data.other_names} onChange={(e) => form.setData('other_names', e.target.value)} />
                        </Field>
                    )}
                    <Field label="SSN" htmlFor="ssn" hint={employee?.ssn_masked ? `On file: ${employee.ssn_masked}` : 'Stored encrypted. Never shown in full.'} error={form.errors.ssn}>
                        <Input id="ssn" autoComplete="off" value={form.data.ssn} onChange={(e) => form.setData('ssn', e.target.value)} />
                    </Field>
                    <Field label="Alternate SSN" htmlFor="alternate_ssn" error={form.errors.alternate_ssn}>
                        <Input id="alternate_ssn" autoComplete="off" value={form.data.alternate_ssn} onChange={(e) => form.setData('alternate_ssn', e.target.value)} />
                    </Field>
                    <YesNo label="Conviction / security disclosure?" checked={form.data.has_conviction} onChange={(value) => form.setData('has_conviction', value)} />
                    {form.data.has_conviction && form.data.incidents.map((row, index) => (
                        <div key={index} className="md:col-span-2 grid gap-3 md:grid-cols-3">
                            <Field label="Incident"><Input value={row.incident ?? ''} onChange={(e) => {
                                const rows = [...form.data.incidents];
                                rows[index] = { ...row, incident: e.target.value };
                                form.setData('incidents', rows);
                            }} /></Field>
                            <Field label="City / state"><Input value={row.city_state ?? ''} onChange={(e) => {
                                const rows = [...form.data.incidents];
                                rows[index] = { ...row, city_state: e.target.value };
                                form.setData('incidents', rows);
                            }} /></Field>
                            <Field label="Charge"><Input value={row.charge ?? ''} onChange={(e) => {
                                const rows = [...form.data.incidents];
                                rows[index] = { ...row, charge: e.target.value };
                                form.setData('incidents', rows);
                            }} /></Field>
                        </div>
                    ))}
                    <Field label="Comments" htmlFor="security_comments">
                        <textarea id="security_comments" rows={2} value={form.data.security_comments} onChange={(e) => form.setData('security_comments', e.target.value)} className={`${controlClassName} h-auto py-2 md:col-span-2`} />
                    </Field>
                    <h2 className="text-sm font-semibold md:col-span-2">Hiring compliance documents</h2>
                    <p className="text-muted-foreground md:col-span-2 text-sm">Tracked with existing credential records. Does not invent extra compliance rules.</p>
                    <Field label="TB test record name"><Input value={form.data.tb_name} onChange={(e) => form.setData('tb_name', e.target.value)} /></Field>
                    <Field label="TB expiration"><Input type="date" value={form.data.tb_expires_on} onChange={(e) => form.setData('tb_expires_on', e.target.value)} /></Field>
                    <Field label="Physician good-health statement"><Input value={form.data.physician_name} onChange={(e) => form.setData('physician_name', e.target.value)} /></Field>
                    <Field label="Physician statement date / expiration"><Input type="date" value={form.data.physician_expires_on} onChange={(e) => form.setData('physician_expires_on', e.target.value)} /></Field>
                </section>
            )}

            {step === 7 && (
                <section className="space-y-4">
                    <div className="surface-panel grid gap-4 p-4 md:grid-cols-2 md:p-5">
                        <h2 className="text-sm font-semibold md:col-span-2">Login account</h2>
                        {employee?.has_login ? (
                            <p className="md:col-span-2 text-sm">
                                Linked login: {employee.login_email} ({employee.login_role})
                            </p>
                        ) : (
                            <>
                                <label className="flex items-center gap-2 text-sm md:col-span-2">
                                    <Checkbox
                                        checked={form.data.create_login}
                                        onCheckedChange={(checked) => form.setData('create_login', Boolean(checked))}
                                    />
                                    Create a login account
                                </label>
                                <Field label="Link existing user" htmlFor="user_id" error={form.errors.user_id}>
                                    <select id="user_id" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} className={controlClassName}>
                                        <option value="">None</option>
                                        {linkableUsers.map((user) => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} ({user.email})
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Password" htmlFor="password" error={form.errors.password}>
                                    <Input id="password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                                </Field>
                                <Field label="Confirm password" htmlFor="password_confirmation" error={form.errors.password_confirmation}>
                                    <Input id="password_confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                                </Field>
                            </>
                        )}
                    </div>
                    <div className="surface-panel grid gap-3 p-4 text-sm md:p-5">
                        <h2 className="font-semibold">Review</h2>
                        <ReviewRow label="Employee" value={`${form.data.first_name} ${form.data.last_name}`.trim() || '—'} onJump={() => setStep(1)} />
                        <ReviewRow label="Role" value={`${form.data.job_type.toUpperCase()} · ${form.data.job_title || '—'}`} onJump={() => setStep(1)} />
                        <ReviewRow label="Contact" value={[form.data.email, form.data.cell_phone, form.data.city].filter(Boolean).join(' · ') || '—'} onJump={() => setStep(2)} />
                        <ReviewRow label="Availability" value={form.data.job_type === 'dsp' ? `${form.data.availability_days.filter((d) => d.is_available).length} days` : 'Not required for this role'} onJump={() => setStep(3)} />
                        <ReviewRow label="Driving" value={form.data.has_drivers_license ? 'License on file' : 'No license'} onJump={() => setStep(3)} />
                        <ReviewRow label="Education" value={`${form.data.educations.filter((row) => row.institution_name).length} record(s)`} onJump={() => setStep(4)} />
                        <ReviewRow label="Credentials" value={`${form.data.credentials.filter((row) => row.name).length} to add`} onJump={() => setStep(4)} />
                        <ReviewRow label="References" value={`${form.data.references.filter((row) => row.name).length}`} onJump={() => setStep(5)} />
                        <ReviewRow label="Work history" value={`${form.data.work_histories.filter((row) => row.employer || row.job_title).length}`} onJump={() => setStep(5)} />
                        <ReviewRow label="Compliance" value={[form.data.tb_expires_on && 'TB', form.data.physician_expires_on && 'Physician'].filter(Boolean).join(' · ') || 'Not yet documented'} onJump={() => setStep(6)} />
                        <ReviewRow label="Account" value={employee?.has_login ? 'Linked' : form.data.create_login ? 'Create login' : 'No login'} onJump={() => setStep(7)} />
                        {missing.length > 0 && (
                            <p className="text-destructive text-sm">
                                Missing required items: {missing.join(', ')}
                            </p>
                        )}
                    </div>
                </section>
            )}

            <div className="sticky-form-actions flex flex-wrap gap-2">
                {step > 1 && (
                    <Button type="button" variant="secondary" onClick={goBack}>
                        Back
                    </Button>
                )}
                {step < 7 && (
                    <Button type="button" onClick={goNext}>
                        Save & Continue
                    </Button>
                )}
                {step === 7 && (
                    <Button type="submit" disabled={form.processing}>
                        {submitLabel}
                    </Button>
                )}
            </div>
        </form>
    );
}

function YesNo({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <fieldset className="flex flex-wrap items-center gap-3 text-sm">
            <legend className="font-medium">{label}</legend>
            <label className="flex items-center gap-1.5">
                <input type="radio" checked={checked} onChange={() => onChange(true)} />
                Yes
            </label>
            <label className="flex items-center gap-1.5">
                <input type="radio" checked={!checked} onChange={() => onChange(false)} />
                No
            </label>
        </fieldset>
    );
}

function ReviewRow({
    label,
    value,
    onJump,
}: {
    label: string;
    value: string;
    onJump: () => void;
}) {
    return (
        <div className="flex items-start justify-between gap-3">
            <div>
                <p className="text-muted-foreground text-xs uppercase">{label}</p>
                <p>{value}</p>
            </div>
            <button type="button" className="text-primary text-xs font-medium" onClick={onJump}>
                Edit
            </button>
        </div>
    );
}

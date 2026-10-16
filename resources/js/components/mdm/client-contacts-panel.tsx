import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ClientContactRecord = {
    id: number;
    role: string;
    role_label: string;
    name: string;
    relationship: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    is_primary: boolean;
    visible_to_dsp: boolean;
};

type RoleOption = { value: string; label: string };

export function ClientContactsPanel({
    clientId,
    contacts,
    roles,
    canManage,
}: {
    clientId: number;
    contacts: ClientContactRecord[];
    roles: RoleOption[];
    canManage: boolean;
}) {
    const form = useForm({
        role: roles[0]?.value ?? 'emergency',
        name: '',
        relationship: '',
        phone: '',
        email: '',
        notes: '',
        is_primary: true,
    });

    return (
        <section className="surface-panel space-y-4 p-4 md:p-5">
            <div>
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Contacts &amp; representatives
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Emergency, guardian/authorized representative, and family
                    contacts. DSPs see emergency contacts only when service
                    access allows.
                </p>
            </div>

            {contacts.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No contacts recorded.
                </p>
            ) : (
                <ul className="space-y-3">
                    {contacts.map((contact) => (
                        <li
                            key={contact.id}
                            className="rounded-md border px-3 py-3 text-sm"
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">{contact.name}</p>
                                <p className="text-muted-foreground text-xs">
                                    {contact.role_label}
                                    {contact.is_primary ? ' · Primary' : ''}
                                </p>
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {[contact.relationship, contact.phone, contact.email]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </p>
                            {contact.notes ? (
                                <p className="mt-2 whitespace-pre-wrap text-xs">
                                    {contact.notes}
                                </p>
                            ) : null}
                            {canManage ? (
                                <div className="mt-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(
                                                `/clients/${clientId}/contacts/${contact.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {canManage ? (
                <form
                    className="grid gap-3 border-t pt-4 sm:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/clients/${clientId}/contacts`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="contact_role">Role</Label>
                        <select
                            id="contact_role"
                            className={controlClassName}
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value)}
                        >
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.role} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="contact_name">Name</Label>
                        <Input
                            id="contact_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="contact_relationship">Relationship</Label>
                        <Input
                            id="contact_relationship"
                            value={form.data.relationship}
                            onChange={(e) =>
                                form.setData('relationship', e.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="contact_phone">Phone</Label>
                        <Input
                            id="contact_phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5 sm:col-span-2">
                        <Label htmlFor="contact_email">Email</Label>
                        <Input
                            id="contact_email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Add contact
                        </Button>
                    </div>
                </form>
            ) : null}
        </section>
    );
}

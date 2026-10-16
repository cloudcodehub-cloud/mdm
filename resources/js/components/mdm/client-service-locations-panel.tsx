import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ClientServiceLocationRecord = {
    id: number;
    label: string;
    display_label: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    latitude: number | null;
    longitude: number | null;
    is_primary: boolean;
    is_active: boolean;
};

export function ClientServiceLocationsPanel({
    clientId,
    locations,
    canManage,
}: {
    clientId: number;
    locations: ClientServiceLocationRecord[];
    canManage: boolean;
}) {
    const form = useForm({
        label: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        postal_code: '',
        is_primary: false,
    });

    return (
        <section className="surface-panel space-y-4 p-4 md:p-5">
            <div>
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Service locations
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Primary location is the scheduling default. Alternate
                    approved locations may be selected per Scheduled Visit.
                </p>
            </div>

            {locations.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No service locations on file.
                </p>
            ) : (
                <ul className="space-y-3">
                    {locations.map((location) => (
                        <li
                            key={location.id}
                            className={`rounded-md border px-3 py-3 text-sm ${
                                location.is_active ? '' : 'opacity-60'
                            }`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">{location.label}</p>
                                <p className="text-muted-foreground text-xs">
                                    {location.is_primary ? 'Primary' : 'Alternate'}
                                    {location.is_active ? '' : ' · Inactive'}
                                </p>
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {location.display_label}
                            </p>
                            {canManage &&
                            location.is_active &&
                            !location.is_primary ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="mt-2"
                                    onClick={() =>
                                        router.post(
                                            `/clients/${clientId}/service-locations/${location.id}/deactivate`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Deactivate
                                </Button>
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
                        form.post(`/clients/${clientId}/service-locations`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="location_label">Label</Label>
                        <Input
                            id="location_label"
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                        />
                        <InputError message={form.errors.label} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="location_address">Address line 1</Label>
                        <Input
                            id="location_address"
                            value={form.data.address_line_1}
                            onChange={(e) =>
                                form.setData('address_line_1', e.target.value)
                            }
                        />
                        <InputError message={form.errors.address_line_1} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="location_city">City</Label>
                        <Input
                            id="location_city"
                            value={form.data.city}
                            onChange={(e) => form.setData('city', e.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="location_state">State</Label>
                        <Input
                            id="location_state"
                            value={form.data.state}
                            onChange={(e) => form.setData('state', e.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="location_postal">Postal code</Label>
                        <Input
                            id="location_postal"
                            value={form.data.postal_code}
                            onChange={(e) =>
                                form.setData('postal_code', e.target.value)
                            }
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={form.data.is_primary}
                            onChange={(e) =>
                                form.setData('is_primary', e.target.checked)
                            }
                        />
                        Set as primary / default
                    </label>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Add service location
                        </Button>
                    </div>
                </form>
            ) : null}
        </section>
    );
}

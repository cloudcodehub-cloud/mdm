import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, controlClassName } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { CareServiceRecord, TaskCatalogPayload } from '@/types/care';

export default function CareServicesIndex({
    services,
    task_catalog,
    can,
}: {
    services: CareServiceRecord[];
    task_catalog: TaskCatalogPayload;
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title="Service Catalog" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Service Catalog
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Services are programs of care. Tasks are the work DSPs
                        perform. Services do not own recurrence or visit
                        schedules.
                    </p>
                </div>

                {can.manage && (
                    <ServiceForm
                        bundles={task_catalog.bundles}
                    />
                )}

                <div className="grid gap-3 md:grid-cols-2">
                    {services.map((service) => (
                        <Panel
                            key={service.id}
                            title={service.name}
                            description={
                                service.is_active ? 'Active' : 'Inactive'
                            }
                        >
                            <p className="text-muted-foreground text-sm">
                                {service.description}
                            </p>
                            {service.recommended_bundle_names.length > 0 && (
                                <p className="mt-2 text-xs">
                                    Recommended bundles:{' '}
                                    {service.recommended_bundle_names.join(', ')}
                                </p>
                            )}
                            {can.manage && (
                                <div className="mt-4">
                                    <ServiceForm
                                        service={service}
                                        bundles={task_catalog.bundles}
                                    />
                                </div>
                            )}
                        </Panel>
                    ))}
                </div>
            </div>
        </>
    );
}

function ServiceForm({
    service,
    bundles,
}: {
    service?: CareServiceRecord;
    bundles: TaskCatalogPayload['bundles'];
}) {
    const [bundleIds, setBundleIds] = useState<number[]>(
        service?.recommended_bundle_ids ?? [],
    );
    const form = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        is_active: service?.is_active ?? true,
        note_required: service?.note_required ?? false,
        supervisor_review_expected:
            service?.supervisor_review_expected ?? false,
        payer_code: service?.payer_code ?? '',
        recommended_bundle_ids: service?.recommended_bundle_ids ?? [],
        recommended_item_ids: service?.recommended_item_ids ?? [],
    });

    const toggleBundle = (id: number) => {
        const next = bundleIds.includes(id)
            ? bundleIds.filter((value) => value !== id)
            : [...bundleIds, id];
        setBundleIds(next);
        form.setData('recommended_bundle_ids', next);
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (service) {
                    form.patch(`/care-services/${service.id}`);
                    return;
                }
                form.post('/care-services', {
                    onSuccess: () => form.reset(),
                });
            }}
            className="grid gap-3"
        >
            {!service && (
                <h2 className="text-sm font-semibold">Add service</h2>
            )}
            <Field label="Name" htmlFor={`name-${service?.id ?? 'new'}`}>
                <Input
                    id={`name-${service?.id ?? 'new'}`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    required
                />
            </Field>
            <Field
                label="Description"
                htmlFor={`description-${service?.id ?? 'new'}`}
            >
                <textarea
                    id={`description-${service?.id ?? 'new'}`}
                    value={form.data.description ?? ''}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    className={`${controlClassName} h-auto py-2`}
                    rows={2}
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(event) =>
                        form.setData('is_active', event.target.checked)
                    }
                />
                Active
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.note_required}
                    onChange={(event) =>
                        form.setData('note_required', event.target.checked)
                    }
                />
                Visit notes often expected
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.supervisor_review_expected}
                    onChange={(event) =>
                        form.setData(
                            'supervisor_review_expected',
                            event.target.checked,
                        )
                    }
                />
                Supervisor review often expected
            </label>
            <Field
                label="Payer / Sandata mapping (placeholder)"
                htmlFor={`payer-${service?.id ?? 'new'}`}
            >
                <Input
                    id={`payer-${service?.id ?? 'new'}`}
                    value={form.data.payer_code ?? ''}
                    onChange={(event) =>
                        form.setData('payer_code', event.target.value)
                    }
                    placeholder="Optional future mapping code"
                />
            </Field>
            <div>
                <p className="text-muted-foreground mb-2 text-xs">
                    Recommended task bundles
                </p>
                <div className="flex flex-wrap gap-2">
                    {bundles.map((bundle) => (
                        <button
                            key={bundle.id}
                            type="button"
                            onClick={() => toggleBundle(bundle.id)}
                            className={`rounded-full border px-3 py-1 text-xs ${
                                bundleIds.includes(bundle.id)
                                    ? 'border-primary/40 bg-primary/10'
                                    : 'border-border/80'
                            }`}
                        >
                            {bundle.name}
                        </button>
                    ))}
                </div>
            </div>
            <Button type="submit" disabled={form.processing} size="sm">
                {service ? 'Save service' : 'Add service'}
            </Button>
        </form>
    );
}

CareServicesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Service Catalog', href: '/care-services' },
    ],
};

import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { OnboardingStepper } from '@/components/mdm/onboarding-stepper';
import { TaskCatalogBuilder } from '@/components/mdm/task-catalog-builder';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as clientsIndex, show } from '@/routes/clients';
import type {
    CarePlanTaskDraft,
    CareServiceRecord,
    TaskCatalogPayload,
} from '@/types/care';
import type { ClientDetail } from '@/types/directory';

type SelectedTask = {
    id?: number;
    catalog_item_id?: number | null;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_detail: string | null;
    weekdays: number[] | null;
    interval_weeks: number | null;
    preferred_timing: string | null;
    is_required: boolean;
    note_required: boolean;
    can_skip: boolean;
    is_critical: boolean;
};

export default function ClientSetup({
    client,
    services,
    selected_service_ids,
    task_catalog,
    selected_tasks,
}: {
    client: ClientDetail;
    services: CareServiceRecord[];
    selected_service_ids: number[];
    task_catalog: TaskCatalogPayload;
    selected_tasks: SelectedTask[];
}) {
    const [step, setStep] = useState<2 | 3>(2);
    const [serviceIds, setServiceIds] = useState<number[]>(selected_service_ids);
    const [tasks, setTasks] = useState<CarePlanTaskDraft[]>(
        selected_tasks.map((task) => ({
            id: task.id,
            catalog_item_id: task.catalog_item_id ?? null,
            title: task.title,
            instructions: task.instructions,
            recurrence: task.recurrence,
            recurrence_detail: task.recurrence_detail,
            weekdays: task.weekdays,
            interval_weeks: task.interval_weeks,
            preferred_timing: task.preferred_timing,
            is_required: task.is_required,
            note_required: task.note_required,
            can_skip: task.can_skip,
            is_critical: task.is_critical,
        })),
    );
    const [saving, setSaving] = useState(false);

    const selectedServices = services.filter((service) =>
        serviceIds.includes(service.id),
    );
    const suggestedBundleIds = useMemo(
        () =>
            Array.from(
                new Set(
                    selectedServices.flatMap(
                        (service) => service.recommended_bundle_ids,
                    ),
                ),
            ),
        [selectedServices],
    );
    const relevantItemIds = useMemo(() => {
        const fromItems = selectedServices.flatMap(
            (service) => service.recommended_item_ids,
        );
        const fromBundles = task_catalog.bundles
            .filter((bundle) => suggestedBundleIds.includes(bundle.id))
            .flatMap((bundle) => bundle.item_ids);

        return Array.from(new Set([...fromItems, ...fromBundles]));
    }, [selectedServices, suggestedBundleIds, task_catalog.bundles]);

    const toggleService = (id: number) => {
        setServiceIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const finish = () => {
        setSaving(true);
        router.post(
            `/clients/${client.id}/setup`,
            {
                service_ids: serviceIds,
                tasks,
            },
            {
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={`Set up care · ${client.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={clientsIndex()}
                            className="hover:text-foreground"
                        >
                            Clients
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Set up care for {client.name}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Profile is saved. Next, choose services and the client
                        care plan.
                    </p>
                </div>

                <OnboardingStepper currentStep={step} />

                {step === 2 && (
                    <div className="space-y-4">
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">Services</h2>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Services are programs of care. They recommend
                                tasks but do not schedule visits.
                            </p>
                            <ul className="mt-3 grid gap-2 md:grid-cols-2">
                                {services.map((service) => {
                                    const selected = serviceIds.includes(
                                        service.id,
                                    );
                                    return (
                                        <li key={service.id}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleService(service.id)
                                                }
                                                className={cn(
                                                    'surface-panel h-full w-full p-3 text-left',
                                                    selected &&
                                                        'border-primary/40 bg-primary/8',
                                                )}
                                            >
                                                <p className="text-sm font-medium">
                                                    {service.name}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {service.description}
                                                </p>
                                                {service.recommended_bundle_names
                                                    .length > 0 && (
                                                    <p className="text-muted-foreground mt-2 text-xs">
                                                        Suggests:{' '}
                                                        {service.recommended_bundle_names.join(
                                                            ', ',
                                                        )}
                                                    </p>
                                                )}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">
                                Client tasks
                            </h2>
                            <p className="text-muted-foreground mt-1 mb-4 text-xs">
                                Suggested bundles are optional. Recurrence on
                                this client plan is authoritative.
                            </p>
                            <TaskCatalogBuilder
                                catalog={task_catalog}
                                selected={tasks}
                                onChange={setTasks}
                                suggestedBundleIds={suggestedBundleIds}
                                relevantItemIds={relevantItemIds}
                                filterByServices
                                servicesSelected={serviceIds.length > 0}
                            />
                        </section>
                        <div className="sticky-form-actions flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() => setStep(3)}
                            >
                                Continue to review
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                asChild
                            >
                                <Link href={show(client.id)}>
                                    Skip for now
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">Client</h2>
                            <p className="mt-2 text-sm">
                                {client.name} · {client.client_number}
                            </p>
                        </section>
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">Services</h2>
                            {selectedServices.length === 0 ? (
                                <p className="text-muted-foreground mt-2 text-sm">
                                    No services selected.
                                </p>
                            ) : (
                                <ul className="mt-2 space-y-1 text-sm">
                                    {selectedServices.map((service) => (
                                        <li key={service.id}>{service.name}</li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <section className="surface-panel p-4 md:p-5">
                            <h2 className="text-sm font-semibold">
                                Selected tasks
                            </h2>
                            {tasks.length === 0 ? (
                                <p className="text-muted-foreground mt-2 text-sm">
                                    No tasks selected yet. You can add them
                                    later from the care plan.
                                </p>
                            ) : (
                                <ul className="mt-2 space-y-1 text-sm">
                                    {tasks.map((task, index) => (
                                        <li key={`${task.title}-${index}`}>
                                            {task.title}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {task.recurrence}
                                                {task.is_required
                                                    ? ' · required'
                                                    : ''}
                                                {task.is_critical
                                                    ? ' · critical'
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <div className="sticky-form-actions flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setStep(2)}
                            >
                                Back
                            </Button>
                            <Button
                                type="button"
                                onClick={finish}
                                disabled={saving}
                            >
                                Finish
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ClientSetup.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
        { title: 'Care setup', href: show.url(0) },
    ],
};

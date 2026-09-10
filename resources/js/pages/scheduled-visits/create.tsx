import { Head, Link } from '@inertiajs/react';
import { ScheduledVisitForm } from '@/components/mdm/scheduled-visit-form';
import { dashboard } from '@/routes';
import {
    create,
    index as visitsIndex,
    store,
} from '@/routes/scheduled-visits';
import type {
    ClientScheduleOption,
    DspScheduleOption,
    OptionItem,
    ShiftTemplateOption,
} from '@/types/directory';

export default function ScheduledVisitsCreate({
    clients,
    dsps,
    supervisors,
    shiftTemplates,
}: {
    clients: ClientScheduleOption[];
    dsps: DspScheduleOption[];
    supervisors: OptionItem[];
    shiftTemplates: ShiftTemplateOption[];
}) {
    return (
        <>
            <Head title="Add Scheduled Visit" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={visitsIndex()}
                            className="hover:text-foreground"
                        >
                            Scheduled Visits
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add Scheduled Visit
                    </h1>
                </div>
                <ScheduledVisitForm
                    action={store.url()}
                    method="post"
                    clients={clients}
                    dsps={dsps}
                    supervisors={supervisors}
                    shiftTemplates={shiftTemplates}
                    submitLabel="Create scheduled visit"
                />
            </div>
        </>
    );
}

ScheduledVisitsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
        { title: 'Add Scheduled Visit', href: create() },
    ],
};

import { Head, Link } from '@inertiajs/react';
import { ScheduledVisitForm } from '@/components/mdm/scheduled-visit-form';
import { dashboard } from '@/routes';
import {
    edit,
    index as visitsIndex,
    show,
    update,
} from '@/routes/scheduled-visits';
import type {
    ClientScheduleOption,
    DspScheduleOption,
    OptionItem,
    ShiftTemplateOption,
    VisitRecord,
} from '@/types/directory';

export default function ScheduledVisitsEdit({
    visit,
    clients,
    dsps,
    supervisors,
    shiftTemplates,
    catalog_services = [],
    care_preview_url,
    availability_board_url,
    is_admin = false,
}: {
    visit: VisitRecord;
    clients: ClientScheduleOption[];
    dsps: DspScheduleOption[];
    supervisors: OptionItem[];
    shiftTemplates: ShiftTemplateOption[];
    catalog_services?: OptionItem[];
    care_preview_url?: string;
    availability_board_url?: string;
    is_admin?: boolean;
}) {
    return (
        <>
            <Head title={`Edit visit · ${visit.client_name ?? visit.client?.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={show(visit.id)}
                            className="hover:text-foreground"
                        >
                            {visit.client_name ?? visit.client?.name}
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Edit scheduled visit
                    </h1>
                </div>
                <ScheduledVisitForm
                    action={update.url(visit.id)}
                    method="put"
                    visit={visit}
                    clients={clients}
                    dsps={dsps}
                    supervisors={supervisors}
                    shiftTemplates={shiftTemplates}
                    catalogServices={catalog_services}
                    carePreviewUrl={care_preview_url}
                    availabilityBoardUrl={availability_board_url}
                    isAdmin={is_admin}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

ScheduledVisitsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
        { title: 'Edit', href: edit.url(0) },
    ],
};

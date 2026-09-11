import { Head, Link } from '@inertiajs/react';
import { ClientForm } from '@/components/mdm/client-form';
import { OnboardingStepper } from '@/components/mdm/onboarding-stepper';
import { dashboard } from '@/routes';
import { create, index as clientsIndex, store } from '@/routes/clients';
import type { OptionItem } from '@/types/directory';

export default function ClientsCreate({
    supervisors,
}: {
    supervisors: OptionItem[];
}) {
    return (
        <>
            <Head title="Add Client" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link href={clientsIndex()} className="hover:text-foreground">
                            Clients
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add Client
                    </h1>
                </div>
                <OnboardingStepper currentStep={1} />
                <ClientForm
                    action={store.url()}
                    method="post"
                    supervisors={supervisors}
                    submitLabel="Save & Continue"
                />
            </div>
        </>
    );
}

ClientsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
        { title: 'Add Client', href: create() },
    ],
};

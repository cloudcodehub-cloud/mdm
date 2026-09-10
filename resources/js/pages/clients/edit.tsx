import { Head, Link } from '@inertiajs/react';
import { ClientForm } from '@/components/mdm/client-form';
import { dashboard } from '@/routes';
import { edit, index as clientsIndex, show, update } from '@/routes/clients';
import type { ClientDetail, OptionItem } from '@/types/directory';

export default function ClientsEdit({
    client,
    supervisors,
}: {
    client: ClientDetail;
    supervisors: OptionItem[];
}) {
    return (
        <>
            <Head title={`Edit ${client.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link href={show(client.id)} className="hover:text-foreground">
                            {client.name}
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Edit client
                    </h1>
                </div>
                <ClientForm
                    action={update.url(client.id)}
                    method="put"
                    client={client}
                    supervisors={supervisors}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

ClientsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
        { title: 'Edit', href: edit.url(0) },
    ],
};

import { Head } from '@inertiajs/react';
import { Panel } from '@/components/mdm/stat-card';
import { dashboard } from '@/routes';

export default function ComingSoon({
    title,
    description,
}: {
    module: string;
    title: string;
    description: string;
}) {
    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <Panel title={title} description={description}>
                    <p className="text-muted-foreground text-sm">
                        Navigation is in place so this area can be opened from
                        the application shell. Records and workflows will be
                        added in a later phase.
                    </p>
                </Panel>
            </div>
        </>
    );
}

ComingSoon.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

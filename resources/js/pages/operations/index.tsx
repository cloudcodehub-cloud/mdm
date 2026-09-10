import { Head } from '@inertiajs/react';
import { OperationsBoardView } from '@/components/mdm/operations-board';
import { dashboard } from '@/routes';
import { index as operationsIndex } from '@/routes/operations';
import type { OperationsBoard } from '@/types/operations';

export default function OperationsIndex({
    operations,
}: {
    operations: OperationsBoard;
}) {
    return (
        <>
            <Head title="Operations" />
            <OperationsBoardView operations={operations} />
        </>
    );
}

OperationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Operations', href: operationsIndex() },
    ],
};

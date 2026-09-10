import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as operationsIndex } from '@/routes/operations';
import {
    index as exceptionsIndex,
    resolve,
    review,
    show as showException,
} from '@/routes/visit-exceptions';
import { show as showVisit } from '@/routes/visits';
import type { VisitExceptionRecord } from '@/types/operations';

export default function VisitExceptionsShow({
    exception,
    can,
}: {
    exception: VisitExceptionRecord;
    can: { review: boolean; resolve: boolean };
}) {
    const reviewForm = useForm({
        review_notes: exception.review_notes ?? '',
    });
    const resolveForm = useForm({
        resolution_notes: exception.resolution_notes ?? '',
    });

    return (
        <>
            <Head title={exception.type_label} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={exceptionsIndex()}
                            className="hover:text-foreground"
                        >
                            Exception review
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {exception.type_label}
                    </h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={exception.status}
                            label={exception.status_label}
                        />
                        {exception.created_at_label && (
                            <span className="text-muted-foreground text-sm">
                                Recorded {exception.created_at_label}
                            </span>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Exception">
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Message" value={exception.message} />
                            <Detail
                                label="Task"
                                value={exception.task_title ?? 'Visit-level'}
                            />
                            <Detail
                                label="Client"
                                value={exception.client_name ?? '—'}
                            />
                            <Detail
                                label="DSP"
                                value={exception.dsp_name ?? '—'}
                            />
                            <Detail
                                label="Service"
                                value={exception.service_type ?? '—'}
                            />
                        </dl>
                        <Button asChild variant="secondary" className="mt-4">
                            <Link href={showVisit.url(exception.visit_id)}>
                                Open visit
                            </Link>
                        </Button>
                    </Panel>
                    <Panel title="Review history">
                        {exception.status_history.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No review or resolution history yet. The
                                original exception record is unchanged.
                            </p>
                        ) : (
                            <ul className="space-y-3 text-sm">
                                {exception.status_history.map((entry, index) => (
                                    <li key={`${entry.at}-${index}`}>
                                        <p className="font-medium capitalize">
                                            {entry.status.replaceAll('_', ' ')}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {entry.user_name}
                                        </p>
                                        {entry.notes && (
                                            <p className="mt-1 whitespace-pre-wrap">
                                                {entry.notes}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {exception.review_notes && (
                            <p className="mt-3 text-sm">
                                <span className="text-muted-foreground text-xs">
                                    Review notes
                                </span>
                                <span className="block whitespace-pre-wrap">
                                    {exception.review_notes}
                                </span>
                            </p>
                        )}
                        {exception.resolution_notes && (
                            <p className="mt-3 text-sm">
                                <span className="text-muted-foreground text-xs">
                                    Resolution notes
                                </span>
                                <span className="block whitespace-pre-wrap">
                                    {exception.resolution_notes}
                                </span>
                            </p>
                        )}
                    </Panel>
                    {can.review && (
                        <Panel title="Mark reviewed">
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    reviewForm.patch(review.url(exception.id), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="review_notes">
                                        Review notes
                                    </Label>
                                    <textarea
                                        id="review_notes"
                                        rows={4}
                                        value={reviewForm.data.review_notes}
                                        onChange={(event) =>
                                            reviewForm.setData(
                                                'review_notes',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                    />
                                    <InputError
                                        message={reviewForm.errors.review_notes}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={reviewForm.processing}
                                >
                                    Mark reviewed
                                </Button>
                            </form>
                        </Panel>
                    )}
                    {can.resolve && (
                        <Panel title="Resolve">
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    resolveForm.patch(
                                        resolve.url(exception.id),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="resolution_notes">
                                        Resolution notes
                                    </Label>
                                    <textarea
                                        id="resolution_notes"
                                        rows={4}
                                        value={
                                            resolveForm.data.resolution_notes
                                        }
                                        onChange={(event) =>
                                            resolveForm.setData(
                                                'resolution_notes',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                    />
                                    <InputError
                                        message={
                                            resolveForm.errors.resolution_notes
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={resolveForm.processing}
                                >
                                    Resolve exception
                                </Button>
                            </form>
                        </Panel>
                    )}
                </div>
            </div>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 whitespace-pre-wrap">{value}</dd>
        </div>
    );
}

VisitExceptionsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Operations', href: operationsIndex() },
        { title: 'Exceptions', href: exceptionsIndex() },
        { title: 'Exception', href: showException.url(0) },
    ],
};

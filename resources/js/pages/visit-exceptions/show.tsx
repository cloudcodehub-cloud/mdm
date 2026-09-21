import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import {
    ContextGroup,
    ContextStrip,
    RecordHeader,
    RecordPage,
    RecordSection,
} from '@/components/mdm/record-detail';
import { ActivityTimeline } from '@/components/mdm/activity-timeline';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { SupervisorFollowUp } from '@/components/mdm/supervisor-follow-up';
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
    can: { review: boolean; resolve: boolean; follow_up?: boolean };
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
            <RecordPage>
                <RecordHeader
                    eyebrow={
                        <BackLink href={exceptionsIndex()}>
                            Back to Exception Review
                        </BackLink>
                    }
                    title={exception.type_label}
                    meta={
                        <>
                            <StatusBadge
                                status={exception.status}
                                label={exception.status_label}
                            />
                            <HighPriorityIndicator
                                active={Boolean(exception.is_high_priority_open)}
                                label="Unresolved high-priority exception"
                            />
                            {exception.created_at_label && (
                                <span className="text-muted-foreground text-sm">
                                    Recorded {exception.created_at_label}
                                </span>
                            )}
                        </>
                    }
                    actions={
                        <Button asChild variant="secondary">
                            <Link href={showVisit.url(exception.visit_id)}>
                                Open visit
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-12">
                    <RecordSection title="Exception" className="lg:col-span-7">
                        <ContextStrip className="border-0 bg-transparent p-0 shadow-none">
                            <ContextGroup label="Message">
                                {exception.message}
                            </ContextGroup>
                            <ContextGroup label="Task">
                                {exception.task_title ?? 'Visit-level'}
                            </ContextGroup>
                            <ContextGroup label="Client">
                                {exception.client_name ?? '—'}
                            </ContextGroup>
                            <ContextGroup label="DSP">
                                {exception.dsp_name ?? '—'}
                            </ContextGroup>
                            <ContextGroup label="Service">
                                {exception.service_type ?? '—'}
                            </ContextGroup>
                            <ContextGroup label="Priority">
                                {exception.is_high_priority ? 'High' : 'Standard'}
                            </ContextGroup>
                        </ContextStrip>
                    </RecordSection>
                    <RecordSection title="Review history" className="lg:col-span-5">
                        {exception.status_history.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No review or resolution history yet. The
                                original exception record is unchanged.
                            </p>
                        ) : (
                            <ActivityTimeline
                                items={exception.status_history.map(
                                    (entry, index) => ({
                                        id: `${entry.at}-${index}`,
                                        title: entry.status.replaceAll('_', ' '),
                                        detail: [entry.user_name, entry.notes]
                                            .filter(Boolean)
                                            .join(' · '),
                                        at_label: entry.at,
                                    }),
                                )}
                            />
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
                    </RecordSection>
                    {can.follow_up && (
                        <RecordSection
                            title="Supervisor follow-up"
                            className="lg:col-span-12"
                        >
                            <SupervisorFollowUp
                                exception={exception}
                                canFollowUp={can.follow_up}
                            />
                        </RecordSection>
                    )}
                    {can.review && (
                        <RecordSection title="Mark reviewed" className="lg:col-span-6">
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
                                        placeholder="Document what was reviewed, action taken, and any follow-up required"
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
                        </RecordSection>
                    )}
                    {can.resolve && (
                        <RecordSection title="Resolve" className="lg:col-span-6">
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
                                        placeholder="Document what was reviewed, action taken, and any follow-up required"
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
                        </RecordSection>
                    )}
                </div>
            </RecordPage>
        </>
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

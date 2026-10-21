import { Form, Head, Link } from '@inertiajs/react';
import { BackLink } from '@/components/mdm/back-link';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as ticketsIndex, store } from '@/routes/support-tickets';

export default function SupportTicketsCreate({
    categories,
    work_impacts,
}: {
    categories: Array<{ value: string; label: string }>;
    work_impacts: Array<{ value: string; label: string }>;
}) {
    return (
        <>
            <Head title="New support ticket" />
            <div className="page-shell">
                <div>
                    <BackLink href={ticketsIndex()}>Back to Support Tickets</BackLink>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        New support ticket
                    </h1>
                    <p className="text-muted-foreground text-sm break-words">
                        Submit for yourself only. Do not include passwords, OTP
                        codes, recovery codes, API keys, or tokens.
                    </p>
                </div>

                <Panel title="Ticket details">
                    <Form
                        {...store.form()}
                        encType="multipart/form-data"
                        className="grid max-w-2xl gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <label className="grid gap-1 text-sm">
                                    <span>Category</span>
                                    <select
                                        name="category"
                                        required
                                        className="border-input bg-background h-9 rounded-md border px-2"
                                    >
                                        <option value="">Select category</option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.value}
                                                value={category.value}
                                            >
                                                {category.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.category ? (
                                        <span className="text-destructive text-xs">
                                            {errors.category}
                                        </span>
                                    ) : null}
                                </label>

                                <label className="grid gap-1 text-sm">
                                    <span>Subject</span>
                                    <Input name="subject" required maxLength={160} />
                                    {errors.subject ? (
                                        <span className="text-destructive text-xs">
                                            {errors.subject}
                                        </span>
                                    ) : null}
                                </label>

                                <label className="grid gap-1 text-sm">
                                    <span>Description</span>
                                    <textarea
                                        name="description"
                                        required
                                        rows={5}
                                        className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                    />
                                    {errors.description ? (
                                        <span className="text-destructive text-xs">
                                            {errors.description}
                                        </span>
                                    ) : null}
                                </label>

                                <label className="grid gap-1 text-sm">
                                    <span>Work impact</span>
                                    <select
                                        name="work_impact"
                                        required
                                        className="border-input bg-background h-9 rounded-md border px-2"
                                    >
                                        <option value="">Select impact</option>
                                        {work_impacts.map((impact) => (
                                            <option
                                                key={impact.value}
                                                value={impact.value}
                                            >
                                                {impact.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.work_impact ? (
                                        <span className="text-destructive text-xs">
                                            {errors.work_impact}
                                        </span>
                                    ) : null}
                                </label>

                                <label className="grid gap-1 text-sm">
                                    <span>Screenshot / file (optional)</span>
                                    <Input
                                        type="file"
                                        name="attachment"
                                        className="min-h-11"
                                    />
                                    {errors.attachment ? (
                                        <span className="text-destructive text-xs">
                                            {errors.attachment}
                                        </span>
                                    ) : null}
                                </label>

                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11 w-full sm:w-auto"
                                    >
                                        Submit ticket
                                    </Button>
                                    <Button asChild variant="secondary">
                                        <Link href={ticketsIndex()}>Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

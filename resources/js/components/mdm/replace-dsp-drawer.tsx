import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { controlClassName } from '@/components/mdm/directory';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { DspScheduleOption } from '@/types/directory';

export function ReplaceDspDrawer({
    visitId,
    currentEmployeeId,
    dsps,
}: {
    visitId: number;
    currentEmployeeId?: number;
    dsps: DspScheduleOption[];
}) {
    const [open, setOpen] = useState(false);
    const [mobile, setMobile] = useState(false);

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const update = () => setMobile(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    return (
        <>
            <Button type="button" variant="outline" onClick={() => setOpen(true)}>
                Replace DSP
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side={mobile ? 'bottom' : 'right'}
                    className={mobile ? 'max-h-[85vh] sm:max-w-none' : 'sm:max-w-md'}
                >
                    <SheetHeader className="pr-8">
                        <SheetTitle>Replace DSP</SheetTitle>
                        <SheetDescription>
                            Assign a replacement for this visit window. The original
                            assignment is kept in history.
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        action={`/scheduled-visits/${visitId}/replace`}
                        method="post"
                        className="grid gap-3 px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                        <select
                            name="employee_id"
                            required
                            className={controlClassName}
                            defaultValue=""
                            aria-label="Replacement DSP"
                        >
                            <option value="">Select replacement DSP</option>
                            {dsps
                                .filter((dsp) => dsp.id !== currentEmployeeId)
                                .map((dsp) => (
                                    <option key={dsp.id} value={dsp.id}>
                                        {dsp.name}
                                    </option>
                                ))}
                        </select>
                        <input
                            name="reason"
                            placeholder="Call-off / replacement reason"
                            required
                            className={controlClassName}
                            aria-label="Reason"
                        />
                        {errors.employee_id && (
                            <p className="text-destructive text-sm">
                                {errors.employee_id}
                            </p>
                        )}
                        {errors.availability_override_required && (
                            <div className="border-warning/40 bg-warning/10 grid gap-2 rounded-md border p-3 text-sm">
                                <p className="font-medium">
                                    Availability override required
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    This DSP has an approved leave or unavailable
                                    period that conflicts with this visit. An
                                    authorized override applies to this visit only.
                                    The DSP will be notified and asked to
                                    acknowledge.
                                </p>
                                <textarea
                                    name="override_reason"
                                    required
                                    rows={3}
                                    placeholder="Availability override reason"
                                    className={`${controlClassName} h-auto py-2`}
                                    aria-label="Availability override reason"
                                />
                                {errors.override_reason && (
                                    <p className="text-destructive text-xs">
                                        {errors.override_reason}
                                    </p>
                                )}
                            </div>
                        )}
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="mark_call_off" value="1" />
                            Mark original DSP unavailable for this window
                        </label>
                        <SheetFooter className="px-0">
                            <Button type="submit" disabled={processing}>
                                {errors.availability_override_required
                                    ? 'Assign with override'
                                    : 'Assign replacement'}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                        </SheetFooter>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}

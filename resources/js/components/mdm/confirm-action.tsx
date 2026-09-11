import { type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function ConfirmAction({
    triggerLabel,
    triggerVariant = 'outline',
    title,
    description,
    confirmLabel,
    destructive = false,
    children,
}: {
    triggerLabel: string;
    triggerVariant?: 'outline' | 'destructive' | 'secondary';
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant={destructive ? 'destructive' : triggerVariant}
                onClick={() => setOpen(true)}
            >
                {triggerLabel}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <div className="[&_button]:w-full sm:[&_button]:w-auto">
                            {children}
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

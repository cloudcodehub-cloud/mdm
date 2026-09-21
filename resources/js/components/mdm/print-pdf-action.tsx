import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function PrintPdfAction({
    href,
    label = 'Print / PDF',
}: {
    href: string;
    label?: string;
}) {
    return (
        <Button asChild variant="outline">
            <a href={href}>
                <Printer aria-hidden />
                {label}
            </a>
        </Button>
    );
}

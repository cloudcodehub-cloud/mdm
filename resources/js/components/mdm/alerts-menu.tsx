import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function AlertsMenu() {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="rounded-full transition-colors duration-200"
                    aria-label="Alerts"
                >
                    <Bell className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuLabel>Alerts</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <p className="text-muted-foreground px-2 py-3 text-sm">
                    No operational alerts yet. Messaging will appear here in a
                    later phase.
                </p>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

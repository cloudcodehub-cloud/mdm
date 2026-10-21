import {
    AlertTriangle,
    CalendarClock,
    CalendarDays,
    ChartColumn,
    ClipboardCheck,
    Clock3,
    FolderOpen,
    HeartHandshake,
    LayoutDashboard,
    LifeBuoy,
    ListChecks,
    Menu,
    MessageSquare,
    ShieldCheck,
    UserCog,
    Users,
    type LucideIcon,
} from 'lucide-react';

const iconsByKey: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard,
    employees: Users,
    clients: HeartHandshake,
    'scheduled-visits': CalendarClock,
    'my-availability': Clock3,
    'my-compliance': ShieldCheck,
    'availability-requests': CalendarDays,
    operations: ListChecks,
    incidents: AlertTriangle,
    supervisors: UserCog,
    attendance: ClipboardCheck,
    compliance: ShieldCheck,
    reports: ChartColumn,
    documents: FolderOpen,
    messages: MessageSquare,
    'support-tickets': LifeBuoy,
    more: Menu,
};

export function mobileNavIcon(key: string): LucideIcon {
    return iconsByKey[key] ?? Menu;
}

import {
    CalendarClock,
    ChartColumn,
    ClipboardCheck,
    HeartHandshake,
    LayoutDashboard,
    MessageSquare,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as attendance } from '@/routes/attendance';
import { index as clients } from '@/routes/clients';
import { index as compliance } from '@/routes/compliance';
import { index as employees } from '@/routes/employees';
import { index as messages } from '@/routes/messages';
import { index as reports } from '@/routes/reports';
import { index as scheduledVisits } from '@/routes/scheduled-visits';
import { index as supervisors } from '@/routes/supervisors';
import type { AppRole } from '@/types/auth';
import type { NavItem } from '@/types';

export type RoleNavItem = NavItem & {
    roles: AppRole[];
};

export const mainNavigation: RoleNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
        roles: ['ADMIN', 'SUPERVISOR', 'DSP'],
    },
    {
        title: 'Employees',
        href: employees(),
        icon: Users,
        roles: ['ADMIN', 'SUPERVISOR'],
    },
    {
        title: 'Clients',
        href: clients(),
        icon: HeartHandshake,
        roles: ['ADMIN', 'SUPERVISOR'],
    },
    {
        title: 'Scheduled Visits',
        href: scheduledVisits(),
        icon: CalendarClock,
        roles: ['ADMIN', 'SUPERVISOR', 'DSP'],
    },
    {
        title: 'Supervisors',
        href: supervisors(),
        icon: UserCog,
        roles: ['ADMIN'],
    },
    {
        title: 'Attendance',
        href: attendance(),
        icon: ClipboardCheck,
        roles: ['ADMIN', 'SUPERVISOR', 'DSP'],
    },
    {
        title: 'Compliance',
        href: compliance(),
        icon: ShieldCheck,
        roles: ['ADMIN', 'SUPERVISOR'],
    },
    {
        title: 'Reports',
        href: reports(),
        icon: ChartColumn,
        roles: ['ADMIN', 'SUPERVISOR'],
    },
    {
        title: 'Messages',
        href: messages(),
        icon: MessageSquare,
        roles: ['ADMIN', 'SUPERVISOR', 'DSP'],
    },
];

export function navigationForRole(role: AppRole): RoleNavItem[] {
    return mainNavigation.filter((item) => item.roles.includes(role));
}

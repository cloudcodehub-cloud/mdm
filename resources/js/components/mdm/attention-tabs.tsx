import { useState } from 'react';
import { ProfileHealthBadge } from '@/components/mdm/profile-health';
import { ProfilePhoto } from '@/components/mdm/profile-photo';
import { RecordPreviewRow } from '@/components/mdm/record-preview-row';
import { EmptyState } from '@/components/mdm/stat-card';
import { cn } from '@/lib/utils';

export type AttentionProfileItem = {
    id: string;
    kind: 'employee' | 'client';
    name: string;
    percent: number;
    summary: string;
    href: string;
    status?: string;
    status_label?: string;
    tone?: string;
    photo_url?: string | null;
    initials?: string;
};

export function AttentionTabs({
    employees,
    clients,
}: {
    employees: AttentionProfileItem[];
    clients: AttentionProfileItem[];
}) {
    const defaultTab =
        employees.length === 0 && clients.length > 0 ? 'clients' : 'employees';
    const [tab, setTab] = useState(defaultTab);
    const items = tab === 'clients' ? clients : employees;

    return (
        <div>
            <div role="tablist" aria-label="Profiles needing attention" className="mb-3 flex flex-wrap gap-1 rounded-lg bg-muted/70 p-1">
                {(
                    [
                        { id: 'employees', label: `Employees (${employees.length})` },
                        { id: 'clients', label: `Clients (${clients.length})` },
                    ] as const
                ).map((item) => {
                    const selected = tab === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            id={`attention-tab-${item.id}`}
                            aria-selected={selected}
                            aria-controls={`attention-panel-${item.id}`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => setTab(item.id)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm transition-colors duration-150',
                                selected
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-background/70 hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    );
                })}
            </div>
            <div
                role="tabpanel"
                id={`attention-panel-${tab}`}
                aria-labelledby={`attention-tab-${tab}`}
            >
                <AttentionProfileList
                    items={items}
                    empty={
                        tab === 'clients'
                            ? 'No clients currently need profile attention.'
                            : 'No employees currently need profile attention.'
                    }
                />
            </div>
        </div>
    );
}

function AttentionProfileList({
    items,
    empty,
}: {
    items: AttentionProfileItem[];
    empty: string;
}) {
    if (items.length === 0) {
        return <EmptyState compact message={empty} />;
    }

    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <li key={item.id}>
                    <RecordPreviewRow href={item.href} className="flex items-center gap-3">
                        <ProfilePhoto
                            name={item.name}
                            photoUrl={item.photo_url}
                            initials={item.initials}
                            size="sm"
                        />
                        <div className="min-w-0">
                            <p className="text-sm font-medium">{item.name}</p>
                            <ProfileHealthBadge
                                percent={item.percent}
                                status={item.status}
                                statusLabel={item.status_label}
                                tone={item.tone}
                                className="mt-0.5"
                            />
                            <p className="text-muted-foreground truncate text-xs">
                                {item.summary || 'Details remaining'}
                            </p>
                        </div>
                    </RecordPreviewRow>
                </li>
            ))}
        </ul>
    );
}


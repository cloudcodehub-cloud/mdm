import {
    cloneElement,
    isValidElement,
    type ReactElement,
    type ReactNode,
} from 'react';
import { Link } from '@inertiajs/react';
import { fieldPlaceholders } from '@/lib/brand';
import { cn } from '@/lib/utils';

type ControlProps = {
    className?: string;
    placeholder?: string;
    required?: boolean;
    type?: string;
};

export const controlClassName = cn(
    'border-input file:text-foreground placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border bg-background/60 px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

function placeholderFor(label: string): string | undefined {
    return fieldPlaceholders[label.trim().toLowerCase()];
}

function enhanceControl(
    child: ReactNode,
    label: string,
    required?: boolean,
): ReactNode {
    if (!isValidElement(child)) {
        return child;
    }

    const el = child as ReactElement<ControlProps>;
    const type = el.props.type;
    const skipPlaceholder =
        type === 'date' ||
        type === 'datetime-local' ||
        type === 'checkbox' ||
        type === 'file' ||
        type === 'hidden' ||
        type === 'number' ||
        el.type === 'select';

    return cloneElement(el, {
        required: el.props.required ?? required,
        placeholder: skipPlaceholder
            ? el.props.placeholder
            : (el.props.placeholder ?? placeholderFor(label)),
    });
}

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    const tone =
        status === 'active' ||
        status === 'completed' ||
        status === 'in_progress'
            ? 'bg-status-success/10 text-status-success'
            : status === 'skipped'
              ? 'bg-status-warning/10 text-status-warning'
              : status === 'pending'
                ? 'bg-status-info/10 text-status-info'
                : status === 'terminated' ||
                    status === 'discharged' ||
                    status === 'cancelled' ||
                    status === 'revoked' ||
                    status === 'expired' ||
                    status === 'late' ||
                    status === 'exception'
                  ? 'bg-destructive/10 text-destructive'
                  : status === 'resolved'
                    ? 'bg-status-success/10 text-status-success'
                    : 'bg-status-warning/10 text-status-warning';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize',
                tone,
            )}
        >
            {label}
        </span>
    );
}

export function ModuleTabs({
    tabs,
    value,
    onChange,
}: {
    tabs: Array<{ id: string; label: string }>;
    value: string;
    onChange: (id: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1 rounded-lg bg-muted/70 p-1">
            {tabs.map((tab) => (
                <button
                    key={tab.id}
                    type="button"
                    onClick={() => onChange(tab.id)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm transition-colors duration-150',
                        value === tab.id
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-background/70 hover:text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

export function Field({
    label,
    htmlFor,
    error,
    hint,
    required,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: ReactNode;
}) {
    const childRequired =
        isValidElement(children) &&
        Boolean((children as ReactElement<ControlProps>).props.required);
    const isRequired = required ?? childRequired;

    return (
        <div className="grid gap-2">
            <label htmlFor={htmlFor} className="text-sm font-medium">
                {label}
                {isRequired ? (
                    <span className="text-destructive ml-0.5" aria-hidden="true">
                        *
                    </span>
                ) : null}
            </label>
            {enhanceControl(children, label, required)}
            {hint && !error ? (
                <p className="text-muted-foreground text-xs">{hint}</p>
            ) : null}
            {error ? (
                <p className="text-destructive text-sm">{error}</p>
            ) : null}
        </div>
    );
}

export function Pagination({
    meta,
    links,
}: {
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: { prev: string | null; next: string | null };
}) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="text-muted-foreground flex items-center justify-between gap-3 text-sm">
            <p>
                Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
            </p>
            <div className="flex gap-2">
                {links.prev ? (
                    <Link href={links.prev} className="text-foreground/80 hover:text-foreground underline-offset-4 hover:underline">
                        Previous
                    </Link>
                ) : (
                    <span className="opacity-50">Previous</span>
                )}
                {links.next ? (
                    <Link href={links.next} className="text-foreground/80 hover:text-foreground underline-offset-4 hover:underline">
                        Next
                    </Link>
                ) : (
                    <span className="opacity-50">Next</span>
                )}
            </div>
        </div>
    );
}

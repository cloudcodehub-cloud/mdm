import { ChevronDown, Search } from 'lucide-react';
import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from 'react';
import { controlClassName } from '@/components/mdm/directory';
import { cn } from '@/lib/utils';

export type TimezoneOption = {
    value: string;
    label: string;
    group?: string;
};

export function filterTimezoneOptions(
    options: TimezoneOption[],
    query: string,
): TimezoneOption[] {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return options;
    }

    return options.filter((option) => {
        const haystacks = [
            option.value.toLowerCase(),
            option.label.toLowerCase(),
            (option.group ?? '').toLowerCase(),
            option.value.replace(/[_/]/g, ' ').toLowerCase(),
        ];

        return haystacks.some((haystack) => haystack.includes(needle));
    });
}

/**
 * MDM 11-07 — friendly searchable IANA timezone selector.
 * Submits the canonical IANA identifier via a hidden input.
 */
export function TimezoneSearchSelect({
    name,
    id,
    options,
    defaultValue,
    className,
}: {
    name: string;
    id?: string;
    options: TimezoneOption[];
    defaultValue: string;
    className?: string;
}) {
    const listboxId = useId();
    const searchId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const optionRefs = useRef<Array<HTMLButtonElement | null>>([]);

    const initial =
        options.find((option) => option.value === defaultValue) ?? options[0] ?? null;

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [value, setValue] = useState(initial?.value ?? defaultValue);
    const [activeIndex, setActiveIndex] = useState(0);

    const selected = useMemo(
        () => options.find((option) => option.value === value) ?? initial,
        [initial, options, value],
    );

    const filtered = useMemo(
        () => filterTimezoneOptions(options, query),
        [options, query],
    );

    const grouped = useMemo(() => {
        const groups: Record<string, TimezoneOption[]> = {};

        for (const option of filtered) {
            const group = option.group ?? 'All timezones';
            groups[group] ??= [];
            groups[group].push(option);
        }

        return groups;
    }, [filtered]);

    const flatFiltered = filtered;

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        searchRef.current?.focus();
        const index = Math.max(
            0,
            flatFiltered.findIndex((option) => option.value === value),
        );
        setActiveIndex(index === -1 ? 0 : index);
    }, [open, flatFiltered, value]);

    useEffect(() => {
        if (!open) {
            return;
        }

        optionRefs.current[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex, open]);

    const selectOption = (option: TimezoneOption) => {
        setValue(option.value);
        setOpen(false);
        setQuery('');
    };

    const onTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            setOpen(true);
        }
    };

    const onSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setQuery('');
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((current) =>
                flatFiltered.length === 0
                    ? 0
                    : Math.min(current + 1, flatFiltered.length - 1),
            );
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((current) => Math.max(current - 1, 0));
            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            setActiveIndex(0);
            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            setActiveIndex(Math.max(flatFiltered.length - 1, 0));
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            const option = flatFiltered[activeIndex];
            if (option) {
                selectOption(option);
            }
        }
    };

    let optionIndex = -1;

    return (
        <div
            ref={rootRef}
            className={cn('relative w-full', className)}
            data-timezone-search
        >
            <input type="hidden" name={name} value={value} data-timezone-value={value} />
            <button
                type="button"
                id={id}
                className={cn(
                    controlClassName,
                    'flex h-auto min-h-9 w-full items-center justify-between gap-2 px-3 py-2 text-left',
                )}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listboxId}
                aria-label="Agency Operational Timezone"
                onClick={() => setOpen((current) => !current)}
                onKeyDown={onTriggerKeyDown}
            >
                <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">
                        {selected?.label ?? 'Select a timezone'}
                    </span>
                    <span className="text-muted-foreground block truncate text-xs">
                        {selected?.value ?? 'IANA identifier required'}
                    </span>
                </span>
                <ChevronDown className="text-muted-foreground size-4 shrink-0" />
            </button>

            {open ? (
                <div
                    className="border-border bg-popover absolute z-40 mt-1 w-full overflow-hidden rounded-md border shadow-md"
                    role="presentation"
                >
                    <div className="border-border flex items-center gap-2 border-b px-2 py-1.5">
                        <Search
                            className="text-muted-foreground size-4 shrink-0"
                            aria-hidden
                        />
                        <input
                            ref={searchRef}
                            id={searchId}
                            type="search"
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActiveIndex(0);
                            }}
                            onKeyDown={onSearchKeyDown}
                            placeholder="Search city, region, or IANA…"
                            className="placeholder:text-muted-foreground w-full bg-transparent py-1.5 text-sm outline-none"
                            aria-autocomplete="list"
                            aria-controls={listboxId}
                            aria-activedescendant={
                                flatFiltered[activeIndex]
                                    ? `${listboxId}-option-${activeIndex}`
                                    : undefined
                            }
                            autoComplete="off"
                        />
                    </div>
                    <div
                        id={listboxId}
                        role="listbox"
                        aria-label="IANA timezones"
                        className="max-h-64 overflow-y-auto py-1"
                    >
                        {flatFiltered.length === 0 ? (
                            <p className="text-muted-foreground px-3 py-3 text-sm">
                                No timezones match “{query.trim()}”.
                            </p>
                        ) : (
                            Object.entries(grouped).map(([group, groupOptions]) => (
                                <div key={group} role="group" aria-label={group}>
                                    <p className="text-muted-foreground px-3 py-1.5 text-[11px] font-medium tracking-wide uppercase">
                                        {group}
                                    </p>
                                    {groupOptions.map((option) => {
                                        optionIndex += 1;
                                        const index = optionIndex;
                                        const active = index === activeIndex;
                                        const isSelected = option.value === value;

                                        return (
                                            <button
                                                key={option.value}
                                                ref={(node) => {
                                                    optionRefs.current[index] = node;
                                                }}
                                                type="button"
                                                id={`${listboxId}-option-${index}`}
                                                role="option"
                                                aria-selected={isSelected}
                                                className={cn(
                                                    'flex w-full flex-col items-start px-3 py-2 text-left text-sm',
                                                    active && 'bg-muted',
                                                    isSelected && 'font-medium',
                                                )}
                                                onMouseEnter={() => setActiveIndex(index)}
                                                onClick={() => selectOption(option)}
                                            >
                                                <span>{option.label}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {option.value}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

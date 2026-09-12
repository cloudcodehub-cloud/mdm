export function formatClock(value: string): string {
    if (value === '') {
        return '—';
    }

    const [hourPart, minutePart = '00'] = value.split(':');
    const hour = Number(hourPart);
    const minute = Number(minutePart.slice(0, 2));

    if (Number.isNaN(hour) || Number.isNaN(minute)) {
        return value;
    }

    const suffix = hour >= 12 ? 'PM' : 'AM';
    const twelve = hour % 12 === 0 ? 12 : hour % 12;

    return `${twelve}:${String(minute).padStart(2, '0')} ${suffix}`;
}

export function formatShortDate(value: string): string {
    if (value === '') {
        return '—';
    }

    const date = new Date(`${value}T12:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

export function compactWindow(start: string, end: string): string {
    if (start === '' || end === '') {
        return '—';
    }

    const from = formatClock(start);
    const to = formatClock(end);
    const fromSuffix = from.slice(-2);
    const toSuffix = to.slice(-2);
    const fromCore = from.replace(/ (AM|PM)$/, '').replace(/:00$/, '');
    const toCore = to.replace(/ (AM|PM)$/, '').replace(/:00$/, '');

    if (fromSuffix === toSuffix) {
        return `${fromCore}–${toCore} ${toSuffix}`;
    }

    return `${from}–${to}`;
}


export function recurrenceBreakdown(
    recurrences: string[],
): Array<{ label: string; count: number }> {
    const counts = new Map<string, number>();

    for (const value of recurrences) {
        const label = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) =>
            letter.toUpperCase(),
        );
        counts.set(label, (counts.get(label) ?? 0) + 1);
    }

    return [...counts.entries()].map(([label, count]) => ({ label, count }));
}

export function focusElement(id: string): void {
    document.getElementById(id)?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}

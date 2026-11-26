import { formatDate } from '@/lib/dates';

type DatedLine = {
    completedAt: string | null;
    quantity: string;
    unit: 'hours' | 'units';
    amount: string;
};

export type InvoiceDay<T extends DatedLine> = {
    day: string | null;
    label: string | null;
    hours: number;
    amount: number;
    lines: T[];
};

export function groupLinesByDay<T extends DatedLine>(
    lines: T[],
): InvoiceDay<T>[] {
    const days = new Map<string | null, T[]>();

    for (const line of lines) {
        days.set(line.completedAt, [
            ...(days.get(line.completedAt) ?? []),
            line,
        ]);
    }

    return [...days.entries()].map(([day, dayLines]) => ({
        day,
        label:
            day === null
                ? null
                : formatDate(day, {
                      weekday: 'short',
                      month: 'short',
                      day: 'numeric',
                  }),
        hours: dayLines
            .filter((line) => line.unit === 'hours')
            .reduce((total, line) => total + Number(line.quantity), 0),
        amount: dayLines.reduce(
            (total, line) => total + Number(line.amount),
            0,
        ),
        lines: dayLines,
    }));
}

export function hasDatedLines(lines: DatedLine[]): boolean {
    return lines.some((line) => line.completedAt !== null);
}

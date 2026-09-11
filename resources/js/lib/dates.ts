const dateOnlyPattern = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Parse an ISO timestamp or a date-only string. Date-only strings are treated as local
 * calendar dates so they do not shift by a day in western time zones.
 */
export function parseDate(value: string): Date {
    return dateOnlyPattern.test(value)
        ? new Date(`${value}T00:00:00`)
        : new Date(value);
}

export function formatDate(
    value: string,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    },
): string {
    return parseDate(value).toLocaleDateString(undefined, options);
}

export function formatDateRange(start: string, end: string): string {
    const options: Intl.DateTimeFormatOptions = {
        month: 'short',
        day: 'numeric',
    };

    return `${formatDate(start, options)} – ${formatDate(end, options)}`;
}

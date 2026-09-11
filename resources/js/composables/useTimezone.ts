/**
 * The browser's IANA timezone, sent along with login and registration so the server
 * can remember it on the user.
 */
export function useTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

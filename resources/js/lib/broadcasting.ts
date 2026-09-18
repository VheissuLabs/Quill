export const broadcastingEnabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY);

/** Mirrors OrganizationBroadcast::channelFor on the server. */
export const organizationChannel = (organizationId: number): string =>
    `organizations.${organizationId}`;

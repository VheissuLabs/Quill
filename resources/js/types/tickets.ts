import type { IssueStatus } from './issues';
import type { SelectOption } from './projects';

export type TicketStatus = 'open' | 'awaiting_customer' | 'resolved' | 'closed';

export type TicketListItem = {
    id: number;
    reference: string;
    subject: string;
    status: TicketStatus;
    statusLabel: string;
    requesterName: string | null;
    requesterEmail: string;
    client: { id: number; name: string; slug: string } | null;
    issue: {
        id: number;
        reference: string;
        title: string;
        status: IssueStatus;
        statusLabel: string;
    } | null;
    reporterName: string | null;
    readyToReply: boolean;
    lastMessageAt: string | null;
    createdAt: string;
};

export type TicketDetail = TicketListItem & {
    requesterIsUser: boolean;
    createdByName: string | null;
    suggestedDomain: string | null;
    canConvert: boolean;
};

export type TicketFilters = {
    status: TicketStatus | null;
    client: string | null;
    view: 'unassigned' | 'ready' | 'mine' | null;
};

export type TicketClientOption = {
    id: number;
    name: string;
    slug?: string;
};

export type TicketStatusOption = SelectOption & { value: TicketStatus };

export type LinkedTicket = {
    id: number;
    reference: string;
    subject: string;
    statusLabel: string;
    requester: string;
    clientName: string | null;
    readyToReply: boolean;
};

export type CommentEmail = {
    direction: 'inbound' | 'outbound';
    deliveryStatus: 'received' | 'queued' | 'sent' | 'failed';
    from: string;
    to: string[];
    attachments: { id: number; filename: string; size: number }[];
};

export type ClientContactOption = {
    id: number;
    name: string | null;
    email: string;
    client: { id: number; name: string } | null;
};

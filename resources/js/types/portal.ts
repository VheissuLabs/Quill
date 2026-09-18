import type { LinkedIssue } from './issues';
import type { ProjectVisibility } from './projects';

export type FeedbackStatus =
    | 'open'
    | 'planned'
    | 'in_progress'
    | 'completed'
    | 'declined';

export type PortalOrganization = {
    name: string;
    slug: string;
    logoUrl: string | null;
};

export type PortalProject = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    visibility?: ProjectVisibility;
};

export type VoteDirection = 'up' | 'down';

export type FeedbackSummary = {
    id: number;
    title: string;
    description: string | null;
    status: FeedbackStatus;
    statusLabel: string;
    acceptsVotes: boolean;
    votesCount: number;
    downvotesCount: number;
    score: number;
    commentsCount: number;
    vote: VoteDirection | null;
    authorName: string | null;
    createdAt: string;
    issue: LinkedIssue | null;
};

export type CommentCommand = {
    name: string;
    usage: string;
    description: string;
};

export type CommentMention = {
    id: number;
    name: string;
};

export type FeedbackComment = {
    id: number;
    body: string;
    authorName: string | null;
    createdAt: string;
    isInternal: boolean;
    canDelete: boolean;
    mentions: CommentMention[];
    replies: FeedbackComment[];
};

export type BoardStatusOption = {
    value: FeedbackStatus;
    label: string;
    count: number;
};

export type BoardFilters = {
    status: FeedbackStatus | null;
    sort: 'top' | 'new';
    search: string | null;
};

export type BoardViewer = {
    isMember: boolean;
    canCreateFeedback: boolean;
    canManageFeedback: boolean;
};

export type FeedbackViewer = {
    isMember: boolean;
    canUpdate: boolean;
    canUpdateStatus: boolean;
    canDelete: boolean;
    canVote: boolean;
    canComment: boolean;
    canCommentInternally: boolean;
    canConvert: boolean;
    canViewActivity: boolean;
};

export type ActivityChange = {
    field: string;
    from: string | null;
    to: string | null;
};

export type ActivityEntry = {
    id: number;
    event: string;
    causerName: string | null;
    createdAt: string;
    changes: ActivityChange[];
};

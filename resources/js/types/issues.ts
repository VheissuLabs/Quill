import type { ActivityEntry, FeedbackStatus } from './portal';
import type { SelectOption } from './projects';

export type IssueStatus =
    | 'backlog'
    | 'todo'
    | 'in_progress'
    | 'done'
    | 'cancelled';

export type BoardColumn = {
    id: number;
    name: string;
    status: IssueStatus;
};

export type TeamBoardColumn = BoardColumn & {
    issuesCount: number;
};

export type IssuePriority = 'none' | 'low' | 'medium' | 'high' | 'urgent';

export type SprintStatus = 'planned' | 'active' | 'completed';

export type IssueListItem = {
    id: number;
    number: number;
    reference: string;
    title: string;
    status: IssueStatus;
    statusLabel: string;
    priority: IssuePriority;
    priorityLabel: string;
    points: number | null;
    boardColumnId: number | null;
    project: { name: string; slug: string };
    teamId: number | null;
    teamName: string | null;
    assigneeId: number | null;
    assigneeName: string | null;
    hasFeedback: boolean;
    createdAt: string;
};

export type IssueLinkPerspective = {
    value: 'parent' | 'blocks' | 'relates' | 'duplicates' | 'clones';
    inverse: boolean;
    label: string;
};

export type IssueLink = {
    id: number;
    type: 'parent' | 'blocks' | 'relates' | 'duplicates' | 'clones';
    label: string;
    inverse: boolean;
    issue: {
        id: number;
        reference: string;
        title: string;
        status: IssueStatus;
        statusLabel: string;
    };
};

export type IssueActivityEntry = ActivityEntry & {
    issue: Pick<IssueListItem, 'id' | 'reference' | 'title'>;
};

export type IssueDetail = IssueListItem & {
    description: string | null;
    teamId: number | null;
    sprintId: number | null;
    columns: BoardColumn[];
    assigneeId: number | null;
    creatorName: string | null;
    completedAt: string | null;
};

export type IssueFilters = {
    status: IssueStatus | null;
    project: string | null;
    assignee: string | null;
    search: string | null;
};

export type IssueProjectOption = { id: number; name: string; slug: string };
export type IssueTeamOption = {
    id: number;
    name: string;
    slug: string;
    columns: BoardColumn[];
};
export type IssueMemberOption = { id: number; name: string };

export type IssueSprintOption = {
    id: number;
    name: string;
    teamId: number;
    teamName: string;
    status: SprintStatus;
    statusLabel: string;
    startsAt: string;
    endsAt: string;
    issuesCount: number;
    points: number;
    issues: IssueListItem[];
};

export type IssueFormOptions = {
    projects: IssueProjectOption[];
    teams: IssueTeamOption[];
    sprints: IssueSprintOption[];
    sprintsEnabled: boolean;
    boardColumns: BoardColumn[];
    members: IssueMemberOption[];
    statuses: SelectOption[];
    priorities: SelectOption[];
};

export type IssueOriginFeedback = {
    id: number;
    title: string;
    status: FeedbackStatus;
    statusLabel: string;
    votesCount: number;
    downvotesCount: number;
    authorName: string | null;
    createdAt: string;
};

export type SprintListItem = {
    id: number;
    name: string;
    status: SprintStatus;
    statusLabel: string;
    startsAt: string;
    endsAt: string;
    owner: { name: string; slug: string; isTeam: boolean };
    issuesCount: number;
    doneIssuesCount: number;
    points: number;
};

export type SprintIssue = IssueListItem;

export type LinkedIssue = {
    id: number;
    reference: string;
    status: IssueStatus;
    statusLabel: string;
};

import type { ActivityEntry, FeatureStatus } from './portal';
import type { SelectOption } from './projects';

export type IssueStatus =
    'backlog' | 'todo' | 'in_progress' | 'done' | 'cancelled';

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
    project: { name: string; slug: string };
    teamName: string | null;
    sprintName: string | null;
    assigneeName: string | null;
    hasFeature: boolean;
    createdAt: string;
};

export type IssueActivityEntry = ActivityEntry & {
    issue: Pick<IssueListItem, 'id' | 'reference' | 'title'>;
};

export type IssueDetail = IssueListItem & {
    description: string | null;
    teamId: number | null;
    sprintId: number | null;
    boardColumnId: number | null;
    columns: BoardColumn[];
    assigneeId: number | null;
    creatorName: string | null;
    completedAt: string | null;
};

export type IssueFilters = {
    status: IssueStatus | null;
    project: string | null;
    assignee: string | null;
};

export type IssueProjectOption = { id: number; name: string; slug: string };
export type IssueTeamOption = { id: number; name: string; slug: string };
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
    members: IssueMemberOption[];
    statuses: SelectOption[];
    priorities: SelectOption[];
};

export type IssueOriginFeature = {
    id: number;
    title: string;
    status: FeatureStatus;
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
    team: { id: number; name: string; slug: string };
    issuesCount: number;
    doneIssuesCount: number;
    points: number;
};

export type SprintIssue = {
    id: number;
    number: number;
    reference: string;
    title: string;
    status: IssueStatus;
    boardColumnId: number | null;
    priority: IssuePriority;
    priorityLabel: string;
    points: number | null;
    projectName: string;
    assigneeName: string | null;
    hasFeature: boolean;
};

export type LinkedIssue = {
    id: number;
    reference: string;
    status: IssueStatus;
    statusLabel: string;
};

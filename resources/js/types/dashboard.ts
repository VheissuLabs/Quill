import type { FeedbackStatus, FeedbackType } from './portal';
import type { ProjectListItem } from './projects';

export type DashboardProject = ProjectListItem & {
    openFeedbackCount: number;
};

export type DashboardStats = {
    projectsCount: number;
    openFeedbackCount: number;
    votesCount: number;
};

export type RecentFeedback = {
    id: number;
    title: string;
    type: FeedbackType;
    typeLabel: string;
    status: FeedbackStatus;
    statusLabel: string;
    votesCount: number;
    createdAt: string;
    project: {
        name: string;
        slug: string;
    };
};

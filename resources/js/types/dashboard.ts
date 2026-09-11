import type { FeatureStatus } from './portal';
import type { ProjectListItem } from './projects';

export type DashboardProject = ProjectListItem & {
    openFeaturesCount: number;
};

export type DashboardStats = {
    projectsCount: number;
    openFeaturesCount: number;
    votesCount: number;
};

export type RecentFeature = {
    id: number;
    title: string;
    status: FeatureStatus;
    statusLabel: string;
    votesCount: number;
    createdAt: string;
    project: {
        name: string;
        slug: string;
    };
};

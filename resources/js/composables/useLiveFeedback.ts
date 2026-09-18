import { useEchoPublic } from '@laravel/echo-vue';
import { broadcastingEnabled, projectChannel } from '@/lib/broadcasting';

type FeedbackPayload = { feedback: number };

type Handlers = {
    voted?: () => void;
    commented?: () => void;
};

export function useLiveFeedback(
    projectId: number,
    handlers: Handlers,
    feedbackId?: number,
): void {
    if (import.meta.env.SSR || !broadcastingEnabled) {
        return;
    }

    const concerns = (payload: FeedbackPayload) =>
        feedbackId === undefined || payload.feedback === feedbackId;

    useEchoPublic(
        projectChannel(projectId),
        '.feedback.voted',
        (payload: FeedbackPayload) => {
            if (concerns(payload)) {
                handlers.voted?.();
            }
        },
    );

    useEchoPublic(
        projectChannel(projectId),
        '.feedback.commented',
        (payload: FeedbackPayload) => {
            if (concerns(payload)) {
                handlers.commented?.();
            }
        },
    );
}

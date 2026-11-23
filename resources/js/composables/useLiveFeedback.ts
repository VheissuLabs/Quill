import { useEchoPublic } from '@laravel/echo-vue';
import { broadcastingEnabled, projectChannel } from '@/lib/broadcasting';

type FeedbackPayload = { feedback: number };

export function useLiveFeedback(
    projectId: number,
    feedbackId: number,
    commented: () => void,
): void {
    if (import.meta.env.SSR || !broadcastingEnabled) {
        return;
    }

    useEchoPublic(
        projectChannel(projectId),
        '.feedback.commented',
        (payload: FeedbackPayload) => {
            if (payload.feedback === feedbackId) {
                commented();
            }
        },
    );
}

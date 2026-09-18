import { router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { broadcastingEnabled, projectChannel } from '@/lib/broadcasting';

type FeedbackPayload = { feedback: number };

/**
 * A feedback board is readable without an account, so the channel is public and carries only a
 * feedback id. The props are refetched rather than patched, which keeps the counts, the sort
 * order and the viewer's own vote in the server's hands.
 *
 * Nobody asked for the refetch, so it carries no progress bar. A bar across the top is a reply
 * to something you did; one that appears on its own reads as the page acting on you.
 *
 * A page showing one feedback passes its id and ignores what happens to the rest of the board.
 */
export function useLiveFeedback(
    projectId: number,
    only: string[],
    feedbackId?: number,
): void {
    if (import.meta.env.SSR || !broadcastingEnabled) {
        return;
    }

    const refresh = useDebounceFn(
        () => router.reload({ only, showProgress: false }),
        300,
    );

    useEchoPublic(
        projectChannel(projectId),
        ['.feedback.voted', '.feedback.commented'],
        (payload: FeedbackPayload) => {
            if (feedbackId !== undefined && payload.feedback !== feedbackId) {
                return;
            }

            void refresh();
        },
    );
}

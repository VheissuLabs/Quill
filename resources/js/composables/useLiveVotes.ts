import { router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { broadcastingEnabled, projectChannel } from '@/lib/broadcasting';

type VotePayload = { feature: number };

/**
 * A feature board is readable without an account, so the channel is public and carries only a
 * feature id. The props are refetched rather than patched, which keeps the counts, the sort
 * order and the viewer's own vote in the server's hands.
 *
 * A page showing one feature passes its id and ignores votes cast on the rest of the board.
 */
export function useLiveVotes(
    projectId: number,
    only: string[],
    featureId?: number,
): void {
    if (import.meta.env.SSR || !broadcastingEnabled) {
        return;
    }

    const refresh = useDebounceFn(() => router.reload({ only }), 300);

    useEchoPublic(
        projectChannel(projectId),
        '.feature.voted',
        (payload: VotePayload) => {
            if (featureId !== undefined && payload.feature !== featureId) {
                return;
            }

            void refresh();
        },
    );
}

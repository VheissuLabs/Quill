import { echo } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { broadcastingEnabled } from '@/lib/broadcasting';

type Typist = { name: string; at: number };
type Whisper = { id: number; name: string };

const FORGET_AFTER = 3000;
const WHISPER_EVERY = 1000;

/**
 * Who is looking at this page, and who is writing something. The typing signal is whispered
 * straight between browsers, so it never reaches the server and nothing is stored.
 *
 * A hidden tab leaves the channel: someone with the page buried behind twelve others is not
 * watching it, and the server reads the same roster when deciding whether to notify them.
 */
export function useCommentPresence(
    channelName: string,
    me: { id: number; name: string } | null,
) {
    const typists = ref<Record<number, Typist>>({});

    const active = !import.meta.env.SSR && broadcastingEnabled && me !== null;

    let joined = false;
    let lastWhisper = 0;
    let prune: ReturnType<typeof setInterval> | undefined;

    const join = () => {
        if (joined || !active) {
            return;
        }

        joined = true;

        echo()
            .join(channelName)
            .listenForWhisper('typing', (payload: Whisper) => {
                if (payload.id === me?.id) {
                    return;
                }

                typists.value = {
                    ...typists.value,
                    [payload.id]: { name: payload.name, at: Date.now() },
                };
            });
    };

    const leave = () => {
        if (!joined) {
            return;
        }

        joined = false;
        typists.value = {};
        echo().leave(channelName);
    };

    const reportTyping = () => {
        if (!active || !joined) {
            return;
        }

        const now = Date.now();

        if (now - lastWhisper < WHISPER_EVERY) {
            return;
        }

        lastWhisper = now;
        echo()
            .join(channelName)
            .whisper('typing', { id: me?.id, name: me?.name });
    };

    const onVisibilityChange = () => {
        if (document.hidden) {
            leave();
        } else {
            join();
        }
    };

    onMounted(() => {
        if (!active) {
            return;
        }

        join();
        document.addEventListener('visibilitychange', onVisibilityChange);

        prune = setInterval(() => {
            const cutoff = Date.now() - FORGET_AFTER;

            typists.value = Object.fromEntries(
                Object.entries(typists.value).filter(
                    ([, typist]) => typist.at > cutoff,
                ),
            );
        }, 1000);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        clearInterval(prune);
        leave();
    });

    const names = computed(() =>
        Object.values(typists.value).map((typist) => typist.name),
    );

    const typingLabel = computed(() => {
        const [first, second] = names.value;

        if (names.value.length === 0) {
            return null;
        }

        if (names.value.length === 1) {
            return `${first} is commenting…`;
        }

        if (names.value.length === 2) {
            return `${first} and ${second} are commenting…`;
        }

        return `${first} and ${names.value.length - 1} others are commenting…`;
    });

    return { typingLabel, reportTyping };
}

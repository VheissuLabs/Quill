import { ref } from 'vue';

const endedAt = ref(0);

/**
 * Rows are draggable and their title is a link, so a drag that starts on the title would
 * navigate when released. Two signals mark a drag, because Sortable runs with forceFallback
 * and captures the pointer: the board reports when a drag finished, and the pointer is
 * watched on the document, where nothing can intercept it.
 */
const GRACE_MS = 250;
const TRAVEL_PX = 5;

let pressedAt: { x: number; y: number } | null = null;
let watching = false;

const watchPointer = () => {
    if (watching || typeof document === 'undefined') {
        return;
    }

    watching = true;

    document.addEventListener(
        'pointerdown',
        (event) => {
            pressedAt = { x: event.clientX, y: event.clientY };
        },
        true,
    );

    document.addEventListener(
        'pointerup',
        (event) => {
            if (!pressedAt) {
                return;
            }

            const travelled = Math.hypot(
                event.clientX - pressedAt.x,
                event.clientY - pressedAt.y,
            );

            pressedAt = null;

            if (travelled > TRAVEL_PX) {
                endedAt.value = performance.now();
            }
        },
        true,
    );
};

export function useRowDrag() {
    watchPointer();

    const markDragEnded = () => {
        endedAt.value = performance.now();
    };

    const draggedJustNow = () => performance.now() - endedAt.value < GRACE_MS;

    return { markDragEnded, draggedJustNow };
}

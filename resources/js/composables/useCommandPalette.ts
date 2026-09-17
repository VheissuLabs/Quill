import { ref } from 'vue';

const open = ref(false);

export function useCommandPalette() {
    const show = () => {
        open.value = true;
    };

    const hide = () => {
        open.value = false;
    };

    const toggle = () => {
        open.value = !open.value;
    };

    return { open, show, hide, toggle };
}

import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Permissions are rows, so the client is handed the granted names rather than a
 * struct of booleans that has to grow every time one is added.
 */
export function usePermissions() {
    const page = usePage()

    const granted = computed<string[]>(() => page.props.permissions ?? [])

    const can = (permission: string): boolean =>
        granted.value.includes(permission)

    return { granted, can }
}

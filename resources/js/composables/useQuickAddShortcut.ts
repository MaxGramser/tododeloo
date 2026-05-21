import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Global keyboard shortcuts to open the quick-add modal:
 * - ⌘K / Ctrl+K from anywhere
 * - "n" when no text field is focused
 */
export function useQuickAddShortcut() {
    const open = ref(false);

    function handler(e: KeyboardEvent) {
        const cmd = e.metaKey || e.ctrlKey;
        if (cmd && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            open.value = true;
            return;
        }
        if (e.key.toLowerCase() === 'n' && !cmd && !isTyping(e)) {
            e.preventDefault();
            open.value = true;
        }
    }

    onMounted(() => window.addEventListener('keydown', handler));
    onBeforeUnmount(() => window.removeEventListener('keydown', handler));

    return { open };
}

function isTyping(e: KeyboardEvent): boolean {
    const target = e.target as HTMLElement | null;
    if (!target) return false;
    const tag = target.tagName;
    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        target.isContentEditable
    );
}

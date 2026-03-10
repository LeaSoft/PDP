import { onBeforeUnmount, watch, type Ref } from 'vue';

type ModalEntry = {
    id: number;
    close: () => void;
    opener: HTMLElement | null;
};

const modalStack: ModalEntry[] = [];
let nextModalId = 1;
let hasKeydownListener = false;

function ensureKeydownListener() {
    if (typeof window === 'undefined' || hasKeydownListener) return;
    window.addEventListener('keydown', onWindowKeydown, true);
    hasKeydownListener = true;
}

function cleanupKeydownListenerIfNeeded() {
    if (typeof window === 'undefined') return;
    if (!hasKeydownListener || modalStack.length > 0) return;
    window.removeEventListener('keydown', onWindowKeydown, true);
    hasKeydownListener = false;
}

function onWindowKeydown(event: KeyboardEvent) {
    if (event.key !== 'Escape') return;
    const top = modalStack.at(-1);
    if (!top) return;

    event.preventDefault();
    event.stopPropagation();
    top.close();
}

function getCurrentFocusedElement(): HTMLElement | null {
    if (typeof document === 'undefined') return null;
    const active = document.activeElement;
    if (!(active instanceof HTMLElement)) return null;
    if (active === document.body) return null;
    return active;
}

function restoreFocus(el: HTMLElement | null) {
    if (!el || typeof document === 'undefined') return;
    if (!document.contains(el)) return;

    requestAnimationFrame(() => {
        if (!document.contains(el)) return;
        try {
            el.focus({ preventScroll: true });
        } catch {
            el.focus();
        }
    });
}

function registerModal(close: () => void): number {
    const id = nextModalId++;
    modalStack.push({
        id,
        close,
        opener: getCurrentFocusedElement(),
    });
    ensureKeydownListener();
    return id;
}

function unregisterModal(id: number, restoreOpenerFocus: boolean) {
    const index = modalStack.findIndex((entry) => entry.id === id);
    if (index === -1) return;

    const wasTop = index === modalStack.length - 1;
    const [removed] = modalStack.splice(index, 1);
    cleanupKeydownListenerIfNeeded();

    if (restoreOpenerFocus && wasTop) {
        restoreFocus(removed.opener);
    }
}

export function useEscapableModal(isOpen: Ref<boolean>, close: () => void) {
    let modalId: number | null = null;

    watch(
        isOpen,
        (open) => {
            if (open) {
                if (modalId === null) {
                    modalId = registerModal(close);
                }
                return;
            }

            if (modalId !== null) {
                unregisterModal(modalId, true);
                modalId = null;
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        if (modalId !== null) {
            unregisterModal(modalId, false);
            modalId = null;
        }
    });
}

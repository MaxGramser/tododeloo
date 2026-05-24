import { ref, watch, type Ref } from 'vue';

export interface ParseSegment {
    type: 'date' | 'recurrence' | 'title' | 'ignored';
    text: string;
    resolved?: string;
}

export interface ParsePreview {
    input: string;
    title: string;
    date: { iso: string; label: string } | null;
    recurrence: {
        rrule: string;
        summary: string;
        anchor_iso: string;
        anchor_label: string;
    } | null;
    segments: ParseSegment[];
}

/**
 * Live "how will this parse" preview for the quick-add field. Debounces and
 * fetches the platform-agnostic segment annotation from the backend, so the
 * same intelligence drives web, iOS and Mac. Read-only GET — no CSRF needed.
 */
export function useParsePreview(
    title: Ref<string>,
    parseEnabled?: Ref<boolean>,
    delay = 180,
) {
    const preview = ref<ParsePreview | null>(null);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let seq = 0;

    function run(value: string) {
        clearTimeout(timer);
        const trimmed = value.trim();
        if (!trimmed) {
            preview.value = null;
            return;
        }
        timer = setTimeout(async () => {
            const mySeq = ++seq;
            // Raw mode (parse off) → the backend returns one plain title segment.
            const raw = parseEnabled && !parseEnabled.value ? '&parse=0' : '';
            try {
                const res = await fetch(
                    `/quick-add/preview?title=${encodeURIComponent(trimmed)}${raw}`,
                    { headers: { Accept: 'application/json' } },
                );
                if (!res.ok) {
                    return;
                }
                const data = (await res.json()) as ParsePreview;
                // Drop out-of-order responses.
                if (mySeq === seq) {
                    preview.value = data;
                }
            } catch {
                // Network hiccup — keep the last good preview.
            }
        }, delay);
    }

    watch(title, run, { immediate: true });
    if (parseEnabled) {
        watch(parseEnabled, () => run(title.value));
    }

    return { preview };
}

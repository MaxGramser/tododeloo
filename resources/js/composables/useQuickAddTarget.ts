import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type CurrentList = {
    id: number;
    type: 'master' | 'daily' | 'custom';
    name: string | null;
    date: string | null;
};

/**
 * Where a quick-add (without an explicit date in the text) lands. Follows the
 * page you're on: today → today, Alles → master, a custom list → that list.
 * On pages with no list it falls back to the workday rule in
 * app/Support/Workday.php (today on weekdays, next Monday in the weekend).
 */
export function useQuickAddTarget() {
    const page = usePage<{ list?: CurrentList | null; today?: string }>();

    const target = computed<{ label: string; listId: number | null }>(() => {
        const list = page.props.list;
        const todayISO = page.props.today;

        if (list) {
            if (list.type === 'master') {
                return { label: 'Alles', listId: list.id };
            }
            if (list.type === 'custom') {
                return { label: list.name ?? 'Lijst', listId: list.id };
            }
            if (list.type === 'daily') {
                const label =
                    todayISO && list.date === todayISO
                        ? 'Vandaag'
                        : (list.date ?? 'Dag');
                return { label, listId: list.id };
            }
        }

        return { ...workdayTarget(), listId: null };
    });

    return { target };
}

function workdayTarget(): { label: string } {
    const now = new Date();
    const day = now.getDay();
    if (day >= 1 && day <= 5) {
        return { label: 'Vandaag' };
    }
    return { label: 'Maandag' };
}

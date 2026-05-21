import { computed } from 'vue';

/**
 * Mirrors the server-side rule in app/Support/Workday.php:
 * - workday (Mon-Fri): target = today
 * - weekend (Sat/Sun): target = next Monday
 */
export function useQuickAddTarget() {
    const target = computed(() => {
        const now = new Date();
        const day = now.getDay();
        if (day >= 1 && day <= 5) {
            return { label: 'Vandaag', date: format(now) };
        }
        const daysUntilMonday = day === 6 ? 2 : 1;
        const monday = new Date(now);
        monday.setDate(now.getDate() + daysUntilMonday);
        return { label: 'Maandag', date: format(monday) };
    });

    return { target };
}

function format(d: Date): string {
    return d.toISOString().slice(0, 10);
}

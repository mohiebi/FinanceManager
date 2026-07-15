const numberFormatter = new Intl.NumberFormat('en-US');
const dateFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

export function formatAdminNumber(value: number): string {
    return numberFormatter.format(value);
}

export function formatAdminPercentage(value: number): string {
    return `${value.toFixed(1)}%`;
}

export function formatAdminDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

export function relativeActivity(value: string | null): string {
    if (!value) {
        return 'Never seen';
    }

    const elapsedMinutes = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 60_000),
    );

    if (elapsedMinutes < 1) {
        return 'Just now';
    }

    if (elapsedMinutes < 60) {
        return `${elapsedMinutes}m ago`;
    }

    const elapsedHours = Math.floor(elapsedMinutes / 60);

    if (elapsedHours < 24) {
        return `${elapsedHours}h ago`;
    }

    const elapsedDays = Math.floor(elapsedHours / 24);

    return elapsedDays < 30 ? `${elapsedDays}d ago` : formatAdminDate(value);
}

export function isRecentlyOnline(value: string | null): boolean {
    if (!value) {
        return false;
    }

    return Date.now() - new Date(value).getTime() <= 15 * 60_000;
}

/**
 * The Advisor's asset series — one colour per holding, in allocation order.
 *
 * Shared by the allocation ring, the row swatches and the legend so a colour
 * means the same asset everywhere on the page. Colour never carries meaning on
 * its own here: every segment is also named and given its percentage.
 */
export const ADVISOR_SERIES_COLORS = [
    '#02cd86',
    '#d9c48f',
    '#60a5fa',
    '#947bff',
    '#e9944e',
    '#55635c',
] as const;

/** Wraps past the sixth holding, where adjacent hues stop being tellable apart. */
export function seriesColor(index: number): string {
    return ADVISOR_SERIES_COLORS[index % ADVISOR_SERIES_COLORS.length];
}

export type RingSegment = {
    percent: number;
    color: string;
};

/**
 * The ring as a conic gradient, drawn only as far as `filled`.
 *
 * A segment is painted in its own series colour once the sweep reaches it and
 * clipped where the sweep stops, so the ring assembles holding by holding
 * rather than growing as one undifferentiated arc. Everything past the sweep is
 * the unfilled track.
 */
export function ringGradient(segments: RingSegment[], filled = 100): string {
    const limit = Math.max(0, Math.min(100, filled));
    const stops: string[] = [];
    let consumed = 0;

    for (const segment of segments) {
        const start = consumed;
        const end = Math.min(consumed + segment.percent, limit);

        if (start < limit) {
            stops.push(`${segment.color} ${start}% ${end}%`);
        }

        consumed += segment.percent;
    }

    if (limit < 100) {
        stops.push(`rgba(255,255,255,.05) ${limit}% 100%`);
    }

    // A gradient needs at least two stops to be valid; an empty plan is a bare
    // track rather than nothing at all.
    if (stops.length === 0) {
        stops.push('rgba(255,255,255,.05) 0% 100%');
    }

    return `conic-gradient(${stops.join(', ')})`;
}

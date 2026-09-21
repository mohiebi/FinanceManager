/**
 * Picker ordering for one-level category hierarchies: every parent directly
 * followed by its subcategories.
 *
 * One level only, so a single pass does it — no tree walk. The server lists a
 * shared category under both types, so a list merged from the two (the
 * filters do this) would show it twice; ids are deduplicated here as well.
 */

export type HierarchicalCategory = {
    id: number;
    name: string;
    parent_id: number | null;
};

export type OrderedCategory<T extends HierarchicalCategory> = T & {
    depth: 0 | 1;
};

/**
 * A subcategory's item in a reka `Select`. The base item pads with physical
 * `pl-2 pr-8` and pins its checkmark on the right, so the indent has to flip
 * sides under RTL rather than use a logical `ps-*` that would not override it.
 */
export const SUBCATEGORY_ITEM_CLASS = 'pl-7 rtl:pl-2 rtl:pr-13';

export function orderByParent<T extends HierarchicalCategory>(
    categories: readonly T[],
): OrderedCategory<T>[] {
    const unique = uniqueById(categories);
    const present = new Set(unique.map(({ id }) => id));
    const childrenOf = new Map<number, T[]>();

    for (const category of unique) {
        if (category.parent_id !== null && present.has(category.parent_id)) {
            childrenOf.set(category.parent_id, [
                ...(childrenOf.get(category.parent_id) ?? []),
                category,
            ]);
        }
    }

    // A subcategory whose parent is not in this list is shown top-level
    // rather than dropped: losing an option is worse than losing its indent.
    return unique
        .filter(
            (category) =>
                category.parent_id === null || !present.has(category.parent_id),
        )
        .flatMap((parent) => [
            { ...parent, depth: 0 as const },
            ...(childrenOf.get(parent.id) ?? []).map((child) => ({
                ...child,
                depth: 1 as const,
            })),
        ]);
}

/**
 * Four no-break spaces, written as an escape so they cannot be mistaken for
 * (and "tidied" into) ordinary ones, which collapse inside option text.
 */
const SUBCATEGORY_INDENT = '\u00A0'.repeat(4);

/**
 * A native `<option>` cannot take padding, so a subcategory is indented with
 * no-break spaces instead. They carry no direction, so the indent lands on
 * the correct side under RTL too.
 */
export function optionLabel(
    category: OrderedCategory<HierarchicalCategory>,
): string {
    return category.depth === 1
        ? `${SUBCATEGORY_INDENT}${category.name}`
        : category.name;
}

function uniqueById<T extends { id: number }>(categories: readonly T[]): T[] {
    const seen = new Set<number>();

    return categories.filter(({ id }) => {
        if (seen.has(id)) {
            return false;
        }

        seen.add(id);

        return true;
    });
}

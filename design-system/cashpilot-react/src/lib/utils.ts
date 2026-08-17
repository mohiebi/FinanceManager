import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind class strings, with later utilities winning conflicts.
 * Ported verbatim from the CashPilot app's `resources/js/lib/utils.ts`.
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

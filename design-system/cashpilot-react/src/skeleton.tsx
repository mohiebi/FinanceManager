import * as React from 'react';
import { cn } from './lib/utils';

export type SkeletonProps = React.HTMLAttributes<HTMLDivElement>;

/**
 * A pulsing placeholder block for loading states. Give it explicit `h-*`/`w-*`
 * classes matching the content it stands in for.
 */
export const Skeleton = React.forwardRef<HTMLDivElement, SkeletonProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="skeleton"
            ref={ref}
            className={cn('animate-pulse rounded-md bg-primary/10', className)}
            {...props}
        />
    ),
);
Skeleton.displayName = 'Skeleton';

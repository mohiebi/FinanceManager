import { Loader2 } from 'lucide-react';
import * as React from 'react';
import { cn } from './lib/utils';

export type SpinnerProps = React.ComponentPropsWithoutRef<typeof Loader2>;

/**
 * An indeterminate loading indicator. Carries `role="status"` and an accessible
 * label; resize with a `size-*` class.
 */
export const Spinner = React.forwardRef<SVGSVGElement, SpinnerProps>(
    ({ className, ...props }, ref) => (
        <Loader2
            ref={ref}
            role="status"
            aria-label="Loading"
            className={cn('size-4 animate-spin', className)}
            {...props}
        />
    ),
);
Spinner.displayName = 'Spinner';

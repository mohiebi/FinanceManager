import * as React from 'react';
import * as LabelPrimitive from '@radix-ui/react-label';
import { cn } from './lib/utils';

export type LabelProps = React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root>;

/**
 * Form field label. Dims itself when the labelled control is disabled, via the
 * `peer-disabled` and `group-data-[disabled=true]` hooks.
 */
export const Label = React.forwardRef<
    React.ElementRef<typeof LabelPrimitive.Root>,
    LabelProps
>(({ className, ...props }, ref) => (
    <LabelPrimitive.Root
        data-slot="label"
        ref={ref}
        className={cn(
            'flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
            className,
        )}
        {...props}
    />
));
Label.displayName = 'Label';

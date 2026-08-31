import * as SeparatorPrimitive from '@radix-ui/react-separator';
import * as React from 'react';
import { cn } from './lib/utils';

export type SeparatorProps = React.ComponentPropsWithoutRef<typeof SeparatorPrimitive.Root>;

/**
 * A one-pixel rule. Horizontal and decorative by default; pass
 * `orientation="vertical"` inside a flex row to divide inline content.
 */
export const Separator = React.forwardRef<
    React.ElementRef<typeof SeparatorPrimitive.Root>,
    SeparatorProps
>(({ className, orientation = 'horizontal', decorative = true, ...props }, ref) => (
    <SeparatorPrimitive.Root
        data-slot="separator"
        ref={ref}
        orientation={orientation}
        decorative={decorative}
        className={cn(
            'bg-border shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px',
            className,
        )}
        {...props}
    />
));
Separator.displayName = 'Separator';

import * as React from 'react';
import * as SwitchPrimitive from '@radix-ui/react-switch';
import { cn } from './lib/utils';

export type SwitchProps = React.ComponentPropsWithoutRef<typeof SwitchPrimitive.Root>;

/**
 * Binary on/off toggle. Unlike the rest of the kit this one is painted with the
 * CashPilot brand green (#02CD86) directly rather than a semantic token, and it
 * assumes a dark surface — the unchecked track is `white/15` and the focus ring
 * offsets against #1a1a1a. RTL-aware: the thumb travel flips under `rtl:`.
 */
export const Switch = React.forwardRef<
    React.ElementRef<typeof SwitchPrimitive.Root>,
    SwitchProps
>(({ className, ...props }, ref) => (
    <SwitchPrimitive.Root
        data-slot="switch"
        ref={ref}
        className={cn(
            'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-[#02CD86] data-[state=unchecked]:bg-white/15',
            className,
        )}
        {...props}
    >
        <SwitchPrimitive.Thumb
            data-slot="switch-thumb"
            className="pointer-events-none block size-4 rounded-full bg-white shadow-lg ring-0 transition-transform data-[state=unchecked]:translate-x-0 data-[state=checked]:translate-x-4 rtl:data-[state=checked]:-translate-x-4"
        />
    </SwitchPrimitive.Root>
));
Switch.displayName = 'Switch';

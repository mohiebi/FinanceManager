import * as React from 'react';
import * as CollapsiblePrimitive from '@radix-ui/react-collapsible';

export type CollapsibleProps = React.ComponentPropsWithoutRef<typeof CollapsiblePrimitive.Root>;

/** An unstyled show/hide container. Styling is left to the consumer. */
export const Collapsible = React.forwardRef<
    React.ElementRef<typeof CollapsiblePrimitive.Root>,
    CollapsibleProps
>((props, ref) => <CollapsiblePrimitive.Root data-slot="collapsible" ref={ref} {...props} />);
Collapsible.displayName = 'Collapsible';

export type CollapsibleTriggerProps = React.ComponentPropsWithoutRef<
    typeof CollapsiblePrimitive.CollapsibleTrigger
>;

/** Toggles its Collapsible open and closed. */
export const CollapsibleTrigger = React.forwardRef<
    React.ElementRef<typeof CollapsiblePrimitive.CollapsibleTrigger>,
    CollapsibleTriggerProps
>((props, ref) => (
    <CollapsiblePrimitive.CollapsibleTrigger data-slot="collapsible-trigger" ref={ref} {...props} />
));
CollapsibleTrigger.displayName = 'CollapsibleTrigger';

export type CollapsibleContentProps = React.ComponentPropsWithoutRef<
    typeof CollapsiblePrimitive.CollapsibleContent
>;

/** The region revealed when its Collapsible is open. */
export const CollapsibleContent = React.forwardRef<
    React.ElementRef<typeof CollapsiblePrimitive.CollapsibleContent>,
    CollapsibleContentProps
>((props, ref) => (
    <CollapsiblePrimitive.CollapsibleContent data-slot="collapsible-content" ref={ref} {...props} />
));
CollapsibleContent.displayName = 'CollapsibleContent';

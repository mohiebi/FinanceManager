import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from './lib/utils';

export const alertVariants = cva(
    'relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current',
    {
        variants: {
            variant: {
                default: 'bg-card text-card-foreground',
                destructive:
                    'text-destructive bg-card [&>svg]:text-current *:data-[slot=alert-description]:text-destructive/90',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export type AlertVariants = VariantProps<typeof alertVariants>;

export interface AlertProps
    extends React.HTMLAttributes<HTMLDivElement>,
        VariantProps<typeof alertVariants> {}

/**
 * An inline message block. Pass an icon as the first child and the grid opens a
 * dedicated icon column; without one the column collapses to zero width.
 */
export const Alert = React.forwardRef<HTMLDivElement, AlertProps>(
    ({ className, variant, ...props }, ref) => (
        <div
            data-slot="alert"
            ref={ref}
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    ),
);
Alert.displayName = 'Alert';

export type AlertTitleProps = React.HTMLAttributes<HTMLDivElement>;

/** The Alert's headline. Clamped to a single line. */
export const AlertTitle = React.forwardRef<HTMLDivElement, AlertTitleProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="alert-title"
            ref={ref}
            className={cn('col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight', className)}
            {...props}
        />
    ),
);
AlertTitle.displayName = 'AlertTitle';

export type AlertDescriptionProps = React.HTMLAttributes<HTMLDivElement>;

/** Supporting copy inside an Alert; inherits the destructive tint when set. */
export const AlertDescription = React.forwardRef<HTMLDivElement, AlertDescriptionProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="alert-description"
            ref={ref}
            className={cn(
                'text-muted-foreground col-start-2 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed',
                className,
            )}
            {...props}
        />
    ),
);
AlertDescription.displayName = 'AlertDescription';

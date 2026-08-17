import * as React from 'react';
import { cn } from './lib/utils';

export type CardProps = React.HTMLAttributes<HTMLDivElement>;

/**
 * The standard surface container. A vertical flex stack with a 1.5rem gutter
 * between sections; pair with CardHeader / CardContent / CardFooter.
 */
export const Card = React.forwardRef<HTMLDivElement, CardProps>(({ className, ...props }, ref) => (
    <div
        data-slot="card"
        ref={ref}
        className={cn(
            'bg-card text-card-foreground flex flex-col gap-6 rounded-xl border py-6 shadow-sm',
            className,
        )}
        {...props}
    />
));
Card.displayName = 'Card';

export type CardHeaderProps = React.HTMLAttributes<HTMLDivElement>;

/**
 * Header row for a Card. Switches to a two-column grid automatically when a
 * CardAction is present, so the action sits flush right of the title block.
 */
export const CardHeader = React.forwardRef<HTMLDivElement, CardHeaderProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="card-header"
            ref={ref}
            className={cn(
                '@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6',
                className,
            )}
            {...props}
        />
    ),
);
CardHeader.displayName = 'CardHeader';

export type CardTitleProps = React.HTMLAttributes<HTMLHeadingElement>;

/** The Card's heading. Renders an `<h3>`. */
export const CardTitle = React.forwardRef<HTMLHeadingElement, CardTitleProps>(
    ({ className, ...props }, ref) => (
        <h3
            data-slot="card-title"
            ref={ref}
            className={cn('leading-none font-semibold', className)}
            {...props}
        />
    ),
);
CardTitle.displayName = 'CardTitle';

export type CardDescriptionProps = React.HTMLAttributes<HTMLParagraphElement>;

/** Muted supporting copy under a CardTitle. */
export const CardDescription = React.forwardRef<HTMLParagraphElement, CardDescriptionProps>(
    ({ className, ...props }, ref) => (
        <p
            data-slot="card-description"
            ref={ref}
            className={cn('text-muted-foreground text-sm', className)}
            {...props}
        />
    ),
);
CardDescription.displayName = 'CardDescription';

export type CardActionProps = React.HTMLAttributes<HTMLDivElement>;

/**
 * Trailing action slot for a CardHeader — a button or menu that aligns to the
 * top-right of the header grid.
 */
export const CardAction = React.forwardRef<HTMLDivElement, CardActionProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="card-action"
            ref={ref}
            className={cn('col-start-2 row-span-2 row-start-1 self-start justify-self-end', className)}
            {...props}
        />
    ),
);
CardAction.displayName = 'CardAction';

export type CardContentProps = React.HTMLAttributes<HTMLDivElement>;

/** The Card's main body region. */
export const CardContent = React.forwardRef<HTMLDivElement, CardContentProps>(
    ({ className, ...props }, ref) => (
        <div data-slot="card-content" ref={ref} className={cn('px-6', className)} {...props} />
    ),
);
CardContent.displayName = 'CardContent';

export type CardFooterProps = React.HTMLAttributes<HTMLDivElement>;

/** Footer row for a Card. Adds top padding when given a top border. */
export const CardFooter = React.forwardRef<HTMLDivElement, CardFooterProps>(
    ({ className, ...props }, ref) => (
        <div
            data-slot="card-footer"
            ref={ref}
            className={cn('flex items-center px-6 [.border-t]:pt-6', className)}
            {...props}
        />
    ),
);
CardFooter.displayName = 'CardFooter';

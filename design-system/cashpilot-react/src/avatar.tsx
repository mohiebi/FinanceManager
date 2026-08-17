import * as React from 'react';
import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cn } from './lib/utils';

export type AvatarProps = React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Root>;

/**
 * Circular user image with a text fallback. 2rem square by default — override
 * with a `size-*` class for larger placements.
 */
export const Avatar = React.forwardRef<
    React.ElementRef<typeof AvatarPrimitive.Root>,
    AvatarProps
>(({ className, ...props }, ref) => (
    <AvatarPrimitive.Root
        data-slot="avatar"
        ref={ref}
        className={cn('relative flex size-8 shrink-0 overflow-hidden rounded-full', className)}
        {...props}
    />
));
Avatar.displayName = 'Avatar';

export type AvatarImageProps = React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Image>;

/** The Avatar's image. Hidden automatically while loading or on error. */
export const AvatarImage = React.forwardRef<
    React.ElementRef<typeof AvatarPrimitive.Image>,
    AvatarImageProps
>(({ className, ...props }, ref) => (
    <AvatarPrimitive.Image
        data-slot="avatar-image"
        ref={ref}
        className={cn('aspect-square size-full', className)}
        {...props}
    />
));
AvatarImage.displayName = 'AvatarImage';

export type AvatarFallbackProps = React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Fallback>;

/** Shown when the AvatarImage is missing or still loading — usually initials. */
export const AvatarFallback = React.forwardRef<
    React.ElementRef<typeof AvatarPrimitive.Fallback>,
    AvatarFallbackProps
>(({ className, ...props }, ref) => (
    <AvatarPrimitive.Fallback
        data-slot="avatar-fallback"
        ref={ref}
        className={cn('bg-muted flex size-full items-center justify-center rounded-full', className)}
        {...props}
    />
));
AvatarFallback.displayName = 'AvatarFallback';

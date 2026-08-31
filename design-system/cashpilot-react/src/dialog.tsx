import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import * as React from 'react';
import { cn } from './lib/utils';

export type DialogProps = React.ComponentPropsWithoutRef<typeof DialogPrimitive.Root>;

/** Modal dialog root. Controls open state for its trigger and content. */
export const Dialog = (props: DialogProps) => <DialogPrimitive.Root data-slot="dialog" {...props} />;
Dialog.displayName = 'Dialog';

export type DialogTriggerProps = React.ComponentPropsWithoutRef<typeof DialogPrimitive.Trigger>;

/** Opens its Dialog. Use `asChild` to wrap a Button. */
export const DialogTrigger = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Trigger>,
    DialogTriggerProps
>((props, ref) => <DialogPrimitive.Trigger data-slot="dialog-trigger" ref={ref} {...props} />);
DialogTrigger.displayName = 'DialogTrigger';

export type DialogCloseProps = React.ComponentPropsWithoutRef<typeof DialogPrimitive.Close>;

/** Closes its Dialog. Use `asChild` to wrap a Button in a DialogFooter. */
export const DialogClose = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Close>,
    DialogCloseProps
>((props, ref) => <DialogPrimitive.Close data-slot="dialog-close" ref={ref} {...props} />);
DialogClose.displayName = 'DialogClose';

export type DialogOverlayProps = React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>;

/** The dimmed backdrop behind DialogContent. Sits at z-index 300. */
export const DialogOverlay = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Overlay>,
    DialogOverlayProps
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        data-slot="dialog-overlay"
        ref={ref}
        className={cn(
            'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-[300] bg-black/80',
            className,
        )}
        {...props}
    />
));
DialogOverlay.displayName = 'DialogOverlay';

export interface DialogContentProps
    extends React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> {
    /** Render the built-in top-right close button. Defaults to `true`. */
    showCloseButton?: boolean;
    /**
     * Screen-reader text for the close button. In the Vue original this came
     * from `useI18n()` (`buttons.close`); pass the translated string here.
     */
    closeLabel?: string;
}

/**
 * The dialog panel. Unlike the rest of the kit this surface is painted with
 * fixed CashPilot chrome rather than semantic tokens — #1a1a1a, white text, a
 * `white/10` hairline ring, and a 25px corner radius. Centred and portalled,
 * with its own overlay at z-index 300 and the panel at 310.
 */
export const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    DialogContentProps
>(({ className, children, showCloseButton = true, closeLabel = 'Close', ...props }, ref) => (
    <DialogPrimitive.Portal>
        <DialogOverlay />
        <DialogPrimitive.Content
            data-slot="dialog-content"
            ref={ref}
            className={cn(
                'bg-[#1a1a1a] text-white ring-1 ring-white/10 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-[310] grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-[25px] border-0 p-6 shadow-2xl duration-200 sm:max-w-lg',
                className,
            )}
            {...props}
        >
            {children}
            {showCloseButton && (
                <DialogPrimitive.Close
                    data-slot="dialog-close"
                    className="ring-offset-background focus:ring-ring text-white/60 hover:text-white data-[state=open]:bg-white/10 data-[state=open]:text-white absolute top-4 right-4 rounded-full p-1 opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
                >
                    <X />
                    <span className="sr-only">{closeLabel}</span>
                </DialogPrimitive.Close>
            )}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
));
DialogContent.displayName = 'DialogContent';

export interface DialogScrollContentProps
    extends React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> {
    /** Screen-reader text for the close button. */
    closeLabel?: string;
}

/**
 * A DialogContent variant for tall bodies: the overlay itself scrolls and the
 * panel flows in the page rather than being pinned to the viewport centre.
 */
export const DialogScrollContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    DialogScrollContentProps
>(({ className, children, closeLabel = 'Close', ...props }, ref) => (
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-[300] grid place-items-center overflow-y-auto bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0">
            <DialogPrimitive.Content
                data-slot="dialog-content"
                ref={ref}
                className={cn(
                    'relative z-[310] grid w-full max-w-lg my-8 gap-4 border-0 bg-[#1a1a1a] text-white ring-1 ring-white/10 p-6 shadow-2xl duration-200 sm:rounded-[25px] md:w-full',
                    className,
                )}
                {...props}
            >
                {children}
                <DialogPrimitive.Close className="absolute top-4 right-4 p-1 text-white/60 transition-colors rounded-full hover:bg-white/10 hover:text-white">
                    <X className="w-4 h-4" />
                    <span className="sr-only">{closeLabel}</span>
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Overlay>
    </DialogPrimitive.Portal>
));
DialogScrollContent.displayName = 'DialogScrollContent';

export type DialogHeaderProps = React.HTMLAttributes<HTMLDivElement>;

/** Title/description block at the top of a DialogContent. */
export const DialogHeader = ({ className, ...props }: DialogHeaderProps) => (
    <div
        data-slot="dialog-header"
        className={cn('flex flex-col gap-2 text-center sm:text-start', className)}
        {...props}
    />
);
DialogHeader.displayName = 'DialogHeader';

export type DialogFooterProps = React.HTMLAttributes<HTMLDivElement>;

/** Action row at the bottom of a DialogContent. Stacks in reverse on mobile. */
export const DialogFooter = ({ className, ...props }: DialogFooterProps) => (
    <div
        data-slot="dialog-footer"
        className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)}
        {...props}
    />
);
DialogFooter.displayName = 'DialogFooter';

export type DialogTitleProps = React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>;

/** The dialog's accessible title. Required for screen readers. */
export const DialogTitle = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Title>,
    DialogTitleProps
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        data-slot="dialog-title"
        ref={ref}
        className={cn('text-lg leading-none font-semibold', className)}
        {...props}
    />
));
DialogTitle.displayName = 'DialogTitle';

export type DialogDescriptionProps = React.ComponentPropsWithoutRef<
    typeof DialogPrimitive.Description
>;

/** Supporting copy under a DialogTitle. */
export const DialogDescription = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Description>,
    DialogDescriptionProps
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Description
        data-slot="dialog-description"
        ref={ref}
        className={cn('text-muted-foreground text-sm', className)}
        {...props}
    />
));
DialogDescription.displayName = 'DialogDescription';

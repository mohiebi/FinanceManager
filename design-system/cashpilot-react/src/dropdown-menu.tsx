import * as React from 'react';
import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { Check, ChevronRight, Circle } from 'lucide-react';
import { cn } from './lib/utils';

export type DropdownMenuProps = React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Root>;

/** Action menu root. Compose with DropdownMenuTrigger and DropdownMenuContent. */
export const DropdownMenu = (props: DropdownMenuProps) => (
    <DropdownMenuPrimitive.Root data-slot="dropdown-menu" {...props} />
);
DropdownMenu.displayName = 'DropdownMenu';

export type DropdownMenuTriggerProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.Trigger
>;

/** Opens its menu. Use `asChild` to wrap a Button. */
export const DropdownMenuTrigger = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Trigger>,
    DropdownMenuTriggerProps
>((props, ref) => (
    <DropdownMenuPrimitive.Trigger data-slot="dropdown-menu-trigger" ref={ref} {...props} />
));
DropdownMenuTrigger.displayName = 'DropdownMenuTrigger';

export const DropdownMenuPortal = DropdownMenuPrimitive.Portal;

export type DropdownMenuGroupProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.Group
>;

/** Groups related items inside a menu. */
export const DropdownMenuGroup = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Group>,
    DropdownMenuGroupProps
>((props, ref) => (
    <DropdownMenuPrimitive.Group data-slot="dropdown-menu-group" ref={ref} {...props} />
));
DropdownMenuGroup.displayName = 'DropdownMenuGroup';

export type DropdownMenuContentProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.Content
>;

/**
 * The floating menu panel. Like Dialog, this surface is painted with fixed
 * CashPilot chrome rather than tokens: a translucent #1f1f1f with a backdrop
 * blur, a `white/10` hairline ring, 1rem corners and a deep drop shadow.
 */
export const DropdownMenuContent = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Content>,
    DropdownMenuContentProps
>(({ className, sideOffset = 4, ...props }, ref) => (
    <DropdownMenuPrimitive.Portal>
        <DropdownMenuPrimitive.Content
            data-slot="dropdown-menu-content"
            ref={ref}
            sideOffset={sideOffset}
            className={cn(
                'bg-[#1f1f1f]/95 text-white supports-[backdrop-filter]:backdrop-blur-xl ring-1 ring-white/10 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[14rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-2xl border-0 p-1.5 shadow-[0_18px_45px_rgba(0,0,0,0.45)]',
                className,
            )}
            {...props}
        />
    </DropdownMenuPrimitive.Portal>
));
DropdownMenuContent.displayName = 'DropdownMenuContent';

export interface DropdownMenuItemProps
    extends React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Item> {
    /** Indent to align with items that have a leading indicator. */
    inset?: boolean;
    /** `destructive` tints the row and its icon with the CashPilot red. */
    variant?: 'default' | 'destructive';
}

/**
 * One menu row. Rounded pill hover at `white/10`; the `destructive` variant
 * switches text, icon and hover tint to #E94E50.
 */
export const DropdownMenuItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Item>,
    DropdownMenuItemProps
>(({ className, inset, variant = 'default', ...props }, ref) => (
    <DropdownMenuPrimitive.Item
        data-slot="dropdown-menu-item"
        data-inset={inset ? '' : undefined}
        data-variant={variant}
        ref={ref}
        className={cn(
            "text-white/80 transition-colors duration-150 focus:bg-white/10 focus:text-white hover:bg-white/10 hover:text-white data-[variant=destructive]:text-[#E94E50] data-[variant=destructive]:focus:bg-[#E94E50]/10 data-[variant=destructive]:focus:text-[#E94E50] data-[variant=destructive]:*:[svg]:!text-[#E94E50] [&_svg:not([class*='text-'])]:text-white/50 relative flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-9 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
            className,
        )}
        {...props}
    />
));
DropdownMenuItem.displayName = 'DropdownMenuItem';

export type DropdownMenuCheckboxItemProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.CheckboxItem
>;

/** A menu row with a check indicator on the left. */
export const DropdownMenuCheckboxItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.CheckboxItem>,
    DropdownMenuCheckboxItemProps
>(({ className, children, ...props }, ref) => (
    <DropdownMenuPrimitive.CheckboxItem
        data-slot="dropdown-menu-checkbox-item"
        ref={ref}
        className={cn(
            "focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
            className,
        )}
        {...props}
    >
        <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
            <DropdownMenuPrimitive.ItemIndicator>
                <Check className="size-4" />
            </DropdownMenuPrimitive.ItemIndicator>
        </span>
        {children}
    </DropdownMenuPrimitive.CheckboxItem>
));
DropdownMenuCheckboxItem.displayName = 'DropdownMenuCheckboxItem';

export type DropdownMenuRadioGroupProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.RadioGroup
>;

/** Groups DropdownMenuRadioItems into one exclusive choice. */
export const DropdownMenuRadioGroup = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.RadioGroup>,
    DropdownMenuRadioGroupProps
>((props, ref) => (
    <DropdownMenuPrimitive.RadioGroup data-slot="dropdown-menu-radio-group" ref={ref} {...props} />
));
DropdownMenuRadioGroup.displayName = 'DropdownMenuRadioGroup';

export type DropdownMenuRadioItemProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.RadioItem
>;

/** A menu row with a filled-dot indicator on the left. */
export const DropdownMenuRadioItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.RadioItem>,
    DropdownMenuRadioItemProps
>(({ className, children, ...props }, ref) => (
    <DropdownMenuPrimitive.RadioItem
        data-slot="dropdown-menu-radio-item"
        ref={ref}
        className={cn(
            "focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
            className,
        )}
        {...props}
    >
        <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
            <DropdownMenuPrimitive.ItemIndicator>
                <Circle className="size-2 fill-current" />
            </DropdownMenuPrimitive.ItemIndicator>
        </span>
        {children}
    </DropdownMenuPrimitive.RadioItem>
));
DropdownMenuRadioItem.displayName = 'DropdownMenuRadioItem';

export interface DropdownMenuLabelProps
    extends React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Label> {
    /** Indent to align with items that have a leading indicator. */
    inset?: boolean;
}

/** A non-interactive caption heading a group of menu rows. */
export const DropdownMenuLabel = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Label>,
    DropdownMenuLabelProps
>(({ className, inset, ...props }, ref) => (
    <DropdownMenuPrimitive.Label
        data-slot="dropdown-menu-label"
        data-inset={inset ? '' : undefined}
        ref={ref}
        className={cn('px-3 py-2 text-sm font-medium text-white data-[inset]:pl-9', className)}
        {...props}
    />
));
DropdownMenuLabel.displayName = 'DropdownMenuLabel';

export type DropdownMenuSeparatorProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.Separator
>;

/** A hairline divider between menu groups, tinted `white/10`. */
export const DropdownMenuSeparator = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Separator>,
    DropdownMenuSeparatorProps
>(({ className, ...props }, ref) => (
    <DropdownMenuPrimitive.Separator
        data-slot="dropdown-menu-separator"
        ref={ref}
        className={cn('bg-white/10 -mx-1.5 my-1.5 h-px', className)}
        {...props}
    />
));
DropdownMenuSeparator.displayName = 'DropdownMenuSeparator';

export type DropdownMenuShortcutProps = React.HTMLAttributes<HTMLSpanElement>;

/** Right-aligned keyboard hint inside a menu row. */
export const DropdownMenuShortcut = ({ className, ...props }: DropdownMenuShortcutProps) => (
    <span
        data-slot="dropdown-menu-shortcut"
        className={cn('text-muted-foreground ml-auto text-xs tracking-widest', className)}
        {...props}
    />
);
DropdownMenuShortcut.displayName = 'DropdownMenuShortcut';

export type DropdownMenuSubProps = React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Sub>;

/** A nested submenu. */
export const DropdownMenuSub = (props: DropdownMenuSubProps) => (
    <DropdownMenuPrimitive.Sub data-slot="dropdown-menu-sub" {...props} />
);
DropdownMenuSub.displayName = 'DropdownMenuSub';

export interface DropdownMenuSubTriggerProps
    extends React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.SubTrigger> {
    /** Indent to align with items that have a leading indicator. */
    inset?: boolean;
}

/** The row that opens a submenu. Shows a trailing chevron. */
export const DropdownMenuSubTrigger = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.SubTrigger>,
    DropdownMenuSubTriggerProps
>(({ className, inset, children, ...props }, ref) => (
    <DropdownMenuPrimitive.SubTrigger
        data-slot="dropdown-menu-sub-trigger"
        data-inset={inset ? '' : undefined}
        ref={ref}
        className={cn(
            'focus:bg-accent focus:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground flex cursor-default items-center rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[inset]:pl-8',
            className,
        )}
        {...props}
    >
        {children}
        <ChevronRight className="ml-auto size-4" />
    </DropdownMenuPrimitive.SubTrigger>
));
DropdownMenuSubTrigger.displayName = 'DropdownMenuSubTrigger';

export type DropdownMenuSubContentProps = React.ComponentPropsWithoutRef<
    typeof DropdownMenuPrimitive.SubContent
>;

/** The submenu panel. Uses the popover tokens rather than the dark chrome. */
export const DropdownMenuSubContent = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.SubContent>,
    DropdownMenuSubContentProps
>(({ className, ...props }, ref) => (
    <DropdownMenuPrimitive.SubContent
        data-slot="dropdown-menu-sub-content"
        ref={ref}
        className={cn(
            'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-hidden rounded-md border p-1 shadow-lg',
            className,
        )}
        {...props}
    />
));
DropdownMenuSubContent.displayName = 'DropdownMenuSubContent';

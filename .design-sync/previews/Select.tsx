import {
    Label,
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@cashpilot/ui';

/**
 * SelectContent is portalled to document.body, so it sits OUTSIDE any inner
 * `.dark` wrapper and would resolve `bg-popover` against the light `:root`
 * tokens — a white panel on a dark page. The app avoids this by putting `dark`
 * on <html> in initializeTheme(); we do the same here.
 */
if (typeof document !== 'undefined') {
    document.documentElement.classList.add('dark');
}

/** Closed state — the control as it appears in a form row. */
export const Closed = () => (
    <div className="dark bg-background text-foreground flex flex-col gap-4 p-6">
        <div className="flex w-full max-w-sm flex-col gap-2">
            <Label htmlFor="currency">Currency</Label>
            <Select defaultValue="toman">
                <SelectTrigger id="currency" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="toman">Toman</SelectItem>
                    <SelectItem value="usd">US Dollar</SelectItem>
                    <SelectItem value="eur">Euro</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div className="flex w-full max-w-sm flex-col gap-2">
            <Label>Placeholder state</Label>
            <Select>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Pick a category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="groceries">Groceries</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div className="flex w-full max-w-sm flex-col gap-2">
            <Label>Small size</Label>
            <Select defaultValue="30">
                <SelectTrigger size="sm" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="30">Last 30 days</SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>
);

/** Rendered open so the portalled option list is visible statically. */
export const Open = () => (
    <div
        className="dark bg-background text-foreground flex justify-center p-4"
        style={{ minHeight: 420 }}
    >
        <Select defaultValue="groceries" defaultOpen>
            <SelectTrigger className="w-64">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectLabel>Essentials</SelectLabel>
                    <SelectItem value="groceries">Groceries</SelectItem>
                    <SelectItem value="utilities">Utilities</SelectItem>
                    <SelectItem value="transport">Transport</SelectItem>
                </SelectGroup>
                <SelectSeparator />
                <SelectGroup>
                    <SelectLabel>Discretionary</SelectLabel>
                    <SelectItem value="dining">Dining out</SelectItem>
                    <SelectItem value="travel">Travel</SelectItem>
                    <SelectItem value="archived" disabled>
                        Archived
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
);

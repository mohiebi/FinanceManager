import {
    Button,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@cashpilot/ui';
import { Copy, Download, Pencil, Settings, Trash2, User } from 'lucide-react';

/** DropdownMenuContent is portalled — see the note in Select.tsx. */
if (typeof document !== 'undefined') {
    document.documentElement.classList.add('dark');
}

/**
 * Rendered open so the panel is visible statically. Like Dialog, the menu
 * surface is fixed CashPilot chrome (#1f1f1f/95 with a backdrop blur), not
 * token-driven.
 */
export const RowActions = () => (
    <div
        className="dark bg-background text-foreground flex justify-center p-4"
        style={{ minHeight: 380 }}
    >
        <DropdownMenu defaultOpen modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">Transaction actions</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem>
                    <Pencil /> Edit transaction
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <Copy /> Duplicate
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <Download /> Export as CSV
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive">
                    <Trash2 /> Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
);

export const WithLabelAndShortcuts = () => (
    <div
        className="dark bg-background text-foreground flex justify-center p-4"
        style={{ minHeight: 380 }}
    >
        <DropdownMenu defaultOpen modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">Account</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuLabel>Sara N.</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem>
                        <User /> Profile
                        <DropdownMenuShortcut>⇧P</DropdownMenuShortcut>
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                        <Settings /> Settings
                        <DropdownMenuShortcut>⌘,</DropdownMenuShortcut>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive">Sign out</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
);

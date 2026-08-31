import { Button, Spinner } from '@cashpilot/ui';
import { ArrowUpRight, Download, Plus, Trash2 } from 'lucide-react';

/**
 * CashPilot renders dark-only — `initializeTheme()` puts `dark` on <html>
 * unconditionally — so every card mounts inside a `.dark` surface.
 */
const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground flex flex-wrap items-center gap-3 p-6">
        {children}
    </div>
);

export const Variants = () => (
    <Surface>
        <Button>Add transaction</Button>
        <Button variant="secondary">Export</Button>
        <Button variant="outline">Filter</Button>
        <Button variant="ghost">Cancel</Button>
        <Button variant="destructive">Delete bill</Button>
        <Button variant="link">View all activity</Button>
    </Surface>
);

export const Sizes = () => (
    <Surface>
        <Button size="sm">Small</Button>
        <Button size="default">Default</Button>
        <Button size="lg">Large</Button>
        <Button size="icon" aria-label="Add">
            <Plus />
        </Button>
        <Button size="icon-sm" variant="outline" aria-label="Download">
            <Download />
        </Button>
        <Button size="icon-lg" variant="secondary" aria-label="Remove">
            <Trash2 />
        </Button>
    </Surface>
);

export const WithIcons = () => (
    <Surface>
        <Button>
            <Plus /> New savings goal
        </Button>
        <Button variant="outline">
            <Download /> Download statement
        </Button>
        <Button variant="link">
            Open portfolio <ArrowUpRight />
        </Button>
    </Surface>
);

export const States = () => (
    <Surface>
        <Button disabled>Disabled</Button>
        <Button variant="outline" disabled>
            Outline disabled
        </Button>
        <Button disabled>
            <Spinner /> Saving…
        </Button>
    </Surface>
);

import { Checkbox, Input, Label } from '@cashpilot/ui';
import { CircleAlert } from 'lucide-react';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

/** Label renders as a flex row, so an icon child sits inline with a 0.5rem gap. */
export const Basic = () => (
    <Surface>
        <div className="flex flex-col gap-3">
            <Label>Monthly allowance</Label>
            <Label>
                <CircleAlert className="size-4" /> Needs review
            </Label>
        </div>
    </Surface>
);

export const WithInput = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-2">
            <Label htmlFor="goal">Savings goal name</Label>
            <Input id="goal" placeholder="e.g. Emergency fund" />
        </div>
    </Surface>
);

export const WithCheckbox = () => (
    <Surface>
        <div className="flex items-center gap-2">
            <Checkbox id="exclude" defaultChecked />
            <Label htmlFor="exclude">Exclude from budget totals</Label>
        </div>
    </Surface>
);

/**
 * The `peer-disabled` hook dims the label automatically when the control it
 * labels is disabled — the Label needs no disabled prop of its own.
 */
export const DisabledPeer = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-2">
            <Input id="locked" className="peer" disabled defaultValue="Locked account" />
            <Label htmlFor="locked" className="peer-disabled:opacity-50">
                Account nickname
            </Label>
        </div>
    </Surface>
);

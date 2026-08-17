import { Input, Label } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

export const Basic = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-3">
            <Input placeholder="Search transactions" />
            <Input defaultValue="Grocery run — Ofogh Koorosh" />
        </div>
    </Surface>
);

export const Types = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-3">
            <Input type="text" placeholder="Description" />
            <Input type="number" defaultValue={1250000} />
            <Input type="date" defaultValue="2026-02-14" />
            <Input type="email" placeholder="you@example.com" />
        </div>
    </Surface>
);

/** `aria-invalid` drives the destructive ring — no extra class needed. */
export const States = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-3">
            <Input placeholder="Default" />
            <Input defaultValue="Disabled" disabled />
            <Input defaultValue="-4000" aria-invalid />
            <Input readOnly defaultValue="Read only" />
        </div>
    </Surface>
);

export const WithLabels = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-4">
            <div className="flex flex-col gap-2">
                <Label htmlFor="amount">Amount</Label>
                <Input id="amount" type="number" defaultValue={1250000} />
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor="payee">Payee</Label>
                <Input id="payee" placeholder="Who was this paid to?" />
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor="ref">Reference</Label>
                <Input id="ref" aria-invalid defaultValue="!!" />
                <p className="text-destructive text-sm">Reference must be alphanumeric.</p>
            </div>
        </div>
    </Surface>
);

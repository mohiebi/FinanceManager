import { Separator } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

export const Horizontal = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-4">
            <div>
                <p className="text-sm font-medium">Accounts</p>
                <p className="text-muted-foreground text-sm">3 connected</p>
            </div>
            <Separator />
            <div>
                <p className="text-sm font-medium">Budgets</p>
                <p className="text-muted-foreground text-sm">7 active this month</p>
            </div>
        </div>
    </Surface>
);

/** Vertical needs a height from its flex parent — `h-full` alone collapses. */
export const Vertical = () => (
    <Surface>
        <div className="flex h-8 items-center gap-4 text-sm">
            <span>Income</span>
            <Separator orientation="vertical" />
            <span>Spending</span>
            <Separator orientation="vertical" />
            <span>Savings</span>
        </div>
    </Surface>
);

export const InCardFooter = () => (
    <Surface>
        <div className="border-border w-full max-w-sm rounded-xl border">
            <div className="p-4">
                <p className="text-sm font-medium">February summary</p>
                <p className="text-muted-foreground text-sm">84 transactions</p>
            </div>
            <Separator />
            <div className="flex items-center justify-between p-4 text-sm">
                <span className="text-muted-foreground">Net</span>
                <span className="font-semibold tabular-nums">+30,700,000 T</span>
            </div>
        </div>
    </Surface>
);

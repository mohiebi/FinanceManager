import { Alert, AlertDescription, AlertTitle } from '@cashpilot/ui';
import { AlertTriangle, Info, WalletMinimal } from 'lucide-react';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground flex flex-col gap-4 p-6">
        {children}
    </div>
);

export const Default = () => (
    <Surface>
        <Alert>
            <Info />
            <AlertTitle>Your February statement is ready</AlertTitle>
            <AlertDescription>
                We reconciled 84 transactions across 3 accounts. Two of them need a category
                before they count toward a budget.
            </AlertDescription>
        </Alert>
    </Surface>
);

export const Destructive = () => (
    <Surface>
        <Alert variant="destructive">
            <AlertTriangle />
            <AlertTitle>Rent is due in 2 days</AlertTitle>
            <AlertDescription>
                The Mellat account holds 8,400,000 T against a 22,000,000 T bill. Move funds
                or the autopay will fail.
            </AlertDescription>
        </Alert>
    </Surface>
);

/** Without a leading icon the grid's icon column collapses to zero width. */
export const WithoutIcon = () => (
    <Surface>
        <Alert>
            <AlertTitle>Budgets roll over on the 1st</AlertTitle>
            <AlertDescription>
                Anything unspent in a category is returned to your leftover allowance.
            </AlertDescription>
        </Alert>
    </Surface>
);

export const TitleOnly = () => (
    <Surface>
        <Alert>
            <WalletMinimal />
            <AlertTitle>3 transactions are missing a category</AlertTitle>
        </Alert>
    </Surface>
);

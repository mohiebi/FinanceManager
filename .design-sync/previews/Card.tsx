import { LockKeyhole, MoreHorizontal, TrendingUp } from 'lucide-react';
import {
    Badge,
    Button,
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

export const BalanceCard = () => (
    <Surface>
        <Card className="w-full max-w-sm">
            <CardHeader>
                <CardTitle>Total balance</CardTitle>
                <CardDescription>Across every connected account</CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-semibold tabular-nums">124,500,000 T</p>
                <p className="text-muted-foreground mt-1 flex items-center gap-1 text-sm">
                    <TrendingUp className="size-4" /> Up 4.2% this month
                </p>
            </CardContent>
        </Card>
    </Surface>
);

export const WithAction = () => (
    <Surface>
        <Card className="w-full max-w-sm">
            <CardHeader>
                <CardTitle>Groceries budget</CardTitle>
                <CardDescription>18 of 30 days elapsed</CardDescription>
                <CardAction>
                    <Button variant="ghost" size="icon-sm" aria-label="Budget options">
                        <MoreHorizontal />
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold tabular-nums">3,200,000 T</p>
                <p className="text-muted-foreground text-sm">of 5,000,000 T allowance</p>
            </CardContent>
        </Card>
    </Surface>
);

/** The composition pattern used by the app's own settings cards: icon + title, then a footer action row. */
export const WithFooter = () => (
    <Surface>
        <Card className="w-full max-w-md">
            <CardHeader>
                <CardTitle className="flex gap-3">
                    <LockKeyhole className="size-4" /> Recovery codes
                </CardTitle>
                <CardDescription>
                    Store these somewhere safe. Each code can be used once to sign in if you
                    lose your authenticator.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="bg-muted rounded-md p-3 font-mono text-sm">
                    4f9c-21ab-77de
                </div>
            </CardContent>
            <CardFooter className="gap-2">
                <Button>View codes</Button>
                <Button variant="secondary">Regenerate</Button>
            </CardFooter>
        </Card>
    </Surface>
);

export const StatRow = () => (
    <Surface>
        <div className="grid w-full grid-cols-3 gap-4">
            <Card>
                <CardHeader>
                    <CardDescription>Income</CardDescription>
                    <CardTitle className="text-2xl tabular-nums">82,000,000 T</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Spending</CardDescription>
                    <CardTitle className="text-2xl tabular-nums">51,300,000 T</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Saved</CardDescription>
                    <CardTitle className="text-2xl tabular-nums">30,700,000 T</CardTitle>
                    <CardAction>
                        <Badge variant="secondary">37%</Badge>
                    </CardAction>
                </CardHeader>
            </Card>
        </div>
    </Surface>
);

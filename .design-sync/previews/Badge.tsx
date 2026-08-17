import { ArrowDownRight, ArrowUpRight, Clock } from 'lucide-react';
import { Badge } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground flex flex-wrap items-center gap-2 p-6">
        {children}
    </div>
);

export const Variants = () => (
    <Surface>
        <Badge>Paid</Badge>
        <Badge variant="secondary">Pending</Badge>
        <Badge variant="destructive">Overdue</Badge>
        <Badge variant="outline">Draft</Badge>
    </Surface>
);

export const WithIcons = () => (
    <Surface>
        <Badge variant="secondary">
            <ArrowUpRight /> +4.2%
        </Badge>
        <Badge variant="destructive">
            <ArrowDownRight /> −1.8%
        </Badge>
        <Badge variant="outline">
            <Clock /> Due in 2 days
        </Badge>
    </Surface>
);

export const InContext = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-3">
            <div className="flex items-center justify-between">
                <span className="text-sm">Electricity</span>
                <Badge variant="destructive">Overdue</Badge>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-sm">Internet</span>
                <Badge variant="secondary">Pending</Badge>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-sm">Rent</span>
                <Badge>Paid</Badge>
            </div>
        </div>
    </Surface>
);

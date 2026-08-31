import {
    Button,
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
    Separator,
} from '@cashpilot/ui';
import { ChevronDown } from 'lucide-react';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

/**
 * Collapsible ships unstyled by design — the trigger and content carry no
 * classes of their own, so the consumer supplies the chrome.
 */
export const Expanded = () => (
    <Surface>
        <Collapsible defaultOpen className="w-full max-w-sm">
            <CollapsibleTrigger asChild>
                <Button variant="ghost" className="w-full justify-between">
                    February transactions <ChevronDown />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="flex flex-col gap-2 pt-2">
                    <div className="flex justify-between text-sm">
                        <span>Ofogh Koorosh</span>
                        <span className="tabular-nums">−1,250,000 T</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between text-sm">
                        <span>Snapp</span>
                        <span className="tabular-nums">−180,000 T</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between text-sm">
                        <span>Salary</span>
                        <span className="tabular-nums">+82,000,000 T</span>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </Surface>
);

export const Collapsed = () => (
    <Surface>
        <Collapsible className="w-full max-w-sm">
            <CollapsibleTrigger asChild>
                <Button variant="ghost" className="w-full justify-between">
                    Advanced filters <ChevronDown />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <p className="text-muted-foreground pt-2 text-sm">Hidden until opened.</p>
            </CollapsibleContent>
        </Collapsible>
    </Surface>
);

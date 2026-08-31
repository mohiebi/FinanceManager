import {
    Button,
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@cashpilot/ui';
import { Info } from 'lucide-react';

/** TooltipContent is portalled — see the note in Select.tsx. */
if (typeof document !== 'undefined') {
    document.documentElement.classList.add('dark');
}

/**
 * Rendered open so the floating label is visible statically. Tooltip is
 * inverted against the page — `bg-foreground` on `text-background` — so on the
 * dark surface it reads as a light chip.
 */
export const Open = () => (
    <div
        className="dark bg-background text-foreground flex items-center justify-center p-12"
        style={{ minHeight: 200 }}
    >
        <TooltipProvider>
            <Tooltip defaultOpen>
                <TooltipTrigger asChild>
                    <Button variant="outline">Allowance</Button>
                </TooltipTrigger>
                <TooltipContent side="top">
                    A share of income, not a fixed envelope
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
);

export const OnIcon = () => (
    <div
        className="dark bg-background text-foreground flex items-center justify-center p-12"
        style={{ minHeight: 200 }}
    >
        <TooltipProvider>
            <Tooltip defaultOpen>
                <TooltipTrigger asChild>
                    <Button variant="ghost" size="icon-sm" aria-label="What is this?">
                        <Info />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="top">Measured in asset units, not toman</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
);

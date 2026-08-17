import { Skeleton } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

export const Shapes = () => (
    <Surface>
        <div className="flex flex-col gap-3">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-4 w-64" />
            <Skeleton className="h-4 w-32" />
        </div>
    </Surface>
);

/** The loading state for the app's transaction rows. */
export const TransactionRows = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-4">
            {[0, 1, 2].map((i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="size-10 rounded-full" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-3.5 w-32" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    </Surface>
);

export const CardPlaceholder = () => (
    <Surface>
        <div className="border-border flex w-full max-w-sm flex-col gap-4 rounded-xl border p-6">
            <Skeleton className="h-4 w-28" />
            <Skeleton className="h-8 w-44" />
            <Skeleton className="h-3 w-36" />
        </div>
    </Surface>
);

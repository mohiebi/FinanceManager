import { Button, Spinner } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground flex flex-wrap items-center gap-4 p-6">
        {children}
    </div>
);

export const Sizes = () => (
    <Surface>
        <Spinner />
        <Spinner className="size-6" />
        <Spinner className="size-8" />
        <Spinner className="size-12" />
    </Surface>
);

export const Tones = () => (
    <Surface>
        <Spinner className="size-6" />
        <Spinner className="text-muted-foreground size-6" />
        <Spinner className="size-6 text-[#02CD86]" />
        <Spinner className="text-destructive size-6" />
    </Surface>
);

export const InButton = () => (
    <Surface>
        <Button disabled>
            <Spinner /> Saving…
        </Button>
        <Button variant="secondary" disabled>
            <Spinner /> Syncing accounts
        </Button>
    </Surface>
);

export const Centered = () => (
    <Surface>
        <div className="flex w-full flex-col items-center gap-3 py-8">
            <Spinner className="text-muted-foreground size-8" />
            <p className="text-muted-foreground text-sm">Loading your portfolio…</p>
        </div>
    </Surface>
);

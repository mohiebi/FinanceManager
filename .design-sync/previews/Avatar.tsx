import { Avatar, AvatarFallback, AvatarImage } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground flex flex-wrap items-center gap-4 p-6">
        {children}
    </div>
);

/** The fallback is what renders offline — initials on the muted token. */
export const Fallbacks = () => (
    <Surface>
        <Avatar>
            <AvatarFallback>SN</AvatarFallback>
        </Avatar>
        <Avatar>
            <AvatarFallback>MK</AvatarFallback>
        </Avatar>
        <Avatar>
            <AvatarFallback>RA</AvatarFallback>
        </Avatar>
    </Surface>
);

export const Sizes = () => (
    <Surface>
        <Avatar className="size-6">
            <AvatarFallback className="text-xs">SN</AvatarFallback>
        </Avatar>
        <Avatar>
            <AvatarFallback className="text-xs">SN</AvatarFallback>
        </Avatar>
        <Avatar className="size-10">
            <AvatarFallback className="text-sm">SN</AvatarFallback>
        </Avatar>
        <Avatar className="size-16">
            <AvatarFallback className="text-lg">SN</AvatarFallback>
        </Avatar>
    </Surface>
);

export const WithImage = () => (
    <Surface>
        <Avatar className="size-10">
            <AvatarImage
                src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA4MCA4MCI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJnIiB4MT0iMCIgeTE9IjAiIHgyPSIxIiB5Mj0iMSI+PHN0b3Agb2Zmc2V0PSIwIiBzdG9wLWNvbG9yPSIjMDJDRDg2Ii8+PHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjNkM0RUU5Ii8+PC9saW5lYXJHcmFkaWVudD48L2RlZnM+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSJ1cmwoI2cpIi8+PC9zdmc+"
                alt="Sara N."
            />
            <AvatarFallback>SN</AvatarFallback>
        </Avatar>
        <div className="text-sm">
            <p className="font-medium">Sara N.</p>
            <p className="text-muted-foreground">Owner</p>
        </div>
    </Surface>
);

export const Stack = () => (
    <Surface>
        <div className="flex -space-x-2">
            <Avatar className="ring-background ring-2">
                <AvatarFallback className="text-xs">SN</AvatarFallback>
            </Avatar>
            <Avatar className="ring-background ring-2">
                <AvatarFallback className="text-xs">MK</AvatarFallback>
            </Avatar>
            <Avatar className="ring-background ring-2">
                <AvatarFallback className="text-xs">RA</AvatarFallback>
            </Avatar>
            <Avatar className="ring-background ring-2">
                <AvatarFallback className="text-xs">+3</AvatarFallback>
            </Avatar>
        </div>
    </Surface>
);

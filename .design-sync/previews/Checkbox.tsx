import { Checkbox, Label } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

export const States = () => (
    <Surface>
        <div className="flex items-center gap-6">
            <Checkbox aria-label="Unchecked" />
            <Checkbox defaultChecked aria-label="Checked" />
            <Checkbox disabled aria-label="Disabled" />
            <Checkbox disabled defaultChecked aria-label="Disabled checked" />
        </div>
    </Surface>
);

/** Checkbox is almost always paired with a Label — that's the clickable row. */
export const WithLabel = () => (
    <Surface>
        <div className="flex items-center gap-2">
            <Checkbox id="recurring" defaultChecked />
            <Label htmlFor="recurring">This bill repeats monthly</Label>
        </div>
    </Surface>
);

export const CategoryFilter = () => (
    <Surface>
        <div className="flex flex-col gap-3">
            <p className="text-sm font-medium">Include categories</p>
            <div className="flex items-center gap-2">
                <Checkbox id="c-groceries" defaultChecked />
                <Label htmlFor="c-groceries">Groceries</Label>
            </div>
            <div className="flex items-center gap-2">
                <Checkbox id="c-transport" defaultChecked />
                <Label htmlFor="c-transport">Transport</Label>
            </div>
            <div className="flex items-center gap-2">
                <Checkbox id="c-utilities" />
                <Label htmlFor="c-utilities">Utilities</Label>
            </div>
            <div className="flex items-center gap-2">
                <Checkbox id="c-archived" disabled />
                <Label htmlFor="c-archived">Archived (none this month)</Label>
            </div>
        </div>
    </Surface>
);

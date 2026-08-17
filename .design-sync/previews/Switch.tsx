import { Label, Switch } from '@cashpilot/ui';

const Surface = ({ children }: { children: React.ReactNode }) => (
    <div className="dark bg-background text-foreground p-6">{children}</div>
);

/**
 * Switch is the one control painted with the CashPilot brand green directly
 * (#02CD86) instead of a semantic token, so the checked state is the same
 * colour in every theme.
 */
export const States = () => (
    <Surface>
        <div className="flex items-center gap-6">
            <Switch aria-label="Off" />
            <Switch defaultChecked aria-label="On" />
            <Switch disabled aria-label="Disabled off" />
            <Switch disabled defaultChecked aria-label="Disabled on" />
        </div>
    </Surface>
);

export const WithLabel = () => (
    <Surface>
        <div className="flex items-center gap-3">
            <Switch id="autopay" defaultChecked />
            <Label htmlFor="autopay">Autopay this bill</Label>
        </div>
    </Surface>
);

export const SettingsRows = () => (
    <Surface>
        <div className="flex w-full max-w-sm flex-col gap-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium">Bill reminders</p>
                    <p className="text-muted-foreground text-sm">Notify me 3 days before</p>
                </div>
                <Switch defaultChecked aria-label="Bill reminders" />
            </div>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium">Weekly digest</p>
                    <p className="text-muted-foreground text-sm">Spending summary each Friday</p>
                </div>
                <Switch aria-label="Weekly digest" />
            </div>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium">Round-up savings</p>
                    <p className="text-muted-foreground text-sm">Not available on this plan</p>
                </div>
                <Switch disabled aria-label="Round-up savings" />
            </div>
        </div>
    </Surface>
);

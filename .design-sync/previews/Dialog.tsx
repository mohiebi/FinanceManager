import {
    Button,
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    Input,
    Label,
} from '@cashpilot/ui';

/** DialogContent is portalled — see the note in Select.tsx. */
if (typeof document !== 'undefined') {
    document.documentElement.classList.add('dark');
}

/**
 * Rendered open (`defaultOpen`) so the panel is visible statically. The panel
 * carries fixed CashPilot chrome — #1a1a1a, a white/10 ring, 25px corners — so
 * it does not depend on the surrounding `.dark` class the way tokens do.
 */
export const ConfirmDelete = () => (
    <div className="dark bg-background text-foreground" style={{ minHeight: 400 }}>
        <Dialog defaultOpen modal={false}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this bill?</DialogTitle>
                    <DialogDescription>
                        “Electricity — Tavanir” and its 14 months of payment history will be
                        removed. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive">Delete bill</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
);

export const FormDialog = () => (
    <div className="dark bg-background text-foreground" style={{ minHeight: 400 }}>
        <Dialog defaultOpen modal={false}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New savings goal</DialogTitle>
                    <DialogDescription>
                        Goals are denominated in asset units — grams of gold, dollars — not
                        toman.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="dlg-goal">Goal name</Label>
                        <Input id="dlg-goal" defaultValue="Emergency fund" />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="dlg-target">Target</Label>
                        <Input id="dlg-target" type="number" defaultValue={50} />
                    </div>
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Cancel</Button>
                    </DialogClose>
                    <Button>Create goal</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
);

# Building with the CashPilot UI kit

CashPilot is a personal-finance app (transactions, bills, budgets, savings
goals, investments). This kit is a React port of its shipped Vue components —
same class strings, same tokens.

## Setup

**This kit is dark-only.** The app calls `initializeTheme()`, which adds `dark`
to `<html>` unconditionally; there is no light mode and no theme toggle. Put
the class on the document root, not on a wrapper div:

```jsx
document.documentElement.classList.add('dark');
```

That placement is load-bearing, not cosmetic. `Select`, `Tooltip` and `Dialog`
portal their floating parts to `document.body`. If `dark` sits on an inner
`<div>`, those portals fall outside it, resolve `bg-popover` against the light
`:root` tokens, and render as **white panels on a dark page**.

`Tooltip` additionally requires a `TooltipProvider` ancestor — it throws
without one. Everything else needs no provider.

## Styling idiom

Tailwind utility classes, exactly as the app writes them. The stylesheet ships
a broad utility layer, so use normal Tailwind for your own layout — grid,
flex, spacing, type scale, responsive `sm:`/`md:`/`lg:`, and `hover:`/`focus-visible:`
variants all resolve.

Colors come from **semantic tokens**, never raw palette values:

| Family | Names |
|---|---|
| Surface | `bg-background`, `bg-card`, `bg-popover`, `bg-muted`, `bg-sidebar` |
| Text | `text-foreground`, `text-card-foreground`, `text-muted-foreground`, `text-primary-foreground` |
| Accent | `bg-primary`, `bg-secondary`, `bg-accent`, `text-destructive`, `bg-destructive` |
| Lines | `border-border`, `border-input`, `ring-ring` |
| Charts | `text-chart-1` … `text-chart-5` |
| Brand | `bg-auth-success` (#02cd86 green), `bg-auth-accent` (#6c4ee9 purple), `text-auth-copy`, `bg-auth-surface` |

Note `--primary` inverts in dark: it is near-white, so a default `Button` is a
white pill with dark text. That is correct, not a bug.

Use `tabular-nums` on every currency figure so digits align in columns.
Amounts are toman by default (`124,500,000 T`); usd and eur also exist.

**Three components ignore the tokens** and paint fixed chrome, so they look
identical regardless of surrounding classes — do not try to re-theme them:
`Dialog` (`#1a1a1a`, 25px radius), `DropdownMenu` (translucent `#1f1f1f` with
backdrop blur), and `Switch` (brand green `#02CD86` when on).

The app is bilingual and supports RTL (`html[dir="rtl"]` swaps the font to
Vazirmatn). Prefer logical spacing and let `rtl:` variants handle direction.

## Where the truth is

Read `_ds/<folder>/styles.css` and its `@import` closure for the real token
values, and each component's `<Name>.prompt.md` for its props. Those beat any
summary here.

## Idiomatic example

```jsx
<Card className="w-full max-w-sm">
  <CardHeader>
    <CardTitle>Groceries budget</CardTitle>
    <CardDescription>18 of 30 days elapsed</CardDescription>
    <CardAction>
      <Button variant="ghost" size="icon-sm" aria-label="Options">
        <MoreHorizontal />
      </Button>
    </CardAction>
  </CardHeader>
  <CardContent>
    <p className="text-2xl font-semibold tabular-nums">3,200,000 T</p>
    <p className="text-muted-foreground text-sm">of 5,000,000 T allowance</p>
  </CardContent>
</Card>
```

`CardAction` is what turns `CardHeader` into a two-column grid — without it the
header stays single-column. `Button` has an unusual size set: alongside `sm` /
`default` / `lg` it offers three square icon sizes, `icon-sm` / `icon` /
`icon-lg`.

## Composition notes

- Icons: `lucide-react`, sized automatically to `size-4` inside `Button` and
  `DropdownMenuItem`.
- `Input` takes its error state from `aria-invalid` — no error class needed.
- `Separator` with `orientation="vertical"` needs a height from its flex
  parent, or it collapses to nothing.
- `Alert` opens an icon column only when an icon is its first child.
- `DropdownMenuItem` takes `variant="destructive"` for delete rows.

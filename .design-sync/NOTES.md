# design-sync notes — CashPilot

## What is being synced

The app in this repo is **Laravel + Inertia + Vue**. claude.ai/design consumes
**React** only (`_ds_bundle.js` exposes components on `window.*`, preview cards
are `.jsx`). So the synced design system is **not** `resources/js/components/ui`
directly — it is `design-system/cashpilot-react/`, a React port of those Vue
SFCs kept deliberately 1:1.

**The port is the thing that can rot.** `resources/js/components/ui/**` is the
source of truth; the React package is a mirror. If someone changes a Vue
component's classes and nobody re-ports it, the synced kit silently drifts.

### How the port was made

The Vue kit is **shadcn-vue on `reka-ui`**; the React port is the same
components on **Radix**. Because shadcn-vue is itself a port of shadcn/ui, the
Tailwind class strings transfer verbatim — that is what makes the mirror cheap
and faithful. Deliberate deviations, all of them forced:

- **`--reka-*` → `--radix-*` CSS variables.** `SelectContent` and
  `DropdownMenuContent` size themselves from
  `--reka-select-content-available-height`,
  `--reka-select-trigger-height/width`, and
  `--reka-dropdown-menu-content-available-height/transform-origin`. Radix emits
  the same variables under a `--radix-` prefix. Miss these and the popovers
  lose their height cap and trigger-width matching.
- **`vue-i18n` close labels → props.** `DialogContent` / `DialogScrollContent`
  called `useI18n()` for `buttons.close`. The React port takes a `closeLabel`
  prop defaulting to `"Close"`. Any consumer wanting Farsi must pass it.
- **v-model → React controlled/uncontrolled props.** `Input`, `Checkbox` and
  `Switch` dropped their `update:modelValue` / `update:checked` emit pairs for
  standard React `value`/`checked`/`defaultChecked`.

### Not ported (scope of this sync)

Ported: Alert, Avatar, Badge, Button, Card, Checkbox, Collapsible, Dialog,
DropdownMenu, Input, Label, Select, Separator, Skeleton, Spinner, Switch,
Tooltip — 65 exported components.

**Not** ported: `breadcrumb`, `input-otp`, `navigation-menu`, `sheet`,
`sidebar`. `sidebar` is the big one (815 lines / 26 files) and is heavily
coupled to the app shell (mobile Sheet, Inertia links, cookie-persisted state);
it needs its own decision before porting.

## Gotchas this repo will hit again

- **The repo-root `tsconfig.json` hijacks preview JSX.** It belongs to the Vue
  app and sets `"jsxImportSource": "vue"`. esbuild resolves `jsxImportSource`
  by walking up from each preview `.tsx`, so previews compiled against
  `vue/jsx-runtime` and every card died with *"Objects are not valid as a React
  child (found: object with keys {__v_isVNode, …})"*. Fix: **`.design-sync/tsconfig.json`**
  exists purely to win that walk. Do not delete it.
  `cfg.tsconfig` does **not** solve this — it is resolved *package*-relative
  (`resolve(PKG_DIR, cfg.tsconfig)`) and only feeds a path-alias plugin.
- **The shipped CSS must carry a utility layer.** A rendered design receives
  only the transitive `@import` closure of `styles.css`. Scanning `../src`
  alone emits just the utilities the components themselves use, so anything an
  author writes for their own layout (`grid-cols-3`, `text-3xl`, `gap-3`,
  `tabular-nums`) silently resolves to nothing — designs collapse into one
  stacked column with no visible error. `styles/app.css` therefore carries a
  large `@source inline(...)` safelist. **Do not trim it** without re-checking
  that authored layouts still work. It is most of the 721 KB stylesheet.
- **Arbitrary Tailwind values in previews need a CSS rebuild first.** The CSS
  is built by a separate command from the bundle, so a preview using
  `min-h-[380px]` written *after* the last `build:css` gets a class that does
  not exist. Preview scaffolding uses inline `style={{ minHeight: … }}` instead
  to remove the ordering dependency.
- **Portalled surfaces need `dark` on `<html>`, not on a wrapper div.** Radix
  portals `SelectContent` / `TooltipContent` / `DialogContent` to
  `document.body`. With `.dark` on an inner wrapper the portal sits outside it
  and resolves `bg-popover` against the light `:root` tokens — a white panel on
  a dark page. The app avoids this in `initializeTheme()`; the overlay previews
  mirror it with a module-scope
  `document.documentElement.classList.add('dark')`.
  `DropdownMenuContent` masks the bug because its chrome is hardcoded dark.
- **Fonts are remote by design.** `Instrument Sans` and `Vazirmatn` are served
  from `fonts.bunny.net` via `<link>` tags in `resources/views/app.blade.php`;
  there are no font files in the repo. `styles/app.css` opens with a remote
  `@import`, which is why validate reports `[FONT_REMOTE]` (informational) and
  not `[FONT_MISSING]`. "Cambria" in that list comes from Tailwind's default
  serif stack — not a CashPilot font, ignore it.

## Known render warns (triaged, expected — not new)

- `[FONT_REMOTE] "Instrument Sans", "Vazirmatn", "Cambria"` — by design, above.
- `tokens: N defined, M referenced (2 missing, below threshold)` — below the
  converter's own threshold, not chased.
- 48 components ship the typographic **floor card**. That is deliberate, not a
  failure: they are sub-parts (`CardHeader`, `SelectItem`, `DialogFooter`, …)
  that only make sense inside a parent, and every one of them is fully
  importable and functional. The 17 composable units carry authored previews.

## Re-sync risks

- **Drift between the Vue kit and the React port is invisible to this
  pipeline.** Nothing checks them against each other. Before a re-sync, diff
  `resources/js/components/ui/**` against `design-system/cashpilot-react/src/**`
  — especially the `cva` blocks and any hardcoded hex.
- **Hardcoded brand hex lives in three components.** `Switch` (`#02CD86`,
  `#1a1a1a`, `white/15`), `DialogContent` / `DialogScrollContent` (`#1a1a1a`,
  `rounded-[25px]`, `z-[300]`/`z-[310]`), `DropdownMenuContent` +
  `DropdownMenuItem` (`#1f1f1f/95`, `#E94E50`). These are **not** token-driven,
  so a token change will not move them and a theme switch will not either.
- **`design-system/cashpilot/MASTER.md` is stale and contradicts the code.** It
  claims an indigo `#4F46E5` palette on a light `#EEF2FF` background with Fira
  Code / Fira Sans. The shipped tokens are green `#02cd86` + purple `#6c4ee9`
  on `#111111`/`#1a1a1a`, in Instrument Sans / Vazirmatn. It also has literal
  CSV rows corrupting its "Page Pattern" section (lines 169–171) and lists
  "Light backgrounds" as an anti-pattern while specifying a light background.
  **Do not feed MASTER.md to the design agent** — it is not wired into this
  sync, and that is intentional.
- **The kit is dark-only.** `useAppearance()` returns `'dark' as const` and
  `initializeTheme()` adds `dark` unconditionally. The light `:root` token block
  still exists and still compiles, so a light-mode design will *render* — it
  just is not a thing CashPilot ships. Previews all assume dark.
- **Build order matters**: `npm run build` inside
  `design-system/cashpilot-react` runs `build:js` then `build:css`. Run the
  package build before `package-build.mjs`, or the converter bundles a stale
  `dist/`.

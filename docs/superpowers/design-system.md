# Terminal Horizon — admin design system

The visual direction for the Inertia panel, produced in Google Stitch and pinned
down here. This file is the contract: when a component looks wrong, this is what
"right" means.

**Where it lives in code**

| Concern | File |
| --- | --- |
| Accent ramps (9 accents) | `resources/js/theme/ramps.js`, mirrored in `app/Support/Theme/AccentRamps.php` |
| Surface, ink and status tokens | `resources/js/theme/tokens.js` |
| PrimeVue preset | `resources/js/theme/preset.js` |
| Tailwind tokens and base layer | `resources/css/app.css` |

`AccentRampsTest` fails if the JS and PHP ramps drift apart.

## Personality

Quiet, technical, strictly functional. A developer's instrument, not enterprise
SaaS. No celebratory animation, no gamified badges, no high-saturation warnings.
Visual density is high *inside* modules and framed by generous outer margin.

Three rules carry most of the look:

1. **Hairlines, not shadows.** Depth comes from 1px borders and flat planar
   nesting. No `box-shadow`, no `backdrop-filter`, no gradients, no glow.
2. **One accent, used sparingly.** Khaki marks primary actions, active states and
   selection. Everything else is neutral.
3. **Monospace for machine values.** Every number, ID, date, hash and currency is
   `JetBrains Mono` with `tabular-nums`. Prose and controls are `Inter`.

## Colour

Dark is the primary scheme; light is derived.

### Dark

| Token | Value | Use |
| --- | --- | --- |
| canvas | `#0D0E11` | Page background, the structural floor |
| surface | `#15171C` | Cards, tables, panels |
| elevated | `#1A1D24` | Popovers, dropdowns, modals, hovered rows |
| embedded | `#121317` | Inputs, code blocks, terminal readouts |
| hairline | `#272B35` | Card and input borders, outer boundaries |
| hairline-subtle | `#20232B` | Table row dividers, internal partitions |
| ink | `#ECEEF2` | Headings, metrics, primary values |
| ink-muted | `#9096A2` | Body context, column headers, secondary labels |
| ink-faint | `#5A606E` | Placeholders, disabled controls, timestamps |

### Accent

**Khaki `#C9BE6E`** by default, selectable from Settings across nine accents.
Used only for primary buttons, active tab and nav states, selected rows, focus
borders and checked controls.

**The khaki rule:** anything filled with the accent takes `#141517` for its text
and glyphs. **Never white.** `#C9BE6E` with white text is roughly 1.9:1 and
fails; with `#141517` it is roughly 11:1. This is why the preset sets
`primary.contrastColor` explicitly in both schemes rather than inheriting.

### Status

Outside the accent ramp, because status must not move when the accent changes.

| State | Colour | Meaning |
| --- | --- | --- |
| nominal | `#529E72` | Healthy, public, paid, active, cleared |
| warning | `#C9BE6E` | Needs attention, private, due within 7 days, pending |
| alert | `#D95757` | Overdue, failed, destructive |
| muted | `#5A606E` | Inactive, stale, not applicable |

Badge semantics per enum:

| Variant | Used by |
| --- | --- |
| primary | Category with a colour set |
| success | Repository `public`, debt `paid`, repository active |
| warning | Repository `private`, debt due soon, expense currency |
| danger | Overdue debt, outgoing money |
| info | Creditor type `person` |
| secondary | Uncategorised, unknown, fallback |

## Typography

| Role | Family | Size / weight | Notes |
| --- | --- | --- | --- |
| Page title | Inter | 24px / 600 | `-0.015em` tracking |
| Section heading | Inter | 18px / 500 | `-0.01em` |
| Body, table cells | Inter | 13–15px / 400 | |
| Label caps | Inter | 11px / 600 | uppercase, `0.06em`, `ink-muted` |
| Metric readout | JetBrains Mono | 26px / 500 | `-0.03em`, KPI tiles |
| Data | JetBrains Mono | 13px / 400 | amounts, IDs, counts |
| Meta | JetBrains Mono | 11px / 400 | timestamps, issue numbers, checksums |

Column headers and form field labels use label-caps. Timestamps always use mono
meta so rows stay vertically aligned.

## Layout

Centred canvas capped at **1280px**. Horizontal top navigation with five cluster
dropdowns — Blog, Budget, Work, Library, General — not a sidebar. 8px baseline
rhythm, 4px micro-increments inside toolbars and table cells.

- Desktop ≥1024px: 1280px wrapper, uncollapsed table columns.
- Tablet 768–1023px: `2rem` margins, metric grids 4-across becomes 2-across,
  dense tables scroll horizontally.
- Mobile <768px: `1rem` margin, single column stack.

## Shape

- **4px** — buttons, inputs, tags, badges, menu items.
- **8px** — cards, table wrappers, modals, layout panels.
- Inner elements nested in an 8px container drop to 4px for concentricity.
- Circles only for status dots and avatars. **No pill buttons.**

## Components

**Buttons.** Primary: khaki fill, `#141517` text at 600, 1px border same colour,
hover `#B8AD5C`. Secondary: surface fill, ink text, hairline border; hover goes
elevated with an `ink-muted` border. Ghost: transparent, `ink-muted` text.
Heights 32px default, 26px compact. 13px label.

**Inputs.** Embedded background, hairline border, 32px high, 4px radius.
Focus swaps the border to the accent — **no outer ring, no offset, no glow.**

**Tables.** 32px header row, 36px body rows, `8px 12px` cell padding. Row divider
`hairline-subtle`, outer boundary `hairline`. **No zebra striping.** Hover fills
the row `elevated`. Text left-aligned in Inter; numbers, IDs, dates and currency
right-aligned in mono.

**Cards and metrics.** Surface fill, hairline border, 8px radius, 16px padding.
Title in label-caps `ink-muted`; readout in mono-metric `ink`; sub-context in
mono-meta `ink-faint`.

**Tags and status pills.** Tags: 20px high, mono 11px, `0 6px` padding, 3px
radius, elevated fill, hairline border, `ink-muted` text. Status pills: 6px dot
plus mono 11px label in the status colour.

## States

- **Empty table** — centred, 48px vertical padding, `ink-muted`, one sentence.
- **Loading** — Inertia's progress bar only. No skeletons, no spinners in tables.
- **Destructive actions** — always a confirm dialog naming what is being deleted.
- **Form errors** — inline under the field, 12px, alert colour. No summary banner.
- **Unsaved changes** — a quiet status pill in the header, not a blocking modal.

## What not to do

- No `box-shadow` or `backdrop-filter` anywhere.
- No white text on khaki.
- No zebra-striped tables.
- No fully rounded pill buttons.
- No accent colour on anything that is not interactive or selected.
- No proportional figures in a column of numbers.

---
name: Terminal Horizon
colors:
  surface: '#111318'
  surface-dim: '#111318'
  surface-bright: '#37393f'
  surface-container-lowest: '#0c0e13'
  surface-container-low: '#1a1b21'
  surface-container: '#1e2025'
  surface-container-high: '#282a2f'
  surface-container-highest: '#33353a'
  on-surface: '#e2e2e9'
  on-surface-variant: '#ccc6b4'
  inverse-surface: '#e2e2e9'
  inverse-on-surface: '#2e3036'
  outline: '#959180'
  outline-variant: '#4a4739'
  surface-tint: '#d4c877'
  primary: '#e6da87'
  on-primary: '#363100'
  primary-container: '#c9be6e'
  on-primary-container: '#544c05'
  inverse-primary: '#685f19'
  secondary: '#c3c6d3'
  on-secondary: '#2c303a'
  secondary-container: '#454954'
  on-secondary-container: '#b5b8c5'
  tertiary: '#d6d8e1'
  on-tertiary: '#2d3038'
  tertiary-container: '#babcc5'
  on-tertiary-container: '#494c54'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#f0e490'
  primary-fixed-dim: '#d4c877'
  on-primary-fixed: '#201c00'
  on-primary-fixed-variant: '#4f4801'
  secondary-fixed: '#dfe2ef'
  secondary-fixed-dim: '#c3c6d3'
  on-secondary-fixed: '#171b25'
  on-secondary-fixed-variant: '#434751'
  tertiary-fixed: '#e0e2ec'
  tertiary-fixed-dim: '#c4c6d0'
  on-tertiary-fixed: '#191c22'
  on-tertiary-fixed-variant: '#44474f'
  background: '#111318'
  on-background: '#e2e2e9'
  surface-variant: '#33353a'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 24px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 22px
  body-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  mono-metric-lg:
    fontFamily: JetBrains Mono
    fontSize: 26px
    fontWeight: '500'
    lineHeight: 32px
    letterSpacing: -0.03em
  mono-metric-md:
    fontFamily: JetBrains Mono
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 24px
    letterSpacing: -0.02em
  mono-data:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  mono-meta:
    fontFamily: JetBrains Mono
    fontSize: 11px
    fontWeight: '400'
    lineHeight: 14px
    letterSpacing: 0.02em
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.06em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.25rem
  margin: 1rem
  margin-tablet: 2rem
  margin-desktop: 3rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

This design system is tailored for a single-operator, self-hosted operational dashboard. It balances utilitarian developer tooling aesthetics with calculated editorial restraint. The design language is quiet, highly technical, and strictly functional—dispensing with visual noise, exaggerated lighting, skeuomorphic illusions, and decorative illustrations.

### Personality & Tone
- **Calm & Dispassionate:** The interface treats user data with monastic gravity. There are no celebratory animations, gamified badges, or high-saturation warnings.
- **Instrument-Grade Precision:** Every structural line, alignment, and coordinate serves orientation and comprehension. Visual density is high within modules, balanced by controlled outer margins.
- **Sovereign & Direct:** Emphasizes local control, complete data legibility, and deterministic interaction.

### Aesthetic Movement
The aesthetic synthesizes **Technical Minimalism** and **Modern Monastic Utility**:
- Strict 1px hairline framework for surfaces and partitions.
- Zero decorative gradients, drop shadows, or luminescent glows.
- Intentional typographical duality: cold, structural sans-serif for functional controls paired with tabular monospaced numbers and metadata.

## Colors

The palette operates under low-light parameters, prioritizing long-session optical comfort and unambiguous hierarchy.

### Background Tiering
- **Page Canvas (`#0D0E11`):** The foundational void. Used for the root canvas, gutter backings, and global viewport limits.
- **Card & Base Surface (`#15171C`):** Standard container surface. Houses data tables, card grids, panels, and side rails.
- **Elevated Surface / Hover State (`#1A1D24`):** Secondary structural tier. Used for popovers, flyouts, and active or hovered rows.
- **Embedded Surface (`#121317`):** Recessed canvas. Strictly applied to input fields, code preview blocks, and terminal-style inline readouts.

### Accent & Highlights
- **Active Khaki (`#C9BE6E`):** The solitary focal accent. Reserved strictly for primary call-to-actions, active tab underlines, selected state indicators, and critical toggle states. Never apply glow or blur beneath this tone.
- **Khaki Interaction Rule:** All interactive elements filled with `#C9BE6E` must enforce `#141517` for text, glyphs, and iconography. White typography on khaki fill is prohibited to preserve legibility and contrast.

### Structural Hairlines
- **Primary Border (`#272B35`):** Structural partition token for cards, structural separators, and input framing.
- **Subtle Partition (`#20232B`):** Internal table row dividers, quiet category boundaries, and nested list items.

### Content Values
- **Primary Readout (`#ECEEF2`):** Primary headings, metrics, titles, and critical values.
- **Secondary Data (`#9096A2`):** Body context, active column headers, and secondary labels.
- **Muted Metadata (`#5A606E`):** Inactive controls, placeholder text, table borders, and structural timestamps.

## Typography

The type system enforces a clear split between prose controls and raw machine telemetry:

1. **System Sans (`Inter`):** Governs human interface navigation, modal headers, field descriptions, button labels, and instructional microcopy. Employs tight letter tracking at headline scales to avoid loose visual weight.
2. **System Monospace (`JetBrains Mono`):** Strictly enforced across all computed data points, financial balances, hardware resource states, database IDs, network latency measurements, IP allocations, timestamps, and log feeds. Monospace elements utilize tabular alignment numbers (`tnum`) by default.

### Application Rules
- **Numerical Primacy:** Any metric or aggregate count presented in a KPI block must be rendered using `mono-metric-lg` or `mono-metric-md`.
- **Labels & Headers:** Section tags, column sort headers, and form field titles apply `label-caps` in uppercase styling with muted contrast (`#9096A2`).
- **Dates & Latency:** Timestamps throughout lists must strictly use `mono-meta` to maintain vertical grid alignment.

## Layout & Spacing

The dashboard is structured around an anchored, centered canvas capped at a maximum width of `1280px`. This maintains compact, gaze-efficient scanning lines on ultra-wide desktop monitors without spreading control widgets across extreme edges.

### Layout Rhythm
- **The Outer Canvas:** Uses generous outer margins (`margin-desktop: 3rem`) to frame the high-density work plane inside negative space.
- **The Grid:** A 12-column responsive fluid grid inside the 1280px container, utilizing a consistent `1.25rem` (20px) desktop gutter.
- **Component Geometry:** Interior layouts utilize an 8px baseline rhythm, with dense 4px micro-increments (`space-xs`) for inline tags, table cell gaps, and toolbars.

### Responsive Breakpoints
- **Desktop (>= 1024px):** Strict 1280px centered wrapper. 12-column layout. Left vertical utility rail (64px collapsed or 220px fixed). Data tables showcase uncollapsed columns with explicit pixel widths.
- **Tablet (768px - 1023px):** Fluid width with `2rem` margins. 6-column layout. Metric card grids adapt from 4-across to 2-across. Dense tables initiate horizontal scroll on overflow.
- **Mobile (< 768px):** Fluid width with `1rem` margin. 1-column stack. Utility navigation shifts to a persistent, hair-lined bottom toolbar. Numerical values maintain full character lengths via horizontal pan or truncation with tap-to-expand.

## Elevation & Depth

This design system avoids all drop shadows, ambient blur radiuses, and diffuse illumination layers. Depth is constructed entirely through flat planar nesting and high-precision boundaries.

### Planar Hierarchy
- **Level 0 (Canvas):** `#0D0E11` serves as the underlying structural floor.
- **Level 1 (Panels & Cards):** `#15171C` raised from Level 0 exclusively via a crisp `1px solid #272B35` perimeter border.
- **Level 2 (Active/Hover Containers):** `#1A1D24` indicating interactive hover focus, nested module containers, or side drawers. Framed with `1px solid #272B35`.
- **Level 3 (Overlays & Context Menus):** `#1A1D24` backed by `1px solid #C9BE6E` (subtle accent indicator) or `1px solid #272B35`. Floating menus do not cast drop shadows; their edges contrast directly against the underlying darker layers.

### Framing Precision
- Never apply blurred backdrops (`backdrop-filter`) under panels. Layer transitions must be completely opaque to maintain high-density data legibility and avoid GPU rendering overhead.
- All structural dividers are exact 1px lines using `#20232B` (internal) or `#272B35` (external boundaries).

## Shapes

The design system uses soft, restrained corner treatments (Radius Level 1). Components use micro-radii to preserve a structured, technical contour without the harshness of completely raw 90-degree corners.

### Shape Geometry Rules
- **Base Components (`rounded` / 0.25rem / 4px):** Standard buttons, input controls, category tags, notification badges, dropdown menus, and list hover hitboxes.
- **Container Elements (`rounded-lg` / 0.5rem / 8px):** Dashboard metric cards, data table wrappers, modal windows, and primary layout panels.
- **Inner Nested Corners:** When an input or sub-card sits within a `rounded-lg` container separated by `0.5rem` padding, the inner element must use the base `rounded` (4px) setting to maintain geometric concentricity.
- **Circular Elements:** Reserved strictly for status pings (e.g., green/red uptime LEDs) and avatar markers. Fully rounded pill buttons (`rounded-full`) are prohibited.

## Components

### Buttons
- **Primary Button:** Background `#C9BE6E`, text `#141517` (`font-weight: 600`), 0 drop shadow, border `1px solid #C9BE6E`. On hover, background shifts to `#B8AD5C`. Active click state scales down opacity slightly (0.95).
- **Secondary Button:** Background `#15171C`, text `#ECEEF2`, border `1px solid #272B35`. Hover transitions background to `#1A1D24` and border to `#9096A2`.
- **Tertiary / Ghost Button:** Background transparent, text `#9096A2`, border 1px transparent. Hover sets background to `#15171C` and text to `#ECEEF2`.
- **Sizing:** Fixed vertical heights of 32px (default) and 26px (compact micro-action). Padding: horizontal 12px (`space-md`), font size `13px`.

### Input Fields & Controls
- **Text Inputs:** Background `#121317`, border `1px solid #272B35`, text `#ECEEF2`, placeholder `#5A606E`, border-radius 4px, height 32px.
- **Focus State:** 1px solid border `#C9BE6E`. Zero outer glow or focus ring offset. The single pixel border swap indicates focus cleanly.
- **Checkboxes & Radios:** 14px x 14px boxes. Background `#121317`, border `1px solid #272B35`, border-radius 2px (checkbox) or circular (radio). When checked: Background `#C9BE6E`, icon/dot `#141517`, border `#C9BE6E`.

### Cards & Metrics
- **Card Container:** Background `#15171C`, border `1px solid #272B35`, border-radius 8px, padding `1rem` (16px).
- **Metric Header:** Upper row holds the metric title using `label-caps` in `#9096A2` paired with an optional top-right quiet status indicator.
- **Metric Readout:** Employs `mono-metric-lg` in `#ECEEF2`. Sub-context (e.g., "+2.4% last week" or "38ms") uses `mono-meta` in `#5A606E`.

### Data Tables & Lists
- **Table Structure:** Clean, zero-shadow block. Header row height 32px, cell padding `8px 12px`, border-bottom `1px solid #272B35`.
- **Row Rows:** Height 36px (dense). Alternating zebra fills are prohibited. Hovering over a row shifts background instantly to `#1A1D24`. Border-bottom between rows is `1px solid #20232B`.
- **Column Alignments:** Text left-aligned (`Inter`). Numerical data, IDs, hashes, dates, and currency right-aligned (`JetBrains Mono`).

### Category Tags & Status Pills
- **Category Tags:** Height 20px, font `JetBrains Mono` (11px), padding `0 6px`, border-radius 3px, background `#1A1D24`, border `1px solid #272B35`, text `#9096A2`.
- **Status Pills (Live/Operational):** Inline flex container. Height 18px. Dot indicator (6px solid circle) + label text in `JetBrains Mono` 11px.
  - Active: Dot `#C9BE6E`, text `#C9BE6E`.
  - Nominal: Dot `#529E72`, text `#9096A2`.
  - Inactive/Stale: Dot `#5A606E`, text `#5A606E`.
  - Alert: Dot `#D95757`, text `#D95757`.

### Terminal / Log Viewers
- Full-width embedded container inside cards.
- Background `#0D0E11`, border `1px solid #20232B`, border-radius 4px, padding 12px.
- Text `JetBrains Mono` 12px, line-height 18px, font color `#9096A2`.
- Line numbers set in `#5A606E` with vertical boundary line `#20232B`.
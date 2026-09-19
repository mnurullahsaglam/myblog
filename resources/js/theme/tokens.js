/**
 * Terminal Horizon - the design direction produced in Stitch and pinned down in
 * docs/superpowers/design-system.md. Dark-first, one khaki accent, 1px hairlines,
 * no shadows or gradients, Inter for prose and JetBrains Mono for every number.
 */
export const TOKENS = {
  dark: {
    canvas: '#0D0E11',
    surface: '#15171C',
    elevated: '#1A1D24',
    embedded: '#121317',
    borderStrong: '#272B35',
    borderSubtle: '#20232B',
    textPrimary: '#ECEEF2',
    textSecondary: '#9096A2',
    textMuted: '#5A606E',
  },
  light: {
    canvas: '#F7F7F5',
    surface: '#FFFFFF',
    elevated: '#F2F2EF',
    embedded: '#FAFAF8',
    borderStrong: '#D9D9D2',
    borderSubtle: '#E8E8E2',
    textPrimary: '#1A1B1E',
    textSecondary: '#5A606E',
    textMuted: '#8B909B',
  },
}

/** Text and glyphs on a khaki fill. Never white. */
export const ON_ACCENT = '#141517'

/** Status colours for pills and badges, outside the accent ramp. */
export const STATUS = {
  nominal: '#529E72',
  alert: '#D95757',
  warning: '#C9BE6E',
  muted: '#5A606E',
}

export const FONTS = {
  sans: "'Inter', ui-sans-serif, system-ui, sans-serif",
  mono: "'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace",
}

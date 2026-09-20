import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import { RAMPS } from './ramps'
import { ON_ACCENT, TOKENS } from './tokens'

const dark = TOKENS.dark
const light = TOKENS.light

export default definePreset(Aura, {
  semantic: {
    primary: RAMPS.khaki,

    borderRadius: {
      none: '0',
      xs: '2px',
      sm: '3px',
      md: '4px',
      lg: '8px',
      xl: '12px',
    },

    focusRing: {
      width: '1px',
      style: 'solid',
      color: '{primary.color}',
      offset: '0',
      shadow: 'none',
    },

    colorScheme: {
      light: {
        primary: {
          color: '{primary.500}',
          contrastColor: ON_ACCENT,
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}',
        },
        content: {
          background: light.surface,
          hoverBackground: light.elevated,
          borderColor: light.borderStrong,
          color: light.textPrimary,
          hoverColor: light.textPrimary,
        },
        text: {
          color: light.textPrimary,
          hoverColor: light.textPrimary,
          mutedColor: light.textSecondary,
          hoverMutedColor: light.textPrimary,
        },
        overlay: {
          select: { background: light.surface, borderColor: light.borderStrong, color: light.textPrimary },
          popover: { background: light.surface, borderColor: light.borderStrong, color: light.textPrimary },
          modal: { background: light.surface, borderColor: light.borderStrong, color: light.textPrimary },
        },
        formField: {
          background: light.embedded,
          disabledBackground: light.elevated,
          filledBackground: light.embedded,
          borderColor: light.borderStrong,
          hoverBorderColor: light.textMuted,
          focusBorderColor: '{primary.500}',
          color: light.textPrimary,
          placeholderColor: light.textMuted,
        },
      },

      dark: {
        primary: {
          color: '{primary.400}',
          contrastColor: ON_ACCENT,
          hoverColor: '{primary.300}',
          activeColor: '{primary.200}',
        },
        content: {
          background: dark.surface,
          hoverBackground: dark.elevated,
          borderColor: dark.borderStrong,
          color: dark.textPrimary,
          hoverColor: dark.textPrimary,
        },
        text: {
          color: dark.textPrimary,
          hoverColor: dark.textPrimary,
          mutedColor: dark.textSecondary,
          hoverMutedColor: dark.textPrimary,
        },
        overlay: {
          select: { background: dark.elevated, borderColor: dark.borderStrong, color: dark.textPrimary },
          popover: { background: dark.elevated, borderColor: dark.borderStrong, color: dark.textPrimary },
          modal: { background: dark.elevated, borderColor: dark.borderStrong, color: dark.textPrimary },
        },
        formField: {
          background: dark.embedded,
          disabledBackground: dark.elevated,
          filledBackground: dark.embedded,
          borderColor: dark.borderStrong,
          hoverBorderColor: dark.textSecondary,
          focusBorderColor: '{primary.400}',
          color: dark.textPrimary,
          placeholderColor: dark.textMuted,
        },
      },
    },
  },

  components: {
    datatable: {
      headerCell: {
        padding: '0.5rem 0.75rem',
        background: 'transparent',
      },
      bodyCell: { padding: '0.5rem 0.75rem' },
      footerCell: { padding: '0.5rem 0.75rem' },
      paginatorTop: { borderWidth: '0' },
      paginatorBottom: { borderWidth: '1px 0 0 0' },
    },
    button: {
      paddingX: '0.75rem',
      paddingY: '0.375rem',
      sm: { paddingX: '0.625rem', paddingY: '0.25rem' },
      label: { fontWeight: '600' },
    },
    card: {
      body: { padding: '1rem' },
      shadow: 'none',
    },
    dialog: { shadow: 'none' },
    popover: { shadow: 'none' },
    menu: { shadow: 'none' },
    tag: {
      padding: '0.0625rem 0.375rem',
      borderRadius: '3px',
      fontSize: '11px',
      fontWeight: '400',
    },
  },
})

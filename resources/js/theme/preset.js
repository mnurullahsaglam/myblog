import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import { DARK_INK, RAMPS } from './ramps'

export default definePreset(Aura, {
    semantic: {
        primary: RAMPS.khaki,
        borderRadius: {
            none: '0',
            xs: '2px',
            sm: '3px',
            md: '4px',
            lg: '6px',
            xl: '10px',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.500}',
                    contrastColor: DARK_INK,
                    hoverColor: '{primary.600}',
                    activeColor: '{primary.700}',
                },
            },
            dark: {
                primary: {
                    color: '{primary.400}',
                    contrastColor: DARK_INK,
                    hoverColor: '{primary.300}',
                    activeColor: '{primary.200}',
                },
            },
        },
    },
    components: {
        datatable: {
            headerCell: { padding: '0.625rem 0.75rem' },
            bodyCell: { padding: '0.5rem 0.75rem' },
        },
    },
})

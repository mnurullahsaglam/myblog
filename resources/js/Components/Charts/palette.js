/**
 * Chart colours come from the accent ramp plus neutrals, never a rainbow.
 * Read from the live CSS variables so a change of accent flows through.
 */
export function chartPalette() {
    const read = (name) =>
        getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#9096A2'

    return [
        read('--p-primary-400'),
        read('--p-primary-600'),
        read('--p-primary-800'),
        '#9096A2',
        '#5A606E',
        '#3A3F4A',
        '#272B35',
    ]
}

export const NEUTRAL = '#272B35'
export const AXIS = '#5A606E'
export const GRID = 'rgba(144, 150, 162, 0.14)'

/** Seconds as "3h 42m", matching the server's formatting. */
export function humanDuration(seconds) {
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)

    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`
}

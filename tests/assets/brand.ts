/**
 * The brand palette every listing asset is painted with.
 *
 * One module, imported by the icon rasteriser, the banner renderer and the
 * screenshot frames alike. Three copies of the same hex is exactly how an icon
 * and a banner drift apart.
 *
 * Every value is a Tailwind sky stop, so the ramp is already
 * contrast-tested against itself rather than hand-picked to be close to
 * something. Sky blue is banking without being any particular bank, and it separates this from the darker blue the WooCommerce set uses for admin tooling.
 *
 * `.wordpress-org/icon.svg` repeats these values because SVG cannot import;
 * its header names this file as canonical.
 */
export const BRAND = {
	/** What the assets call the plugin. One place, so a rename is one edit. */
	name: 'GoCardless Payments',
	nameAccent: 'for WooCommerce',
	tagline: 'Direct Debit for WooCommerce',

	/** Ground, darkest first. */
	ink: '#082f49',
	inkMid: '#0c4a6e',
	inkLift: '#075985',

	/** Accent, deep to bright. */
	royal: '#0284c7',
	royalLight: '#0ea5e9',
	sky: '#38bdf8',

	/** The wordmark's gradient, and anything that must stay legible on the ground. */
	accent: '#7dd3fc',

	/** Shadow colour under white cards, so shadows read as the same hue. */
	shadow: '8, 47, 73',

	/** The glyph fill, top to bottom. */
	glyphTop: '#ffffff',
	glyphMid: '#f0f9ff',
	glyphBase: '#bae6fd',
} as const;

/**
 * The field, as stacked CSS backgrounds — glow, specular, ground, in the order
 * CSS paints them. `angle` tilts the ground ramp.
 *
 * @param angle Ground ramp angle, in degrees.
 */
export function field( angle = 135 ): string {
	return [
		'radial-gradient(70% 100% at 78% 104%, rgba(2, 132, 199, .38) 0%, rgba(2, 132, 199, 0) 64%)',
		'linear-gradient(to bottom, rgba(255, 255, 255, .10) 0%, rgba(255, 255, 255, 0) 34%)',
		`linear-gradient(${ angle }deg, ${ BRAND.ink } 0%, ${ BRAND.inkMid } 42%, ${ BRAND.inkLift } 100%)`,
	].join( ', ' );
}

/** Alias, so the banner and the frames can each call it by the name that fits. */
export const glassField = field;

/**
 * The mark, without its squircle — a tile inside a branded field would be a
 * panel on a panel.
 *
 * @param size Rendered size in pixels; the viewBox is always the 256 master.
 */
export function markSvg( size: number ): string {
	return `<svg width="${ size }" height="${ size }" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <linearGradient id="mark-glyph" x1="50%" y1="0%" x2="50%" y2="100%">
      <stop offset="0" stop-color="${ BRAND.glyphTop }"/>
      <stop offset="0.6" stop-color="${ BRAND.glyphMid }"/>
      <stop offset="1" stop-color="${ BRAND.glyphBase }"/>
    </linearGradient>
  </defs>
	<path d="M128 42 L220 92 H36 Z" fill="url(#mark-glyph)"/>
	<rect x="62" y="106" width="26" height="80" rx="7" fill="url(#mark-glyph)"/>
	<rect x="115" y="106" width="26" height="80" rx="7" fill="url(#mark-glyph)"/>
	<rect x="168" y="106" width="26" height="80" rx="7" fill="url(#mark-glyph)"/>
	<rect x="40" y="198" width="176" height="22" rx="11" fill="url(#mark-glyph)"/>
</svg>`;
}

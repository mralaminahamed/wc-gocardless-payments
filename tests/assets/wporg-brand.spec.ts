import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { BRAND, glassField, markSvg } from './brand';

/**
 * Renders the WordPress.org icon and banner PNGs from their source markup.
 *
 *   yarn shots:banners
 *
 * Needs no running site — everything is local files. .wordpress-org/icon.svg
 * is the icon master; the PNGs are rasterised from it here so they can never
 * drift from the vector.
 *
 * The banner's layout lives in resources/brand/banner.html, but its palette
 * does not: this file injects it from ./brand, which is also what the
 * screenshot frames paint with. See the note at the top of banner.html.
 */

const ROOT = path.resolve( __dirname, '..', '..' );
const OUT = path.join( ROOT, '.wordpress-org' );

const fileUrl = ( relative: string ) =>
	pathToFileURL( path.join( ROOT, relative ) ).toString();

test.describe( 'WordPress.org brand assets', () => {
	test( 'icon PNGs', async ( { page } ) => {
		// Inlined rather than loaded as a document: an SVG page has no <head>
		// to style, and as an <img> the gradients rasterise at the intrinsic
		// size before scaling. Inline markup renders crisply at any size.
		const svg = fs.readFileSync( path.join( OUT, 'icon.svg' ), 'utf8' );

		// 128 and 256 are what the directory uses; 512 is kept for channels
		// outside WordPress.org that ask for a larger mark.
		for ( const size of [ 512, 256, 128 ] ) {
			await page.setViewportSize( { width: size, height: size } );
			await page.setContent(
				`<!DOCTYPE html><html><head><style>
					html,body{margin:0;padding:0;background:transparent}
					svg{display:block;width:${ size }px;height:${ size }px}
				</style></head><body>${ svg }</body></html>`
			);

			await page.screenshot( {
				path: path.join( OUT, `icon-${ size }x${ size }.png` ),
				omitBackground: true,
			} );
		}
	} );

	test( 'banner PNGs', async ( { page } ) => {
		const variants = [
			// The wide banners read better on a diagonal ramp; the square crop
			// has too little width for a diagonal to resolve, so it gets one
			// closer to vertical.
			{ width: 1544, height: 500, klass: '', angle: 135 },
			{ width: 772, height: 250, klass: 'is-narrow', angle: 135 },
			{ width: 1024, height: 512, klass: 'is-square', angle: 150 },
		];

		for ( const variant of variants ) {
			await page.setViewportSize( {
				width: variant.width,
				height: variant.height,
			} );
			await page.goto( fileUrl( 'resources/brand/banner.html' ) );

			await page.evaluate(
				( { klass, field, brand, mark } ) => {
					const banner = document.getElementById( 'banner' );

					banner?.classList.remove( 'is-narrow', 'is-square' );

					if ( klass ) {
						banner?.classList.add( klass );
					}

					// The palette, from tests/assets/brand.ts. banner.html
					// declares no fallbacks, so this is the only paint it gets.
					const root = document.documentElement.style;

					root.setProperty( '--brand-field', field );
					root.setProperty( '--brand-deep', brand.ink );
					root.setProperty( '--brand-pool', brand.accent );
					root.setProperty( '--brand-glyph-base', brand.glyphBase );
					root.setProperty( '--brand-shadow', brand.shadow );

					const holder = document.getElementById( 'mark' );

					if ( holder ) {
						holder.innerHTML = mark;
					}
				},
				{
					klass: variant.klass,
					field: glassField( variant.angle ),
					brand: BRAND,
					// Sized to the box it fills; .mark is already dimensioned
					// in CSS, so the SVG only needs to fill it.
					mark: markSvg( 256 ).replace(
						/width="256" height="256"/,
						'width="100%" height="100%"'
					),
				}
			);

			// Web fonts, if any resolved, must be settled before capture.
			await page.evaluate( () => document.fonts.ready );

			await page.locator( '#banner' ).screenshot( {
				path: path.join(
					OUT,
					`banner-${ variant.width }x${ variant.height }.png`
				),
			} );
		}
	} );
} );

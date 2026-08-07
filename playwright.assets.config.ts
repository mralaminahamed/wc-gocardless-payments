import { defineConfig } from '@playwright/test';

/**
 * Config for regenerating the WordPress.org listing assets.
 *
 *   yarn shots:banners   — icon + banners (renders markup, no site needed)
 *   yarn shots:wporg   — screenshots (drives a logged-in WordPress admin)
 *
 * Kept apart from any end-to-end suite this plugin grows later: asset
 * rendering shares none of that environment and should not be able to break
 * it, or be broken by it.
 *
 * Uses `channel: 'chrome'` so it drives an already-installed Chrome rather
 * than requiring `playwright install`, and pins the viewport to 1440x900 so
 * every screenshot in .wordpress-org/ comes out the same size.
 */
export default defineConfig( {
	testDir: './tests/assets',
	timeout: 60_000,
	fullyParallel: false,
	workers: 1,

	/*
	 * Retry twice.
	 *
	 * These drive a local dev site, often over a self-signed certificate, and
	 * Chrome intermittently fails a burst of subresources. Two retries clear
	 * the flake without hiding a real fault: a genuinely broken page fails all
	 * three attempts.
	 */
	retries: 2,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://wc-affiliate.test',
		ignoreHTTPSErrors: true,
		screenshot: 'off',
		video: 'off',
		trace: 'off',
	},
	projects: [
		{
			name: 'banners',
			testMatch: 'wporg-brand.spec.ts',
			use: { channel: 'chrome' },
		}
	],
} );

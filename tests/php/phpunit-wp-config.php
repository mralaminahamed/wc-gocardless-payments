<?php

// @see https://github.com/WordPress/wordpress-develop/blob/trunk/wp-tests-config-sample.php

$wordpress_dir = dirname( __DIR__, 2 ) . '/wordpress/';
if ( ! is_dir( $wordpress_dir ) ) {
	$wordpress_dir = dirname( __DIR__, 5 ) . '/';
}

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', $wordpress_dir );

define( 'WP_DEFAULT_THEME', 'default' );

// Test with multisite enabled.
// Alternatively, use the tests/phpunit/multisite.xml configuration file.
// define( 'WP_TESTS_MULTISITE', true );

// Force known bugs to be run.
// Tests with an associated Trac ticket that is still open are normally skipped.
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** Database settings ** //

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wp_phpunit_tests' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASS' ) ?: "" );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define( 'AUTH_KEY', '~Z]Uj*!*WCS_#a}t&#2Garz&Ql$&UP/z<tP,8TtEh!ID9-7o<X@6-5z9$m,)0h9`' );
define( 'SECURE_AUTH_KEY', '%6I~t*SQrwFix/33~InbaU82vxLV<hfYf+*nW;@<b5+0HU=YXYt[pL$oVJ.tQi~.' );
define( 'LOGGED_IN_KEY', '+ iy zdG_MBlb+?K|?+,{,:wQ|R2bgq)>Sv-LIo(e(i~iI9A6A`hJqD(+Jtp3Z6%' );
define( 'NONCE_KEY', '-y->8j{neb?- [>P$OJdVN/|L4b=4f{Zo=[R }afr2ha7@`i&_g<CUUE mFR{$?X' );
define( 'AUTH_SALT', 'T9|IXuozx=MQ7Nx_6&Da2E;p)J!p{f*,[1Bm/Cga+vHlNmz}*G%H.Y7.hy|U_4-i' );
define( 'SECURE_AUTH_SALT', 'N->yj|(@3`8{B_N(0=]03t1]?f)BMa<Sjk{|3cdY`VQN9|]kxusX(U:InD`9L:L}' );
define( 'LOGGED_IN_SALT', '~A=hF2gH|6T#@-gF?c-Om|:Qgc.tKK}SjEflzS*x|vRs.V?4}fV+z4|<~Ch_WNP ' );
define( 'NONCE_SALT', '$cj14C&HGAR}{Qz5^I+X@g|?$>Lc/PWN&k(S%!/p/-XKb,Y[_j<hT(hsn:{p[,;w' );

$table_prefix = 'unit_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

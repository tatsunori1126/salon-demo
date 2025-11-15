<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'gonzoublog_saron' );

/** Database username */
define( 'DB_USER', 'gonzoublog_saron' );

/** Database password */
define( 'DB_PASSWORD', 'xuL7m8MSUdtd' );

/** Database hostname */
define( 'DB_HOST', 'mysql10031.xserver.jp' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'UA3KZBHO5lgu=6A82bV#^vIB0BEPM`dD[SK:3.l>~+([bh7F23[}u|R]o2co!$TL' );
define( 'SECURE_AUTH_KEY',  '(ev2j]UxF*9o1MjW32J[>9xnyZ2N9UQi|Id%}{ ~>~(oJx+tE*%%]y(-}WUhaX|N' );
define( 'LOGGED_IN_KEY',    '`KeDhGMks]#f1_TT0m^DOzm$lR}3Zy{6Apk.rH<S4n;jpRaQ#fI6!~ISf?7_(9*u' );
define( 'NONCE_KEY',        '{S3gf_R|VH1SwY8K)FIHyAs|XjH,3)yDn:5_umeo>iW|YiDJc[RALV6Z$uQn:QIb' );
define( 'AUTH_SALT',        '1PD@6e.!H49jsf[@SRqceR5KH`%A1K$+_}]p{hd`oFdrW0Qt(Nwp.5Rg{ip]8y~S' );
define( 'SECURE_AUTH_SALT', 'VPo.|YZ06NfxK&SvAx+{7?_Q+Z0b8AO{V< U075qV/:*MZA]1E(G7#=!l>23=.{y' );
define( 'LOGGED_IN_SALT',   '4g2}v$R5G0PXl&7Wvp^mWv]w3u7D*_)_6h)kl,ojx3w2iY 19PiQz|N#^DcN Hyc' );
define( 'NONCE_SALT',       'CkK2#yhm<?[?/4e:]LRa{5$a6n-tgrb.wKYojbFIy,AR)T%#=;M#BQ4w.JFbXJM.' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
// define( 'WP_DEBUG', false );
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

<?php
/**
 * Plugin Name: Discord for JetFormBuilder
 * Description: Sends JetFormBuilder form submissions to Discord via webhooks.
 * Author:      Soczó Kristóf
 * Version:     1.0.1
 * Text Domain: discord-for-jetformbuilder
 * Requires Plugins: jetformbuilder
 */

declare(strict_types=1);

use Discord\JetFormBuilder\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DISCORD_JFB_VERSION', '1.0.1' );
define( 'DISCORD_JFB_PLUGIN_FILE', __FILE__ );
define( 'DISCORD_JFB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DISCORD_JFB_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

const DISCORD_JFB_MIN_PHP_VERSION = '7.4';
const DISCORD_JFB_MIN_WP_VERSION  = '6.0';

$autoload = DISCORD_JFB_PLUGIN_PATH . 'vendor/autoload.php';
$update_checker_bootstrap = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

if ( file_exists( $update_checker_bootstrap ) ) {
	require_once $update_checker_bootstrap;
}

if ( file_exists( $autoload ) ) {
	require $autoload;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Discord\\JetFormBuilder\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', '/', $relative_class );
		$file           = DISCORD_JFB_PLUGIN_PATH . 'includes/' . $relative_path . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		$errors = discord_jfb_requirement_errors();

		if ( empty( $errors ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( plugin_basename( __FILE__ ) );
		unset( $_GET['activate'] );

		$GLOBALS['discord_jfb_activation_errors'] = $errors;

		add_action( 'admin_notices', 'discord_jfb_activation_admin_notice' );
	}
);

if ( ! function_exists( 'discord_jfb_requirement_errors' ) ) {
	/**
	 * Gather unmet requirement messages.
	 *
	 * @param bool $include_plugin_checks Whether to validate plugin dependencies.
	 *
	 * @return string[]
	 */
	function discord_jfb_requirement_errors( bool $include_plugin_checks = true ): array {
		$errors = array();

		if ( version_compare( PHP_VERSION, DISCORD_JFB_MIN_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'Discord for JetFormBuilder requires PHP version %1$s or higher. Current version: %2$s.', 'discord-for-jetformbuilder' ),
				DISCORD_JFB_MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		global $wp_version;

		if ( version_compare( $wp_version, DISCORD_JFB_MIN_WP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version */
				__( 'Discord for JetFormBuilder requires WordPress version %1$s or higher. Current version: %2$s.', 'discord-for-jetformbuilder' ),
				DISCORD_JFB_MIN_WP_VERSION,
				$wp_version
			);
		}

		if ( ! $include_plugin_checks ) {
			return $errors;
		}

		if ( ! function_exists( 'jet_form_builder' ) && ! class_exists( '\Jet_Form_Builder\Plugin' ) ) {
			$errors[] = __( 'Discord for JetFormBuilder requires the JetFormBuilder plugin to be installed and active.', 'discord-for-jetformbuilder' );
		}

		return $errors;
	}
}

if ( ! function_exists( 'discord_jfb_activation_admin_notice' ) ) {
	function discord_jfb_activation_admin_notice(): void {
		if ( empty( $GLOBALS['discord_jfb_activation_errors'] ) || ! is_array( $GLOBALS['discord_jfb_activation_errors'] ) ) {
			return;
		}

		$errors = $GLOBALS['discord_jfb_activation_errors'];

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>',
			esc_html__( 'Discord for JetFormBuilder could not be activated.', 'discord-for-jetformbuilder' ),
			implode( '</li><li>', array_map( 'esc_html', $errors ) )
		);

		unset( $GLOBALS['discord_jfb_activation_errors'] );
	}
}

if ( ! function_exists( 'discord_jfb_admin_notice' ) ) {
	function discord_jfb_admin_notice(): void {
		$errors = $GLOBALS['discord_jfb_runtime_errors'] ?? discord_jfb_requirement_errors();

		if ( empty( $errors ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>',
			esc_html__( 'Discord for JetFormBuilder cannot run:', 'discord-for-jetformbuilder' ),
			implode( '</li><li>', array_map( 'esc_html', $errors ) )
		);
	}
}

$initial_environment_errors = discord_jfb_requirement_errors( false );

if ( ! empty( $initial_environment_errors ) ) {
	$GLOBALS['discord_jfb_runtime_errors'] = $initial_environment_errors;

	if ( is_admin() ) {
		add_action( 'admin_notices', 'discord_jfb_admin_notice' );
	}

	return;
}

add_action(
	'plugins_loaded',
	static function () {
		$errors = discord_jfb_requirement_errors();

		if ( ! empty( $errors ) ) {
			$GLOBALS['discord_jfb_runtime_errors'] = $errors;

			if ( is_admin() ) {
				add_action( 'admin_notices', 'discord_jfb_admin_notice' );
			}

			return;
		}

		Plugin::instance( DISCORD_JFB_PLUGIN_FILE );
	}
);

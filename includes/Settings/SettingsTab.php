<?php

namespace Discord\JetFormBuilder\Settings;

use Discord\JetFormBuilder\Plugin;
use Jet_Form_Builder\Admin\Tabs_Handlers\Base_Handler;
use Jet_Form_Builder\Admin\Pages\Pages_Manager;
use function rest_sanitize_boolean;
use function sanitize_text_field;
use function wp_unslash;

class SettingsTab extends Base_Handler {

	public function slug() {
		return 'discord-settings-tab';
	}

	public function before_assets() {
		$handle = Plugin::instance()->slug() . '-' . $this->slug();

		$script_path = Plugin::instance()->path( 'assets/js/settings-tab.js' );
		$version     = file_exists( $script_path )
			? filemtime( $script_path )
			: '1.0.0';

		wp_deregister_script( $handle );

		wp_register_script(
			$handle,
			Plugin::instance()->url( 'assets/js/settings-tab.js' ),
			array(
				Pages_Manager::SCRIPT_VUEX_PACKAGE,
				'wp-hooks',
				'wp-i18n',
			),
			$version,
			true
		);

		wp_enqueue_script( $handle );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				'discord-for-jetformbuilder'
			);
		}
	}

	public function on_get_request() {
		$webhook = sanitize_text_field(
			wp_unslash( $_POST['webhook'] ?? '' )
		);
		$failure_webhook = sanitize_text_field(
			wp_unslash( $_POST['failure_webhook'] ?? '' )
		);
		$notify_failures_only = rest_sanitize_boolean(
			wp_unslash( $_POST['notify_failures_only'] ?? false )
		);

		$result = $this->update_options(
			array(
				'webhook'         => $webhook,
				'failure_webhook' => $failure_webhook,
				'notify_failures_only' => (bool) $notify_failures_only,
			)
		);

		$this->send_response( $result );
	}

	public function on_load() {
		return $this->get_options(
			array(
				'webhook'         => '',
				'failure_webhook' => '',
				'notify_failures_only' => false,
			)
		);
	}
}

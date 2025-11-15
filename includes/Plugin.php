<?php

namespace Discord\JetFormBuilder;

use Discord\JetFormBuilder\Actions\DiscordNotificationAction;
use Discord\JetFormBuilder\Notifications\FailureNotifier;
use Discord\JetFormBuilder\Settings\SettingsTab;
use Jet_Form_Builder\Actions\Manager as ActionsManager;
use Jet_Form_Builder\Form_Handler;
use YahnisElsts\PluginUpdateChecker\v5p0\PucFactory;

class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $slug = 'discord-for-jetformbuilder';

	/**
	 * @var string
	 */
	private $plugin_file;

	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;

		$this->init_hooks();
		$this->init_updater();
	}

	public static function instance( ?string $plugin_file = null ): Plugin {
		if ( null === self::$instance ) {
			if ( null === $plugin_file ) {
				throw new \RuntimeException( 'Plugin file path is required on first initialization.' );
			}

			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	public function slug(): string {
		return $this->slug;
	}

	public function url( string $path = '' ): string {
		return DISCORD_JFB_PLUGIN_URL . ltrim( $path, '/' );
	}

	public function path( string $path = '' ): string {
		return DISCORD_JFB_PLUGIN_PATH . ltrim( $path, '/' );
	}

	private function init_hooks(): void {
		add_filter(
			'jet-form-builder/register-tabs-handlers',
			array( $this, 'register_tabs' )
		);

		add_action(
			'jet-form-builder/actions/register',
			array( $this, 'register_actions' )
		);

		add_action(
			'jet-form-builder/editor-assets/before',
			array( $this, 'enqueue_editor_assets' )
		);

		add_action(
			'jet-form-builder/form-handler/after-send',
			array( $this, 'handle_submission_result' ),
			20,
			2
		);
	}

	public function register_tabs( array $tabs ): array {
		$tabs[] = new SettingsTab();

		return $tabs;
	}

	public function register_actions( ActionsManager $manager ): void {
		$manager->register_action_type( new DiscordNotificationAction() );
	}

	public function enqueue_editor_assets(): void {
		$script_rel_path = 'assets/js/action-editor.js';
		$script_path     = $this->path( $script_rel_path );

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$handle  = $this->slug() . '-action-editor';
		$version = (string) filemtime( $script_path );

		wp_register_script(
			$handle,
			$this->url( $script_rel_path ),
			array( 'jet-fb-components', 'wp-element', 'wp-components', 'wp-i18n', 'wp-hooks' ),
			$version,
			true
		);

		$action = new DiscordNotificationAction();

		$localized = array(
			'__labels'        => $action->editor_labels(),
			'__help_messages' => $action->editor_labels_help(),
		);

		$extra = $action->action_data();

		if ( is_array( $extra ) && ! empty( $extra ) ) {
			$localized = array_merge( $localized, $extra );
		}

		wp_localize_script(
			$handle,
			'DiscordNotification',
			$localized
		);

		wp_enqueue_script( 'jet-fb-components' );
		wp_enqueue_script( $handle );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				'discord-for-jetformbuilder'
			);
		}
	}

	public function handle_submission_result( Form_Handler $form_handler, bool $is_success ): void {
		FailureNotifier::handle( $form_handler, $is_success );
	}

	private function init_updater(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		PucFactory::buildUpdateChecker(
			'https://pluginupdater.hellodevs.dev/plugins/discord-for-jetformbuilder.json',
			$this->plugin_file,
			'discord-for-jetformbuilder'
		);
	}
}

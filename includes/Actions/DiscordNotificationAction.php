<?php

namespace Discord\JetFormBuilder\Actions;

use Discord\JetFormBuilder\Webhook\Client as Webhook_Client;
use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Admin\Tabs_Handlers\Tab_Handler_Manager;
use Jet_Form_Builder\Form_Handler;
use Jet_Form_Builder\Plugin as JetFB_Plugin;
use Jet_Form_Builder\Exceptions\Action_Exception;
use function __;
use function jet_fb_context;
use function is_wp_error;
use function sanitize_textarea_field;
use function wp_json_encode;
use function esc_url_raw;
use function wp_unslash;

class DiscordNotificationAction extends Base {

	public function get_id() {
		return 'discord_notification';
	}

	public function get_name() {
		return __( 'Discord notification', 'discord-for-jetformbuilder' );
	}

	public function self_script_name() {
		return 'DiscordNotification';
	}

	public function editor_labels() {
		return array(
			'message'        => __( 'Discord message', 'discord-for-jetformbuilder' ),
			'include_refer'  => __( 'Include refer URL', 'discord-for-jetformbuilder' ),
			'include_form'   => __( 'Include form name', 'discord-for-jetformbuilder' ),
		);
	}

	public function editor_labels_help() {
		return array(
			'message'       => __( 'Text that will be delivered to your Discord channel. Use JetFormBuilder macros to include form values.', 'discord-for-jetformbuilder' ),
			'include_refer' => __( 'Toggle whether the Discord message should contain the form refer URL at the end.', 'discord-for-jetformbuilder' ),
			'include_form'  => __( 'Toggle whether the Discord message should contain the form name.', 'discord-for-jetformbuilder' ),
		);
	}

	public function action_attributes() {
		return array(
			'message' => array(
				'default' => '',
			),
			'include_refer' => array(
				'default' => true,
			),
			'include_form' => array(
				'default' => true,
			),
		);
	}

	public function action_data() {
		return array();
	}

	public function do_action( array $request, Action_Handler $handler ) {
		$message_template = isset( $this->settings['message'] )
			? (string) $this->settings['message']
			: '';

		$message_template = sanitize_textarea_field( wp_unslash( $message_template ) );
		$message_template = str_replace( array( "\r\n", "\r" ), "\n", $message_template );

		$webhook = $this->get_webhook_url();

		if ( '' === $webhook ) {
			throw new Action_Exception(
				__( 'Discord webhook is not configured. Please set it in Discord settings.', 'discord-for-jetformbuilder' )
			);
		}

		$message = $this->replace_macros( $message_template );
		$message = trim( $message );

		if ( '' === $message ) {
			return;
		}

		$referer = $this->get_referer_url();
		$include_refer = isset( $this->settings['include_refer'] )
			? (bool) $this->settings['include_refer']
			: true;
		$include_form = isset( $this->settings['include_form'] )
			? (bool) $this->settings['include_form']
			: true;

		$meta_lines = array();

		if ( $include_refer && '' !== $referer ) {
			$meta_lines[] = '**Refer:** ' . $referer;
		}

		if ( $include_form ) {
			$form_name = $this->get_form_name( $handler );

			if ( '' !== $form_name ) {
				$meta_lines[] = '**Form:** ' . $form_name;
			}
		}

		if ( ! empty( $meta_lines ) ) {
			array_unshift( $meta_lines, '---' );
			$message = trim( $message ) . "\n\n" . implode( "\n", $meta_lines );
		}

		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 2000 );
		} else {
			$message = substr( $message, 0, 2000 );
		}

		$this->send_to_discord( $webhook, $message );
	}

	private function get_webhook_url(): string {
		$options = Tab_Handler_Manager::instance()->options( 'discord-settings-tab', array() );

		$webhook = isset( $options['webhook'] ) ? (string) $options['webhook'] : '';

		return trim( $webhook );
	}

	private function get_referer_url(): string {
		$url = '';

		if ( function_exists( 'jet_fb_handler' ) ) {
			$form_handler = jet_fb_handler();

			if ( $form_handler instanceof Form_Handler ) {
				$url = $form_handler->get_referrer();
			}
		}

		if ( ! is_string( $url ) || '' === $url ) {
			if ( function_exists( 'jet_fb_context' ) ) {
				$context = jet_fb_context();

				if ( $context && $context->has_field( '__refer' ) ) {
					$context_url = $context->get_value( '__refer' );

					if ( is_string( $context_url ) && '' !== $context_url ) {
						$url = $context_url;
					}
				}
			}
		}

		if ( ! is_string( $url ) || '' === $url ) {
			$url = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}

		return is_string( $url ) ? trim( $url ) : '';
	}

	private function get_form_name( Action_Handler $handler ): string {
		$form_id = $handler->get_form_id();

		if ( ! $form_id ) {
			return '';
		}

		$form_post = get_post( $form_id );

		if ( $form_post && ! empty( $form_post->post_title ) ) {
			return $form_post->post_title;
		}

		if ( class_exists( JetFB_Plugin::class ) ) {
			$manager = JetFB_Plugin::instance()->post_type;

			if ( method_exists( $manager, 'get_form_name' ) ) {
				$name = $manager->get_form_name( $form_id );

				if ( is_string( $name ) && '' !== $name ) {
					return $name;
				}
			}
		}

		return '';
	}

	/**
	 * Replace JetFormBuilder macros with submitted values.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	private function replace_macros( string $template ): string {
		if ( false === strpos( $template, '%' ) ) {
			return $template;
		}

		return (string) preg_replace_callback(
			'/%(?P<name>[a-zA-Z0-9\\-_]+)%/',
			static function ( $match ) {
				$field = $match['name'];

				if ( ! jet_fb_context()->has_field( $field ) ) {
					return $match[0];
				}

				$value = jet_fb_context()->get_value( $field );

				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}

				return (string) $value;
			},
			$template
		);
	}

	/**
	 * @throws Action_Exception When the webhook call fails.
	 */
	private function send_to_discord( string $webhook, string $message ): void {
		$result = Webhook_Client::send(
			$webhook,
			array(
				'content' => $message,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new Action_Exception(
				sprintf(
					/* translators: %s: error message. */
					__( 'Discord request failed: %s', 'discord-for-jetformbuilder' ),
					$result->get_error_message()
				)
			);
		}
	}
}

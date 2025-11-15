<?php

namespace Discord\JetFormBuilder\Notifications;

use Discord\JetFormBuilder\Webhook\Client as Webhook_Client;
use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Form_Handler;
use WP_Error;
use function gmdate;
use function esc_url;
use function get_the_title;
use function jet_fb_action_handler;
use function sanitize_text_field;
use function wp_unslash;

class FailureNotifier {

	public static function handle( Form_Handler $form_handler, bool $is_success ): void {
		$settings             = self::get_settings();
		$webhook              = trim( (string) ( $settings['failure_webhook'] ?? '' ) );
		$notify_failures_only = ! empty( $settings['notify_failures_only'] );

		if ( '' === $webhook ) {
			return;
		}

		$action_handler = $form_handler->action_handler instanceof Action_Handler
			? $form_handler->action_handler
			: jet_fb_action_handler();

		list( $passed, $skipped, $failed ) = self::chunk_actions( $action_handler );

		if ( $notify_failures_only && empty( $failed ) ) {
			return;
		}

		$has_failures = ! empty( $failed );
		$actions_block = self::format_actions_block( $passed, $skipped, $failed );
		$status_key    = $form_handler->response_args['status'] ?? 'failed';
		$form_id       = (int) $form_handler->get_form_id();
		$form_title    = $form_id ? get_the_title( $form_id ) : '';
		$referer       = $form_handler->get_referrer();
		$site_host     = ! empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		$content = $has_failures
			? '⚠️ **JetFormBuilder submission failure detected**'
			: '✅ **JetFormBuilder submission processed**';

		$fields = array(
			array(
				'name'   => 'Form',
				'value'  => $form_title
					? sprintf( '%s (ID: %d)', $form_title, $form_id )
					: sprintf( 'Form ID: %d', $form_id ),
				'inline' => true,
			),
			array(
				'name'   => 'Result',
				'value'  => sprintf(
					"Status key: `%s`\nReported success: **%s**\nFailures detected: **%s**",
					$status_key,
					$is_success ? 'yes' : 'no',
					$has_failures ? 'yes' : 'no'
				),
				'inline' => true,
			),
		);

		if ( $site_host ) {
			$fields[] = array(
				'name'   => 'Site',
				'value'  => $site_host,
				'inline' => true,
			);
		}

		if ( $referer ) {
			$fields[] = array(
				'name'   => 'Referrer',
				'value'  => sprintf( '<%s>', esc_url( $referer ) ),
				'inline' => false,
			);
		}

		if ( $actions_block ) {
			$fields[] = array(
				'name'   => 'Actions',
				'value'  => $actions_block,
				'inline' => false,
			);
		}

		$result = Webhook_Client::send(
			$webhook,
			array(
				'content' => $content,
				'embeds'  => array(
					array(
						'color'  => $has_failures ? 0xE74C3C : 0x2ECC71,
						'fields' => $fields,
						'timestamp' => gmdate( 'c' ),
					),
				),
			)
		);

		if ( $result instanceof WP_Error ) {
			error_log(
				sprintf(
					'[Discord JFB] Failed to notify webhook about submission failure: %s',
					$result->get_error_message()
				)
			);
		}
	}

	private static function chunk_actions( Action_Handler $handler ): array {
		$passed_ids  = $handler->get_passed_actions();
		$skipped_ids = $handler->get_skipped_actions();

		$passed  = array();
		$skipped = array();
		$failed  = array();

		foreach ( $handler->get_all() as $id => $action ) {
			if ( in_array( $id, $passed_ids, true ) ) {
				$passed[ $id ] = $action;
			} elseif ( in_array( $id, $skipped_ids, true ) ) {
				$skipped[ $id ] = $action;
			} else {
				$failed[ $id ] = $action;
			}
		}

		return array( $passed, $skipped, $failed );
	}

	private static function format_actions_block( array $passed, array $skipped, array $failed ): string {
		$lines = array();

		foreach ( $passed as $id => $action ) {
			$line = self::format_action_line( $action, $id, 'success' );

			if ( $line ) {
				$lines[] = $line;
			}
		}

		foreach ( $skipped as $id => $action ) {
			$line = self::format_action_line( $action, $id, 'skipped' );

			if ( $line ) {
				$lines[] = $line;
			}
		}

		foreach ( $failed as $id => $action ) {
			$line = self::format_action_line( $action, $id, 'failed' );

			if ( $line ) {
				$lines[] = $line;
			}
		}

		if ( empty( $lines ) ) {
			return '';
		}

		$actions = implode( "\n", $lines );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $actions, 0, 1024 );
		}

		return substr( $actions, 0, 1024 );
	}

	private static function format_action_line( $action, int $id, string $status ): string {
		if ( ! ( $action instanceof Base ) ) {
			return '';
		}

		$icons = array(
			'success' => '✅',
			'skipped' => '⏭️',
			'failed'  => '❌',
		);

		$label = method_exists( $action, 'get_name' )
			? trim( (string) $action->get_name() )
			: '';

		$icon     = $icons[ $status ] ?? '•';
		$base     = sprintf( '%s `%s` (#%d)', $icon, $action->get_id(), $id );
		$trail    = $label ? sprintf( ' – %s', $label ) : '';

		return $base . $trail;
	}

	private static function get_settings(): array {
		if ( ! class_exists( '\Jet_Form_Builder\Admin\Tabs_Handlers\Tab_Handler_Manager' ) ) {
			return array();
		}

		return \Jet_Form_Builder\Admin\Tabs_Handlers\Tab_Handler_Manager::instance()->options(
			'discord-settings-tab',
			array()
		);
	}
}

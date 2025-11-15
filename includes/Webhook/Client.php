<?php

namespace Discord\JetFormBuilder\Webhook;

use WP_Error;

class Client {

	/**
	 * Send payload to a Discord webhook endpoint.
	 *
	 * @param string $webhook Discord webhook URL.
	 * @param array  $payload Request payload array.
	 *
	 * @return array|WP_Error
	 */
	public static function send( string $webhook, array $payload ) {
		$response = wp_remote_post(
			$webhook,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'discord_http_error',
				sprintf(
					'Discord request returned HTTP %d.',
					$status_code
				),
				array(
					'response' => $response,
				)
			);
		}

		return $response;
	}
}

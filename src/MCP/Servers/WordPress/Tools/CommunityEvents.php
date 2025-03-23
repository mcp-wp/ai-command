<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress\Tools;

use Mcp\Types\TextContent;
use WP_Community_Events;

readonly class CommunityEvents {

	public function get_tools(): array {
		$tools = [];

		$tools[] = [
			'name'        => 'fetch_wp_community_events',
			'description' => 'Fetches upcoming WordPress community events near a specified city or the user\'s current location. If no events are found in the exact location, nearby events within a specific radius will be considered.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'location' => [
						'type'        => 'string',
						'description' => 'City name or "near me" for auto-detected location. If no events are found in the exact location, the tool will also consider nearby events within a specified radius (default: 100 km).',
					],
				],
				'required'   => [ 'location' ],  // We only require the location
			],
			'callable'    => static function ( $params ) {
				$location_input = strtolower( trim( $params['location'] ) );

				// Manually include the WP_Community_Events class if it's not loaded
				if ( ! class_exists( 'WP_Community_Events' ) ) {
					require_once ABSPATH . 'wp-admin/includes/class-wp-community-events.php';
				}

				$location = [
					'description' => $location_input,
				];

				$events_instance = new WP_Community_Events( 0, $location );

				// Get events from WP_Community_Events
				$events = $events_instance->get_events( $location_input );

				// Check for WP_Error
				if ( is_wp_error( $events ) ) {
					return $events;
				}

				return new TextContent(
					json_encode( $events['events'] )
				);
			},
		];

		return $tools;
	}
}

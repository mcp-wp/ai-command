<?php

namespace WP_CLI\AiCommand\Tools;
use WP_CLI\AiCommand\Entity\Tool;

class CommunityEvents {

	public function get_tools(){
		$tools = [];

		$tools[] =  new Tool( [
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
				'callable'    => function ( $params ) {
						// Default user ID is 0
						$user_id = 0;

						// Get the location from the parameters (already supplied in the prompt)
						$location_input = strtolower( trim( $params['location'] ) );

						// Manually include the WP_Community_Events class if it's not loaded
						if ( ! class_exists( 'WP_Community_Events' ) ) {
								require_once ABSPATH . 'wp-admin/includes/class-wp-community-events.php';
						}

						// Determine location for the WP_Community_Events class
						$location = null;
						if ( $location_input !== 'near me' ) {
								// Provide city name (WP will resolve coordinates)
								$location = [
										'description' => $location_input,
								];
						}

						// Instantiate WP_Community_Events with user ID (0) and optional location
						$events_instance = new WP_Community_Events( $user_id, $location );

						// Get events from WP_Community_Events
						$events = $events_instance->get_events($location_input);

						// Check for WP_Error
						if ( is_wp_error( $events ) ) {
								return [ 'error' => $events->get_error_message() ];
						}

					// If no events found
					if ( empty( $events['events'] ) ) {
						return [ 'message' => 'No events found near ' . ( $location_input === 'near me' ? 'your location' : $location_input ) ];
					}

					// Format and return the events correctly
					$formatted_events = array_map( function ( $event ) {
						// Log event details to ensure properties are accessible
						error_log( 'Event details: ' . print_r( $event, true ) );

						// Initialize a formatted event string
						$formatted_event = '';

						// Format event title
						if ( isset( $event['title'] ) ) {
								$formatted_event .= $event['title'] . "\n";
						}

						// Format the date nicely
						$formatted_event .= '  - Date: ' . ( isset( $event['date'] ) ? date( 'F j, Y g:i A', strtotime( $event['date'] ) ) : 'No date available' ) . "\n";

						// Format the location
						if ( isset( $event['location']['location'] ) ) {
								$formatted_event .= '  - Location: ' . $event['location']['location'] . "\n";
						}

						// Format the event URL
						$formatted_event .= isset( $event['url'] ) ? '  - URL: ' . $event['url'] . "\n" : '';

						return $formatted_event;
					}, $events['events'] );

					// Combine the formatted events into a single string
					$formatted_events_output = implode("\n", $formatted_events);

					// Return the formatted events string
					return [
						'message' => "OK. I found " . count($formatted_events) . " WordPress events near " . ( $location_input === 'near me' ? 'your location' : $location_input ) . ":\n\n" . $formatted_events_output
					];
				},
		] );

		return $tools;
	}
}

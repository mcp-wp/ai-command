<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress;

use Felix_Arntz\AI_Services\Services\API\Enums\AI_Capability;
use Felix_Arntz\AI_Services\Services\API\Helpers;
use Felix_Arntz\AI_Services\Services\API\Types\Parts\File_Data_Part;
use Felix_Arntz\AI_Services\Services\API\Types\Parts\Inline_Data_Part;
use WP_CLI;
use Exception;

class WpAiClient {
	// Must not have the same name as the tool, otherwise it takes precedence.
	public function get_image_from_ai_service( string $prompt ) {
		// See https://github.com/felixarntz/ai-services/issues/25.
		add_filter(
			'map_meta_cap',
			static function () {
				return [ 'exist' ];
			}
		);

		try {
			$service    = ai_services()->get_available_service(
				[
					'capabilities' => [
						AI_Capability::IMAGE_GENERATION,
					],
				]
			);
			$candidates = $service
				->get_model(
					[
						'feature'      => 'image-generation',
						'capabilities' => [
							AI_Capability::IMAGE_GENERATION,
						],
					]
				)
				->generate_image( $prompt );

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$image_id  = null;
		$image_url = '';
		foreach ( $candidates->get( 0 )->get_content()->get_parts() as $part ) {
			if ( $part instanceof Inline_Data_Part ) {
				$image_url  = $part->get_base64_data(); // Data URL.
				$image_blob = Helpers::base64_data_url_to_blob( $image_url );

				if ( $image_blob ) {
					$filename  = tempnam( '/tmp', 'ai-generated-image' );
					$parts     = explode( '/', $part->get_mime_type() );
					$extension = $parts[1];
					rename( $filename, $filename . '.' . $extension );
					$filename .= '.' . $extension;

					file_put_contents( $filename, $image_blob->get_binary_data() );

					$image_url = $filename;
					$image_id  = MediaManager::upload_to_media_library( $image_url );
				}

				break;
			}

			if ( $part instanceof File_Data_Part ) {
				$image_url = $part->get_file_uri(); // Actual URL. May have limited TTL (often 1 hour).
				// TODO: Save as file or so.
				break;
			}
		}

		return $image_id ?: 'no image found';
	}

	public function modify_image_with_ai( $prompt, $media_element ): bool {

		$mime_type      = $media_element['mime_type'];
		$image_path     = $media_element['filepath'];
		$image_contents = file_get_contents( $image_path );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$base64_image = base64_encode( $image_contents );

		// API Configuration
		$api_key = get_option( 'ais_google_api_key' );

		if ( ! $api_key ) {
			WP_CLI::error( 'Gemini API Key is not available' );
		}
		$model   = 'gemini-2.0-flash-exp';
		$api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

		// Prepare request payload
		$payload = [
			'contents'         => [
				[
					'role'  => 'user',
					'parts' => [
						[
							'text' => $prompt,
						],
						[
							'inline_data' => [
								'mime_type' => $mime_type,
								'data'      => $base64_image,
							],
						],
					],
				],
			],
			'generationConfig' => [
				'responseModalities' => [ 'TEXT', 'IMAGE' ],
			],
		];

		// Convert payload to JSON
		$json_payload = json_encode( $payload );

		// Set up cURL request
		$ch = curl_init( $api_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_payload );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'Content-Length: ' . strlen( $json_payload ),
			]
		);

		// Execute request
		$response    = curl_exec( $ch );
		$error       = curl_error( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		// Handle errors
		if ( $error ) {
			WP_CLI::error( 'cURL Error: ' . $error );
			return false;
		}

		if ( $status_code >= 400 ) {
			WP_CLI::error( "API Error (Status $status_code): " . $response );
			return false;
		}

		// Process response
		$response_data = json_decode( $response, true );

		// Check for valid response
		if ( empty( $response_data ) || ! isset( $response_data['candidates'][0]['content']['parts'] ) ) {
			WP_CLI::error( 'Invalid API response format' );
			return false;
		}

		// Extract image data from response
		$image_data = null;
		foreach ( $response_data['candidates'][0]['content']['parts'] as $part ) {
			if ( isset( $part['inlineData'] ) ) {
				$image_data         = $part['inlineData']['data'];
				$response_mime_type = $part['inlineData']['mimeType'];
				break;
			}
		}

		if ( ! $image_data ) {
			WP_CLI::error( 'No image data in response' );
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$binary_data = base64_decode( $image_data );
		if ( false === $binary_data ) {
			WP_CLI::error( 'Failed to decode image data' );
			return false;
		}

		// Create temporary file for the image
		$extension = explode( '/', $response_mime_type )[1] ?? 'jpg';
		$filename  = tempnam( '/tmp', 'ai-generated-image' );
		rename( $filename, $filename . '.' . $extension );
		$filename .= '.' . $extension;

		// Save image to the file
		if ( ! file_put_contents( $filename, $binary_data ) ) {
			WP_CLI::error( 'Failed to save image to temporary file' );
			return false;
		}

		// Upload to media library
		$image_id = MediaManager::upload_to_media_library( $filename );

		if ( $image_id ) {
			WP_CLI::success( 'Image generated with ID: ' . $image_id );
			return $image_id;
		}

		return false;
	}
}

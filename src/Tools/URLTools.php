<?php

namespace WP_CLI\AiCommand\Tools;
use WP_CLI\AiCommand\Entity\Tool;

class URLTools {

	private $server;

	public function __construct( $server ) {
		$this->server = $server;
	}

	public function get_tools(){
		$tools = [];

		$tools[] =  new Tool( [
				'name'        => 'retrieve_page',
				'description' => 'Retrieves a page from the web.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'    => [
							'type'        => 'string',
							'description' => 'The URL of the page to retrieve.',
						],
					],
					'required'   => [ 'url' ],
				],
				'callable'    => function ( $params ) {
					$url = $params['url'];
					$response = wp_remote_get( $url );
					$body = wp_remote_retrieve_body( $response );
					return $body;
				},
			]
		);

		$tools[] =  new Tool( [
				'name'        => 'retrieve_rss_feed',
				'description' => 'Retrieves an RSS feed.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'    => [
							'type'        => 'string',
							'description' => 'The URL of the RSS feed to retrieve.',
						],
					],
					'required'   => [ 'url' ],
				],
				'callable'    => function ( $params ) {
					$url = $params['url'];
					$response = wp_remote_get( $url );
					$body = wp_remote_retrieve_body( $response );
					$rss = simplexml_load_string( $body );
					return $rss;
				},
			]
		);

		return $tools;
	}
}

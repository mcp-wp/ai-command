<?php

namespace WP_CLI\AiCommand\Tools;

class FileTools {

    private $server;

	public function __construct( $server ) {
        $this->server = $server;
        $this->register_tools();
	}

    public function register_tools() {
		$this->server->register_tool(
			[
				'name'        => 'write_file',
				'description' => 'Writes a file.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [
							'type'        => 'string',
							'description' => 'The path of the file to write.',
						],
						'content' => [
							'type'        => 'string',
							'description' => 'The content of the file to write.',
						],
					],
					'required'   => [ 'path', 'content' ],
				],
				'callable'    => function ( $params ) {
					$path = $params['path'];
					$content = $params['content'];
					return file_put_contents( $path, $content );
				},
			]
		);

		$this->server->register_tool(
			[
				'name'        => 'delete_file',
				'description' => 'Deletes a file.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [
							'type'        => 'string',
							'description' => 'The path of the file to delete.',
						],
					],
					'required'   => [ 'path' ],
				],
				'callable'    => function ( $params ) {
					$path = $params['path'];
					return unlink( $path );
				},
			]
		);

		$this->server->register_tool(
			[
				'name'        => 'read_file',
				'description' => 'Reads a file.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [
							'type'        => 'string',
							'description' => 'The path of the file to read.',
						],
					],
					'required'   => [ 'path' ],
				],
				'callable'    => function ( $params ) {
					$path = $params['path'];
					return file_get_contents( $path );
				},
			]
		);

		$this->server->register_tool(
			[
				'name'        => 'move_file',
				'description' => 'Moves a file.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [
							'type'        => 'string',
								'description' => 'The path of the file to move.',
						],
						'new_path' => [
							'type'        => 'string',
							'description' => 'The new path of the file.',
						],
					],
					'required'   => [ 'path', 'new_path' ],
				],
				'callable'    => function ( $params ) {
					$path = $params['path'];
					$new_path = $params['new_path'];
					return rename( $path, $new_path );
				},
			]
		);

		$this->server->register_tool(
			[
				'name'        => 'list_files',
				'description' => 'Lists files in a directory.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [
							'type'        => 'string',
								'description' => 'The path of the directory to list files from.',
						],
					],
					'required'   => [ 'path' ],
				],
				'callable'    => function ( $params ) {
					$path = $params['path'];
					return scandir( $path );
				},
			]
		);

    }
}


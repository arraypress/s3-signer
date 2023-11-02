<?php
/**
 * These helper functions provide utilities for working with S3 Signer class.
 *
 * @package     ArrayPress/s3-signer
 * @copyright   Copyright (c) 2023, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\Utils\S3;

use Exception, InvalidArgumentException;

if ( ! function_exists( 'get_object_url' ) ) {
	/**
	 * Parse the provided arguments to generate a signed URL for an S3 object.
	 *
	 * @param array $args               {
	 *                                  An array of arguments.
	 *
	 * @type string $access_key         The S3 Access Key ID.
	 * @type string $secret_key         The S3 Secret Key.
	 * @type string $endpoint           The S3 server endpoint.
	 * @type string $region             Optional. The S3 bucket's region. Default is 'us-west-1'.
	 * @type bool   $use_path_style     Optional. Use path-style URLs. Default is true.
	 * @type string $extra_query_string Optional. Extra query strings for the URL.
	 * @type string $bucket             The S3 bucket name.
	 * @type string $object_key         The S3 object key.
	 * @type int    $period             Optional. Period for the signed URL. Default is 5.
	 *                                  }
	 *
	 * @return string|false The pre-signed S3 URL or false on failure.
	 */
	function get_object_url( array $args ) {
		$defaults = array(
			'access_key'         => '',
			'secret_key'         => '',
			'endpoint'           => '',
			'region'             => 'us-west-1',
			'use_path_style'     => true,
			'extra_query_string' => '',
			'bucket'             => '',
			'object_key'         => '',
			'period'             => 5
		);

		$args = array_merge( $defaults, $args );

		// Argument type validations
		if ( ! is_string( $args['access_key'] ) || ! is_string( $args['secret_key'] ) || ! is_string( $args['endpoint'] ) ) {
			throw new InvalidArgumentException( 'Invalid arguments provided. Access Key, Secret Key, and Endpoint are mandatory and must be strings.' );
		}

		if ( ! is_string( $args['region'] ) || ! is_bool( $args['use_path_style'] ) || ! is_string( $args['extra_query_string'] ) ) {
			throw new InvalidArgumentException( 'Invalid arguments provided. Check types for region, use_path_style, and extra_query_string.' );
		}

		$signer = new Signer( $args['access_key'], $args['secret_key'], $args['endpoint'], $args['region'], $args['use_path_style'], $args['extra_query_string'] );

		try {
			return $signer->get_object_url( $args['bucket'], $args['object_key'], $args['period'] );
		} catch ( Exception $e ) {
			if ( isset( $args['error_callback'] ) && is_callable( $args['error_callback'] ) ) {
				call_user_func( $args['error_callback'], $e );
			}

			// Handle the exception or log it if needed
			return false;
		}
	}
}
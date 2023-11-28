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

use InvalidArgumentException;

if ( ! function_exists( 'get_object_url' ) ) {
	/**
	 * Generates a pre-signed URL for accessing an S3 object, using the provided arguments.
	 *
	 * This method accepts a range of arguments for S3 authentication and URL construction,
	 * allowing optional configuration for region specificity, URL style, query string augmentation,
	 * and URL validity duration. It returns a URL string that grants temporary access to the specified S3 object.
	 * In case of an error, a callback function can be executed for custom error handling.
	 *
	 * @param array         $args           Associative array of arguments with keys:
	 *                                      - 'access_key' (string, required): S3 Access Key ID for authentication.
	 *                                      - 'secret_key' (string, required): S3 Secret Access Key corresponding to the Access Key ID.
	 *                                      - 'endpoint' (string, required): Endpoint URL of the S3 service.
	 *                                      - 'region' (string, optional): Region of the S3 bucket, defaulting to 'us-west-1'.
	 *                                      - 'use_path_style' (bool, optional): Whether to use path-style URLs, defaulting to true.
	 *                                      - 'extra_query_string' (string, optional): Extra query string parameters for the URL.
	 *                                      - 'bucket' (string, optional): Name of the S3 bucket.
	 *                                      - 'object_key' (string, optional): Key/path of the S3 object.
	 *                                      - 'duration' (int, optional): Validity period of the URL in minutes, must be positive.
	 *
	 * @param string        $bucket         Optional override for the S3 bucket name from $args.
	 * @param string        $object_key     Optional override for the S3 object key from $args.
	 * @param int|null      $duration       Optional override for the URL duration from $args.
	 * @param callable|null $error_callback Optional callback for error handling, called with the exception object as an argument.
	 *
	 * @return string|null The signed URL on success, or null on failure.
	 * @throws InvalidArgumentException If mandatory arguments are missing or invalid.
	 */
	function get_object_url( array $args, string $bucket = '', string $object_key = '', ?int $duration = null, ?callable $error_callback = null ): ?string {
		try {
			$signer = new Signer( $args );

			return $signer->get_object_url( $bucket, $object_key, $duration );
		} catch ( InvalidArgumentException $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}

			// Handle the exception or log it if needed
			return null; // Return null on failure
		}
	}
}
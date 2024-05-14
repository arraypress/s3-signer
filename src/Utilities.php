<?php
/**
 * These helper functions provide utilities for working with S3 Signer class.
 *
 * @package     ArrayPress/s3-signer
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\S3;

use InvalidArgumentException;

if ( ! function_exists( 'getObjectUrl' ) ) {
	/**
	 * Generates a pre-signed URL for accessing an S3 object.
	 *
	 * This function facilitates the creation of S3 URLs that grant temporary access to private objects
	 * without needing to provide AWS credentials directly. It supports customization of the URL's path style,
	 * validity duration, and additional query parameters.
	 *
	 * @param string        $accessKey        S3 Access Key ID for authentication.
	 * @param string        $secretKey        S3 Secret Access Key corresponding to the Access Key ID.
	 * @param string        $endpoint         Endpoint URL of the S3 service.
	 * @param string        $bucket           The S3 bucket name.
	 * @param string        $objectKey        The S3 object key.
	 * @param int           $duration         Validity period of the URL in minutes. Must be a positive integer. Defaults to 5 minutes.
	 * @param string        $extraQueryString Additional query string parameters to append to the URL. Defaults to an empty string.
	 * @param string        $region           (Optional) Region of the S3 bucket. Defaults to 'us-west-1'.
	 * @param bool          $usePathStyle     (Optional) Specifies whether to use path-style URLs. Defaults to true.
	 * @param callable|null $errorCallback    (Optional) A callback function that is called if an error occurs, with the exception object as an argument.
	 *
	 * @return string|null The signed URL on success, or null on failure.
	 * @throws InvalidArgumentException If mandatory arguments are missing or invalid.
	 *
	 * Usage Examples:
	 * - Basic usage with required parameters only:
	 *   `$signedUrl = getObjectUrl('accessKey', 'secretKey', 'endpoint', 'my-bucket', 'my-object');`
	 *
	 * - Advanced usage with all parameters:
	 *   `$signedUrl = getObjectUrl('accessKey', 'secretKey', 'endpoint', 'my-bucket', 'my-object', 60, 'user_id=123', 'us-east-1', false, function($e) { echo $e->getMessage(); });`
	 */
	function getObjectUrl(
		string $accessKey,
		string $secretKey,
		string $endpoint,
		string $bucket,
		string $objectKey,
		int $duration = 5,
		string $extraQueryString = '',
		string $region = 'us-west-1',
		bool $usePathStyle = true,
		?callable $errorCallback = null
	): ?string {
		try {
			$signer = new Signer(
				$accessKey,
				$secretKey,
				$endpoint,
				$region,
				$usePathStyle,
				$extraQueryString,
			);

			return $signer->getObjectUrl( $bucket, $objectKey, $duration );
		} catch ( InvalidArgumentException $e ) {
			if ( is_callable( $errorCallback ) ) {
				call_user_func( $errorCallback, $e );
			}

			return null; // Return null on failure
		}
	}

}
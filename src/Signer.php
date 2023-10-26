<?php
/**
 * The Signer class provides an interface to generate pre-signed S3 URLs, allowing temporary
 * access to S3 objects without requiring AWS credentials or permissions.
 *
 * This class aids in the creation of signed URLs to access resources in S3 securely. It supports both
 * path-style and virtual-hosted style URLs, with a configurable duration for URL validity.
 *
 * Key Features:
 *
 * - Path-Style and Virtual-Hosted Style URL Support: Choose between two URL formats based on
 *   requirements and bucket naming.
 * - Configurable Validity: Specify the duration for which the generated URL remains valid.
 * - Extra Query Parameters: Add extra query string parameters to the generated S3 URL.
 * - Cloudflare R2 S3 Compatibility: Designed to work with the Cloudflare R2 storage solution.
 *
 * @example
 * // Basic Usage:
 * $signer = new Signer($accessKey, $secretKey, $endpoint);
 * $signedUrl = $signer->get_object_url('my-bucket', 'path/to/my-object');
 *
 * @example
 * // With Custom Duration:
 * $signer = new Signer($accessKey, $secretKey, $endpoint);
 * $signedUrl = $signer->get_object_url('my-bucket', 'path/to/my-object', 60); // 1 hour validity
 *
 * This class offers a streamlined process for obtaining secure access to S3 resources, ideal for
 * applications requiring temporary access or sharing links.
 *
 * @package     arraypress/s3-signer
 * @copyright   Copyright (c) 2023, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 * @description Generates pre-signed S3 URLs for temporary object access.
 */

namespace ArrayPress\Utils\S3;

use InvalidArgumentException;

/**
 * Check if the class `Signer` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Signer' ) ) :

	/**
	 * Signer
	 *
	 * Provides an interface to generate pre-signed S3 URLs.
	 */
	class Signer {

		/**
		 * The S3 Access Key ID used to identify the account making the request.
		 *
		 * @var string
		 */
		private $access_key;

		/**
		 * The S3 Secret Key associated with the access key for request authentication.
		 *
		 * @var string
		 */
		private $secret_key;

		/**
		 * The endpoint to which S3 requests are sent.
		 *
		 * @var string
		 */
		private $endpoint;

		/**
		 * The region where the S3 bucket resides.
		 *
		 * Regions represent the physical locations in the world where AWS data centers are clustered.
		 * For example, 'us-west-1' refers to the US West (North California) region.
		 * Note: CloudFlare R2 typically uses 'auto' as the default region.
		 *
		 * @var string
		 */
		private $region = 'us-west-1';

		/**
		 * Specifies whether to use path-style or virtual-hosted style for the S3 URL.
		 *
		 * Path-style URLs use the format: https://s3.amazonaws.com/BUCKET/KEY
		 * Virtual-hosted style URLs use the format: https://BUCKET.s3.amazonaws.com/KEY
		 *
		 * In this context:
		 * - BUCKET: represents the name of the S3 bucket.
		 * - KEY: represents the object key or path within the bucket.
		 *
		 * For example:
		 * Path-style: https://s3.amazonaws.com/my-bucket/my-object
		 * Virtual-hosted style: https://my-bucket.s3.amazonaws.com/my-object
		 *
		 * AWS started transitioning to the virtual-hosted style as the default
		 * since it provides better routing performance. However, path-style can
		 * still be used especially for buckets created before the transition or
		 * when bucket names aren't DNS-compliant.
		 *
		 * @var bool
		 */
		private $use_path_style = true;

		/**
		 * Any extra query string to be appended to the S3 URL.
		 *
		 * @var string
		 */
		private $extra_query_string = '';

		/**
		 * The S3 bucket name.
		 *
		 * @var string
		 */
		private $bucket;

		/**
		 * The key or path of the object in the bucket.
		 *
		 * @var string
		 */
		private $object;

		/**
		 * Duration for which the pre-signed URL should remain valid, in minutes.
		 *
		 * @var int
		 */
		private $period = 5;  // Default to 5 minutes

		/**
		 * Unix timestamp indicating when the current request was made.
		 *
		 * @var int
		 */
		private $time;

		/**
		 * Initializes the Signer class properties.
		 *
		 * @param string $access_key         The S3 Access Key ID.
		 * @param string $secret_key         The S3 Secret Key.
		 * @param string $endpoint           The S3 server endpoint.
		 * @param string $region             Optional. The S3 bucket's region. Default is 'us-west-1'.
		 * @param bool   $use_path_style     Optional. Use path-style URLs. Default is true.
		 * @param string $extra_query_string Optional. Extra query strings for the URL.
		 *
		 * @throws InvalidArgumentException If the Access Key, Secret Key, or Endpoint are empty.
		 */
		public function __construct( string $access_key, string $secret_key, string $endpoint, string $region = 'us-west-1', bool $use_path_style = true, string $extra_query_string = '' ) {
			if ( empty( $access_key ) || empty( $secret_key ) || empty( $endpoint ) ) {
				throw new InvalidArgumentException( 'Invalid arguments provided. Access Key, Secret Key, and Endpoint are mandatory.' );
			}

			$this->access_key         = trim( $access_key );
			$this->secret_key         = trim( $secret_key );
			$this->endpoint           = trim( $endpoint );
			$this->region             = trim( $region );
			$this->use_path_style     = $use_path_style;
			$this->extra_query_string = $extra_query_string;
		}

		/**
		 * Returns a pre-signed S3 URL for a specified object or file.
		 *
		 * This method generates a signed URL that provides temporary access to an S3 object
		 * without requiring the user to have AWS credentials or permissions.
		 *
		 * There are two styles for S3 URLs:
		 * 1. Path-style: `https://[endpoint]/[bucket]/[object]`
		 * 2. Virtual-hosted style: `https://[bucket].[endpoint]/[object]`
		 *
		 * The `use_path_style` property determines which style to use.
		 *
		 * @param string $bucket The name of the S3 bucket containing the object.
		 * @param string $object The key of the object within the bucket. This can be the filename or path.
		 * @param int    $period Optional. Duration for which the generated URL should be valid, in minutes. Default is 5
		 *                       minutes.
		 *
		 * @return string The pre-signed S3 URL.
		 * @throws InvalidArgumentException If the bucket or object name provided is empty or invalid.
		 */
		public function get_object_url( string $bucket, string $object, int $period = 5 ) {

			$this->time   = time();
			$this->bucket = trim( $bucket );
			$this->object = $this->encode_object_name( $object );
			$this->period = $this->convert_period_to_seconds( $period );

			// Validate bucket and object.
			if ( empty( $this->bucket ) ) {
				throw new InvalidArgumentException( "The bucket name provided is empty or invalid." );
			}

			if ( empty( $this->object ) ) {
				throw new InvalidArgumentException( "The object name provided is empty or invalid." );
			}

			if ( $this->use_path_style ) {
				$url = 'https://' . $this->endpoint . '/' . $this->bucket . '/' . $this->object . '?';
			} else {
				$url = 'https://' . $this->bucket . '.' . $this->endpoint . '/' . $this->object . '?';
			}

			$url .= $this->get_query_strings();
			$url .= '&X-Amz-Signature=' . $this->generate_signature();

			return $url;
		}

		/**
		 * Converts a given period in minutes to seconds.
		 *
		 * @param int $period The period in minutes.
		 *
		 * @return int Returns the period in seconds.
		 */
		private function convert_period_to_seconds( int $period ): int {
			return $period * 60;
		}

		/**
		 * Return the "canonical request" for the current S3 API request
		 *
		 * @return string
		 */
		private function get_canonical_request(): string {
			$request = "GET\n";

			if ( $this->use_path_style ) {
				$request .= '/' . $this->bucket . '/' . $this->object . "\n";
			} else {
				$request .= '/' . $this->object . "\n";
			}

			$request .= $this->get_query_strings() . "\n";

			if ( $this->use_path_style ) {
				$request .= 'host:' . $this->endpoint . "\n\n";
			} else {
				$request .= 'host:' . $this->bucket . '.' . $this->endpoint . "\n\n";
			}

			$request .= "host\n";
			$request .= 'UNSIGNED-PAYLOAD';

			return $request;
		}

		/**
		 * Generates our list of query strings to generate a signature from
		 *
		 * @return string
		 */
		private function get_query_strings(): string {
			$url = 'X-Amz-Algorithm=AWS4-HMAC-SHA256';
			$url .= '&X-Amz-Credential=' . urlencode( $this->access_key . '/' . $this->get_credential() );
			$url .= '&X-Amz-Date=' . gmdate( 'Ymd\THis\Z', $this->time );
			$url .= '&X-Amz-Expires=' . $this->period;
			$url .= '&X-Amz-SignedHeaders=host';

			if ( ! empty( $this->extra_query_string ) ) {
				$url .= '&' . $this->extra_query_string . '=';
			}

			return $url;
		}

		/**
		 * Returns the "credential" line for signed Amazon S3 requests
		 *
		 * @return string
		 */
		private function get_credential(): string {
			$credential = date( 'Ymd', $this->time ) . '/';
			$credential .= $this->region . '/s3/aws4_request';

			return $credential;
		}

		/**
		 * Generates the actual string/data we are signing
		 *
		 * @return string
		 */
		private function get_string_to_sign(): string {
			$string = 'AWS4-HMAC-SHA256' . "\n";
			$string .= gmdate( 'Ymd\THis\Z', $this->time ) . "\n";
			$string .= $this->get_credential() . "\n";
			$string .= $this->hex16( hash( 'sha256', $this->get_canonical_request(), true ) );

			return $string;
		}

		/**
		 * Base16 Hex
		 *
		 * @param $value
		 *
		 * @return string
		 */
		private function hex16( $value ): string {
			$result = unpack( 'H*', $value );

			return reset( $result );
		}

		/**
		 * Generates our final signature using a signing key and get_string_to_sign
		 *
		 * @return string
		 */
		private function generate_signature(): string {
			$date_key                = hash_hmac( 'sha256', date( 'Ymd', $this->time ), 'AWS4' . $this->secret_key, true );
			$date_region_key         = hash_hmac( 'sha256', $this->region, $date_key, true );
			$date_region_service_key = hash_hmac( 'sha256', 's3', $date_region_key, true );
			$signing_key             = hash_hmac( 'sha256', 'aws4_request', $date_region_service_key, true );
			$string_to_sign          = $this->get_string_to_sign();

			return $this->hex16( hash_hmac( 'sha256', $string_to_sign, $signing_key, true ) );
		}

		/**
		 * Raw URL encode a key and allow for '/' characters
		 *
		 * @param string $key Key to encode
		 *
		 * @return string Returns the encoded key
		 */
		private function encode_object_name( string $key ): string {
			$key = str_replace( '+', ' ', $key );

			return str_replace( '%2F', '/', rawurlencode( $key ) );
		}

	}

endif;
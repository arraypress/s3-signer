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
		private string $access_key;

		/**
		 * The S3 Secret Key associated with the access key for request authentication.
		 *
		 * @var string
		 */
		private string $secret_key;

		/**
		 * The endpoint to which S3 requests are sent.
		 *
		 * @var string
		 */
		private string $endpoint;

		/**
		 * The region where the S3 bucket resides.
		 *
		 * Regions represent the physical locations in the world where AWS data centers are clustered.
		 * For example, 'us-west-1' refers to the US West (North California) region.
		 * Note: CloudFlare R2 typically uses 'auto' as the default region.
		 *
		 * @var string
		 */
		private string $region = 'us-west-1';

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
		private bool $use_path_style = true;

		/**
		 * Any extra query string to be appended to the S3 URL.
		 *
		 * @var string
		 */
		private string $extra_query_string = '';

		/**
		 * The S3 bucket name.
		 *
		 * @var string
		 */
		private string $bucket;

		/**
		 * The key or path of the object in the bucket.
		 *
		 * @var string
		 */
		private string $object_key;

		/**
		 * Duration for which the pre-signed URL should remain valid, in minutes.
		 *
		 * @var int
		 */
		private int $duration = 5;  // Default to 5 minutes

		/**
		 * Unix timestamp indicating when the current request was made.
		 *
		 * @var int
		 */
		private int $time;

		/**
		 * Constructor for the Signer class, responsible for initializing properties
		 * related to Amazon S3 service interactions.
		 *
		 * This constructor sets up the necessary properties required for generating pre-signed
		 * S3 URLs and for handling other S3 related operations. It accepts an array of
		 * arguments and merges them with default values. Each argument is validated for
		 * type and value correctness. If any required argument is missing or invalid,
		 * an InvalidArgumentException is thrown.
		 *
		 * @param array $args Associative array of arguments with the following keys:
		 *                    - 'access_key' (string): Mandatory. The S3 Access Key ID used for authentication.
		 *                    - 'secret_key' (string): Mandatory. The S3 Secret Key associated with the access key.
		 *                    - 'endpoint' (string): Mandatory. The S3 server endpoint URL.
		 *                    - 'region' (string): Optional. The region where the S3 bucket resides. Default is 'us-west-1'.
		 *                    - 'use_path_style' (bool): Optional. Flag to indicate whether to use path-style URLs. Default is true.
		 *                    - 'extra_query_string' (string): Optional. Additional query string parameters to append to the URL.
		 *                    - 'bucket' (string): Optional. The name of the S3 bucket.
		 *                    - 'object_key' (string): Optional. The key/path of the object within the S3 bucket.
		 *                    - 'duration' (int): Optional. Duration in minutes for which the pre-signed URL is valid. Must be a positive integer.
		 *
		 * @throws InvalidArgumentException If any mandatory argument is empty or if an argument is of an incorrect type.
		 */
		public function __construct( array $args ) {
			$defaults = [
				'access_key'         => '',
				'secret_key'         => '',
				'endpoint'           => '',
				'region'             => 'us-west-1',
				'use_path_style'     => true,
				'extra_query_string' => '',
				'bucket'             => '',
				'object_key'         => '',
				'duration'           => 5
			];

			$args = array_merge( $defaults, $args );

			// Validate each argument individually
			if ( empty( trim( $args['access_key'] ) ) ) {
				throw new InvalidArgumentException( "Access Key is required and cannot be empty." );
			}

			if ( empty( trim( $args['secret_key'] ) ) ) {
				throw new InvalidArgumentException( "Secret Key is required and cannot be empty." );
			}

			if ( empty( trim( $args['endpoint'] ) ) ) {
				throw new InvalidArgumentException( "Endpoint is required and cannot be empty." );
			}

			if ( ! is_string( $args['region'] ) || empty( trim( $args['region'] ) ) ) {
				throw new InvalidArgumentException( "Region must be a non-empty string." );
			}

			if ( ! is_bool( $args['use_path_style'] ) ) {
				throw new InvalidArgumentException( "use_path_style must be a boolean." );
			}

			if ( ! is_string( $args['extra_query_string'] ) ) {
				throw new InvalidArgumentException( "extra_query_string must be a string." );
			}

			if ( ! is_string( $args['bucket'] ) ) {
				throw new InvalidArgumentException( "Bucket must be a string." );
			}

			if ( ! is_string( $args['object_key'] ) ) {
				throw new InvalidArgumentException( "Object key must be a string." );
			}

			if ( ! is_int( $args['duration'] ) || $args['duration'] <= 0 ) {
				throw new InvalidArgumentException( "Invalid duration value. It should be a positive integer representing a duration." );
			}

			// Assign validated values to class properties
			$this->access_key         = trim( $args['access_key'] );
			$this->secret_key         = trim( $args['secret_key'] );
			$this->endpoint           = trim( $args['endpoint'] );
			$this->region             = trim( $args['region'] );
			$this->use_path_style     = $args['use_path_style'];
			$this->extra_query_string = $args['extra_query_string'];
			$this->bucket             = trim( $args['bucket'] );
			$this->object_key         = trim( $args['object_key'] );
			$this->duration           = $args['duration'];
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
		 * @param string   $bucket     Optional. The name of the S3 bucket containing the object.
		 * @param string   $object_key Optional. The key of the object within the bucket.
		 * @param int|null $duration   Optional. Duration for which the generated URL should be valid, in minutes.
		 *
		 * @return string The pre-signed S3 URL.
		 * @throws InvalidArgumentException If the bucket or object name provided is empty or invalid.
		 */
		public function get_object_url( string $bucket = '', string $object_key = '', ?int $duration = null ): string {
			$this->time = time();

			$this->bucket     = ! empty( $bucket ) ? trim( $bucket ) : $this->bucket;
			$this->object_key = ! empty( $object_key ) ? trim( $object_key ) : $this->object_key;

			$this->object_key = $this->encode_object_name( $this->object_key );
			$this->duration   = $this->convert_duration_to_seconds( $duration !== null ? $duration : $this->duration );

			// Validate bucket and object.
			if ( empty( $this->bucket ) ) {
				throw new InvalidArgumentException( "The bucket name provided is empty or invalid." );
			}

			if ( empty( $this->object_key ) ) {
				throw new InvalidArgumentException( "The object name provided is empty or invalid." );
			}

			if ( $this->use_path_style ) {
				$url = 'https://' . $this->endpoint . '/' . $this->bucket . '/' . $this->object_key . '?';
			} else {
				$url = 'https://' . $this->bucket . '.' . $this->endpoint . '/' . $this->object_key . '?';
			}

			$url .= $this->get_query_strings();
			$url .= '&X-Amz-Signature=' . $this->generate_signature();

			return $url;
		}

		/**
		 * Converts a given duration in minutes to seconds.
		 *
		 * @param int $duration The duration in minutes.
		 *
		 * @return int Returns the duration in seconds.
		 */
		protected function convert_duration_to_seconds( int $duration ): int {
			return $duration * 60;
		}

		/**
		 * Return the "canonical request" for the current S3 API request
		 *
		 * @return string
		 */
		protected function get_canonical_request(): string {
			$request = "GET\n";

			if ( $this->use_path_style ) {
				$request .= '/' . $this->bucket . '/' . $this->object_key . "\n";
			} else {
				$request .= '/' . $this->object_key . "\n";
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
		protected function get_query_strings(): string {
			$url = 'X-Amz-Algorithm=AWS4-HMAC-SHA256';
			$url .= '&X-Amz-Credential=' . urlencode( $this->access_key . '/' . $this->get_credential() );
			$url .= '&X-Amz-Date=' . gmdate( 'Ymd\THis\Z', $this->time );
			$url .= '&X-Amz-Expires=' . $this->duration;
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
		protected function get_credential(): string {
			$credential = date( 'Ymd', $this->time ) . '/';
			$credential .= $this->region . '/s3/aws4_request';

			return $credential;
		}

		/**
		 * Generates the actual string/data we are signing
		 *
		 * @return string
		 */
		protected function get_string_to_sign(): string {
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
		protected function hex16( $value ): string {
			$result = unpack( 'H*', $value );

			return reset( $result );
		}

		/**
		 * Generates our final signature using a signing key and get_string_to_sign
		 *
		 * @return string
		 */
		protected function generate_signature(): string {
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
		protected function encode_object_name( string $key ): string {
			$key = str_replace( '+', ' ', $key );

			return str_replace( '%2F', '/', rawurlencode( $key ) );
		}

	}

endif;
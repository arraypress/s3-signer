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
 * $signer = new Signer( $accessKey, $secretKey, $endpoint ) ;
 * $signedUrl = $signer->getObjectUrl( 'my-bucket', 'path/to/my-object' );
 *
 * @example
 * // With Custom Duration:
 * $signer = new Signer( $accessKey, $secretKey, $endpoint ;
 * $signedUrl = $signer->getObjectUrl( 'my-bucket', 'path/to/my-object', 60 ); // 1 hour validity
 *
 * This class offers a streamlined process for obtaining secure access to S3 resources, ideal for
 * applications requiring temporary access or sharing links.
 *
 * @package       arraypress/s3-signer
 * @copyright     Copyright (c) 2024, ArrayPress Limited
 * @license       GPL2+
 * @version       0.1.0
 * @author        David Sherlock
 * @description   Generates pre-signed S3 URLs for temporary object access.
 */

declare( strict_types=1 );

namespace ArrayPress\S3\Signer;

use ArrayPress\S3\Utils\Serialization;
use ArrayPress\S3\Utils\Validate;
use InvalidArgumentException;
use function gmdate;
use function hash;
use function hash_hmac;
use function reset;
use function time;
use function trim;
use function unpack;
use function urlencode;

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
	private string $accessKey;

	/**
	 * The S3 Secret Key associated with the access key for request authentication.
	 *
	 * @var string
	 */
	private string $secretKey;

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
	private string $region;

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
	private bool $usePathStyle;

	/**
	 * Any extra query string to be appended to the S3 URL.
	 *
	 * @var string
	 */
	private string $extraQueryString;

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
	private string $objectKey;

	/**
	 * Duration for which the pre-signed URL should remain valid, in minutes.
	 *
	 * @var int
	 */
	private int $duration;  // Default to 5 minutes

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
	 * @param string $accessKey        The S3 Access Key ID used for authentication.
	 * @param string $secretKey        The S3 Secret Key associated with the access key.
	 * @param string $endpoint         The S3 server endpoint URL.
	 * @param string $region           The region where the S3 bucket resides. Default is 'us-west-1'.
	 * @param bool   $usePathStyle     Flag to indicate whether to use path-style URLs. Default is true.
	 * @param string $extraQueryString Additional query string parameters to append to the URL.
	 *
	 * @throws InvalidArgumentException If any mandatory argument is empty or if an argument is of an incorrect type.
	 */
	public function __construct(
		string $accessKey,
		string $secretKey,
		string $endpoint,
		string $region = 'us-west-1',
		bool $usePathStyle = true,
		string $extraQueryString = ''
	) {
		$this->setAccessKey( $accessKey );
		$this->setSecretKey( $secretKey );
		$this->setEndpoint( $endpoint );
		$this->setRegion( $region );
		$this->setPathStyle( $usePathStyle );
		$this->setExtraQueryString( $extraQueryString );
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
	 * The `usePathStyle` property determines which style to use.
	 *
	 * @param string   $bucket    The name of the S3 bucket containing the object.
	 * @param string   $objectKey The key of the object within the bucket.
	 * @param int|null $duration  Optional. Duration for which the generated URL should be valid, in minutes.
	 *
	 * @return string The pre-signed S3 URL.
	 * @throws InvalidArgumentException If the bucket or object name is empty or invalid.
	 */
	public function getObjectUrl( string $bucket, string $objectKey, int $duration = 5 ): string {
		$this->time = time();

		$this->bucket    = trim( $bucket );
		$this->objectKey = trim( $objectKey );
		$this->duration  = $duration;

		// Check if $bucket and $objectKey are empty.
		if ( empty( $this->bucket ) || empty( $this->objectKey ) ) {
			throw new InvalidArgumentException( 'Bucket and object name must not be empty.' );
		}

		// Validate bucket, object and duration.
		Validate::bucket( $this->bucket );
		Validate::objectKey( $this->objectKey );
		Validate::duration( $this->duration );

		$this->objectKey = Serialization::encodeObjectName( $this->objectKey );

		// Convert duration to seconds directly as the default value is already ensured by the method signature
		$this->duration *= 60;

		$url = $this->usePathStyle
			? 'https://' . $this->endpoint . '/' . $this->bucket . '/' . $this->objectKey . '?'
			: 'https://' . $this->bucket . '.' . $this->endpoint . '/' . $this->objectKey . '?';

		$url .= $this->getQueryStrings();
		$url .= '&X-Amz-Signature=' . $this->generateSignature();

		return $url;
	}

	/**
	 * Sets the AWS S3 Access Key ID.
	 *
	 * @param string $accessKey The AWS Access Key ID.
	 */
	public function setAccessKey( string $accessKey ): void {
		Validate::accessKey( $accessKey ); // Validate the access key
		$this->accessKey = trim( $accessKey );
	}

	/**
	 * Sets the AWS S3 Secret Access Key.
	 *
	 * @param string $secretKey The AWS Secret Access Key.
	 */
	public function setSecretKey( string $secretKey ): void {
		Validate::secretKey( $secretKey ); // Validate the secret key
		$this->secretKey = trim( $secretKey );
	}

	/**
	 * Sets the endpoint URL for S3 requests.
	 *
	 * @param string $endpoint The new endpoint URL.
	 */
	public function setEndpoint( string $endpoint ): void {
		$this->endpoint = trim( $endpoint );
		Validate::endpoint( $this->endpoint ); // Ensure the new endpoint is valid
	}

	/**
	 * Sets the AWS S3 region.
	 *
	 * @param string $region The AWS region where the S3 bucket resides.
	 */
	public function setRegion( string $region ): void {
		$this->region = trim( $region );
		Validate::region( $this->region ); // Ensure the new region is valid
	}

	/**
	 * Sets the URL style for the S3 request.
	 *
	 * @param bool $usePathStyle Indicates whether to use path-style URLs.
	 */
	public function setPathStyle( bool $usePathStyle ): void {
		$this->usePathStyle = $usePathStyle;
	}

	/**
	 * Sets extra query string parameters to be appended to the S3 URL.
	 *
	 * @param string $extraQueryString The extra query string parameters.
	 */
	public function setExtraQueryString( string $extraQueryString ): void {
		$this->extraQueryString = trim( $extraQueryString );
		Validate::extraQueryString( $this->extraQueryString ); // Ensure the new query string is valid
	}

	/**
	 * Return the "canonical request" for the current S3 API request
	 *
	 * @return string
	 */
	protected function getCanonicalRequest(): string {
		$request = "GET\n";

		$request .= $this->usePathStyle
			? '/' . $this->bucket . '/' . $this->objectKey . "\n"
			: '/' . $this->objectKey . "\n";

		$request .= $this->getQueryStrings() . "\n";

		$request .= $this->usePathStyle
			? 'host:' . $this->endpoint . "\n\n"
			: 'host:' . $this->bucket . '.' . $this->endpoint . "\n\n";

		$request .= "host\n";
		$request .= 'UNSIGNED-PAYLOAD';

		return $request;
	}

	/**
	 * Generates our list of query strings to generate a signature from
	 *
	 * @return string
	 */
	protected function getQueryStrings(): string {
		$url = 'X-Amz-Algorithm=AWS4-HMAC-SHA256';
		$url .= '&X-Amz-Credential=' . urlencode( $this->accessKey . '/' . $this->getCredential() );
		$url .= '&X-Amz-Date=' . gmdate( 'Ymd\THis\Z', $this->time );
		$url .= '&X-Amz-Expires=' . $this->duration;
		$url .= '&X-Amz-SignedHeaders=host';

		if ( ! empty( $this->extraQueryString ) ) {
			$url .= '&' . $this->extraQueryString . '=';
		}

		return $url;
	}

	/**
	 * Returns the "credential" line for signed Amazon S3 requests
	 *
	 * @return string
	 */
	protected function getCredential(): string {
		$credential = gmdate( 'Ymd', $this->time ) . '/';
		$credential .= $this->region . '/s3/aws4_request';

		return $credential;
	}

	/**
	 * Generates the actual string/data we are signing
	 *
	 * @return string
	 */
	protected function getStringToSign(): string {
		$string = 'AWS4-HMAC-SHA256' . "\n";
		$string .= gmdate( 'Ymd\THis\Z', $this->time ) . "\n";
		$string .= $this->getCredential() . "\n";
		$string .= $this->hex16( hash( 'sha256', $this->getCanonicalRequest(), true ) );

		return $string;
	}

	/**
	 * Base16 Hex
	 *
	 * @param string $value The value to convert to hexadecimal.
	 *
	 * @return string The hexadecimal representation of the input value.
	 */
	protected function hex16( string $value ): string {
		$result = unpack( 'H*', $value );

		return reset( $result );
	}

	/**
	 * Generates our final signature using a signing key and get_string_to_sign
	 *
	 * @return string
	 */
	protected function generateSignature(): string {
		$dateKey              = hash_hmac( 'sha256', gmdate( 'Ymd', $this->time ), 'AWS4' . $this->secretKey, true );
		$dateRegionKey        = hash_hmac( 'sha256', $this->region, $dateKey, true );
		$dateRegionServiceKey = hash_hmac( 'sha256', 's3', $dateRegionKey, true );
		$signingKey           = hash_hmac( 'sha256', 'aws4_request', $dateRegionServiceKey, true );
		$stringToSign         = $this->getStringToSign();

		return $this->hex16( hash_hmac( 'sha256', $stringToSign, $signingKey, true ) );
	}

}
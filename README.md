# S3 Signer Library for Pre-signed URLs

The S3 Signer Library streamlines generating pre-signed URLs for Amazon S3 objects, facilitating secure, temporary access without directly exposing AWS credentials. This library is essential for applications requiring secure, time-limited access to S3 objects, such as sharing private files or providing temporary download links.

## Key Features

- **Support for Path-Style and Virtual-Hosted Style URLs:** Offers flexibility in URL format to accommodate various bucket naming conventions and requirements.
- **Configurable URL Validity:** Customize the duration for which the pre-signed URL remains valid, from minutes to days.
- **Extra Query Parameters:** Append additional query parameters to the generated URLs for fine-grained control over access.
- **Compatibility with S3 and S3-like Services:** Designed to work seamlessly not just with AWS S3, but also with S3-compatible services such as Cloudflare R2, making it versatile for different storage solutions.

## Installation

Use Composer to install the S3 Signer Library into your project:

```bash
composer require arraypress/s3-signer
```

## Quick Start

After installation, include the Composer autoloader in your PHP script:

```php
require 'vendor/autoload.php';
```

Create an instance of the `Signer` class with your S3 credentials and endpoint:

```php
use ArrayPress\S3\Signer;

$accessKey = 'your-access-key-id';
$secretKey = 'your-secret-access-key';
$endpoint = '{account_id}.r2.cloudflarestorage.com'; // Or your S3-compatible service endpoint
$region = 'auto';

$signer = new Signer( $accessKey, $secretKey, $endpoint, $region );
```

Generate a pre-signed URL for an S3 object:

```php
$bucket = 'your-bucket-name';
$objectKey = 'mydownload.zip';
$duration = 60; // URL is valid for 60 minutes

$signedUrl = $signer->getObjectUrl( $bucket, $objectKey, $duration );

echo "Pre-Signed URL: $signedUrl\n";
```

## Advanced Usage

You can customize the behavior of the `Signer` class further by using the available setter methods:

- **setRegion:** Define the S3 bucket's region.
- **setPathStyle:** Toggle between path-style and virtual-hosted-style URLs.
- **setExtraQueryString:** Append extra query parameters for advanced use cases.

Example setting the region and using path-style URLs:

```php
$signer->setRegion( 'us-west-2' );
$signer->setPathStyle( true );
```

## Supported Providers

This library supports generating pre-signed URLs for AWS S3 and other S3-compatible storage solutions adhering to the SigV4 signing process. Supported providers include:

* **AWS S3:** The original and most comprehensive cloud storage service.
* **Cloudflare R2:** Offers compatibility with S3 APIs and competitive pricing.
* **DigitalOcean Spaces:** Provides simple, scalable storage with S3-compatible APIs.
* **Linode Object Storage:** Offers S3-compatible storage for storing and accessing data.
* **And more:** Any S3-compatible service using SigV4 can work with this library.

## Using the getObjectUrl Helper Function

You can also use the `getObjectUrl` helper function for a more straightforward approach to generate pre-signed URLs:

```php
$signedUrl = getObjectUrl(
    'your-access-key-id',
    'your-secret-access-key',
    's3.amazonaws.com',
    'your-bucket-name',
    'path/to/your/object',
    60, // Duration in minutes
    '', // Extra query string
    'us-west-2', // Region
    true // Use path style
);

echo "Pre-Signed URL using helper function: $signedUrl\n";
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug
fixes or new features. Share feedback and suggestions for improvements.

## License: GPLv2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
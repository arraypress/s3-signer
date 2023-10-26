#  S3 URL Signing Library

The `Signer` class is designed to streamline the process of generating pre-signed S3 URLs. These URLs grant temporary access to S3 objects without the need for AWS credentials or permissions. This is especially useful for applications that require short-term access or sharing links to resources stored in an S3 bucket.

**Key Features:**

* **Path-Style and Virtual-Hosted Style URLs:** The library supports both URL formats, accommodating different requirements and bucket naming conventions.
* **Configurable URL Validity:** You can set the duration for which the generated URL remains valid.
* **Extra Query Parameters:** Enhance the generated S3 URL by appending extra query string parameters.
* **Expansive S3 Compatibility:** Not just limited to Cloudflare R2, the class is meticulously engineered to synchronize with a plethora of S3-Compatible storage solutions like Linode, DigitalOcean Spaces, BackBlaze, and more.

## Installation and set up

The extension in question needs to have a `composer.json` file, specifically with the following:

```json 
{
  "require": {
    "arraypress/s3-signer": "*"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/arraypress/s3-signer"
    }
  ]
}
```

Once set up, run `composer install --no-dev`. This should create a new `vendors/` folder
with `arraypress/s3-signer/` inside.

## Utilizing the S3 URL Presigning Tool

The `Signer` class empowers you to generate secure, pre-signed URLs for objects stored on any S3-compatible storage provider, including CloudFlare R2 and others. This tool makes it seamless to share private content for a temporary duration. Here's a step-by-step guide to harness its capabilities:

### Including the Vendor Library

Before using the `Signer` class, you need to include the Composer-generated autoload file. This file ensures that the required dependencies and classes are loaded into your PHP script. You can include it using the following code:

```php 
// Include the Composer-generated autoload file.
require_once dirname(__FILE__) . '/vendor/autoload.php';
```

### Generating Pre-signed URLs for CloudFlare R2

```php
$access_key = 'YOUR_R2_ACCESS_KEY';   // Update with your actual CloudFlare R2 access key
$secret_key = 'YOUR_R2_SECRET_KEY';   // Update with your actual CloudFlare R2 secret key
$endpoint   = '{account_id}.r2.cloudflarestorage.com'; // Use your specific R2 account ID here
$region     = 'auto'; // For CloudFlare, set region as 'auto' when creating pre-signed URLs

$signer = new ArrayPress\Utils\S3\Signer( $access_key, $secret_key, $endpoint, $region );

$bucket_name = 'my-bucket';          // Input your desired bucket name here
$object_path = 'sample-file.zip';    // Specify the object's path you want to share

// Creating a pre-signed URL with a standard 5-minute expiration
$signed_url = $signer->get_object_url( $bucket_name, $object_path );
echo "Your Pre-Signed URL is: " . $signed_url . "\n";

// Creating a pre-signed URL with a 2-hour expiration
$signed_url = $signer->get_object_url( $bucket_name, $object_path, 120 );
echo "Generated Pre-Signed URL with 2 hours validity: " . $signed_url . "\n";
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug
fixes or new features. Share feedback and suggestions for improvements.

## License

This library is licensed under
the [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).
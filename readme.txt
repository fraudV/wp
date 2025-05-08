=== Squeeze ===
Contributors: barb0ss
Tags: image compression, convert webp, image optimization, compress images, optimize images
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Squeeze plugin optimizes and compresses images on your website directly in your browser. No extra server used, no limits for images compression.

== Description ==
Boost your site speed and performance by reducing the sizes of your images!
Squeeze plugin optimizes and compresses images on your website directly in your browser. No extra server used, no limits for images compression.

== How does the Squeeze plugin work? == 
Squeeze plugin compresses images directly from your WordPress Media Library or during the image upload process. All the work is handled directly inside your browser using advanced compression algorithms. That means there’s no third party service for compression images. Thus, you can be sure with the privacy of your images, ensuring data privacy and faster processing.
== Key Features == 
* **Performance Boost:** Reduces image sizes to improve website loading speed and overall user experience.
* **WEBP Conversion and Serving:** Convert images to the modern and efficient WEBP format and serve them seamlessly. The plugin adds .htaccess rules to serve WEBP images for supported browsers without changing URLs. Alternatively, enable the "Replace Image URLs" option to explicitly use WEBP versions on all pages.
* **Client-Side Image Squeezing:** Compress images directly in the browser without sending files to external servers
* **Upload Optimization:** Compresses images on-the-fly during the upload process, ensuring optimized images are added to your media library.
* **Bulk Compression:** Allows you to compress multiple images at once from your WordPress Media Library.
* **Custom Path Compression:** Select a folder on your site and compress all the images within that folder.
* **Selective Compression:** Choose which images to compress based on your preferences and requirements.
* **Custom Squeezing Settings:** Adjust compression parameters such as quality level to suit your specific needs.
* **Backup Option:** Creates a backup file to restore a compressed image to the original image.
* **Wide Format Support:** Squeezes images in popular formats, including JPEG, PNG, AVIF, and WebP.

== Installation ==
1. Download the plugin ZIP file from the WordPress Plugin Directory, or install the plugin via the WordPress plugin installer.
2. Extract the ZIP file (if downloaded from WordPress Plugin Directory).
3. Upload the plugin folder to the wp-content/plugins/ directory of your WordPress installation.
4. Activate the Squeeze plugin from the WordPress plugins dashboard.

== Frequently Asked Questions ==
= How does the plugin work? =

The plugin uses client-side compression algorithms and provides you with the ability to compress images in your WordPress media library or during the image upload process.

= What does the speed of image compression depend on? =

Because the compression process happens directly into your browser - it depends on your device’s performance. 

= Image compression process seems to be stuck. The browser doesn't respond. =

It may happen if you are trying to compress a PNG image with a high resolution. In that case, you should wait a while, until the image finishes its compression or a “Request timed out.” error message occurs.

= How to fix a “Request timed out” error =

Go to the plugin’s setting page (Settings -> Squeeze).
At the “Basic Settings” tab increase the value of the field “Squeeze timeout”. By default it equals 60 seconds, try to make it bigger. If the error still persists, that means the script cannot process your image. 

= How are the images processed? Are they sent to an external server? =

The images compressed directly in your browser – means no external server used. Squeeze does all the work locally. So you should not worry about privacy.

= Can this plugin convert images to the WEBP format? =

Yes! The Squeeze plugin supports converting images to the WEBP format during the compression process. WEBP is a modern image format that provides superior compression while maintaining high quality, resulting in faster loading times and reduced bandwidth usage. To enable this feature, simply check the "Generate WEBP" option under the Basic Settings tab to output images in WEBP format.

= How are WEBP images served? =

By default, the Squeeze plugin adds rules to your .htaccess file to serve WEBP images automatically. This means that when a browser supports the WEBP format, the corresponding WEBP version of an image will be served without changing the original image's URL. This allows seamless integration without disrupting your website's existing structure.
If you enable the "Replace Images URLs" option in the plugin settings, the plugin will directly replace all image URLs on your pages with their corresponding WEBP versions. This can be useful for ensuring that WEBP images are explicitly used everywhere on your site.
The generated WEBP images are stored in the wp-content/squeeze-webp folder within your WordPress installation.

= Which scripts are used for compressing and converting images?

The Squeeze plugin utilizes the same scripts and libraries that power the Squoosh.app, an open-source project by the Google Chrome team. These include highly efficient image processing libraries such as:

* MozJPEG: Used for compressing JPEG images.
* OxiPNG: Used for compressing PNG images.
* WebP: Used for converting and compressing images to the modern WEBP format.
* AVIF: An advanced codec for creating lightweight and high-quality AVIF images.

= Why should I use image compression on my website? =

Image compression helps improve your website's performance by reducing the file size of images without significantly impacting their quality. Smaller image files load faster, resulting in faster page load times and a better user experience. Additionally, compressed images consume less bandwidth, which can be beneficial for websites with limited hosting resources or mobile users with limited data plans.

= Which image formats does the Squeeze plugin work with? =

Squeeze plugin supports JPG, PNG, WEBP and AVIF image formats.

= Can I compress multiple images at once? =

Yes, the plugin provides a bulk compression feature. This saves time and effort compared to compressing images individually.

= Can I compress images NOT from the Media Library, but from a custom folder? =

Yes, you can compress images from any folder within your WordPress installation.

= Can I customize the compression settings? =

Yes, the plugin allows you to customize various compression settings according to your preferences. The Settings page is located at Settings -> Squeeze.

== Screenshots ==
1. Compressed image with the Squeeze Plugin
2. Squeeze's Bulk Compression Page
3. Squeeze's Restore and Recompress Options
4. Serving WEBP images instead of JPG and PNG. Even though the URL of the image remains the same, the response is in the WEBP format.
5. Replacing images URLs with the corresponding WEBP version. If your server does not support .htaccess file or it is not writable, then you can use this option to replace your images URLs and serve WEBP images.
6. Squeeze's Basic Settings
7. Squeeze's JPEG compression settings
8. Squeeze's PNG compression settings
9. Squeeze's WebP compression settings
10. Squeeze's AVIF compression settings
11. Squeeze's Bulk actions in the List view of the Media Library
12. Squeeze's Filter in the Grid view of the Media Library

== Changelog ==
= 1.6 =
* Refactored PHP and JS code
= 1.5.2 =
* Fixed webp convertion
* Added option to select image formats
= 1.5.1 =
* Fixed webworker bug
= 1.5 =
* Added WEBP serving
* Minor UI updates
= 1.4.9 =
* Added UI popup for bulk directory squeeze
* Added filters to the Media Library to select non-squeezed images
= 1.4.8 =
* Added webworker
* Fixed pause/resume on bulk Squeeze
* Minor UI/UX updates
= 1.4.7 =
* Updated plugin's UI and description
= 1.4.6 =
* Bulk compress option for the list view of the Media Library
* Pause/Resume option for the bulk compress process
* Fixed bugs with the list mode
* Fixed bug when bulk process stuck if image processing failed
* Added timeout for image compression process
* Added scaled image size for compression
* Refactored JS code
= 1.4.5 =
* Add bulk restore option to the list view of Media Library
* Compress selected thumbnails separately
= 1.4.4 =
* Delete .bak file on media delete
= 1.4.3 =
* Fixed minor JS bug
= 1.4.2 =
* Fixed security issue: check permissions for file upload
= 1.4.1 =
* Fixed security issue: Arbitrary File Upload
= 1.4 =
* Add AVIF support
= 1.3 =
* Add WEBP support
* Update settings page layout with tabs
* Add ability to re-compress images
* Add ability to compress images from custom folder
= 1.2 =
* Fix minor bug in Media library
= 1.1 =
* Fix PNG compressor
= 1.0 =
* First release.

== Upgrade Notice ==
= 1.6 =
* Refactored PHP: converted to Classes, exploded complex functions
* Refactored JS: exploded complex functions, add html templates to separate business logic from layouts
= 1.5.2 =
* Fixed webp convertion
* Added option to select image formats
= 1.5.1 =
* Fixed webworker bug: terminate it on complete squeezing
= 1.5 =
* Added WEBP serving for JPG and PNG images.
* Minor UI updates (savings label on the comparison table)
= 1.4.9 =
* Added UI popup for bulk directory squeeze
* Added filters to the Media Library
= 1.4.8 =
* Added webworker
* Fixed pause/resume on bulk Squeeze
* Minor UI/UX updates
= 1.4.7 =
* Updated plugin's UI and description
* Moved JS and CSS backend to external files
* Added sprite.svg for backend icons
* Updated restore defaults handler function
= 1.4.6 =
* Bulk compress option for the list view of the Media Library
* Pause/Resume option for the bulk compress process
* Fixed bugs with the list mode
* Fixed bug when bulk process stuck if image processing failed
* Added timeout for image compression process
* Refactored JS code
= 1.4.5 =
* Add bulk restore option to the list view of Media Library
* Compress selected thumbnails separately
= 1.4 =
* Add AVIF support
= 1.3 =
* Add WEBP support
* Update settings page layout with tabs
* Add ability to re-compress images
* Add ability to compress images from custom folder
= 1.2 =
* Fix minor bug in Media library
= 1.1 =
* Fix PNG compressor
= 1.0 =
* First release.
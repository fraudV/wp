<?php

namespace Squeeze;

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SqueezeHandlers extends SqueezeInit {
    public function __construct() {
        add_action( 'wp_ajax_squeeze_update_attachment', [$this, 'update_attachment'] );
        add_action( 'wp_ajax_squeeze_restore_attachment', [$this, 'restore_attachment'] );
        add_action( 'wp_ajax_squeeze_get_attachment', [$this, 'get_attachment'] );
        add_action( 'wp_ajax_squeeze_get_attachment_by_path', [$this, 'get_attachment_by_path'] );
        add_action( 'wp_ajax_squeeze_get_next_attachments', [$this, 'get_next_attachments'] );
        add_action( 'wp_ajax_squeeze_get_directories', [$this, 'get_directories'] );
        add_action( 'delete_attachment', [$this, 'delete_backup_attachment'] );
        add_action( 'delete_attachment', [$this, 'delete_webp_images'] );
        add_filter( 'bulk_actions-upload', [$this, 'bulk_actions'] );
        add_filter(
            'handle_bulk_actions-upload',
            [$this, 'handle_bulk_actions'],
            10,
            3
        );
        add_filter( 'image_size_names_choose', [$this, 'custom_image_sizes'] );
        add_filter( 'mod_rewrite_rules', [$this, 'add_webp_rewrite_rules'] );
        add_action( 'pre-html-upload-ui', [$this, 'single_file_upload_notice'], 10 );
        add_action( 'admin_notices', [$this, 'bulk_action_admin_notice'] );
        add_action( 'init', [$this, 'output_buffer_start'], 1 );
    }

    public function update_attachment() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        if ( !current_user_can( 'upload_files' ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'You do not have permission to upload files', 'squeeze' ) );
        }
        if ( !isset( $_POST["base64"] ) || empty( $_POST["base64"] ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'No image data found', 'squeeze' ) );
        }
        $base64 = sanitize_text_field( $_POST["base64"] );
        $sizes = ( isset( $_POST["base64Sizes"] ) ? $_POST["base64Sizes"] : array() );
        // DO NOT SANITIZE because it's an array
        $base64_webp = sanitize_text_field( $_POST["base64Webp"] );
        $sizes_webp = ( isset( $_POST["base64SizesWebp"] ) ? $_POST["base64SizesWebp"] : array() );
        $file_format = sanitize_text_field( $_POST["format"] );
        $filename = sanitize_text_field( $_POST["filename"] );
        $extension = pathinfo( $filename, PATHINFO_EXTENSION );
        $image_formats = self::$SqueezeHelpers->get_image_formats();
        if ( !in_array( $extension, $image_formats ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'Invalid image format', 'squeeze' ) );
        }
        $attach_id = (int) $_POST["attachmentID"];
        $meta_data = wp_get_attachment_metadata( $attach_id );
        $url = sanitize_text_field( $_POST["url"] );
        // sanitize_url() replaces spaces with %20, so we use sanitize_text_field() instead
        $process = sanitize_text_field( $_POST["process"] );
        // process: all, uncompressed, path
        $is_backup_original = self::$SqueezeHelpers->get_option( 'backup_original' );
        // Upload path.
        $upload_path = self::$SqueezeHelpers->get_upload_path( $attach_id, $filename, $url );
        $decoded = self::$SqueezeHelpers->decode_base64_image( $base64, $file_format );
        if ( $is_backup_original && $process !== 'path' ) {
            // do not backup for non library images
            // backup original
            $backup_original_image = self::$SqueezeHelpers->backup_original_image( $upload_path, $filename );
            if ( is_wp_error( $backup_original_image ) ) {
                wp_send_json_error( $backup_original_image->get_error_message() );
            }
        }
        $sizes['original']['original_size'] = filesize( $upload_path . $filename );
        $sizes['original']['compressed_size'] = strlen( $decoded );
        // Save the image in the uploads directory.
        $upload_image = self::$SqueezeHelpers->upload_image( $upload_path, $filename, $decoded );
        if ( is_wp_error( $upload_image ) ) {
            wp_send_json_error( $upload_image->get_error_message() );
        }
        if ($base64_webp) {
			$upload_webp = self::$SqueezeHelpers->upload_webp($upload_path, $base64_webp, $filename);

			if (is_wp_error( $upload_webp )) {
				wp_send_json_error( $upload_webp->get_error_message() );
			}

			self::$SqueezeHelpers->upload_webp_thumbs($upload_path, $sizes_webp);

			// skip handling errors for webp thumbs, because they are not always required
		}
        // upload thumbnails
        if ( $process !== 'path' ) {
            $sizes = self::$SqueezeHelpers->upload_image_thumbs( $upload_path, $sizes, $file_format );
            if ( is_wp_error( $sizes ) ) {
                wp_send_json_error( $sizes->get_error_message() );
            }
            update_post_meta( $attach_id, "squeeze_is_compressed", true );
            $response_msg = self::$SqueezeHelpers->get_comparison_table( $sizes );
            $response_msg = '<strong>✅ ' . esc_html__( 'Squeezed successfully', 'squeeze' ) . '!</strong> ' . $response_msg;
            $uncompressed_images = self::$SqueezeHelpers->get_stats_option( 'uncompressed_images' );
            $uncompressed_images--;
            update_option( 'squeeze_stats', array(
                'uncompressed_images' => $uncompressed_images,
            ) );
            wp_send_json_success( $response_msg );
        } else {
            wp_send_json_success( '✅ ' . esc_html__( 'Squeezed successfully', 'squeeze' ) );
        }
        wp_die();
    }

    public function restore_attachment() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        if ( !isset( $_POST["attachmentID"] ) || empty( $_POST["attachmentID"] ) || !wp_get_attachment_url( $_POST["attachmentID"] ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'Attachment not found', 'squeeze' ) );
        }
        $attach_id = (int) $_POST["attachmentID"];
        $can_restore = self::$SqueezeHelpers->can_restore( $attach_id );
        if ( $can_restore ) {
            $is_restore_attachment = self::$SqueezeHelpers->restore_attachment( $attach_id );
            if ( $is_restore_attachment ) {
                wp_send_json_success( '✅ ' . esc_html__( 'Restored successfully', 'squeeze' ) );
            } else {
                wp_send_json_error( '❌ ' . esc_html__( 'Attachment not restored', 'squeeze' ) );
            }
        }
        wp_die();
    }

    public function get_attachment() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        if ( !isset( $_POST["attachmentID"] ) || empty( $_POST["attachmentID"] ) || !wp_get_attachment_url( $_POST["attachmentID"] ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'Attachment not found', 'squeeze' ) );
        }
        $attach_id = (int) $_POST["attachmentID"];
        $sizes = wp_get_attachment_metadata( $attach_id );
        $sizes = $sizes['sizes'];
        $full_image = wp_get_attachment_image_src( $attach_id, 'full' );
        $sizes['full'] = array(
            'url'    => $full_image[0],
            'width'  => $full_image[1],
            'height' => $full_image[2],
        );
        foreach ( $sizes as $size_name => $size_data ) {
            $sizes[$size_name]['url'] = wp_get_attachment_image_url( $attach_id, $size_name );
        }
        $attach_data = array(
            'id'       => $attach_id,
            'url'      => wp_get_original_image_url( $attach_id ),
            'mime'     => get_post_mime_type( $attach_id ),
            'name'     => get_the_title( $attach_id ),
            'filename' => basename( wp_get_original_image_path( $attach_id ) ),
            'sizes'    => $sizes,
        );
        wp_send_json_success( $attach_data );
        wp_die();
    }

    public function get_attachment_by_path() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        if ( !isset( $_POST["path"] ) || empty( $_POST["path"] ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'Path not found', 'squeeze' ) );
        }
        $pathes = sanitize_text_field( $_POST["path"] );
        $pathes = json_decode( stripslashes( $pathes ), true );
        $attach_data = array();
        $image_formats = self::$SqueezeHelpers->get_image_formats();
        $image_formats = implode( ',', $image_formats );
        foreach ( $pathes as $path ) {
            $path = str_replace( ['\\\\', '\\'], '/', $path );
            // replace double and then single backslashes with slashes
            if ( substr( $path, -1 ) !== '/' ) {
                $path .= '/';
            }
            if ( substr( $path, 0, 1 ) !== '/' ) {
                $path = '/' . $path;
            }
            $images = glob( ABSPATH . $path . '*.{' . $image_formats . '}', GLOB_BRACE );
            if ( empty( $images ) ) {
                continue;
            }
            foreach ( $images as $image ) {
                $attach_id = attachment_url_to_postid( $image );
                $attach_mime = image_type_to_mime_type( exif_imagetype( $image ) );
                $attach_url = str_replace( ABSPATH, home_url(), $image );
                $attach_name = pathinfo( $image, PATHINFO_FILENAME );
                $attach_data[] = array(
                    'id'       => $attach_id,
                    'url'      => $attach_url,
                    'mime'     => $attach_mime,
                    'name'     => $attach_name,
                    'filename' => basename( $image ),
                );
            }
        }
        // Save pathes to cache
        set_transient( 'squeeze_bulk_path', $pathes, MONTH_IN_SECONDS );
        if ( empty( $attach_data ) ) {
            wp_send_json_error( '❌ ' . esc_html__( 'Images were not found in the selected directories', 'squeeze' ) );
        }
        wp_send_json_success( $attach_data );
        wp_die();
    }

    public function delete_backup_attachment( $attach_id ) {
        $original_img_path = wp_get_original_image_path( (int) $attach_id );
        $backup_img_path = preg_replace( "/(\\.(?!.*\\.))/", '.bak.', $original_img_path );
        if ( file_exists( $backup_img_path ) ) {
            return wp_delete_file( $backup_img_path );
        }
        return false;
    }

    public function delete_webp_images( $attach_id ) {
        $original_img_path = wp_get_original_image_path( (int) $attach_id );
        $attach_data = wp_get_attachment_metadata( $attach_id );
        $delete_webp_images = self::$SqueezeHelpers->delete_webp_images( $original_img_path, $attach_data );
        return $delete_webp_images;
    }

    public function bulk_actions( $actions ) {
        $actions['squeeze_bulk_restore'] = esc_html__( 'Restore Original Image', 'squeeze' );
        $actions['squeeze_bulk_compress'] = esc_html__( 'Squeeze Image', 'squeeze' );
        $actions['squeeze_bulk_delete_backup'] = esc_html__( 'Delete Backup Image', 'squeeze' );
        $actions['squeeze_bulk_delete_webp'] = esc_html__( 'Delete WEBP Image', 'squeeze' );
        return $actions;
    }

    public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( $doaction === 'squeeze_bulk_restore' ) {
            $restored_ids_count = 0;
            foreach ( $post_ids as $post_id ) {
                $can_restore = self::$SqueezeHelpers->can_restore( $post_id );
                if ( $can_restore ) {
                    $is_restore_attachment = self::$SqueezeHelpers->restore_attachment( $post_id, true );
                    if ( $is_restore_attachment ) {
                        $restored_ids_count += 1;
                    }
                }
            }
            $redirect_to = add_query_arg( 'squeeze_bulk_restored', $restored_ids_count, $redirect_to );
        }
        if ( $doaction === 'squeeze_bulk_compress' ) {
            foreach ( $post_ids as $post_id ) {
                $redirect_to = add_query_arg( 'squeeze_bulk_compressed', count( $post_ids ), $redirect_to );
            }
        }
        if ( $doaction === 'squeeze_bulk_delete_backup' ) {
            $deleted_ids_count = 0;
            foreach ( $post_ids as $post_id ) {
                $is_delete_backup = $this->delete_backup_attachment( $post_id );
                if ( $is_delete_backup ) {
                    $deleted_ids_count += 1;
                }
            }
            $redirect_to = add_query_arg( 'squeeze_bulk_deleted', $deleted_ids_count, $redirect_to );
        }
        if ( $doaction === 'squeeze_bulk_delete_webp' ) {
            $deleted_ids_count = 0;
            foreach ( $post_ids as $post_id ) {
                $is_delete_webp = $this->delete_webp_images( $post_id );
                if ( $is_delete_webp ) {
                    $deleted_ids_count += 1;
                }
            }
            $redirect_to = add_query_arg( 'squeeze_bulk_webp_deleted', $deleted_ids_count, $redirect_to );
        }
        return $redirect_to;
    }

    public function bulk_action_admin_notice() {
        if ( !empty( $_REQUEST['squeeze_bulk_restored'] ) ) {
            $message = sprintf( 
                /* translators: %d: number of attachments restored */
                _n(
                    '%d attachment restored.',
                    '%d attachments restored.',
                    $_REQUEST['squeeze_bulk_restored'],
                    'squeeze'
                ),
                number_format_i18n( $_REQUEST['squeeze_bulk_restored'] )
             );
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
        }
        if ( !empty( $_REQUEST['squeeze_bulk_compressed'] ) ) {
            $message = sprintf( 
                /* translators: %d: number of attachments squeezed */
                _n(
                    '%d attachment squeezed.',
                    '%d attachments squeezed.',
                    $_REQUEST['squeeze_bulk_compressed'],
                    'squeeze'
                ),
                number_format_i18n( $_REQUEST['squeeze_bulk_compressed'] )
             );
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
        }
        if ( !empty( $_REQUEST['squeeze_bulk_deleted'] ) ) {
            $message = sprintf( 
                /* translators: %d: number of backup images deleted */
                _n(
                    '%d backup image deleted.',
                    '%d backup images deleted.',
                    $_REQUEST['squeeze_bulk_deleted'],
                    'squeeze'
                ),
                number_format_i18n( $_REQUEST['squeeze_bulk_deleted'] )
             );
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
        }
        if ( !empty( $_REQUEST['squeeze_bulk_webp_deleted'] ) ) {
            $message = sprintf( 
                /* translators: %d: number of webp images deleted */
                _n(
                    '%d WEBP image deleted.',
                    '%d WEBP images deleted.',
                    $_REQUEST['squeeze_bulk_webp_deleted'],
                    'squeeze'
                ),
                number_format_i18n( $_REQUEST['squeeze_bulk_webp_deleted'] )
             );
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
        }
    }

    public function custom_image_sizes( $sizes ) {
        $available_sizes = wp_get_registered_image_subsizes();
        foreach ( $available_sizes as $size_name => $size_data ) {
            $sizes[$size_name] = $size_data['width'] . 'x' . $size_data['height'];
        }
        return $sizes;
    }

    public function get_next_attachments() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        $per_page = self::$MEDIA_PER_PAGE;
        $page = ( isset( $_POST['page'] ) ? (int) $_POST['page'] : 1 );
        $type = ( isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'uncompressed' );
        if ( $type === 'uncompressed' ) {
            $next_images = self::$SqueezeHelpers->get_uncompressed_images( $page );
        } else {
            $next_images = self::$SqueezeHelpers->get_total_images( $page );
        }
        wp_send_json_success( $next_images );
    }

    public function single_file_upload_notice() {
        global $current_screen;
        if ( $current_screen->id === 'media' ) {
            ?>
			<div class="notice notice-info hide-if-js squeeze-single-file-upload-notice">
				<p><?php 
            esc_html_e( 'Single file upload is not supported for the image compression by Squeeze. Please use multi-file uploader or bulk squeeze.', 'squeeze' );
            ?></p>
			</div>
			<?php 
        }
    }

    public function get_directories() {
        check_ajax_referer( 'squeeze-nonce', '_ajax_nonce' );
        $parent_directory = ( isset( $_POST['parentDir'] ) ? sanitize_text_field( $_POST['parentDir'] ) : '' );
        $base_dir = ( $parent_directory ? ABSPATH . $parent_directory : WP_CONTENT_DIR );
        $directories = scandir( $base_dir );
        if ( !$parent_directory ) {
            $directories[] = $base_dir;
        }
        $result = array_filter( $directories, function ( $dir ) use($base_dir) {
            if ( $dir === $base_dir ) {
                return true;
            }
            return is_dir( $base_dir . '/' . $dir ) && !in_array( $dir, ['.', '..'] );
        } );
        $output = array_map( function ( $dir ) use($base_dir) {
            if ( $dir === 'squeeze-webp' ) {
                return [
                    'name'         => '',
                    'path'         => '',
                    'is_writeable' => false,
                    'parent'       => '',
                ];
            }
            if ( $dir === $base_dir ) {
                $path = str_replace( ABSPATH, '/', $dir . '/' );
                $parent_path = dirname( $base_dir );
                $parent_path = str_replace( ABSPATH, '/', $parent_path . '/' );
                return [
                    'name'         => 'wp-content',
                    'path'         => $path,
                    'is_writeable' => false,
                    'parent'       => $parent_path,
                ];
            }
            $path = str_replace( ABSPATH, '/', $base_dir . '/' . $dir . '/' );
            $parent_path = dirname( $base_dir . '/' . $dir );
            $parent_path = str_replace( ABSPATH, '/', $parent_path . '/' );
            // Remove double slashes from path
            $path = preg_replace( '/\\/+/', '/', $path );
            $parent_path = preg_replace( '/\\/+/', '/', $parent_path );
            return [
                'name'         => $dir,
                'path'         => $path,
                'is_writeable' => wp_is_writable( $base_dir . '/' . $dir ),
                'parent'       => $parent_path,
            ];
        }, $result );
        usort( $output, function ( $a, $b ) {
            if ( $a['name'] === 'wp-content' ) {
                return -1;
            } elseif ( $b['name'] === 'wp-content' ) {
                return 1;
            }
            return strcmp( $a['name'], $b['name'] );
        } );
        wp_send_json( $output );
    }

    public function add_webp_rewrite_rules( $rules ) {
        $is_auto_webp = self::$SqueezeHelpers->get_option( 'auto_webp' );
        // Get the WordPress installation subdirectory, if applicable
        $wordpress_subdirectory = wp_parse_url( home_url(), PHP_URL_PATH );
        // Check if WordPress is installed in a subdirectory (not just the root)
        if ( strlen( $wordpress_subdirectory ) > 1 ) {
            // Ensure the subdirectory is used correctly in the rules
            $rewrite_base = $wordpress_subdirectory . '/';
        } else {
            // If WordPress is installed in the root, no subdirectory path is needed
            $rewrite_base = '/';
        }
        $webp_rules = "\n# Serve WebP images from the wp-content/squeeze-webp folder if available\n";
        $webp_rules .= "RewriteCond %{HTTP_ACCEPT} image/webp\n";
        // Check if browser supports WebP
        $webp_rules .= "RewriteCond %{REQUEST_URI} \\.(jpg|jpeg|png)\$ [NC]\n";
        // Check if request is for JPG, JPEG, or PNG
        $webp_rules .= "RewriteCond %{DOCUMENT_ROOT}" . $rewrite_base . "wp-content/squeeze-webp/\$1.\$2.webp -f\n";
        // Check if WebP file exists
        $webp_rules .= "RewriteRule ^wp-content/(.+)\\.(jpg|jpeg|png)\$ wp-content/squeeze-webp/\$1.\$2.webp [T=image/webp,E=webp_request,L]\n";
        // Serve WebP file
        $webp_rules .= "\n";
        if ( !$is_auto_webp ) {
            // If auto WebP conversion is disabled, return the original rules and replace the WebP rules if they exist
            $rules = preg_replace( '/# Serve WebP images from the wp-content\\/squeeze-webp folder if available.*?# Serve WebP images from the wp-content\\/squeeze-webp folder if available/s', '', $rules );
            return $rules;
        }
        // Check if the server is Apache and htaccess is writable
        if ( !function_exists( 'apache_get_modules' ) || !in_array( 'mod_rewrite', apache_get_modules() ) ) {
            return $rules;
        }
        return $webp_rules . $rules;
    }

    public function output_buffer_start() {
        if ( !is_admin() && (!isset( $_SERVER['HTTP_X_WP_REMOTE_REQUEST'] ) || $_SERVER['HTTP_X_WP_REMOTE_REQUEST'] !== 'true') || function_exists( "wp_doing_ajax" ) && wp_doing_ajax() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            ob_start( [$this, 'replace_image_urls_with_webp'] );
        }
    }

    // Other helper functions
    public function replace_image_urls_with_webp( $content ) {
        if ( !self::$SqueezeHelpers->is_webp_replace_urls() ) {
            return $content;
        }
        // Regular expression to find JPG and PNG images in src and srcset attributes.
        $pattern = '/(\\/\\/.*?\\/wp-content\\/)([^"\\s]+\\.(jpg|jpeg|png))(\\?[^"\\s]*)?/i';
        // Callback function to replace the URLs.
        $callback = function ( $matches ) {
            $file_extension = strtolower( pathinfo( $matches[0], PATHINFO_EXTENSION ) );
            if ( $file_extension === 'webp' ) {
                return $matches[0];
            }
            $protocol = ( is_ssl() ? 'https:' : 'http:' );
            $webp_url = self::$SqueezeHelpers->convert_image_path_to_webp_path( $matches[0] ) . '.webp';
            // WebP URL like 'example.com/wp-content/squeeze-webp/uploads/2024/12/test.jpg.webp'
            // Check if the WEBP file exists on the server.
            $webp_file_path = str_replace( home_url(), ABSPATH, $protocol . $webp_url );
            // Convert URL to file path.
            //return $webp_file_path.'::'.$webp_url;
            if ( file_exists( $webp_file_path ) ) {
                return $webp_url;
                // Use WEBP version if it exists.
            }
            return $matches[0];
            // Fallback to the original URL if WEBP file doesn't exist.
        };
        // Replace URLs in the content, including src and srcset attributes.
        $content = preg_replace_callback( $pattern, $callback, $content );
        return $content;
    }

}
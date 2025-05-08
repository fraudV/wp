<?php
namespace Squeeze;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

class SqueezeHelpers extends SqueezeInit {
	public function __construct() {
		//parent::__construct(); // will cause infinite loop in SquuezeInit
	}

	public function get_upload_path($attach_id, $filename, $url) {
		if ($attach_id > 0) {
			$upload_dir = str_replace($filename, "", wp_get_original_image_path($attach_id));
		} else {
			$upload_url = str_replace($filename, "", $url);
			$upload_dir = str_replace(home_url(), ABSPATH, $upload_url);
		}
		return str_replace('/', DIRECTORY_SEPARATOR, $upload_dir) . DIRECTORY_SEPARATOR;
	}

	public function backup_original_image($upload_path, $filename) {
		$backup_filename = preg_replace("/(\.(?!.*\.))/", '.bak.', $filename);
		if (!file_exists($upload_path . $backup_filename)) {
			$upload_backup_file = copy($upload_path . $filename, $upload_path . $backup_filename);
			if (!$upload_backup_file) {
				return new \WP_Error('squeeze_backup_original_image_failed', '❌ '.esc_html__('Backup original image failed', 'squeeze') . ': '. $upload_path . $backup_filename);
			}

			return $upload_backup_file;
		}

		return true;
	}

	public function decode_base64_image($base64, $file_format) {
		$img = str_replace('data:image/'.$file_format.';base64,', '', $base64);
		$img = str_replace(' ', '+', $img);
		return base64_decode($img);
	}

	public function upload_image($upload_path, $filename, $decoded_image) {
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once(ABSPATH . '/wp-admin/includes/file.php');
			WP_Filesystem();
		}
		$upload_file = $wp_filesystem->put_contents($upload_path . $filename, $decoded_image);
		if (!$upload_file) {
			return new \WP_Error('squeeze_upload_image_failed', '❌ '.esc_html__('Upload image failed', 'squeeze') . ': upload_path:' . $upload_path . ':: filename:' . $filename);
		}

		return $upload_file;
	}

	public function upload_image_thumbs($upload_path, $sizes, $file_format) {
		if (!is_array($sizes) || empty($sizes)) {
			return new \WP_Error('squeeze_upload_image_thumbs_failed', '❌ '.esc_html__('No image data found', 'squeeze'));
		}
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once(ABSPATH . '/wp-admin/includes/file.php');
			WP_Filesystem();
		}
		foreach ($sizes as $size_name => $size_data) {
			if ($size_name === 'original') {
				continue;
			}
			$size_base64 = sanitize_text_field($size_data['base64']);
			$size_decoded = $this->decode_base64_image($size_base64, $file_format);
			$size_filename = basename(sanitize_url($size_data['url']));
			if ($size_name === 'full') {
				$size_name = 'scaled';
				unset($sizes['full']);
			}
			$sizes[$size_name]['original_size'] = filesize($upload_path . $size_filename);
			$upload_size_file = $wp_filesystem->put_contents($upload_path . $size_filename, $size_decoded);
			if (!$upload_size_file) {
				return new \WP_Error('squeeze_upload_image_thumbs_failed', '❌ '.esc_html__('Upload image failed', 'squeeze') . ': upload_path:' . $upload_path . ':: filename:' . $size_filename);
			} else {
				$sizes[$size_name]['compressed_size'] = strlen($size_decoded);
			}
		}
		return $sizes;
	}

	public function upload_webp($upload_path, $base64_webp, $filename) {
		if (!$base64_webp) {
			return new \WP_Error('squeeze_upload_webp_failed', '❌ '.esc_html__('No WebP data found', 'squeeze'));
		}

		$upload_webp_path = $this->convert_image_path_to_webp_path($upload_path);
		if (!file_exists($upload_webp_path)) {
			wp_mkdir_p($upload_webp_path);
		}
		$decoded_webp = $this->decode_base64_image($base64_webp, 'webp');
		$filename_webp = $filename.'.webp';
		$upload_file_webp = $this->upload_image($upload_webp_path, $filename_webp, $decoded_webp);

		return $upload_file_webp;
	}

	public function upload_webp_thumbs($upload_path, $sizes_webp) {
		if (!is_array($sizes_webp) || empty($sizes_webp)) {
			return new \WP_Error('squeeze_upload_webp_thumbs_failed', '❌ '.esc_html__('No WebP data found', 'squeeze'));
		}

		$upload_webp_path = $this->convert_image_path_to_webp_path($upload_path);
		if (!file_exists($upload_webp_path)) {
			wp_mkdir_p($upload_webp_path);
		}
		foreach ($sizes_webp as $size_name => $size_data) {
			if ($size_name === 'original') {
				continue;
			}
			$size_base64 = sanitize_text_field($size_data['base64']);
			$size_decoded = $this->decode_base64_image($size_base64, 'webp');
			$size_filename = basename(sanitize_url($size_data['url']));
			$size_filename = $size_filename.'.webp';
			$upload_size_file = $this->upload_image($upload_webp_path, $size_filename, $size_decoded);
		}
		return $sizes_webp;
	}

	public function convert_image_path_to_webp_path($image_path) {
		$webp_path = preg_replace('/wp-content[\/\\\\]/', 'wp-content'.DIRECTORY_SEPARATOR.'squeeze-webp'.DIRECTORY_SEPARATOR, $image_path);
		return $webp_path;
	}

    public function can_restore($attach_id) {
		$original_img_path = wp_get_original_image_path((int) $attach_id);
        $backup_img_path = preg_replace("/(\.(?!.*\.))/", '.bak.', $original_img_path);
        $can_restore = file_exists($backup_img_path);

        return $can_restore;
	}

    public function restore_attachment($attach_id, $is_bulk = false) {
        $original_img_path = wp_get_original_image_path($attach_id);
        $backup_img_path = preg_replace("/(\.(?!.*\.))/", '.bak.', $original_img_path);

        if (!copy($backup_img_path, $original_img_path)) {
            $error_message = '❌ ' . esc_html__('Restore original image failed', 'squeeze');
            if ($is_bulk) {
                wp_die($error_message);
            } else {
                return new \WP_Error('squeeze_restore_attachment_failed', $error_message);
            }
            return false;
        }

        $attach_data = wp_create_image_subsizes($original_img_path, $attach_id);
        if (!delete_post_meta($attach_id, "squeeze_is_compressed")) {
            return false;
        }

        wp_delete_file($backup_img_path);
        $this->delete_webp_images($original_img_path, $attach_data);

        $uncompressed_images = $this->get_stats_option('uncompressed_images');
        update_option('squeeze_stats', array('uncompressed_images' => ++$uncompressed_images));
        return true;
    }

    public function delete_webp_images($original_img_path, $attach_data) {
		$result = false;
        $original_filename = pathinfo($original_img_path, PATHINFO_BASENAME);

        $webp_path = $this->convert_image_path_to_webp_path($original_img_path);
        $result = wp_delete_file($webp_path . '.webp');

        foreach ($attach_data['sizes'] as $size_data) {
            $webp_thumb_path = str_replace($original_filename, $size_data['file'] . '.webp', $original_img_path);
            $result = wp_delete_file($this->convert_image_path_to_webp_path($webp_thumb_path));
        }

        $webp_scaled_filename = pathinfo($attach_data['file'], PATHINFO_BASENAME);
        $webp_scaled_path = str_replace($original_filename, $webp_scaled_filename . '.webp', $original_img_path);
        $result = wp_delete_file($this->convert_image_path_to_webp_path($webp_scaled_path));

		return $result;
    }

    public function get_stats_option($option) {
		$stats = get_option('squeeze_stats');
		$option_value = isset($stats[$option]) ? $stats[$option] : 0;

		return $option_value;
	}

	public function get_option($option) {
		$options = get_option('squeeze_options');
		$option_value = isset($options[$option]) ? $options[$option] : $this->get_default_value($option);

		return $option_value;
	}

    public function get_comparison_table($sizes) {
		if (!is_array($sizes) || empty($sizes)) {
			return '';
		}

		$table = '<div class="squeeze-comparison-table">';
		$table .= '<table class="wp-list-table widefat striped">';
		$table .= '<thead><tr><th>'.esc_html__('Size Name', 'squeeze').'</th><th>'.esc_html__('Original Size', 'squeeze').'</th><th>'.esc_html__('Squeezed Size', 'squeeze').'</th><th>'.esc_html__('Savings', 'squeeze').' (%)</th></tr></thead>';
		$table .= '<tbody>';

		foreach ($sizes as $size_name => $size_data) {
			$size_filename = basename(sanitize_url($size_data['url']));
			$original_size = $size_data['original_size'];
			$compressed_size = $size_data['compressed_size'];
			$savings = $original_size - $compressed_size;
			$savings_percent = round(($savings / $original_size) * 100, 2);
			$savings_class = $savings > 0 ? 'squeeze-savings-positive' : 'squeeze-savings-negative';

			$table .= '<tr>';
			$table .= '<td><strong>'.$size_name.'</strong></td>';
			$table .= '<td>'.size_format($original_size, 0).'</td>';
			$table .= '<td>'.size_format($compressed_size, 0).'</td>';
			$table .= '<td><span class="squeeze-savings-label '.$savings_class.'">'.$savings_percent.'%</span></td>';
			$table .= '</tr>';
		}

		$table .= '</tbody></table></div>';

		return $table;
	}

    public function is_webp_replace_urls() {
		$is_auto_webp = $this->get_option('auto_webp');
		$is_webp_replace_urls = $this->get_option('webp_replace_urls');

		if (!$is_auto_webp || !$is_webp_replace_urls) {
			return false;
		}
		
		return true;
	}

    public function get_image_formats($return_mimes = false) {
		$allowed_image_formats = $this->get_option('compress_formats');
		$allowed_image_formats = array_keys($allowed_image_formats);

		if ($return_mimes) {
			$allowed_image_formats = array_map(function($format) {
				return 'image/'.$format;
			}, $allowed_image_formats);
		}

		return $allowed_image_formats;
	}

    public function get_total_images_count() {
		$total_images = $this->get_stats_option('total_images');

		if ($total_images > 0) {
			return $total_images;
		}

		$query_all = new \WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => $this->get_image_formats(true),
			'posts_per_page' => -1,
			'fields' => 'ids',
		));

		$total_images = $query_all->found_posts;

		$stats['total_images'] = $total_images;
		update_option('squeeze_stats', $stats);

		return $total_images;
	}

    public function get_uncompressed_images_count() {
		$uncompressed_images = $this->get_stats_option('uncompressed_images');

		if ($uncompressed_images > 0) {
			return $uncompressed_images;
		}

		$query_uncompressed = new \WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => $this->get_image_formats(true),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'squeeze_is_compressed',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => 'squeeze_is_compressed',
					'compare' => '!=',
					'value' => '1',
				),
			),
		));

		$uncompressed_images = $query_uncompressed->found_posts;

		$stats['uncompressed_images'] = $uncompressed_images;
		update_option('squeeze_stats', $stats);

		return $uncompressed_images;
	}

    public function get_uncompressed_images($paged = 1) {
		$query_uncompressed = new \WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => $this->get_image_formats(true),
            'posts_per_page' => self::$MEDIA_PER_PAGE,
			'paged' => $paged,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'squeeze_is_compressed',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => 'squeeze_is_compressed',
					'compare' => '!=',
					'value' => '1',
				),
			),
		));
	
		return $query_uncompressed->posts;
	}

	public function get_total_images($paged = 1) {
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => $this->get_image_formats(true),
			'posts_per_page' => self::$MEDIA_PER_PAGE,
			'paged' => $paged,
			'fields' => 'ids',
		);
	
		$query_all = new \WP_Query($args);
	
		return $query_all->posts;
	}

	public function get_hint($hint, $class = 'squeeze-hint') {
		return '<span class="' . esc_attr($class) . '">' . esc_html($hint) . '</span>';
	}

	public function get_default_value ( $option, $all = false ) {
		$options_defaults = apply_filters('squeeze_options_default', 
		array(
			// JPEG settings
			'jpeg_quality' => 80,
			'jpeg_baseline' => false,
			'jpeg_arithmetic' => false,
			'jpeg_progressive' => true,
			'jpeg_optimize_coding' => true,
			'jpeg_smoothing' => 0,
			'jpeg_color_space' => 3,
			'jpeg_quant_table' => 3,
			'jpeg_trellis_multipass' => false,
			'jpeg_trellis_opt_zero' => false,
			'jpeg_trellis_opt_table' => false,
			'jpeg_trellis_loops' => 1,
			'jpeg_auto_subsample' => true,
			'jpeg_chroma_subsample' => 2,
			'jpeg_separate_chroma_quality' => false,
			'jpeg_chroma_quality' => 75,
	
			// PNG settings
			'png_level' => 2,
			'png_interlace' => false,
	
			// WEBP settings
			'webp_method' => 4,
			'webp_quality' => 80,
			'webp_lossless' => false,
			'webp_near_lossless' => 100,
	
			// AVIF settings
			'avif_cqLevel' => 70,
	
			// General settings
			'auto_compress' => true,
			'auto_webp' => false, // needs to be false by default, because user has to save settings first in order to flush rewrite rules
			'webp_replace_urls' => false,
			'cdn_url' => '',
			'backup_original' => true,
			'compress_formats' => self::ALLOWED_IMAGE_FORMATS,
			'compress_thumbs' => array('large' => 'on', 'full' => 'on'),
			'max_width' => '',
			'max_height' => '',
			'excluded_images' => '',
			'timeout' => 60,
			'restore_defaults' => false, // special option to trigger restore defaults
		)
		);
		if ($all) {
			return $options_defaults;
		}
		return in_array($option, array_keys($options_defaults)) ? $options_defaults[ $option ] : false;
	}
}

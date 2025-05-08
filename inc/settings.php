<?php

namespace Squeeze;

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SqueezeSettings extends SqueezeInit {
    public function __construct() {
        add_action( 'admin_menu', [$this, 'options_page'] );
        add_action( 'admin_menu', [$this, 'options_bulk_page'] );
        add_action( 'admin_init', [$this, 'register_settings'] );
        add_action(
            'update_option_squeeze_options',
            [$this, 'restore_defaults'],
            10,
            2
        );
        add_action(
            'update_option_squeeze_options',
            [$this, 'flush_rewrite_rules'],
            10,
            2
        );
        add_filter(
            'attachment_fields_to_edit',
            [$this, 'add_custom_field_to_attachment'],
            10,
            2
        );
        add_filter( 'manage_media_columns', [$this, 'add_media_columns'] );
        add_action(
            'manage_media_custom_column',
            [$this, 'media_custom_column'],
            10,
            2
        );
        add_filter( 'manage_upload_sortable_columns', [$this, 'sortable_columns'] );
        add_action( 'pre_get_posts', [$this, 'sortable_columns_orderby'] );
        add_action( 'restrict_manage_posts', [$this, 'media_filter_dropdown'] );
        add_action( 'pre_get_posts', [$this, 'media_filter_query'] );
        add_filter(
            'ajax_query_attachments_args',
            [$this, 'media_filter_ajax_query'],
            10,
            1
        );
        add_action( 'admin_footer', [$this, 'svg_sprite_output'] );
    }

    public function options_page() {
        add_submenu_page(
            'options-general.php',
            __( 'Squeeze Settings', 'squeeze' ),
            __( 'Squeeze', 'squeeze' ),
            'manage_options',
            'squeeze',
            [$this, 'options_page_html']
        );
    }

    public function options_bulk_page() {
        add_submenu_page(
            'upload.php',
            __( 'Bulk Squeeze', 'squeeze' ),
            __( 'Bulk Squeeze', 'squeeze' ),
            'manage_options',
            'squeeze-bulk',
            [$this, 'options_bulk_page_html']
        );
    }

    public function options_bulk_page_html() {
        // check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        $is_single_page_squeeze = false;
        $uncompressed_count = self::$SqueezeHelpers->get_uncompressed_images_count();
        $uncompressed_pages = ceil( $uncompressed_count / self::$MEDIA_PER_PAGE );
        $total_count = self::$SqueezeHelpers->get_total_images_count();
        $total_pages = ceil( $total_count / self::$MEDIA_PER_PAGE );
        $compressed_count = $total_count - $uncompressed_count;
        $compressed_percentage = ( $total_count > 0 ? round( $compressed_count / $total_count * 100, 2 ) : 0 );
        $dasharray = $compressed_percentage * 560 / 100;
        //$total_count = array_sum((array)wp_count_attachments("image"));
        $not_compressed_posts = implode( ",", self::$SqueezeHelpers->get_uncompressed_images() );
        $all_posts = implode( ",", self::$SqueezeHelpers->get_total_images() );
        $directory_path = ( get_transient( 'squeeze_bulk_path' ) ? get_transient( 'squeeze_bulk_path' ) : array('/wp-content/uploads/') );
        $directory_path_json = wp_json_encode( $directory_path );
        ?>
        <div class="wrap">
            <h1>
                <?php 
        echo esc_html( get_admin_page_title() );
        ?>
            </h1>
            <section class="squeeze-box">
                <div class="squeeze-box-bulk-grid">
                    <?php 
        if ( !$is_single_page_squeeze ) {
            ?>
                    <div class="squeeze-box-bulk-grid__col">
                        <div class="squeeze-box-header">
                            <h2><?php 
            esc_html_e( 'Bulk Media Library Squeeze', 'squeeze' );
            ?></h2>
                        </div>
                        <div class="squeeze-box-content">
                            <div class="squeeze-box-content__row">
                                <div class="squeeze-box-content__col">
                                    <div class="squeeze-bulk-media-stats">
                                        <div class="squeeze-bulk-media-stats-chart" style="--squeeze-dasharray: <?php 
            echo esc_attr( $dasharray );
            ?>;">
                                            <svg width="200" height="200">
                                                <circle cx="100" cy="100" r="90" class="squeeze-bulk-media-stats-chart-total" fill="none" />
                                                <circle cx="100" cy="100" r="90" class="squeeze-bulk-media-stats-chart-squeezed" fill="none"/>

                                                <g class="squeeze-bulk-media-stats-chart-value">
                                                    <text x="100" y="100" alignment-baseline="central" text-anchor="middle"><?php 
            echo esc_html( $compressed_percentage );
            ?>%</text>
                                                </g>
                                            </svg>
                                        </div>
                                        <div class="squeeze-bulk-media-stats-item">
                                            <div class="squeeze-bulk-media-stats-item-label"><?php 
            esc_html_e( 'Squeezed images: ', 'squeeze' );
            ?></div>
                                            <div class="squeeze-bulk-media-stats-item-value"><?php 
            echo esc_html( $compressed_count );
            ?> / <?php 
            echo esc_html( $total_count );
            ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="squeeze-box-content__col squeeze-bulk-media-actions">
                                    <button name="squeeze_bulk" class="button button-primary button-hero" type="button" <?php 
            echo ( $uncompressed_count === 0 ? 'hidden disabled' : '' );
            ?>>
                                        <svg class="squeeze-icon">
                                            <use xlink:href="#play-button-round-icon"></use>
                                        </svg>
                                        <?php 
            esc_attr_e( 'Run Bulk Squeeze', 'squeeze' );
            ?>
                                    </button>
                                    <button name="squeeze_bulk_again" class="button button-secondary button-large" type="button">
                                        <svg class="squeeze-icon">
                                            <use xlink:href="#combine-left-right-outline-icon"></use>
                                        </svg>
                                        <?php 
            esc_attr_e( 'Repeat Bulk Squeeze', 'squeeze' );
            ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="squeeze-box-bulk-grid__col">
                        <div class="squeeze-box-header">
                            <h2><?php 
            esc_html_e( 'Directory Squeeze', 'squeeze' );
            ?></h2>
                        </div>
                        <div class="squeeze-box-content">
                            <div class="squeeze-banner squeeze-banner--warning">
                                <svg class="squeeze-icon">
                                    <use xlink:href="#exclamation-triangle-line-icon"></use>
                                </svg>
                                <div class="squeeze-banner__content">
                                    <p><?php 
            esc_html_e( 'Warning! Backup option is NOT applicable here.', 'squeeze' );
            ?></p>
                                    <p><?php 
            esc_html_e( 'Please backup your images manually before optimising them.', 'squeeze' );
            ?></p>
                                </div>
                            </div>
                            <div class="squeeze-box-content__row">
                                <div class="squeeze-box-content__col">
                                    <label>
                                        <?php 
            esc_html_e( 'Directory Path:', 'squeeze' );
            ?><br>
                                        
                                        <input type="hidden" name="squeeze_bulk_path" value="<?php 
            echo esc_attr( $directory_path_json );
            ?>" />
                                        
                                        <div class="squeeze-path-list">
                                        <?php 
            foreach ( $directory_path as $path ) {
                ?>
                                            <input type="text" class="squeeze-path-list__item" value="<?php 
                echo esc_attr( $path );
                ?>" readonly />
                                        <?php 
            }
            ?>
                                        </div>
                                    </label>
                                    <button name="squeeze_select_path_button" class="button button-secondary button-large" type="button">
                                        <svg class="squeeze-icon">
                                            <use xlink:href="#open-folder-outline-icon"></use>
                                        </svg>
                                        <?php 
            esc_attr_e( 'Select Directory', 'squeeze' );
            ?>
                                    </button>
                                    <dialog id="squeeze-path-dialog">
                                        <div class="squeeze-box-header">
                                            <h2><?php 
            esc_html_e( "Select Directory for Squeeze", "squeeze" );
            ?></h2>
                                            <button name="squeeze_close_path_dialog_button" class="button button-link" type="button">
                                                <svg class="squeeze-icon">
                                                    <use xlink:href="#close-round-icon"></use>
                                                </svg>
                                            </button>
                                        </div>

                                        <div id="squeeze-bulk-directory-list">
                                            <svg class="squeeze-icon">
                                                <use xlink:href="#reload-sync-icon"></use>
                                            </svg>
                                            <?php 
            esc_attr_e( "Loading directories...", "squeeze" );
            ?>
                                        </div>

                                        <div class="squeeze-box-footer">
                                            <button disabled name="squeeze_save_path_button" class="button button-secondary button-large" type="button">
                                                <svg class="squeeze-icon">
                                                    <use xlink:href="#open-folder-outline-icon"></use>
                                                </svg>
                                                <?php 
            esc_attr_e( 'Select Directory', 'squeeze' );
            ?>
                                            </button>
                                        </div>
                                    </dialog>
                                </div>
                                <div class="squeeze-box-content__col">
                                    <button name="squeeze_bulk_path_button" class="button button-primary button-hero" type="button">
                                        <svg class="squeeze-icon">
                                            <use xlink:href="#play-button-round-icon"></use>
                                        </svg>
                                        <?php 
            esc_attr_e( 'Run Directory Squeeze', 'squeeze' );
            ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
        }
        ?>
                    
                    <?php 
        ?>

                    
                    <div class="squeeze-box-bulk-grid__row">
                        <div class="squeeze-box-content">
                            <p class="squeeze-hint">
                                <?php 
        $images_formats = self::$SqueezeHelpers->get_image_formats();
        $images_formats = implode( ', ', $images_formats );
        esc_html_e( 'Processed formats:', 'squeeze' );
        echo esc_html( '&nbsp;' . $images_formats );
        ?>
                            </p>
                        </div>
                    </div>

                </div>
            </section>
            <section class="squeeze-box" name="squeeze_bulk_log" id="squeeze_bulk_log" contenteditable="false">
                <div class="squeeze-log-placeholder">
                    <?php 
        esc_html_e( 'Log will be displayed here', 'squeeze' );
        ?>
                </div>
                <div id="squeeze-log-data"></div>
                <div id="squeeze-anchor"></div><!-- to force scroll to the bottom -->
                <input type="hidden" value="<?php 
        echo wp_kses_data( $not_compressed_posts );
        ?>" name="squeeze_bulk_ids" />
                <input type="hidden" value="<?php 
        echo wp_kses_data( $all_posts );
        ?>" name="squeeze_bulk_all_ids" />
                <input type="hidden" value="<?php 
        echo wp_kses_data( $total_pages );
        ?>" name="squeeze_bulk_total_pages" />
                <input type="hidden" value="<?php 
        echo wp_kses_data( $uncompressed_pages );
        ?>" name="squeeze_bulk_uncompressed_pages" />
            </section>
        </div>
        <?php 
    }

    public function options_page_html() {
        // check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( !function_exists( 'apache_get_modules' ) || !in_array( 'mod_rewrite', apache_get_modules() ) ) {
            $is_auto_webp = self::$SqueezeHelpers->get_option( 'auto_webp' );
            $is_webp_replace_urls = self::$SqueezeHelpers->get_option( 'webp_replace_urls' );
            if ( $is_auto_webp && !$is_webp_replace_urls ) {
                add_settings_error(
                    'squeeze_notices',
                    'squeeze_notices',
                    __( 'The Apache mod_rewrite module is not enabled on your server OR your server is not running Apache.', 'squeeze' ) . '<br>' . __( 'In order to make WebP serving work, you need to check the "Replace images URLs" option OR enable the mod_rewrite module.', 'squeeze' ),
                    'warning'
                );
            }
        }
        ?>
        <div class="wrap">
            <h1>
                <?php 
        echo esc_html( get_admin_page_title() );
        ?>
            </h1>
            <nav class="nav-tab-wrapper">
                <a href="#squeeze_basic" class="nav-tab nav-tab-active"><?php 
        esc_html_e( 'Basic Settings', 'squeeze' );
        ?></a>
                <a href="#squeeze_jpeg" class="nav-tab"><?php 
        esc_html_e( 'JPEG Settings', 'squeeze' );
        ?></a>
                <a href="#squeeze_png" class="nav-tab"><?php 
        esc_html_e( 'PNG Settings', 'squeeze' );
        ?></a>
                <a href="#squeeze_webp" class="nav-tab"><?php 
        esc_html_e( 'WEBP Settings', 'squeeze' );
        ?></a>
                <a href="#squeeze_avif" class="nav-tab"><?php 
        esc_html_e( 'AVIF Settings', 'squeeze' );
        ?></a>
            </nav>
            <div class="tab-content">
                <form action="options.php" method="post">
                    <?php 
        settings_errors( 'squeeze_notices' );
        settings_fields( 'squeeze_options' );
        //do_settings_sections( 'squeeze_options' );
        ?>

                    <section id="squeeze_basic">
                        <div class="squeeze-box squeeze-box--settings">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'Basic Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_basic_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_basic_settings' );
        ?>
                            </table>
                        </div>
                    </section>
                    <section id="squeeze_jpeg">
                        <div class="squeeze-box squeeze-box--settings">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'JPEG Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_jpeg_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_jpeg_settings' );
        ?>
                            </table>
                        </div>
                        <div class="squeeze-box">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'JPEG Advanced Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_jpeg_advanced_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_jpeg_advanced_settings' );
        ?>
                            </table>
                        </div>
                    </section>
                    <section id="squeeze_png">
                        <div class="squeeze-box squeeze-box--settings">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'PNG Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_png_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_png_settings' );
        ?>
                            </table>
                        </div>
                    </section>
                    <section id="squeeze_webp">
                        <div class="squeeze-box squeeze-box--settings">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'WEBP Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_webp_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_webp_settings' );
        ?>
                            </table>
                        </div>
                    </section>
                    <section id="squeeze_avif">
                        <div class="squeeze-box squeeze-box--settings">
                            <div class="squeeze-box-header">
                                <div class="squeeze-box-header__col">
                                    <h2><?php 
        esc_html_e( 'AVIF Settings', 'squeeze' );
        ?></h2>
                                    <?php 
        $this->setting_avif_desc();
        ?>
                                </div>
                            </div>
                            <table class="form-table" role="presentation">
                                <?php 
        do_settings_fields( 'squeeze_options', 'squeeze_avif_settings' );
        ?>
                            </table>
                        </div>
                    </section>

                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php 
        esc_attr_e( 'Save Changes' );
        ?>">
                        <input name="squeeze_restore_button" class="button button-secondary" type="button" value="<?php 
        esc_attr_e( 'Restore defaults', 'squeeze' );
        ?>" />
                    </p>
                </form>
            </div>
        </div>
        <?php 
    }

    public function register_settings() {
        $auto_webp = self::$SqueezeHelpers->get_option( 'auto_webp' );
        register_setting( 'squeeze_options', 'squeeze_options', [$this, 'options_validate'] );
        add_settings_section(
            'squeeze_basic_settings',
            __( 'Basic Settings', 'squeeze' ),
            'squeeze_setting_basic_desc',
            'squeeze_options',
            array(
                'section_class' => 'squeeze_basic',
            )
        );
        add_settings_field(
            'squeeze_setting_auto_compress',
            __( 'Squeeze on upload', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'auto_compress',
                'class'     => 'squeeze_setting_auto_compress',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_backup_original',
            __( 'Backup original image', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'backup_original',
                'class'     => 'squeeze_setting_backup_original',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_compress_formats',
            __( 'Image formats', 'squeeze' ) . self::$SqueezeHelpers->get_hint( __( 'Select which image formats you want to be squeezed.', 'squeeze' ) ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'compress_formats',
                'class'     => 'squeeze_setting_compress_formats',
                'type'      => 'formats_checkbox_group',
            )
        );
        add_settings_field(
            'squeeze_setting_auto_webp',
            __( 'Generate WEBP', 'squeeze' ) . self::$SqueezeHelpers->get_hint( __( 'Generate and serve WEBP images for all uploaded images. The original image URL remains the same, instead the image will be served in a WEBP format.', 'squeeze' ) ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'auto_webp',
                'class'     => 'squeeze_setting_auto_webp',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_webp_replace_urls',
            __( 'Replace images URLs', 'squeeze' ) . self::$SqueezeHelpers->get_hint( __( 'If the method above does not work, check this option. It replaces original URLs of images with the related WEBP images.', 'squeeze' ) ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'webp_replace_urls',
                'class'     => 'squeeze_setting_webp_replace_urls',
                'type'      => 'checkbox',
                'hidden'    => ( $auto_webp ? '' : true ),
            )
        );
        add_settings_field(
            'squeeze_setting_compress_thumbs',
            __( 'Squeeze thumbnails', 'squeeze' ) . self::$SqueezeHelpers->get_hint( __( 'Choose which image thumbnail sizes you want to squeeze along with the original image.', 'squeeze' ) ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'compress_thumbs',
                'class'     => 'squeeze_setting_compress_thumbs',
                'type'      => 'thumbs_checkbox_group',
            )
        );
        add_settings_field(
            'squeeze_setting_timeout',
            __( 'Squeeze timeout', 'squeeze' ) . self::$SqueezeHelpers->get_hint( __( 'Time limit for squeezing an image. If you get an error during image squeezing, try to increase this value.', 'squeeze' ) ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'timeout',
                'class'     => 'squeeze_setting_timeout',
                'type'      => 'number',
                'units'     => 'sec',
                'min'       => 1,
            )
        );
        add_settings_field(
            'squeeze_setting_restore_defaults',
            '',
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_basic_settings',
            array(
                'label_for' => 'restore_defaults',
                'class'     => 'squeeze_setting_restore_defaults',
                'type'      => 'hidden',
            )
        );
        add_settings_section(
            'squeeze_jpeg_settings',
            __( 'JPEG Settings', 'squeeze' ),
            'squeeze_setting_jpeg_desc',
            'squeeze_options',
            array(
                'section_class' => 'squeeze_jpeg',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_quality',
            __( 'Quality', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_settings',
            array(
                'label_for' => 'jpeg_quality',
                'class'     => 'squeeze_setting_jpeg_quality',
                'type'      => 'range',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_smoothing',
            __( 'Smoothing', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_settings',
            array(
                'label_for' => 'jpeg_smoothing',
                'class'     => 'squeeze_setting_jpeg_smoothing',
                'type'      => 'range',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_baseline',
            __( 'Pointless spec compliance', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_baseline',
                'class'     => 'squeeze_setting_jpeg_baseline',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_arithmetic',
            __( 'Arithmetic', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_arithmetic',
                'class'     => 'squeeze_setting_jpeg_arithmetic',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_progressive',
            __( 'Progressive rendering', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_progressive',
                'class'     => 'squeeze_setting_jpeg_progressive',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_optimize_coding',
            __( 'Optimize Huffman table', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_optimize_coding',
                'class'     => 'squeeze_setting_jpeg_optimize_coding',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_color_space',
            __( 'Channels', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_color_space',
                'class'     => 'squeeze_setting_jpeg_color_space',
                'type'      => 'select',
                'options'   => array(
                    '3' => 'YCbCr',
                    '1' => 'Grayscale',
                    '2' => 'RGB',
                ),
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_quant_table',
            __( 'Quantization', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_quant_table',
                'class'     => 'squeeze_setting_jpeg_quant_table',
                'type'      => 'select',
                'options'   => array(
                    '0' => 'JPEG Annex K',
                    '1' => 'Flat',
                    '2' => 'MSSIM-tuned Kodak',
                    '3' => 'ImageMagick',
                    '4' => 'PSNR-HVS-M-tuned Kodak',
                    '5' => 'Klein et al',
                    '6' => 'Watson et al',
                    '7' => 'Ahumada et al',
                    '8' => 'Peterson et al',
                ),
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_trellis_multipass',
            __( 'Trellis multipass', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_trellis_multipass',
                'class'     => 'squeeze_setting_jpeg_trellis_multipass',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_trellis_opt_zero',
            __( 'Optimize zero block runs', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_trellis_opt_zero',
                'class'     => 'squeeze_setting_jpeg_trellis_opt_zero',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_trellis_opt_table',
            __( 'Optimize after trellis quantization', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_trellis_opt_table',
                'class'     => 'squeeze_setting_jpeg_trellis_opt_table',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_trellis_loops',
            __( 'Trellis quantization passes', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_trellis_loops',
                'class'     => 'squeeze_setting_jpeg_trellis_loops',
                'type'      => 'range',
                'min'       => 1,
                'max'       => 50,
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_auto_subsample',
            __( 'Auto subsample chroma', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_auto_subsample',
                'class'     => 'squeeze_setting_jpeg_auto_subsample',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_chroma_subsample',
            __( 'Subsample chroma by', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_chroma_subsample',
                'class'     => 'squeeze_setting_jpeg_chroma_subsample',
                'type'      => 'range',
                'min'       => 1,
                'max'       => 4,
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_separate_chroma_quality',
            __( 'Separate chroma quality', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_separate_chroma_quality',
                'class'     => 'squeeze_setting_jpeg_separate_chroma_quality',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_jpeg_chroma_quality',
            __( 'Chroma quality', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_jpeg_advanced_settings',
            array(
                'label_for' => 'jpeg_chroma_quality',
                'class'     => 'squeeze_setting_jpeg_chroma_quality',
                'type'      => 'range',
            )
        );
        add_settings_section(
            'squeeze_png_settings',
            __( 'PNG Settings', 'squeeze' ),
            'squeeze_setting_png_desc',
            'squeeze_options',
            array(
                'section_class' => 'squeeze_png',
            )
        );
        add_settings_field(
            'squeeze_setting_png_level',
            __( 'Effort', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_png_settings',
            array(
                'label_for' => 'png_level',
                'class'     => 'squeeze_setting_png_level',
                'type'      => 'range',
                'min'       => 0,
                'max'       => 3,
            )
        );
        add_settings_field(
            'squeeze_setting_png_interlace',
            __( 'Interlace', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_png_settings',
            array(
                'label_for' => 'png_interlace',
                'class'     => 'squeeze_setting_png_interlace',
                'type'      => 'checkbox',
            )
        );
        add_settings_section(
            'squeeze_webp_settings',
            __( 'WEBP Settings', 'squeeze' ),
            'squeeze_setting_webp_desc',
            'squeeze_options',
            array(
                'section_class' => 'squeeze_webp',
            )
        );
        add_settings_field(
            'squeeze_setting_webp_method',
            __( 'Effort', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_webp_settings',
            array(
                'label_for' => 'webp_method',
                'class'     => 'squeeze_setting_webp_method',
                'type'      => 'range',
                'min'       => 0,
                'max'       => 6,
            )
        );
        add_settings_field(
            'squeeze_setting_webp_quality',
            __( 'Quality', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_webp_settings',
            array(
                'label_for' => 'webp_quality',
                'class'     => 'squeeze_setting_webp_quality',
                'type'      => 'range',
                'min'       => 0,
                'max'       => 100,
            )
        );
        add_settings_field(
            'squeeze_setting_webp_lossless',
            __( 'Lossless', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_webp_settings',
            array(
                'label_for' => 'webp_lossless',
                'class'     => 'squeeze_setting_webp_lossless',
                'type'      => 'checkbox',
            )
        );
        add_settings_field(
            'squeeze_setting_webp_near_lossless',
            __( 'Near lossless', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_webp_settings',
            array(
                'label_for' => 'webp_near_lossless',
                'class'     => 'squeeze_setting_webp_near_lossless',
                'type'      => 'range',
                'min'       => 0,
                'max'       => 100,
            )
        );
        add_settings_section(
            'squeeze_avif_settings',
            __( 'AVIF Settings', 'squeeze' ),
            'squeeze_setting_avif_desc',
            'squeeze_options',
            array(
                'section_class' => 'squeeze_avif',
            )
        );
        add_settings_field(
            'squeeze_setting_avif_cqLevel',
            __( 'Quality', 'squeeze' ),
            [$this, 'options_callback'],
            'squeeze_options',
            'squeeze_avif_settings',
            array(
                'label_for' => 'avif_cqLevel',
                'class'     => 'squeeze_setting_avif_cqLevel',
                'type'      => 'range',
                'min'       => 1,
                'max'       => 100,
            )
        );
    }

    public function options_validate( $input ) {
        $input['jpeg_quality'] = absint( $input['jpeg_quality'] );
        $input['jpeg_smoothing'] = absint( $input['jpeg_smoothing'] );
        $input['jpeg_color_space'] = absint( $input['jpeg_color_space'] );
        $input['jpeg_quant_table'] = absint( $input['jpeg_quant_table'] );
        $input['jpeg_trellis_loops'] = absint( $input['jpeg_trellis_loops'] );
        $input['jpeg_chroma_subsample'] = absint( $input['jpeg_chroma_subsample'] );
        $input['jpeg_chroma_quality'] = absint( $input['jpeg_chroma_quality'] );
        $input['png_level'] = absint( $input['png_level'] );
        $input['webp_method'] = absint( $input['webp_method'] );
        $input['webp_quality'] = absint( $input['webp_quality'] );
        $input['webp_near_lossless'] = absint( $input['webp_near_lossless'] );
        $input['avif_cqLevel'] = absint( $input['avif_cqLevel'] );
        $input['jpeg_baseline'] = ( isset( $input['jpeg_baseline'] ) ? $input['jpeg_baseline'] : '0' );
        $input['jpeg_arithmetic'] = ( isset( $input['jpeg_arithmetic'] ) ? $input['jpeg_arithmetic'] : '0' );
        $input['jpeg_progressive'] = ( isset( $input['jpeg_progressive'] ) ? $input['jpeg_progressive'] : '0' );
        $input['jpeg_optimize_coding'] = ( isset( $input['jpeg_optimize_coding'] ) ? $input['jpeg_optimize_coding'] : '0' );
        $input['jpeg_trellis_multipass'] = ( isset( $input['jpeg_trellis_multipass'] ) ? $input['jpeg_trellis_multipass'] : '0' );
        $input['jpeg_trellis_opt_zero'] = ( isset( $input['jpeg_trellis_opt_zero'] ) ? $input['jpeg_trellis_opt_zero'] : '0' );
        $input['jpeg_trellis_opt_table'] = ( isset( $input['jpeg_trellis_opt_table'] ) ? $input['jpeg_trellis_opt_table'] : '0' );
        $input['jpeg_auto_subsample'] = ( isset( $input['jpeg_auto_subsample'] ) ? $input['jpeg_auto_subsample'] : '0' );
        $input['jpeg_separate_chroma_quality'] = ( isset( $input['jpeg_separate_chroma_quality'] ) ? $input['jpeg_separate_chroma_quality'] : '0' );
        $input['png_interlace'] = ( isset( $input['png_interlace'] ) ? $input['png_interlace'] : '0' );
        $input['webp_lossless'] = ( isset( $input['webp_lossless'] ) ? $input['webp_lossless'] : '0' );
        $input['auto_compress'] = ( isset( $input['auto_compress'] ) ? $input['auto_compress'] : '0' );
        $input['auto_webp'] = ( isset( $input['auto_webp'] ) ? $input['auto_webp'] : '0' );
        $input['webp_replace_urls'] = ( isset( $input['webp_replace_urls'] ) && $input['auto_webp'] ? $input['webp_replace_urls'] : '0' );
        $input['cdn_url'] = ( isset( $input['cdn_url'] ) && $input['auto_webp'] ? $input['cdn_url'] : '' );
        $input['backup_original'] = ( isset( $input['backup_original'] ) ? $input['backup_original'] : '0' );
        $input['compress_formats'] = ( isset( $input['compress_formats'] ) ? $input['compress_formats'] : array() );
        $input['compress_thumbs'] = ( isset( $input['compress_thumbs'] ) ? $input['compress_thumbs'] : array() );
        $input['max_width'] = ( isset( $input['max_width'] ) && $input['max_width'] > 0 ? absint( $input['max_width'] ) : '' );
        $input['max_height'] = ( isset( $input['max_height'] ) && $input['max_height'] > 0 ? absint( $input['max_height'] ) : '' );
        $input['excluded_images'] = ( isset( $input['excluded_images'] ) ? $input['excluded_images'] : '' );
        $input['timeout'] = ( isset( $input['timeout'] ) && $input['timeout'] > 0 ? absint( $input['timeout'] ) : 1 );
        $input['restore_defaults'] = ( isset( $input['restore_defaults'] ) ? $input['restore_defaults'] : '0' );
        if ( !isset( $input['restore_defaults'] ) || $input['restore_defaults'] !== '1' ) {
            add_settings_error(
                'squeeze_notices',
                'settings_updated',
                __( 'Settings have been updated.', 'squeeze' ),
                'success'
            );
        }
        return $input;
    }

    public function options_callback( $args ) {
        $label_for = $args['label_for'];
        $class = $args['class'];
        $type = $args['type'];
        $default = self::$SqueezeHelpers->get_default_value( $label_for );
        $options = get_option( 'squeeze_options' );
        $is_hidden = ( isset( $args['hidden'] ) && $args['hidden'] ? 'hidden' : '' );
        $extra_classes = [];
        if ( $is_hidden ) {
            $extra_classes[] = 'squeeze-hidden';
        }
        $extra_classes = implode( ' ', $extra_classes );
        switch ( $type ) {
            case 'text':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                $placeholder = ( isset( $args['placeholder'] ) ? $args['placeholder'] : '' );
                echo "<input class='" . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]' type='text' value='" . esc_attr( $value ) . "' placeholder='" . esc_attr( $placeholder ) . "' />";
                break;
            case 'number':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                $units = ( isset( $args['units'] ) ? $args['units'] : '' );
                $min = ( isset( $args['min'] ) ? $args['min'] : '' );
                $max = ( isset( $args['max'] ) ? $args['max'] : '' );
                echo "<input class='" . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]' type='number' value='" . esc_attr( $value ) . "' " . (( $min ? "min='" . esc_attr( $min ) . "'" : "" )) . " " . (( $max ? "min='" . esc_attr( $max ) . "'" : "" )) . " />";
                if ( $units ) {
                    echo "<span class='squeeze-setting-units'>" . esc_html( $units ) . "</span>";
                }
                break;
            case 'range':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                $min = ( isset( $args['min'] ) ? $args['min'] : 0 );
                $max = ( isset( $args['max'] ) ? $args['max'] : 100 );
                $step = ( isset( $args['step'] ) ? $args['step'] : 1 );
                echo "<input class='" . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]' min='" . (int) $min . "' max='" . (int) $max . "' step='" . (int) $step . "' type='range' value='" . esc_attr( $value ) . "' />";
                echo '<output id="squeeze_setting_' . esc_attr( $label_for ) . '_value"></output>';
                ?>
                <script>
                    (function () {
                        const value = document.querySelector("#squeeze_setting_<?php 
                echo esc_attr( $label_for );
                ?>_value")
                        const input = document.querySelector("#squeeze_setting_<?php 
                echo esc_attr( $label_for );
                ?>")
                        value.textContent = input.value
                        input.addEventListener("input", (event) => {
                            value.textContent = event.target.value
                        })
                    })()
                </script>
                <?php 
                break;
            case 'checkbox':
                $value = ( isset( $options[$label_for] ) ? (bool) $options[$label_for] : $default );
                echo "<input class='squeeze-ios8-switch " . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]' type='checkbox' " . checked( $value, true, false ) . " />";
                echo '<label for="squeeze_setting_' . esc_attr( $label_for ) . '"></label>';
                break;
            case 'select':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                echo "<select class='" . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]'>";
                foreach ( $args['options'] as $key => $option ) {
                    echo "<option value='" . esc_attr( $key ) . "' " . selected( $value, $key, false ) . ">" . esc_html( $option ) . "</option>";
                }
                echo "</select>";
                break;
            case 'thumbs_checkbox_group':
                $thumbs = array();
                $value = ( isset( $options[$label_for] ) ? (array) $options[$label_for] : $default );
                $available_sizes = wp_get_registered_image_subsizes();
                foreach ( $available_sizes as $key => $size ) {
                    $thumbs[$key] = ucwords( str_replace( '_', ' ', $key ) ) . ' (' . $size['width'] . 'x' . $size['height'] . ')';
                }
                // Add the scaled image size option
                $big_image_size_threshold = apply_filters( 'big_image_size_threshold', 2560 );
                $thumbs['full'] = 'Scaled (' . $big_image_size_threshold . 'x' . $big_image_size_threshold . ')';
                echo '<div class="squeeze-box squeeze-box--fieldset">';
                echo '<div class="squeeze-box-content">';
                foreach ( $thumbs as $key => $option ) {
                    echo '<div class="squeeze-suboption">';
                    echo "<input class='squeeze-ios8-switch' id='squeeze_setting_" . esc_attr( $label_for ) . "_" . esc_attr( $key ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "][" . esc_attr( $key ) . "]' type='checkbox' " . checked( array_key_exists( $key, $value ), true, false ) . " /> ";
                    echo "<label for='squeeze_setting_" . esc_attr( $label_for ) . "_" . esc_attr( $key ) . "'>" . esc_html( $option ) . "</label>";
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                break;
            case 'formats_checkbox_group':
                $formats = self::ALLOWED_IMAGE_FORMATS;
                $value = ( isset( $options[$label_for] ) ? (array) $options[$label_for] : $default );
                echo '<div class="squeeze-box squeeze-box--fieldset">';
                echo '<div class="squeeze-box-content">';
                foreach ( $formats as $key => $option ) {
                    echo '<div class="squeeze-suboption">';
                    echo "<input class='squeeze-ios8-switch' id='squeeze_setting_" . esc_attr( $label_for ) . "_" . esc_attr( $key ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "][" . esc_attr( $key ) . "]' type='checkbox' " . checked( array_key_exists( $key, $value ), true, false ) . " /> ";
                    echo "<label for='squeeze_setting_" . esc_attr( $label_for ) . "_" . esc_attr( $key ) . "'>" . esc_html( $key ) . "</label>";
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                break;
            case 'hidden':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                echo "<input id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]' type='hidden' value='" . esc_attr( $value ) . "' />";
                break;
            case 'textarea':
                $value = ( isset( $options[$label_for] ) ? $options[$label_for] : $default );
                echo "<textarea class='" . esc_attr( $extra_classes ) . "' id='squeeze_setting_" . esc_attr( $label_for ) . "' name='squeeze_options[" . esc_attr( $label_for ) . "]'>" . esc_textarea( $value ) . "</textarea>";
                break;
        }
    }

    public function setting_basic_desc() {
        echo '<p>' . esc_html__( 'Basic squeezing settings.', 'squeeze' ) . '</p>';
    }

    public function setting_jpeg_desc() {
        echo '<p>' . esc_html__( 'Squeezing settings for JPEG images.', 'squeeze' ) . '</p>';
    }

    public function setting_jpeg_advanced_desc() {
        echo '<p>' . esc_html__( 'More precise settings for experienced users.', 'squeeze' ) . '</p>';
    }

    public function setting_png_desc() {
        echo '<p>' . esc_html__( 'Squeezing settings for PNG images.', 'squeeze' ) . '</p>';
    }

    public function setting_webp_desc() {
        echo '<p>' . esc_html__( 'Squeezing settings for WebP images.', 'squeeze' ) . '</p>';
    }

    public function setting_avif_desc() {
        echo '<p>' . esc_html__( 'Squeezing settings for Avif images.', 'squeeze' ) . '</p>';
    }

    public function restore_defaults( $old_value, $value ) {
        if ( isset( $value['restore_defaults'] ) && $value['restore_defaults'] === '1' ) {
            $result = delete_option( 'squeeze_options', "" );
            if ( $result ) {
                add_settings_error(
                    'squeeze_notices',
                    'settings_restored',
                    __( 'Settings have been restored.', 'squeeze' ),
                    'success'
                );
            } else {
                add_settings_error(
                    'squeeze_notices',
                    'settings_not_restored',
                    __( 'Settings have not been restored.', 'squeeze' ),
                    'error'
                );
            }
        }
    }

    public function flush_rewrite_rules( $old_value, $value ) {
        if ( isset( $value['auto_webp'] ) && $value['auto_webp'] !== $old_value['auto_webp'] ) {
            flush_rewrite_rules();
            add_settings_error(
                'squeeze_notices',
                'rewrite_rules_flushed',
                __( 'Rewrite rules have been flushed.', 'squeeze' ),
                'success'
            );
        }
    }

    public function add_custom_field_to_attachment( $form_fields, $post ) {
        $allowed_mimes = self::$SqueezeHelpers->get_image_formats( true );
        if ( in_array( $post->post_mime_type, $allowed_mimes ) ) {
            $is_compressed = get_post_meta( $post->ID, 'squeeze_is_compressed', true );
            $can_restore = self::$SqueezeHelpers->can_restore( $post->ID );
            $form_fields['squeeze_is_compressed'] = array(
                'label' => __( 'Squeeze', 'squeeze' ),
                'input' => 'html',
                'html'  => ( $is_compressed ? '<label><span class="squeeze_status"><span style="padding-top: 0; line-height: 1; color: green;" class="dashicons dashicons-yes-alt"></span>&nbsp;' . __( 'Squeezed', 'squeeze' ) . '</span></label>' . (( $can_restore ? '
                    <br><br><p><button name="squeeze_restore" type="button" class="button button-secondary squeeze-restore-button" data-attachment="' . $post->ID . '">' . __( 'Restore original', 'squeeze' ) . '</button></p>' : '' )) . '<p>
                    <button name="squeeze_compress_again" type="button" class="button button-primary squeeze-compress-button" data-attachment="' . $post->ID . '">' . __( 'Squeeze again', 'squeeze' ) . '</button></p>' : '<label><span class="squeeze_status"><span style="padding-top: 0; line-height: 1; color: red;" class="dashicons dashicons-no-alt"></span>&nbsp;' . __( 'Not squeezed', 'squeeze' ) . '</span></label>
                    <br><br><button name="squeeze_compress_single" type="button" class="button button-primary squeeze-compress-button" data-attachment="' . $post->ID . '">' . __( 'Squeeze', 'squeeze' ) . '</button>' ),
            );
        }
        return $form_fields;
    }

    public function add_media_columns( $posts_columns ) {
        $posts_columns['squeeze'] = __( 'Squeeze', 'squeeze' );
        return $posts_columns;
    }

    public function media_custom_column( $column_name, $post_id ) {
        if ( 'squeeze' !== $column_name ) {
            return;
        }
        $form_fields = $this->add_custom_field_to_attachment( array(), get_post( $post_id ) );
        if ( $form_fields ) {
            echo wp_kses_post( $form_fields['squeeze_is_compressed']['html'] );
        }
    }

    public function sortable_columns( $columns ) {
        $columns['squeeze'] = 'squeeze';
        return $columns;
    }

    public function sortable_columns_orderby( $query ) {
        if ( !is_admin() || !$query->is_main_query() ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( 'squeeze' === $orderby ) {
            // Define the allowed image formats
            $allowed_formats = self::$SqueezeHelpers->get_image_formats( true );
            // include all media
            $query->set( 'meta_query', array(
                'relation' => 'OR',
                array(
                    'key'     => 'squeeze_is_compressed',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'squeeze_is_compressed',
                    'compare' => 'EXISTS',
                ),
            ) );
            // Add the meta_query to filter by specific image formats
            //$query->set('post_mime_type', $allowed_formats);
            $query->set( 'orderby', 'meta_value' );
        }
    }

    public function media_filter_dropdown() {
        global $wp_query;
        $post_type = 'attachment';
        if ( isset( $_GET['post_type'] ) ) {
            $post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
        }
        if ( 'attachment' === $post_type ) {
            $selected = ( isset( $_GET['squeeze_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['squeeze_filter'] ) ) : '' );
            $options = array(
                'all'          => __( 'Squeeze: All images', 'squeeze' ),
                'non-squeezed' => __( 'Non Squeezed Images', 'squeeze' ),
            );
            echo '<select name="squeeze_filter" id="squeeze-filter">';
            foreach ( $options as $value => $label ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $value ),
                    selected( $selected, $value, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
        }
    }

    public function media_filter_query( $query ) {
        global $current_screen;
        if ( !is_admin() || !empty( $current_screen ) && 'upload' !== $current_screen->base || 'attachment' !== $query->get( 'post_type' ) ) {
            return $query;
        }
        if ( isset( $_GET['squeeze_filter'] ) ) {
            $squeeze_filter = sanitize_text_field( wp_unslash( $_GET['squeeze_filter'] ) );
            if ( 'non-squeezed' === $squeeze_filter ) {
                $query->set( 'meta_query', array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'squeeze_is_compressed',
                        'compare' => '!=',
                        'value'   => '1',
                    ),
                    array(
                        'key'     => 'squeeze_is_compressed',
                        'compare' => 'NOT EXISTS',
                    ),
                ) );
                $query->set( 'orderby', 'meta_value' );
                $query->set( 'post_mime_type', self::$SqueezeHelpers->get_image_formats( true ) );
            }
        }
        return $query;
    }

    public function media_filter_ajax_query( $query ) {
        if ( !isset( $_POST['query']['squeeze_filter'] ) ) {
            return $query;
        }
        $squeeze_filter = sanitize_text_field( wp_unslash( $_POST['query']['squeeze_filter'] ) );
        if ( 'non-squeezed' === $squeeze_filter ) {
            $query['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'squeeze_is_compressed',
                    'compare' => '!=',
                    'value'   => '1',
                ),
                array(
                    'key'     => 'squeeze_is_compressed',
                    'compare' => 'NOT EXISTS',
                ),
            );
            $query['orderby'] = 'meta_value';
            $query['post_mime_type'] = self::$SqueezeHelpers->get_image_formats( true );
        }
        return $query;
    }

    public function svg_sprite_output() {
        global $pagenow;
        if ( $pagenow === 'upload.php' && isset( $_GET['page'] ) && $_GET['page'] === 'squeeze-bulk' ) {
            include self::$PLUGIN_DIR . 'assets/images/sprite.svg';
        }
    }

}

<?php

/**
 * Plugin Name: Squeeze – Image Optimization & Compression, WebP Conversion
 * Description: Compress unlimited images directly into your browser. Convert images to WebP format. No limits on file size or number of images. No third-party services or API keys required.
 * Author URI:  https://bogdan.kyiv.ua
 * Author:      Bogdan Bendziukov
 * Version:     1.6
 *
 * Text Domain: squeeze
 * Domain Path: /languages
 *
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * 
 */
namespace Squeeze;

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SqueezeInit {
    /**
     * Plugin version
     */
    const VERSION = '1.6';

    /**
     * Allowed image formats
     */
    const ALLOWED_IMAGE_FORMATS = array(
        'jpg'  => 'JPG',
        'jpeg' => 'JPEG',
        'png'  => 'PNG',
        'webp' => 'WebP',
        'avif' => 'AVIF',
    );

    public static $SqueezeHelpers;

    public static $SqueezeSettings;

    public static $SqueezeHandlers;

    public static $SqueezePremium;

    /**
     * Media per page
     */
    public static $MEDIA_PER_PAGE;

    /**
     * Plugin directory
     */
    public static $PLUGIN_DIR;

    /**
     * Plugin URL
     */
    public static $PLUGIN_URL;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        self::$PLUGIN_DIR = plugin_dir_path( __FILE__ );
        self::$PLUGIN_URL = plugin_dir_url( __FILE__ );
        self::$MEDIA_PER_PAGE = apply_filters( 'squeeze_media_per_page', 50 );
        
        $this->load_helpers();
        $this->load_handlers();
        $this->load_settings();
        self::$SqueezeHelpers = new SqueezeHelpers();
        self::$SqueezeSettings = new SqueezeSettings();
        self::$SqueezeHandlers = new SqueezeHandlers();
        add_action( 'plugins_loaded', array($this, 'load_textdomain') );
        add_action( 'admin_print_footer_scripts', array($this, 'load_assets') );
        add_filter(
            'plugin_action_links',
            array($this, 'plugin_action_links'),
            10,
            2
        );
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'squeeze', false, self::$PLUGIN_DIR . '/languages/' );
    }

    public function load_settings() {
        require_once self::$PLUGIN_DIR . 'inc/settings.php';
    }

    public function load_handlers() {
        require_once self::$PLUGIN_DIR . 'inc/handlers.php';
    }

    public function load_helpers() {
        require_once self::$PLUGIN_DIR . 'inc/helpers.php';
    }

    /**
     * Enqueue assets
     */
    public function load_assets() {
        global $pagenow;
        $options = get_option( 'squeeze_options' );
        $default_options = self::$SqueezeHelpers->get_default_value( null, true );
        // get all default values
        $js_options = array();
        foreach ( $default_options as $key => $value ) {
            if ( isset( $options[$key] ) ) {
                if ( is_numeric( $options[$key] ) ) {
                    $js_options[$key] = intval( $options[$key] );
                } elseif ( $options[$key] === "on" ) {
                    $js_options[$key] = true;
                } elseif ( $key === "compress_thumbs" ) {
                    $js_options[$key] = $options[$key];
                }
            } else {
                $js_options[$key] = $value;
            }
        }
        if ( !wp_script_is( 'media-editor', 'enqueued' ) ) {
            wp_enqueue_media();
        }
        // Enqueue script for backend.
        wp_enqueue_script(
            'squeeze-script',
            self::$PLUGIN_URL . 'assets/js/script.bundle.js',
            array('jquery', 'wp-mediaelement'),
            self::VERSION,
            true
        );
        // WP Localized globals. Use dynamic PHP stuff in JavaScript via `squeeze` object.
        wp_localize_script( 
            'squeeze-script',
            'squeezeOptions',
            // Array containing dynamic data for a JS Global.
            [
                'pluginUrl'    => self::$PLUGIN_URL,
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'squeeze-nonce' ),
                'options'      => wp_json_encode( $js_options ),
                'templateBase' => self::$PLUGIN_URL . 'assets/templates/',
                'templates'    => array(
                    'logWrapper'         => self::$PLUGIN_URL . 'assets/templates/log-wrapper.html',
                    'logStep'            => self::$PLUGIN_URL . 'assets/templates/log-step.html',
                    'logDetailsButton'   => self::$PLUGIN_URL . 'assets/templates/log-details-button.html',
                    'directoryItem'      => self::$PLUGIN_URL . 'assets/templates/directory-item.html',
                    'directoryItemEmpty' => self::$PLUGIN_URL . 'assets/templates/directory-item-empty.html',
                    'pathListItem'       => self::$PLUGIN_URL . 'assets/templates/path-list-item.html',
                ),
            ]
         );
        if ( $pagenow === 'upload.php' && isset( $_GET['page'] ) && $_GET['page'] === 'squeeze-bulk' ) {
            wp_localize_script( 'squeeze-script', 'squeezeBulk', [
                'allImages'          => implode( ",", self::$SqueezeHelpers->get_total_images() ),
                'unCompressedImages' => implode( ",", self::$SqueezeHelpers->get_uncompressed_images() ),
            ] );
        }
        // Check if we are on the options page for the plugin
        if ( $pagenow === 'upload.php' || $pagenow === 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] === 'squeeze' ) {
            wp_enqueue_script(
                'squeeze-settings-script',
                self::$PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                self::VERSION,
                true
            );
        }
        wp_set_script_translations( 'squeeze-script', 'squeeze', self::$PLUGIN_DIR . 'languages' );
        // Enqueue styles for backend.
        wp_enqueue_style(
            'squeeze-style',
            self::$PLUGIN_URL . 'assets/css/admin.css',
            array(),
            self::VERSION
        );
    }

    /**
     * Add settings link on plugin page
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links( $actions, $plugin_file ) {
        static $plugin;
        if ( !isset( $plugin ) ) {
            $plugin = plugin_basename( __FILE__ );
        }
        if ( $plugin === $plugin_file ) {
            $settings_link = array(
                'settings' => '<a href="' . admin_url( 'options-general.php?page=squeeze' ) . '">' . __( 'Settings', 'squeeze' ) . '</a>',
            );
            $bulk_link = array(
                'bulk' => '<a href="' . admin_url( 'upload.php?page=squeeze-bulk' ) . '">' . __( 'Bulk Squeeze', 'squeeze' ) . '</a>',
            );
            $actions = array_merge( $bulk_link, $actions );
            $actions = array_merge( $settings_link, $actions );
        }
        return $actions;
    }

}

new SqueezeInit();

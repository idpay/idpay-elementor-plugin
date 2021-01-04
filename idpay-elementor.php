<?php
/**
 * Plugin Name: IDPay Elementor
 * Description: IDPay payment gateway for the Elementor Pro
 * Author: IDPay
 * Version: 1.0.1
 * License: GPL v2.0.
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Domain Path: /languages/
 */

use Elementor\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main IDPay Elementor Extension Class
 *
 * The main class that initiates and runs the plugin.
 */
final class IDPay_Elementor_Extension {

    const PAGE_ID = 'idpay-transactions';
    const VERSION = '1.0.0';
    const IDPAY_TABLE_NAME = 'elementor_idpay_transactions';
    const MINIMUM_ELEMENTOR_VERSION = '2.0.0';
    const MINIMUM_PHP_VERSION = '7.0';
    private static $_instance = null;

    /**
     * Instance
     *
     * Ensures only one instance of the class is loaded or can be loaded.
     *
     * @return IDPay_Elementor_Extension An instance of the class.
     */
    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;

    }

    /**
     * Constructor
     */
    public function __construct() {

        add_action( 'init', [ $this, 'i18n' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ] );

    }

    /**
     * Load Textdomain
     * Load plugin localization files.
     * Fired by `init` action hook.
     */
    public function i18n() {

        load_plugin_textdomain( 'idpay-elementor', false, basename( dirname( __FILE__ ) ) . '/languages' );

    }

    /**
     * Initialize the plugin
     *
     * Load the plugin only after Elementor (and other plugins) are loaded.
     * Checks for basic plugin requirements, if one check fail don't continue,
     * if all check have passed load the files required to run the plugin.
     *
     * Fired by `plugins_loaded` action hook.
     */
    public function init() {

        // Check if Elementor installed and activated
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
            return;
        }

        // Check for required Elementor version
        if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_minimum_elementor_version' ] );
            return;
        }

        // Check for required PHP version
        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_minimum_php_version' ] );
            return;
        }

        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 200 );

        // Register widgets and form action
        add_action( 'elementor_pro/init', [ $this, 'register' ] );

        // handle payment callback
        if( !empty( $_GET['elementor_idpay_action'] ) ){
            if( sanitize_text_field($_GET['elementor_idpay_action']) == 'callback' ) {
                require_once( __DIR__ . '/includes/callback.php' );
                $callback = new IDPay_Payment_Callback;
                $callback->process();
            }
        }

    }

    /**
     * Warning when the site doesn't have Elementor installed or activated.
     */
    public function admin_notice_missing_main_plugin() {

        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

        $message = sprintf(
        /* translators: 1: Plugin name 2: Elementor */
            esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'idpay-elementor' ),
            '<strong>' . esc_html__( 'Elementor IDPay Extension', 'idpay-elementor' ) . '</strong>',
            '<strong>' . esc_html__( 'Elementor', 'idpay-elementor' ) . '</strong>'
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

    }

    /**
     * Warning when the site doesn't have a minimum required Elementor version.
     */
    public function admin_notice_minimum_elementor_version() {

        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

        $message = sprintf(
        /* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
            esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'idpay-elementor' ),
            '<strong>' . esc_html__( 'Elementor IDPay Extension', 'idpay-elementor' ) . '</strong>',
            '<strong>' . esc_html__( 'Elementor', 'idpay-elementor' ) . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

    }

    /**
     * Warning when the site doesn't have a minimum required PHP version.
     */
    public function admin_notice_minimum_php_version() {

        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

        $message = sprintf(
        /* translators: 1: Plugin name 2: PHP 3: Required PHP version */
            esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'idpay-elementor' ),
            '<strong>' . esc_html__( 'Elementor IDPay Extension', 'idpay-elementor' ) . '</strong>',
            '<strong>' . esc_html__( 'PHP', 'idpay-elementor' ) . '</strong>',
            self::MINIMUM_PHP_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

    }

    /**
     * Register admin page to list transactions
     */
    public function register_admin_menu() {
        $page_title = __( 'IDPay Transactions', 'idpay-elementor' );

        add_submenu_page(
            Settings::PAGE_ID,
            $page_title,
            $page_title,
            'manage_options',
            self::PAGE_ID,
            [ $this, 'list_transactions' ]
        );

    }

    /**
     * list all IDPay transactions
     */
    public function list_transactions() {
        require_once(__DIR__ . '/includes/list-transactions.php');
    }

    /**
     * Load required plugin core files.
     */
    public function includes() {
        require_once(__DIR__ . '/includes/action.php');
        require_once(__DIR__ . '/includes/widget.php');
    }

    /**
     * Registers Elementor Pro forms action to be triggered after form submits
     */
    public function register() {

        $this->includes();

        $idpay_action = new \IDPay_Action_After_Submit();
        \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( $idpay_action->get_name(), $idpay_action );

        \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Elementor_IDPay_Widget );

    }

    /**
     * Call the gateway endpoints.
     *
     * Try to get response from the gateway for 4 times.
     *
     * @param string $url
     * @param array $args
     * @return array|WP_Error
     */
    function call_gateway_endpoint( $url, $args ) {
        $tries = 4;

        while ( $tries ) {
            $response = wp_safe_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                $tries--;
                continue;
            } else {
                break;
            }
        }

        return $response;
    }

    /**
     * This is triggered when the plugin is going to be activated.
     *
     * Creates a table in database which stores all transactions.
     */
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::IDPAY_TABLE_NAME;

        if ( $wpdb->get_var( "show tables like '$table_name'" ) != $table_name ) {
            $collate = '';

            if ( $wpdb->has_cap( 'collation' ) ) {
                if ( ! empty($wpdb->charset ) ) {
                    $collate .= "DEFAULT CHARACTER SET utf8";
                }
                if ( ! empty($wpdb->collate ) ) {
                    $collate .= " COLLATE utf8_unicode_ci";
                }
            }
            $sql = "CREATE TABLE $table_name (
                id mediumint(11) NOT NULL AUTO_INCREMENT,
                order_id bigint(11) DEFAULT '0' NOT NULL,
                post_id bigint(11) DEFAULT '0' NOT NULL,
                trans_id VARCHAR(255) NOT NULL,
                track_id VARCHAR(255) NULL,
                amount bigint(11) DEFAULT '0' NOT NULL,
                phone VARCHAR(11) NULL,
                description VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL,
                created_at bigint(11) DEFAULT '0' NOT NULL,
                status VARCHAR(255) NOT NULL,
                log LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
                return_url LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
                PRIMARY KEY id (id)
            ) $collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta( $sql );
        }
    }

    /**
     * This is triggered when the plugin is going to be deactivated.
     */
    public static function deactivate() {
        // Nothing yet
    }
}

$IDPay_Elementor_Extension = new IDPay_Elementor_Extension;
$IDPay_Elementor_Extension::instance();

register_activation_hook( __FILE__, [$IDPay_Elementor_Extension, 'activate'] );
register_deactivation_hook( __FILE__, [$IDPay_Elementor_Extension, 'deactivate'] );

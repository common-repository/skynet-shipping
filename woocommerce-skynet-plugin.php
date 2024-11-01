<?php

/**
 * Plugin Name: Skynet Shipping
 * Plugin URI: https://skynet.co.za/ecomm/woocommerce-shipping-plugin
 * Description: Skynet ships everywhere across ZAR
 * Version: 1.1.5
 * Author: Skynet
 * Author URI: https://profiles.wordpress.org/skynetza/
 * Requires at least: 5.6.0
 * Tested up to: 6.6.1
 * Requires PHP: 8.0
 * WC requires at least: 6.0
 * WC tested up to: 6
 * Copyright: 2022+ Skynet Worldwide Express
 * License: MIT
 * License URI: https://skynet.co.za/licenses/MIT
 */

if (!defined('ABSPATH')) {
    exit;
}



add_action( 'admin_notices', 'skysuccess_admin_notice_notice' );

function skysuccess_admin_notice_notice(){

    /* Check transient, if available display notice */
    if( get_transient( 'skysuccess-admin-notice' ) ){
        ?>
        <div class="updated notice is-dismissible">
            <p>Thank you for using the SkyNet plugin!</p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'skysuccess-admin-notice' );
    }
}

function create_custom_api_key_table()
{
    global $wpdb;

    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

    $custom_table_name  = $wpdb->prefix . "skynet_settings";
    $custom_table_name2  = $wpdb->prefix . "skynet_waybills";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $custom_table_name (
        username varchar(255) NOT NULL,
        user_Password varchar(255) NOT NULL,
        account_number varchar(25) NOT NULL,
        in_house_code varchar(10) NOT NULL,
        shipper_name varchar(255) NOT NULL,
        shipper_address1 varchar(255) NOT NULL,
        shipper_address2 varchar(255) NOT NULL,
        shipper_suburb varchar(255) NOT NULL,
        shipper_postcode varchar(25) NOT NULL,
        shipper_phone varchar(25) NOT NULL,
        shipper_contact varchar(255) NOT NULL,
        shipper_service varchar(10) NOT NULL,
        default_weight varchar(10) NOT NULL,
        fallback_cost varchar(18) NOT NULL,
        freeship_threshold varchar(18) NOT NULL DEFAULT 0,
        markupon_skynet varchar(10) NOT NULL DEFAULT 0,
        use_flatrate varchar(10) NOT NULL DEFAULT 'no',
        flat_rate varchar(18) NOT NULL DEFAULT 0,
        isuat varchar(10) NOT NULL DEFAULT 'no',
        status_trigger varchar(200) NOT NULL DEFAULT 'wc-processed'
    ) $charset_collate;";
    dbDelta($sql);

    $sql2 = "CREATE TABLE $custom_table_name2 (
        id serial,
        order_id varchar(255) NOT NULL,
        waybill_number varchar(255) NOT NULL
    ) $charset_collate;";
    dbDelta($sql2);

    set_transient( 'skysuccess-admin-notice', true, 5 );
}

/**
 * register_activation_hook
 * @method | create_custom_api_key_table() - Create a custom table for storing keys
 * @method | load_retail_store_service_keys() - Insert statement ran here
 */
function register_custom_methods()
{
    create_custom_api_key_table();
}

register_activation_hook(__FILE__, 'register_custom_methods');

$filters = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $filters)) {

    /**
     * Custom API table | This method is created in the intent to load the user's service key
     * to communicate with the Skynet Server
     * ----------------------------------------------------------------------------------------
     */


    class SkynetShipping
    {
        public function __construct()
        {
            define('SKYNET_DELIVERY_VERSION', '1.1.5');
            // Enable WP_DEBUG mode
            //define('WPS_DEBUG_DOM', true);
            // Enable Debug logging to the /wp-content/debug.log file
            //define( 'WP_DEBUG_LOG', true );

            define('SKYNET_DELIVERY_VERSION_DEBUG', defined('WP_DEBUG') &&
                'true' == WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || 'true' == WP_DEBUG_DISPLAY));
            add_action('plugins_loaded', array($this, 'init'));
        }

        public function add_to_shipping_methods($shipping_methods)
        {
            $shipping_methods['skynet_shipping'] = 'SkynetShippingMethod';
            return $shipping_methods;
        }

        public function init()
        {
            add_filter('woocommerce_shipping_methods', array($this, 'add_to_shipping_methods'));
            add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
            add_action('init', array($this, 'load_plugin_textdomain'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 100);
        }

        public function enqueue_scripts()
        {
            //wp_enqueue_style('woocommerce-skynet-shipping-style', plugins_url('/assets/css/style.css', __FILE__));
            //wp_enqueue_script('woocommerce-skynet-shipping-script', plugins_url('/assets/js/main.js', __FILE__));
        }

        public function load_plugin_textdomain()
        {
            load_plugin_textdomain('woocommerce-skynet-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function shipping_init()
        {
            include_once('includes/skynet-shipping-method.php');
            include_once('includes/functions.php');
        }
    }

    new SkynetShipping();
}
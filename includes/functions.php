<?php

/**
 * WooCommerce Skynet Plugin functions and definitions file
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Skynet
 */

require_once('vendor/autoload.php');

\Dotenv\Dotenv::createImmutable(__DIR__, '.env')->load();

# WC_Checkout shipping phone
add_filter('woocommerce_checkout_fields', 'skynet_add_shipping_phone_checkout');
# Hide shipping on cart
add_filter('woocommerce_cart_ready_to_calc_shipping', 'skynet_disable_shipping_calc_on_cart', 99, 1);
add_filter('handle_bulk_actions-edit-shop_order', 'skynet_create_shipment_order_bulk', 10, 3);
#add_filter('woocommerce_order_button_html', 'skynet_disable_place_order_button_html' );

//add_action('woocommerce_review_order_before_payment', 'skynet_review_order_before_payment', 10);
add_action('woocommerce_order_status_changed','skynet_create_shipment',10,1);
//add_action('admin_action_mark_processing', 'skynet_create_shipment_order_bulk');
add_action( 'woocommerce_checkout_order_processed', 'skynet_create_on_order_processed', 10, 3 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'skynet_create_on_api_order_processed', 10, 1 );
# WC_Order_Note_Action
# ----------------------------------------------------------------------------------------------------------


function skynet_create_on_order_processed($order_id, $posted_data, $order)
{
    global $wpdb;
	//if ( !$order_id ) {
    //    return;
    //}
    $skynetUserKeys = $wpdb->prefix . "skynet_settings";
    $isResultsStored = $wpdb->get_results("SELECT * FROM $skynetUserKeys  LIMIT 1");  
	$selectedStatus = "";
	if(!empty($isResultsStored[0]->status_trigger)) 
    {
		if(str_contains($isResultsStored[0]->status_trigger,'wc-'))
        {
		    $selectedStatus = substr($isResultsStored[0]->status_trigger,3);
        }
        else 
        {
            $selectedStatus = $isResultsStored[0]->status_trigger;
        }
	}

    if($selectedStatus === $order->status) 
    {
		$plugin = new SkynetShippingMethod();
        $plugin->skynet_create_shipment_order( $order_id, $order );
    }

}

function skynet_create_on_api_order_processed($order)
{
    global $wpdb;
	//if ( !$order ) {
    //    return;
    //}

    $skynetUserKeys = $wpdb->prefix . "skynet_settings";
    $isResultsStored = $wpdb->get_results("SELECT * FROM $skynetUserKeys  LIMIT 1");  
	$selectedStatus = "";
	if(!empty($isResultsStored[0]->status_trigger)) 
    {
		if(str_contains($isResultsStored[0]->status_trigger,'wc-'))
        {
		    $selectedStatus = substr($isResultsStored[0]->status_trigger,3);
        }
        else 
        {
            $selectedStatus = $isResultsStored[0]->status_trigger;
        }
	}

    if($selectedStatus === $order->status) 
    {
		$plugin = new SkynetShippingMethod();
        $plugin->skynet_create_shipment_order( $order_id, $order );
    }

}

function skynet_create_shipment($order_id)
{
	global $wpdb;
	//if ( !$order_id ) {
    //    return;
    //}
    $order = wc_get_order($order_id);
    $skynetUserKeys = $wpdb->prefix . "skynet_settings";
    $isResultsStored = $wpdb->get_results("SELECT * FROM $skynetUserKeys  LIMIT 1");  
	$selectedStatus = "";
	if(!empty($isResultsStored[0]->status_trigger)) 
    {
		if(str_contains($isResultsStored[0]->status_trigger,'wc-'))
        {
		    $selectedStatus = substr($isResultsStored[0]->status_trigger,3);
        }
        else 
        {
            $selectedStatus = $isResultsStored[0]->status_trigger;
        }
	}

    if($selectedStatus === $order->status) 
    {
		$plugin = new SkynetShippingMethod();
        $plugin->skynet_create_shipment_order( $order_id, $order );
    }
    
}

function skynet_create_shipment_order_bulk($redirect_to, $action, $order_ids) {

	global $wpdb;
    if ( !$order_ids ) {
        return;
    }
    $order = wc_get_order($order_id);
    $skynetUserKeys = $wpdb->prefix . "skynet_settings";
    $isResultsStored = $wpdb->get_results("SELECT * FROM $skynetUserKeys  LIMIT 1");  
    $selectedStatus = "";
	if(!empty($isResultsStored[0]->status_trigger)) 
    {
		if(str_contains($isResultsStored[0]->status_trigger,'wc-'))
        {
		    $selectedStatus = substr($isResultsStored[0]->status_trigger,3);
        }
        else 
        {
            $selectedStatus = $isResultsStored[0]->status_trigger;
        }
	}
    if($selectedStatus === $order->status) 
    {
        $plugin = new SkynetShippingMethod();
        foreach( $order_ids as $order_id ) {

            $order = wc_get_order($order_id);          
            $plugin->skynet_create_shipment_order( $order_id, $order );
        }
    }
    return $redirect_to;
}
    




# ----------------------------------------------------------------------------------------------------------

//function skynet_disable_place_order_button_html( $button ) {
    // HERE define your targeted shipping method id
//    $shipping_total = $order->get_shipping_total();

    // Get the chosen shipping method (if it exist)
    //$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    
    // If the targeted shipping method is selected, we disable the button
//    if( $shipping_total == 0  ) {
    //    $style  = 'style="background:Silver !important; color:white !important; cursor: not-allowed !important; text-align:center;"';
    //    $text   = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
     //   $button = '<a class="button" '.$style.'>' . $text . '</a>';
//     wc_add_notice( __( 'We cannot ship this product to your country. Please remove it from the cart to continue!', 'woocommerce' ), 'error' );
 //   }
//    return $button;
//    }

/**
 * @snippet -   Add a heading to the Checkout page on Payments
 */
function skynet_review_order_before_payment()
{
    echo '<h3>' . esc_html__('Payment methods') . '</h3>';
}

function skynet_wc_add_order_meta_box_action($actions)
{
    global $theorder;

    // bail if the order has been paid for or this action has been run
    if (!$theorder->is_paid() || get_post_meta($theorder->id, '_wc_order_marked_printed_for_packaging', true)) {
        return $actions;
    }

    // add "mark printed" custom action
    $actions['wc_custom_order_action'] = __('Dispatch To Skynet', 'my-textdomain');
    return $actions;
}

function skynet_wc_process_order_meta_box_action($order)
{
    $message = sprintf(__('Order information updated by %s for Skynet.', 'my-textdomain'), wp_get_current_user()->display_name);
    $order->add_order_note($message);

    update_post_meta($order->id, '_wc_order_marked_printed_for_packaging', 'yes');
}
/**
 * @snippet -   Hide shipping on cart
 */
function skynet_disable_shipping_calc_on_cart($show_shipping)
{
    if (is_checkout()) {
        return true;
    }
    if (is_cart()) {
        return true;
    }

    return $show_shipping;
}
/**
 * @snippet - Shipping Phone to the Checkout page
 */
function skynet_add_shipping_phone_checkout($fields)
{
    $fields['shipping']['shipping_phone'] = [
        'label' => 'Phone',
        'required' => false,
        'class' => ['form-row-wide'],
        'priority' => 90,
    ];
    return $fields;
}

if (!function_exists('write_log')) {

    function write_log($log, $isError = false) {
        $logger = wc_get_logger();
        if($isError)
        {
            $logger->error( $log, array( 'source' => 'sky-plugin' ) );
        } 
        else 
        {
            $logger->info( $log, array( 'source' => 'sky-plugin' ) );
        }
		
    }

}



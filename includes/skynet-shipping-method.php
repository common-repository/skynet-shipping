<?php
# The maximum execution time, in seconds. If set to zero, no time limit is imposed.
set_time_limit(0);

# Make sure to keep alive the script when a client disconnect.
ignore_user_abort(true);

if (!defined('ABSPATH')) {
    exit;
}

require_once('vendor/autoload.php');

class SkynetShippingMethod extends WC_Shipping_Method
{
    public $total_cost;
    public $fallback_cost;
    public $freeship_threshold;
    public $markupon_skynet;
    public $use_flatrate;
    public $flat_rate;
    public $isuat;
    public $status_trigger;
    public $_bookingConfirmedOrderID;
    public $_api_key_endpoint;
    public $_service_id_endpoint;
    public $curl_response;
    public $store_Api_key_endpoint;
    public $store_Service_Key_endpoint;
    public $calculate_distance_for_quotes;
    public $username;
    public $user_password;
    public $account_number;
    public $system_id;
    public $in_house_code;
    public $securityToken;
    public $skynet_parcels;
    public $pickupSuburb;
    public $droffSuburb;
    private $woocommerce_statuses;
    protected static $instance;


    public function __construct($instance_id = 0)
    {
        $this->id                 = 'skynet_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Skynet Shipping');
        $this->method_description = __('Skynet Shipping');
        $this->supports           = array(
            'zones',
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->enabled              = 'yes';
        //$this->last_response        = array();

        if ($instance_id == 0) return;

        \Dotenv\Dotenv::createImmutable(__DIR__, '.env')->load();

        $this->init();

        $this->load_retail_store_service_keys($this->get_option('username'), $this->get_option('user_password'), $this->get_option('account_number'), $this->get_option('in_house_code'),$this->get_option('shipper_name') ,$this->get_option('shipper_address1'),$this->get_option('shipper_address2'),$this->get_option('shipper_suburb')  
		,$this->get_option('shipper_postcode'),$this->get_option('shipper_phone'),$this->get_option('shipper_contact') ,$this->get_option('shipper_service'),$this->get_option('default_weight'),$this->get_option('fallback_cost')
        ,$this->get_option('freeship_threshold'),$this->get_option('markupon_skynet'),$this->get_option('use_flatrate'),$this->get_option('flat_rate'),$this->get_option('isuat'),$this->get_option('status_trigger'));
    }

    /**
	 * Get a reference to the Plugin instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    function provide_instance() 
    {
        return $this;
    }

    public function InsertOrderWaybill($orderId, $waybillNumber)
    {
        global $wpdb;
        try {
            write_log('Waybill created:  '.$waybillNumber. ' Order: ' . $orderId);
            if (empty($orderId) || empty($waybillNumber)) { return false; }
                
            $wpdb->insert(
                $wpdb->prefix . 'skynet_waybills',
                    [
                        'order_id'              => $orderId,
                        'waybill_number'         => $waybillNumber
                    ]
                ); 
        }
        catch (Exception $e) {
            write_log('ERROR: Insert Waybill.' .$waybillNumber. ' | ' . $orderId . ' | '.$e);
        }
        return true;   
    }

    public function WaybillExists($orderId)
    {
        global $wpdb;
        if (empty($orderId)) return false;
        $rowcount = $wpdb->get_var(
                'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'skynet_waybills WHERE order_id=' . $orderId
            ); 
        return $rowcount > 0;
    }

    public function GetSettings()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'skynet_settings';
        return $wpdb->get_results("SELECT * FROM $tableName LIMIT 1");    
        // return $wpdb->get_results(
        //    $wpdb->prepare(
        //        'SELECT * FROM %s LIMIT 1',$tableName
        //    ),
        //    ARRAY_A
        //);
    }

    /**
     * @description | Stores in the user api and service keys
     * @param null $api_key - API Key
     * @param null $service_key - Service Key
     */
    public function load_retail_store_service_keys($user_name = null, $pass_code = null, $account = null, $inHouseCode = null
	,$shipperName = null,$shipperAddress1 = null,$shipperAddress2 = null,$shipperSuburb = null,$shipperPostcode = null,$shipperPhone = null
    ,$shipperContact = null,$shipperService = null, $defaultweight = 2, $fallbackcost = 150,$freeship_threshold = 0,$markupon_skynet = 0
    ,$use_flatrate = 0,$flat_rate = 0,$isuat = 'no', $status_trigger = 'wc-processed')
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'skynet_settings';

        if (empty($user_name) || empty($pass_code)) return false;

        $wpdb->query('DELETE FROM '. $tableName);

        $isResultsStored = $this->GetSettings(); 
        

        if (!$isResultsStored) {
        $wpdb->insert(
                $wpdb->prefix . 'skynet_settings',
                [
                    'username'              => $user_name,
                    'user_Password'         => $pass_code,
                    'account_number'        => $account,
                    'in_house_code'         => $inHouseCode,
					'shipper_name'         => $shipperName,
					'shipper_address1'         => $shipperAddress1,
					'shipper_address2'         => $shipperAddress2,
					'shipper_suburb'         => strtoupper($shipperSuburb),
					'shipper_postcode'         => strtoupper($shipperPostcode),
					'shipper_phone'         => $shipperPhone,
					'shipper_contact'         => $shipperContact,
					'shipper_service'         => strtoupper($shipperService),
                    'default_weight'         => strtoupper($defaultweight),
                    'fallback_cost'         => $fallbackcost,
                    'freeship_threshold'    => $freeship_threshold,
                    'markupon_skynet'       => $markupon_skynet,
                    'use_flatrate'          => $use_flatrate,
                    'flat_rate'             =>  $flat_rate,
                    'isuat'             =>  $isuat,
                    'status_trigger'             =>  $status_trigger
                ]
            );
            
         } else {
			/* $wpdb->update(
                $wpdb->prefix . 'skynet_settings',
                [
                    'username'              => $user_name,
                    'user_Password'         => $pass_code,
                    'account_number'        => $account,
                    'in_house_code'         => $inHouseCode,
					'shipper_name'         => $shipperName,
					'shipper_address1'         => $shipperAddress1,
					'shipper_address2'         => $shipperAddress2,
					'shipper_suburb'         => strtoupper($shipperSuburb),
					'shipper_postcode'         => strtoupper($shipperPostcode),
					'shipper_phone'         => $shipperPhone,
					'shipper_contact'         => $shipperContact,
					'shipper_service'         => strtoupper($shipperService),
                    'default_weight'         => strtoupper($defaultweight)
                ],
                ['account_number'=>$account]
            ); */

            $wpdb->query($wpdb->prepare('UPDATE '. $tableName . '
                    username              = %s,
                    user_Password         = %s,
                    account_number        = %s,
                    in_house_code        = %s,
					shipper_name         = %s,
					shipper_address1         = %s,
					shipper_address2        = %s,
					shipper_suburb        = %s,
					shipper_postcode         = %s,
					shipper_phone         = %s,
					shipper_contact         = %s,
					shipper_service         = %s,
                    default_weight         = %s,
                    fallback_cost           = %s,
                    freeship_threshold      = %s,
                    markupon_skynet         = %s,
                    use_flatrate            = %s,
                    flat_rate               = %s,
                    isuat               = %s,
                    status_trigger      = %s,'
                ,$user_name,$pass_code,$account,$inHouseCode,$shipperName,$shipperAddress1,$shipperAddress2,strtoupper($shipperSuburb),
                strtoupper($shipperPostcode),$shipperPhone,$shipperContact,strtoupper($shipperService),strtoupper($defaultweight),$fallbackcost,$freeship_threshold,
                $markupon_skynet,$use_flatrate,$flat_rate,$isuat,$status_trigger
            ));
		} 
        $isResultsStored = $this->GetSettings(); 
  
        return $isResultsStored;
    }

    public function init()
    {
       
        $this->init_form_fields();
        $this->init_settings();

        $this->title              = $this->get_option('title', __('Label', 'skynet_shipping'));
        $this->method_title       = $this->get_option('Skynet Shipping');
        //$this->description        = $this->get_option('description');
        //$this->method_description = $this->description;

        $this->username                     = $this->get_option('username');
        $this->user_password                = $this->get_option('user_password');
        $this->account_number               = $this->get_option('account_number');
        $this->system_id                    = $_ENV['SYSTEM_ID'];
        $this->skynet_parcels = [];
        add_filter('_no_shipping_available_html', array($this, 'skynet_filter_cart_no_shipping_available_html'), 10, 1);
        add_action('woocommerce_proceed_to_checkout', array($this, 'skynet_maybe_clear_wc_shipping_rates_cache'));
        add_filter('woocommerce_cart_ready_to_calc_shipping', [$this, 'skynet_disable_shipping_calc_on_cart'], 99);
        add_filter('get_skyplugin_class', array ($this, 'provide_instance'));
    }

    public function skynet_thank_you_title( $thank_you_title, $order )
   {
	  return 'Hi ' . $order->get_billing_first_name() . ', thank you so much for your order!';
   }

   public function skynet_create_shipment_order( $order_id,$order ) 
   {
	global $wpdb;
    $orderId = $order->get_id();

    if($this->WaybillExists($orderId)) return;
        //WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
     
            if( $order->get_shipping_method() == 'Skynet shipping' ) {
        
                $isResultsStored = $this->GetSettings(); 

                    if(empty($isResultsStored[0]->username) || empty($isResultsStored[0]->user_Password) || empty($isResultsStored[0]->account_number))
                    { 
                        write_log('ERROR:   User credentials not available',true);
                        $note = __("Your shipping cost could not be calculated.");
                        $order->add_order_note( $note );
                    }
                
                    $securityToken = $this->GetSkynetToken();
                    if(empty($securityToken))
                    {
                        write_log('ERROR:   Get token failed');
                        $note = __("SkyNet get token failed.");
                        $order->add_order_note( $note );
                        return;
                    }
                    $order_data = [
                        'customer_id'       => $order->get_customer_id(),
                        'order_id'          => $order->get_id(),
                        'order_number'      => $order->get_order_number(),
                        'order_total'       => wc_format_decimal($order->get_total(), 2),
                        'billing_first_name' => $order->get_billing_first_name(),
                        'billing_last_name' => $order->get_billing_last_name(),
                        'billing_company'   => $order->get_billing_company(),
                        'billing_email'     => $order->get_billing_email(),
                        'billing_phone'     => $order->get_billing_phone(),
                        'billing_address_1' => $order->get_billing_address_1(),
                        'billing_address_2' => $order->get_billing_address_2(),
                        'billing_postcode'  => strtoupper($order->get_billing_postcode()),
                        'billing_city'      => strtoupper($order->get_billing_city()),
                        'billing_state'     => strtoupper($order->get_billing_state()),
                        'billing_country'   => strtoupper($order->get_billing_country()),
                        'shipping_first_name' => $order->get_shipping_first_name(),
                        'shipping_last_name' => $order->get_shipping_last_name(),
                        'shipping_company'   => $order->get_shipping_company(),
                        'shipping_address_1' => $order->get_shipping_address_1(),
                        'shipping_address_2' => $order->get_shipping_address_2(),
                        'shipping_postcode'  => strtoupper($order->get_shipping_postcode()),
                        'shipping_city'     => strtoupper($order->get_shipping_city()),
                        'shipping_state'    => strtoupper($order->get_shipping_state()),
                        'shipping_country'  => strtoupper($order->get_shipping_country()),
                        'customer_note'     => $order->get_customer_note()
                    ];

                    $_OrderNo		= str_pad($order->get_order_number(),6,"0",STR_PAD_LEFT);
                
                    $_postcode      = $order_data['shipping_postcode'] ? $order_data['shipping_postcode'] : $order_data['billing_postcode'];
                    $_city          = $order_data['shipping_city'] ? $order_data['shipping_city'] : $order_data['billing_city'];
                    $_address_1     = $order_data['shipping_address_1'] ? $order_data['shipping_address_1'] : $order_data['billing_address_1'];
                    $_company       = $order_data['shipping_company'] ? $order_data['shipping_company'] : $order_data['billing_company'];
                    $_address_2_unit    = $order_data['shipping_address_2'] ? $order_data['shipping_address_2'] : $order_data['billing_address_2'];
                    $_first_name        = $order_data['shipping_first_name'] ? $order_data['shipping_first_name'] : $order_data['billing_first_name'];
                    $_last_name         = $order_data['shipping_last_name'] ? $order_data['shipping_last_name'] : $order_data['billing_last_name'];
                    $_phone             = $order_data['billing_phone'] ? $order_data['billing_phone'] : $order_data['shipping_phone'];
                    $_fullName			= $_first_name." ".$_last_name;
                    $customer_note      = $order_data['customer_note'];
                    $_company_name      = get_bloginfo('name');
                    $_shipper_name 		= $isResultsStored[0]->shipper_name ? $isResultsStored[0]->shipper_name : $_company_name;
                    $_shipper_address1 = $isResultsStored[0]->shipper_address1 ? $isResultsStored[0]->shipper_address1 : WC()->countries->get_base_address();
                    $_shipper_address2 = $isResultsStored[0]->shipper_address2 ? $isResultsStored[0]->shipper_address2 : WC()->countries->get_base_address_2();
                    $_shipper_suburb = $isResultsStored[0]->shipper_suburb ? $isResultsStored[0]->shipper_suburb : WC()->countries->get_base_city();
                    $_shipper_city  = $isResultsStored[0]->shipper_suburb ? $isResultsStored[0]->shipper_suburb : WC()->countries->get_base_city();
                    $_shipper_postCode = $isResultsStored[0]->shipper_postcode ? $isResultsStored[0]->shipper_postcode : WC()->countries->get_base_postcode();
                    $_shipper_phone  = $isResultsStored[0]->shipper_phone ? $isResultsStored[0]->shipper_phone : "";	
                    $_shipper_contact  = $isResultsStored[0]->shipper_contact ? $isResultsStored[0]->shipper_contact : "";
                    $_shipper_service  = $isResultsStored[0]->shipper_service ? $isResultsStored[0]->shipper_service : "DBC";
                    $_default_weight  = $isResultsStored[0]->default_weight ? $isResultsStored[0]->default_weight : "2";

                    # Get and Loop Over Order Items
                    $counter = 0;
                    $isHazardous = false;
                    $waybillOptions = array();
                    foreach ($order->get_items() as $item_id => $item) {
                        $product = json_decode($item->get_product(), true);
                        $counter = $counter + 1;
                        // $product_description = $product['description'];
                        $product_length = (int) $product['length'];
                        $product_width  = (int) $product['width'];
                        $product_height = (int) $product['height'];
                        $product_weight = (int) $product['weight'];
                        if ($product_weight  == 0) { (int) $product_weight=$isResultsStored[0]->default_weight; }
                        if ($product_height == 0) { $product_height=10; }
                        if ($product_width == 0) { $product_width=30; }
                        if ($product_length == 0) { $product_length=40; }

                        $_bookingDimensions[] = $this->booking_plugin_attributes($product_length, $product_width, $product_height, $product_weight, $isResultsStored[0]->in_house_code . $_OrderNo, $counter);

                        // get an array of the WP_Term objects for a defined product ID
						$terms = get_the_terms( $product['id'], 'product_tag' );

                        // Loop through each product tag for the current product
                        if(is_array($terms) &&  count($terms) > 0 ){
                            foreach($terms as $term){
                                $term_name = strtoupper($term->name); // Product tag Name
								
                                if(str_starts_with($term_name, 'HAZ') || str_starts_with($term_name, 'FLAMMABLE'))
                                    {
										$note = __("Contains Tag: " .$term_name);
										$order->add_order_note( $note );
                                        $isHazardous=true;
                                        $_shipper_service ="DBC";
                                        $waybillOptions[] = "HAZ";
                                    }
                            }
                        }
                        break;
                    }
                
                    $rand = mt_rand();
                    $date = date_create($order->order_date);
                    $collectionDate = date_format($date,"Y-m-d");
                    $delDate =date("Y-m-d", strtotime("+ 5 day"));
                    
                    $_parcelCount = count($_bookingDimensions);

                    $create_plugin_booking_waybill = [
                        "InHouseWaybillId" => 0,
                        "SecurityToken" => $securityToken,
                        "PerformAddressValidation" => true,
                        "PerformParcelDimsValidation" => true,
                        "PerformServiceTypeValidation" => true,
                        "FailRequestOnValidation" => false,
                        "ExportDataOnManifest" => false,
                        "AccountNumber" => $isResultsStored[0]->account_number,
                        "CompanyName" =>  $_company_name,
                        "CustomerReference" => $isResultsStored[0]->in_house_code . $_OrderNo,
                        "SecureDelivery" => false,
                        "RICA" => false,
                        "UseOTP" => false,
                        "ConditionalDelivery" => false,
                        "ConsigneeIDNumber" => "",
                        "InHouseCode" => $isResultsStored[0]->in_house_code,
                        "GenerateWaybillNumber" => true,
                        "ServiceType" => $_shipper_service,
                        "CollectionDate" => $collectionDate,
                        "DeliveryDate" => $delDate,
                        "Instructions" => "",
                        "FromAddressName" => $_shipper_contact,
                        "FromCompanyName" =>$_shipper_name,
                        "FromBuildingComplex" => "",
                        "FromAddress1" => $_shipper_address1,
                        "FromAddress2" => $_shipper_address2,
                        "FromAddress3" => "",
                        "FromAddress4" => "",
                        "FromSuburb" => strtoupper($_shipper_suburb),
                        "FromCity"  => strtoupper($_shipper_city),
                        "FromPostCode" => strtoupper($isResultsStored[0]->shipper_postcode),
                        "FromCounterCode" => "",
                        "FromAddressLatitude" => "",
                        "FromAddressLongitude" => "",
                        "FromTelephone" => $this->phonize($_shipper_phone,'ZA'),
                        "FromFax"  => "",
                        "FromEmail"  => "",// $order->get_billing_email(),
                        "FromOfficeTelephoneNumber" => "",
                        "FromAlternativeContactName" => "",
                        "FromAlternativeContactNumber" => "",
                        "ToAddressName" => $_fullName,
                        "ToCompanyName" => "",
                        "ToBuildingComplex" => "",
                        "ToAddress1" => $_address_1,
                        "ToAddress2" => "",
                        "ToAddress3" => "",
                        "ToAddress4" => "",
                        "ToSuburb" => strtoupper($_city), // $dropOffBurb->suburb,
                        "ToCity" => strtoupper($_city),
                        "ToPostCode" => strtoupper($_postcode),
                        "ToCounterCode" => "",
                        "ToAddressLatitude" => "",
                        "ToAddressLongitude" => "",
                        "ToTelephone" => $this->phonize($_phone ? $_phone : $this->get_shipping_phone_number($_phone),'ZA'),
                        "ToFax" => "",
                        "ToEmail" =>  $order->get_billing_email(),
                        "ToOfficeTelephoneNumber" => "",
                        "ToAlternativeContactName" => "",
                        "ToAlternativeContactNumber" => "",
                        "ReadyTime" => "",
                        "OpenTill" => "",
                        "InsuranceType" => "1",
                        "InsuranceAmount" => "0",
                        "InvoiceAmount" => "0",
                        "ChainStoreIndicator" => false,
                        "FridgeLine" => false,
                        "Security" => false,
                        "ExpectedParcelCount" => $_parcelCount,
                        "ParcelList" => $_bookingDimensions,
                        "OffSiteCollection" => false,
                        "UseClientParcelNumber" => false,
                        "eWaybill" => false,
                        "WaybillNumber" => $isResultsStored[0]->in_house_code . $_OrderNo,
                        "OriginalWaybillNumber" => "",
                        "WaybillOptions" => $waybillOptions,
                        "CreatedBy" => ""
                    ];

                
                    $response1 = $this->skynet_curl_endpoint($create_plugin_booking_waybill, 'WAYBILL', 'POST');

                    $response_status_code = wp_remote_retrieve_response_code($response1);

                    //print_r($response1);

                    if (!is_array($response1) && is_wp_error($response1)) 
                    {
                        write_log('ERROR:   Waybill not created. | '.$response1->get_error_message());
                        return $response1->get_error_message();
                    }

                    if (200 >= $response_status_code || 308 <= $response_status_code) {
                        //echo $response_status_code;
                        $data = json_decode($response1['body'],true);
                        if(!empty($isResultsStored[0]->isuat) && $isResultsStored[0]->isuat === "yes") 
                        {
                            $url = $_ENV['UAT_TRACK_BASE'];
                        }
                        else 
                        {
                            $url = $_ENV['PROD_TRACK_BASE'];
                        }

                        if(empty($data['errorCode']) && !empty($data["waybillNumber"]))
                        {
                            $this->InsertOrderWaybill($orderId, $data["waybillNumber"]);
                           
                            // The text for the note
                            $note = __("Waybill number: <a href='".$url.$data["waybillNumber"] . "' target='blank'>".$data["waybillNumber"]."</a>");
                            // Add the note
                            $order->add_order_note( $note );
                            //$text .= ' <div class=\"outside-delivery-checkout\"><strong>'. __("PLEASE NOTE", "woocommerce") . ':</strong><br />'.__("Track your delivery here <a href='https://web.skynet.co.za:5002/T?R=".$data["waybillNumber"]."'>".$data["waybillNumber"]."</a>", "woocommerce").'</div>';
                        } else {
                            // The text for the note
                            $note = __("Waybill error: " . $data["errorDescription"]);

                            // Add the note
                            $order->add_order_note( $note );

                            if(!empty($data["waybillNumber"]))
							{

                                $this->InsertOrderWaybill($orderId, $data["waybillNumber"]);
                               
                                $note = __("Waybill number: <a href='".$url.$data["waybillNumber"] . "' target='blank'>".$data["waybillNumber"]."</a>");

                            	// Add the note
                            	$order->add_order_note( $note );
							}
                        }
                    }
                }
            
    
    
            
        return;
    }

    /**
     * @Description         Gets an Array of the product's attributes
     * @param $_length      Item Length
     * @param $_breadth     Item Width
     * @param $_height      Item Height
     * @param $_mass        Item Weight
     */
    public function booking_plugin_attributes($_length, $_breadth, $_height, $_mass, $_orderNo, $_counter)
    {
        (array) $parcels = new SkynetParcel($_mass, $_breadth, $_height, $_length);
        $_dimensions = [
            "parcel_length"     => !empty($parcels->get_length()) ? $parcels->get_length() == 0 ? 40 : $parcels->get_length() : 40,
            "parcel_breadth"    => !empty($parcels->get_width()) ? $parcels->get_width() == 0 ? 30 : $parcels->get_width() : 30,
            "parcel_height"     => !empty($parcels->get_height() ) ? $parcels->get_height() == 0 ? 1 : $parcels->get_height() : 1,
            "parcel_mass"       => !empty($parcels->get_itemMass()) ? $parcels->get_itemMass() == 0 ? 2 : $parcels->get_itemMass() : 2,
			"parcel_number" => "$_counter",
            "parcel_description" => "parcel.$_counter",
            "parcel_volumetricMass" => 0,
            "parcel_reference" => $_orderNo.'_'.str_pad($_counter,3,'0',STR_PAD_LEFT),
            "parcel_value" => "0"
        ];

        if (is_array($_dimensions)) return $_dimensions;
    }
	
	function phonize($phoneNumber, $country) {

		$countryCodes = array(
			'ZA' => '+27'
		);

		return preg_replace('/[^0-9+]/', '',
           preg_replace('/^0/', $countryCodes[$country], $phoneNumber));
	}

    /**
     * @Description             - Gets the custom shipping phone number from the UserMeta table
     * @param $_current_user_id - Gets the current logged in User
     * @return $results         - Returns the User's shipping number
     */
    public function get_shipping_phone_number($_current_user_number)
    {
        global $wpdb;

        $_user_billing_number = (string) $_current_user_number ? (string) $_current_user_number : strval($_current_user_number);

        if ('' != $_user_billing_number) $_billing_number = $wpdb->get_results($wpdb->prepare('SELECT meta_value FROM wp_postmeta WHERE meta_value = %s', $_user_billing_number), ARRAY_A);

        return $_billing_number[0]['meta_value'];
    }
    /**
     * @description     Hide/Show the shipping calculation method on the Cart Page
     * @param           $show_shipping - Checks if the user is on the Cart Page
     * @return          $show_shipping - Returns false/true once on the Cart Page
     */
    public function skynet_disable_shipping_calc_on_cart($show_shipping)
    {
        if (is_checkout()) {
            return true;
        }
        if (is_cart()) {
            return true;
        }

        return $show_shipping;
    }

    public function skynet_maybe_clear_wc_shipping_rates_cache()
    {
        if ($this->get_option('clear_wc_shipping_cache') == 'yes') {
            $packages = WC()->cart->get_shipping_packages();
            foreach ($packages as $key => $value) {
                $shipping_session = "shipping_for_package_$key";
                unset(WC()->session->$shipping_session);
            }
        }
    }

    public function get_skynet_quote($package = array()){
        global $wpdb;
        $quoteValue = 0;
        $isResultsStored = $this->GetSettings();   
        if ($isResultsStored[0]->use_flatrate === 'no')
        { 
            //print_r($isResultsStored);
            if(empty($isResultsStored[0]->username) || empty($isResultsStored[0]->user_Password) || empty($isResultsStored[0]->account_number))
            { 
                $text .= ' <div class=\"outside-delivery-checkout\"><strong>'. __("PLEASE NOTE", "woocommerce") . ':</strong><br />'.__("Your shipping cost could not be calculated.", "woocommerce") . '</div>';
            }

            $pickUpPCode        = $isResultsStored[0]->shipper_postcode ? $isResultsStored[0]->shipper_postcode : WC()->countries->get_base_postcode();
            $pickUpBurb        = $isResultsStored[0]->shipper_suburb ? $isResultsStored[0]->shipper_suburb : WC()->countries->get_base_city();
            $dropOffPCode       = $package['destination']['postcode'];
            $dropOffBurb       = $package['destination']['city'];
	

            $this->securityToken = $this->GetSkynetToken();
            if(empty($this->securityToken))
            {
                write_log('ERROR:   Get token failed');
                // The text for the note
                $note = __("SkyNet get token failed.");
                // Add the note
                $order->add_order_note( $note );
                return;
            }
            $create_get_quote =  [
                'SecurityToken' => $this->securityToken,
                'AccountNumber' => $isResultsStored[0]->account_number,
                'FromCity' => $pickUpBurb,
                'FromCityPostalCode' => $pickUpPCode,
                'ToCity' => $dropOffBurb,
                'ToCityPostalCode' => $dropOffPCode,
                'ServiceType' => "DBC",
                'DestinationPCode' => $dropOffPCode,
                'ParcelList' => $this->skynet_parcels
            ];

            //print_r($create_get_quote);
    
            $this->curl_response = $this->skynet_curl_endpoint($create_get_quote,'QUOTE',  'POST');
            //print_r($this->curl_response);
            if (!is_array($this->curl_response) && is_wp_error($this->curl_response)) 
			{
				write_log('ERROR:   Quote not created. | '.$this->curl_response->get_error_message());
				wc_add_notice( __( 'Your shipping cost could not be calculated at this time. Please try refreshing the page again.', 'woocommerce' ), 'error' );
				return 'Your shipping cost could not be calculated at this time. Please try refreshing the page again.'; //$this->curl_response->get_error_message();
			}
            $response_status_code = 0;// wp_remote_retrieve_response_code($this->curl_response);
            if(isset($this->curl_response['response']))
            {
                $response_status_code = $this->curl_response['response']['code'];
            }
            
            if (200 >= $response_status_code || 308 <= $response_status_code) {

                foreach ((array) $this->curl_response['body'] as $key => $value) :
                    $responseVar = json_decode($value, true);
                endforeach;
                $quoteValue = $responseVar['charges'];
                $markup = floatval($isResultsStored[0]->markupon_skynet);
                if(is_numeric($isResultsStored[0]->markupon_skynet) && $markup !== 0)
                {
                    $quoteValue = $quoteValue + ($quoteValue * ($markup / 100));
                }
                if($quoteValue == 0 && is_numeric($isResultsStored[0]->fallback_cost))
                {
                    $quoteValue = $isResultsStored[0]->fallback_cost;
                }
                if($quoteValue == 0)
                {
                    write_log('ERROR:   Quote not created. | '. json_encode($create_get_quote)); //somthing went wrong to get here!!!
                }
                //get cart total and check against free shipping threshold
                $totalCart = WC()->cart->get_cart_contents_total(); // Float
                if(is_numeric($isResultsStored[0]->freeship_threshold) && $totalCart > floatval($isResultsStored[0]->freeship_threshold) && floatval($isResultsStored[0]->freeship_threshold) != 0)
                {
                    $quoteValue = 0;
                }
                $this->total_cost = $quoteValue;
                
            } 
        }
        else 
        {
            if(is_numeric($isResultsStored[0]->flat_rate))
                    {
                        $quoteValue = $isResultsStored[0]->flat_rate;
                    }
            if($quoteValue == 0)
                    {
                        write_log('ERROR:   Could not resolve flat rate. ' . $isResultsStored[0]->flat_rate); //somthing went wrong to get here!!!
                    }
            //get cart total and check against free shipping threshold
            $totalCart = WC()->cart->get_cart_contents_total(); // Float
            if(is_numeric($isResultsStored[0]->freeship_threshold) && $totalCart > floatval($isResultsStored[0]->freeship_threshold) && floatval($isResultsStored[0]->freeship_threshold) != 0)
            {
                $quoteValue = 0;
            }
            $this->total_cost = $quoteValue;
        }
    }

    /**
     * @description  - There are no shipping methods available. Please double check your address
     */
    public function skynet_filter_cart_no_shipping_available_html($previous)
    {
        return $previous . $this->last_response['cart_no_shipping_available_html'];
    }

    public function get_option($key, $empty_value = null)
    {
        // Instance options take priority over global options
        if (in_array($key, array_keys($this->instance_form_fields))) {
            return $this->get_instance_option($key, $empty_value);
        }

        // Return global option
        return parent::get_option($key, $empty_value);
    }

    public function get_instance_option($key, $empty_value = null)
    {
        if (empty($this->instance_settings)) {
            $this->init_instance_settings();
        }

        // Get option default if unset.
        if (!isset($this->instance_settings[$key])) {
            $form_fields = $this->instance_form_fields;

            if (is_callable(array($this, 'get_field_default'))) {
                $this->instance_settings[$key] = $this->get_field_default($form_fields[$key]);
            } else {
                $this->instance_settings[$key] = empty($form_fields[$key]['default']) ? '' : $form_fields[$key]['default'];
            }
        }

        if (!is_null($empty_value) && '' === $this->instance_settings[$key]) {
            $this->instance_settings[$key] = $empty_value;
        }

        return $this->instance_settings[$key];
    }

    public function get_instance_option_key()
    {
        return $this->instance_id ? $this->plugin_id . $this->id . '_' . $this->instance_id . '_settings' : '';
    }

    public function init_instance_settings()
    {
        // 2nd option is for BW compat
        $this->instance_settings = get_option($this->get_instance_option_key(), get_option($this->plugin_id . $this->id . '-' . $this->instance_id . '_settings', null));

        // If there are no settings defined, use defaults.
        if (!is_array($this->instance_settings)) {
            $form_fields             = $this->get_instance_form_fields();
            $this->instance_settings = array_merge(array_fill_keys(array_keys($form_fields), ''), wp_list_pluck($form_fields, 'default'));
        }
    }

    public function get_order_statuses()
    {
        foreach( wc_get_order_statuses() as $key => $status )
        {
            $woocommerce_statuses[$key] = $status;
        }
       return $woocommerce_statuses;
    }

    public function init_form_fields()
    {
        $wc_statuses = $this->get_order_statuses();
        $this->form_fields     = array(); // No global options for table rates
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Method Title', 'woocommerce-skynet-shipping'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-skynet-shipping'),
                'default'     => __('Skynet shipping', 'woocommerce-skynet-shipping')
            ),
            'description' => array(
                'title'       => __('Method Description', 'woocommerce-skynet-shipping'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-skynet-shipping'),
                'default'     => __('Skynet ships everywhere across ZAR', 'woocommerce-skynet-shipping')
            ),
            'isuat' => array(
                'title'       => __('Use UAT', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'required'    => true,
                'description' => __('Enable uat testing.', 'woocommerce-skynet-shipping'),
                'default'     => 'no'
            ),
            'use_flatrate' => array(
                'title'       => __('Use flat rate shipping', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'required'    => true,
                'description' => __('Enable flat rate shipping, will not retrieve a cost.', 'woocommerce-skynet-shipping'),
                'default'     => 'no'
            ),
            'flat_rate' => array(
                'title'       => __('Flat rate for shipping', 'woocommerce-skynet-shipping'),
                'desc_tip'    => true,
                'required'    => true,
                'type'        => 'text',     
                'default'     => '0',   
                'description' => __('Flat rate for shipping', 'woocommerce-skynet-shipping'),
            ),
            'fallback_cost' => array(
                'title'       => __('Fallback cost', 'woocommerce-skynet-shipping'),
                'desc_tip'    => true,
                'required' => true,
               // 'default'     => '0',
                'type'        => 'select',
                'options' => array(
                    '0' => '0',
                    '50' => '50',
                    '60' => '60',
                    '70' => '70',
                    '80' => '80',
                    '90' => '90',
                    '100' => '100',
                    '110' => '110',
                    '120' => '120',
                    '130' => '130',
                    '140' => '140',
                    '150' => '150',
                    '160' => '160',
                    '170' => '170',
                    '180' => '180',
                    '190' => '190',
                    '200' => '200',
                    ),
                'description' => __('Use this shipping cost when service unavailable', 'woocommerce-skynet-shipping'),
            ),
            'freeship_threshold' => array(
                'title'       => __('Free shipping threshold', 'woocommerce-skynet-shipping'),
                'desc_tip'    => true,
                'required' => true,
                'default'     => '0',
                'type'        => 'text',
                
                'description' => __('Free shipping if cart total exceeds this', 'woocommerce-skynet-shipping'),
            ),
            'markupon_skynet' => array(
                'title'       => __('Markup on SkyNet quote (%)', 'woocommerce-skynet-shipping'),
                'desc_tip'    => true,
                'required'    => true,
                'default'     => '0',
                'type'        => 'text',
                'description' => __('Markup on SkyNet quote (%)', 'woocommerce-skynet-shipping'),
            ),
            'username' => array(
                'title'       => __('Username', 'woocommerce-skynet-shipping'),
                'type'        => 'text',
                'required' => true,
                'desc_tip'    => true,
                'default'     => '0',
                'description' => __('API user name', 'woocommerce-skynet-shipping'),
            ),
            'user_password' => array(
                'title'       => __('Password', 'woocommerce-skynet-shipping'),
                'type'        => 'password',
                'required' => true,
                'desc_tip'    => true,
                'description' => __('API password', 'woocommerce-skynet-shipping'),
            ),
            'account_number' => array(
                'title'       => __('Account Number', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
            'in_house_code' => array(
                'title'       => __('In house code', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_name' => array(
                'title'       => __('Shipper Name', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_address1' => array(
                'title'       => __('Address 1', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_address2' => array(
                'title'       => __('Address 2', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'text',
            ),
			'shipper_suburb' => array(
                'title'       => __('Suburb', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_postcode' => array(
                'title'       => __('Postal Code', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_phone' => array(
                'title'       => __('Phone Number (+27XXXXXXXXX)', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
                'type'        => 'text',
            ),
			'shipper_contact' => array(
                'title'       => __('Contact Name', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'text',
            ),
			'shipper_service' => array(
                'title'       => __('Service Type', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
              //  'default'     => 'DBC',
                'type'        => 'select',
                'options' => array(
                    'DBC' => 'DBC',
                    'ON1' => 'ON1',
                    ),
            ),
            'default_weight' => array(
                'title'       => __('Default Weight', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
               // 'default'     => '2',
                'type'        => 'select',
                'options' => array(
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                    ),
            ),
            'status_trigger' => array(
                'title'       => __('Status to trigger', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'required' => true,
               // 'default'     => '2',
                'type'        => 'select',
                'options' => $wc_statuses,
            ),
            'debug' => array(
                'title'       => __('Debug', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'checkbox',
                'default'     => 'yes',
                'description' => __('Set a "debug": "yes" flag in the JSON sent to the service.', 'woocommerce-skynet-shipping'),
            ),
            'clear_wc_shipping_cache' => array(
                'title'       => __('Disable Shipping Cache', 'woocommerce-skynet-shipping'),
                'label'       => ' ',
                'type'        => 'checkbox',
                'default'     => 'no',
                'description' => __("Clear WooCommerce's session-based shipping calculation cache at every load.", 'woocommerce-skynet-shipping'),
            ),

        );
    }


    /**
     * @description     - Method override [public function calculate_shipping( $package = array() ) {}]
     * @param $package  - Array package for the products variants
     * @return $rate    - Returns the quotation amount
     */
    public function calculate_shipping($package = array())
    {
        global $wpdb;
        $isResultsStored = $this->GetSettings(); 
        // prepare a JSON object to be sent to the costing endpoint
        foreach ($package['contents'] as $item_id => $values) {
            $_product = $values['data'];
            $class_slug = $_product->get_shipping_class();
            $package['contents'][$item_id]['shipping_class_slug'] = $class_slug;

            // collect category slugs
            $catids = $_product->get_category_ids();
            $catslugs = array();
            foreach ($catids as $catid) {
                $cat = get_category($catid);
                // array_push($catslugs, $cat->slug);
            }
            // collect product attributes
            $attrs = array();

            foreach ($_product->get_attributes() as $att) {
                if (is_object($att)) { // of class WC_Product_Attribute
                    $terms = $att->get_terms();
                    if ($terms) {
                        // This is a woocommerce predefined product attribute (Menu: WooCommerce -> Attributes)
                        $termvalues = array();
                        foreach ($terms as $term) {
                            array_push($termvalues, $term->name);
                        }
                        $attrs[$att->get_name()] = $termvalues;
                    } else {
                        // This is a woocommerce custom product attribute
                        $attrs[$att->get_name()] = $att->get_options();
                    }
                } else {
                    // for variations, attributes are strings
                    array_push($attrs, $att);
                }
            }

            $package['contents'][$item_id]['categories'] = $catslugs;
            $package['contents'][$item_id]['attributes'] = $attrs;
            // $package['contents'][$item_id]['name'] = $_product->name;
            // $package['contents'][$item_id]['sku'] = $_product->sku;
            $package['contents'][$item_id]['dimensions'] = $_product->get_dimensions(false);
            $package['contents'][$item_id]['purchase_note'] = $_product->get_purchase_note();
            $package['contents'][$item_id]['weight'] = $_product->get_weight();
            $package['contents'][$item_id]['downloadable'] = $_product->get_downloadable();
            $package['contents'][$item_id]['virtual'] = $_product->get_virtual();
        }

        
        $package['site']['locale'] = get_locale();
        $package['shipping_method']['instance_id'] = $this->instance_id;
        $package['debug'] = $this->get_option('debug');
        # --------------------------------------------------------------------------------------------
        if (!is_array($package['destination'])) return trigger_error('The destination set from the Array Package is not available.', E_USER_ERROR);

        $pickUpPCode        = WC()->countries->get_base_postcode();
        $dropOffPCode       = $package['destination']['postcode'];

        //if ('yes' !== $package['debug']) return false;

        $total_mass = 0;
        $cart_parcels = [];

        foreach ($package['contents'] as $keys => $values) {
            # mass
            $weight                 = (float) $values['weight'];
            $length                 = (float) $values['dimensions']['length'];
            $breath                 = (float) $values['dimensions']['width'];
            $height                 = (float) $values['dimensions']['height'];

            if (empty($weight) || $weight == 0) { (int) $weight = $isResultsStored[0]->default_weight; }
			if (empty($weight) || $weight == 0) { (int) $weight = 2; }
            if (empty($height) || $height == 0) { (int) $height = 1; }
            if (empty($breath ) || $breath  == 0) { (int) $breath  = 30; }
            if (empty($length) || $length == 0) { (int) $length = 40; }

            if ($values['quantity'] >= 1) {
                $get_wooCommerce_rates = new SkynetQuotes($length, $breath, $height, $weight);

                $totalWeightIncremeneted = $get_wooCommerce_rates->get_parcel_mass() * $values['quantity'];

                $total_mass += $totalWeightIncremeneted;
                array_push($cart_parcels,$get_wooCommerce_rates);
                $this->calculate_distance_for_quotes = [
                    "mass"                       => $total_mass,
                    "dimensions"                 => [$get_wooCommerce_rates]
                ];

                //$skynet_parcel = [
                //    'parcel_number' => "1",
                //    'parcel_length' =>  $length,
                //    'parcel_breadth' => $breath,
                //    'parcel_height' => $height,
                //    'parcel_mass' =>$weight
                //];

                //array_push($this->skynet_parcels,$skynet_parcel);
                 //print_r($get_wooCommerce_rates);
            }

            $skynet_parcel = [
                'parcel_number' => $values['quantity'],
                'parcel_length' =>  $length,
                'parcel_breadth' => $breath,
                'parcel_height' => $height,
                'parcel_mass' => $weight
            ];

            array_push($this->skynet_parcels,$skynet_parcel);
        }
        
        $this->get_skynet_quote($package);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $this->total_cost,
                    'price_decimals' => wc_get_price_decimals(),
                );

                $this->add_rate($rate);
                break;
        }
        # --------------------------------------------------------------------------------------------
    }
    /**
     * @Description                             Gets the products attributes to generate a quotation
     * @param $_pickUpPCode                     Pick up postal code
     * @param $_dropOffPCode                    Drop off postal code
     * @param $_product_total_mass              Product weight
     * @return $calculate_distance_for_quotes   Array
     */
    public function quotes_plugin_attributes($_length, $_breadth, $_height, $_mass)
    {
        (array) $_get_product_attributes = new SkynetQuotes($_length, $_breadth, $_height, $_mass);

        $calculate_distance_for_quotes = [
            "mass"                       => $_get_product_attributes->get_parcel_mass(),
            "dimensions"                 => [
                "parcel_length"     => $_get_product_attributes->get_parcel_length(),
                "parcel_breadth"    => $_get_product_attributes->get_parcel_breadth(),
                "parcel_height"     => $_get_product_attributes->get_parcel_height(),
                "parcel_mass"       => $_get_product_attributes->get_parcel_mass()
            ]
        ];

        if (is_array($calculate_distance_for_quotes)) return $calculate_distance_for_quotes;
    }

    function GetSkynetToken()
    {
        global $wpdb;
        $isResultsStored = $this->GetSettings();   
		//print_r($isResultsStored);
        if(empty($isResultsStored[0]->username)) { write_log("Empty credentials: " . json_encode($isResultsStored),true); return ""; }
        $securityToken = "";
        $create_user_token = [
            'Username'          => $isResultsStored[0]->username,
            'Password'         => $isResultsStored[0]->user_Password,
            'SystemId'       => $_ENV['SYSTEM_ID'],
            'AccountNumber'      => $isResultsStored[0]->account_number
        ];
		
        $response = $this->skynet_curl_endpoint($create_user_token, 'TOKEN', 'POST');
        $response_status_code = wp_remote_retrieve_response_code($response);

        if (!is_array($response) && is_wp_error($response)) 
        {
            write_log('ERROR:   API token failed. | '.$response->get_error_message());
            return $response->get_error_message();
        }
        
        if (200 >= $response_status_code || 308 <= $response_status_code) {
            
            foreach ((array) $response['body'] as $key => $value) :
                $responseVar = json_decode($value, true);
            endforeach;
            $securityToken = $responseVar['SecurityToken'];
			//print_r($responseVar);
            if(empty($securityToken))
            {
                write_log("GetTokenFailed. Payload: " . json_encode($create_user_token),true);
            }
        }
        return $securityToken;
    }

    /**
     * @description             - HTTP Client
     * @param $endpoint         - HTTP API
     * @param $body             - HTML body being passed from the form
     * @param $method           - Optional HTTP Method
     * @res
     */
    function skynet_curl_endpoint($body, $endpoint = "TOKEN", $method = 'GET'){
        global $wpdb;
        $url = "";

        $isResultsStored = $this->GetSettings(); 
        if(!empty($isResultsStored[0]->isuat) && $isResultsStored[0]->isuat === "yes") 
        {
            $url = $_ENV['UAT_BASE'];
        }
        else 
        {
            $url = $_ENV['PROD_BASE'];
        }
    
        if($endpoint=="TOKEN")
        {
            $url = $url.$_ENV['GET_TOKEN'];
        }
        else if ($endpoint=="QUOTE"){
            $url = $url.$_ENV['GET_QUOTE'];
        }
        else {
            $url = $url.$_ENV['CREATE_WAYBILL'];
        }
    
        $curl_options = [
            'method'        => $method,
            'headers'       => [
                "Content-Type"  => "application/json",
                "Connection"    => "Keep-Alive",
                "Accept"        => "application/json",
                // "Authorization" => "Bearer {$isResultsStored[0]->retail_api_key}:{$isResultsStored[0]->retail_service_key}"
                // "Authorization" => "Bearer {$_ENV['UAT_RETAIL_API_KEY']}:{$_ENV['UAT_RETAIL_SERVICE_ID']}"
            ],
            'body'          => wp_json_encode($body),
            'blocking'      => true,
            'timeout'       => 60,
            'redirection'   => 5,
            'httpversion'   => '1.0',
            'data_format'   => 'body',
            'sslverify'     => true
        ];
    
        return wp_remote_post($url, $curl_options);
    }

    public function skynet_curl_endpoint1($endpoint, $body, $method = 'GET')
    {
        global $wpdb;


        $isResultsStored = $this->GetSettings(); 

        $curl_options = [
            'method'        => $method,
            'headers'       => [
                "Content-Type"  => "application/json",
                "Connection"    => "Keep-Alive",
                "Accept"        => "application/json",
            ],
            'body'          => wp_json_encode($body),
            'blocking'      => true,
            'timeout'       => 60,
            'redirection'   => 5,
            'httpversion'   => '1.0',
            'data_format'   => 'body',
            'sslverify'     => true
        ];

        return wp_remote_post($endpoint, $curl_options);
    }

    // public function curl
}

class SkynetParcel
{
    public $itemMass;
    public $width;
    public $height;
    public $length;
    public $description;

    function __construct($itemMass, $width, $height, $length)
    {
        $this->itemMass = $itemMass;
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
    }

    function get_itemMass()
    {
        return $this->itemMass;
    }

    function get_width()
    {
        return $this->width;
    }

    function get_height()
    {
        return $this->height;
    }

    function get_length()
    {
        return $this->length;
    }
}

class SkynetQuoteParcel
{
    public $parcel_length;
    public $parcel_breadth;
    public $parcel_height;
	public $VolumetricMass;
	public $parcel_description;
	public $parcel_reference;
	public $parcel_number;

    public function __construct($parcel_length, $parcel_breadth, $parcel_height, $parcel_mass, $VolumetricMass, $parcel_description, $parcel_reference,$parcel_number)
    {
        $this->parcel_length      = $parcel_length;
        $this->parcel_breadth     = $parcel_breadth;
        $this->parcel_height      = $parcel_height;
        $this->parcel_mass        = $parcel_mass;
		$this->parcel_description        	= $parcel_description;
		$this->parcel_reference        		= $parcel_reference;
		$this->parcel_number        		= $parcel_number;
		$this->VolumetricMass        		= $VolumetricMass;
		
    }

    function get_parcel_length()
    {
        return $this->parcel_length;
    }

    function get_parcel_breadth()
    {
        return $this->parcel_breadth;
    }

    function get_parcel_height()
    {
        return $this->parcel_height;
    }

    function get_parcel_mass()
    {
        return $this->parcel_mass;
    }
	
	function get_VolumetricMass()
    {
        return $this->VolumetricMass;
    }
	function get_parcel_description()
    {
        return $this->parcel_description;
    }
	function get_parcel_reference()
    {
        return $this->parcel_reference;
    }
	function get_parcel_number()
    {
        return $this->parcel_number;
    }
}

class SkynetQuote
{
	public $SecurityToken;
    public $AccountNumber;
    public $CompanyName;
    public $FromCity;
    public $FromCityPostalCode;
    public $ToCity;
    public $ToCityPostalCode;
    public $ToLong;
    public $ToLat;
    public $InsuranceType;
    public $InsuranceAmount;
    public $ParcelList;
    public $OptionList;
    public $CreatedBy;

    public function __construct($SecurityToken, $AccountNumber, $CompanyName, 
    $FromCity, $FromCityPostalCode, $ToCity, $ToCityPostalCode,$ToLong, $ToLat,
    $InsuranceType, $InsuranceAmount, $ParcelList,$OptionList,$CreatedBy)
    {
        $this->SecurityToken        = $SecurityToken;
        $this->AccountNumber        = $AccountNumber;
        $this->CompanyName          = $CompanyName;
        $this->FromCity             = $FromCity;
		$this->FromCityPostalCode   = $FromCityPostalCode;
		$this->ToCity        		= $ToCity;
		$this->ToCityPostalCode     = $ToCityPostalCode;
		$this->ToLong        		= $ToLong;
        $this->ToLat        		= $ToLat;
        $this->InsuranceType        = $InsuranceType;
        $this->InsuranceAmount      = $InsuranceAmount;
        $this->ParcelList           = $ParcelList;
        $this->OptionList           = $OptionList;
        $this->CreatedBy            = $CreatedBy;
		
    }

    function get_SecurityToken()
    {
        return $this->SecurtyToken;
    }
    function get_AccountNumber()
    {
        return $this->AccountNumber;
    }
    function get_CompanyName()
    {
        return $this->CompanyName;
    }
    function get_FromCity()
    {
        return $this->FromCity;
    }
    function get_FromCityPostalCode()
    {
        return $this->FromCityPostalCode;
    }
    function get_ToCity()
    {
        return $this->ToCity;
    }
    function get_ToCityPostalCode()
    {
        return $this->ToCityPostalCode;
    }
    function get_ToLong()
    {
        return $this->ToLong;
    }
    function get_ToLat()
    {
        return $this->ToLat;
    }
    function get_InsuranceType()
    {
        return $this->InsuranceType;
    }
    function get_InsuranceAmount()
    {
        return $this->InsuranceAmount;
    }
    function get_ParcelList()
    {
        return $this->ParcelList;
    }
    function get_OptionList()
    {
        return $this->OptionList;
    }
    function get_CreatedBy()
    {
        return $this->CreatedBy;
    }
}

class SkynetQuotes
{
    public $parcel_length;
    public $parcel_breadth;
    public $parcel_height;
    public $parcel_mass;

    public function __construct($parcel_length, $parcel_breadth, $parcel_height, $parcel_mass)
    {
        $this->parcel_length      = $parcel_length;
        $this->parcel_breadth     = $parcel_breadth;
        $this->parcel_height      = $parcel_height;
        $this->parcel_mass        = $parcel_mass;
    }

    function get_parcel_length()
    {
        return $this->parcel_length;
    }

    function get_parcel_breadth()
    {
        return $this->parcel_breadth;
    }

    function get_parcel_height()
    {
        return $this->parcel_height;
    }

    function get_parcel_mass()
    {
        return $this->parcel_mass;
    }
}
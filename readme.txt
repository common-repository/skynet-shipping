=== SkyNet Shipping WooCommerce Plugin ===
Contributors: skynetza
Donate link: https://skynet.co.za/woocommerce-shipping-plugin
Tags: Shipping, Courier
Requires at least: 5.6.0
Tested up to: 6.6.1
Stable tag: 1.1.5
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

== Description ==

The SkyNet Shipping for WooCommerce application or plugin allows you to ships goods everywhere across South Africa.
* This Shipping method uses Express Courier to deliver your parcels.
* Our Express service is available nationwide.
* No collections/deliveries on weekends

== Short Description ==

The SkyNet Shipping for WooCommerce application or plugin allows you to ships goods everywhere across South Africa.

== System Requirements ==

> PHP 7.2 and above is required to run the plugin.


== Before Installation ==

* All plugin users must be InHouse clients of SkyNet along with a API credentials. 
Please contact SkyNet administration to help set it up. customerservice@skynet.co.za
* Please make sure that WooCommerce extension has been installed in your Wordpress account.

== Installation ==

1. Navigate to WooCommerce > Settings > Shipping
    
    Shipping Tab
    -	Click the Shipping Classes under the Settings tab and add a Shipping Class
        o	[Shipping class] - should be Skynet Shipping
        o	[Slug] - should be skynet-shipping
        o	[Description] - should be SkyNet ships everywhere across ZAR (optional)
    -	Click the Save shipping Classes button to set the action
    
    Shipping Zones
    -	Add a Shipping Zone and give it any name of your choosing or SkyNet shipping
    -	Click on the Add Shipping Method button and select SkyNet shipping then Save Changes

    Skynet Shipping Method
    -	Method Title – e.g SkyNet Shipping
    -	Method Description – e.g SkyNet ships across South Africa
    -	Use UAT – if ticked all information will be posted to the SkyNet UAT environment
    -	Use flat rate shipping – if ticked it will use the specified value as shipping cost
    -	Fallback cost - is an optional field that if the plugin is not getting read or quote fails, then your clients will see the amount you havve specified
    -	Markup on SkyNet quote – this will add the entered markup percentage (%) to the SkyNet quote received
    -	Username – the SkyNet username provided
    -	Password – the SkyNet password provided
    -	Account number – the SkyNet account number provided
    -	In house code – the SkyNet in house code provided
    -	Shipper name – the name of the shop/company doing the shipping
    -	Address 1 – the street address of the shipper
    -	Address 2 – optional
    -	Suburb – must be a valid SkyNet suburb. The Suburb/Postal code combination must validate on SkyNet systems
    -	Postal code – must be a valid SkyNet postal code. The Suburb/Postal code combination must validate on SkyNet systems
    -	Phone number - if completed, must be in international format
    -	Contact name – Contact of the Shipper
    -	Service type – the default service type to use. Must be a valid SkyNet service type as agreed with the SkyNet account manager, DBC (domestic budget cargo), ON1 (overnight express)
    -	Default weight – the default kilogram weight to create the waybill with. This can be changed when processing the waybills on the SkyNet portal
    -	Status to trigger – select the order status on which to trigger the call to SkyNet (when the order status to this selected status)
    -	The [Disable Shipping Cache] checkbox has to be set to true (checked).
    -	Then click the Save change button

2. Now go to the Site or Store
    - Add in multiples items from the store into the Cart, then go to Checkout.
    - Add in your Billing information [Address], as soon as the desired Postal code has been entered, the plugin would get activated and display a Quotation, after adding all the information, if the quote isn't displayed, please refresh the page.
    - Select the Skynet Shipping as your shipping method, and select your desired payment method as well
    - Place an order.
3. After placing an order and making successful payment
    - Navigate back to WooCommerce > Orders link
    - All Orders need to be changed to the status trigger specified in settings for waybills to be created

== Frequently Asked Questions ==

1. Does the Skynet Shipping plugin requires an authorization access key?

Yes. In most cases, whenever working with apps, credentials are required to allow data intergration with the application.

2. How long does it take to activate the plugin.

As soon as the plugin is downloaded and activated on the Wordpress dashboard, users can communicate with the support team customerservice@skynet.co.za
for instructions on activating the plugin.

== Screenshots ==

1. Settings section.
2. The output at Checkout page.

== Support ==

Should you encounter any issue with the plugin, please revert back to our support developer team customerservice@skynet.co.za

== Changelog ==
= 1.1.5 =

1. Trigger processing on order creation based on selected status trigger
2. Upgrade PhpOption
3. Improvements in code quality 
4. Prevent re-submission if waybill was already created

= 1.1.3 =

1. Trigger call to Skynet on order create based on selected status 

= 1.1.1 =

1. Additional error handling for flat rate & quote failure 

= 1.1.0 =

1. Added boolean flag for UAT testing.
2. Add status trigger option in plugin settings.
3. Removed reliance on port 3227

= 1.0.6 =

1. Added option for flat rate shipping and markup on SkyNet quote.


= 1.0.5 =

1. Added free shipping threshold. If the total value of content in the cart exceeds this value the shipping cost will be 0.

= 1.0.4 =

1. Added fallback cost for delivery should api not be available at the time.

= 1.0.3 =

1. Waybill will be created when order status is changed to completed.

= 1.0.2 =

1. Make provision for 0 & empty values passed in quote request

= 1.0.1 =

* This is the official release of this version.
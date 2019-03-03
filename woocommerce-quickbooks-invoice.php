<?php
/**
 * Plugin Name: WooCommerce Quickbooks Invoice
 * Description: Integrates Quickbooks Online invoices to WooCommerce orders.
 * Version:     0.0.1
 * Author:      Julian Flynn <hello@juliantrueflynn.com>
 * Text Domain: wcqbi
 * License:     GPL-2.0+
 */

register_activation_hook( __FILE__, 'my_activation' );
register_deactivation_hook( __FILE__, 'my_deactivation' );

function my_activation() {
    if ( ! wp_next_scheduled ( 'wcqbi_schedule_qbo_invoice_check' ) ) {
		wp_schedule_event( time(), 'hourly', 'wcqbi_schedule_qbo_invoice_check' );
    }
}
add_action( 'wcqbi_schedule_qbo_invoice_check', 'wcqbi_build_new_invoices' );

function my_deactivation() {
	delete_option( 'wcqbi_qbo' ); // Just for debugging. Remove for production!
	wp_clear_scheduled_hook( 'wcqbi_schedule_qbo_invoice_check' );
}

function wcqbi_build_new_invoices() {
	$client = new WCQBI_QBO_API_Client();
	$client->set_client();
	$invoices = $client->get_invoices();
	$create_time = "";

	foreach ( $invoices as $invoice ) {
		$customer = $client->get_customer_by_id( intVal( $invoice->CustomerRef ) );
		$email = $invoice->BillEmail ? $invoice->BillEmail->Address : $customer->PrimaryEmailAddr->Address;
		$phone = isset( $customer->PrimaryPhone->FreeFormNumber ) ? $customer->PrimaryPhone->FreeFormNumber : "";
		$address_line_1 = isset( $invoice->BillAddr->Line1 ) ? $invoice->BillAddr->Line1 : "";
		$address_line_2 = isset( $invoice->BillAddr->Line2 ) ? $invoice->BillAddr->Line2 : "";
		$address_line_3 = isset( $invoice->BillAddr->Line3 ) ? $invoice->BillAddr->Line1 : "";
		$address_line_4 = isset( $invoice->BillAddr->Line4 ) ? $invoice->BillAddr->Line1 : "";
		$invoice_city = isset( $invoice->ShipAddr->City ) ? $invoice->ShipAddr->City : "";
		$customer_city = isset( $customer->BillAddr->City ) ? $customer->BillAddr->City : "";
		$invoice_zip_code = isset( $invoice->ShipAddr->PostalCode ) ? $invoice->ShipAddr->PostalCode : "";
		$customer_zip_code = isset( $customer->BillAddr->PostalCode ) ? $customer->BillAddr->PostalCode : "";

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$password = wp_generate_password();
			$user = wc_create_new_customer( $email, '', $password );
		}
		$customer_id = $user->ID;

		$billing_info = [
			'first_name' => $customer->GivenName,
			'last_name'  => $customer->FamilyName,
			'email'      => $email,
			'phone'      => $phone,
			'address_1'  => $address_line_1 . " " . $address_line_2,
			'address_2'  => $address_line_3 . " " . $address_line_4,
			'city'       => $invoice_city ? $invoice_city : $customer_city,
			'postcode'   => $invoice_zip_code ? $invoice_zip_code : $customer_zip_code,
		];

		$create_time = $invoice->MetaData->CreateTime;
		wcqbi_create_order( $billing_info, $customer_id );
	}

	update_option( 'wcqbi_last_update', $create_time );
} 

function wcqbi_init_get_requests() {
	if ( isset( $_GET['code'] ) && isset( $_GET['realmId'] ) ) {
		$client = new WCQBI_QBO_API_Client();
		$client->set_auth_tokens( $_GET['code'], $_GET['realmId'] );
	}
}
add_action( 'admin_init', 'wcqbi_init_get_requests' );

function wcqbi_qbo_connect() {
	if ( ! $_REQUEST['wcqbi_connection_status'] ) {
		update_option( 'wcqbi_connection_status', true );
		$client = new WCQBI_QBO_API_Client();
		$client->redirect_to_auth();
	}

	die( __FUNCTION__ );
}
add_action( 'admin_post_wcqbi_qbo_connect', 'wcqbi_qbo_connect' );

function wcqbi_create_order( $qbo_invoice_args, $customer_id ) {		
	global $woocommerce;

	$defaults = [
		'state' => 'MA',
		'country' => 'US',
	];
	$address = array_merge( $defaults, $qbo_invoice_args );

	$order = new WC_Order();
	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );
	$order->set_customer_id( $customer_id );
	$order->calculate_totals();
	$order->update_status( 'Completed', 'Order created dynamically - ', TRUE );
}

require_once( 'class-qbo-api-client.php' );
require_once( 'wcqbi-settings-page.php' );

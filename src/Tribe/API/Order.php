<?php
/**
 * Handles all of the Order API functionality.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer\API
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\API;

use Exception;

/**
 * Class Order
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer\API
 */
trait Order {

	/**
	 * Generates an Order ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string Generated Order ID.
	 */
	public function generate_order_id() {
		return md5( time() . mt_rand() );
	}

	/**
	 * Create an order for a EDD ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket     Ticket object.
	 * @param array                          $order_data Order data.
	 *
	 * @return int Order ID.
	 *
	 * @throws Exception
	 */
	public function create_order_for_edd_ticket( $ticket, $order_data ) {
		$required_details = [
			'full_name',
			'email',
		];

		foreach ( $required_details as $required_detail ) {
			// Detail is not set.
			if ( ! isset( $order_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Order field "%s" is not set.', $required_detail ) );
			}

			// Detail is empty.
			if ( 'optout' !== $required_detail && empty( $order_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Order field "%s" is empty.', $required_detail ) );
			}
		}

		$full_name         = $order_data['full_name'];
		$email             = $order_data['email'];
		$user_id           = isset( $order_data['user_id'] ) ? (int) $order_data['user_id'] : 0;
		$create_user       = isset( $order_data['create_user'] ) ? (boolean) $order_data['create_user'] : true;
		$use_existing_user = isset( $order_data['use_existing_user'] ) ? (boolean) $order_data['use_existing_user'] : true;
		$order_status      = isset( $order_data['order_status'] ) ? $order_data['order_status'] : 'publish';
		$product_id        = $ticket->ID;

		$order_status = strtolower( trim( $order_status ) );

		/**
		 * Allow filtering on whether to create the user if user_id value is not set. Default is on.
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $create_user Whether to create the user if user_id value is not set.
		 */
		$create_user = (boolean) apply_filters( 'tribe_ext_tickets_attendee_csv_importer_order_api_create_user', $create_user );

		/**
		 * Allow filtering on whether to use an existing user (by email) if user_id value is not set. Default is on.
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $use_existing_user Whether to use an existing user (by email) if user_id value is not set.
		 */
		$use_existing_user = (boolean) apply_filters( 'tribe_ext_tickets_attendee_csv_importer_order_api_use_existing_user', $use_existing_user );

		$first_name = $full_name;
		$last_name  = '';

		if ( false !== strpos( $full_name, ' ' ) ) {
			$name_parts = explode( ' ', $full_name );

			$first_name = array_shift( $name_parts );
			$last_name  = implode( ' ', $name_parts );
		}

		if ( 0 === $user_id && $use_existing_user ) {
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 === $user_id && $create_user ) {
			$user = wp_insert_user( [
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password(),
				'display_name' => $full_name,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
			] );

			if ( ! is_wp_error( $user ) ) {
				$user_id = $user;
			}
		}

		$payment_data = [
			'price'        => $ticket->price,
			'date'         => date( 'Y-m-d H:i:s' ),
			'user_email'   => $email,
			'purchase_key' => uniqid( 'edd-ticket-', true ),
			'currency'     => edd_get_currency(),
			'status'       => $order_status,
			'downloads'    => [
				[
					'id'       => $product_id,
					'quantity' => 1,
				],
			],
			'user_info'    => [
				'id'         => $user_id,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'email'      => $email,
			],
			'cart_details' => [
				[
					'id'         => $product_id,
					'quantity'   => 1,
					'price_id'   => null,
					'tax'        => 0,
					'item_price' => $ticket->price,
					'fees'       => [],
					'discount'   => 0,
				],
			],
		];

		// Remove certain actions to ensure they don't fire when creating the payments
		remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );
		remove_action( 'edd_admin_sale_notice', 'edd_admin_email_notice', 10 );

		// Record the pending payment.
		$order_id = edd_insert_payment( $payment_data );

		// Decrease the ticket stock.
		if ( $order_id && $ticket->manage_stock() ) {
			update_post_meta( $order_id, '_stock', $ticket->stock() - 1 );
		}

		return $order_id;
	}

	/**
	 * Create an order for a WooCommerce ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket     Ticket object.
	 * @param array                          $order_data Order data.
	 *
	 * @return int Order ID.
	 *
	 * @throws Exception
	 */
	public function create_order_for_woo_ticket( $ticket, $order_data ) {
		// Prevent WC emails.
		add_filter( 'woocommerce_email_enabled_new_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false' );

		// Disable normal attendee generation.
		remove_action( 'woocommerce_order_status_changed', [ $ticket->get_provider(), 'delayed_ticket_generation' ], 12 );

		$required_details = [
			'full_name',
			'email',
		];

		foreach ( $required_details as $required_detail ) {
			// Detail is not set.
			if ( ! isset( $order_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Order field "%s" is not set.', $required_detail ) );
			}

			// Detail is empty.
			if ( 'optout' !== $required_detail && empty( $order_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Order field "%s" is empty.', $required_detail ) );
			}
		}

		$full_name         = $order_data['full_name'];
		$email             = $order_data['email'];
		$user_id           = isset( $order_data['user_id'] ) ? (int) $order_data['user_id'] : 0;
		$create_user       = isset( $order_data['create_user'] ) ? (boolean) $order_data['create_user'] : true;
		$use_existing_user = isset( $order_data['use_existing_user'] ) ? (boolean) $order_data['use_existing_user'] : true;
		$order_status      = isset( $order_data['order_status'] ) ? $order_data['order_status'] : 'completed';
		$product_id        = $ticket->ID;

		$order_status = strtolower( trim( $order_status ) );

		/**
		 * Allow filtering on whether to create the user if user_id value is not set. Default is on.
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $create_user Whether to create the user if user_id value is not set.
		 */
		$create_user = (boolean) apply_filters( 'tribe_ext_tickets_attendee_csv_importer_order_api_create_user', $create_user );

		/**
		 * Allow filtering on whether to use an existing user (by email) if user_id value is not set. Default is on.
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $use_existing_user Whether to use an existing user (by email) if user_id value is not set.
		 */
		$use_existing_user = (boolean) apply_filters( 'tribe_ext_tickets_attendee_csv_importer_order_api_use_existing_user', $use_existing_user );


		$first_name = $full_name;
		$last_name  = '';

		if ( false !== strpos( $full_name, ' ' ) ) {
			$name_parts = explode( ' ', $full_name );

			$first_name = array_shift( $name_parts );
			$last_name  = implode( ' ', $name_parts );
		}

		if ( 0 === $user_id && $use_existing_user ) {
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 === $user_id && $create_user ) {
			$user = wp_insert_user( [
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password(),
				'display_name' => $full_name,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
			] );

			if ( ! is_wp_error( $user ) ) {
				$user_id = $user;
			}
		}

		$order = wc_create_order( [
			'customer_id' => $user_id,
			'created_via' => 'import',
		] );

		$product = wc_get_product( $product_id );

		$order->add_product( $product, 1 );

		$address = [
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
		];

		// Set addresses.
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		// Set payment gateway.
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		// Use bank transfer method for now.
		$order->set_payment_method( $payment_gateways['bacs'] );

		// Calculate totals
		$order->calculate_totals();

		// Update status.
		$order->update_status( $order_status, 'Order created dynamically from EA Attendee Import', true );

		return $order->get_id();
	}
}

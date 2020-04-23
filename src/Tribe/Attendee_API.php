<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer;

use Exception;
use Tribe__Post_Transient;
use Tribe__Tickets__Tickets;

/**
 * Class Attendee_API
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
trait Attendee_API {

	/**
	 * Generates an Order ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string Generated Order ID.
	 */
	public function generate_order_id() {
		return md5( time() . rand() );
	}

	/**
	 * Create an attendee for a RSVP ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                          $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws Exception
	 */
	public function create_attendee_for_rsvp_ticket( $ticket, $attendee_data ) {
		$rsvp_options = \Tribe__Tickets__Tickets_View::instance()->get_rsvp_options( null, false );

		$required_details = [
			'full_name',
			'email',
		];

		foreach ( $required_details as $required_detail ) {
			// Detail is not set.
			if ( ! isset( $attendee_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Attendee field "%s" is not set.', $required_detail ) );
			}

			// Detail is empty.
			if ( 'optout' !== $required_detail && empty( $attendee_data[ $required_detail ] ) ) {
				throw new Exception( sprintf( 'Attendee field "%s" is empty.', $required_detail ) );
			}
		}

		$full_name    = $attendee_data['full_name'];
		$email        = $attendee_data['email'];
		$optout       = true;
		$order_status = isset( $attendee_data['order_status'] ) ? $attendee_data['order_status'] : 'yes';
		$order_id     = ! empty( $attendee_data['order_id'] ) ? $attendee_data['order_id'] : $this->generate_order_id();
		$product_id   = $ticket->ID;
		$i            = isset( $attendee_data['i'] ) ? $attendee_data['i'] : null;

		if ( isset( $attendee_data['optout'] ) && '' !== $attendee_data['optout'] ) {
			$optout = tribe_is_truthy( $attendee_data['optout'] );
		}

		if ( ! isset( $rsvp_options[ $order_status ] ) ) {
			$order_status = 'yes';
		}

		/** @var \Tribe__Tickets__RSVP $provider */
		$provider = $ticket->get_provider();

		// Get the event this ticket is for.
		$post_id = (int) get_post_meta( $product_id, $provider->event_key, true );

		if ( empty( $post_id ) ) {
			return false;
		}

		$attendee = [
			'post_status' => 'publish',
			'post_title'  => $full_name,
			'post_type'   => $provider::ATTENDEE_OBJECT,
			'ping_status' => 'closed',
		];

		if ( $i ) {
			$attendee['post_title'] .= ' | ' . $i;
		}

		// Insert individual ticket purchased.
		$attendee_id = wp_insert_post( $attendee );

		if ( is_wp_error( $attendee_id ) ) {
			throw new Exception( $attendee_id->get_error_message() );
		}

		// Get the RSVP status `decrease_stock_by` value.
		$status_stock_size = $rsvp_options[ $order_status ]['decrease_stock_by'];

		if ( 0 < $status_stock_size ) {
			// @todo Holy race condition batman!

			// Adjust total sales.
			$sales = (int) get_post_meta( $product_id, 'total_sales', true );
			update_post_meta( $product_id, 'total_sales', ++ $sales );

			// Adjust stock.
			$stock = (int) get_post_meta( $product_id, '_stock', true ) - $status_stock_size;
			update_post_meta( $product_id, '_stock', $stock );
		}

		update_post_meta( $attendee_id, $provider::ATTENDEE_PRODUCT_KEY, $product_id );
		update_post_meta( $attendee_id, $provider::ATTENDEE_EVENT_KEY, $post_id );
		update_post_meta( $attendee_id, $provider::ATTENDEE_RSVP_KEY, $order_status );
		update_post_meta( $attendee_id, $provider->security_code, $provider->generate_security_code( $attendee_id ) );
		update_post_meta( $attendee_id, $provider->order_key, $order_id );
		update_post_meta( $attendee_id, $provider::ATTENDEE_OPTOUT_KEY, (int) $optout );
		update_post_meta( $attendee_id, $provider->full_name, $full_name );
		update_post_meta( $attendee_id, $provider->email, $email );
		update_post_meta( $attendee_id, '_paid_price', 0 );

		/**
		 * RSVP specific action fired when a RSVP-driven attendee ticket for an event is generated.
		 * Used to assign a unique ID to the attendee.
		 *
		 * @param int    $attendee_id ID of attendee ticket
		 * @param int    $post_id     ID of event
		 * @param string $order_id    RSVP order ID (hash)
		 * @param int    $product_id  RSVP product ID
		 */
		do_action( 'event_tickets_rsvp_attendee_created', $attendee_id, $post_id, $order_id, $product_id );

		/**
		 * Action fired when an RSVP attendee ticket is created.
		 * Used to store attendee meta.
		 *
		 * @param int $attendee_id       ID of the attendee post
		 * @param int $post_id           Event post ID
		 * @param int $product_id        RSVP ticket post ID
		 * @param int $order_attendee_id Attendee # for order
		 */
		do_action( 'event_tickets_rsvp_ticket_created', $attendee_id, $post_id, $product_id, $i );

		// Check if user exists.
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			update_post_meta( $attendee_id, $provider->attendee_user_id, $user->ID );
		}

		/**
		 * Action fired when an RSVP has had attendee tickets generated for it
		 *
		 * @param int    $product_id RSVP ticket post ID
		 * @param string $order_id   ID (hash) of the RSVP order
		 * @param int    $qty        Quantity ordered
		 */
		do_action( 'event_tickets_rsvp_tickets_generated_for_product', $product_id, $order_id, 1 );

		// After Adding the Values we Update the Transient
		Tribe__Post_Transient::instance()->delete( $post_id, Tribe__Tickets__Tickets::ATTENDEES_CACHE );

		return true;
	}

	/**
	 * Create an attendee for a PayPal ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                          $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws Exception
	 */
	public function create_attendee_for_paypal_ticket( $ticket, $attendee_data ) {
		throw new Exception( 'Not ready yet' );

		/** @var $provider \Tribe__Tickets__Commerce__PayPal__Main */
		// @todo Figure out ticket generation for PayPal.
		$provider->generate_tickets( 'completed', false );
	}

	/**
	 * Create an attendee for a EDD ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                          $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws Exception
	 */
	public function create_attendee_for_edd_ticket( $ticket, $attendee_data ) {
		throw new Exception( 'Not ready yet' );

		/** @var $provider \Tribe__Tickets_Plus__Commerce__EDD__Main */
		// @todo Figure out ticket generation for EDD.
		$provider->generate_tickets( $order_id );
	}

	/**
	 * Create an attendee for a WooCommerce ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                          $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws Exception
	 */
	public function create_attendee_for_woo_ticket( $ticket, $attendee_data ) {
		throw new Exception( 'Not ready yet' );

		/** @var $provider \Tribe__Tickets_Plus__Commerce__WooCommerce__Main */
		// @todo Figure out ticket generation for WooCommerce.
		$provider->generate_tickets( $order_id );
	}
}

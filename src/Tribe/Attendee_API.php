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
use Tribe__Tickets__Commerce__PayPal__Stati;
use Tribe__Tickets__Global_Stock;
use WC_Order;

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

		$full_name         = $attendee_data['full_name'];
		$email             = $attendee_data['email'];
		$optout            = true;
		$user_id           = isset( $attendee_data['user_id'] ) ? (int) $attendee_data['user_id'] : 0;
		$order_status      = isset( $attendee_data['order_status'] ) ? $attendee_data['order_status'] : 'yes';
		$order_id          = ! empty( $attendee_data['order_id'] ) ? $attendee_data['order_id'] : $this->generate_order_id();
		$product_id        = $ticket->ID;
		$order_attendee_id = isset( $attendee_data['order_attendee_id'] ) ? $attendee_data['order_attendee_id'] : null;

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
			'post_type'   => $provider->attendee_object,
			'ping_status' => 'closed',
		];

		if ( $order_id ) {
			$attendee['post_title'] = $order_id . ' | ' . $attendee['post_title'];
		}

		if ( null !== $order_attendee_id ) {
			$attendee['post_title'] .= ' | ' . $order_attendee_id;
		}

		// Insert individual ticket purchased.
		$attendee_id = wp_insert_post( $attendee );

		if ( is_wp_error( $attendee_id ) ) {
			throw new Exception( $attendee_id->get_error_message() );
		}

		update_post_meta( $attendee_id, $provider->attendee_product_key, $product_id );
		update_post_meta( $attendee_id, $provider->attendee_event_key, $post_id );
		update_post_meta( $attendee_id, $provider->security_code, $provider->generate_security_code( $attendee_id ) );
		update_post_meta( $attendee_id, $provider->order_key, $order_id );
		update_post_meta( $attendee_id, $provider->attendee_optout_key, (int) $optout );

		if ( 0 === $user_id ) {
			// Check if user exists.
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 < $user_id ) {
			update_post_meta( $attendee_id, $provider->attendee_user_id, $user_id );
		}

		// @todo ET should add a property for this.
		update_post_meta( $attendee_id, $provider::ATTENDEE_RSVP_KEY, $order_status );
		update_post_meta( $attendee_id, $provider->full_name, $full_name );
		update_post_meta( $attendee_id, $provider->email, $email );

		update_post_meta( $attendee_id, '_paid_price', 0 );

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

		/**
		 * RSVP specific action fired when a RSVP-driven attendee ticket for an event is generated.
		 * Used to assign a unique ID to the attendee.
		 *
		 * @param int    $attendee_id ID of attendee ticket.
		 * @param int    $post_id     ID of event.
		 * @param string $order_id    RSVP order ID (hash).
		 * @param int    $product_id  RSVP product ID.
		 */
		do_action( 'event_tickets_rsvp_attendee_created', $attendee_id, $post_id, $order_id, $product_id );

		/**
		 * Action fired when an RSVP attendee ticket is created.
		 * Used to store attendee meta.
		 *
		 * @param int $attendee_id       ID of the attendee post.
		 * @param int $post_id           Event post ID.
		 * @param int $product_id        RSVP ticket post ID.
		 * @param int $order_attendee_id Attendee # for order.
		 */
		do_action( 'event_tickets_rsvp_ticket_created', $attendee_id, $post_id, $product_id, $order_attendee_id );

		/**
		 * Action fired when an RSVP ticket has had attendee tickets generated for it.
		 *
		 * @param int    $product_id RSVP ticket post ID.
		 * @param string $order_id   ID (hash) of the RSVP order.
		 * @param int    $qty        Quantity ordered.
		 */
		do_action( 'event_tickets_rsvp_tickets_generated_for_product', $product_id, $order_id, 1 );

		$provider->clear_attendees_cache( $post_id );

		return $attendee_id;
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

		$full_name         = $attendee_data['full_name'];
		$email             = $attendee_data['email'];
		$optout            = true;
		$user_id           = isset( $attendee_data['user_id'] ) ? (int) $attendee_data['user_id'] : 0;
		$order_status      = isset( $attendee_data['order_status'] ) ? $attendee_data['order_status'] : 'completed';
		$order_id          = ! empty( $attendee_data['order_id'] ) ? $attendee_data['order_id'] : $this->generate_order_id();
		$product_id        = $ticket->ID;
		$order_attendee_id = isset( $attendee_data['order_attendee_id'] ) ? $attendee_data['order_attendee_id'] : null;

		if ( isset( $attendee_data['optout'] ) && '' !== $attendee_data['optout'] ) {
			$optout = tribe_is_truthy( $attendee_data['optout'] );
		}

		$order_status = strtolower( trim( $order_status ) );

		/** @var \Tribe__Tickets__Commerce__PayPal__Main $provider */
		$provider = $ticket->get_provider();

		// Get the event this ticket is for.
		$post_id = (int) get_post_meta( $product_id, $provider->event_key, true );

		if ( empty( $post_id ) ) {
			return false;
		}

		$attendee = [
			'post_status' => 'publish',
			'post_title'  => $order_id . ' | ' . $full_name,
			'post_type'   => $provider->attendee_object,
			'ping_status' => 'closed',
		];

		if ( null !== $order_attendee_id ) {
			$attendee['post_title'] .= ' | ' . $order_attendee_id;
		}

		// Insert individual ticket purchased.
		$attendee_id = wp_insert_post( $attendee );

		if ( is_wp_error( $attendee_id ) ) {
			throw new Exception( $attendee_id->get_error_message() );
		}

		update_post_meta( $attendee_id, $provider->attendee_product_key, $product_id );
		update_post_meta( $attendee_id, $provider->attendee_event_key, $post_id );
		update_post_meta( $attendee_id, $provider->security_code, $provider->generate_security_code( $attendee_id ) );
		update_post_meta( $attendee_id, $provider->order_key, $order_id );
		update_post_meta( $attendee_id, $provider->attendee_optout_key, (int) $optout );

		if ( 0 === $user_id ) {
			// Check if user exists.
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 < $user_id ) {
			update_post_meta( $attendee_id, $provider->attendee_user_id, $user_id );
		}

		// @todo ET should add a generic property for this.
		update_post_meta( $attendee_id, $provider->attendee_tpp_key, $order_status );
		update_post_meta( $attendee_id, $provider->full_name, $full_name );
		update_post_meta( $attendee_id, $provider->email, $email );

		/** @var \Tribe__Tickets__Commerce__Currency $currency */
		$currency        = tribe( 'tickets.commerce.currency' );
		$currency_symbol = $currency->get_currency_symbol( $product_id, true );

		update_post_meta( $attendee_id, '_paid_price', get_post_meta( $product_id, '_price', true ) );
		update_post_meta( $attendee_id, '_price_currency_symbol', $currency_symbol );

		$global_stock    = new Tribe__Tickets__Global_Stock( $post_id );
		$shared_capacity = $global_stock->is_enabled();

		// @todo Holy race condition batman!
		switch ( $order_status ) {
			case Tribe__Tickets__Commerce__PayPal__Stati::$completed:
				$provider->increase_ticket_sales_by( $product_id, 1, $shared_capacity, $global_stock );
				break;
			case Tribe__Tickets__Commerce__PayPal__Stati::$refunded:
				$provider->decrease_ticket_sales_by( $product_id, 1, $shared_capacity, $global_stock );

				// Set refund info.
				$refund_transaction_id = ! empty( $attendee_data['refund_transaction_id'] ) ? $attendee_data['refund_transaction_id'] : '';

				update_post_meta( $attendee_id, $provider->refund_order_key, $refund_transaction_id );
				break;
			default:
				break;
		}

		/**
		 * Action fired when an PayPal attendee ticket is created.=
		 *
		 * @param int    $attendee_id       Attendee post ID.
		 * @param string $order_id          PayPal Order ID.
		 * @param int    $product_id        PayPal ticket post ID.
		 * @param int    $order_attendee_id Attendee number in submitted order.
		 * @param string $order_status      The order status for the attendee.
		 */
		do_action( 'event_tickets_tpp_attendee_created', $attendee_id, $order_id, $product_id, $order_attendee_id, $order_status );

		/**
		 * Action fired when a PayPal ticket has had attendee tickets generated for it.
		 *
		 * @param int    $product_id PayPal ticket post ID.
		 * @param string $order_id   ID of the PayPal order.
		 * @param int    $qty        Quantity ordered.
		 */
		do_action( 'event_tickets_tpp_tickets_generated_for_product', $product_id, $order_id, 1 );

		$provider->clear_attendees_cache( $post_id );

		return $attendee_id;
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

		$full_name         = $attendee_data['full_name'];
		$email             = $attendee_data['email'];
		$optout            = true;
		$user_id           = isset( $attendee_data['user_id'] ) ? (int) $attendee_data['user_id'] : 0;
		$order_status      = isset( $attendee_data['order_status'] ) ? $attendee_data['order_status'] : 'publish';
		$order_id          = ! empty( $attendee_data['order_id'] ) ? (int) $attendee_data['order_id'] : 0;
		$product_id        = $ticket->ID;
		$order_attendee_id = isset( $attendee_data['order_attendee_id'] ) ? $attendee_data['order_attendee_id'] : null;

		if ( isset( $attendee_data['optout'] ) && '' !== $attendee_data['optout'] ) {
			$optout = tribe_is_truthy( $attendee_data['optout'] );
		}

		if ( is_numeric( $order_id ) && 0 < $order_id && 0 === $user_id ) {
			$user_id = get_post_meta( $order_id, '_edd_payment_user_id', true );
		}

		$order_status = strtolower( trim( $order_status ) );

		/** @var \Tribe__Tickets_Plus__Commerce__EDD__Main $provider */
		$provider = $ticket->get_provider();

		// Get the event this ticket is for.
		$post_id = (int) get_post_meta( $product_id, $provider->event_key, true );

		if ( empty( $post_id ) ) {
			return false;
		}

		$attendee = [
			'post_status' => 'publish',
			'post_title'  => $ticket->name,
			'post_type'   => $provider->attendee_object,
			'ping_status' => 'closed',
		];

		if ( 0 < $order_id ) {
			$attendee['post_title'] = $order_id . ' | ' . $attendee['post_title'];
		}

		if ( null !== $order_attendee_id ) {
			$attendee['post_title'] .= ' | ' . $order_attendee_id;
		}

		// Insert individual ticket purchased.
		$attendee_id = wp_insert_post( $attendee );

		if ( is_wp_error( $attendee_id ) ) {
			throw new Exception( $attendee_id->get_error_message() );
		}

		update_post_meta( $attendee_id, $provider->attendee_product_key, $product_id );
		update_post_meta( $attendee_id, $provider->attendee_event_key, $post_id );
		update_post_meta( $attendee_id, $provider->security_code, $provider->generate_security_code( $attendee_id ) );
		update_post_meta( $attendee_id, $provider->order_key, $order_id );
		update_post_meta( $attendee_id, $provider->attendee_optout_key, (int) $optout );

		if ( 0 === $user_id && $email ) {
			// Check if user exists.
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 < $user_id ) {
			update_post_meta( $attendee_id, $provider->attendee_user_id, $user_id );
		}

		/** @var \Tribe__Tickets__Commerce__Currency $currency */
		$currency        = tribe( 'tickets.commerce.currency' );
		$currency_symbol = $currency->get_currency_symbol( $product_id, true );

		update_post_meta( $attendee_id, '_paid_price', $provider->get_price_value( $product_id ) );
		update_post_meta( $attendee_id, '_price_currency_symbol', $currency_symbol );

		/**
		 * Easy Digital Downloads specific action fired when an EDD-driven attendee ticket for an event is generated.
		 *
		 * @param int $attendee_id ID of attendee ticket.
		 * @param int $post_id     ID of event.
		 * @param int $order_id    Easy Digital Downloads order ID.
		 * @param int $product_id  Easy Digital Downloads product ID.
		 */
		do_action( 'event_ticket_edd_attendee_created', $attendee_id, $post_id, $order_id, $product_id );

		/**
		 * Action fired when an attendee ticket is generated.
		 *
		 * @param int $attendee_id       ID of attendee ticket.
		 * @param int $order             EDD order ID.
		 * @param int $product_id        Product ID attendee is "purchasing".
		 * @param int $order_attendee_id Attendee # for order.
		 */
		do_action( 'event_tickets_edd_ticket_created', $attendee_id, $order_id, $product_id, $order_attendee_id );

		$provider->clear_attendees_cache( $post_id );

		return $attendee_id;
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

		$full_name         = $attendee_data['full_name'];
		$email             = $attendee_data['email'];
		$optout            = true;
		$user_id           = isset( $attendee_data['user_id'] ) ? (int) $attendee_data['user_id'] : 0;
		$order_status      = isset( $attendee_data['order_status'] ) ? $attendee_data['order_status'] : 'publish';
		$order_id          = ! empty( $attendee_data['order_id'] ) ? (int) $attendee_data['order_id'] : 0;
		$product_id        = $ticket->ID;
		$order_attendee_id = isset( $attendee_data['order_attendee_id'] ) ? $attendee_data['order_attendee_id'] : null;
		$order_item_id     = isset( $attendee_data['order_item_id'] ) ? $attendee_data['order_item_id'] : null;

		if ( isset( $attendee_data['optout'] ) && '' !== $attendee_data['optout'] ) {
			$optout = tribe_is_truthy( $attendee_data['optout'] );
		}

		if ( is_numeric( $order_id ) && 0 < $order_id ) {
			$order = new WC_Order( $order_id );

			if ( 0 === $user_id ) {
				if ( method_exists( $order, 'get_customer_id' ) ) {
					// WooCommerce 3.x and above.
					$user_id = $order->get_customer_id();
				} else {
					// WooCommerce 2.x and below.
					$user_id = $order->customer_user;
				}
			}
		}

		$order_status = strtolower( trim( $order_status ) );

		/** @var \Tribe__Tickets_Plus__Commerce__WooCommerce__Main $provider */
		$provider = $ticket->get_provider();

		// Get the event this ticket is for.
		$post_id = (int) get_post_meta( $product_id, $provider->event_key, true );

		if ( empty( $post_id ) ) {
			return false;
		}

		$attendee = [
			'post_status' => 'publish',
			'post_title'  => $ticket->name,
			'post_type'   => $provider->attendee_object,
			'ping_status' => 'closed',
		];

		if ( 0 < $order_id ) {
			$attendee['post_title'] = $order_id . ' | ' . $attendee['post_title'];
		}

		if ( null !== $order_attendee_id ) {
			$attendee['post_title'] .= ' | ' . $order_attendee_id;
		}

		// Insert individual ticket purchased.
		$attendee_id = wp_insert_post( $attendee );

		if ( is_wp_error( $attendee_id ) ) {
			throw new Exception( $attendee_id->get_error_message() );
		}

		update_post_meta( $attendee_id, $provider->attendee_product_key, $product_id );
		update_post_meta( $attendee_id, $provider->attendee_event_key, $post_id );
		update_post_meta( $attendee_id, $provider->security_code, $provider->generate_security_code( $attendee_id ) );
		update_post_meta( $attendee_id, $provider->order_key, $order_id );
		update_post_meta( $attendee_id, $provider->attendee_optout_key, (int) $optout );

		if ( 0 === $user_id && $email ) {
			// Check if user exists.
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( 0 < $user_id ) {
			update_post_meta( $attendee_id, $provider->attendee_user_id, $user_id );
		}

		if ( null !== $order_item_id ) {
			update_post_meta( $attendee_id, $provider->attendee_order_item_key, $order_item_id );
		}

		/** @var \Tribe__Tickets__Commerce__Currency $currency */
		$currency        = tribe( 'tickets.commerce.currency' );
		$currency_symbol = $currency->get_currency_symbol( $product_id, true );

		update_post_meta( $attendee_id, '_paid_price', $provider->get_price_value( $product_id ) );
		update_post_meta( $attendee_id, '_price_currency_symbol', $currency_symbol );

		/**
		 * WooCommerce-specific action fired when a WooCommerce-driven attendee ticket for an event is generated.
		 *
		 * @param int           $attendee_id ID of attendee ticket.
		 * @param int           $post_id     ID of event.
		 * @param WC_Order|null $order       WooCommerce order.
		 * @param int           $product_id  WooCommerce product ID.
		 */
		do_action( 'event_ticket_woo_attendee_created', $attendee_id, $post_id, $order, $product_id );

		/**
		 * Action fired when an attendee ticket is generated.
		 *
		 * @param int $attendee_id       ID of attendee ticket.
		 * @param int $order_id          WooCommerce order ID.
		 * @param int $product_id        Product ID attendee is "purchasing".
		 * @param int $order_attendee_id Attendee # for order.
		 */
		do_action( 'event_tickets_woocommerce_ticket_created', $attendee_id, $order_id, $product_id, $order_attendee_id );

		// @todo This is temporary
		/**
		 * Action fired when a WooCommerce has had attendee tickets generated for it
		 *
		 * @param int $product_id RSVP ticket post ID
		 * @param int $order_id   ID of the WooCommerce order
		 * @param int $quantity   Quantity ordered
		 * @param int $post_id    ID of event
		 */
		do_action( 'event_tickets_woocommerce_tickets_generated_for_product', $product_id, $order_id, 1, $post_id );

		update_post_meta( $order_id, $provider->order_has_tickets, '1' );

		$provider->clear_attendees_cache( $post_id );

		return $attendee_id;
	}
}

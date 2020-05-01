<?php
/**
 * Handles all of the Attendee Email API functionality.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer\API\Attendee
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\API\Attendee;

use Tribe__Tickets__Commerce__PayPal__Stati;

/**
 * Class Email
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer\API\Attendee
 */
trait Email {

	/**
	 * Send email for a RSVP ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $order_id              The order ID.
	 * @param int                   $post_id               The post/event of the ticket being ordered.
	 * @param string                $attendee_order_status The order status.
	 * @param \Tribe__Tickets__RSVP $provider              Provider object.
	 */
	public function send_email_for_rsvp_ticket( $order_id, $post_id, $attendee_order_status, $provider ) {
		/** @var Tribe__Tickets__Status__Manager $status_mgr */
		$status_mgr = tribe( 'tickets.status' );

		$send_mail_stati = $status_mgr->get_statuses_by_action( 'attendee_dispatch', 'rsvp' );

		/**
		 * Filters whether a confirmation email should be sent or not for RSVP tickets.
		 *
		 * This applies to attendance and non attendance emails.
		 *
		 * @param bool $send_mail Defaults to `true`.
		 */
		$send_mail = apply_filters( 'tribe_tickets_rsvp_send_mail', true );

		if ( $send_mail ) {
			/**
			 * Filters the attendee order stati that should trigger an attendance confirmation.
			 *
			 * Any attendee order status not listed here will trigger a non attendance email.
			 *
			 * @param array  $send_mail_stati       An array of default stati triggering an attendance email.
			 * @param int    $order_id              ID of the RSVP order.
			 * @param int    $post_id               ID of the post the order was placed for.
			 * @param string $attendee_order_status status if the user indicated they will attend.
			 */
			$send_mail_stati = apply_filters( 'tribe_tickets_rsvp_send_mail_stati', $send_mail_stati, $order_id, $post_id, $attendee_order_status );

			// No point sending tickets if their current intention is not to attend
			if ( in_array( $attendee_order_status, $send_mail_stati, true ) ) {
				$provider->send_tickets_email( $order_id, $post_id );
			} else {
				$provider->send_non_attendance_confirmation( $order_id, $post_id );
			}
		}
	}

	/**
	 * Send email for a PayPal ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                  $order_id              The order ID.
	 * @param int                                     $post_id               The post/event of the ticket being ordered.
	 * @param string                                  $attendee_order_status The order status.
	 * @param \Tribe__Tickets__Commerce__PayPal__Main $provider              Provider object.
	 */
	public function send_email_for_paypal_ticket( $order_id, $post_id, $attendee_order_status, $provider ) {
		/**
		 * Filters whether a confirmation email should be sent or not for PayPal tickets.
		 *
		 * This applies to attendance and non attendance emails.
		 *
		 * @since 4.7
		 *
		 * @param bool $send_mail Defaults to `true`.
		 */
		$send_mail = apply_filters( 'tribe_tickets_tpp_send_mail', true );

		if ( $send_mail && $attendee_order_status === Tribe__Tickets__Commerce__PayPal__Stati::$completed ) {
			$provider->send_tickets_email( $order_id, $post_id );
		}
	}

	/**
	 * Send email for a EDD ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                    $order_id              The order ID.
	 * @param int                                       $post_id               The post/event of the ticket being
	 *                                                                         ordered.
	 * @param \Tribe__Tickets_Plus__Commerce__EDD__Main $provider              Provider object.
	 */
	public function send_email_for_edd_ticket( $order_id, $post_id, $provider ) {
		/**
		 * Filters whether a confirmation email should be sent or not for EDD tickets.
		 *
		 * This applies to attendance and non attendance emails.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $send_mail Defaults to `true`.
		 */
		$send_mail = apply_filters( 'tribe_tickets_plus_edd_send_mail', true );

		if ( $send_mail ) {
			update_post_meta( $order_id, $provider->order_has_tickets, '1' );

			/**
			 * Run the action that triggers the email to be sent to user.
			 *
			 * @param int $order_id The EDD order number.
			 */
			do_action( 'eddtickets-send-tickets-email', $order_id );

			/**
			 * An Action fired when an email is sent after successful generation of attendees for an order.
			 *
			 * @since 4.11.0
			 *
			 * @param int $order_id The EDD order number.
			 */
			do_action( 'event_tickets_plus_edd_send_ticket_emails', $order_id );
		}
	}

	/**
	 * Send email for a WooCommerce ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                            $order_id              The order ID.
	 * @param int                                               $post_id               The post/event of the ticket
	 *                                                                                 being ordered.
	 * @param \Tribe__Tickets_Plus__Commerce__WooCommerce__Main $provider              Provider object.
	 */
	public function send_email_for_woo_ticket( $order_id, $post_id, $provider ) {
		$provider->send_tickets_email( $order_id );
	}
}

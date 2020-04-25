<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\Providers\WooCommerce;

use Exception;
use Tribe\Extensions\Tickets\Attendee_CSV_Importer\Importer as Importer_Base;
use Tribe__Tickets__Ticket_Object;

/**
 * Class Importer
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
class Importer extends Importer_Base {

	/**
	 * Create an attendee for a ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                         $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws Exception
	 */
	protected function create_attendee_for_ticket( $ticket, $attendee_data ) {
		$order_id = $this->create_order_for_woo_ticket( $ticket, $attendee_data );

		$attendee_data['order_id'] = $order_id;

		return $this->create_attendee_for_woo_ticket( $ticket, $attendee_data );
	}
}

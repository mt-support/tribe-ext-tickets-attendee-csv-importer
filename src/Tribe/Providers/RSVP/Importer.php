<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\Providers\RSVP;

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
	 * Get attendee data from record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return array Attendee data.
	 */
	protected function get_attendee_data_from( array $record ) {
		$data = parent::get_attendee_data_from( $record );

		$data['going'] = $this->get_value_by_key( $record, 'going' );

		if ( '' === $data['going'] ) {
			// Set default.
			$data['going'] = true;
		} else {
			// Enforce boolean.
			$data['going'] = tribe_is_truthy( $data['going'] ) || 'going' === $data['going'];
		}

		return $data;
	}

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
		$attendee_data['order_status'] = 'no';

		if ( tribe_is_truthy( $attendee_data['going'] ) || 'going' === strtolower( $attendee_data['going'] ) ) {
			$attendee_data['order_status'] = 'yes';
		}

		return $this->create_attendee_for_rsvp_ticket( $ticket, $attendee_data );
	}
}

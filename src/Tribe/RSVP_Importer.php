<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer;

/**
 * Class RSVP_Importer
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
class RSVP_Importer extends Importer {

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
		$data = [
			'attendee_name'  => $this->get_value_by_key( $record, 'attendee_name' ),
			'attendee_email' => $this->get_value_by_key( $record, 'attendee_email' ),
			'going'          => $this->get_value_by_key( $record, 'going' ),
			'display_optin'  => $this->get_value_by_key( $record, 'display_optin' ),
		];

		if ( '' === $data['going'] ) {
			// Set default.
			$data['going'] = true;
		} else {
			// Enforce boolean.
			$data['going'] = tribe_is_truthy( $data['going'] ) || 'going' === $data['going'];
		}

		if ( '' === $data['display_optin'] ) {
			// Set default.
			$data['display_optin'] = false;
		} else {
			// Enforce boolean.
			$data['display_optin'] = tribe_is_truthy( $data['display_optin'] );
		}

		/**
		 * Add an opportunity to change the data for the RSVP attendee created via a CSV file
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Attendee data.
		 */
		$data = (array) apply_filters( 'tribe_ext_tickets_attendee_csv_rsvp_importer_attendee_data', $data );

		return $data;
	}

	/**
	 * Registers the post types for tracking activity.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Events__Aggregator__Record__Activity $activity Activity object.
	 */
	public function register_post_type_activity( $activity ) {
		$activity->register( 'rsvp_attendee' );
	}
}

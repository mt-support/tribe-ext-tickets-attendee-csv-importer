<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\Providers\RSVP;

use Tribe\Extensions\Tickets\Attendee_CSV_Importer\Integration as Integration_Base;
use Tribe__Events__Importer__File_Importer as File_Importer;
use Tribe__Events__Importer__File_Reader as File_Reader;

/**
 * Class Integration
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
class Integration extends Integration_Base {

	/**
	 * The integration post type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $type = 'tribe_rsvp_attendees';

	/**
	 * The ORM type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $orm_type = 'rsvp';

	/**
	 * The event meta key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $event_meta_key = '_tribe_rsvp_for_event';

	/**
	 * Get the importer object for EA.
	 *
	 * @since 1.0.0
	 *
	 * @param File_Importer|null $instance    The default instance that would be used for the type.
	 * @param File_Reader        $file_reader File reader object.
	 *
	 * @return Importer
	 */
	public function get_importer( $instance, File_Reader $file_reader ) {
		$importer = new Importer( $file_reader );
		$importer->set_integration( $this );

		return $importer;
	}

	/**
	 * Get import label, used for the post type label in EA Import Origin.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_import_label() {
		return __( 'RSVP Attendees', 'tribe-ext-tickets-attendee-csv-importer' );
	}

	/**
	 * Adds column names to the importer mapping options.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of column names.
	 */
	public function set_column_names() {
		return array_merge( parent::set_column_names(), [
			'going' => esc_html__( 'Going or Not Going', 'tribe-ext-tickets-attendee-csv-importer' ),
		] );
	}
}

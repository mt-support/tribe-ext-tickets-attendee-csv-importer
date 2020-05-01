<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\Providers\WooCommerce;

use Tribe\Extensions\Tickets\Attendee_CSV_Importer\Integration as Integration_Base;
use Tribe__Events__Importer__File_Importer as File_Importer;
use Tribe__Events__Importer__File_Reader as File_Reader;
use Tribe__Tickets__Tickets;

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
	public $type = 'tribe_wooticket';

	/**
	 * The ORM type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $orm_type = 'woo';

	/**
	 * The event meta key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $event_meta_key = '_tribe_wooticket_for_event';

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
		return __( 'Ticket Attendees (WooCommerce)', 'tribe-ext-tickets-attendee-csv-importer' );
	}

	/**
	 * Add Attendees to list of CSV post types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types List of CSV post types.
	 *
	 * @return array List of CSV post types.
	 */
	public function add_csv_post_type( $post_types ) {
		$active_modules = Tribe__Tickets__Tickets::modules();

		if ( empty( $active_modules['Tribe__Tickets_Plus__Commerce__WooCommerce__Main'] ) ) {
			return $post_types;
		}

		return parent::add_csv_post_type( $post_types );
	}
}

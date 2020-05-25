<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer\Providers\EDD;

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
	public $type = 'tribe_eddticket';

	/**
	 * The ORM type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $orm_type = 'edd';

	/**
	 * The event meta key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $event_meta_key = '_tribe_eddticket_for_event';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function hooks() {
		parent::hooks();

		if ( ! function_exists( 'EDD' ) || ! class_exists( 'Tribe__Tickets_Plus__Commerce__EDD__Main' ) ) {
			return;
		}

		// @todo ET+ registers EDD post type in init priority 1 but that causes a problem for us (not sure why).
		add_action( 'init', tribe_callback( 'tickets-plus.commerce.edd', 'register_eddtickets_type' ) );
	}

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
		return __( 'Ticket Attendees (Easy Digital Downloads)', 'tribe-ext-tickets-attendee-csv-importer' );
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
		if ( ! $this->is_active() ) {
			return $post_types;
		}

		return parent::add_csv_post_type( $post_types );
	}

	/**
	 * Check if the provider is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the provider is active.
	 */
	public function is_active() {
		$active_modules = Tribe__Tickets__Tickets::modules();

		return function_exists( 'EDD' ) && ! empty( $active_modules['Tribe__Tickets_Plus__Commerce__EDD__Main'] );
	}
}

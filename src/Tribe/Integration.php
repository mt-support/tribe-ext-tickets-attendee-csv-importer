<?php
/**
 * Handles all of the EA Importer integration.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer;

use Tribe__Events__Importer__File_Importer as File_Importer;
use Tribe__Events__Importer__File_Reader as File_Reader;
use Tribe__Template as Template;
use WP_Error;

/**
 * Class Integration
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
abstract class Integration {

	/**
	 * Option prefix used for column mapping.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const MAPPING_OPTION_PREFIX = 'tribe_events_import_column_mapping_';

	/**
	 * The integration type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The ORM type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $orm_type;

	/**
	 * The event meta key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $event_meta_key;

	/**
	 * The template object.
	 *
	 * @since 1.0.0
	 *
	 * @var Template
	 */
	protected $template;

	/**
	 * Handle adding all hooks needed for integration.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {
		// EA removes "tribe_" from the post types.
		$no_tribe = str_replace( 'tribe_', '', $this->type );

		// Importer handler.
		add_filter( "tribe_events_import_{$no_tribe}_importer", [ $this, 'get_importer' ], 10, 2 );
		add_action( 'tribe_aggregator_record_activity_wakeup', [ $this, 'register_post_type_activity' ] );

		// Import UI.
		add_filter( 'tribe_aggregator_csv_post_types', [ $this, 'add_csv_post_type' ], 11 );
		add_filter( 'tribe_events_import_options_rows', [ $this, 'add_import_option' ] );

		// Import record saving.
		add_filter( 'tribe_aggregator_import_submit_meta', [ $this, 'add_tab_meta' ] );
		add_filter( 'tribe_aggregator_import_validate_meta_by_origin', [ $this, 'validate_tab_meta' ], 10, 3 );
		add_action( 'tribe_events_aggregator_tabs_new_handle_import_finalize', [ $this, 'save_import_record' ], 10, 2 );
		add_action( 'tribe_events_aggregator_import_form', [ $this, 'render_options' ] );

		// Column mapping.
		add_filter( 'tribe_aggregator_csv_column_mapping', [ $this, 'set_columns' ] );
		add_filter( "tribe_event_import_{$no_tribe}_column_names", [ $this, 'set_column_names' ] );

		// @todo JS to hide.
		/**
		 * .tribe-default-settings
		 */
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
	abstract public function get_importer( $instance, File_Reader $file_reader );

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
		$post_type_obj = $this->get_post_type_object();

		if ( ! $post_type_obj ) {
			return $post_types;
		}

		$post_types[] = $post_type_obj;

		return $post_types;
	}

	/**
	 * Get the customized post type object.
	 *
	 * @since 1.0.0
	 *
	 * @return null|WP_Post_Type The post type or null if it does not exist.
	 */
	public function get_post_type_object() {
		$post_type_obj = get_post_type_object( $this->type );

		if ( ! $post_type_obj ) {
			return null;
		}

		$post_type_obj->labels->name = $this->get_import_label();

		return $post_type_obj;
	}

	/**
	 * Get import label, used for the post type label in EA Import Origin.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract public function get_import_label();

	/**
	 * Add import option to list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $import_options List of import options.
	 *
	 * @return array List of import options.
	 */
	public function add_import_option( array $import_options ) {
		// EA removes "tribe_" from the post types.
		$no_tribe = str_replace( 'tribe_', '', $this->type );

		$import_options[ $no_tribe ] = $this->get_import_label();

		return $import_options;
	}

	/**
	 * Adds attendee column mapping.
	 *
	 * @since 1.0.0
	 *
	 * @param array $csv_column_mapping CSV column mapping.
	 *
	 * @return array CSV column mapping.
	 */
	public function set_columns( $csv_column_mapping ) {
		// EA removes "tribe_" from the post types.
		$no_tribe = str_replace( 'tribe_', '', $this->type );

		$csv_column_mapping[ $no_tribe ] = get_option( self::MAPPING_OPTION_PREFIX . '-' . $no_tribe, [] );

		return $csv_column_mapping;
	}

	/**
	 * Adds column names to the importer mapping options.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of column names.
	 */
	public function set_column_names() {
		$column_names = [
			'event_name'     => esc_html__( 'Event Name or ID or Slug', 'tribe-ext-tickets-attendee-csv-importer' ),
			'ticket_name'    => esc_html__( 'Ticket Name or ID', 'tribe-ext-tickets-attendee-csv-importer' ),
			'attendee_name'  => esc_html__( 'Attendee Name', 'tribe-ext-tickets-attendee-csv-importer' ),
			'attendee_email' => esc_html__( 'Attendee Email', 'tribe-ext-tickets-attendee-csv-importer' ),
			'display_optin'  => esc_html__( 'Opt-in Display', 'tribe-ext-tickets-attendee-csv-importer' ),
			'user_id'        => esc_html__( 'User ID', 'tribe-ext-tickets-attendee-csv-importer' ),
			'order_id'       => esc_html__( 'Order ID', 'tribe-ext-tickets-attendee-csv-importer' ),
		];

		/**
		 * Allow plugins to filter the list of column names used.
		 *
		 * @since 1.0.0
		 *
		 * @param array $column_names List of column names.
		 */
		$column_names = (array) apply_filters( "tribe_ext_tickets_attendee_csv_importer_column_names_{$this->type}", $column_names );

		return $column_names;
	}

	/**
	 * Validate tab meta.
	 *
	 * @since 1.0.0
	 *
	 * @param array|WP_Error $result The updated/validated meta array or A `WP_Error` if the validation failed.
	 * @param string         $origin Origin name.
	 * @param array          $meta   Import meta.
	 *
	 * @return array|WP_Error The updated/validated meta array or A `WP_Error` if the validation failed.
	 */
	public function validate_tab_meta( $result, $origin, $meta ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 'csv' !== $origin ) {
			return $result;
		}

		if ( $this->type !== $meta['content_type'] ) {
			return $result;
		}

		if ( ! empty( $meta[ $this->type . '_event' ] ) && ! tribe_is_event( $meta[ $this->type . '_event' ] ) ) {
			return new WP_Error( 'invalid-event', __( 'Invalid ticket event selected.', 'tribe-ext-tickets-attendee-csv-importer' ) );
		}

		return $result;
	}

	/**
	 * Add tab meta to be used during submit.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meta Import meta.
	 *
	 * @return array Import meta.
	 */
	public function add_tab_meta( $meta ) {
		if ( 'csv' !== $meta['origin'] ) {
			return $meta;
		}

		if ( $this->type !== $meta['content_type'] ) {
			return $meta;
		}

		// @todo Update TEC filter to pass in $data too.

		$post_data = $_POST['aggregator'];

		$data = $post_data[ $meta['origin'] ];

		if ( ! empty( $data[ $this->type . '_event' ] ) ) {
			$meta[ $this->type . '_event' ] = (int) $data[ $this->type . '_event' ];
		}

		return $meta;
	}

	/**
	 * Handle saving the import record.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Events__Aggregator__Record__Abstract $record Import record.
	 * @param array                                        $data   List of import options.
	 */
	public function save_import_record( $record, $data ) {
		if ( 'csv' !== $data['origin'] ) {
			return;
		}

		if ( empty( $data['csv']['content_type'] ) || $this->type !== $data['csv']['content_type'] ) {
			return;
		}

		if ( empty( $data['csv'][ $this->type . '_event' ] ) ) {
			$record->delete_meta( $this->type . '_event' );

			return;
		}

		$record->update_meta( $this->type . '_event', (int) $data['csv'][ $this->type . '_event' ] );
	}

	/**
	 * Render the custom import options.
	 *
	 * @since 1.0.0
	 */
	public function render_options() {
		$help = __( 'Select an Event to use for importing attendees to. If an event is provided, it will be used instead of any event passed through the CSV.', 'tribe-ext-tickets-attendee-csv-importer' );

		$events_orm = tribe_events();

		// Only include events that have tickets.

		// @todo ET needs to extend and add another filter has_tickets_or_rsvp.

		/**
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		$events_orm->join_clause( "
			JOIN `{$wpdb->postmeta}` `has_tickets_or_rsvp_ticket_event`
				ON `has_tickets_or_rsvp_ticket_event`.`meta_value` = `{$wpdb->posts}`.`ID`
		" );

		// @todo ET needs to check specific meta keys, it's not right now and that could return unexpected values.
		$events_orm->where_clause( $wpdb->prepare( '
			`has_tickets_or_rsvp_ticket_event`.`meta_key` = %s
		', $this->event_meta_key ) );

		// Show the most recent events.
		$events_orm->order( 'date' );
		$events_orm->order_by( 'desc' );

		// Show only 100 events.
		$events_orm->per_page( 100 );

		// Get list of events in ID => Title array format.
		$events = $events_orm->all();
		$events = wp_list_pluck( $events, 'post_title', 'ID' );

		$this->get_template()->template( 'import-options', [
			'help'              => $help,
			'events'            => $events,
			'selected_event_id' => 0,
			'provider'          => $this->type,
		] );
	}

	/**
	 * Get the template object, set it up if it's not yet setup.
	 *
	 * @since 1.0.0
	 *
	 * @return Template The Template object.
	 */
	protected function get_template() {
		if ( $this->template ) {
			return $this->template;
		}

		$this->template = new Template();
		$this->template->set_template_origin( tribe( Main::class ) );
		$this->template->set_template_folder( 'src/admin-views' );

		// Configure this templating class to extra variables.
		$this->template->set_template_context_extract( true );

		return $this->template;
	}

	/**
	 * Registers the post types for tracking activity.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Events__Aggregator__Record__Activity $activity Activity object.
	 */
	public function register_post_type_activity( $activity ) {
		// EA removes "tribe_" from the post types.
		$no_tribe = str_replace( 'tribe_', '', $this->type );

		$activity->register( $this->type, [ $no_tribe ] );
	}

}

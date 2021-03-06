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
use Tribe\Extensions\Tickets\Attendee_CSV_Importer\API\Attendee;
use Tribe\Extensions\Tickets\Attendee_CSV_Importer\API\Order;
use Tribe__Events__Importer__File_Importer as File_Importer;
use Tribe__Tickets__Main;
use Tribe__Tickets__Tickets;

/**
 * Class Importer
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
abstract class Importer extends File_Importer {

	use Attendee;
	use Order;

	/**
	 * Event name cache.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $event_name_cache = [];
	/**
	 * Ticket name cache.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $ticket_name_cache = [];
	/**
	 * Required CSV fields.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $required_fields = [
		'ticket_name',
		'attendee_name',
		'attendee_email',
	];
	/**
	 * Validation row message.
	 *
	 * @since 1.0.0
	 *
	 * @var bool|string
	 */
	protected $row_message = false;

	/**
	 * The integration object.
	 *
	 * @since 1.0.0
	 *
	 * @var Integration
	 */
	protected $integration;

	/**
	 * Resets the static caches.
	 *
	 * @since 1.0.0
	 */
	public static function reset_cache() {
		self::$event_name_cache  = [];
		self::$ticket_name_cache = [];
	}

	/**
	 * Set integration for importer.
	 *
	 * @since 1.0.0
	 *
	 * @param Integration $integration
	 */
	public function set_integration( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Determine if the record matches an existing post.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return bool Whether the record matches an existing post.
	 */
	public function match_existing_post( array $record ) {
		// No support for checking for updated attendees for now.
		return false;
	}

	/**
	 * Update post for record.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id Post ID.
	 * @param array $record  Import record.
	 */
	public function update_post( $post_id, array $record ) {
		// Nothing is updated in existing tickets.
		if ( $this->is_aggregator && ! empty( $this->aggregator_record ) ) {
			$this->aggregator_record->meta['activity']->add( $this->integration->type, 'skipped', $post_id );
		}
	}

	/**
	 * Create post for record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return int|bool The new post ID or false if not created.
	 */
	public function create_post( array $record ) {
		$event = $this->get_event_from( $record );

		if ( empty( $event ) ) {
			return false;
		}

		$ticket = $this->get_ticket_from( $record, $event );

		if ( empty( $ticket ) ) {
			return false;
		}

		$attendee_data = $this->get_attendee_data_from( $record );

		/**
		 * Allow plugins to filter the attendee data used for creation.
		 *
		 * @since 1.0.0
		 *
		 * @param array $attendee_data Attendee data.
		 */
		$attendee_data = (array) apply_filters( "tribe_ext_tickets_attendee_csv_importer_data_{$this->integration->type}", $attendee_data );

		try {
			$attendee_data = $this->map_csv_data_to_attendee( $attendee_data );

			$attendee_id = $this->create_attendee_for_ticket( $ticket, $attendee_data );
		} catch ( Exception $exception ) {
			return false;
		}

		if ( $this->is_aggregator && ! empty( $this->aggregator_record ) ) {
			$this->aggregator_record->meta['activity']->add( $this->integration->type, 'created', $attendee_id );
		}

		return $attendee_id;
	}

	/**
	 * Get event from record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return bool|\WP_Post Event post object or false if not found.
	 */
	protected function get_event_from( array $record ) {
		$event_name = $this->get_value_by_key( $record, 'event_name' );

		if ( ! empty( $this->aggregator_record->meta[ $this->integration->type . '_event' ] ) ) {
			$event_name = (int) $this->aggregator_record->meta[ $this->integration->type . '_event' ];
		} elseif ( empty( $event_name ) ) {
			return false;
		}

		if ( isset( self::$event_name_cache[ $event_name ] ) ) {
			return self::$event_name_cache[ $event_name ];
		}

		try {
			/** @var Post_Repository $posts_orm */
			$posts_orm = tribe( 'ext.tickets.attendee-csv-importer.repository.post' );

			$post_types = Tribe__Tickets__Main::instance()->post_types();

			$posts_orm->where( 'post_type', $post_types );
			$posts_orm->where( 'post_status', [
				'publish',
				'private',
				'draft',
			] );
			$posts_orm->where_multi( [
				'ID',
				'post_title',
				'post_name',
			], '=', $event_name );
			$posts_orm->per_page( 1 );

			$event = $posts_orm->first();

			if ( empty( $event ) ) {
				$event = false;
			}
		} catch ( Exception $exception ) {
			$event = false;
		}

		self::$event_name_cache[ $event_name ] = $event;

		return $event;
	}

	/**
	 * Get ticket from record and event.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $record Import record.
	 * @param \WP_Post $event  Event post object.
	 *
	 * @return bool|\Tribe__Tickets__Ticket_Object Ticket object or false if not found.
	 */
	protected function get_ticket_from( array $record, $event ) {
		$ticket_name = $this->get_value_by_key( $record, 'ticket_name' );

		if ( empty( $ticket_name ) ) {
			return false;
		}

		$cache_key = $event->ID . '-' . $ticket_name;

		if ( isset( self::$ticket_name_cache[ $cache_key ] ) ) {
			return self::$ticket_name_cache[ $cache_key ];
		}

		try {
			$tickets_orm = tribe_tickets( $this->integration->orm_type );

			$tickets_orm->by( 'event', $event->ID );
			$tickets_orm->where_multi( [
				'ID',
				'post_title',
			], '=', $ticket_name );
			$tickets_orm->per_page( 1 );

			$ticket_post = $tickets_orm->first();

			$ticket = false;

			if ( ! empty( $ticket_post ) ) {
				$ticket = Tribe__Tickets__Tickets::load_ticket_object( $ticket_post->ID );

				if ( ! $ticket ) {
					$ticket = false;
				}
			}
		} catch ( Exception $exception ) {
			$ticket = false;
		}

		self::$ticket_name_cache[ $cache_key ] = $ticket;

		return $ticket;
	}

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
			'display_opt_in' => $this->get_value_by_key( $record, 'display_opt_in' ),
			'user_id'        => (int) $this->get_value_by_key( $record, 'user_id' ),
			'order_id'       => $this->get_value_by_key( $record, 'order_id' ),
			'send_email'     => $this->will_send_email( $record ),
		];

		if ( '' === $data['order_id'] ) {
			// Set default.
			$data['order_id'] = null;
		}

		if ( '' === $data['display_opt_in'] ) {
			// Set default.
			$data['display_opt_in'] = false;
		} else {
			// Enforce boolean.
			$data['display_opt_in'] = tribe_is_truthy( $data['display_opt_in'] );
		}

		return $data;
	}

	/**
	 * Map an attendee from CSV fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attendee_data CSV attendee data.
	 *
	 * @return array Attendee data.
	 */
	public function map_csv_data_to_attendee( $attendee_data ) {
		return [
			'full_name'  => $attendee_data['attendee_name'],
			'email'      => $attendee_data['attendee_email'],
			'optout'     => ! tribe_is_truthy( $attendee_data['display_opt_in'] ),
			'user_id'    => $attendee_data['user_id'],
			'order_id'   => $attendee_data['order_id'],
			'send_email' => $attendee_data['send_email'],
		];
	}

	/**
	 * Create an attendee for a ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket        Ticket object.
	 * @param array                          $attendee_data Attendee data.
	 *
	 * @return int Attendee ID.
	 *
	 * @throws \Exception
	 */
	abstract protected function create_attendee_for_ticket( $ticket, $attendee_data );

	/**
	 * Determine whether to send emails for the imported attendees.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return bool Whether to send emails for the imported attendees.
	 */
	public function will_send_email( array $record = [] ) {
		static $send_email;

		if ( null !== $send_email ) {
			return $send_email;
		}

		$send_email = $this->get_value_by_key( $record, 'send_email' );

		if ( '' === $send_email && isset( $this->aggregator_record->meta[ $this->integration->type . '_send_email' ] ) ) {
			$send_email = tribe_is_truthy( $this->aggregator_record->meta[ $this->integration->type . '_send_email' ] );
		}

		return tribe_is_truthy( $send_email );
	}

	/**
	 * Determine if the record is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record Import record.
	 *
	 * @return bool Whether the record is valid.
	 */
	public function is_valid_record( array $record ) {
		$valid = parent::is_valid_record( $record );

		if ( ! $valid ) {
			return $valid;
		}

		$event = $this->get_event_from( $record );

		if ( empty( $event ) ) {
			$this->row_message = esc_html__( 'An event is required to import attendees.', 'tribe-ext-tickets-attendee-csv-importer' );

			return false;
		}

		$ticket = $this->get_ticket_from( $record, $event );

		if ( empty( $ticket ) ) {
			return false;
		}

		$provider = $ticket->get_provider();

		if ( null === $provider || $this->integration->type !== $provider->attendee_object ) {
			return false;
		}

		if ( function_exists( 'tribe_is_recurring_event' ) ) {
			$is_recurring = tribe_is_recurring_event( $event->ID );

			if ( $is_recurring ) {
				$this->row_message = sprintf( esc_html__( 'Recurring event tickets are not supported, event %s.', 'tribe-ext-tickets-attendee-csv-importer' ), $event->post_title );

				return false;
			}
		}

		$this->row_message = false;

		return true;
	}

	/**
	 * Get skipped row message if it is overridden or use the normal one.
	 *
	 * @since 1.0.0
	 *
	 * @param array $row Row data.
	 *
	 * @return string Skipped row message.
	 */
	protected function get_skipped_row_message( $row ) {
		return $this->row_message === false ? parent::get_skipped_row_message( $row ) : $this->row_message;
	}
}

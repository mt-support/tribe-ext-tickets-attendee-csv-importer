<?php
/**
 * The main service provider for Integration support
 *
 * @since   1.0.0
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer;

use tad_DI52_ServiceProvider;

/**
 * Class Service_Provider
 *
 * @since   1.0.0
 * @package Tribe\Extensions\Tickets\Attendee_CSV_Importer
 */
class Service_Provider extends tad_DI52_ServiceProvider {

	/**
	 * Binds and sets up implementations.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Register the provider on the container.
		$this->container->singleton( 'ext.tickets.attendee-csv-importer', $this );
		$this->container->singleton( static::class, $this );

		// Register the post repository on the container.
		$this->container->bind( 'ext.tickets.attendee-csv-importer.repository.post', Post_Repository::class );
		$this->container->bind( Post_Repository::class, Post_Repository::class );

		// Register RSVP integration.
		$rsvp_integration = new Providers\RSVP\Integration( $this->container );
		$this->container->singleton( Providers\RSVP\Integration::class, $rsvp_integration );
		$this->container->singleton( 'ext.tickets.attendee-csv-importer.providers.rsvp.integration', $rsvp_integration );
		$rsvp_integration->hooks();

		// Register PayPal integration if PayPal is available.
		$paypal_integration = new Providers\PayPal\Integration( $this->container );
		$this->container->singleton( Providers\PayPal\Integration::class, $paypal_integration );
		$this->container->singleton( 'ext.tickets.attendee-csv-importer.providers.paypal.integration', $paypal_integration );
		$paypal_integration->hooks();

		// Register EDD integration if EDD is available.
		$edd_integration = new Providers\EDD\Integration( $this->container );
		$this->container->singleton( Providers\EDD\Integration::class, $edd_integration );
		$this->container->singleton( 'ext.tickets.attendee-csv-importer.providers.edd.integration', $edd_integration );
		$edd_integration->hooks();

		// Register WooCommerce integration if WooCommerce is ready.
		$woo_integration = new Providers\WooCommerce\Integration( $this->container );
		$this->container->singleton( Providers\WooCommerce\Integration::class, $woo_integration );
		$this->container->singleton( 'ext.tickets.attendee-csv-importer.providers.woo.integration', $woo_integration );
		$woo_integration->hooks();
	}
}

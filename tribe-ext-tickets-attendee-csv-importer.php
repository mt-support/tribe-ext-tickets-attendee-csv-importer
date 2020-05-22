<?php
/**
 * Plugin Name:       Event Tickets Extension: Attendee CSV Importer
 * Plugin URI:        https://theeventscalendar.com/extensions/event-tickets-attendee-csv-importer/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-tickets-attendee-csv-importer
 * Description:       Enable importing of Attendees from CSV to Event Tickets.
 * Version:           1.0.0
 * Extension Class:   Tribe\Extensions\Tickets\Attendee_CSV_Importer\Main
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-tickets-attendee-csv-importer
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

namespace Tribe\Extensions\Tickets\Attendee_CSV_Importer;

use Tribe__Autoloader;
use Tribe__Extension;

/**
 * Define Constants
 */

if ( ! defined( __NAMESPACE__ . '\NS' ) ) {
	define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );
}

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if ( class_exists( 'Tribe__Extension' ) && ! class_exists( Main::class ) ) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Main extends Tribe__Extension {

		/**
		 * Version string.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * File path.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		const PATH = __DIR__;

		/**
		 * Class loader.
		 *
		 * @since 1.0.0
		 *
		 * @var Tribe__Autoloader
		 */
		private $class_loader;

		/**
		 * Plugin Directory.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $plugin_dir;

		/**
		 * Plugin path.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $plugin_path;

		/**
		 * Plugin URL.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $plugin_url;

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			$this->plugin_dir  = trailingslashit( basename( self::PATH ) );
			$this->plugin_path = trailingslashit( self::PATH );
			$this->plugin_url  = plugins_url( $this->plugin_dir, self::PATH );

			/**
			 * Examples:
			 * All these version numbers are the ones on or after November 16, 2016, but you could remove the version
			 * number, as it's an optional parameter. Know that your extension code will not run at all (we won't even
			 * get this far) if you are not running The Events Calendar 4.3.3+ or Event Tickets 4.3.3+, as that is where
			 * the Tribe__Extension class exists, which is what we are extending.
			 *
			 * If using `tribe()`, such as with `Tribe__Dependency`, require TEC/ET version 4.4+ (January 9, 2017).
			 */
			$this->add_required_plugin( 'Tribe__Tickets__Main', '4.12' );
			$this->add_required_plugin( 'Tribe__Tickets_Plus__Main', '4.12' );
			$this->add_required_plugin( 'Tribe__Events__Main', '5.1' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			load_plugin_textdomain( 'tribe-ext-tickets-attendee-csv-importer', false, basename( __DIR__ ) . '/languages/' );

			if ( ! $this->php_version_check() ) {
				return;
			}

			$this->class_loader();

			tribe_singleton( static::class, $this );
			tribe_register_provider( Service_Provider::class );
		}

		/**
		 * Check if we have a sufficient version of PHP. Admin notice if we don't and user should see it.
		 *
		 * @link https://theeventscalendar.com/knowledgebase/php-version-requirement-changes/ All extensions require PHP 5.6+.
		 *
		 * Delete this paragraph and the non-applicable comments below.
		 * Make sure to match the readme.txt header.
		 *
		 * Note that older version syntax errors may still throw fatal errors even
		 * if you implement this PHP version checking so QA it at least once.
		 *
		 * @link https://secure.php.net/manual/en/migration56.new-features.php
		 * 5.6: Variadic Functions, Argument Unpacking, and Constant Expressions
		 *
		 * @link https://secure.php.net/manual/en/migration70.new-features.php
		 * 7.0: Return Types, Scalar Type Hints, Spaceship Operator, Constant Arrays Using define(), Anonymous Classes, intdiv(), and preg_replace_callback_array()
		 *
		 * @link https://secure.php.net/manual/en/migration71.new-features.php
		 * 7.1: Class Constant Visibility, Nullable Types, Multiple Exceptions per Catch Block, `iterable` Pseudo-Type, and Negative String Offsets
		 *
		 * @link https://secure.php.net/manual/en/migration72.new-features.php
		 * 7.2: `object` Parameter and Covariant Return Typing, Abstract Function Override, and Allow Trailing Comma for Grouped Namespaces
		 *
		 * @return bool
		 */
		private function php_version_check() {
			$php_required_version = '5.6';

			if ( version_compare( PHP_VERSION, $php_required_version, '<' ) ) {
				if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
					$message = '<p>';

					$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', 'tribe-ext-tickets-attendee-csv-importer' ), $this->get_name(), $php_required_version );

					$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );

					$message .= '</p>';

					tribe_notice( 'tribe-ext-tickets-attendee-csv-importer-php-version', $message, [ 'type' => 'error' ] );
				}

				return false;
			}

			return true;
		}

		/**
		 * Use Tribe Autoloader for all class files within this namespace in the 'src' directory.
		 *
		 * @return Tribe__Autoloader
		 */
		public function class_loader() {
			if ( empty( $this->class_loader ) ) {
				$this->class_loader = new Tribe__Autoloader;
				$this->class_loader->set_dir_separator( '\\' );
				$this->class_loader->register_prefix( NS, __DIR__ . DIRECTORY_SEPARATOR . 'src/Tribe' );
			}

			$this->class_loader->register_autoloader();

			return $this->class_loader;
		}

	} // end class
} // end if class_exists check

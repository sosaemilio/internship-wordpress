<?php

/**
 * A representation of a WordPress admin notice.
 */

namespace Nexcess\MAPPS\Support;

use Nexcess\MAPPS\Exceptions\ImmutableValueException;
use Nexcess\MAPPS\Integrations\Admin;

/**
 * @property-read bool        $alt            Whether or not to include the "notice-alt" class for
 *                                            alternative coloring.
 * @property-read string|null $capability     The capability check assigned to the notice.
 * @property-read string      $id             A unique ID for this notice.
 * @property-read bool        $inline         Whether or not the notice should render inline.
 * @property-read bool        $is_dismissible Whether or not the notice should be dismissible.
 * @property-read bool        $is_persistent  Whether or not the notice should persist across
 *                                            multiple page loads.
 * @property-read string      $message        The contents of the notice.
 * @property-read string      $type           The notice type.
 */
class AdminNotice {

	/**
	 * @var bool Whether or not to use the alternate coloring for the type.
	 */
	protected $alt = false;

	/**
	 * @var string A capability check that must pass in order to render this notice.
	 */
	protected $capability;

	/**
	 * @var string A unique ID for this notice.
	 */
	protected $id;

	/**
	 * @var bool Whether or not the notice should be treated as being inline (e.g. not moved to the
	 *           top of the page).
	 */
	protected $inline = false;

	/**
	 * @var bool Whether or not the notice should be dismissible.
	 */
	protected $is_dismissible;

	/**
	 * @var bool Whether or not to save the dismissal of the notice. Really only useful for
	 *           a notice that shows after an action, like a settings save.
	 */
	protected $save_dismissal = true;

	/**
	 * @var bool Whether or not this notice has been persisted in cache.
	 */
	protected $is_persistent = false;

	/**
	 * @var bool Whether or not this notice should be delayed.
	 */
	protected $is_delayed = false;

	/**
	 * @var string The type of delay, either 'user' or 'site'.
	 */
	protected $delayed_type = 'user';

	/**
	 * @var int Amount of time to delay the delayed notice.
	 */
	protected $delayed_time = 0;

	/**
	 * @var string The contents of the notice.
	 */
	protected $message;

	/**
	 * @var string The notice type.
	 */
	protected $type;

	/**
	 * Transient that holds persisted notices.
	 */
	const PERSISTENT_NOTICES_CACHE_KEY = 'nexcess_mapps_admin_notices';

	/**
	 * User meta key for dismissed notifications.
	 */
	const USER_META_DISMISSED_NOTICES = '_nexcess_mapps_dismissed_notices';

	/**
	 * Key for delayed notifications, used either as user meta or site option.
	 */
	const DELAYED_NOTICES_KEY = '_nexcess_mapps_delayed_notices';

	/**
	 * Create a new notification instance.
	 *
	 * @param string $message        The contents of the notice.
	 * @param string $type           Optional. The notice type, one of "success", "error",
	 *                               "warning", or "info". Default is "info.".
	 * @param bool   $is_dismissible Optional. Whether or not the notice should be marked as
	 *                               dismissible. Default is true.
	 * @param string $id             Optional. A unique ID for the notification, which is used for
	 *                               tracking dismissed notifications. Default is a hash of $message.
	 *
	 * @throws \DomainException If the given $type is not in $valid_types.
	 */
	public function __construct( $message, $type = 'info', $is_dismissible = true, $id = '' ) {
		$valid_types = $this->getValidTypes();

		if ( ! in_array( $type, $valid_types, true ) ) {
			throw new \DomainException( sprintf(
				/* Translators: %1$s is the passed type, %2$s is an imploded list of permitted values. */
				__( 'Type "%1$s" is not defined, and must be one of: %2$s.', 'nexcess-mapps' ),
				$type,
				implode( ', ', $valid_types )
			) );
		}

		$this->message        = $message;
		$this->type           = $type;
		$this->is_dismissible = $is_dismissible;
		$this->id             = ! empty( $id ) ? $id : substr( md5( $type . ':' . $message ), 0, 10 );
	}

	/**
	 * Enable protected properties to be accessed easily.
	 *
	 * @param string $prop The property to retrieve.
	 *
	 * @return string|bool|null Either the string/bool value of the property, or null if the
	 *                          property is undefined.
	 */
	public function __get( $prop ) {
		return isset( $this->{$prop} ) ? $this->{$prop} : null;
	}

	/**
	 * AdminNotices should be treated as immutable.
	 *
	 * @param string $prop  The property name.
	 * @param mixed  $value The value that is being assigned.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\ImmutableValueException If the object is immutable.
	 */
	public function __set( $prop, $value ) {
		throw new ImmutableValueException( sprintf(
			/* Translators: %1$s is the current class name. */
			__( 'The %1$s object is immutable.', 'nexcess-mapps' ),
			__CLASS__
		) );
	}

	/**
	 * Automatically render the notice if it's cast to a string.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}

	/**
	 * Get the array of valid notice types.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/admin_notices/
	 *
	 * @return string[] Valid admin notice types.
	 */
	public function getValidTypes() {
		return [
			'error',
			'info',
			'success',
			'warning',
		];
	}

	/**
	 * Remove a persistent notice.
	 *
	 * @return self
	 */
	public function forget() {
		if ( $this->is_persistent ) {
			$notices = (array) ( get_transient( self::PERSISTENT_NOTICES_CACHE_KEY ) ?: [] );
			unset( $notices[ $this->id ] );

			// Update the transient, or remove it if it's empty.
			if ( empty( $notices ) ) {
				delete_transient( self::PERSISTENT_NOTICES_CACHE_KEY );
			} else {
				set_transient( self::PERSISTENT_NOTICES_CACHE_KEY, $notices, 0 );
			}
		}

		return $this;
	}

	/**
	 * Print the rendered notice to the screen.
	 */
	public function output() {
		echo wp_kses_post( $this->render() );
	}

	/**
	 * Persist the notice as a transient.
	 *
	 * This enables a notice to persist across multiple page loads and redirections.
	 *
	 * Note that non-dismissible notices will only be displayed once. If the message should be
	 * shown on multiple page loads, $this->is_dismissible should be true.
	 *
	 * @return self
	 */
	public function persist() {
		$this->is_persistent  = true;
		$notices              = (array) ( get_transient( self::PERSISTENT_NOTICES_CACHE_KEY ) ?: [] );
		$notices[ $this->id ] = $this;
		set_transient( self::PERSISTENT_NOTICES_CACHE_KEY, $notices, 0 );

		return $this;
	}

	/**
	 * Set whether or not to use alternative coloring.
	 *
	 * @param bool $alt True if alternative colors should be used, false otherwise.
	 *
	 * @return self
	 */
	public function setAlt( $alt ) {
		$this->alt = (bool) $alt;

		return $this;
	}

	/**
	 * Set the required capability in order to render this notice.
	 *
	 * @param string $cap The capability check.
	 *
	 * @return self
	 */
	public function setCapability( $cap ) {
		$this->capability = (string) $cap;

		return $this;
	}

	/**
	 * Set the value of $inline.
	 *
	 * @param bool $inline Whether or not the notice should be rendered inline.
	 *
	 * @return self
	 */
	public function setInline( $inline ) {
		$this->inline = (bool) $inline;

		return $this;
	}

	/**
	 * Set the value of $save_dismissal.
	 *
	 * @param bool $save Whether or not to save the dismissal in the user's meta.
	 *
	 * @return self
	 */
	public function setSaveDismissal( $save ) {
		$this->save_dismissal = $save;

		return $this;
	}

	/**
	 * Set the value of $is_delayed.
	 *
	 * @param bool $is_delayed Whether or not the notice should be delayed.
	 *
	 * @return self
	 */
	public function setDelayed( $is_delayed = true ) {
		$this->is_delayed = (bool) $is_delayed;

		return $this;
	}

	/**
	 * Set the delayed time for a notice.
	 *
	 * @param int $time The time in seconds to delay the notice.
	 *
	 * @return self
	 */
	public function setDelayedFor( $time ) {
		$this->delayed_time = (int) $time;

		return $this;
	}

	/**
	 * Set the type of delay for a notice.
	 *
	 * @param string $type The type of delay, either 'user' or 'site'. Defaults to 'user'.
	 *
	 * @return self
	 */
	public function setDelayedType( $type = 'user' ) {
		$this->delayed_type = $type;

		return $this;
	}

	/**
	 * Set a notice as delayed. Wrapper around setDelayed, setDelayedFor, and setDelayedType.
	 *
	 * @param int    $time The time in seconds to delay the notice.
	 * @param string $type The type of delay, either 'user' or 'site'. Defaults to 'user'.
	 *
	 * @return self
	 */
	public function delay( $time, $type = 'user' ) {
		$this->setDelayed( true )
			->setDelayedFor( $time )
			->setDelayedType( $type );

		return $this;
	}

	/**
	 * Generate the markup for the notification.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! empty( $this->capability ) && ! current_user_can( $this->capability ) ) {
			return '';
		}

		// Ensure our admin scripts are available.
		if ( $this->is_dismissible ) {
			wp_enqueue_script( 'nexcess-mapps-admin' );
		}

		// Assemble a list of classes.
		$classes = [
			'notice',
			"notice-{$this->type}",
			'mapps-notice',
		];

		if ( $this->alt ) {
			$classes[] = 'notice-alt';
		}

		if ( $this->inline ) {
			$classes[] = 'inline';
		}

		if ( $this->is_dismissible ) {
			$classes[] = 'is-dismissible';
		}

		return sprintf(
			'<div class="%1$s" data-id="%2$s" data-nonce="%3$s">%4$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$this->id,
			wp_create_nonce( Admin::HOOK_DISMISSED_NOTICE ),
			wpautop( $this->message )
		);
	}

	/**
	 * Check to see if a notice is delayed and whether or not the delay has expired.
	 *
	 * @return bool Whether or not the notice is delayed.
	 */
	public function noticeIsDelayed() {
		// If the notice isn't set as delayed, then it's not delayed.
		if ( ! $this->is_delayed ) {
			return false;
		}

		// Get the user meta of what notices are currently delayed.
		$delayed_notices = $this->getDelayedNotices();

		// If the notice is not set as delayed, then it's not delayed, so we want to save it as delayed.
		if ( empty( $delayed_notices[ $this->id ] ) ) {
			return $this->setDelayedNotice();
		}

		// If the notice is delayed, but the delay has expired, then it's not delayed.
		$delay_ends = $delayed_notices[ $this->id ] + $this->delayed_time;

		return $delay_ends > time();
	}

	/**
	 * Get the notice meta data from either user meta or site options.
	 *
	 * @return array The notice meta data.
	 */
	public function getDelayedNotices() {
		if ( 'user' === $this->delayed_type ) {
			$notices = get_user_meta( get_current_user_id(), self::DELAYED_NOTICES_KEY, true );
		} elseif ( 'site' === $this->delayed_type ) {
			$notices = get_site_option( self::DELAYED_NOTICES_KEY, [] );
		} else {
			$notices = [];
		}

		return (array) $notices;
	}

	/**
	 * Set the notice meta data.
	 *
	 * @return bool Whether or not the notice meta was set.
	 */
	public function setDelayedNotice() {
		$notices = $this->getDelayedNotices();

		$notices[ $this->id ] = time();

		if ( 'user' === $this->delayed_type ) {
			return (bool) update_user_meta( get_current_user_id(), self::DELAYED_NOTICES_KEY, $notices );
		}

		if ( 'site' === $this->delayed_type ) {
			return update_site_option( self::DELAYED_NOTICES_KEY, $notices );
		}

		return false;
	}

	/**
	 * Determine whether or not a particular notice should be shown based on the user's previously-
	 * dismissed notices.
	 *
	 * @return bool True if the user has dismissed the notice before or false if the user has
	 *              either not dismissed it or the notice is not dismissible.
	 */
	public function userHasDismissedNotice() {
		if ( ! $this->is_dismissible || ! $this->save_dismissal ) {
			return false;
		}

		return self::noticeWasDismissed( get_current_user_id(), $this->id );
	}

	/**
	 * Retrieve any persistent admin notices.
	 *
	 * @return \Nexcess\MAPPS\Support\AdminNotice[]
	 */
	public static function getPersistentNotices() {
		return array_filter( (array) get_transient( self::PERSISTENT_NOTICES_CACHE_KEY ) ?: [], function ( $notice ) {
			return $notice instanceof self;
		} );
	}

	/**
	 * Forget a persistent admin notice by ID.
	 *
	 * @param string $id The notice ID.
	 *
	 * @return bool True if the notice was deleted, false otherwise.
	 */
	public static function forgetPersistentNotice( $id ) {
		$notices = get_transient( self::PERSISTENT_NOTICES_CACHE_KEY ) ?: [];

		if ( ! isset( $notices[ $id ] ) ) {
			return false;
		}

		unset( $notices[ $id ] );

		return set_transient( self::PERSISTENT_NOTICES_CACHE_KEY, $notices );
	}

	/**
	 * Add a notice to the list of dismissed notices for a user if it has not already been dismissed.
	 *
	 * @param int    $user_id   The ID of the WordPress user to check.
	 * @param string $notice_id The ID of the notice to check for dismissal.
	 *
	 * @return int|bool The new meta key ID, true on successful update, false on failure.
	 */
	public static function dismissNotice( $user_id, $notice_id ) {
		if ( self::noticeWasDismissed( $user_id, $notice_id ) ) {
			return true;
		}

		// Track the dismissed notices in user meta.
		$dismissed = get_user_meta( $user_id, self::USER_META_DISMISSED_NOTICES, true ) ?: [];

		// Add the new notice.
		$dismissed[ $notice_id ] = time();

		return update_user_meta( $user_id, self::USER_META_DISMISSED_NOTICES, $dismissed );
	}

	/**
	 * Determine whether or not a particular notice should be shown based on the notice ID and the user's previously-
	 * dismissed notices.
	 *
	 * @param int    $user_id   The ID of the WordPress user to check.
	 * @param string $notice_id The ID of the notice to check for dismissal.
	 *
	 * @return bool True if the user has dismissed the notice before or false if the user has not dismissed it.
	 */
	public static function noticeWasDismissed( $user_id, $notice_id ) {
		$dismissed = (array) get_user_meta( $user_id, self::USER_META_DISMISSED_NOTICES, true ) ?: [];

		return isset( $dismissed[ $notice_id ] );
	}
}

<?php
/**
 * Helpful Composer scripts used by required.
 *
 * @package ComposerScripts
 */

namespace Required\ComposerScripts;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;
use DateTime;
use ErrorException;

class PluginAvailability {
	/**
	 * Determines the availability of WordPress plugins being installed or updated.
	 *
	 * If a plugin is not available in the WordPress Plugin Directory, it means that
	 * it has been temporarily or permanently removed because guideline violations,
	 * abandonce by its developer, or even security issues.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public static function checkAvailability( PackageEvent $event ) {
		$package = self::getPackage( $event );

		if ( self::isWordPressPlugin( $package ) && ! self::isPluginAvailable( self::getPluginURL( $package ) ) ) {
			$event->getIO()->writeError( sprintf(
				'<warning>The plugin %s does not seem to be available in the WordPress Plugin Directory anymore',
				$package->getName()
			) );
		}
	}

	/**
	 * Determines the maintenance status of WordPress plugins being installed or updated.
	 *
	 * Plugins that haven't been updated in a while should be used with caution, as it means
	 * they might not be compatible with the latest versions of WordPress.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public static function checkMaintenanceStatus( PackageEvent $event ) {
		$package = self::getPackage( $event );

		if ( self::isWordPressPlugin( $package ) && ! self::isPluginActivelyMaintained( self::getPluginURL( $package ) ) ) {
			$event->getIO()->writeError( sprintf(
				'<warning>The plugin %s has not been updated in over two years. Please double-check before using it.',
				$package->getName()
			) );
		}
	}

	/**
	 * Returns the package referenced by the current event.
	 *
	 * @param PackageEvent $event The current event.
	 *
	 * @return PackageInterface The current package.
	 */
	protected static function getPackage( PackageEvent $event ) {
		/* @var PackageInterface $package */
		return $event->getOperation() instanceof InstallOperation ? $event->getOperation()->getPackage() : $event->getOperation()->getTargetPackage();
	}

	/**
	 * Determines whether a package is from WPackagist.
	 *
	 * WPackagist is a mirror of the WordPress Plugin Directory.
	 *
	 * @param PackageInterface $package The current package.
	 *
	 * @return bool True if package is a WordPress plugin, false otherwise.
	 */
	protected static function isWordPressPlugin( PackageInterface $package ) {
		return 'wordpress-plugin' === $package->getType() && 0 === strpos( $package->getName(), 'wpackagist-plugin/' );
	}

	/**
	 * Returns the URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @param PackageInterface $package The current package.
	 *
	 * @return string The plugin URL.
	 */
	protected static function getPluginURL( PackageInterface $package ) {
		return 'https://api.wordpress.org/plugins/info/1.0/' . str_replace( 'wpackagist-plugin/', '', $package->getName() ) . '.json';
	}

	/**
	 * Determines whether a plugin is available in the WordPress Plugin Directory.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin is available, false if otherwise (404 status).
	 */
	protected static function isPluginAvailable( $url ) {
		try {
			$result = file_get_contents( $url, false );

			$result = json_decode( $result, true );

			return null !== $result;
		} catch( ErrorException $e ) {
			return false;
		}
	}

	/**
	 * Determines whether a plugin is actively maintained or not.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin has been updated in the last two years, false otherwise.
	 */
	protected static function isPluginActivelyMaintained( $url ) {
		try {
			$result = file_get_contents( $url, false );

			$result = json_decode( $result, true );

			if ( null === $result ) {
				return false;
			}

			$now          = new DateTime();
			$last_updated = new DateTime( $result['last_updated'] );

			return $last_updated < $now->modify( '-2 years' );
		} catch ( ErrorException $e ) {
			return false;
		}
	}
}
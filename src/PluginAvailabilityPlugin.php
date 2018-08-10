<?php

namespace Required\ComposerScripts;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use DateTime;
use ErrorException;
use Required\ComposerScripts\WordPressPluginHelper;

class PluginAvailabilityPlugin implements PluginInterface, EventSubscriberInterface {
	/** @var Composer */
	private $composer;

	/** @var IOInterface */
	private $io;

	/** @var WordPressPluginHelper */
	private $helper;

	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
		$this->helper   = new WordPressPluginHelper();
	}

	public static function getSubscribedEvents() {
		return [
			PackageEvents::PRE_PACKAGE_INSTALL => [
				[ 'checkAvailability' ],
			],
			PackageEvents::PRE_PACKAGE_UPDATE  => [
				[ 'checkAvailability', 10 ],
				[ 'checkMaintenanceStatus', 5 ],
				[ 'checkCompatibilityStatus', 5 ],
			],
		];
	}

	/**
	 * Determines the availability of WordPress plugins being installed or updated.
	 *
	 * If a plugin is not available in the WordPress Plugin Directory, it means that
	 * it has been temporarily or permanently removed because guideline violations,
	 * abandonment by its developer, or even security issues.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public function checkAvailability( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		if (
			$this->helper->isWordPressPlugin( $package ) &&
			! $this->isPluginAvailable( $this->helper->getPluginApiURL( $package ) )
		) {
			$this->io->writeError( sprintf(
				'<warning>The plugin %s does not seem to be available in the WordPress Plugin Directory anymore.</warning>',
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
	public function checkMaintenanceStatus( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		if (
			$this->helper->isWordPressPlugin( $package ) &&
			$this->isPluginAvailable( $this->helper->getPluginApiURL( $package ) ) &&
			! $this->isPluginActivelyMaintained( $this->helper->getPluginApiURL( $package ) )
		) {
			$this->io->writeError( sprintf(
				'<warning>The plugin %s has not been updated in over two years. Please double-check before using it.</warning>',
				$package->getName()
			) );
		}
	}

	/**
	 * Determines the compatibility status of WordPress plugins being installed or updated.
	 *
	 * Plugins that haven't been tested with the last 3 major releases of WordPress should be used with caution, as it means
	 * they might not be compatible with the latest versions of WordPress.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public function checkCompatibilityStatus( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		if (
			$this->helper->isWordPressPlugin( $package ) &&
			$this->isPluginAvailable( $this->helper->getPluginApiURL( $package ) ) &&
			! $this->hasPluginBeenTestedWithLatestVersions( $this->helper->getPluginApiURL( $package ) )
		) {
			$this->io->writeError( sprintf(
				'<warning>The plugin %s has not been tested with the last 3 major releases of WordPress. Please double-check before using it.</warning>',
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
	protected function getPackage( PackageEvent $event ) {
		/* @var PackageInterface $package */
		return $event->getOperation() instanceof InstallOperation ? $event->getOperation()->getPackage() : $event->getOperation()->getTargetPackage();
	}

	/**
	 * Determines whether a plugin is available in the WordPress Plugin Directory.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin is available, false if otherwise (404 status).
	 */
	protected function isPluginAvailable( $url ) {
		$result = $this->loadPluginData( $url );

		return null !== $result && ! isset( $result['error'] );
	}

	/**
	 * Determines whether a plugin is actively maintained or not.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin has been updated in the last two years, false otherwise.
	 */
	protected function isPluginActivelyMaintained( $url ) {
		$result = $this->loadPluginData( $url );

		if ( null === $result || ! isset( $result['last_updated'] ) ) {
			return false;
		}

		$now          = new DateTime();
		$last_updated = new DateTime( $result['last_updated'] );

		return $last_updated > $now->modify( '-2 years' );
	}

	/**
	 * Determines whether a plugin has been tested with one of the three last major releases of WordPress.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin has been tested with recent WordPress major releases or not.
	 */
	protected function hasPluginBeenTestedWithLatestVersions( $url ) {
		$result = $this->loadPluginData( $url );

		if ( null === $result || ! isset( $result['tested'] ) ) {
			return false;
		}

		$tested     = $this->incrementVersion( $result['tested'], 3 );
		$wp_version = $this->getLatestWordPressVersion();

		return version_compare( $tested, $wp_version, '>=' );
	}

	/**
	 * Increment a version number a few times.
	 *
	 * Turns 1.2.3 into 1.4. or 1.5, for example.
	 *
	 * @param string $version Version number, e.g. 1.2.3
	 * @param int $steps Number of steps the version number should be incremented.
	 * @return string Incremented version number.
	 */
	protected function incrementVersion( $version, $steps = 1 ) {
		while ( $steps > 0 ) {
			$parts = explode( '.', $version );
			$parts = array_splice( $parts, 0, 2 );

			if ( $parts[1] < 9 ) {
				$parts[1]++;
			} else {
				$parts[1] = 0;
				$parts[0]++;
			}

			$version = implode( '.', $parts );

			$steps --;
		}

		return $version;
	}

	/**
	 * Returns the current WordPress version number.
	 *
	 * @return string Version number of the latest WordPress major release, e.g. 4.9
	 */
	protected function getLatestWordPressVersion() {
		$result = file_get_contents( 'https://api.wordpress.org/core/version-check/1.7/' );

		$result = json_decode( $result, true );

		if ( isset( $result['offers'][0]['current'] ) ) {
			$parts = explode( '.', $result['offers'][0]['current'] );
			$parts = array_splice( $parts, 0, 2 );

			return implode( '.', $parts );
		}

		return null;
	}

	/**
	 * Loads data for a given plugin and tries to interpret the result as JSON.
	 *
	 * @param string $url Plugin URL.
	 *
	 * @return array|null Plugin data on success, null on failure.
	 */
	protected function loadPluginData( $url ) {
		$result = file_get_contents( $url );

		$result = json_decode( $result, true );

		return $result;
	}
}

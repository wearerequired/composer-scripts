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

class PluginAvailabilityPlugin implements PluginInterface, EventSubscriberInterface {
	/** @var Composer */
	private $composer;

	/** @var IOInterface */
	private $io;

	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
	}

	public static function getSubscribedEvents() {
		return [
			PackageEvents::PRE_PACKAGE_INSTALL => [
				[ 'checkAvailability' ],
			],
			PackageEvents::PRE_PACKAGE_UPDATE  => [
				[ 'checkAvailability' ],
				[ 'checkMaintenanceStatus', 1 ],
			],
		];
	}

	/**
	 * Determines the availability of WordPress plugins being installed or updated.
	 *
	 * If a plugin is not available in the WordPress Plugin Directory, it means that
	 * it has been temporarily or permanently removed because guideline violations,
	 * abandonce by its developer, or even security issues.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public function checkAvailability( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		if ( $this->isWordPressPlugin( $package ) && ! $this->isPluginAvailable( $this->getPluginURL( $package ) ) ) {
			$this->io->writeError( sprintf(
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
	public function checkMaintenanceStatus( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		if ( $this->isWordPressPlugin( $package ) && ! $this->isPluginActivelyMaintained( $this->getPluginURL( $package ) ) ) {
			$this->io->writeError( sprintf(
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
	protected function getPackage( PackageEvent $event ) {
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
	protected function isWordPressPlugin( PackageInterface $package ) {
		return 'wordpress-plugin' === $package->getType() && 0 === strpos( $package->getName(), 'wpackagist-plugin/' );
	}

	/**
	 * Returns the URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @param PackageInterface $package The current package.
	 *
	 * @return string The plugin URL.
	 */
	protected function getPluginURL( PackageInterface $package ) {
		return 'https://api.wordpress.org/plugins/info/1.0/' . str_replace( 'wpackagist-plugin/', '', $package->getName() ) . '.json';
	}

	/**
	 * Determines whether a plugin is available in the WordPress Plugin Directory.
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin is available, false if otherwise (404 status).
	 */
	protected function isPluginAvailable( $url ) {
		try {
			$result = $this->loadPluginData( $url );

			return null !== $result;
		} catch ( ErrorException $e ) {
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
	protected function isPluginActivelyMaintained( $url ) {
		try {
			$result = $this->loadPluginData( $url );

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
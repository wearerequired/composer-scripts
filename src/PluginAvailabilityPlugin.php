<?php

namespace Required\ComposerScripts;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\Util\HttpDownloader;
use DateTime;

class PluginAvailabilityPlugin implements PluginInterface, EventSubscriberInterface {
	/** @var Composer */
	private $composer;

	/** @var IOInterface */
	private $io;

	/** @var WordPressPluginHelper */
	private $helper;

	/** @var HttpDownloader */
	private $httpDownloader;

	/**
	 * Applies plugin modifications to Composer.
	 *
	 * @param \Composer\Composer       $composer Composer.
	 * @param \Composer\IO\IOInterface $io       Input/Output helper interface.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer       = $composer;
		$this->io             = $io;
		$this->httpDownloader = Factory::createHttpDownloader( $this->io, $this->composer->getConfig() );
		$this->helper         = new WordPressPluginHelper();
	}

	/**
	 * Removes any hooks from Composer.
	 *
	 * This will be called when a plugin is deactivated before being
	 * uninstalled, but also before it gets upgraded to a new version
	 * so the old one can be deactivated and the new one activated.
	 *
	 * @param \Composer\Composer       $composer Composer.
	 * @param \Composer\IO\IOInterface $io       Input/Output helper interface.
	 */
	public function deactivate( Composer $composer, IOInterface $io ) {
	}

	/**
	 * Prepares the plugin to be uninstalled.
	 *
	 * This will be called after deactivate.
	 *
	 * @param \Composer\Composer       $composer Composer.
	 * @param \Composer\IO\IOInterface $io       Input/Output helper interface.
	 */
	public function uninstall( Composer $composer, IOInterface $io ) {
	}

	/**
	 * Returns an array of event names this subscriber wants to listen to.
	 *
	 * @return array The event names to listen to.
	 */
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
	 * abandonment by its developer, or even security issues.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public function checkAvailability( PackageEvent $event ) {
		$package = $this->getPackage( $event );

		try {
			if ( $this->helper->isWordPressPlugin( $package ) && ! $this->isPluginAvailable( $this->helper->getPluginApiURL( $package ) ) ) {
				$this->io->writeError( sprintf(
					'<warning>The plugin %s does not seem to be available in the WordPress Plugin Directory anymore.</warning>',
					$package->getName()
				) );
			}
		} catch ( TransportException $e ) {
			$this->io->writeError(
				'<warning>Could not reach WordPress.org to verify plugin availability status.</warning>'
			);
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

		try {
			if ( $this->helper->isWordPressPlugin( $package ) && ! $this->isPluginActivelyMaintained( $this->helper->getPluginApiURL( $package ) ) ) {
				$this->io->writeError( sprintf(
					'<warning>The plugin %s has not been updated in over two years. Please double-check before using it.</warning>',
					$package->getName()
				) );
			}
		} catch ( TransportException $e ) {
			$this->io->writeError(
				'<warning>Could not reach WordPress.org to verify plugin availability status.</warning>'
			);
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
		$operation = $event->getOperation();
		if ( $operation instanceof InstallOperation ) {
			return $operation->getPackage();
		}

		if ( $operation instanceof UpdateOperation ) {
			return $operation->getTargetPackage();
		}

		return null;
	}

	/**
	 * Determines whether a plugin is available in the WordPress Plugin Directory.
	 *
	 * @throws TransportException
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin is available, false if otherwise (404 status).
	 */
	protected function isPluginAvailable( $url ) {
		$result = $this->loadPluginData( $url );

		return ! isset( $result['error'] );
	}

	/**
	 * Determines whether a plugin is actively maintained or not.
	 *
	 * @throws TransportException
	 *
	 * @param string $url URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @return bool True if the plugin has been updated in the last two years, false otherwise.
	 */
	protected function isPluginActivelyMaintained( $url ) {
		$result = $this->loadPluginData( $url );

		if ( null === $result || isset( $result['error'] ) ) {
			return false;
		}

		$now          = new DateTime();
		$last_updated = new DateTime( $result['last_updated'] );

		return $last_updated > $now->modify( '-2 years' );
	}

	/**
	 * Loads data for a given plugin and tries to interpret the result as JSON.
	 *
	 * @throws TransportException
	 *
	 * @param string $url Plugin URL.
	 *
	 * @return array|null Plugin data on success, null on failure.
	 */
	protected function loadPluginData( $url ) {
		try {
			$result = $this->httpDownloader->get( $url )->getBody();
		} catch ( TransportException $e ) {
			// If a plugin is not found a 404 HTTP status is also returned.
			if ( 404 === $e->getStatusCode() ) {
				$result = $e->getResponse();
			} else {
				throw $e;
			}
		}

		$result = json_decode( $result, true );

		return $result;
	}
}

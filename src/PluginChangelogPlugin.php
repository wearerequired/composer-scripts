<?php

namespace Required\ComposerScripts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;

class PluginChangelogPlugin implements PluginInterface, EventSubscriberInterface {
	/** @var Composer */
	private $composer;

	/** @var IOInterface */
	private $io;

	/** @var WordPressPluginHelper */
	private $helper;

	/**
	 * Applies plugin modifications to Composer.
	 *
	 * @param \Composer\Composer       $composer Composer.
	 * @param \Composer\IO\IOInterface $io       Input/Output helper interface.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
		$this->helper   = new WordPressPluginHelper();
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
			PackageEvents::POST_PACKAGE_UPDATE => [
				[ 'printLinkToChangelog' ],
			],
		];
	}

	/**
	 * Prints a link to the changelog on WordPress.org after it's being
	 * installed or updated.
	 *
	 * @param PackageEvent $event The current event.
	 */
	public function printLinkToChangelog( PackageEvent $event ) {
		/** @var \Composer\DependencyResolver\Operation\UpdateOperation $operation */
		$operation = $event->getOperation();
		$package   = $operation->getTargetPackage();

		if ( $this->helper->isWordPressPlugin( $package ) ) {
			$this->io->write( sprintf(
				'    Changelog: %s',
				$this->helper->getPluginChangelogURL( $package )
			) );
		}
	}
}

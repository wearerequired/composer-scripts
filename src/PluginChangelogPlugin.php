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

	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
		$this->helper   = new WordPressPluginHelper();
	}

	public static function getSubscribedEvents() {
		return [
			PackageEvents::PRE_PACKAGE_UPDATE => [
				[ 'printLinkToChangelog' ],
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
	public function printLinkToChangelog( PackageEvent $event ) {
		$package = $event->getOperation()->getTargetPackage();

		if ( $this->helper->isWordPressPlugin( $package ) ) {
			$this->io->write( sprintf(
				'Changelog: %s',
				$this->helper->getPluginChangelogURL( $package )
			) );
		}
	}
}
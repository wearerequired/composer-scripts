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
		$package = $event->getOperation()->getTargetPackage();

		if ( $this->helper->isWordPressPlugin( $package ) ) {
			$this->io->write( sprintf(
				'    Changelog: %s',
				$this->helper->getPluginChangelogURL( $package )
			) );
		}
	}
}

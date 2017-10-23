<?php

namespace Required\ComposerScripts;

use Composer\Package\PackageInterface;

class WordPressPluginHelper {
	/**
	 * Determines whether a package is from WPackagist.
	 *
	 * WPackagist is a mirror of the WordPress Plugin Directory.
	 *
	 * @param PackageInterface $package The current package.
	 *
	 * @return bool True if package is a WordPress plugin, false otherwise.
	 */
	public function isWordPressPlugin( PackageInterface $package ) {
		return 'wordpress-plugin' === $package->getType() && 0 === strpos( $package->getName(), 'wpackagist-plugin/' );
	}

	/**
	 * Returns the plugin's slug, without the wpackagist-plugin prefix.
	 *
	 * @param PackageInterface $package The package.
	 *
	 * @return string The plugin slug.
	 */
	protected function getPluginSlug( PackageInterface $package  ) {
		return str_replace( 'wpackagist-plugin/', '', $package->getName() );
	}

	/**
	 * Returns the URL to the plugin in the WordPress Plugin Directory.
	 *
	 * @param PackageInterface $package The package.
	 *
	 * @return string The plugin URL.
	 */
	public function getPluginApiURL( PackageInterface $package ) {
		return sprintf( 'https://api.wordpress.org/plugins/info/1.0/%s.json', $this->getPluginSlug( $package ) );
	}

	/**
	 * Returns the URL to the plugin's changelog on WordPress.org.
	 *
	 * @param PackageInterface $package The package.
	 *
	 * @return string The plugin changelog URL.
	 */
	public function getPluginChangelogURL( PackageInterface $package ) {
		return sprintf( 'https://wordpress.org/plugins/%s/#developers', $this->getPluginSlug( $package ) );
	}
}
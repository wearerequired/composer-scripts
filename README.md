# Composer Scripts &middot; [![Build Status](https://travis-ci.org/wearerequired/composer-scripts.svg?branch=master)](https://travis-ci.org/wearerequired/composer-scripts)

A collection of useful Composer plugins used by required.

## Features

### Plugin Availability

Inspired by [No Longer in Directory](https://wordpress.org/plugins/no-longer-in-directory/), this feature detects if a WordPress plugin (type `wordpress-plugin`) is being installed or updated from WPackagist that:

* has been pulled from the WordPress Plugin Directory but is still available via Subversion.
* has not been updated in over two years and thus should be used carefully.

### Plugin Changelog

This feature prints a link to the changelog on WordPress.org if a WordPress plugin has been installed or updated.

![Example](https://user-images.githubusercontent.com/617637/31888603-1942a592-b7fd-11e7-9a1f-40e5f0ebf02a.png)


## Installation

Install this script by using `composer global require wearerequired/composer-scripts`.
And that's it! From now on, this composer plugin will run automatically when updating or installing WordPress plugins through Composer.

**Note:** It's recommended to install this into your global `~/.composer/composer.json` file instead of every project's configuration. This way, the script is only run locally and not elsewhere.


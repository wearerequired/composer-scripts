# Composer Scripts &middot; [![Build Status](https://travis-ci.org/wearerequired/composer-scripts.svg?branch=master)](https://travis-ci.org/wearerequired/composer-scripts)

A collection of useful Composer plugins used by required.

## Features

### `PluginAvailability`

Inspired by [No Longer in Directory](https://wordpress.org/plugins/no-longer-in-directory/), this class detects if a WordPress plugin (type `wordpress-plugin`) is being installed or updated from WPackagist that:

* has been pulled from the WordPress Plugin Directory but is still available via Subversion.
* has not been updated in over two years and thus should be used carefully.

## Installation

Install this script by using `composer require wearerequired/composer-scripts` (coming soon) or by loading it directly in your `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/wearerequired/composer-scripts"
        }
    ],
    "require": {
        "wearerequired/composer-scripts": "dev-master"
    }
}
```

And that's it! From now on, this composer plugin will run automatically when updating or installing WordPress plugins through Composer.

**Note:** it's recommended to put this into your global `~/.composer.composer.json` file instead of every project's configuration. This way, the script is only run locally and not elsewhere.


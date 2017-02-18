# Composer Scripts

A collection of useful Composer scripts used by required.

## Features

### `PluginAvailability`

Inspired by [No Longer in Directory](https://wordpress.org/plugins/no-longer-in-directory/), this class detects if a WordPress plugin (type `wordpress-plugin`) is being installed or updated from WPackagist that:

* has been pulled from the WordPress Plugin Directory but is still available via Subversion.
* has not been updated in over two years and thus should be used carefully.

## Installation

1. Install this script by using `composer require wearerequired/composer-scripts` (coming soon) or by loading it directly in your `composer.json` file:

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

## Usage

You can specify the scripts you want to use in the `scripts` section of your `composer.json` file. Here's an example:

```json
{
    "scripts": {
        "pre-package-install": "Required\\ComposerScripts\\PluginAvailability::checkAvailability",
        "pre-package-update": [
            "Required\\ComposerScripts\\PluginAvailability::checkAvailability",
            "Required\\ComposerScripts\\PluginAvailability::checkMaintenanceStatus"
        ]
    }
}
```


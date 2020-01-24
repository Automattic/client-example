# Installation

Before using the plugin code on your WordPress site, you need to build it.

## Building the plugin

### Prerequisites

If your system is set up to build Jetpack, you should be good to go. The only prerequisite for successfully building the plugin is composer.

### Build

Check out the repository, enter the folder and run `composer install`. You should see packages being installed:

```
$ composer install
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
Package operations: 3 installs, 0 updates, 0 removals
  - Installing automattic/jetpack-constants (dev-master 5a83f0a): Cloning 5a83f0a1f2 from cache
  - Installing automattic/jetpack-options (dev-master 54f17b3): Cloning 54f17b3b2f from cache
  - Installing automattic/jetpack-connection (dev-master ed0647c): Cloning ed0647c0ba from cache
Generating autoload files
```

After the plugin dependencies are installed, you need to zip the folder and upload it as a plugin to your test site.

```
$ cd ..
$ zip -9r --exclude=*.git* jetpack-boost.zip jetpack-boost
```

# Usage

The activated plugin will add a `Jetpack Boost` dashboard item that opens to a debug page. There you will see three main pieces: *Registration*, *Options*, and *Private Options*. On first installation you should see nothing, but using the button in the Registration section you can request a new blog identifier and token for your site. After a request, the data will be populated by the identifiers and token received from WordPress.com. At that time your site is capable of **signing and verifying signed** requests to and from WordPress.com.

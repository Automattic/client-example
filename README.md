# What this plugin is for

In order to use some functionality provided by WordPress.com and used by Jetpack, a connection between the site and WordPress.com is needed. The package providing connection capability is called jetpack-connection, and this plugin illustrates what needs to be done to use it.

# Building and Developing the plugin

## Making your development site publicly available

This plugin, like Jetpack itself, requires that your site has a public address on the internet. Assuming you are developing on a laptop or desktop machine (rather than a server with its own existing public address), this means you need a tool like `ngrok` in order to "tunnel" from a public address (e.g. mysubdomain.ngrok.io) to your development machine.

On my laptop, I create a tunnel to my development site (running on Local by Flywheel) like this:

```bash
$ ngrok http -subdomain=goldsounds3 80
```

My site also needs to have its public URL set to http://goldsounds3.ngrok.io

## Building the plugin

Check out the code into your plugins directory:

```bash
$ cd my-site/wp-content/plugins
$ git clone git@github.com:Automattic/client-example.git
```

Install the dependencies:

```bash
$ composer install
```

Now activate the plugin in wp-admin of your development site.

You can then navigate to the "Client Example" menu item in wp-admin.

## Troubleshooting / FAQ

### The Jetpack server was unable to communicate with your site

When you try to connect, you might see this error:

```
The Jetpack server was unable to communicate with your site http://goldsounds3.ngrok.io [HTTP 404]. Ask your web host if they allow connections from WordPress.com. If you need further assistance, contact Jetpack Support: http://jetpack.com/support/
```

This means you don't have a correct public address that allows WordPress.com servers to access your machine. Double check your tunnelling configuration, and if possible confirm your site is publicly accessible by pinging or using `curl` from the shell of another computer on the internet.

# What it does

## Initialization

The first thing that the plugin must do is [initialize the Connection through the Jetpack Config package ](https://github.com/Automattic/client-example/blob/master/client-example.php#L82). This will set all necessary hooks in order for the rest of the parts to work properly. Notice how the starting point function is run at the `plugins_loaded` hook with priority 1. This is the standard procedure for using Jetpack Config - not too early to allow all plugins to load, but not too late to allow all plugins that need early initialization some space.

## Admin area

Initalization adds a `Client Example` dashboard item with a control page displaying the main button and some debugging information. It's a very simple [administration page](https://github.com/Automattic/client-example/blob/master/admin/partials/client-example-admin-display.php) with several sections.

### Site registration

The main `Register` button runs a register request that is handled by the controllers described in technical sections below.

### User disconnection

The `Disconnect current user` button appears if you already are  connected to WordPress.com. Pressing this button will disconnect your currently logged in user. To test this procedure you can install Jetpack alongside this plugin on your test site and connect your user.

## Registration controller

The [registration controller](https://github.com/Automattic/client-example/blob/master/admin/class-client-example-admin.php#L139) is a very basic WordPress action controller with a nonce check. All the heavy lifting is done by the Connection Manager.

## User disconnect controller

The same can be said about the disconnect controller - it's just handling a form submission and calling the `disconnect_user` method with the current user ID. Note that if you are the connection owner (AKA the only administrator connected to WordPress.com), you won't be able to disconnect, and the UI won't show you any error messages in this case.

## Callback controller

The callback controller also works using the handlers set up by the Connection Manager at initialization state. This callback is needed for WordPress.com to confirm the registration intent.

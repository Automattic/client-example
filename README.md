# What this plugin is for

In order to use some functionality provided by WordPress.com and used by Jetpack, a connection between the site and WordPress.com is needed. The package providing connection capability is called jetpack-connection, and this plugin illustrates what needs to be done to use it.

# What it does

## Initialization

The first thing that the plugin must do is [initialize the Connection Manager object|https://github.com/Automattic/client-example/blob/master/client-example.php#L82]. This will set all necessary hooks in order for the rest of the parts to work properly.

## Admin area

Initalization adds a `Client Example` dashboard item with a control pag displaying the main button and some debugging information. It's a very simple [administration page|https://github.com/Automattic/client-example/blob/master/admin/partials/client-example-admin-display.php] with several sections. The main `Register` button runs a register request that is handled by the controllers described below.

## Registration controller

The [registration controller|https://github.com/Automattic/client-example/blob/master/admin/class-client-example-admin.php#L139] is a very basic WordPress action controller with a nonce check. All the heavy lifting is done by the Connection Manager.

## Callback controller

The callback controller also works using the handlers set up by the Connection Manager at initialization state. This callback is needed for WordPress.com to confirm the registration intent.

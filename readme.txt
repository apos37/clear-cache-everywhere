=== Clear Cache Everywhere ===
Contributors: apos37
Tags: cache, clear cache, flush cache, performance, admin bar
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Clear all cache sources in one click from the WordPress admin bar, including WP cache, transients, sessions, browser cache.

== Description ==

**Clear Cache Everywhere** allows administrators to instantly clear various cache sources directly from the WordPress admin bar. This ensures changes are reflected immediately without waiting for cache expiration.

**Features:**

- **One-Click Cache Clearing:** Clears WordPress object cache, page cache, all transients, and sessions with a single click.
- **Browser Cache Clearing:** Forces all visitors to reload fresh content by invalidating the browser cache.
- **Admin Bar Access:** Provides quick cache purging directly from the WordPress admin bar with an easy-to-use button (eraser icon).
- **Hosting Integration:** Optionally clears hosting-level cache if a purge URL is provided in the settings.
- **Third-Party Support:** Clears cache for various supported plugins, such as Elementor, Cornerstone, and other integrations.
- **Developer Hooks:** Developers can customize the cache clearing process using hooks to add more integrations or actions.

By default, clearing the cache will execute the following actions:

- **Flush Rewrite Rules:** Clears WordPress rewrite rules to immediately reflect changes to permalinks.
- **Clear WordPress Object Cache:** Clears the object cache in WordPress if enabled.
- **Clear All Transients:** Deletes all stored transients in the WordPress database.
- **Destroy PHP Sessions:** Clears PHP sessions if enabled.
- **Clear Cookies:** Clears all cookies except those related to logged-in users (`wordpress_logged_in_*`).
- **Force Browser Cache Invalidation:** Sends headers to force the browser to reload fresh content.
- **Clear Hosting-Level Cache:** Optionally calls a purge URL to clear hosting-level cache if a URL is provided.

**Integrations:**

The plugin already supports clearing cache for the following third-party plugins:

- Cornerstone
- Elementor
- WP Super Cache
- W3 Total Cache
- WP Rocket
- LiteSpeed Cache
- SiteGround Optimizer
- Cloudflare
- Autoptimize
- Swift Performance
- Comet Cache
- WP Fastest Cache
- Hummingbird
- Nginx Helper
- WP-Optimize

This plugin is ideal for developers, content managers, and site owners who need immediate cache flushing across multiple layers.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/clear-cache-everywhere/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The **Clear Cache** button will appear in the admin bar.

== Frequently Asked Questions ==

= How does this plugin clear cache? =
It flushes WordPress object cache, transients, sessions, rewrite rules, and any configured third-party caches. If a hosting cache purge URL is set, it will be called as well. Additionally, it forces browsers to reload fresh content.

= Does this work with all hosting providers? =
This depends on your hosting provider. If they offer a cache purge URL, you can configure it in the plugin settings. For example, if you have GoDaddy's Website Security and Backups, you can navigate to Firewall > Settings > Performance > Clear Cache, then grab the Clear Cache API link.

= Will this force browsers to load fresh content? =
Yes! The plugin sends cache-control headers to prompt browsers to reload updated content.

= Can I add custom cache clearing actions? =
Yes! Developers can hook into `cceverywhere_after_clear_cache` and `cceverywhere_custom_settings`:

`<?php
// Action to purge a custom cache directory after clearing cache
add_action( 'cceverywhere_after_clear_cache', function() {
    // Check if the custom cache purge option is enabled
    $prefix = 'clear_cache_everywhere_';
    if ( get_option( $prefix . 'purge_custom_cache_directory', false ) ) {
        // Purge a custom cache directory
        $cache_dir = WP_CONTENT_DIR . '/cache/myplugin/';
        if ( file_exists( $cache_dir ) ) {
            array_map( 'unlink', glob( $cache_dir . '*' ) );
        }
    }
} );

// Filter to add custom settings field for the action above
add_filter( 'cceverywhere_custom_settings', function( $fields ) {
    // Define a custom checkbox field
    $fields[] = [
        'key'       => 'purge_custom_cache_directory',
        'title'     => __( 'Purge Custom Cache Directory', 'clear-cache-everywhere' ),
        'type'      => 'checkbox',
        'sanitize'  => 'sanitize_checkbox',
        'section'   => 'custom',
        'default'   => FALSE,
        'comments'  => __( 'Enable this option to automatically purge a custom cache directory (e.g., for your plugin\'s cache). The cache will be cleared after the global cache is cleared.', 'clear-cache-everywhere' ),
    ];
    return $fields;
} );
?>`

= Is there a function to trigger clearing cache everywhere? =
Yes! A helper function is available for you to use: `cceverywhere_clear_all( $log_results = false )`. 

The function returns the results of the cache clearing processing. Optionally, you can log the results by setting the `$log_results` parameter to `true`. This will log the results to the debug log.

= Where can I request features and get further support? =
We recommend using our [website support forum](https://pluginrx.com/support/plugin/clear-cache-everywhere/) as the primary method for requesting features and getting help. You can also reach out via our [Discord support server](https://discord.gg/3HnzNEJVnR) or the [WordPress.org support forum](https://wordpress.org/support/plugin/clear-cache-everywhere/), but please note that WordPress.org doesn’t always notify us of new posts, so it’s not ideal for time-sensitive issues.

== Demo ==
https://youtu.be/61dk4iydfDA

== Screenshots ==

1. Settings page and admin bar button.

== Changelog ==

= 1.1.0 =
* Update: New support links

= 1.0.2.1 =
* Fix: Sanitized cookie names
* Update: Moved scripts and styles to enqueue

= 1.0.2 =
* Update: Updated author name and website per WordPress trademark policy

= 1.0.1 =
* Initial Release on March 19, 2025
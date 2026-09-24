=== Nizbay Asset Downloader ===
Contributors: bayzid416
Tags: downloader, theme downloader, plugin downloader, media exporter, asset downloader
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download installed themes, plugins, and media library files directly from your WordPress Dashboard as ZIP archives.

== Description ==

Nizbay Asset Downloader provides simple, essential tools for exporting and downloading WordPress site assets directly from the WordPress Dashboard.

With this plugin, you can:

* Download installed plugins as ZIP archives
* Download installed themes as ZIP archives
* Download individual media library files
* Bulk download selected media library files as a single ZIP package

== Features ==

* Plugin Downloader (ZIP export for active and inactive plugins)
* Theme Downloader (ZIP export for active and inactive themes)
* Media Downloader (Single file download & bulk ZIP export with media filters)
* Simple, fast, and secure admin interface

== External services ==

This plugin optionally connects to an external service to collect voluntary opt-in contact information and site telemetry data upon plugin activation.

* **Service Purpose**: Collects voluntary user contact details for security updates, feature announcements, and basic compatibility diagnostics.
* **Service Provider**: Provided by Md. Bayzid Mostafiz via endpoint: `https://bayzidmostafiz.com/wp-json/wpdt-collector/v1/subscribe`.
* **Data Transmitted**: Voluntary user contact information (Name, Email Address, Phone Number) and site diagnostic metadata (Site URL, WordPress Version, PHP Version, Plugin Name & Version).
* **Transmission Condition**: Transmitted ONLY if the user explicitly clicks "Allow & Continue" on the welcome modal dialog. No data is sent if the user clicks "Skip" or dismisses the dialog.
* **Terms of Service**: [Terms of Service](https://bayzidmostafiz.com/terms-of-service-for-wp-plugins/)
* **Privacy Policy**: [Privacy Policy](https://bayzidmostafiz.com/privacy-policy-for-wp-plugins/)

== Installation ==

1. Upload the `nizbay-asset-downloader` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open Nizbay Asset Downloader from the WordPress Dashboard.

== Frequently Asked Questions ==

= Can I download installed plugins? =

Yes. Nizbay Asset Downloader allows you to download any installed plugin as a ZIP archive directly from your WordPress Dashboard.

= Can I download installed themes? =

Yes. You can download installed themes as ZIP archives directly from the plugin.

= Can I export media library files? =

Yes. You can filter media by images, videos, audio, or documents and download single files or bulk export selected files as a ZIP package.

== Screenshots ==

1. Themes Downloader tab showing installed themes and download options.
2. Plugins Downloader tab displaying installed plugins with quick ZIP download buttons.
3. Media Downloader tab with type filters and bulk ZIP export options.

== Changelog ==

= 1.0.3 =
* Renamed plugin to Nizbay Asset Downloader.
* Added External Services disclosure in readme.txt.
* Enqueued JavaScript handlers via wp_add_inline_script for standard script management.

= 1.0.0 =
* Initial release.
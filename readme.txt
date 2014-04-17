=== Filters - An Enhanced UI for Post management ===
Contributors: sc0ttkclark, logikal16
Donate link: http://podsfoundation.org/donate/
Tags: admin, filters, edit
Requires at least: 3.8
Tested up to: 3.9
Stable tag: 0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A flexible replacement for the default WordPress post management. Takes over edit.php screens for all post types.

== Description ==

This plugin lets you use an enhanced UI for your post types search/filtering to really clean up the experience.

It is pretty basic right now, there are no options and there may be a few bugs as it's tested across more environments.

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP Admin plugin page)
1. Activate this plugin

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name

== Screenshots ==

1. An enhanced UI for search / filtering that's tucked away under a pop-up to do advanced filtering
2. The advanced filtering pop-up

== Changelog ==

= 0.4 =
* Added: Added support for WP 3.8 and 3.9
* Removed: No longer offering support for WP 3.6 or 3.7
* Coming soon: Modal based filtering, and the new WP 3.9 theme browser bar to replace the Filters bar

= 0.3 =
* Added: Custom Field Suite integration, now automatically pulls filters from the post type, if it has fields valid for CFS
* Added: Added support for WP 3.7
* Improved: Pods integration now better for relationship fields
* Removed: No longer offering support for WP 3.5

= 0.2 =
* Added: Pods integration, now automatically pulls filters from the post type, if it's created/extended by Pods
* Added: New compare / relation handling in query parameters available, form UI has not been updated to reflect the new options: filters_relation (and/or) and filters_compare_fieldname (see WP_Query meta_query)
* Added: New filter - filters_post_type_blacklist - Provide an array of post types to exclude from the enhanced UI
* Added: New filter - filters_ui_fields - Provide an array of field data to use
* Added: New filter - filters_ui_filters - Provide an array of filters to use (utilizes field data)
* Added: New filter - filters_ui_views - Provide an array of 'view' => 'Label' to use (defaults to post statuses)
* Added: New filter - filters_ui_popup_months - Provide null, false, or empty string to exclude the 'Months' input in the filters popup
* Improved: Cleaned up the Filters_Posts_List_Table class a bit, abstracted for fields/filters data (for Pods integration)
* Fixed: CSS fixes for WP 3.5
* Removed: No longer offering support for WP 3.4

= 0.1 =
* Alpha release, to get things rolling!
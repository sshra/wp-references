=== References ===
Contributors: shra
Donate link: https://shra.ru/2016/06/references-wp-plugin/
Tags: reference, node reference, post connections
Requires at least: 3.0
Tested up to: 5.2.2
Stable tag: 1.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Enables post references (for any type of publications) to connect articles to each other.

== Description ==

This plugin will let you manage the post references. It is like "node reference" in Drupal CMS module functionality.

Use Reference Settings page to configure publications connections.

After configuring step you will see additional metabox(s) on post editor page. Now you can choose articles of referenced post types to attach it to article you edit.

Plugin stores connected article list to post metas. For template you can use, for example, get_post_meta($post->ID, '_ref_ID', true) to receive that list. As 'ref_ID' you should use a meta key configured on Referenced settings page earlier.

Plugin allow you to configure widgets to view the list(s) of attached articles.

= Plugin API =

Plugin has own API which allows you create references from your code (after registering your own post types, etc). All functions are static and available through class REFShraAPI.

Currently there are implemented 5 functions. List of functions is below. More details about API functions, their arguments, examples look in referenece.php.

REFShraAPI::config_load($postType = NULL, $refKey = NULL);
//returns array currently configured REFERENCES.

REFShraAPI::config_add($postType, $refKey, $linkedTypes, $title)
// ADD/update REFERENCE configs.

REFShraAPI::config_remove($postType, $refKey);
// Delete REFERENCE config by pair postType / refKey.

REFShraAPI::get($postID = NULL);
// Get references data by post ID.

REFShraAPI::update($postID = NULL, $refkey, $postIDs);
// Update references data by post ID.

REFShraAPI::find($postID = NULL, $postTypes = array(), $onlyPublished = false);
// Search for article with attached post_id 

= Shortcode REF =

Plugin supports shortcode REF. It allows to show list of referenced articles in any place of your post. Shortcode function implementation allows you customize list and output. Only two attributes are available.

[ref id="POST_ID" key="REFERENCE_KEY"]

== Installation ==

Best is to install directly from WordPress. If manual installation is required, please make sure that the plugin files are in a folder named "references", usually "wp-content/plugins".

== Changelog ==

= 1.2 =

New API function REFShraAPI::find // returns array of articles where attached given article (as post_id).

= 1.1 =
Added implementation referesence list shortdcode - ref.
[ref id="POST_ID" key="REFERENCE_KEY"]

Added reference API. You can use in your code static functions of special class REFShraAPI. Now 5 functions are available. More details about API functions, their arguments, examples look in referenece.php.

REFShraAPI::config_load($postType = NULL, $refKey = NULL); //returns array currently configured REFERENCES.

REFShraAPI::config_add($postType, $refKey, $linkedTypes, $title) // ADD/update REFERENCE configs.

REFShraAPI::config_remove($postType, $refKey); // Delete REFERENCE config by pair postType / refKey.

REFShraAPI::get($postID = NULL); // Get references data by post ID.

REFShraAPI::update($postID = NULL, $refkey, $postIDs); // Update references data by post ID.

= 1.02 =
Fixed bug with empty value reference case.

= 1.01 =
Few cosmetic changes. Main change is next - plugin now allows to manage all post types with `show_ui` flag instead of post types with `public` flag.

= 1.0 =
Includes an admin page with plugin setting and Widgets.

== Screenshots ==

1. Install References plugin.
2. The References settings page.
3. Build article connections.
4. Configure widget(s).
5. Created widget view.
6. Using REF shortcode in post editor.
7. REF shortcode rendered on frontend.

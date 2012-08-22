=== Chirp Twitter Widget ===
Contributors: spencerfinnell
Tags: twitter, widget, tweets
Stable tag: 0.1
Requires at least: 3.4
Tested up to: 3.4.1
License: GPLv2

A simple widget for displaying tweets. Allows more advanced customization via hooks and filters.

== Description ==

Display the latest Tweets from your Twitter accounts inside WordPress widgets. Customize Tweet displays using your site or theme CSS, as well as custom hooks and filters.

Customize the number of tweets displayed. Filter out @replies from your displayed tweets. Optionally include retweets. The widget plugin automatically links usernames, lists, and hashtags mentioned in Tweets to keep your readers on top of the full conversation.

== Installation ==

1. Upload the directory to your /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the widget to your sidebar from Appearance->Widgets and configure the widget options.

== Frequently Asked Questions ==

= Can multiple instances of the widget be used? =

Yes.

= Can private Twitter accounts be used? =

No. The widget does not support authenticated requests for private data.

= I see less than the requested number of Tweets displayed =

Twitter may return less than the requested number of Tweets if the requested account has a high number of @replies in its user timeline.

== Screenshots ==

1. Enter your Twitter username, customize your widget title, set the total number of tweets, hide replies, and customize text display in your widget editor.
2. Latest tweets display.

== Changelog ==

= 0.1 =
* Initial version
=== Plugin Name ===
Contributors: george_michael
Donate link: 
Tags: 
Requires at least: 3.5.2
Tested up to: 3.5.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lets users suggest that content may need to be reviewed or re-examined.

== Description ==

This plugin lets users suggest a post for review. For example, if you have a help document that hasn't been updated in
a while and is now out of date, a user can let you know that you need to update it without much work on their part.

* Can be configured so that when a post is marked as needing review, an email is sent to the author.
* Weekly, an email listing all of the review suggestions will be sent to a list of email addresses.
* Can be configured to allow guests to mark items for review.
* Can also be configured to put the last update date to the end of each post.
* Exclude the suggest review button by IDs of pages, posts

Neither the mark for review button or last update date will appear on list of post pages or search results.

== Installation ==

1. Upload `suggest-review.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why'd you make this? =

I was looking into using Wordpress for documentation within our department and knew it would be handy to have a
mechanism for users to let us know when something was old or confusing. Sure, they could just leave a comment,
but I wanted something that would also limit the amount of feedback from one article. Also, I'd never written a
plugin and really had no knowledge of Wordpress, so it seemed like a good way to learn.

== Screenshots ==

1. Example of a post that has been flagged for review. Also shows the 'last updated' option.
2. If content hasn't been marked for review, the user sees this button.
3. When editing a post, if it has been suggested for review, this meta box will appear. The checkbox is automatically
checked; the assumption is that you are editing the content to resolve a review suggestion.
4. Options page.

== Changelog ==

= 1.0.1 =
* Added exclude posts by IDs option

= 1.0.0 =
* Initial version.

== Upgrade Notice ==

== Arbitrary section ==

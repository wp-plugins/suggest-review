=== Plugin Name ===
Contributors: george_michael
Donate link: 
Tags: 
Requires at least: 3.5.2
Tested up to: 4.2.2
Stable tag: 1.3.3
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
* There is a page in the dashboard that lists all of the content that has been flagged for review (that the current user can access)

Neither the mark for review button or last update date will appear on list of post pages or search results.

== Installation ==

From your WordPress dashboard:
1. From the Plugins page, select 'Add New'
1. Search for 'Suggest Review'
1. Click the 'Install Now' or 'Update Now' button
1. Activate the plugin through the 'Plugins' page
Not using the dashboard?
1. Download the plugin zip file
1. Unpack the zip file into your WordPress plugins directory (i.e., /PathToWordPress/wp-content/plugins/suggest-review/)
1. Activate the plugin through the 'Plugins' page

== Frequently Asked Questions ==

= Why'd you make this? =

I was looking into using Wordpress for documentation within our department and knew it would be handy to have a
mechanism for users to let us know when something was old or confusing. Sure, they could just leave a comment,
but I wanted something that would also limit the amount of feedback from one article. Also, I'd never written a
plugin and really had no knowledge of Wordpress, so it seemed like a good way to learn.

== Screenshots ==

1. Example of a post that has been flagged for review. Also shows the 'last updated' option.
2. If content hasn't been marked for review, the user sees this button.
3. When the button is clicked, it changes to a text box with submit and cancel buttons.
4. When editing a post, if it has been suggested for review, this meta box will appear. The checkbox is automatically checked; the assumption is that you are editing the content to resolve a review suggestion. Also note the radio button that allows you to exclude a post while you are editing, rather than entering them directly into the admin options.
5. Options page.
6. Dashboard page to list flagged posts.
7. Admins see the settings and post list in the dashboard.

== Changelog ===

= 1.3.3 =
* Moved some CSS to external stylesheet for easier editing. Added some options to make language translation easier. Thanks to Jakub for the feedback.

= 1.3.2 =
* Minor bug fix relating to CSS on the comment box.

= 1.3.1 =
* Added option to change the text of the button.

= 1.3.0 =
* Added option to not display comment on posts.
* Added dashboard page that lists all flagged posts (that the current user has access to).
* Both new features are at WPHaider's suggestion, so big thanks go to Haider!

= 1.2.5 =
* Fixed bug that caused the button to now appear when using http instead of https. Thanks to WPHaider for finding it.

= 1.2.4 =
* Added option for text alignment of footer.

= 1.2.3 =
* Changed option for adding last update stamp to posts. You can now select "Yes, if not in exclusion list".

= 1.2.2 =
* Added bulk action option to exclude posts (and only posts at this time) from SR.

= 1.2.1 =
* Added checkbox to set a page or post to be excluded while editing that page or post. This allows non-admin users to exclude things without asking an admin to do it.

= 1.2.0 =
* When submitting comments, the review flag was always set and comments would be lost. This is now fixed.
* Changed the_content filter priority to interact better with other plugins.
* Added comment field directly to plugin so that comments are separate from post comments.
* Changed time of mark and last update to use local time instead of UTC.

= 1.1.0 =
* Added ability to change text for emails to authors and digest email
* Added link to plugin settings from the admin plugin listing

= 1.0.1 =
* Added exclude posts by IDs option

= 1.0.0 =
* Initial version.

== Upgrade Notice ===

= 1.3.3 =
Minor changes based on user feedback. Upgrade recommended.

= 1.3.2 =
Minor bug fix. Upgrade optional.

= 1.3.1 =
New option. Upgrade optional.

= 1.3.0 =
New features. Upgrade recommended, but not required.

= 1.2.5 =
Bug fix. Upgrade recommended.

= 1.2.4 =
New option. Upgrade optional.

= 1.2.3 =
New option. Upgrade optional.

= 1.2.2 =
Useful new feature. Upgrade recommended.

= 1.2.1 =
New option when editing a post. Upgrade recommended.

= 1.2.0 =
Fixed serious bug with comments and the review flag. Everyone should upgrade.

= 1.1.0 =
New features. Upgrade recommended.

= 1.0.1 =
New features. Upgrade optional.

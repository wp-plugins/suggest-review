<?php
/**
 * @package Suggest_Review
 * @version 1.2.0
 */
/*
Plugin Name: Suggest Review
Plugin URI: http://wordpress.org/plugins/suggest-review/
Description: Lets users suggest that content may need to be reviewed or re-examined.
Author: Michael George
Version: 1.1.2

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! class_exists( "SuggestReview" ) ) {
	class SuggestReview {
		var $adminOptionsName = "SuggestReviewAdminOptions";

		function SuggestReview() { //constructor
		}

		function init() {
			$this->getAdminOptions();
		}

		//Returns an array of admin options
		function getAdminOptions() {
			$suggestReviewAdminOptions = array('allow_unregistered' => 'false'
				,'send_email_to_author' => 'true'
				,'subject_for_email_to_author' => 'Blog content flagged for review'
				,'body_for_email_to_author' => 'Content you authored on the blog has been suggested for review. You should check this content and any comments on it and then post an update.

Title: [post_title]
Suggested by: [suggesting_user_login]
Comment: [comment]

View the content at: [permalink]'
				,'add_update_date_to_posts' => 'true'
				,'excluded_ids' => ''
				,'address_for_digest_email' => ''
				,'subject_for_digest_email' => 'Blog content flagged for review'
				,'body_for_digest_email' => 'The following content has been marked for review on the blog.'
				,'item_for_digest_email' => '
Title: [post_title]
Author: [author_login]
Last Modified: [post_modified]
Link: [permalink]');
			$devOptions = get_option( $this->adminOptionsName );
			if ( ! empty( $devOptions ) ) {
				foreach ( $devOptions as $key => $option )
					$suggestReviewAdminOptions[$key] = $option;
			}				
			update_option( $this->adminOptionsName, $suggestReviewAdminOptions );
			return $suggestReviewAdminOptions;
		}

		//Do the actual marking in the meta table and send an email if option enabled
		function markForReview( $post_id, $marking_user = '', $marking_date, $comment, $author ) {
			update_post_meta( $post_id, 'suggestreview_needed', 'true' );
			update_post_meta( $post_id, 'suggestreview_by', $marking_user );
			update_post_meta( $post_id, 'suggestreview_date', $marking_date );
			update_post_meta( $post_id, 'suggestreview_comment', $comment );
			$devOptions = get_option( $this->adminOptionsName );

			if ( $devOptions['send_email_to_author'] == "true" ) {
				$authoremail = get_the_author_meta('user_email');
				wp_mail( $authoremail
					,replaceTokens( $devOptions['subject_for_email_to_author'], $post_id )
					,replaceTokens( $devOptions['body_for_email_to_author'], $post_id ) );
			}
			return true;
		}

		function resolveMarkForReview( $post_id ) {
			update_post_meta( $post_id, 'suggestreview_needed', 'false' );
			return true;
		}

		function content_insertion( $content ) {
			//Setup some variables
			$devOptions = $this->getAdminOptions();
			$my_post_id = get_the_ID();
			if ( is_user_logged_in() ) {
				$my_user = wp_get_current_user();
			} else {
				$my_user = new WP_User( 0 );
				$my_user->user_login = 'guest';
			}
			$my_author = get_the_author();
			$return = $content;

			//Check to see if the URL is a permalink, if not, we aren't doing anything
			//This prevents the suggest review button from appear on search results and post lists
			if ( "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] == get_permalink() ) {

				if ( isset( $_POST['suggestreview'] ) && $_POST['suggestreview'] == '1' && isset( $_POST['suggestreviewid'] ) && $_POST['suggestreviewid'] == $my_post_id ) {
					$this->markForReview( $_POST['suggestreviewid'], $my_user->user_login, date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ), $_POST['suggestreviewcomment'], $my_author );
				} else if ( isset( $_POST['resolvereview'] ) && $_POST['resolvereview'] == '1' && isset( $_POST['resolvereviewid'] ) && $_POST['resolvereviewid'] == $my_post_id ) {
					$this->resolveMarkForReview( $_POST['resolvereviewid'] );
				}

				//See if we should put in the last update time
				if ( $devOptions['add_update_date_to_posts'] == 'true' ) {
					$lastupdated = get_post_meta( $my_post_id, 'suggestreview_lastupdated' );
					$lastupdatedby = get_post_meta( $my_post_id, 'suggestreview_lastupdatedby' );
					$return .= '<p>Last updated by '.$lastupdatedby[0].' on '.$lastupdated[0].'</p>';
				}

				$excludedIDs = explode( ',', $devOptions['exclude_ids']);
				$excluded = in_array( $my_post_id, $excludedIDs);
				$needsReview = get_post_meta( $my_post_id, 'suggestreview_needed' );
				//If this has been marked for review
				if ( ! empty($needsReview) && $needsReview[0] == "true" && ! $excluded ) {
					$markedbyuser = get_post_meta( $my_post_id, 'suggestreview_by' );
					$markedbydate = get_post_meta( $my_post_id, 'suggestreview_date' );
					$markedbycomment = get_post_meta( $my_post_id, 'suggestreview_comment' );
					$return .= '<p>The above was flagged for review by ';
					$return .= $markedbyuser[0];
					$return .= ' on ';
					$return .= $markedbydate[0];
					$return .= '<br>Comment: <em>';
					$return .= $markedbycomment[0];
					$return .= '</em></p>';
				//If not marked for review
				} else {
					//Can guests mark it?
					if ($devOptions['allow_unregistered'] == "true" ) {
						//Guests can mark, is it excluded?
						if ( ! $excluded ) {
							//Be advised this same stuff should appear here and just below, so if you change one, change both!
							$return .= '<div><p><button id="SuggestReviewButton">Flag this information for review</button></p></div>

<div id="SuggestReviewComment" style="display:none"><p style="margin-bottom:0px;"><strong>Suggest Review:</strong> Please leave a comment below explaining why.</p>
<form id="SuggestReviewForm" method="post" action="'.$_SERVER["REQUEST_URI"].'"><input type="hidden" name="suggestreview" value="1"><input type="hidden" name="suggestreviewid" value="'.$my_post_id.'"><input id="SuggestReviewRealSubmitButton" type="submit" style="display:none;">
<textarea rows="4" name="suggestreviewcomment" style="width:85%; margin-top:-5px; margin-bottom:3px;"></textarea>
</form>
<p><button id="SuggestReviewCancelButton" style="float:left;">Cancel</button><button id="SuggestReviewSubmitButton" style="float:left; margin-left:50px;">Submit</button></p><br><br>
</div>

<script>
jQuery( "#SuggestReviewButton").click(function(e){
		e.preventDefault;
		jQuery("#SuggestReviewComment").toggle();
		jQuery("#SuggestReviewButton").toggle();
	});
jQuery( "#SuggestReviewCancelButton").click(function(e){
		e.preventDefault;
		jQuery("#SuggestReviewComment").toggle();
		jQuery("#SuggestReviewButton").toggle();
	});
jQuery( "#SuggestReviewSubmitButton" ).click(function(e){
        e.preventDefault;
        jQuery("#SuggestReviewRealSubmitButton").click();
    });
</script>';
						}
					} else {
						//If guests can't mark, is anyone logged in?
						if ( is_user_logged_in() ) {
							//User is logged in, is it excluded?
							if ( ! $excluded ) {
								//Be advised this same stuff should appear here and just above, so if you change one, change both!
								$return .= '<div><p><button id="SuggestReviewButton">Flag this information for review</button></p></div>

<div id="SuggestReviewComment" style="display:none"><p style="margin-bottom:0px;"><strong>Suggest Review:</strong> Please leave a comment below explaining why.</p>
<form id="SuggestReviewForm" method="post" action="'.$_SERVER["REQUEST_URI"].'"><input type="hidden" name="suggestreview" value="1"><input type="hidden" name="suggestreviewid" value="'.$my_post_id.'"><input id="SuggestReviewRealSubmitButton" type="submit" style="display:none;">
<textarea rows="4" name="suggestreviewcomment" style="width:85%; margin-top:-5px; margin-bottom:3px;"></textarea>
</form>
<p><button id="SuggestReviewCancelButton" style="float:left;">Cancel</button><button id="SuggestReviewSubmitButton" style="float:left; margin-left:50px;">Submit</button><br></p>
</div>

<script>
jQuery( "#SuggestReviewButton").click(function(e){
		e.preventDefault;
		jQuery("#SuggestReviewComment").toggle();
		jQuery("#SuggestReviewButton").toggle();
	});
jQuery( "#SuggestReviewCancelButton").click(function(e){
		e.preventDefault;
		jQuery("#SuggestReviewComment").toggle();
		jQuery("#SuggestReviewButton").toggle();
	});
jQuery( "#SuggestReviewSubmitButton" ).click(function(e){
        e.preventDefault;
        jQuery("#SuggestReviewRealSubmitButton").click();
    });
</script>';
							}
						}
					}
				}

			} //end if permalink

			return $return;
		}

		//Gets the settings link to show on the plugin management page
		//Thanks to "Floating Social Bar" plugin as the code is humbly taken from it
		//Since 1.1.0
		function settings_link( $links ) {

			$setting_link = sprintf( '<a href="%s">%s</a>', add_query_arg( array( 'page' => 'suggest-review.php' ), admin_url( 'options-general.php' ) ), __( 'Settings', 'Suggest Review' ) );
			array_unshift( $links, $setting_link );
			return $links;

		}

		//Prints out the admin page
		function printAdminPage() {

			$devOptions = $this->getAdminOptions();

			if ( isset($_POST['update_suggestReviewSettings']) ) {
				if ( isset($_POST['suggestReviewAllowUnregistered']) ) {
					$devOptions['allow_unregistered'] = $_POST['suggestReviewAllowUnregistered'];
				}
				if ( isset($_POST['suggestReviewSendEmailToAuthor']) ) {
					$devOptions['send_email_to_author'] = $_POST['suggestReviewSendEmailToAuthor'];
				}
				if ( isset($_POST['suggestReviewSubjectForEmailToAuthor']) ) {
					$devOptions['subject_for_email_to_author'] = apply_filters('content_save_pre', $_POST['suggestReviewSubjectForEmailToAuthor'] );
				}
				if ( isset($_POST['suggestReviewBodyForEmailToAuthor']) ) {
					$devOptions['body_for_email_to_author'] = apply_filters('content_save_pre', $_POST['suggestReviewBodyForEmailToAuthor'] );
				}
				if ( isset($_POST['suggestReviewAddUpdateDate']) ) {
					$devOptions['add_update_date_to_posts'] = $_POST['suggestReviewAddUpdateDate'];
				}
				if ( isset($_POST['suggestReviewIDsToExclude']) ) {
					$devOptions['exclude_ids'] = apply_filters( 'content_save_pre', $_POST['suggestReviewIDsToExclude'] );
				}
				if ( isset($_POST['suggestReviewAddressForDigest']) ) {
					$devOptions['address_for_digest_email'] = apply_filters( 'content_save_pre', $_POST['suggestReviewAddressForDigest'] );
				}
				if ( isset($_POST['suggestReviewSubjectForDigestEmail']) ) {
					$devOptions['subject_for_digest_email'] = apply_filters( 'content_save_pre', $_POST['suggestReviewSubjectForDigestEmail'] );
				}
				if ( isset($_POST['suggestReviewBodyForDigestEmail']) ) {
					$devOptions['body_for_digest_email'] = apply_filters( 'content_save_pre', $_POST['suggestReviewBodyForDigestEmail'] );
				}
				if ( isset($_POST['suggestReviewItemForDigestEmail']) ) {
					$devOptions['item_for_digest_email'] = apply_filters( 'content_save_pre', $_POST['suggestReviewItemForDigestEmail'] );
				}
				update_option($this->adminOptionsName, $devOptions);

				?>
<div class="updated"><p><strong><?php _e("Settings Updated.", "SuggestReview");?></strong></p></div>
				<?php
			} ?>

<div class=wrap>
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<div id="suggest-review_option_page" style="width:80%">
  <div id="column1" style="float:left; width:50%;">
    <h2>Suggest Review Options</h2>

    <h3 style="margin-bottom:0">Allow unregistered users to mark for review</h3>
    <p style="margin-top:0"><label for="suggestReviewAllowUnregistered_yes"><input type="radio" id="suggestReviewAllowUnregistered_yes" name="suggestReviewAllowUnregistered" value="true" <?php if ($devOptions['allow_unregistered'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAllowUnregistered_no"><input type="radio" id="suggestReviewAllowUnregistered_no" name="suggestReviewAllowUnregistered" value="false" <?php if ($devOptions['allow_unregistered'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

    <h3 style="margin-bottom:0">Send email to author when content is marked for review</h3>
    <p style="margin-top:0"><label for="suggestReviewSendEmailToAuthor_yes"><input type="radio" id="suggestReviewSendEmailToAuthor_yes" name="suggestReviewSendEmailToAuthor" value="true" <?php if ($devOptions['send_email_to_author'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewSendEmailToAuthor_no"><input type="radio" id="suggestReviewSendEmailToAuthor_no" name="suggestReviewSendEmailToAuthor" value="false" <?php if ($devOptions['send_email_to_author'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

    <h3 style="margin-bottom:0">Subject line for email to author</h3>
    <p style="margin-top:0">Supports limited variables which are case sensitive. (<a href="#" onclick="jQuery('#legend').toggle();">Legend</a>)<br>
    <input type="text" name="suggestReviewSubjectForEmailToAuthor" style="width:100%;" value="<?php _e(apply_filters('format_to_edit',$devOptions['subject_for_email_to_author']), 'SuggestReview') ?>"></p>

    <h3 style="margin-bottom:0">Body for email to author</h3>
    <p style="margin-top:0">Supports limited variables which are case sensitive. (<a href="#" onclick="jQuery('#legend').toggle();">Legend</a>)<br>
    <textarea rows="4" name="suggestReviewBodyForEmailToAuthor" style="width:100%;"><?php _e(apply_filters('format_to_edit',$devOptions['body_for_email_to_author']), 'SuggestReview') ?></textarea></p>

    <h3 style="margin-bottom:0">Add last update date to end of posts</h3>
    <p style="margin-top:0"><label for="suggestReviewAddUpdateDate_yes"><input type="radio" id="suggestReviewAddUpdateDate_yes" name="suggestReviewAddUpdateDate" value="true" <?php if ($devOptions['add_update_date_to_posts'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_no"><input type="radio" id="suggestReviewAddUpdateDate_no" name="suggestReviewAddUpdateDate" value="false" <?php if ($devOptions['add_update_date_to_posts'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

    <h3 style="margin-bottom:0">Page, post IDs to exclude</h3>
    <p style="margin-top:0">This is the comma-separated list of IDs that will not show the suggest review button. The 'last update' text will still show if that option is selected.<br>
    <input type="text" name="suggestReviewIDsToExclude" style="width:100%;" value="<?php _e(apply_filters('format_to_edit',$devOptions['exclude_ids']), 'SuggestReview') ?>"></p>

    <h3 style="margin-bottom:0">Addresses for digest email</h3>
    <p style="margin-top:0">This is the comma-separated list of addresses that will receive weekly emails about what content has been flagged for review.<br>
    <input type="text" name="suggestReviewAddressForDigest" style="width:100%;" value="<?php _e(apply_filters('format_to_edit',$devOptions['address_for_digest_email']), 'SuggestReview') ?>"></p>

    <h3 style="margin-bottom:0">Subject line for digest email</h3>
    <p style="margin-top:0"><input type="text" name="suggestReviewSubjectForDigestEmail" style="width:100%;" value="<?php _e(apply_filters('format_to_edit',$devOptions['subject_for_digest_email']), 'SuggestReview') ?>"></p>

    <h3 style="margin-bottom:0">Body for digest email</h3>
    <p style="margin-top:0">A blank line will be inserted after this and before the post items. Supports limited variables which are case sensitive. (<a href="#" onclick="jQuery('#legend').toggle();">Legend</a>)<br>
    <textarea rows="4" name="suggestReviewBodyForDigestEmail" style="width:100%;"><?php _e(apply_filters('format_to_edit',$devOptions['body_for_digest_email']), 'SuggestReview') ?></textarea></p>

    <h3 style="margin-bottom:0">Post items for digest email body</h3>
    <p style="margin-top:0">Each item marked for review will appear after the "Body for digest email". A blank line will separate each item. Supports limited variables which are case sensitive. (<a href="#" onclick="jQuery('#legend').toggle();">Legend</a>)<br>
    <textarea rows="4" name="suggestReviewItemForDigestEmail" style="width:100%;"><?php _e(apply_filters('format_to_edit',$devOptions['item_for_digest_email']), 'SuggestReview') ?></textarea></p>

    <div class="submit">
      <input type="submit" name="update_suggestReviewSettings" value="<?php _e('Update Settings', 'SuggestReview') ?>" />
    </div>

    <p>Like it? Hate it? Give it a review!<br><a href='http://wordpress.org/support/view/plugin-reviews/suggest-review' target='_blank'>http://wordpress.org/support/view/plugin-reviews/suggest-review</a></p>
  </form>
  </div>
  <div id="column2" style="float:right; width:40%; padding-top:25%;">
    <div id="legend" style="display:none;">
    <h3 style="text-align:center">Variable Legend</h3>
    <table>
      <tbody>
        <tr><td style="float:right;"><code><strong>[suggesting_user_login]</strong></code></td><td><?php _e('The login name of the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[suggesting_user_email]</strong></code></td><td><?php _e('The email address of the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[suggesting_user_firstname]</strong></code></td><td><?php _e('The first name of the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[suggesting_user_lastname]</strong></code></td><td><?php _e('The last name of the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[suggesting_user_display_name]</strong></code></td><td><?php _e('The display name of the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[comment]</strong></code></td><td><?php _e('The comment from the user who flagged the item', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[author_login]</strong></code></td><td><?php _e('The login name of the post author', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[author_email]</strong></code></td><td><?php _e('The email address of the post author', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[author_firstname]</strong></code></td><td><?php _e('The first name of the post author', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[author_lastname]</strong></code></td><td><?php _e('The last name of the post author', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[author_display_name]</strong></code></td><td><?php _e('The display name of the post author', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[post_title]</strong></code></td><td><?php _e('The title of the post', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[post_date]</strong></code></td><td><?php _e('The original publish date of the post', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[post_modified]</strong></code></td><td><?php _e('The last modified date of the post', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[permalink]</strong></code></td><td><?php _e('The permalink to the post', 'suggest-review' ); ?></td></tr>
      </tbody>
    </table>
    </div>
  </div>
</div>
</div>


					<?php
		}//End function printAdminPage()

	}
} //End Class SuggestReview

if ( class_exists( "SuggestReview" ) ) {
	$svvsd_suggestReview = new SuggestReview();
}

//Initialize the admin panel
if ( ! function_exists( "suggestReview_ap" ) ) {
	function suggestReview_ap() {
		global $svvsd_suggestReview;
		if ( ! isset( $svvsd_suggestReview ) ) {
			return;
		}
		if ( function_exists( 'add_options_page' ) ) {
	add_options_page( 'Suggest Review', 'Suggest Review', 9, basename( __FILE__ ), array( &$svvsd_suggestReview, 'printAdminPage' ) );
		}
	}	
}

function add_markresolve_box() {
	add_meta_box(
 			'suggest_review_meta_box'
			,__( 'Resolve Suggest Review Flag' )
			,'render_meta_box_content'
			,get_post_type( get_the_ID() )
			,'side'
			,'high'
		);
}

function render_meta_box_content( $post ) {
	$needsReview = get_post_meta( $post->ID, 'suggestreview_needed' );
	if ( ! empty( $needsReview ) && $needsReview[0] == 'true' ) {
		$markedbyuser = get_post_meta( $post->ID, 'suggestreview_by' );
		$markedbydate = get_post_meta( $post->ID, 'suggestreview_date' );
		echo "<p>Review suggested by $markedbyuser[0] on $markedbydate[0]</p>";
		echo '<label for="suggestReview_resolveflag"><input type="checkbox" id="suggestReview_resolveflag" name="suggestReview_resolveflag" value="suggestReview_resolveflag" checked /> Remove this flag</label>';
	} else {
		echo '<p>This post has not been suggested for review.</p>';
		?><!-- in render_meta_box_content false statement--><?php
	}
	return true;
}

function updateLastUpdated( $post_id ) {
	$my_user = wp_get_current_user();

	if ( isset($_POST['suggestReview_resolveflag']) ) {
		update_post_meta( $post_id, 'suggestreview_needed', 'false' );
		update_post_meta( $post_id, 'suggestreview_by', $my_user->user_login );
		update_post_meta( $post_id, 'suggestreview_date', date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ) );
	}

	update_post_meta( $post_id, 'suggestreview_lastupdated', date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ) );
	update_post_meta( $post_id, 'suggestreview_lastupdatedby', $my_user->user_login );
	return true;
}

function my_activation() {
	wp_schedule_event( time(), 'weekly', 'suggestreviewdigest');
//	wp_schedule_event( time(), 'quarterhourly', 'suggestreviewdigest');
}

function my_deactivation() {
	wp_clear_scheduled_hook('suggestreviewdigest');
}

function my_add_weekly( $schedules ) {
	// add a 'weekly' schedule to the existing set
	$schedules['weekly'] = array(
		'interval' => 604800,
		'display' => __('Once Weekly')
//	$schedules['quarterhourly'] = array(
//		'interval' => 900,
//		'display' => __('Once every 15')
	);
	return $schedules;
}

function digest_email() {
	//Vars
	global $wpdb;
	global $post;
	global $svvsd_suggestReview;
	$devOptions = $svvsd_suggestReview->getAdminOptions();

	//Get the posts that are marked for review
	$querystr = "
			SELECT *
			FROM ".$wpdb->posts." wposts
			INNER JOIN ".$wpdb->postmeta." wpostmeta
			ON wposts.ID = wpostmeta.post_id
			WHERE wpostmeta.meta_key = 'suggestreview_needed'
			AND  wpostmeta.meta_value = 'true'
			";
	$posts_for_digest = $wpdb->get_results($querystr, OBJECT);

	//If we have any posts, make some mail
	if ( ! empty( $posts_for_digest ) ) {
		//First part of body comes from options
		$body_string = replaceTokens( $devOptions['body_for_digest_email'], $post->ID );
		$body_string .= '

';
		//Post items to go in body
		foreach ( $posts_for_digest as $post ) {
			setup_postdata( $post );
			$body_string .= replaceTokens( $devOptions['item_for_digest_email'], $post->ID );
			$body_string .= '

';
		}
		$subject_line = $devOptions['subject_for_digest_email'];
		$addresses = explode( ',', $devOptions['address_for_digest_email']);
		foreach ( $addresses as $address ) {
				wp_mail( $address
					,$subject_line
					,$body_string
					);
		}
	}
}

//Replaces token variables in email templates
//Since 1.1.0
function replaceTokens( $text, $post_id ) {
	//Get meta
	$marking_user_login = get_post_meta( $post_id, 'suggestreview_by');
	$marked_date = get_post_meta( $post_id, 'suggestreview_date' );
	$marked_comment = get_post_meta( $post_id, 'suggestreview_comment' );
	$permalink = get_permalink( $post_id );

	//Setup suggesting user
	$marking_user = get_user_by( 'login', $marking_user_login[0] );
	//If it was marked by guest, setup guest user
	if ( $my_user == false ) {
		$my_user = new WP_User( 0 );
		$my_user->user_login = 'guest';
		$my_user->user_email = 'guest';
		$my_user->user_firstname = 'guest';
		$my_user->user_lastname = 'user';
		$my_user->display_name = 'Guest User';
	}

	//Get author
	$post = get_post( $post_id );
	$author = get_user_by( 'id', $post->post_author );

	$tokens = array( '[suggesting_user_login]' => $marking_user->user_login,
					'[suggesting_user_email]' => $marking_user->user_email,
					'[suggesting_user_firstname]' => $marking_user->user_firstname,
					'[suggesting_user_lastname]' => $marking_user->user_lastname,
					'[suggesting_user_display_name]' => $marking_user->display_name,
					'[author_login]' => $author->user_login,
					'[author_email]' => $author->user_email,
					'[author_firstname]' => $author->user_firstname,
					'[author_lastname]' => $author->user_lastname,
					'[author_display_name]' => $author->display_name,
					'[post_title]' => $post->post_title,
					'[post_date]' => $post->post_date,
					'[post_modified]' => $post->post_modified,
					'[permalink]' => $permalink,
					'[comment]' => $marked_comment[0]
					);

	foreach ( $tokens as $key => $value ) {
		$text = str_replace( $key, $value, $text );
	}

	return $text;
}

//Actions and Filters
if ( isset( $svvsd_suggestReview ) ) {
	register_activation_hook( __FILE__, 'my_activation' );
	register_deactivation_hook( __FILE__, 'my_deactivation' );

	//Filters
	add_filter( 'the_content', array( &$svvsd_suggestReview, 'content_insertion' ), 10 );
	add_filter( 'cron_schedules', 'my_add_weekly' );
	add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'suggest-review.php' ), array( &$svvsd_suggestReview, 'settings_link' ) );

	//Actions
	add_action( 'admin_menu', 'suggestReview_ap' );
	add_action( 'activate_suggest_review/suggest-review.php',  array( &$svvsd_suggestReview, 'init' ) );
	add_action( 'add_meta_boxes', 'add_markresolve_box' );
	add_action( 'save_post', 'updateLastUpdated' );
	add_action( 'suggestreviewdigest', 'digest_email' );
}
?>
<?php
/**
 * @package Suggest_Review
 * @version 1.3.2
 */
/*
Plugin Name: Suggest Review
Plugin URI: http://wordpress.org/plugins/suggest-review/
Description: Lets users suggest that content may need to be reviewed or re-examined.
Author: Michael George
Version: 1.3.2

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
			$suggestReviewAdminOptions = array(
                'allow_unregistered' => 'false'
				,'send_email_to_author' => 'true'
				,'subject_for_email_to_author' => 'Blog content flagged for review'
				,'body_for_email_to_author' => 'Content you authored on the blog has been suggested for review. You should check this content and any comments on it and then post an update.

Title: [post_title]
Suggested by: [suggesting_user_login]
Comment: [comment]

View the content at: [permalink]'
				,'add_update_date_to_posts' => 1 // 1 for yes, 2 for yes if not excluded, 0 for no. Since 1.2.3
				,'footer_alignment' => 'left' // since 1.2.4
				,'show_comment' => 1 // since 1.3.0. Whether or not to show comments on post when flagged
				,'excluded_ids' => ''
				,'address_for_digest_email' => ''
				,'subject_for_digest_email' => 'Blog content flagged for review'
				,'body_for_digest_email' => 'The following content has been marked for review on the blog.'
				,'item_for_digest_email' => '
Title: [post_title]
Author: [author_login]
Last Modified: [post_modified]
Link: [permalink]'
                ,'flag_button_text' => 'Flag this information for review' // since 1.3.1
                );
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
					,suggestReview_replaceTokens( $devOptions['subject_for_email_to_author'], $post_id )
					,suggestReview_replaceTokens( $devOptions['body_for_email_to_author'], $post_id ) );
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
			// Bug fix in 1.2.5: The check below was hard coded to using https and is now dynamic
			if ( "http" . ( $_SERVER['HTTPS'] == "on" ? "s" : "" ) . "://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] == get_permalink() ) {
				echo "<!-- sr content_insertion found uri is permalink -->\r";
				if ( isset( $_POST['suggestreview'] ) && $_POST['suggestreview'] == '1' && isset( $_POST['suggestreviewid'] ) && $_POST['suggestreviewid'] == $my_post_id ) {
					$this->markForReview( $_POST['suggestreviewid'], $my_user->user_login, date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ), $_POST['suggestreviewcomment'], $my_author );
				} else if ( isset( $_POST['resolvereview'] ) && $_POST['resolvereview'] == '1' && isset( $_POST['resolvereviewid'] ) && $_POST['resolvereviewid'] == $my_post_id ) {
					$this->resolveMarkForReview( $_POST['resolvereviewid'] );
				}

				$excludedIDs = explode( ',', $devOptions['exclude_ids']);
				$excluded = in_array( $my_post_id, $excludedIDs);
				$needsReview = get_post_meta( $my_post_id, 'suggestreview_needed' );

				//See if we should put in the last update time
				if ( $devOptions['add_update_date_to_posts'] == 1 || ( $devOptions['add_update_date_to_posts'] == 2 && ! $excluded ) ) {
					$lastupdated = get_post_meta( $my_post_id, 'suggestreview_lastupdated' );
					$lastupdatedby = get_post_meta( $my_post_id, 'suggestreview_lastupdatedby' );
					$return .= '<p style=\'text-align: ' . $devOptions['footer_alignment'] . '\'>Last updated by '.$lastupdatedby[0].' on '.$lastupdated[0].'</p>';
				}

				//If this has been marked for review
				if ( ! empty($needsReview) && $needsReview[0] == "true" && ! $excluded ) {
					$markedbyuser = get_post_meta( $my_post_id, 'suggestreview_by' );
					$markedbydate = get_post_meta( $my_post_id, 'suggestreview_date' );
					$markedbycomment = get_post_meta( $my_post_id, 'suggestreview_comment' );
					$return .= '<p style=\'text-align: ' . $devOptions['footer_alignment'] . '\'>The above was flagged for review by ';
					$return .= $markedbyuser[0];
					$return .= ' on ';
					$return .= $markedbydate[0];
					if ( $devOptions['show_comment'] ) {
						$return .= '<br>Comment: <em>';
						$return .= $markedbycomment[0];
						$return .= '</em>';
					}
					$return .= '</p>';
				//If not marked for review
				} else {
                    $contentToInsert = '<div><p style=\'text-align: ' . $devOptions['footer_alignment'] . '\'><button id="SuggestReviewButton">' . $devOptions['flag_button_text'] . '</button></p></div>

<div id="SuggestReviewComment" style="display:none"><p style="margin-bottom:0px;"><strong>Suggest Review:</strong> Please leave a comment below explaining why.</p>
<form id="SuggestReviewForm" method="post" action="'.$_SERVER["REQUEST_URI"].'"><input type="hidden" name="suggestreview" value="1"><input type="hidden" name="suggestreviewid" value="'.$my_post_id.'"><input id="SuggestReviewRealSubmitButton" type="submit" style="display:none;">
<textarea rows="4" name="suggestreviewcomment" style="width:85%; margin:2px 0px 0px 5px;"></textarea>
</form>
<p><button id="SuggestReviewCancelButton" style="float:left;">Cancel</button><button id="SuggestReviewSubmitButton" style="float:left; margin-left:50px;">Submit</button></p><br>
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
					//Can guests mark it?
					if ($devOptions['allow_unregistered'] == "true" ) {
						//Guests can mark, is it excluded?
						if ( ! $excluded ) {
							//Be advised this same stuff should appear here and just below, so if you change one, change both!
							$return .= $contentToInsert;
						}
					} else {
						//If guests can't mark, is anyone logged in?
						if ( is_user_logged_in() ) {
							//User is logged in, is it excluded?
							if ( ! $excluded ) {
								//Be advised this same stuff should appear here and just above, so if you change one, change both!
								$return .= $contentToInsert;
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

		//Add the custom Bulk Action to the select menus. Snagged from Justin Stern (http://www.skyverge.com/blog/add-custom-bulk-action/).
		// Since 1.2.2
		function custom_bulk_admin_footer() {
			global $post_type;
			
			if($post_type == 'post') {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery('<option>').val('exclude').text('<?php _e('Exclude from SuggestReview')?>').appendTo("select[name='action']");
							jQuery('<option>').val('exclude').text('<?php _e('Exclude from SuggestReview')?>').appendTo("select[name='action2']");
						});
					</script>
				<?php
	    	}
		}

		//Handles the custom Bulk Action. Snagged from Justin Stern (http://www.skyverge.com/blog/add-custom-bulk-action/).
		//Since 1.2.2
		function custom_bulk_action() {
			global $typenow;
			$post_type = $typenow;

			if($post_type == 'post') {
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
				$action = $wp_list_table->current_action();

				$allowed_actions = array("exclude");
				if(!in_array($action, $allowed_actions)) return;
				
				// security check
				check_admin_referer('bulk-posts');

				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if(isset($_GET['post'])) {
					$post_ids = array_map('intval', $_GET['post']);
				}
				if(empty($post_ids)) return;
				
				// this is based on wp-admin/edit.php
				$sendback = remove_query_arg( array('excluded', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
				if ( ! $sendback )
					$sendback = admin_url( "edit.php?post_type=$post_type" );
				
				$pagenum = $wp_list_table->get_pagenum();
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );

				switch($action) {
					case 'exclude':
						
						// if we set up user permissions/capabilities, the code might look like:
						//if ( !current_user_can($post_type_object->cap->exclude_post, $post_id) )
						//	wp_die( __('You are not allowed to exclude this post.') );
						
						$excluded = 0;
						foreach( $post_ids as $post_id ) {

							if ( !$this->perform_exclusion($post_id) )
								wp_die( __('Error excluding post.') );
			
							$excluded++;
						}
						
						$sendback = add_query_arg( array('excluded' => $excluded, 'ids' => join(',', $post_ids) ), $sendback );
					break;
					
					default: return;
				}
				
				$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
				
				wp_redirect($sendback);
				exit();
			}
		}

		//Displays notice on admin page after bulk excluding. Snagged from Justin Stern (http://www.skyverge.com/blog/add-custom-bulk-action/).
		//Since 1.2.2
		function custom_bulk_admin_notices() {
			global $post_type, $pagenow;
			
			if($pagenow == 'edit.php' && $post_type == 'post' && isset($_GET['excluded']) && (int) $_GET['excluded']) {
				$message = sprintf( _n( 'Post excluded from SuggestReview.', '%s posts excluded from SuggestReview.', $_GET['excluded'] ), number_format_i18n( $_GET['excluded'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
		}
		
		//Actually does the exclusion for bulk exclude. Snagged from Justin Stern (http://www.skyverge.com/blog/add-custom-bulk-action/).
		//Since 1.2.2
		function perform_exclusion($post_id) {
			$devOptions = $this->getAdminOptions();
			$excludedIDs = explode( ',', $devOptions['exclude_ids']);
			$is_excluded = in_array( $post_id, $excludedIDs);

			if ( !$is_excluded ) {
				$devOptions['exclude_ids'] = $devOptions['exclude_ids'] . "," . $post_id;
				$devOptions['exclude_ids'] = apply_filters( 'content_save_pre', $devOptions['exclude_ids'] );
				update_option($this->adminOptionsName, $devOptions);
			}

			return true;
		}

		//Prints out the admin settings page
		//Since 1.0.0
		//Renamed in 1.3.0 from printAdminPage to printSettingsPage as there are now 2 admin pages
		function printSettingsPage() {

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
				if ( isset($_POST['suggestReviewFooterAlignment']) ) {
					$devOptions['footer_alignment'] = $_POST['suggestReviewFooterAlignment'];
				}
				if ( isset($_POST['suggestReviewShowComment']) ) {
					$devOptions['show_comment'] = ( $_POST['suggestReviewShowComment'] == 'true' ? 1 : 0 );
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
				if ( isset($_POST['suggestReviewFlagButtonText']) ) {
					$devOptions['flag_button_text'] = apply_filters( 'content_save_pre', $_POST['suggestReviewFlagButtonText'] );
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
    <p style="margin-top:0"><label for="suggestReviewAddUpdateDate_yes"><input type="radio" id="suggestReviewAddUpdateDate_yes" name="suggestReviewAddUpdateDate" value="1" <?php if ($devOptions['add_update_date_to_posts'] == 1) { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_yesifnot"><input type="radio" id="suggestReviewAddUpdateDate_yesifnot" name="suggestReviewAddUpdateDate" value="2" <?php if ($devOptions['add_update_date_to_posts'] == 2) { _e('checked="checked"', "SuggestReview"); }?> /> Yes, if not in exclusion list</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_no"><input type="radio" id="suggestReviewAddUpdateDate_no" name="suggestReviewAddUpdateDate" value="0" <?php if ($devOptions['add_update_date_to_posts'] == 0) { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

    <h3 style="margin-bottom:0">Footer alignment (affects last update date and button)</h3>
    <p style="margin-top:0"><label for="suggestReviewFooterAlignment_left"><input type="radio" id="suggestReviewFooterAlignment_left" name="suggestReviewFooterAlignment" value="left" <?php if ($devOptions['footer_alignment'] == 'left') { _e('checked="checked"', "SuggestReview"); }?> /> Left</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewFooterAlignment_center"><input type="radio" id="suggestReviewFooterAlignment_center" name="suggestReviewFooterAlignment" value="center" <?php if ($devOptions['footer_alignment'] == 'center') { _e('checked="checked"', "SuggestReview"); }?> /> Center</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewFooterAlignment_right"><input type="radio" id="suggestReviewFooterAlignment_right" name="suggestReviewFooterAlignment" value="right" <?php if ($devOptions['footer_alignment'] == 'right') { _e('checked="checked"', "SuggestReview"); }?>/> Right</label></p>

    <h3 style="margin-bottom:0">Show comment on post when flagged</h3>
    <p style="margin-top:0"><label for="suggestReviewShowComment_yes"><input type="radio" id="suggestReviewShowComment_yes" name="suggestReviewShowComment" value="true" <?php if ($devOptions['show_comment'] ) { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewShowComment_no"><input type="radio" id="suggestReviewShowComment_no" name="suggestReviewShowComment" value="false" <?php if ( ! $devOptions['show_comment'] ) { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

    <h3 style="margin-bottom:0">Flag button text</h3>
    <input type="text" name="suggestReviewFlagButtonText" style="width:100%;" value="<?php _e(apply_filters('format_to_edit',$devOptions['flag_button_text']), 'SuggestReview') ?>"></p>

    <h3 style="margin-bottom:0">Page, post IDs to exclude</h3>
    <p style="margin-top:0">This is the comma-separated list of IDs that will not show the suggest review button. The 'last update' text will still show, depending on the above option.<br>
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
		}//End function printSettingsPage()

		//Display the flagged posts page, which shows a user all the posts they have edit
		//access to that have been suggested for review
		//Since 1.3.0
		function printFlaggedPostsPage() {
			global $wpdb;
			$orderbyoptions = array( "postdate", "author", "srby", "srdate" );
			$orderby = ( isset( $_GET['srfporderby'] ) && in_array( $_GET['srfporderby'], $orderbyoptions ) ? $_GET['srfporderby'] : 'postdate' );
			$orderdir = ( isset( $_GET['srfporderdir'] ) && in_array( $_GET['srfporderdir'], array( "asc", "desc" ) ) ? $_GET['srfporderdir'] : 'desc' );
			$args = array(
				'orderby' => 'date'
				,'order' => 'ASC'
				,'meta_query' => array(
					array(
						'key' => 'suggestreview_needed'
						,'value' => 'true'
						,'compare' => 'LIKE'
						)
					)
				);
			$rows = query_posts( $args );
			if ( $rows ) {
				//echo "<!-- " . print_r( $rows, true ) . " -->\r";
				//Main array for holding results, others for sorting
				$sortedposts = array();

				//building the array from the posts. makes for quick sorting later
				foreach ( $rows as $post ) {
					setup_postdata( $post );
					if ( current_user_can( 'edit_post', $post->ID ) ) {
						$sortedposts[] = array(
								"ID" => $post->ID
								,"post_title" => $post->post_title
								,"post_date" => strtotime( $post->post_date )
								,"author" => get_user_by( 'id', $post->post_author )->user_login
								,"srby" => get_post_meta( $post->ID, 'suggestreview_by', true )
								,"srcom" => get_post_meta( $post->ID, 'suggestreview_comment', true )
								,"srdate" => strtotime( get_post_meta( $post->ID, 'suggestreview_date', true ) )
								);
					}
				}

				//Sort it. Why not sort in the query you ask? Well, some of the meta data doesn't exist
				//at that point, so we can't.
				$postdates = array();
				$authors = array();
				$srbys = array();
				$srdates = array();
				foreach ( $sortedposts as $key => $row ) {
					$postdates[$key] = $row['post_date'];
					$authors[$key] = $row['author'];
					$srbys[$key] = $row['srby'];
					$srdates[$key] = $row['srdate'];
				}
				array_multisort( ( $orderby == 'postdate' ? $postdates :
									( $orderby == 'author' ? $authors :
										( $orderby == 'srby' ? $srbys :
											( $orderby == 'srdate' ? $srdates : $postdates ) ) ) )
								,( $orderdir == 'asc' ? SORT_ASC : SORT_DESC )
								,$sortedposts );
			}

			//Build the page, including the table of posts
			if ( count( $sortedposts ) > 0 ) {
				$baseURL = suggestReview_delArgFromURL( $_SERVER['REQUEST_URI'], array( 'srfporderby', 'srfporderdir' ) );
				echo "\r\t<div id='suggestreview_flaggedposts'>\r";
				echo "\t<style>\r";
				echo "\t\ttable { border-collapse: collapse; border: 1px solid black; }\r";
				echo "\t\ttable th, td { padding: 3px; }\r";
				echo "\t\t.tablerow { background-color: white; }\r";
				echo "\t\t.tablerowalt { background-color: #F0F0F0; }\r";
				echo "\t</style>\r";
				echo "\t\t<h2>Posts Flagged for Review</h2>\r";
				echo "\t\t<p>This page lists all of the posts you have access to that have been suggested for a review of their content.</p>\r";
				echo "\t\t<table border=1>\r";
				echo "\t\t\t<thead>\r";
				echo "\t\t\t\t<tr class='tablerow'><th style='text-align: left;'>Title</th>" .
					"<th><a href='" . $baseURL . "&srfporderby=author&srfporderdir=" . ( $orderby == 'author' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>Author</a>" . ( $orderby == 'author' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>" .
					"<th><a href='" . $baseURL . "&srfporderby=postdate&srfporderdir=" . ( $orderby == 'postdate' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>Post Date</a>" . ( $orderby == 'postdate' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>" .
					"<th><a href='" . $baseURL . "&srfporderby=srby&srfporderdir=" . ( $orderby == 'srby' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>Flagged By</a>" . ( $orderby == 'srby' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>" .
					"<th><a href='" . $baseURL . "&srfporderby=srdate&srfporderdir=" . ( $orderby == 'srdate' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>Flagged On</a>" . ( $orderby == 'srdate' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th><tr>\r";
				echo "\t\t\t</thead>\r";
				echo "\t\t\t<tbody>\r";
				$i = 0;
				foreach ( $sortedposts as $post ) {
					echo "\t\t\t\t<tr class='tablerow" . ( $i % 2 == 0 ? "alt" : "" ) . "'>" .
						"<td><a href='" . get_permalink( $post['ID'] ) . "'>" . $post['post_title'] . "</a><br></td>" .
						"<td>" . $post['author'] . "</td>" .
						"<td>" . date( 'Y/m/d', $post['post_date'] ) . "</td>" .
						"<td>" . $post['srby'] . "</td>" .
						"<td>" . date( 'Y/m/d', $post['srdate'] ) . "</td></tr>\r";
					$i++;
				}
				echo "\t\t\t</tbody>\r";
				echo "\t\t</table>\r";
				echo "\t</div>\r";
			}

		}

		//Add admin page(s) to dashboard
		//Since 1.3.0
		function adminMenu() {
			//If user is an admin, show all the options, otherwise just show the flagged posts page
			if ( current_user_can( 'manage_options' ) ) {
				add_menu_page( 'Suggest Review Settings', 'Suggest Review', 'manage_options', 'suggestreview_settings', array( $this, 'printSettingsPage' ), 'dashicons-flag' );
				add_submenu_page( 'suggestreview_settings', 'Suggest Review Settings', 'Settings', 'manage_options', 'suggestreview_settings', array( $this, 'printSettingsPage' ) );
				add_submenu_page( 'suggestreview_settings', 'Posts Suggested for Review', 'Flagged Posts', 'edit_posts', 'suggestreview_posts', array( $this, 'printFlaggedPostsPage' ) );
			} else {
				add_menu_page( 'Posts Suggested for Review', 'SR Flagged Posts', 'edit_posts', 'suggestreview_posts', array( $this, 'printFlaggedPostsPage' ), 'dashicons-flag' );
			}
		}

	}
} //End Class SuggestReview

if ( class_exists( "SuggestReview" ) ) {
	$svvsd_suggestReview = new SuggestReview();
}

function suggestReview_addMarkresolveBox() {
	add_meta_box(
 			'suggest_review_meta_box'
			,__( 'Resolve Suggest Review Flag' )
			,'suggestReview_renderMetaBoxContent'
			,get_post_type( get_the_ID() )
			,'side'
			,'high'
		);
}

function suggestReview_renderMetaBoxContent( $post ) {
	global $svvsd_suggestReview;
	$needsReview = get_post_meta( $post->ID, 'suggestreview_needed' );
	if ( ! empty( $needsReview ) && $needsReview[0] == 'true' ) {
		$markedbyuser = get_post_meta( $post->ID, 'suggestreview_by' );
		$markedbydate = get_post_meta( $post->ID, 'suggestreview_date' );
		echo "<p>Review suggested by $markedbyuser[0] on $markedbydate[0]<br>";
		echo '<label for="suggestReview_resolveflag"><input type="checkbox" id="suggestReview_resolveflag" name="suggestReview_resolveflag" value="suggestReview_resolveflag" checked /> Remove this flag</label></p>';
	} else {
		echo '<p>This post has not been suggested for review.</p>';
	}
	$devOptions = $svvsd_suggestReview->getAdminOptions();
	$excludedIDs = explode( ',', $devOptions['exclude_ids']);
	$excluded = in_array( $post->ID, $excludedIDs);
	echo '<p>Exclude from SR? <input type="radio" id="suggestReview_excludeflag_yes" name="suggestReview_excludeflag" value="true"';
	if ( $excluded ) {
		echo ' checked';
	}
	echo ' />Yes&nbsp;&nbsp;';
	echo '<input type="radio" id="suggestReview_excludeflag_no" name="suggestReview_excludeflag" value="false"';
	if ( !$excluded ) {
		echo ' checked';
	}
	echo ' />No</p>';
	return true;
}

function suggestReviewUpdatePost( $post_id ) {
	global $svvsd_suggestReview;
	$my_user = wp_get_current_user();
	$devOptions = $svvsd_suggestReview->getAdminOptions();
	$excludedIDs = explode( ',', $devOptions['exclude_ids']);
	$excluded = in_array( $post_id, $excludedIDs);

	if ( isset( $_POST['suggestReview_resolveflag'] ) ) {
		update_post_meta( $post_id, 'suggestreview_needed', 'false' );
		update_post_meta( $post_id, 'suggestreview_by', $my_user->user_login );
		update_post_meta( $post_id, 'suggestreview_date', date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ) );
	}
	if ( isset( $_POST['suggestReview_excludeflag'] ) && $_POST['suggestReview_excludeflag'] == "true" ) {
		if ( !$excluded ) {
			$devOptions['exclude_ids'] = $devOptions['exclude_ids'] . "," . $post_id;
			$devOptions['exclude_ids'] = apply_filters( 'content_save_pre', $devOptions['exclude_ids'] );
			update_option($svvsd_suggestReview->adminOptionsName, $devOptions);
		}
	} else if ( isset( $_POST['suggestReview_excludeflag'] ) && $_POST['suggestReview_excludeflag'] == "false" ) {
		if ( $excluded ) {
			while( ( $key = array_search( $post_id, $excludedIDs ) ) !== false ) {
			    unset( $excludedIDs[$key] );
			}
			$devOptions['exclude_ids'] = implode( ",", $excludedIDs );
			$devOptions['exclude_ids'] = apply_filters( 'content_save_pre', $devOptions['exclude_ids'] );
			update_option($svvsd_suggestReview->adminOptionsName, $devOptions);
		}
	}
	update_post_meta( $post_id, 'suggestreview_lastupdated', date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ) );
	update_post_meta( $post_id, 'suggestreview_lastupdatedby', $my_user->user_login );
	return true;
}

function suggestReviewActivation() {
	wp_schedule_event( time(), 'weekly', 'suggestreviewdigest');
//	wp_schedule_event( time(), 'quarterhourly', 'suggestreviewdigest');
}

function suggestReviewDeactivation() {
	wp_clear_scheduled_hook('suggestreviewdigest');
}

function suggestReview_addWeeklySchedule( $schedules ) {
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

function suggestReview_digestEmail() {
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
		$body_string = suggestReview_replaceTokens( $devOptions['body_for_digest_email'], $post->ID );
		$body_string .= '

';
		//Post items to go in body
		foreach ( $posts_for_digest as $post ) {
			setup_postdata( $post );
			$body_string .= suggestReview_replaceTokens( $devOptions['item_for_digest_email'], $post->ID );
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
function suggestReview_replaceTokens( $text, $post_id ) {
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

//Handy for monkeying with _GET parameters
function suggestReview_delArgFromURL ( $url, $in_arg ) {
	if ( ! is_array( $in_arg ) ) {
		$args = array( $in_arg );
	} else {
		$args = $in_arg;
	}

	$pos = strrpos( $url, "?" ); // get the position of the last ? in the url
	$query_string_parts = array();

	if ( $pos !== false ) {
		foreach ( explode( "&", substr( $url, $pos + 1 ) ) as $q ) {
			list( $key, $val ) = explode( "=", $q );
			//echo "<!-- key: $key value: $val -->\r";
			if ( ! in_array( $key, $args ) ) {
				// keep track of the parts that don't have arg3 as the key
				$query_string_parts[] = "$key=$val";
			}
		}

		// rebuild the url
		$url = substr( $url, 0, $pos + 1 ) . join( $query_string_parts, '&' );

		if ( strrpos( $url, "?" ) == strlen( $url ) - 1 ) {
			$url = strstr( $url, '?', true );
		}
	}
	//echo "<!-- result from delArgFromURL: $url -->\r";
	return $url;
}

//Actions and Filters
if ( isset( $svvsd_suggestReview ) ) {
	register_activation_hook( __FILE__, 'suggestReviewActivation' );
	register_deactivation_hook( __FILE__, 'suggestReviewDeactivation' );

	//Filters
	add_filter( 'the_content', array( &$svvsd_suggestReview, 'content_insertion' ), 10 );
	add_filter( 'cron_schedules', 'suggestReview_addWeeklySchedule' );
	add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'suggest-review.php' ), array( &$svvsd_suggestReview, 'settings_link' ) );

	//Actions
	add_action( 'admin_menu', array( &$svvsd_suggestReview, 'adminMenu' ) );
	add_action( 'activate_suggest_review/suggest-review.php',  array( &$svvsd_suggestReview, 'init' ) );
	add_action( 'add_meta_boxes', 'suggestReview_addMarkresolveBox' );
	add_action( 'save_post', 'suggestReviewUpdatePost' );
	add_action( 'suggestreviewdigest', 'suggestReview_digestEmail' );
	add_action( 'admin_footer-edit.php', array( &$svvsd_suggestReview, 'custom_bulk_admin_footer' ) );
	add_action( 'load-edit.php', array( &$svvsd_suggestReview, 'custom_bulk_action' ) );
	add_action( 'admin_notices', array( &$svvsd_suggestReview, 'custom_bulk_admin_notices' ) );

}
?>
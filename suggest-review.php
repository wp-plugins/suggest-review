<?php
/**
 * @package Suggest_Review
 * @version 1.0.1
 */
/*
Plugin Name: Suggest Review
Plugin URI: http://www.svvsd.org
Description: Lets users suggest that content may need to be reviewed or re-examined.
Author: Michael George
Version: 1.0.1

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

if (!class_exists("SuggestReview")) {
	class SuggestReview {
		var $adminOptionsName = "SuggestReviewAdminOptions";
		function SuggestReview() { //constructor
		}
		function init() {
			$this->getAdminOptions();
		}
		//Returns an array of admin options
		function getAdminOptions() {
			$suggestReviewAdminOptions = array('allow_unregistered' => 'false',
				'send_email_to_author' => 'true', 
				'add_update_date_to_posts' => 'true',
				'address_for_digest_email' => '',
				'excluded_ids' => '');
			$devOptions = get_option($this->adminOptionsName);
			if (!empty($devOptions)) {
				foreach ($devOptions as $key => $option)
					$suggestReviewAdminOptions[$key] = $option;
			}				
			update_option($this->adminOptionsName, $suggestReviewAdminOptions);
			return $suggestReviewAdminOptions;
		}

		function markForReview( $post_id, $marking_user = '', $marking_date, $author, $sendemail ) {
			update_post_meta( $post_id, 'suggestreview_needed', 'true' );
			update_post_meta( $post_id, 'suggestreview_by', $marking_user );
			update_post_meta( $post_id, 'suggestreview_date', $marking_date );
			if ( $sendemail == "true" ) {
				$authoremail = get_the_author_meta('user_email');
				wp_mail( $authoremail,
					'Support blog content flagged for review',
					'Content you authored on the Support blog has been suggested for review. You should check this content and any comments on it and then post an update.

Suggested by: '.$marking_user.'.

View the content at: '.get_permalink());
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
				$my_user->user_login = "guest";
			}
			$my_author = get_the_author();
			$return = $content;

			//Check to see if the URL is a permalink, if not, we aren't doing anything
			//This prevents the suggest review button from appear on search results and post lists
			if ( "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] == get_permalink() ) {
			
				if ( isset( $_POST['suggestreview'] ) && isset( $_POST['suggestreviewid'] ) && $_POST['suggestreviewid'] == $my_post_id ) {
					$this->markForReview( $_POST['suggestreviewid'], $my_user->user_login, date("Y-m-d H:i:s"), $my_author, $devOptions['send_email_to_author'] );
				} else if ( isset( $_POST['resolvereview'] ) && isset( $_POST['resolvereviewid'] ) && $_POST['resolvereviewid'] == $my_post_id && $my_user->user_login == $my_author ) {
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
					$return .= '<p>The above was flagged for review by ';
					$return .= $markedbyuser[0];
					$return .= ' on ';
					$return .= $markedbydate[0];
					$return .= '</p>';
				//If not marked for review
				} else {
					//Can guests mark it?
					if ($devOptions['allow_unregistered'] == "true" ) {
						//Guests can mark, is it excluded?
						if ( ! $excluded ) {
							$return .= '<p><form method="post" action="'.$_SERVER["REQUEST_URI"].'"><input type="hidden" name="suggestreview" value="1"><input type="hidden" name="suggestreviewid" value="'.$my_post_id.'"><input type="submit" value="Suggest review"> Flag this information for review. Please leave a comment explaining why.</p>';
						}
					} else {
						//If guests can't mark, is anyone logged in?
						if ( is_user_logged_in() ) {
							//User is logged in, is it excluded?
							if ( ! $excluded ) {
								$return .= '<p><form method="post" action="'.$_SERVER["REQUEST_URI"].'"><input type="hidden" name="suggestreview" value="1"><input type="hidden" name="suggestreviewid" value="'.$my_post_id.'"><input type="submit" value="Suggest review"> Flag this information for review. Please leave a comment explaining why.</p>';
							}
						}
					}
				}

			} //end if permalink

			return $return;
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
				if ( isset($_POST['suggestReviewAddUpdateDate']) ) {
					$devOptions['add_update_date_to_posts'] = $_POST['suggestReviewAddUpdateDate'];
				}
				if ( isset($_POST['suggestReviewAddressForDigest']) ) {
					$devOptions['address_for_digest_email'] = apply_filters('content_save_pre', $_POST['suggestReviewAddressForDigest']);
				}
				if ( isset($_POST['suggestReviewIDsToExclude']) ) {
					$devOptions['exclude_ids'] = apply_filters('content_save_pre', $_POST['suggestReviewIDsToExclude']);
				}
				update_option($this->adminOptionsName, $devOptions);

				?>
<div class="updated"><p><strong><?php _e("Settings Updated.", "SuggestReview");?></strong></p></div>
				<?php
			} ?>

<div class=wrap>
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<h2>Suggest Review Options</h2>
<h3>Addresses for digest email</h3>
<p>This is the comma-separated list of addresses that will receive weekly emails about what content has been flagged for review.<br>
<input type="text" name="suggestReviewAddressForDigest" style="width: 500px;" value="<?php _e(apply_filters('format_to_edit',$devOptions['address_for_digest_email']), 'SuggestReview') ?>"></p>

<h3>Allow unregistered users to mark for review</h3>
<p><label for="suggestReviewAllowUnregistered_yes"><input type="radio" id="suggestReviewAllowUnregistered_yes" name="suggestReviewAllowUnregistered" value="true" <?php if ($devOptions['allow_unregistered'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAllowUnregistered_no"><input type="radio" id="suggestReviewAllowUnregistered_no" name="suggestReviewAllowUnregistered" value="false" <?php if ($devOptions['allow_unregistered'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

<h3>Send email to author when content is marked for review</h3>
<p><label for="suggestReviewSendEmailToAuthor_yes"><input type="radio" id="suggestReviewSendEmailToAuthor_yes" name="suggestReviewSendEmailToAuthor" value="true" <?php if ($devOptions['send_email_to_author'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewSendEmailToAuthor_no"><input type="radio" id="suggestReviewSendEmailToAuthor_no" name="suggestReviewSendEmailToAuthor" value="false" <?php if ($devOptions['send_email_to_author'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

<h3>Add last update date to end of posts</h3>
<p><label for="suggestReviewAddUpdateDate_yes"><input type="radio" id="suggestReviewAddUpdateDate_yes" name="suggestReviewAddUpdateDate" value="true" <?php if ($devOptions['add_update_date_to_posts'] == "true") { _e('checked="checked"', "SuggestReview"); }?> /> Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_no"><input type="radio" id="suggestReviewAddUpdateDate_no" name="suggestReviewAddUpdateDate" value="false" <?php if ($devOptions['add_update_date_to_posts'] == "false") { _e('checked="checked"', "SuggestReview"); }?>/> No</label></p>

<h3>Page, post IDs to exclude</h3>
<p>This is the comma-separated list of IDs that will not show the suggest review button. The 'last update' text will still show if that option is selected.<br>
<input type="text" name="suggestReviewIDsToExclude" style="width: 500px;" value="<?php _e(apply_filters('format_to_edit',$devOptions['exclude_ids']), 'SuggestReview') ?>"></p>

<div class="submit">
<input type="submit" name="update_suggestReviewSettings" value="<?php _e('Update Settings', 'SuggestReview') ?>" /></div>
</form>
 </div>
					<?php
		}//End function printAdminPage()

	}
} //End Class SuggestReview

if (class_exists("SuggestReview")) {
	$svvsd_suggestReview = new SuggestReview();
}

//Initialize the admin panel
if (!function_exists("suggestReview_ap")) {
	function suggestReview_ap() {
		global $svvsd_suggestReview;
		if (!isset($svvsd_suggestReview)) {
			return;
		}
		if (function_exists('add_options_page')) {
	add_options_page('Suggest Review', 'Suggest Review', 9, basename(__FILE__), array(&$svvsd_suggestReview, 'printAdminPage'));
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
	if ( ! empty($needsReview) && $needsReview[0] == 'true' ) {
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
		update_post_meta( $post_id, 'suggestreview_date', date("Y-m-d H:i:s") );
	}

	update_post_meta( $post_id, 'suggestreview_lastupdated', date("Y-m-d H:i:s") );
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
	global $wpdb;
	global $post;
	global $svvsd_suggestReview;
	$devOptions = $svvsd_suggestReview->getAdminOptions();

	$querystr = "
			SELECT *
			FROM ".$wpdb->posts." wposts
			INNER JOIN ".$wpdb->postmeta." wpostmeta
			ON wposts.ID = wpostmeta.post_id
			WHERE wpostmeta.meta_key = 'suggestreview_needed'
			AND  wpostmeta.meta_value = 'true'
			";

	$posts_for_digest = $wpdb->get_results($querystr, OBJECT);
	if ( ! empty($posts_for_digest) ) {
		$email_string = 'The following content has been marked for review on the Support blog.
';
		foreach ( $posts_for_digest as $post ) {
			setup_postdata( $post );
			$email_string .= '
Title: '.get_the_title().'
Author: '.get_the_author().'
Last Modified: '.get_the_modified_date().'
Link: '.get_permalink().'
';
		}
		$addresses = explode( ',', $devOptions['address_for_digest_email']);
		foreach ( $addresses as $address ) {
				wp_mail( $address
					,'Support blog content flagged for review'
					,$email_string
					);
		}
	}
}

//Actions and Filters
if (isset($svvsd_suggestReview)) {
	register_activation_hook(__FILE__, 'my_activation');
	register_deactivation_hook(__FILE__, 'my_deactivation');
	//Filters
	add_filter( 'the_content', array(&$svvsd_suggestReview, 'content_insertion'), 1 );
	add_filter( 'cron_schedules', 'my_add_weekly' );
	//Actions
	add_action( 'admin_menu', 'suggestReview_ap' );
	add_action( 'activate_suggest_review/suggest-review.php',  array(&$svvsd_suggestReview, 'init') );
	add_action( 'add_meta_boxes', 'add_markresolve_box' );
	add_action( 'save_post', 'updateLastUpdated' );
	add_action( 'suggestreviewdigest', 'digest_email' );
}
?>
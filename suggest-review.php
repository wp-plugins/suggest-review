<?php
/**
 * @package Suggest_Review
 * @version 1.3.4
 */
/*
Plugin Name: Suggest Review
Plugin URI: http://wordpress.org/plugins/suggest-review/
Description: Lets users suggest that content may need to be reviewed or re-examined.
Author: Michael George
Text Domain: suggest-review
Version: 1.3.4

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
                ,'subject_for_email_to_author' => _x( 'Blog content flagged for review', 'Default subject line for email to author', 'suggest-review' )
                ,'body_for_email_to_author' => sprintf( _x( 'Content you authored on the blog has been suggested for review. You should check this content and any comments on it and then post an update.\n\nTitle: [%s]\nSuggested by: [%s]\nComment: [%s]\n\nView the content at: [%s]', 'Default body for email to author', 'suggest-review' )
                                                        ,_x( 'post_title', 'Variable name for posts title', 'suggest-review' )
                                                        ,_x( 'suggesting_user_login', 'Variable name for suggesting users login name', 'suggest-review' )
                                                        ,_x( 'comment', 'Variable name for suggesting users comment', 'suggest-review' )
                                                        ,_x( 'permalink', 'Variable name for posts permalink', 'suggest-review' )
                                                        )
                ,'add_update_date_to_posts' => 1 // 1 for yes, 2 for yes if not excluded, 0 for no. Since 1.2.3
                ,'footer_alignment' => 'left' // since 1.2.4
                ,'show_comment' => 1 // since 1.3.0. Whether or not to show comments on post when flagged
                ,'excluded_ids' => ''
                ,'address_for_digest_email' => ''
                ,'subject_for_digest_email' => _x( 'Blog content flagged for review', 'Default subject line for digest email', 'suggest-review' )
                ,'body_for_digest_email' => _x( 'The following content has been marked for review on the blog.', 'Default body for digest email', 'suggest-review' )
                ,'item_for_digest_email' => sprintf( _x( '\nTitle: [%s]\nAuthor: [%s]\nLast Modified: [%s]\nLink: [%s]', 'Default for items in digest email', 'suggest-review' )
                                                    ,_x( 'post_title', 'Variable name for posts title', 'suggest-review' )
                                                    ,_x( 'author_login', 'Variable name for post authors login name', 'suggest-review' )
                                                    ,_x( 'post_modified', 'Variable name for posts last modification date', 'suggest-review' )
                                                    ,_x( 'permalink', 'Variable name for posts permalink', 'suggest-review' )
                                                    )
                ,'flag_button_text' => _x( 'Flag this information for review', 'Default text for the button to suggest review of a post', 'suggest-review' ) // since 1.3.1
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
                    $return .= "<p style='text-align: " . $devOptions['footer_alignment'] . "'>";
                    $return .= sprintf( _x( 'Last updated by %1$s on %2$s'
                                            ,'Display when set to display last update information. Variable 1 is login name. Variable 2 is date when updated.'
                                            ,'suggest-review'
                                            )
                                        ,$lastupdatedby[0]
                                        ,$lastupdated[0]
                                        );
                    $return .= "</p>";
                }

                //If this has been marked for review
                if ( ! empty($needsReview) && $needsReview[0] == "true" && ! $excluded ) {
                    $markedbyuser = get_post_meta( $my_post_id, 'suggestreview_by' );
                    $markedbydate = get_post_meta( $my_post_id, 'suggestreview_date' );
                    $markedbycomment = get_post_meta( $my_post_id, 'suggestreview_comment' );
                    $return .= sprintf( '<p style=\'text-align: %s\'> %s'
                                        ,$devOptions['footer_alignment']
                                        ,sprintf( _x( 'The above was flagged for review by %1$s on %2$s', 'Text to display when a post has been flagged already. Variable 1 is suggesting users login. Variable 2 is date when flagged.', 'suggest-review' )
                                                ,$markedbyuser[0]
                                                ,$markedbydate[0]
                                                )
                                        );
                    if ( $devOptions['show_comment'] ) {
                        $return .= '<br>';
                        $return .= _x( 'Comment:', 'Displays before suggesting users comment (if enabled) after a post', 'suggest-review' );
                        $return .= ' <em>';
                        $return .= $markedbycomment[0];
                        $return .= '</em>';
                    }
                    $return .= '</p>';
                //If not marked for review
                } else {
                    $contentToInsert = sprintf( '<div><p style=\'text-align: %s\'><button id="SuggestReviewButton">%s</button></p></div>

<div id="SuggestReviewComment" style="display:none">
    <p style="margin-bottom:0px;"><strong>%s</strong> %s</p>
    <form id="SuggestReviewForm" method="post" action="%s">
        <input type="hidden" name="suggestreview" value="1">
        <input type="hidden" name="suggestreviewid" value="%s">
        <textarea id="suggestReview_commentBox" name="suggestreviewcomment"></textarea>
    </form>
    <p><button id="SuggestReviewCancelButton" style="float:left;">%s</button><button id="SuggestReviewSubmitButton" style="float:left; margin-left:50px;">%s</button><br /></p>
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
                jQuery("#SuggestReviewForm").submit();
            });
    </script>
</div>'
                                                ,$devOptions['footer_alignment']
                                                ,$devOptions['flag_button_text']
                                                ,_x( 'Suggest Review:', 'Text in bold that appears above the suggest review comment box', 'suggest-review' )
                                                ,_x( 'Please leave a comment below explaining why.', 'Text after the bold text that appears above the suggest review comment box', 'suggest-review' )
                                                ,$_SERVER["REQUEST_URI"]
                                                ,$my_post_id
                                                ,_x( 'Cancel', 'Text for cancel button that appears below the suggest review comment box', 'suggest-review' )
                                                ,_x( 'Submit', 'Text for submit button that appears below the suggest review comment box', 'suggest-review' )
                                                );
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

            $setting_link = sprintf( '<a href="%s">%s</a>', add_query_arg( array( 'page' => 'suggest-review.php' ), admin_url( 'options-general.php' ) ), __( 'Settings', 'suggest-review' ) );
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
                        //    wp_die( __('You are not allowed to exclude this post.') );
                        
                        $excluded = 0;
                        foreach( $post_ids as $post_id ) {

                            if ( !$this->perform_exclusion($post_id) )
                                wp_die( __( 'Error excluding post.', 'suggest-review' ) );
            
                            $excluded++;
                        }
                        
                        $sendback = add_query_arg( array('excluded' => $excluded, 'ids' => join(',', $post_ids) ), $sendback );
                    break;
                    
                    default: return;
                }
                
                $sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
                
                wp_redirect( $sendback );
                exit();
            }
        }

        //Displays notice on admin page after bulk excluding. Snagged from Justin Stern (http://www.skyverge.com/blog/add-custom-bulk-action/).
        //Since 1.2.2
        function custom_bulk_admin_notices() {
            global $post_type, $pagenow;
            
            if($pagenow == 'edit.php' && $post_type == 'post' && isset($_GET['excluded']) && (int) $_GET['excluded']) {
                $message = sprintf( _nx( 'Post excluded from SuggestReview.'
                                        ,'%s posts excluded from SuggestReview.'
                                        ,$_GET['excluded']
                                        ,'Result text for using the bult action to exclude posts from suggest review. Make sure plural message includes variable for number of posts affected'
                                        ,'suggest-review'
                                        )
                                    ,number_format_i18n( $_GET['excluded'] )
                                    );
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
<div class="updated"><p><strong><?php _e( 'Settings Updated.', 'suggest-review' );?></strong></p></div>
                <?php
            } ?>

<div class=wrap>
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<div id="suggest-review_option_page" style="width:90%">
  <div id="column1" style="float:left; width:50%;">
    <h2><?php _e( 'Suggest Review Options', 'suggest-review' );?></h2>

    <h3 class='suggestReview_setting'><?php _ex( 'Allow unregistered users to mark for review', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><label for="suggestReviewAllowUnregistered_yes"><input type="radio" id="suggestReviewAllowUnregistered_yes" name="suggestReviewAllowUnregistered" value="true" <?php if ( $devOptions['allow_unregistered'] == 'true' ) { echo 'checked="checked"'; }?> /> <?php _e( 'Yes', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAllowUnregistered_no"><input type="radio" id="suggestReviewAllowUnregistered_no" name="suggestReviewAllowUnregistered" value="false" <?php if ( $devOptions['allow_unregistered'] == 'false' ) { echo 'checked="checked"'; }?>/> <?php _e( 'No', 'suggest-review' );?></label></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Send email to author when content is marked for review', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><label for="suggestReviewSendEmailToAuthor_yes"><input type="radio" id="suggestReviewSendEmailToAuthor_yes" name="suggestReviewSendEmailToAuthor" value="true" <?php if ( $devOptions['send_email_to_author'] == 'true' ) { echo 'checked="checked"'; }?> /> <?php _e( 'Yes', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewSendEmailToAuthor_no"><input type="radio" id="suggestReviewSendEmailToAuthor_no" name="suggestReviewSendEmailToAuthor" value="false" <?php if ( $devOptions['send_email_to_author'] == 'false' ) { echo 'checked="checked"'; }?>/> <?php _e( 'No', 'suggest-review' );?></label></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Subject line for email to author', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'Supports limited variables which are case sensitive.', 'Help text on setting page', 'suggest-review' );?> (<span class="suggestReview_settingsLink" onclick="jQuery('#legend').toggle();"><?php _e( 'Legend', 'suggest-review' );?></span>)<br>
    <input type="text" name="suggestReviewSubjectForEmailToAuthor" style="width:100%;" value="<?php echo apply_filters( 'format_to_edit', $devOptions['subject_for_email_to_author'] );?>"></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Body for email to author', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'Supports limited variables which are case sensitive.', 'Help text on setting page', 'suggest-review' );?> (<span class="suggestReview_settingsLink" onclick="jQuery('#legend').toggle();"><?php _e( 'Legend', 'suggest-review' );?></span>)<br>
    <textarea rows="4" name="suggestReviewBodyForEmailToAuthor" style="width:100%;"><?php echo apply_filters( 'format_to_edit', $devOptions['body_for_email_to_author'] );?></textarea></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Add last update date to end of posts', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><label for="suggestReviewAddUpdateDate_yes"><input type="radio" id="suggestReviewAddUpdateDate_yes" name="suggestReviewAddUpdateDate" value="1" <?php if ( $devOptions['add_update_date_to_posts'] == 1 ) { echo 'checked="checked"'; }?> /> <?php _e( 'Yes', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_yesifnot"><input type="radio" id="suggestReviewAddUpdateDate_yesifnot" name="suggestReviewAddUpdateDate" value="2" <?php if ( $devOptions['add_update_date_to_posts'] == 2 ) { echo 'checked="checked"'; }?> /> <?php _e( 'Yes, if not in exclusion list', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewAddUpdateDate_no"><input type="radio" id="suggestReviewAddUpdateDate_no" name="suggestReviewAddUpdateDate" value="0" <?php if ( $devOptions['add_update_date_to_posts'] == 0 ) { echo 'checked="checked"'; }?>/> <?php _e( 'No', 'suggest-review' );?></label></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Footer alignment', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'Affects last update date and flag button.', 'Help text on setting page', 'suggest-review' );?><br><label for="suggestReviewFooterAlignment_left"><input type="radio" id="suggestReviewFooterAlignment_left" name="suggestReviewFooterAlignment" value="left" <?php if ( $devOptions['footer_alignment'] == 'left' ) { echo 'checked="checked"'; }?> /> <?php _e( 'Left', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewFooterAlignment_center"><input type="radio" id="suggestReviewFooterAlignment_center" name="suggestReviewFooterAlignment" value="center" <?php if ( $devOptions['footer_alignment'] == 'center' ) { echo 'checked="checked"'; }?> /> <?php _e( 'Center', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewFooterAlignment_right"><input type="radio" id="suggestReviewFooterAlignment_right" name="suggestReviewFooterAlignment" value="right" <?php if ( $devOptions['footer_alignment'] == 'right' ) { echo 'checked="checked"'; }?>/> <?php _e( 'Right', 'suggest-review' );?></label></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Show comment on post when flagged', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><label for="suggestReviewShowComment_yes"><input type="radio" id="suggestReviewShowComment_yes" name="suggestReviewShowComment" value="true" <?php if ( $devOptions['show_comment'] ) { echo 'checked="checked"'; }?> /> <?php _e( 'Yes', 'suggest-review' );?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="suggestReviewShowComment_no"><input type="radio" id="suggestReviewShowComment_no" name="suggestReviewShowComment" value="false" <?php if ( ! $devOptions['show_comment'] ) { echo 'checked="checked"'; }?>/> <?php _e( 'No', 'suggest-review' );?></label></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Flag button text', 'Option on setting page', 'suggest-review' );?></h3>
    <input type="text" name="suggestReviewFlagButtonText" style="width:100%;" value="<?php echo apply_filters( 'format_to_edit', $devOptions['flag_button_text'] );?>"></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Page, post IDs to exclude', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'This is the comma-separated list of IDs that will not show the suggest review button. The "last update" text will still show, depending on the above option.', 'Help text on setting page', 'suggest-review' );?><br>
    <input type="text" name="suggestReviewIDsToExclude" style="width:100%;" value="<?php echo apply_filters( 'format_to_edit', $devOptions['exclude_ids'] );?>"></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Addresses for digest email', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'This is the comma-separated list of addresses that will receive weekly emails about what content has been flagged for review.', 'Help text on setting page', 'suggest-review' );?><br>
    <input type="text" name="suggestReviewAddressForDigest" style="width:100%;" value="<?php echo apply_filters( 'format_to_edit', $devOptions['address_for_digest_email'] );?>"></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Subject line for digest email', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><input type="text" name="suggestReviewSubjectForDigestEmail" style="width:100%;" value="<?php echo apply_filters( 'format_to_edit', $devOptions['subject_for_digest_email'] );?>"></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Body for digest email', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'A blank line will be inserted after this and before the post items. Supports limited variables which are case sensitive.', 'Help text on setting page', 'suggest-review' );?> (<span class="suggestReview_settingsLink" onclick="jQuery('#legend').toggle();"><?php _e( 'Legend', 'suggest-review' );?></span>)<br>
    <textarea rows="4" name="suggestReviewBodyForDigestEmail" style="width:100%;"><?php echo apply_filters( 'format_to_edit', $devOptions['body_for_digest_email'] );?></textarea></p>

    <h3 class='suggestReview_setting'><?php _ex( 'Post items for digest email body', 'Option on setting page', 'suggest-review' );?></h3>
    <p class='suggestReview_setting'><?php _ex( 'Each item marked for review will appear after the "Body for digest email". A blank line will separate each item. Supports limited variables which are case sensitive.', 'Help text on setting page', 'suggest-review' );?> (<span class="suggestReview_settingsLink" onclick="jQuery('#legend').toggle();"><?php _e( 'Legend', 'suggest-review' );?></span>)<br>
    <textarea rows="4" name="suggestReviewItemForDigestEmail" style="width:100%;"><?php echo apply_filters( 'format_to_edit', $devOptions['item_for_digest_email'] );?></textarea></p>

    <div class="submit">
      <input type="submit" name="update_suggestReviewSettings" value="<?php _ex('Update Settings', 'Save button on setting page', 'SuggestReview') ?>" />
    </div>

    <p><?php _e( 'Like it? Hate it? Give it a review!', 'suggest-review' );?><br><a href='http://wordpress.org/support/view/plugin-reviews/suggest-review' target='_blank'>http://wordpress.org/support/view/plugin-reviews/suggest-review</a></p>
  </form>
  </div>
  <div id="column2" style="float:right; width:48%; padding-top:35%;">
    <div id="legend" style="display:none;">
    <h3 class="suggestReview_setting" style="text-align:center"><?php _ex( 'Variable Legend', 'Title of variable legend column on settings page', 'suggest-review' );?></h3>
    <p class="suggestReview_setting suggestReview_settingsLink" style="text-align: center;" onclick="jQuery('#legend').toggle();"><?php _e( 'Hide', 'suggest-review' );?></p>
    <table>
      <tbody>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'suggesting_user_login', 'Variable name for suggesting users login name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The login name of the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'suggesting_user_email', 'Variable name for suggesting users email address', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The email address of the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'suggesting_user_firstname', 'Variable name for suggesting users first (given) name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The first name of the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'suggesting_user_lastname', 'Variable name for suggesting users last (sur) name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The last name of the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'suggesting_user_display_name', 'Variable name for suggesting users display name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The display name of the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'comment', 'Variable name for suggesting users comment', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The comment from the user who flagged the item', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'author_login', 'Variable name for post authors login name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The login name of the post author', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'author_email', 'Variable name for post authors email address', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The email address of the post author', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'author_firstname', 'Variable name for post authors first (given) name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The first name of the post author', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'author_lastname', 'Variable name for post authors last (sur) name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The last name of the post author', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'author_display_name', 'Variable name for post authors display name', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The display name of the post author', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'post_title', 'Variable name for posts title', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The title of the post', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'post_date', 'Variable name for posts original publish date', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The original publish date of the post', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'post_modified', 'Variable name for posts last modification date', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The last modified date of the post', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
        <tr><td style="float:right;"><code><strong>[<?php _ex( 'permalink', 'Variable name for posts permalink', 'suggest-review' ); ?>]</strong></code></td><td><?php _ex( 'The permalink to the post', 'Discription of variable on settings page', 'suggest-review' ); ?></td></tr>
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
                suggestReview_addCSS();
                $baseURL = suggestReview_delArgFromURL( $_SERVER['REQUEST_URI'], array( 'srfporderby', 'srfporderdir' ) );
                echo "\r\t<div id='suggestreview_flaggedposts'>\r";
                echo "\t\t<h2>" . _x( 'Posts Flagged for Review', 'Title text for flagged posts table', 'suggest-review' ) . "</h2>\r";
                echo "\t\t<p>" . _x( 'This page lists all of the posts you have access to that have been suggested for a review of their content.', 'Helper text for flagged posts table', 'suggest-review' ) . "</p>\r";
                echo "\t\t<table id='suggestReview_flaggedPostsTable'>\r";
                echo "\t\t\t<thead>\r";
                echo "\t\t\t\t<tr class='tablerow'>\r";
                echo "\t\t\t\t\t<th style='text-align: left;'>" . _x( 'Title', 'Column header on flagged posts table', 'suggest-review' ) . "</th>\r";
                echo "\t\t\t\t\t<th><a href='" . $baseURL . "&srfporderby=author&srfporderdir=" . ( $orderby == 'author' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>" . _x( 'Author', 'Column header on flagged posts table', 'suggest-review' ) . "</a>" . ( $orderby == 'author' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>\r";
                echo "\t\t\t\t\t<th><a href='" . $baseURL . "&srfporderby=postdate&srfporderdir=" . ( $orderby == 'postdate' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>" . _x( 'Post Date', 'Column header on flagged posts table', 'suggest-review' ) . "</a>" . ( $orderby == 'postdate' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>\r";
                echo "\t\t\t\t\t<th><a href='" . $baseURL . "&srfporderby=srby&srfporderdir=" . ( $orderby == 'srby' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>" . _x( 'Flagged By', 'Column header on flagged posts table', 'suggest-review' ) . "</a>" . ( $orderby == 'srby' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>\r";
                echo "\t\t\t\t\t<th><a href='" . $baseURL . "&srfporderby=srdate&srfporderdir=" . ( $orderby == 'srdate' && $orderdir == 'asc' ? "desc" : "asc" ) . "'>" . _x( 'Flagged On', 'Column header on flagged posts table', 'suggest-review' ) . "</a>" . ( $orderby == 'srdate' ? "<div class='dashicons dashicons-arrow-" . ( $orderdir == 'asc' ? "down" : "up" ) . "'></div>" : "" ) . "</th>\r";
                echo "\t\t\t\t</tr>\r";
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
            } else {
                echo "\r\t<div id='suggestreview_flaggedposts'>\r";
                echo "\t\t<h2>" . _x( 'Posts Flagged for Review', 'Title text for flagged posts table', 'suggest-review' ) . "</h2>\r";
                echo "\t\t<p>" . _x( 'No posts have been flagged for review.', 'suggest-review' ) . "</p>\r";
                echo "\t</div>\r";
            }

        }

        //Add admin page(s) to dashboard
        //Since 1.3.0
        function adminMenu() {
            //If user is an admin, show all the options, otherwise just show the flagged posts page
            if ( current_user_can( 'manage_options' ) ) {
                add_menu_page( _x( 'Suggest Review Settings', 'Title for settings page', 'suggest-review')
                                ,_x( 'Suggest Review', '', 'suggest-review')
                                ,'manage_options'
                                ,'suggestreview_settings'
                                ,array( $this, 'printSettingsPage' )
                                ,'dashicons-flag'
                                );
                add_submenu_page( 'suggestreview_settings'
                                ,_x( 'Suggest Review Settings', 'Title for settings page', 'suggest-review')
                                ,_x( 'Settings', 'Text for link to settings in settings submenu', 'suggest-review')
                                ,'manage_options'
                                ,'suggestreview_settings'
                                ,array( $this, 'printSettingsPage' )
                                );
                add_submenu_page( 'suggestreview_settings'
                                ,_x( 'Posts Suggested for Review', 'Title for admin flagged posts page', 'suggest-review')
                                ,_x( 'Flagged Posts', 'Text for link to admin flagged posts page in settings submenu', 'suggest-review')
                                ,'edit_posts'
                                ,'suggestreview_posts'
                                ,array( $this, 'printFlaggedPostsPage' )
                                );
            } else {
                add_menu_page( _x( 'Posts Suggested for Review', 'Title for non-admin flagged posts page', 'suggest-review')
                                ,_x( 'SR Flagged Posts', 'Text for link to non-admin flagged posts page in dashboard menu', 'suggest-review')
                                ,'edit_posts'
                                ,'suggestreview_posts'
                                ,array( $this, 'printFlaggedPostsPage' )
                                ,'dashicons-flag'
                                );
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
            ,_x( 'Resolve Suggest Review Flag', 'Title of meta box when editing post', 'suggest-review' )
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
        echo "<p>" . sprintf( _x( 'Review suggested by %1$s on %2$s'
                                    ,'Text to display in meta box when editing a flagged post. Variable 1 is suggesting users login name. Variable 2 is date of flag.'
                                    ,'suggest_review' )
                            ,$markedbyuser[0]
                            ,$markedbydate[0] ) . "<br>";
        echo '<label for="suggestReview_resolveflag"><input type="checkbox" id="suggestReview_resolveflag" name="suggestReview_resolveflag" value="suggestReview_resolveflag" checked /> ' . _x( 'Remove this flag', 'Checkbox in meta box when editing a flagged post', 'suggest-review') . '</label></p>';
    } else {
        echo '<p>' . _x( 'This post has not been suggested for review.', 'Displayed in meta box when editing a post that has not been flagged', 'suggest-review' ) . '</p>';
    }
    $devOptions = $svvsd_suggestReview->getAdminOptions();
    $excludedIDs = explode( ',', $devOptions['exclude_ids']);
    $excluded = in_array( $post->ID, $excludedIDs);
    echo '<p>' . _x( 'Exclude from SR?', 'Displayed in meta box when editing a post', 'suggest-review' ) . ' <input type="radio" id="suggestReview_excludeflag_yes" name="suggestReview_excludeflag" value="true"' . ( $excluded ? ' checked' : '' ) . ' />' . __( 'Yes', 'suggest-review' ) . '&nbsp;&nbsp;';
    echo '<input type="radio" id="suggestReview_excludeflag_no" name="suggestReview_excludeflag" value="false"' . ( !$excluded ? ' checked' : '' ) . ' />' . __( 'No', 'suggest-review' ) . '</p>';
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
//    wp_schedule_event( time(), 'quarterhourly', 'suggestreviewdigest');
}

function suggestReviewDeactivation() {
    wp_clear_scheduled_hook('suggestreviewdigest');
}

function suggestReview_addWeeklySchedule( $schedules ) {
    // add a 'weekly' schedule to the existing set
    $schedules['weekly'] = array(
        'interval' => 604800,
        'display' => _x( 'Once Weekly', 'Description of the digest email schedule', 'suggest-review' )
//    $schedules['quarterhourly'] = array(
//        'interval' => 900,
//        'display' => __('Once every 15')
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

    $tokens = array( '[' . _x( 'suggesting_user_login', 'Variable name for suggesting users login name', 'suggest-review' ) . ']' => $marking_user->user_login
                    ,'[' . _x( 'suggesting_user_email', 'Variable name for suggesting users email address', 'suggest-review' ) . ']' => $marking_user->user_email
                    ,'[' . _x( 'suggesting_user_firstname', 'Variable name for suggesting users first (given) name', 'suggest-review' ) . ']' => $marking_user->user_firstname
                    ,'[' . _x( 'suggesting_user_lastname', 'Variable name for suggesting users last (sur) name', 'suggest-review' ) . ']' => $marking_user->user_lastname
                    ,'[' . _x( 'suggesting_user_display_name', 'Variable name for suggesting users display name', 'suggest-review' ) . ']' => $marking_user->display_name
                    ,'[' . _x( 'author_login', 'Variable name for post authors login name', 'suggest-review' ) . ']' => $author->user_login
                    ,'[' . _x( 'author_email', 'Variable name for post authors email address', 'suggest-review' ) . ']' => $author->user_email
                    ,'[' . _x( 'author_firstname', 'Variable name for post authors first (given) name', 'suggest-review' ) . ']' => $author->user_firstname
                    ,'[' . _x( 'author_lastname', 'Variable name for post authors last (sur) name', 'suggest-review' ) . ']' => $author->user_lastname
                    ,'[' . _x( 'author_display_name', 'Variable name for post authors display name', 'suggest-review' ) . ']' => $author->display_name
                    ,'[' . _x( 'post_title', 'Variable name for posts title', 'suggest-review' ) . ']' => $post->post_title
                    ,'[' . _x( 'post_date', 'Variable name for posts original publish date', 'suggest-review' ) . ']' => $post->post_date
                    ,'[' . _x( 'post_modified', 'Variable name for posts last modification date', 'suggest-review' ) . ']' => $post->post_modified
                    ,'[' . _x( 'permalink', 'Variable name for posts permalink', 'suggest-review' ) . ']' => $permalink
                    ,'[' . _x( 'comment', 'Variable name for suggesting users comment', 'suggest-review' ) . ']' => $marked_comment[0]
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

//Load CSS from file
function suggestReview_addCSS() {
    wp_enqueue_style( 'suggestReview_CSS', plugins_url( 'styles.css', __FILE__ ) );
}

function suggestReview_loadTextDomain() {
    load_plugin_textdomain( 'suggest-review'
                            ,false
                            ,dirname( plugin_basename( __FILE__ ) ) . '/languages'
                            );
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
    add_action( 'init', 'suggestReview_loadTextDomain' );
    add_action( 'admin_menu', array( &$svvsd_suggestReview, 'adminMenu' ) );
    add_action( 'activate_suggest_review/suggest-review.php',  array( &$svvsd_suggestReview, 'init' ) );
    add_action( 'add_meta_boxes', 'suggestReview_addMarkresolveBox' );
    add_action( 'save_post', 'suggestReviewUpdatePost' );
    add_action( 'suggestreviewdigest', 'suggestReview_digestEmail' );
    add_action( 'admin_footer-edit.php', array( &$svvsd_suggestReview, 'custom_bulk_admin_footer' ) );
    add_action( 'load-edit.php', array( &$svvsd_suggestReview, 'custom_bulk_action' ) );
    add_action( 'admin_notices', array( &$svvsd_suggestReview, 'custom_bulk_admin_notices' ) );
    add_action( 'wp_enqueue_scripts', 'suggestReview_addCSS' );
    add_action( 'admin_enqueue_scripts', 'suggestReview_addCSS' );

}
?>
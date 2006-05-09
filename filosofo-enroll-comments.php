<?php
/*
Plugin Name: Filosofo Enroll Comments
Plugin URI: http://www.ilfilosofo.com/blog/enroll-comments/
Description: Filosofo Enroll Comments lets users sign up to receive emails when new comments appear.    
Version: 0.5
Author: Austin Matzko
Author URI: http://www.ilfilosofo.com/blog/
*/

/*  Copyright 2005  Austin Matzko  (email : if.website at gmail.com)

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
class filosofo_ec {
var $min_role; var $max_rows; var $user_messages = array(); var $checkbox_label; var $default_role;
function filosofo_ec() {
//*************************************************************************************************
// Configurable options
//*************************************************************************************************

/* 	The messages that appear in the comments area, if appropriate */
$this->checkbox_label = __('Receive an email if someone else comments on this post?');
$this->manage_label = __('Manage <a href="%s">your subscriptions</a>.');

/* 	The capability a user needs to be able to edit others' enrollments */
$this->min_role = 'edit_others_posts';

/* 	The number of rows of subscriptions to show per page */
$this->max_rows = 15; 

/* 	The role all new registrations get by default 
	(including comment enrollees).  This is set only when the plugin is activated. */
$this->default_role = 'subscriber'; 

//*************************************************************************************************
// Edit below here at your own risk :-)
//*************************************************************************************************
}
	function activate_plugin() {
		update_option('default_role',$this->default_role);
	}

	function add_enroll_checkbox($post_ID) {
		$notice = ($this->check_status($post_ID)) ? '<p>' . sprintf($this->manage_label, get_settings('siteurl') . '/wp-admin/profile.php?page=' . basename(__FILE__)) . '</p>' 
		: '<label for="filosofo_enroll">' . $this->checkbox_label . '<input type="checkbox" id="filosofo_enroll" name="filosofo_enroll"' . $checker . ' /></label>' ;
		echo $notice;
	} 

	function box_is_checked($comment_ID) {
		global $user_ID;
		get_currentuserinfo();
		//if not a logged in user
		if ( !$user_ID ) {
			$email = trim($_POST['email']);
			$user = $this->get_user_by_email($email);
			if ($user->ID)
				$user_ID = $user->ID;
			else
				$user_ID = $this->create_user();		

		}
		$comment_data = get_commentdata($comment_ID,1);
		$comment_post_ID = $comment_data['comment_post_ID'];
		$this->add_enrollment($comment_post_ID,$user_ID);
	}

	function check_status($post_ID) {
		global $user_ID;
		$user_ids = $this->get_post_enrollees($post_ID);
		$the_post = get_post($post_ID);
		$comment_author_email = '';
		if ( isset($_COOKIE['comment_author_email_'.COOKIEHASH]) ) {
			$comment_author_email = apply_filters('pre_comment_author_email', $_COOKIE['comment_author_email_'.COOKIEHASH]);
			$comment_author_email = stripslashes($comment_author_email);
			$comment_author_email = wp_specialchars($comment_author_email, true);		
		}
		if ( $user_ID ) {
			if (in_array($user_ID,$user_ids)) return true;
			elseif ($user_ID == $the_post->post_author) return true;
		} 
		elseif (isset($comment_author_email)) {
			$user = $this->get_user_by_email($comment_author_email);
			if ( !$user->ID ) return false;
			if (in_array($user->ID,$user_ids)) return true;
			elseif ($user->ID == $the_post->post_author) return true;
		}
		else return false;
	} 

	function get_user_by_email($email) {
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM $wpdb->users WHERE user_email = '$email' LIMIT 1");
	}

	function add_enrollment($comment_post_ID,$user_ID) {
		if (!$comment_post_ID || !$user_ID) return;
		$this->add_post_enrollees($comment_post_ID,$user_ID);
                $this->add_enrollee_post($user_ID,$comment_post_ID);
	}

	function remove_enrollment($comment_post_ID,$user_ID) {
		// remove post record
		$values = (array) $this->get_post_enrollees($comment_post_ID);
		unset($values[$user_ID]);
		delete_post_meta($comment_post_ID,'filosofo_ec_comment_enrollees');
                add_post_meta($comment_post_ID,'filosofo_ec_comment_enrollees',$values);
		// remove user record
		$values = get_usermeta($user_ID,'filosofo_ec_enrollee_posts');
                unset($values[$comment_post_ID]);
		update_usermeta($user_ID,'filosofo_ec_enrollee_posts',$values);
		wp_cache_flush(); 
	}

	function get_post_enrollees($comment_post_ID) {
		$values = get_post_meta($comment_post_ID,'filosofo_ec_comment_enrollees',true);
                return (array) maybe_unserialize($values);
	}

	function add_post_enrollees($comment_post_ID,$user_ID) {
		$values = (array) $this->get_post_enrollees($comment_post_ID);
		$values[$user_ID] = $user_ID;
		delete_post_meta($comment_post_ID,'filosofo_ec_comment_enrollees');
		add_post_meta($comment_post_ID,'filosofo_ec_comment_enrollees',$values);
	}

	function add_enrollee_post($user_ID,$comment_post_ID) {
		$values = get_usermeta($user_ID,'filosofo_ec_enrollee_posts');
		$values[$comment_post_ID] = $comment_post_ID;
		update_usermeta($user_ID,'filosofo_ec_enrollee_posts',$values);		
	}

	function create_user() { 
		require_once( ABSPATH . WPINC . '/registration-functions.php');
		
		// get email prefix
		$user_email = strtolower($_POST['email']);
		list($first_name,$last_name) = explode(' ',trim($_POST['author']));
		$email = explode('@',$user_email);		
		$user_login = $email[0];
		
		// add incremented numbers to email prefix to create user_login
		$i = 1;
		$orig_user = $user_login;
		while (username_exists($user_login)) {
			$user_login = $orig_user . "$i";
			$i++;	
		}
		$password = substr( md5( uniqid( microtime() ) ), 0, 7);
                $user_id = wp_create_user( $user_login, $password, $user_email );
		update_usermeta( $user_id, 'first_name', $first_name);
		update_usermeta( $user_id, 'last_name', $last_name); 
		$this->notify_new_enrollee($user_id, $password);
		return $user_id;
	} // end function create_user

	function notify_new_enrollee($user_id, $plaintext_pass = '') {
		$user = new WP_User($user_id);
		$user_login = stripslashes($user->user_login);
		$user_email = stripslashes($user->user_email);
		$message .= sprintf(__('Username: %s'), $user_login) . "\r\n";
		$message .= sprintf(__('Password: %s'), $plaintext_pass) . "\r\n";
		$this->user_messages[$user_id] = $message;
	} // end function notify_new_enrollee

	function email_enrollees($comment_ID) {
		$comment_data = get_commentdata($comment_ID,1);
                $comment_post_ID = $comment_data['comment_post_ID'];
		$title = '"' . get_the_title($comment_post_ID) . '"';		
		$enrollees = $this->get_post_enrollees($comment_post_ID);
		$message = sprintf(__('A new comment has been posted to %s: %s '), $title, get_permalink($comment_post_ID)) . "\r\n";
		$message .= sprintf(__('Author: %s'), $comment_data['comment_author']) . "\r\n";
		$message .= sprintf(__('Message: %s'), $comment_data['comment_content']) . "\r\n";
		$message .= "\r\n ------------------- \r\n";
		$message .= "\r\n" . sprintf(__('Manage your subscriptions by logging in here: %s'), get_settings('siteurl') . "/wp-login.php") . "\r\n"; 
		foreach ($enrollees as $user_id) {
			$message_custom = $message;
			$message_custom .= $this->user_messages[$user_id];
			$user = get_userdata($user_id);
			$unsubscribe_link = get_settings('siteurl') . '/index.php?' . 'filosofo_ec_user_id=' . $user_id . '&check=' . $this->encode_user_id($user_id) . '&filosofo_ec_post_id=' . $comment_post_ID . '&filosofo_ec_subscribe=unsubscribe&email=true';
			$message_custom .= "\r\n" . sprintf(__('Unsubscribe From This Entry %s'),$unsubscribe_link) . "\r\n";
			wp_mail(stripslashes($user->user_email), sprintf(__('[%s] New Comment Posted to %s'), get_settings('blogname'), $title), $message_custom);
		}
	} //end function email_enrollees

	function new_comment_posted($comment_ID) {  // determine whether to enroll someone
		if(TRUE == trim($_POST['filosofo_enroll'])) {
			$this->box_is_checked($comment_ID);
		}
		$this->email_enrollees($comment_ID);
	} 

	function add_admin_menu() {
		global $list_js;
		$list_js = true;
		add_submenu_page("profile.php", __('Comment Subscriptions'), __('Comment Subscriptions'),0, basename(__FILE__), array(&$this,'user_subscription_page'));
	}
	
	function user_can_manage_enrollment() {
		if (current_user_can($this->min_role)) return true;
		else return false;
	}

	function encode_user_id($user_id) {
		$user = get_userdata($user_id);
		return substr(md5($user_id . date('d') . $user->user_email . $user->user_pass), 0, 10);
	}

	function test_for_posts() {
		global $user_ID;
		if (isset($_REQUEST['filosofo_ec_subscribe'])) { //request for changes to enrollment
			if (trim($_REQUEST['check']) != $this->encode_user_id(trim($_REQUEST['filosofo_ec_user_id']))) die(sprintf(__('Your request has expired.  Please <a href="%s">login</a> to manage your subscriptions.'), get_settings('siteurl') . "/wp-login.php"));
			switch(trim($_REQUEST['filosofo_ec_subscribe'])) {
				case 'subscribe':
					foreach (array('page_to_enroll','post_to_enroll') as $option)
						if (isset($_REQUEST[$option])) $this->add_enrollment(trim($_REQUEST[$option]),trim($_REQUEST['filosofo_ec_user_id']));
				break;
				case 'unsubscribe': $this->remove_enrollment(trim($_REQUEST['filosofo_ec_post_id']),trim($_REQUEST['filosofo_ec_user_id']));
				break;  				
			}
			if (isset($_REQUEST['filosofo_ec_ajax_test'])) die("1");
			elseif (isset($_REQUEST['email'])) die(__('You have successfully unsubscribed.')); 
		}
	}

	function user_subscription_page() {
		global $user_ID, $wpdb;
		$user = (isset($_REQUEST['filosofo_ec_user_id'])) ? get_userdata(trim($_REQUEST['filosofo_ec_user_id'])) : get_userdata($user_ID);
		$user_dropdown = '';
		if ($this->user_can_manage_enrollment()) {
			$user = (isset($_REQUEST['enrolled_user'])) ? get_userdata($_REQUEST['enrolled_user']) : $user;
			$user_dropdown = '<form id="enrollees_form" name="enrollees_form" method="post" action="' . $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__) . '">';
			$all_logins = $wpdb->get_results("SELECT ID, user_login, display_name FROM $wpdb->users ORDER BY user_login");
        		$user_dropdown .= '<select name="enrolled_user" id="enrolled_user" onchange="submitIt(\'enrolled_user\')">';
        		foreach ($all_logins as $login)	{
				$selected = ($login->ID == $user->ID) ? 'selected="selected"' : '';
				$user_dropdown .= "<option value=\"{$login->ID}\" $selected >{$login->user_login}: {$login->display_name}</option>";
			}
			$user_dropdown .= '</select><input type="submit" name="submit" value="' . __('Select User') . ' &raquo;" /></form>'; 		}
		$this->test_for_posts();
		?><script type="text/javascript">
		//<![CDATA[
		function ajaxDelete(what, id) {
			ajaxDel = new sack('<?php echo get_settings('siteurl') . $_SERVER['PHP_SELF']; ?>?page=<?php echo basename(__FILE__); ?>&filosofo_ec_ajax_test=true');
			if ( ajaxDel.failed ) return true;
			ajaxDel.myResponseElement = getResponseElement();
			ajaxDel.method = 'POST';
			ajaxDel.onLoading = function() { ajaxDel.myResponseElement.innerHTML = 'Sending Data...'; };
			ajaxDel.onLoaded = function() { ajaxDel.myResponseElement.innerHTML = 'Data Sent...'; };
			ajaxDel.onInteractive = function() { ajaxDel.myResponseElement.innerHTML = 'Processing Data...'; };
			ajaxDel.onCompletion = function() { removeThisItem( what + '-' + id ); };
			ajaxDel.runAJAX('<?php echo '&filosofo_ec_user_id=' . $user->ID . '&check=' . $this->encode_user_id($user->ID); ?>&filosofo_ec_subscribe=unsubscribe&filosofo_ec_post_id=' + id);
			return false;
		}
		function submitIt(selection) {
			var the_selection = document.getElementById(selection);
			location.href = '<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__) . '&enrolled_user='; ?>' + the_selection.value;
		}
		//]]>
		</script><?php	
		$post_ids = array_keys((array) $user->filosofo_ec_enrollee_posts);
		rsort($post_ids);
		$post_ids = (isset($_GET['paged'])) ? array_slice($post_ids, (((int) trim($_GET['paged']))-1)*((int) $this->max_rows)) : $post_ids;
		$next_page = (isset($_GET['paged'])) ? ((int) trim($_GET['paged'])) - 1 : 0;
		$previous_page = (count($post_ids) > $this->max_rows) ? ((int) $next_page) + 2 : 0;
		$next_link = ($next_page > 0) ? '<a href="' . $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__) . '&amp;paged=' . $next_page . '">' . __('Next Entries &raquo;') . '</a>' : '';
		$previous_link = ($previous_page > 0) ? '<a href="' . $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__) . '&amp;paged=' . $previous_page . '">' . __('&laquo; Previous Entries') . '</a>' : '';
		$post_ids = ($previous_page) ? array_slice($post_ids,0,$this->max_rows) : $post_ids;
		$posts_columns = array(
		'id'		=> __('ID'),
		'title'		=> __('Title'),
		'view'		=> '',
		'unsubscribe'	=> '');
		?><div class="wrap"><h2><?php _e('Manage Comment Subscriptions'); ?></h2><?php echo $user_dropdown; ?>
		<h4><? echo sprintf(__('%s is enrolled to be emailed comments from the following posts and pages:'),$user->user_login); ?></h4>
		<table id="the-list-x" width="100%" cellpadding="3" cellspacing="3"><tr>
		<?php foreach($posts_columns as $column_display_name) { ?>
		<th scope="col"><?php echo $column_display_name; ?></th>
		<?php } ?></tr>
		<?php
		$alternate = '';
		foreach ($post_ids as $post_id) {
			$class = ('alternate' == $class) ? '' : 'alternate';
			echo '<tr id="enroll-' . $post_id . '" class="' . $class . '">';
			foreach($posts_columns as $column_name=>$column_display_name) {
				switch($column_name) {
					case 'id': ?><th scope="row"><?php echo $post_id; ?></th>
					<?php break;
					case 'title': ?><td><?php echo get_the_title($post_id); ?></td>
					<?php break;
					case 'view': ?><td><a href="<?php echo get_permalink($post_id); ?>" rel="permalink" class="edit"><?php _e('View Post'); ?></a></td>
					<?php break;
					case 'unsubscribe': ?><td><a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo basename(__FILE__) . '&amp;filosofo_ec_user_id=' . $user->ID . '&amp;check=' . $this->encode_user_id($user->ID) . '&amp;filosofo_ec_post_id=' . $post_id . '&amp;filosofo_ec_subscribe=unsubscribe'; ?>" rel="permalink" class="delete" <?php echo "onclick=\"return deleteSomething( 'enroll', " . $post_id . ", '" . sprintf(__("You are about to unsubscribe from this post &quot;%s&quot;.\\n&quot;OK&quot; to unsubscribe, &quot;Cancel&quot; to stop."), str_replace("&#039;","&rsquo;",wp_specialchars(get_the_title($post_id), 1) )) . "' );\""; 
					?>><?php _e('Unsubscribe'); ?></a></td>
					<?php break;
				}
			}
			echo '</tr>';
		}

		?></table><div id="ajax-response"></div>
		<div class="navigation"><div class="alignleft"><?php echo $previous_link; ?></div>
		<div class="alignright"><?php echo $next_link; ?></div></div>

		<?php
		$pub_posts = get_posts('numberposts=200&orderby=post_date');
		$pages = get_pages();
		?>
		<form name="subscribe_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo basename(__FILE__); ?>">
		<fieldset class="options">
		<table><tr><th scope="row"><label for="post_to_enroll"><?php echo sprintf(__('Enroll %s in a post\'s comments:'), $user->user_login); ?></label></th>
        	<td><select name="post_to_enroll" id="post_to_enroll"><option value="0" selected="selected"><?php _e('None Selected'); ?></option>
		<?php 	foreach ($pub_posts as $post) {
				list($date,$time) = explode(' ',$post->post_date);
				echo "\n\t<option value=\"$post->ID\">$date: $post->post_title</option>\n";
		}
		?>
		</select></td><td rowspan="2"><input type="submit" name="submit" value="<?php _e('Subscribe') ?> &raquo;" /></td></tr>
		<tr><th scope="row"><label for="page_to_enroll"><?php echo sprintf(__('Enroll %s in a page\'s comments:'), $user->user_login); ?></label></th>
                <td><select name="page_to_enroll" id="page_to_enroll"><option value="0" selected="selected"><?php _e('None Selected'); ?></option>
                <?php   foreach ($pages as $page)
                                echo "\n\t<option value=\"$page->ID\">$page->post_title</option>\n";
                ?>
                </select></td></tr></table>
		</fieldset><input type="hidden" name="filosofo_ec_subscribe" id="filosofo_ec_subscribe" value="subscribe" /><input type="hidden" name="filosofo_ec_user_id" id="filosofo_ec_user_id" value="<?php echo $user->ID; ?>" /><input type="hidden" name="check" id="check" value="<?php echo $this->encode_user_id($user->ID); ?>" /></form>
		</div>
		<?php		
	} //end function user_subscription_page

} //end class filosofo_ec
$filosofo_ec_class = new filosofo_ec();

add_action('comment_form', array(&$filosofo_ec_class,'add_enroll_checkbox'),1);
add_action('comment_post', array(&$filosofo_ec_class,'new_comment_posted'),50);
add_action('activate_' . basename(__FILE__), array(&$filosofo_ec_class,'activate_plugin'));
add_action('admin_menu', array(&$filosofo_ec_class, 'add_admin_menu'));
add_action('plugins_loaded', array(&$filosofo_ec_class, 'test_for_posts'));
?>

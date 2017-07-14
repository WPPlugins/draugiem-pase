<?php
/*  Copyright 2010  Ģirts Upītis (girts.upitis at gmail dot com)

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
Plugin Name: Draugiem.lv pase
Author: Ģirts Upītis
Description: Allows to register, log in and post comments to a Wordpress based website by using "Draugiem pase" authentication provided by draugiem.lv social network. To use this plugin, you have to get your App ID and API key by registering your application on draugiem.lv - <a href="http://www.draugiem.lv/development/?view=my_dev_apps">development section</a>. Plugin replaces user avatars with their draugiem.lv profile pics (may not work with older themes) and shows a link to draugiem.lv profile next to comments.
Version: 1.0
*/
define ('DRAUGIEM_API_URL', 'http://api.draugiem.lv/php/');
define ('DRAUGIEM_LOGIN_URL', 'http://api.draugiem.lv/authorize/');

register_activation_hook( __FILE__, 'draugiem_install');
register_deactivation_hook( __FILE__, 'draugiem_uninstall');
add_action('init', 'draugiem_init');



function draugiem_init() {
	global $draugiem_cfg, $draugiem_user;
	$draugiem_cfg = array();
	
	$cfg = get_option('draugiem_config');
	if(!is_array($cfg)){
		$cfg = array();
	}	
	$draugiem_cfg = $cfg + array(
		'app_id' => false,
		'app_key' => false,
		'icon' => true,
		'login_c' => true,
		'friend_url' => true,
		'remember' => false,
		'block_admin' => true,
		'avatar' => true,
		'cron' => true,
	);
	
	if($draugiem_cfg['app_id'] && $draugiem_cfg['app_key']){
		
		if($draugiem_cfg['login_c']) add_action('comment_form', 'draugiem_login_form');
		if($draugiem_cfg['block_admin']) add_action('admin_init', 'draugiem_block_admin_panel'); 
		if($draugiem_cfg['avatar']) add_filter( 'get_avatar', 'draugiem_avatar', 10, 3);
		if($draugiem_cfg['friend_url'])	add_filter( 'get_comment_author_url', 'draugiem_comments_url', 3);		
		add_filter( 'comments_array', 'draugiem_comments', 10, 3);
		add_action('login_form', 'draugiem_login_form');
		add_filter( 'get_comment_author', 'draugiem_comments_author', 3);
		if($draugiem_cfg['cron']) add_action ('draugiem_cron', 'draugiem_update_users');

		$user = wp_get_current_user();
		if($user->ID) {
			$draugiem_user = draugiem_get_wpusers($user->ID);
		} else {
			$draugiem_user = false;
		}
		if(!$draugiem_user){
			draugiem_session_init();
		}
	}
	add_action('admin_menu', 'draugiem_cfg_page');	
}

function draugiem_install(){
	global $wpdb;
	$table_name = $wpdb->prefix.'draugiem_users';
	$wpdb->query("CREATE TABLE IF NOT EXISTS `$table_name` (
	  `uid` int(11) NOT NULL,
	  `wpuid` bigint(20) NOT NULL,
	  `apikey` varchar(32) NOT NULL,
	  `sex` char(1) NOT NULL,
	  `place` varchar(100) NOT NULL DEFAULT '',
	  `age` int(11) NOT NULL DEFAULT '0',
	  `image` varchar(100) NOT NULL DEFAULT '',
	  `updated` datetime NOT NULL,
	  `created` datetime NOT NULL,
	  `active` tinyint(4) NOT NULL DEFAULT '1',
	  PRIMARY KEY (`uid`),
	  UNIQUE KEY `wpuid` (`wpuid`)
	)");
	wp_schedule_event(time(), 'hourly', 'draugiem_cron');
}
function draugiem_uninstall(){
	wp_clear_scheduled_hook('draugiem_cron');
}

function draugiem_cfg_page() {
		add_options_page('"Draugiem.lv pase" configuration', 'Draugiem pase', 'manage_options', 'draugiem-config', 'draugiem_conf');
}

function draugiem_conf() {
	global $draugiem_cfg;
	$app_id = $draugiem_cfg['app_id'];
	$app_key = $draugiem_cfg['app_key'];

	//Save settings
	if ( isset($_POST['submit'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'draugiem_cfg') ) {
		$app_id = (int)$_POST['app_id'];
		$app_key = $_POST['app_key'];
		if($app_id > 0 && preg_match('/^[a-h0-9]{32}$/',$app_key)){
		
			$draugiem_cfg['app_id'] = $app_id;
			$draugiem_cfg['app_key'] = $app_key;
			$draugiem_cfg['icon'] = !empty($_POST['icon']);
			$draugiem_cfg['login_c'] = !empty($_POST['login_c']);
			$draugiem_cfg['friend_url'] = !empty($_POST['friend_url']);
			$draugiem_cfg['remember'] = !empty($_POST['remember']);
			$draugiem_cfg['avatar'] = !empty($_POST['avatar']);
			$draugiem_cfg['block_admin'] = !empty($_POST['block_admin']);
			update_option('draugiem_config', $draugiem_cfg);
			
			?><div id="message" class="updated fade">
			<p><strong>Settings saved!</strong></p>
			</div><?php		
		} else {
			?><div id="message" class="error fade">
			<p><strong>Invalid value for APP id or API key!</strong></p>
			</div><?php				
		}
	} 
	?>
	<div class="wrap">
	<h2>"Draugiem.lv pase" configuration</h2>
	<form action="" method="post">
	<h3>Application information</h3>
	<table class="form-table">
		<tr>
			<th>Application ID:</th>
			<td>
				<input class="small-text" type="text" name="app_id" value="<?php echo $draugiem_cfg['app_id'] ?>" />
			</td>
		</tr>
		<tr>
			<th>API key:</th>
			<td>
				<input class="regular-text" type="text" name="app_key" value="<?php echo $draugiem_cfg['app_key'] ?>" />
				<br /><i>Create your application and get parameters <a href="http://www.draugiem.lv/development/?view=my_dev_apps">here</a>!</i>
			</td>
		</tr>
	</table>
	<h3>Settings</h3>
		<table class="form-table">
			<tr>
				<th>Show draugiem.lv icon next to the comments:</th>
				<td><input type="checkbox" name="icon" <?php if(!empty($draugiem_cfg['icon'])){echo 'checked="checked"';}?>/></td>
			</tr>
			<tr>
				<th>Show "Draugiem pase" login button below comments form:</th>
				<td><input type="checkbox" name="login_c" <?php if(!empty($draugiem_cfg['login_c'])){echo 'checked="checked"';}?>/></td>
			</tr>
			<tr>
				<th>Show link to draugiem.lv profile page next to comments:</th>
				<td><input type="checkbox" name="friend_url" <?php if(!empty($draugiem_cfg['friend_url'])){echo 'checked="checked"';}?>/></td>
			</tr>
			<tr>
				<th>Use profile pictures as avatars:</th>
				<td><input type="checkbox" name="avatar" <?php if(!empty($draugiem_cfg['avatar'])){echo 'checked="checked"';}?>/></td>
			</tr>
			<tr>
				<th>Disable access to Dashboard/Profile page:</th>
				<td><input type="checkbox" name="block_admin" <?php if(!empty($draugiem_cfg['block_admin'])){echo 'checked="checked"';}?>/>
				<br /><i>If checked, draugiem.lv users won't be able to access their Wordpress profile settings page.</i>
				</td>
			</tr>
			<tr>
				<th>"Remember me":</th>
				<td>
				<input type="checkbox" name="remember" <?php if(!empty($draugiem_cfg['remember'])){echo 'checked="checked"';}?>/>
				<br /><i>If checked, user stays logged in until he logs off.</i>
				</td>
			</tr>
			<tr>
				<th>Update user data via draugiem.lv API on background:</th>
				<td><input type="checkbox" name="cron" <?php if(!empty($draugiem_cfg['cron'])){echo 'checked="checked"';}?>/><br />
				<i>If checked, system will get latest user profile info from draugiem.lv API to update their user names and profile pictures.</i>  
				</td>
			</tr>
		</table>		
	<p class="submit">
		<?php wp_nonce_field('draugiem_cfg') ?> 
		<input type="submit" value="Save" name="submit" class="button-primary" />
	</p>
	</div>
	<?php
}

 
function draugiem_login_form(){
	global $draugiem_cfg;
	
	$user = wp_get_current_user();
	if($user->ID) {
		return;
	}
	if(strpos($_SERVER['PHP_SELF'],'wp-login.php')){
		if(isset($_GET['redirect_to'])){
			$pageURL = $_GET['redirect_to'];
		} elseif($draugiem_cfg['block_admin']) {
			$pageURL = get_option('siteurl');
		} else {
			$pageURL = admin_url();
		}
	} else {
		$pageURL = is_ssl()?'https://':'http://'.$_SERVER["SERVER_NAME"].($_SERVER["SERVER_PORT"] != "80"?":".$_SERVER["SERVER_PORT"]:'').$_SERVER["REQUEST_URI"];
	}

	$pageURL = htmlspecialchars(draugiem_login_url(str_replace('&amp;','&',wp_nonce_url($pageURL, 'draugiem'))));
		
	echo '<a href="'.$pageURL.'" class="draugiem_passport_link"><img class="draugiem_passport_img" border="0" src="'.get_option('siteurl') . '/wp-content/plugins/draugiem-pase/pase.png" alt="Draugiem.lv pase" /></a><br />';

}


function draugiem_login_url ($redirect_url){
	global $draugiem_cfg;
	$hash = md5($draugiem_cfg['app_key'].$redirect_url);//Request checksum
	$link = DRAUGIEM_LOGIN_URL.'?app='.$draugiem_cfg['app_id'].'&hash='.$hash.'&redirect='.urlencode($redirect_url);
	return $link;
}

function draugiem_session_init(){
	global $draugiem_cfg, $wpdb;
	
	if(isset($_GET['dr_auth_code'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'draugiem')){// New session authorization
		$response = draugiem_api_call('authorize', array('code'=>$_GET['dr_auth_code']));
		if($response && isset($response['apikey'])){//API key received
			//User profile info
			$userData = reset($response['users']);
						
			if(!empty($userData)){
			

				$root = dirname(dirname(dirname(dirname(__FILE__))));
				require_once($root . '/wp-includes/registration.php');

				$uid = $userData['uid'];
				$usermeta = array(
					'uid'=>$uid,
					'apikey'=>$response['apikey'],
					'sex'=>$userData['sex'],
					'image'=>$userData['img'],
					'place'=>htmlspecialchars($userData['place']),
					'age'=>(int)$userData['age'],
					'updated'=>date('Y-m-d H:i:s'),
					'active'=>1,
				);
				
				if(!($wpuser = draugiem_uid_to_wpuser($uid))){
					$username = '_draugiem_' . $uid;
					if (username_exists($username)) {
					  $username .= '_'.rand(0,1000000) ;
					}
					$userinfo = array(
						'user_login' => $username,
						'display_name' => $userData['name'] .' '.$userData['surname'],
						'user_email' => '',
						'first_name' => $userData['name'],
						'last_name' => $userData['surname'],
						'nickname' => $userData['nick'],
						'user_pass' => wp_generate_password(),
						'role' => 'subscriber',
					 );
					
					  $wpuser = wp_insert_user($userinfo);
					  if($wpuser) {
							$usermeta['wpuid'] = $wpuser;
							$usermeta['created'] = $usermeta['updated'];
							$wpdb->insert( $wpdb->prefix.'draugiem_users', $usermeta, array( '%d', '%s', '%s', '%s', '%s', '%d', '%s','%d','%d','%s'));
					  }
				} else {
					$wpdb->update( $wpdb->prefix.'draugiem_users', $usermeta, array('wpuid'=>$wpuser), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d',), array('%d'));
					wp_update_user(array(
						'ID' => $wpuser,
						'display_name' => $userData['name'] .' '.$userData['surname'],
						'first_name' => $userData['name'],
						'last_name' => $userData['surname'],
						'nickname' => $userData['nick'],
					 ));
				}
				if($wpuser){
					wp_set_current_user($wpuser);
					wp_set_auth_cookie($wpuser, $draugiem_cfg['remember'], get_user_option('use_ssl', $wpuser));
					wp_redirect(remove_query_arg(array('dr_auth_code','dr_auth_status','_wpnonce')));
					die;
				}
			}
		}
	}
}

function draugiem_uid_to_wpuser($uid) {
	global $wpdb;
    $sql = "SELECT wpuid FROM {$wpdb->prefix}draugiem_users WHERE uid = '%d'";
	return $wpdb->get_var($wpdb->prepare($sql, $uid));
}

function draugiem_comments($comments){
	$uids = array();
	foreach($comments as $k=>$v){
		if($v->user_id){
			$uids[$v->user_id] = $v->user_id;
		}
	}
	$users = draugiem_get_wpusers($uids);
	return $comments;
}

function draugiem_comments_author($author){
	global $comment, $draugiem_cfg;
	 if (strpos($_SERVER['REQUEST_URI'], 'wp-admin')) {
		return $author;
	}
	if($comment->user_id){
		$user = draugiem_get_wpusers($comment->user_id);
		if(isset($user['display_name'])){
			return ($draugiem_cfg['icon']?'<img src="'.get_option('siteurl') . '/wp-content/plugins/draugiem-pase/icon.png" alt="" /> ':'').$user['display_name'];
		}
	}
	return $author;
}

function draugiem_comments_url($url){
	global $comment;
	if(empty($url) && $comment->user_id){
		$user = draugiem_get_wpusers($comment->user_id);
		if(isset($user['uid'])){
			return 'http://www.draugiem.lv/friend/?'.$user['uid'];
		}
	}
	return $url;
}
function draugiem_avatar($data, $comment, $size){
	$uid = false;
	if(!empty($comment->user_id)){
		$uid = $comment->user_id;
	} elseif(!is_object($comment) && is_numeric($comment)){
		$uid = (int)$comment;
	}
	if($uid){
		$user = draugiem_get_wpusers($uid);
		if(!empty($user['image'])){
			if($size<=50){
				$user['image'] = str_replace('sm_','i_', $user['image']);
			}
			return preg_replace(array('/src="(.*?)"/',
		"/src='(.*?)'/"),'src="'.$user['image'].'"',$data);
		}
	}
	return $data;
}

function draugiem_get_wpusers($ids){
	global $wpdb;
	static $cache = array();
	if(!is_array($ids)){
		$ids = (int)$ids;
		if(isset($cache[$ids])){
			return $cache[$ids];
		}
		$cache[$ids] = $wpdb->get_row("SELECT a.*, b.display_name FROM {$wpdb->prefix}draugiem_users a INNER JOIN $wpdb->users b ON a.wpuid=b.ID WHERE wpuid = $ids AND a.active=1", ARRAY_A);
		return $cache[$ids];
	} elseif(!empty($ids)) {
		$idlist = implode(',',$ids);
		$data = $wpdb->get_results("SELECT a.*, b.display_name FROM {$wpdb->prefix}draugiem_users a INNER JOIN $wpdb->users b ON a.wpuid=b.ID WHERE wpuid IN($idlist) AND a.active=1", ARRAY_A);
		$results = array();
		if(!empty($data)){
			foreach($data as $val){
				$results[$val['wpuid']] = $val;
			}	
		}
		foreach($ids as $id){
			$cache[$id] = isset($results[$id]) ? $results[$id] : false;
		}
		return $results;
	}
	return array();
}

function draugiem_update_users(){
	global $wpdb, $draugiem_cfg;

	
	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-includes/registration.php');
	
	$wpdb->query("DELETE FROM {$wpdb->prefix}draugiem_users WHERE wpuid NOT IN(SELECT ID from $wpdb->users)");

	$sql = "SELECT count(*) FROM {$wpdb->prefix}draugiem_users WHERE updated < DATE_ADD(NOW(), INTERVAL - 1 DAY)";
	$total = $wpdb->get_var($wpdb->prepare($sql));
	if($total == 0){
		return;
	}
	$pp = 100;
	$pages = ceil($total/$pp);
	for($i = 0; $i<$pages; $i++){
		$start = $i*$pp;
		$data = $wpdb->get_results("SELECT uid,wpuid FROM {$wpdb->prefix}draugiem_users WHERE updated < DATE_ADD(NOW(), INTERVAL - 1  DAY) ORDER BY uid ASC LIMIT $start,$pp", ARRAY_A);
		$users = array();
		if(!empty($data)){
			foreach($data as $val){
				$users[$val['uid']] = $val['wpuid'];
			}
		}
		
		$userList = draugiem_api_call('userdata', array('ids'=>implode(',',array_keys($users))));
		if(isset($userList['users'])){
			foreach($userList['users'] as $userData){
				$wpdb->update( $wpdb->prefix.'draugiem_users', array(
					'sex'=>$userData['sex'],
					'image'=>$userData['img'],
					'place'=>htmlspecialchars($userData['place']),
					'age'=>(int)$userData['age'],
					'updated'=>date('Y-m-d H:i:s'),
					'active'=>1,
				), array('uid'=>$userData['uid']), array('%s', '%s', '%s', '%d', '%s', '%d'), array('%d'));
				wp_update_user(array(
					'ID' => $users[$userData['uid']],
					'display_name' => $userData['name'] .' '.$userData['surname'],
					'first_name' => $userData['name'],
					'last_name' => $userData['surname'],
					'nickname' => $userData['nick'],
				 ));	
				 unset($users[$userData['uid']]);		
			}
			if(!empty($users)){//Padara neaktīvus tos, kas vairs neizmanto API
				$users = implode(',',$users);
				$wpdb->query("UPDATE {$wpdb->prefix}draugiem_users SET active=0 WHERE wpuid IN($users)");
			}
		}
	}
}


 function draugiem_block_admin_panel () {
	global $draugiem_user;
     if (current_user_can('level_10')) {  
         return;  
     } elseif($draugiem_user) {
		wp_redirect(get_option('siteurl'));
		die;
	}  
 }  
 
function draugiem_api_call($action, $args = array()){
	global $draugiem_cfg, $draugiem_user;
	
	$url =DRAUGIEM_API_URL.'?app='.$draugiem_cfg['app_key'];
	if($draugiem_user){//User has been authorized
		$url.='&apikey='.$draugiem_user['apikey'];
	}
	$url.='&action='.$action;
	if(!empty($args)){
		foreach($args as $k=>$v){
			if($v!==false){
				$url.='&'.$k.'='.urlencode($v);
			}
		}
	}
	$response = wp_remote_get($url);//Get API response (@ to avoid accidentaly displaying API keys in case of errors)

	if(is_wp_error($response) || empty($response['body'])){//Request failed
		return false;
	} 
	$response = unserialize($response['body']);
	if(is_array($response) && !isset($response['error'])){//Empty response
		return $response;
	}
	return false;
}	
 

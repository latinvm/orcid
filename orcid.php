<?php
/*
Plugin Name: ORCID
Version: 0.5
Plugin URI: https://github.com/ServerDotBiz/orcid
Description: This plugin adds a field for ORCID to users posts and comments
Author: Roy Boverhof
Author URI: http://www.elsevier.com
*/

/*  Copyright 2014 Roy Boverhof (email: r.boverhof@elsevier.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class wpORCID {
	/* not used for now, might be useful later */
	protected $pluginPath;
	
    public function __construct(){
		$this->pluginPath = dirname(__FILE__);
		$this->assets_path = plugins_url('assets/', __FILE__);
		
		/* hook actions / filters onto WP functions */
		//js and css
		add_action('wp_enqueue_scripts',array($this,'add_orcid_assets'));
		add_action('admin_enqueue_scripts', array($this, 'add_orcid_assets'));
		
		if ( get_option('add-orcid-to-comments', 'on') == 'on' ) {
			//add to comments fields
			add_filter('comment_form_default_fields',array($this,'comment_form_custom_fields'));
			add_action('comment_post',array($this,'save_comment_metadata'));	
			//add to coment output
			add_filter('comment_text',array($this,'comment_orcid_text'));
		}
		
		//user profile hooks
		add_action('user_new_form',array($this,'show_user_profile_orcid'));
		add_action('edit_user_profile',array($this,'show_user_profile_orcid'));
		add_action('show_user_profile',array($this,'show_user_profile_orcid'));
		add_action('personal_options_update',array($this,'update_user_profile_orcid'));
		add_action('edit_user_profile_update',array($this,'admin_user_profile_orcid'));
		add_action('user_register',array($this,'admin_user_profile_orcid'));
		
		//add options page to settings menu
		add_action('admin_menu', array($this, 'orcid_settings_menu'));
		
		//output
		
		add_filter('the_content',array($this,'the_content_orcid'));
		
		if ( get_option('use-orcid-shortcode') ) {
			/* add a shortcode [ORCID] to be used in templates */
			add_shortcode( get_option('orcid-shortcode'), array($this,'shortcode') );
		}
		
		/* add shortcode support for widgets */
		add_filter('widget_text', 'do_shortcode');
    }
	
	/* add the default plugin stylesheet */
	public function add_orcid_assets() {
		wp_enqueue_style( 'prefix-style', plugins_url('assets/orcid.css', __FILE__) );
		//MUST be queued such and jQuery loads first
		wp_enqueue_script( 'orcid-javascript', 
			plugins_url('assets/orcid.js', __FILE__),
			array( 'jquery' ) 
		);
	}
	
	/*Add the ORCID settings menu*/
	function orcid_settings_menu() {
		add_options_page('ORCID for Wordpress', 'ORCID for Wordpress', 
		'activate_plugins', 'orcid-settings', array($this, 'orcid_settings_form'));
		add_action( 'admin_init', array($this, 'orcid_register_settings') );
		
	}
	
	function orcid_register_settings() {
		register_setting('orcid_settings_group', 'add-orcid-to-posts');
		register_setting('orcid_settings_group', 'add-orcid-to-pages');
		register_setting('orcid_settings_group', 'add-orcid-to-comments');
		register_setting('orcid_settings_group', 'use-orcid-shortcode');
		register_setting('orcid_settings_group', 'orcid-shortcode');
	}
	
	function orcid_settings_form() {
		if ( $_SERVER['REQUEST_METHOD'] === 'POST') {
			check_admin_referer( 'orcid_nonce' );
		} else {
			?>
				<div class = "wrap">
					<h2>ORCID for Wordpress Settings</h2>
					<form method = "POST" action="options.php" id="orcid-settings">
						<?php settings_fields('orcid_settings_group'); ?>
						<table class="form-table">
							<tr>
								<td>Add ORCID to</td>
								<td>
									<?php checkbox('add-orcid-to-posts', 'Posts', 'on'); ?><br />
									<?php checkbox('add-orcid-to-pages', 'Pages'); ?><br />
									<?php checkbox('add-orcid-to-comments', 'Comments', 'on'); ?><br />
									<?php checkbox('use-orcid-shortcode', 'Shortcode'); ?>
									[<input type="text" name="orcid-shortcode"
									value="<?php echo get_option('orcid-shortcode', 'ORCID'); ?>"
									/ >]	
								</td>								
								<td></td>
							</tr>
							
							
							<tr>
								<td></td>
								<td><input type="submit" name="submit" value="Save changes" class="button-primary" /></td>
								<td></td>
							</tr>
						</table>
					</form>
				</div>		
			<?php
		}
	}
	
	
	/* override default comment fields */
	public function comment_form_custom_fields($fields) {
		$commenter = wp_get_current_commenter();
		
		$req = get_option('require_name_email');
		if ($req){
			$aria_req = ' aria-required="true"';
		} else {
			$aria_req = '';
		}

		$fields['author'] = '<p class="comment-form-author"><label for="author">'.__( 'Name' ).($req ? '<span class="required">*</span>' : '').'</label>'.'<input id="author" name="author" type="text" value="'.esc_attr($commenter['comment_author']).'" size="30" '.$aria_req.' /></p>';
		$fields['email'] = '<p class="comment-form-email"><label for="email">'.__( 'Email' ).($req ? '<span class="required">*</span>' : '').'</label>'.'<input id="email" name="email" type="text" value="'.esc_attr($commenter['comment_author_email']).'" size="30" '.$aria_req.' /></p>';
		$fields['url'] = '<p class="comment-form-url"><label for="url">'.__( 'Website' ).'</label><input id="url" name="url" type="text" value="'.esc_attr($commenter['comment_author_url'] ).'" size="30" /></p>';
		$fields['orcid'] = '<p class="comment-form-orcid"><label for="orcid">ORCID
			<img src = "'.$this->assets_path.'orcid.png'.'" id = "orcid-success" />
			<img src = "'.$this->assets_path.'close-icon.png" id = "orcid-failure" />
		</label>
		<input id="orcid" name="orcid" type="text" /><br />
		<span class="comment-notes">e.g. 0000-0002-7299-680X</span></p>';

		return $fields;
	}
	
	/* Attach additional comment data */
	/* This does not override the comment insertion. The comment has already
	been created at the time this hook is run. I don't think there is a hook
	to validate comments, so if we want to add ORCID comment validation, we'll
	have to write a new comments.php script*/
	public function save_comment_metadata($comment_id) {
		if ((isset($_POST['orcid'])) && ($_POST['orcid'] != '')){
			$orcid = wp_filter_nohtml_kses($_POST['orcid']);
			$orcid = $this->strip_orcid_url($orcid);
			if ( $this->validate_orcid($orcid) ) {
				add_comment_meta($comment_id,'orcid',$orcid);
			}
		}
	}
	
	/*covers cases in which the user enters the full URL to their ORCID profile.
	Finds 'orcid.org/' in the inputed string and returns the entire string following it*/
	function strip_orcid_url($user_input) {
		if ( ( $b = strpos($user_input, 'orcid.org/') ) !== FALSE ) {
			$e = $b + 10; //length of 'orcid.org/'
			$orcid = substr($user_input, $e);
		} else {
			$orcid = $user_input;
		}
		return $orcid;	
	}
	
	/*Validate the ORCID number to ensure it only contains letters, numbers, and the dash.
	In the future we should probably use the ORCID API to ensure that it's associated with
	an actual profile (this would reduce comment spam substantially)*/
	function validate_orcid($user_input) {
		if ( preg_match('/[^0-9A-Za-z\-]/', $user_input) ) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	
	/* add ORCID field to user profile / user admin forms */
	public function show_user_profile_orcid($user) {
		$orcid = $user->orcid;
		echo '<table class="form-table"><tr><th><label for="orcid">ORCID</label></th><td><input type="text" id="orcid" name="orcid" class="regular-text" value="'.$orcid.'" />
		<img src = "'.$this->assets_path.'orcid.png'.'" id = "orcid-success" />
		<img src = "'.$this->assets_path.'close-icon.png" id = "orcid-failure" />		
		<br /><span class="description">Add your ORCID here. (e.g. 0000-0002-7299-680X)</span></td></tr></table>';
	}

	/* update orcid metadata for user */
	public function update_user_profile_orcid(){
		global $user_ID;
		update_user_meta($user_ID,'orcid',$_POST['orcid']);    
	}

	/* update orcid metadata from admin pages */
	public function admin_user_profile_orcid($user_id){
		update_user_meta($user_id,'orcid',$_POST['orcid']);    
	}
	
	/* add ORCID link to default to comment text, comment this function if you want to modify your templates and manually add the ORCID using get_the_comment_orcid() */
	public function comment_orcid_text($text){
		// get the comment's ORCID
		$orcid = $this->get_the_comment_orcid();
		
		if (!$orcid){
			// get user's metadata if an existing user
			$orcid = get_user_meta($this->orcid_get_comment_author_id(get_comment_ID()),'orcid',true);
		}
		
		if ($orcid){
			// allow HTML override
			$html = $this->orcid_html($orcid);
			
			return $html.$text;
 
		} else {
			return $text;
		}
	}
	
	/* add orcid to top of post content, comment out this function if you want to use get_the_author_orcid() to manually add the ORCID to the post template */
	function the_content_orcid($content){
		//check if ORCID HTML goes on this post type
		if ( get_post_type() == 'post' ) {
			if ( !get_option( 'add-orcid-to-posts', 'on' ) ) return $content; 
		} elseif ( get_post_type() == 'page' ) {
			if ( !get_option( 'add-orcid-to-pages') ) return $content;
		} else {
			//all other post types
			return $content;
		}
		
		// get author's ORCID
		if ( $orcid = $this->get_the_author_orcid() ) {
			// allow HTML override
			$html = $this->orcid_html($orcid);
			return $html.$content;
		} else {
			return $content;
		}
	}
	
	/* get comment author ID because WP's function returns display name */
	function orcid_get_comment_author_id($comment_id){
		$comment = get_comment($comment_id);

		if ($comment->user_id && $user = get_userdata($comment->user_id)) {
			return $user->ID;
		} else {
			return false;
		}
	}

	/* returns the comments orcid */
	public function get_the_comment_orcid(){
		$orcid = get_comment_meta(get_comment_ID(),'orcid',true);
		return $orcid;
	}
	
	/* returns the authors orcid */
	public function get_the_author_orcid(){
		$orcid = get_the_author_meta('orcid');
		return $orcid;
	}
	
	/* use the shortcode [ORCID] to display the authors ORCID in a post */
	public function shortcode(){	
		$orcid = get_the_author_meta('orcid');
		return $this->orcid_html($orcid);
    }
    
    public function orcid_html($orcid) {
		return sprintf('<div class="wp_orcid_field"><a href="http://orcid.org/%s" target="_blank" rel="author">%s</a></div>', $orcid, $orcid);
	}
}

$wpORCID = new wpORCID();

/* returns the comments orcid for use in custom templates simply use <?php orcid_comment(); ?> in a comment template to display the comments ORCID */
function orcid_comment(){
	global $wpORCID;
	
	$orcid = $wpORCID->get_the_comment_orcid();
	if (!$orcid){
		// get user's metadata if an existing user
		$orcid = get_user_meta($wpORCID->orcid_get_comment_author_id(get_comment_ID()),'orcid',true);
	}
	
	if ($orcid){
		echo $orcid;
	} else {
		echo '';
	}
}

/* returns the authors orcid for use in custom templates simply use <?php orcid_author(); ?> in a content tempate to display the authors ORCID */
function orcid_author(){
	global $wpORCID;
	
	$orcid = $wpORCID->get_the_author_orcid();
	if ($orcid){
		echo $orcid;
	} else {
		echo '';
	}
}

function checkbox($name, $label, $default = FALSE) {
	echo '<input type="checkbox" name="'.$name.'" ';
	if ( get_option($name, $default) == 'on' ) {
		echo 'checked="checked" />'; 
	} else {
		echo '/>';
	}
	echo '<label for="'.$name.'">'.$label.'</label>';
} 

?>
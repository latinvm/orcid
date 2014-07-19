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
		register_setting('orcid_settings_group', 'orcid-display');
		register_setting('orcid_settings_group', 'orcid-approve-comments');
		register_setting('orcid_settings_group', 'orcid-html-position');
		register_setting('orcid_settings_group', 'orcid-html-comments-position');
	}
	
	function orcid_settings_form() {
		$f = new OrcidFormFields();
		?>
		<div class = "wrap">
			<h2>ORCID for Wordpress Settings</h2>
			<form method = "POST" action="options.php" id="orcid-settings">
				<?php settings_fields('orcid_settings_group'); ?>
				<table class="form-table">
					<tr>
						<td>Automatically add ORCID to</td>
						<td>
							<?php $f->checkbox('add-orcid-to-posts', 'Posts', 'on'); ?><br />
							<?php $f->checkbox('add-orcid-to-pages', 'Pages'); ?><br />
							<?php $f->checkbox('add-orcid-to-comments', 'Comments', 'on'); ?><br />
							<?php $f->checkbox('use-orcid-shortcode', 'Shortcode'); ?>
							[<input type="text" name="orcid-shortcode"
							value="<?php echo get_option('orcid-shortcode', 'ORCID'); ?>"
							/ >]<br />
							Note: You can insert ORCIDs into templates directly using <b>the_orcid_author()</b> and <b>the_orcid_comment_author()</b>
						</td>								
						<td></td>
					</tr>
					
					<tr>
						<td>ORCID position and display text</td>
						<td>
							<?php $f->radio('orcid-html-position', 'top', 'Top of posts', 'top'); ?><br />
							<?php $f->radio('orcid-html-position', 'bottom', 'Bottom of posts'); ?><br /><br />
							<?php $f->radio('orcid-html-comments-position', 'top', 'Top of comments', 'top'); ?><br />
							<?php $f->radio('orcid-html-comments-position', 'bottom', 'Bottom of comments'); ?><br /><br />
							<?php $f->radio('orcid-display', 'numbers', 'Show ORCID numbers', 'numbers'); ?><br />
							<?php $f->radio('orcid-display', 'names', 'Show author\'s name'); ?>
						</td>
						
					</tr>
					
					<tr>	
						<td>Comment validation</td>
						<td>
							<?php $f->checkbox('orcid-approve-comments', 'Automatically approve comments with valid ORCIDs'); ?>
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
			<img src = "'.$this->assets_path.'orcid.png'.'" id = "orcid-success" class = "orcid-icon" />
			<img src = "'.$this->assets_path.'close-icon.png" id = "orcid-failure" class = "orcid-icon" />
			<img src = "'.$this->assets_path.'orcid-waiting.gif" id = "orcid-waiting" class = "orcid-icon" />
		</label>
		<input id="orcid" name="orcid" type="text" /><br />
		<span id="orcid-instructions">Add your ORCID here. (e.g. 0000-0002-7299-680X)</span></p>';

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
				$api = new OrcidAPI($orcid);
				if ( $api->connection && get_option('orcid-approve-comments') ) {
					add_comment_meta($comment_id, 'orcid-name', $api->name );
					//approve the comment if the ORCID profile was found and that option is set
					wp_set_comment_status($comment_id, 'approve');
				} elseif ( $api->connection ) {
					add_comment_meta($comment_id, 'orcid-name', $api->name );
				} else {
					//fallback is to set the name to an empty string and replace with the number
					//at output time
					add_comment_meta($comment_id, 'orcid-name', '');
				}
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
		<img src = "'.$this->assets_path.'orcid.png'.'" id = "orcid-success" class = "orcid-icon" />
		<img src = "'.$this->assets_path.'close-icon.png" id = "orcid-failure" class = "orcid-icon" />
		<img src = "'.$this->assets_path.'orcid-waiting.gif" id = "orcid-waiting" class = "orcid-icon" />	
		<br /><span id="orcid-instructions">Add your ORCID here. (e.g. 0000-0002-7299-680X)</span></td></tr></table>';
	}

	/* update orcid metadata for user */
	public function update_user_profile_orcid(){
		global $user_ID;
		$orcid = $_POST['orcid'];
		update_user_meta( $user_ID, 'orcid', $orcid );
		$api = new OrcidAPI($orcid);
		if ( $api->connection ) {
			update_user_meta( $user_ID, 'orcid-name', $api->name );
		}    
	}

	/* update orcid metadata from admin pages */
	public function admin_user_profile_orcid($user_id){
		$orcid = $_POST['orcid'];
		update_user_meta( $user_id, 'orcid', $orcid );
		$api = new OrcidAPI($orcid);
		if ( $api->connection ) {
			update_user_meta( $user_id, 'orcid-name', $api->name );
		}  
	}
	
	/* add ORCID link to default to comment text */
	/* This function will only execute if add-orcid-to-comments is set to 'on' */
	public function comment_orcid_text($text){
		
		$field = new OrcidField('comment');
		
		//make sure we have an ORCID, if not just return the input
		if ( !$field->orcid ) {
			return $text;
		}
		
		//output HTML based on the set position
		if ( get_option('orcid-html-comments-position', 'bottom') == 'bottom' ) return $text.$field->html; 
		else return $field->html.$text;
		
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
		
		$field = new OrcidField('author');
		
		// get author's ORCID (make sure we have an ORCID set)
		if ( !$field->orcid ) {
			return $content;
		}
		
		//output HTML based on the set position
		if ( get_option('orcid-html-position', 'bottom') == 'bottom' ) return $content.$field->html; 
		else return $field->html.$content;

	}
	
	/* use the shortcode [ORCID] to display the authors ORCID in a post */
	public function shortcode(){	
		$field = new OrcidField('author');
		return $field->html;
    }
}

class OrcidAPI {
	public $profile_xml, $connection, $name;
	function __construct($orcid) {
		$url = 'http://pub.orcid.org/'.$orcid;
		if ( $this->profile_xml = $this->remote_call($url) ) {
			$this->connection = TRUE;
		} else {
			throw new Exception('No connection');
			$this->connection = FALSE;
			$this->name = FALSE;
		}
		$dom = new DOMDocument();
		$dom->loadXML($this->profile_xml);
		$node = $dom->getElementsByTagName('credit-name');
		$this->name = $node->item(0)->nodeValue;
	}
	
	
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * Taken from impactpubs by Casey A. Ydenberg
	 * string page remote_call(string url)
	 * Checks if the WP HTTP functions are installed.
	 * If they are, uses WP to retrieve the page and returns the page body.
	 * If not, uses file_get_contents and passes back the whole page.
	 */
	function remote_call($url) {
		if ( function_exists('wp_remote_get') ) {
			return wp_remote_retrieve_body( wp_remote_get($url) );
		} else {
			try {
				$file = file_get_contents($url);
				return $file;
			} catch (Exception $e) {
				throw new Exception('No connection');
				return FALSE;
			}
		}
	}
}

/*Output functions*/

class OrcidField {
	public $type, $orcid, $orcid_name, $html;

	function __construct($type) {
		$this->type = $type;
		//set orcid info for comments	
		if ( $type == 'comment' ) {
			// get the comment's ORCID		
			$this->orcid = get_comment_meta(get_comment_ID(),'orcid',true);
			$this->orcid_name = get_comment_meta(get_comment_ID(),'orcid-name',true);
			
			if (!$this->orcid){
				// get user's metadata if an existing user
				$this->orcid = get_user_meta($this->get_comment_author_id(get_comment_ID()),'orcid',true);
				$this->orcid_name = get_user_meta($this->get_comment_author_id(get_comment_ID()),'orcid-name',true);
			}
			
			//set orcid_name to an empty string if we couldn't find it
			if (!$this->orcid_name) $this->orcid_name = '';
			
		} elseif ( $type == 'author' ) {
			
			//find metadata for the post author
			$this->orcid = get_the_author_meta('orcid');
			$this->orcid_name = get_the_author_meta('orcid-name');		
			//set orcid_name to an empty sting if we couldn't find it
			if ( !$this->orcid_name ) $this->orcid_name = '';
		
		} else throw new Exception('Invalid OrcidField type supplied');
		
		//set the HTML output for the object
		$this->html = $this->get_orcid_html();
	
	}
	
	/* get comment author ID because WP's function returns display name */
	function get_comment_author_id($comment_id){
		$comment = get_comment($comment_id);

		if ($comment->user_id && $user = get_userdata($comment->user_id)) {
			return $user->ID;
		} else {
			return false;
		}
	}
    

    public function get_orcid_html() {
		if ( get_option('orcid-display') == 'names' && $this->orcid_name != '' ) {
			return sprintf(
				apply_filters('orcid_field_html','<div class="wp_orcid_field"><a href="http://orcid.org/%s" target="_blank" rel="author">%s</a></div>'),
				$this->orcid,
				$this->orcid_name
			);
		} else {
			return sprintf(
				apply_filters('orcid_field_html','<div class="wp_orcid_field"><a href="http://orcid.org/%s" target="_blank" rel="author">%s</a></div>'),
				$this->orcid,
				$this->orcid
			);
		}
	}
}

$wpORCID = new wpORCID();

/* returns the comments orcid for use in custom templates simply use <?php the_orcid_comment_author(); ?> in a comment template to display the comments ORCID */
function the_orcid_comment_author(){

	$field = new OrcidField('comment');
	echo $field->html;
	
}

/* returns the authors orcid for use in custom templates simply use <?php orcid_author(); ?> in a content tempate to display the authors ORCID */
function the_orcid_author(){
	
	$field = new OrcidField('author');
	echo $field->html;
	
}

class OrcidFormFields {
	public function checkbox($name, $label, $default = FALSE) {
		echo '<input type="checkbox" name="'.$name.'" ';
		if ( get_option($name, $default) == 'on' ) {
			echo 'checked="checked" />'; 
		} else {
			echo '/>';
		}
		echo '<label for="'.$name.'">'.$label.'</label>';
	} 
	
	public function radio($name, $value, $label, $default = FALSE) {
		if ( get_option($name, $default) == $value ) $checked = 'checked="checked"';
		else $checked = '';
		echo sprintf(
			'<input type="radio" name="%s" value="%s" %s /><label for=%s>%s</label>', 
			$name, $value, $checked, $value, $label
		);
	}
}

?>
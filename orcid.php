<?php
/*
Plugin Name: ORCID
Version: 0.42
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

add_action( 'wp_enqueue_scripts', 'add_orcid_stylesheet' );
function add_orcid_stylesheet() {
	wp_enqueue_style( 'prefix-style', plugins_url('assets/orcid.css', __FILE__) );
}

/* override default comment fields */
add_filter('comment_form_default_fields','comment_form_custom_fields');
function comment_form_custom_fields($fields) {
	$commenter = wp_get_current_commenter();
	
	$req = get_option( 'require_name_email' );
	if ($req){
		$aria_req = ' aria-required="true"';
	} else {
		$aria_req = '';
	}

	$fields['author'] = '<p class="comment-form-author"><label for="author">'.__( 'Name' ).($req ? '<span class="required">*</span>' : '').'</label>'.'<input id="author" name="author" type="text" value="'.esc_attr($commenter['comment_author']).'" size="30" tabindex="1"'.$aria_req.' /></p>';
	$fields['email'] = '<p class="comment-form-email"><label for="email">'.__( 'Email' ).($req ? '<span class="required">*</span>' : '').'</label>'.'<input id="email" name="email" type="text" value="'.esc_attr($commenter['comment_author_email']).'" size="30"  tabindex="2"'.$aria_req.' /></p>';
	$fields['url'] = '<p class="comment-form-url"><label for="url">'.__( 'Website' ).'</label><input id="url" name="url" type="text" value="'.esc_attr($commenter['comment_author_url'] ).'" size="30"  tabindex="3" /></p>';
	$fields['orcid'] = '<p class="comment-form-orcid"><label for="orcid">ORCID</label><input id="orcid" name="orcid" type="text" size="30"  tabindex="4" /></p>';
	
	return $fields;
}

/* override default comment action */
add_action('comment_post','save_comment_metadata');
function save_comment_metadata($comment_id) {
	if ((isset($_POST['orcid'])) && ($_POST['orcid'] != '')){
		$orcid = wp_filter_nohtml_kses($_POST['orcid']);
		// todo: add filter to validate ORCID
	}
	add_comment_meta($comment_id,'orcid',$orcid);
}

/* add ORCID link to default to comment text, comment this function if you want to modify your templates and manually add the ORCID using get_the_comment_orcid() */
add_filter('comment_text','comment_orcid_text');
function comment_orcid_text($text){
	$orcid = get_the_comment_orcid();
	if ($orcid) {
		$text = '<div class="wp_orcid_comment"><a href="http://orcid.org/'.$orcid.'" target="_blank" rel="author">'.$orcid.'</a></div>'.$text;
	}
	
	return $text;
}

/* returns the comments orcid for use in custom templates simply use <?php echo get_the_comment_orcid(); ?> in a comment template to display the comments ORCID */
function get_the_comment_orcid(){
	$orcid = get_comment_meta(get_comment_ID(),'orcid',true);
    return $orcid;
}

/* add ORCID field to user profile / user admin forms */
add_action('user_new_form', 'show_user_profile_orcid');
add_action('edit_user_profile', 'show_user_profile_orcid');
add_action('show_user_profile', 'show_user_profile_orcid');
function show_user_profile_orcid($user) {
	$orcid = $user->orcid;
	echo '<table class="form-table"><tr><th><label for="orcid">ORCID</label></th><td><input type="text" id="orcid" name="orcid" class="regular-text" value="'.$orcid.'" /><br /><span class="description">Add your ORCID here. (e.g. 0000-0002-7299-680X)</span></td></tr></table>';
}

/* update orcid metadata for user */
add_action('personal_options_update', 'update_user_profile_orcid');
function update_user_profile_orcid(){
    global $user_ID;
    update_user_meta($user_ID,'orcid',$_POST['orcid']);    
}

/* update orcid metadata from admin pages */
add_action('edit_user_profile_update', 'admin_user_profile_orcid');
add_action('user_register', 'admin_user_profile_orcid');
function admin_user_profile_orcid($user_id){
    update_user_meta($user_id,'orcid',$_POST['orcid']);    
}

/* add orcid to top of post content, comment out this function if you want to use get_the_author_orcid() to manually add the ORCID to the post template */
add_filter('the_content', 'the_content_orcid');
function the_content_orcid($content){
	$orcid = get_the_author_orcid();
    echo '<div class="wp_orcid_post"><a href="http://orcid.org/'.$orcid.'" target="_blank" rel="author">'.$orcid.'</a></div>'.$content;   
}

/* returns the authors orcid for use in custom templates simply use <?php echo get_the_author_orcid(); ?> in a content tempate to display the authors ORCID */
function get_the_author_orcid(){
	$orcid = get_the_author_meta('orcid');
    return $orcid;
}
?>
ORCID WordPress Plugin
=====

This is a WordPress plugin that displays ORCIDs in comments and posts.

TODO:


- use OAuth to allow social login via orcid.org
- add ORCID to admin/update comment page

##Settings##

The ORCID Wordpress plugin adds a Settings page and a user option to the Wordpress admin interface. Users can add their ORCID to their profile, and administrators can choose to add these to posts, or pages, at either the top or the bottom of the post. The ORCID number is displayed as text, hyperlinked to the users ORCID profile, along with the ORCID logo.

In addition, administators can allow commentors to add their ORCID. 

## Notes for theme developers ##

The ORCID HTML output is attached by default to either the top or bottom of `the_content`, or at the top or bottom the the `comment_text`. This can be overridden by turning off the settings for adding ORCID automatically, and placing the `the_orcid_author` or `the_orcid_comment_author` directly in the theme code.

The default HTML produced is `<div class="wp_orcid_field"><a href="http://orcid.org/%s" target="_blank" rel="author">%s</a></div>`, where `%s` in each case represents the ORCID number. This can be overridden by creating a hook called `orcid_field_html` for example:

    add_filter('orcid_field_html', 'my_orcid_field_html');
    function my_orcid_field_html() {
    	return '<ul><li>ORCID: %s</li></ul>'
    }

 to get something really ugly.
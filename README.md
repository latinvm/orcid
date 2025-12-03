# ORCID WordPress Plugin

A WordPress plugin that displays ORCID (Open Researcher and Contributor ID) identifiers in comments and posts.

## Description

This plugin allows WordPress users to add their ORCID to their profile, which can then be displayed on posts and pages. Commenters can also optionally enter their ORCID in the comment form.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Features

- Add ORCID field to user profiles
- Display author ORCID on posts and pages
- Optional ORCID field in comment forms
- Real-time ORCID validation via the ORCID public API
- Auto-approve comments from verified ORCID holders
- Customizable display position (top/bottom of content)
- Display ORCID numbers or author names
- Shortcode support for flexible placement
- Template functions for theme integration
- Internationalization ready

## Installation

1. Upload the `orcid` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings → ORCID for WordPress

## Settings

The plugin adds a Settings page accessible via Settings → ORCID for WordPress. Available options:

- **Automatically add ORCID to**: Choose to display ORCIDs on Posts, Pages, and/or Comments
- **Shortcode support**: Enable the `[ORCID]` shortcode for manual placement
- **Position**: Display at top or bottom of posts/comments
- **Display text**: Show ORCID numbers or author names (fetched from ORCID profiles)
- **Comment validation**: Automatically approve comments with valid ORCIDs

## Usage

### For Users

Add your ORCID to your WordPress profile under Users → Your Profile. The ORCID will be validated against the ORCID public API.

### For Theme Developers

#### Template Functions

Place these functions directly in your theme templates:

```php
// Display author's ORCID
<?php the_orcid_author(); ?>

// Display comment author's ORCID
<?php the_orcid_comment_author(); ?>
```

#### Shortcode

Use the shortcode in post/page content:

```
[ORCID]
```

#### Customizing HTML Output

Filter the HTML output using the `orcid_field_html` filter:

```php
add_filter( 'orcid_field_html', 'my_custom_orcid_html', 10, 4 );
function my_custom_orcid_html( $html, $orcid, $display_text, $type ) {
    return sprintf(
        '<span class="custom-orcid"><a href="https://orcid.org/%s">%s</a></span>',
        esc_attr( $orcid ),
        esc_html( $display_text )
    );
}
```

Parameters:
- `$html` - The default HTML output
- `$orcid` - The ORCID ID
- `$display_text` - The text being displayed (ORCID number or name)
- `$type` - Context type: 'comment' or 'author'

## Changelog

### 1.0.0
- Modernized codebase for WordPress 5.0+ and PHP 7.4+
- Added proper security with nonce verification
- Updated to use ORCID API v3.0 with HTTPS
- Added AJAX-based ORCID validation
- Improved input sanitization and output escaping
- Added PHP type declarations
- Added internationalization support
- Improved accessibility
- Singleton pattern for main plugin class
- WordPress Coding Standards compliance

### 0.5
- Initial public release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Original author: Roy Boverhof (Elsevier)
- Contributors: Casey A. Ydenberg

## Links

- [ORCID](https://orcid.org/)
- [Plugin Repository](https://github.com/latinvm/orcid)

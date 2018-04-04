<?php
/**
 * Template hierarchy class.
 *
 * The framework has its own template hierarchy that can be used instead of the
 * default WordPress template hierarchy.  It is not much different than the
 * default.  It was built to extend the default by making it smarter and more
 * flexible.  The goal is to give theme developers and end users an easy-to-override
 * system that doesn't involve massive amounts of conditional tags within files.
 *
 * @package   HybridCore
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2008 - 2018, Justin Tadlock
 * @link      https://themehybrid.com/hybrid-core
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid\Template;

use WP_User;
use function Hybrid\clean_post_format_slug;
use function Hybrid\filter_templates;
use function Hybrid\get_attachment_type;
use function Hybrid\get_attachment_subtype;
use function Hybrid\get_post_template;
use function Hybrid\get_term_template;
use function Hybrid\get_user_template;

/**
 * Overwrites the core WP template hierarchy.
 *
 * @since  5.0.0
 * @access public
 */
class Hierarchy {

	/**
	 * Array of template types in core WP.
	 *
	 * @link   https://developer.wordpress.org/reference/hooks/type_template_hierarchy/
	 * @since  5.0.0
	 * @access protected
	 * @var    array
	 */
	protected $types = [
	       'index',
	       '404',
	       'archive',
	       'author',
	       'category',
	       'tag',
	       'taxonomy',
	       'date',
	       'embed',
	       'home',
	       'frontpage',
	       'page',
	       'paged',
	       'search',
	       'single',
	       'singular',
	       'attachment'
	];

	/**
	 * Copy of the located template found when running through the
	 * template hierarchy.
	 *
	 * @since  5.0.0
	 * @access public
	 * @var    string
	 */
	public $located = '';

	/**
	 * An array of the entire template hierarchy for the current page view.
	 * This hierarchy does not have the `.php` file name extension.
	 *
	 * @since  5.0.0
	 * @access public
	 * @var    array
	 */
	public $hierarchy = [];

	/**
	 * Sets up template hierarchy filters.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		// Filter the front page template.
		add_filter( 'frontpage_template_hierarchy',  [ $this, 'front_page' ], 5 );

		// Filter the single, page, and attachment templates.
		add_filter( 'single_template_hierarchy',     [ $this, 'single' ], 5 );
		add_filter( 'page_template_hierarchy',       [ $this, 'single' ], 5 );
		add_filter( 'attachment_template_hierarchy', [ $this, 'single' ], 5 );

		// Filter taxonomy templates.
		add_filter( 'taxonomy_template_hierarchy', [ $this, 'taxonomy' ], 5 );
		add_filter( 'category_template_hierarchy', [ $this, 'taxonomy' ], 5 );
		add_filter( 'tag_template_hierarchy',      [ $this, 'taxonomy' ], 5 );

		// Filter the author template.
		add_filter( 'author_template_hierarchy', [ $this, 'author' ], 5 );

		// Filter the date template.
		add_filter( 'date_template_hierarchy', [ $this, 'date' ], 5 );

		// System for capturing the template hierarchy.
		foreach ( $this->types as $type ) {

			// Capture the template hierarchy for each type.
			add_filter( "{$type}_template_hierarchy", [ $this, 'template_hierarchy' ], PHP_INT_MAX );

			// Capture the located template.
			add_filter( "{$type}_template", [ $this, 'template' ], PHP_INT_MAX );
		}

		// Re-add the located template.
		add_filter( 'template_include', [ $this, 'template_include' ], PHP_INT_MAX );
	}

	/**
	 * Fix for the front page template handling in WordPress core. Its
	 * handling is not logical because it forces devs to account for both a
	 * page on the front page and posts on the front page.  Theme devs must
	 * handle both scenarios if they've created a "front-page.php" template.
	 * This filter overwrites that and disables the `front-page.php` template
	 * if posts are to be shown on the front page.  This way, the
	 * `front-page.php` template will only ever be used if an actual page is
	 * supposed to be shown on the front.
	 *
	 * Additionally, this filter allows the user to override the front page
	 * via the standard page template.  User choice should always trump
	 * developer choice.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  array   $templates
	 * @return array
	 */
	public function front_page( $templates ) {

		$templates = [];

		if ( ! is_home() ) {

			$custom = get_post_template( get_queried_object_id() );

			if ( $custom ) {
				$templates[] = $custom;
			}

			$templates[] = 'front-page.php';
		}

		// Return the template hierarchy.
		return $templates;
	}

	/**
	 * Overrides the default single (singular post) template for all post
	 * types, including pages and attachments.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  array   $templates
	 * @return array
	 */
	public function single( $templates ) {

		$templates = [];

		// Get the queried post.
		$post = get_queried_object();

		// Decode the post name.
		$name = urldecode( $post->post_name );

		// Check for a custom post template.
		$custom = get_post_template( $post->ID );

		if ( $custom ) {
			$templates[] = $custom;
		}

		// If viewing an attachment page, handle the files by mime type.
		if ( is_attachment() ) {

			// Split the mime type into two distinct parts.
			$type    = get_attachment_type();
			$subtype = get_attachment_subtype();

			if ( $subtype ) {
				$templates[] = "attachment-{$type}-{$subtype}.php";
				$templates[] = "attachment-{$subtype}.php";
			}

			$templates[] = "attachment-{$type}.php";

		// If not viewing an attachment page.
		} else {

			// Add a post ID template.
			$templates[] = "single-{$post->post_type}-{$post->ID}.php";
			$templates[] = "{$post->post_type}-{$post->ID}.php";

			// Add a post name (slug) template.
			$templates[] = "single-{$post->post_type}-{$name}.php";
			$templates[] = "{$post->post_type}-{$name}.php";
		}

		// Add a template based off the post type name.
		$templates[] = "single-{$post->post_type}.php";
		$templates[] = "{$post->post_type}.php";

		// Allow for WP standard 'single' template.
		$templates[] = 'single.php';

		// Return the template hierarchy.
		return $templates;
	}

	/**
	 * Overrides WP's default template for taxonomy-based archives. This
	 * allows better organization of taxonomy template files by making
	 * categories and post tags work the same way as other taxonomies.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  array   $templates
	 * @return array
	 */
	public function taxonomy( $template ) {

		$templates = [];

		// Get the queried term object.
		$term = get_queried_object();

		// Remove 'post-format' from the slug.
		$slug = 'post_format' === $term->taxonomy ? clean_post_format_slug( $term->slug ) : urldecode( $term->slug );

		// Check for a custom term template.
		$custom = get_term_template( get_queried_object_id() );

		if ( $custom ) {
			$templates[] = $custom;
		}

		// Slug-based template.
		$templates[] = "taxonomy-{$term->taxonomy}-{$slug}.php";

		// Taxonomy-specific template.
		$templates[] = "taxonomy-{$term->taxonomy}.php";

		// Default template.
		$templates[] = 'taxonomy.php';

		// Return the template hierarchy.
		return $templates;
	}

	/**
	 * Overrides WP's default template for author-based archives. Better
	 * abstraction of templates than `is_author()` allows by allowing themes
	 * to specify templates for a specific author.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  array   $templates
	 * @return array
	 */
	public function author( $templates ) {

		$templates = [];

		// Get the user nicename.
		$name = get_the_author_meta( 'user_nicename', get_query_var( 'author' ) );

		// Get the user object.
		$user = new WP_User( absint( get_query_var( 'author' ) ) );

		// Check for a custom user template.
		$custom = get_user_template( $user->ID );

		if ( $custom ) {
			$templates[] = $custom;
		}

		// Add the user nicename template.
		$templates[] = "user-{$name}.php";

		// Add role-based templates for the user.
		if ( is_array( $user->roles ) ) {

			foreach ( $user->roles as $role ) {
				$templates[] = "user-role-{$role}.php";
			}
		}

		// Add a basic user/author template.
		$templates[] = 'user.php';
		$templates[] = 'author.php';

		// Return the template hierarchy.
		return $templates;
	}

	/**
	 * Overrides WP's default template for date-based archives. Better
	 * abstraction of templates than `is_date()` allows by checking for the
	 * year, month, week, day, hour, and minute.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  array   $templates
	 * @return array
	 */
	public function date( $templates ) {

		$templates = [];

		// If viewing a time-based archive.
		if ( is_time() ) {

			// If viewing a minutely archive.
			if ( get_query_var( 'minute' ) ) {
				$templates[] = 'minute.php';

			// If viewing an hourly archive.
			} elseif ( get_query_var( 'hour' ) ) {
				$templates[] = 'hour.php';
			}

			// Catchall for any time-based archive.
			$templates[] = 'time.php';

		// If viewing a daily archive.
		} elseif ( is_day() ) {

			$templates[] = 'day.php';

		// If viewing a weekly archive.
		} elseif ( get_query_var( 'w' ) ) {

			$templates[] = 'week.php';

		// If viewing a monthly archive.
		} elseif ( is_month() ) {

			$templates[] = 'month.php';

		// If viewing a yearly archive.
		} elseif ( is_year() ) {

			$templates[] = 'year.php';
		}

		// Catchall template for date-based archives.
		$templates[] = 'date.php';

		// Return the template hierarchy.
		return $templates;
	}

	/**
	 * Filters a queried template hierarchy for each type of template and
	 * looks templates within `resources/views`.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return array
	 */
	public function template_hierarchy( $templates ) {

		// Merge the current template's hierarchy with the overall
		// hierarchy array.
		$this->hierarchy = array_merge(
			$this->hierarchy,
			array_map( function( $template ) {

				// Strip extension from file name.
				return substr(
					$template,
					0,
					strlen( $template ) - strlen( strrchr( $template, '.' ) )
				);

			}, $templates )
		);

		return filter_templates( $templates );
	}

	/**
	 * Filters the template for each type of template in the hierarchy. If
	 * `$template` exists, it means we've located a template. So, we're going
	 * to store that template for later use and return an empty string so
	 * that the template hierarchy continues processing. That way, we can
	 * capture the entire hierarchy.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $template
	 * @return string
	 */
	public function template( $template ) {

		if ( ! $this->located && $template ) {
			$this->located = $template;
		}

		return '';
	}

	/**
	 * Filter on `template_include` to make sure we fall back to our
	 * located template from earlier.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $template
	 * @return string
	 */
	public function template_include( $template ) {

		// If the template is not a string at this point, it either
		// doesn't exist or a plugin is telling us it's doing
		// something custom.
		if ( ! is_string( $template ) ) {

			return $template;
		}

		// If there's a template, return it. Otherwise, return our
		// located template from earlier.
		return $template ?: $this->located;
	}
}
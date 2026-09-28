<?php
/**
 * Plugin Name:       Blankless – Default Block Patterns for Post Types
 * Plugin URI:        https://github.com/bmx269/blankless
 * Description:       Never start with a blank slate again. Set a default block pattern for any post type's new-post editor. Patterns saved in the Site Editor take priority over patterns in code.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Trent Stromkins
 * Author URI:        https://github.com/bmx269
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       blankless
 * Domain Path:       /languages
 *
 * @package Blankless
 */

declare(strict_types=1);

namespace Blankless;

use WP_Block_Patterns_Registry;
use WP_Post;
use WP_Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION_KEY = 'blankless';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_settings_page' );
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
add_filter( 'default_content', __NAMESPACE__ . '\\filter_default_content', 10, 2 );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\\add_settings_link' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_assets' );
add_action( 'load-appearance_page_blankless', __NAMESPACE__ . '\\add_help_tabs' );

// ---------------------------------------------------------------------------
// Settings registration
// ---------------------------------------------------------------------------

/**
 * Add the settings page under Appearance.
 */
function register_settings_page(): void {
	add_theme_page(
		__( 'Blankless', 'blankless' ),
		__( 'Blankless', 'blankless' ),
		'manage_options',
		'blankless',
		__NAMESPACE__ . '\\render_settings_page'
	);
}

/**
 * Load the settings screen stylesheet, on that screen only.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function enqueue_admin_assets( string $hook_suffix ): void {
	if ( 'appearance_page_blankless' !== $hook_suffix ) {
		return;
	}

	$path = plugin_dir_path( __FILE__ ) . 'assets/admin.css';
	wp_enqueue_style(
		'blankless-admin',
		plugins_url( 'assets/admin.css', __FILE__ ),
		array(),
		(string) filemtime( $path )
	);
}

/**
 * Add Help tabs to the settings screen.
 */
function add_help_tabs(): void {
	$screen = get_current_screen();
	if ( null === $screen ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'blankless-getting-started',
			'title'   => __( 'Getting started', 'blankless' ),
			'content' => '<ol>'
				. '<li>' . esc_html__( 'Build a pattern. Create one in the Site Editor (Appearance > Editor > Patterns), or use one that your theme or a plugin registers in code.', 'blankless' ) . '</li>'
				. '<li>' . esc_html__( 'Enter its slug next to a post type on this screen. Start typing to see suggestions, then save.', 'blankless' ) . '</li>'
				. '<li>' . esc_html__( 'Create a new post of that type. The editor opens with the pattern\'s blocks already in place.', 'blankless' ) . '</li>'
				. '</ol>'
				. '<p>' . esc_html__( 'Leave a post type blank to keep the normal empty editor. Only new posts that start out empty are filled, so existing content is never changed.', 'blankless' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'blankless-matching',
			'title'   => __( 'How matching works', 'blankless' ),
			'content' => '<p>' . esc_html__( 'When a new post is created, the slug is looked up in this order and the first match is used:', 'blankless' ) . '</p>'
				. '<ol>'
				. '<li>' . esc_html__( 'The pattern saved in the Site Editor that had that slug when these settings were last saved. Edits to it apply straight away.', 'blankless' ) . '</li>'
				. '<li>' . esc_html__( 'A pattern registered in code with that full name, such as mytheme/staff-profile.', 'blankless' ) . '</li>'
				. '<li>' . esc_html__( 'A pattern registered in code whose name ends with the slug, such as staff-profile.', 'blankless' ) . '</li>'
				. '</ol>'
				. '<p>' . esc_html__( 'The Status column shows which one matched. "Saved in Site Editor" patterns have an Edit link. "In Code" patterns come from your theme or a plugin and are changed in their files. If you publish a Saved Pattern after saving this screen, save it again to use that pattern.', 'blankless' ) . '</p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>' . esc_html__( 'More help', 'blankless' ) . '</strong></p>'
		. '<p><a href="https://wordpress.org/support/plugin/blankless/">' . esc_html__( 'Support forum', 'blankless' ) . '</a></p>'
		. '<p><a href="https://github.com/bmx269/blankless">' . esc_html__( 'GitHub', 'blankless' ) . '</a></p>'
	);
}

/**
 * Register the option with the Settings API.
 */
function register_settings(): void {
	register_setting(
		'blankless_group',
		OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
			'default'           => array(),
			'show_in_rest'      => false,
		)
	);
}

/**
 * Sanitize the settings array and pin each slug to the Saved Pattern it names right now.
 *
 * Recording the pattern ID at save time means a Saved Pattern published later
 * with a matching slug (by any user who can publish patterns) is never used
 * unless an administrator saves this screen again.
 *
 * @param mixed $input Raw option value from the Settings API.
 * @return array<string, array{slug: string, pattern_id: int}>
 */
function sanitize_settings( $input ): array {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$allowed   = array_keys( get_manageable_post_types() );
	$sanitized = array();

	foreach ( $input as $post_type => $value ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type || ! in_array( $post_type, $allowed, true ) ) {
			continue;
		}

		// The form sends a slug. add_option() runs this callback a second time, passing an entry saved by the first pass.
		$slug = is_array( $value ) ? ( $value['slug'] ?? '' ) : $value;
		if ( ! is_string( $slug ) ) {
			continue;
		}

		$slug = sanitize_text_field( $slug );

		// An empty slug clears the setting for that post type.
		if ( '' !== $slug ) {
			$sanitized[ $post_type ] = array(
				'slug'       => $slug,
				'pattern_id' => find_saved_pattern_id( $slug ),
			);
		}
	}

	return $sanitized;
}

// ---------------------------------------------------------------------------
// Settings page render
// ---------------------------------------------------------------------------

/**
 * Render the settings page.
 */
function render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_types = get_manageable_post_types();
	$settings   = get_saved_settings();
	?>
	<div class="wrap">
		<div class="blankless-header">
			<img src="<?php echo esc_url( plugins_url( 'assets/icon.svg', __FILE__ ) ); ?>" alt="">
			<div>
				<h1><?php echo esc_html__( 'Blankless', 'blankless' ); ?></h1>
				<p class="blankless-tagline"><?php esc_html_e( 'Never start with a blank slate again.', 'blankless' ); ?></p>
				<p><?php esc_html_e( 'Start every new post from the block pattern you picked for its post type.', 'blankless' ); ?></p>
			</div>
		</div>
		<hr class="wp-header-end">
		<?php
		// Pages outside the Settings menu don't print save notices automatically.
		settings_errors();
		?>
		<div class="blankless-intro">
			<p><?php esc_html_e( 'Choose a pattern for each post type. New posts of that type open with its blocks already in place. Leave a field empty to keep the normal empty editor.', 'blankless' ); ?></p>
			<p>
				<?php
				printf(
					/* translators: 1: example Saved Pattern slug, 2: example registered pattern name. */
					esc_html__( 'Use the slug of a pattern saved in the Site Editor (%1$s) or the name of a pattern registered in code by your theme or a plugin (%2$s). Start typing to see suggestions. If both match, the pattern saved in the Site Editor takes priority over the one in code.', 'blankless' ),
					'<code>staff-profile</code>',
					'<code>mytheme/staff-profile</code>'
				);
				?>
			</p>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'blankless_group' ); ?>
			<?php render_pattern_datalist(); ?>
			<table class="widefat striped blankless-table">
				<thead>
					<tr>
						<th class="column-type"><?php esc_html_e( 'Post type', 'blankless' ); ?></th>
						<th><?php esc_html_e( 'Pattern', 'blankless' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'blankless' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $post_types as $type_slug => $obj ) :
					$saved_slug = $settings[ $type_slug ]['slug'] ?? '';
					$pattern_id = $settings[ $type_slug ]['pattern_id'] ?? 0;
					$linked     = $pattern_id > 0 ? get_post( $pattern_id ) : null;
					// Show a renamed linked pattern's current slug, so saving again keeps the link.
					if ( $linked instanceof WP_Post && 'wp_block' === $linked->post_type && 'publish' === $linked->post_status ) {
						$saved_slug = $linked->post_name;
					}
					$field_id   = 'blankless-' . $type_slug;
					$type_label = is_string( $obj->labels->singular_name ?? null ) ? $obj->labels->singular_name : $obj->label;
					?>
					<tr>
						<td>
							<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $type_label ); ?></label>
							<code><?php echo esc_html( $type_slug ); ?></code>
						</td>
						<td>
							<input
								type="text"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( OPTION_KEY . '[' . $type_slug . ']' ); ?>"
								value="<?php echo esc_attr( $saved_slug ); ?>"
								placeholder="<?php esc_attr_e( 'No default', 'blankless' ); ?>"
								list="blankless-patterns"
							>
						</td>
						<td><?php echo wp_kses_post( render_pattern_status( $saved_slug, $pattern_id ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * All public post types the plugin can target.
 *
 * @return array<string, WP_Post_Type>
 */
function get_manageable_post_types(): array {
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $post_types['attachment'], $post_types['wp_block'] );
	return $post_types;
}

/**
 * The saved settings per post type, ignoring malformed entries.
 *
 * @return array<string, array{slug: string, pattern_id: int}>
 */
function get_saved_settings(): array {
	$saved = get_option( OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		return array();
	}

	$settings = array();
	foreach ( $saved as $post_type => $entry ) {
		if ( ! is_string( $post_type ) || ! is_array( $entry ) || ! isset( $entry['slug'] ) || ! is_string( $entry['slug'] ) || '' === $entry['slug'] ) {
			continue;
		}

		$settings[ $post_type ] = array(
			'slug'       => $entry['slug'],
			'pattern_id' => isset( $entry['pattern_id'] ) ? absint( $entry['pattern_id'] ) : 0,
		);
	}

	return $settings;
}

/**
 * The ID of the published Saved Pattern with this slug, or 0 when there is none.
 *
 * @param string $slug Pattern slug.
 * @return int
 */
function find_saved_pattern_id( string $slug ): int {
	$db = get_page_by_path( $slug, OBJECT, 'wp_block' );
	return $db instanceof WP_Post && 'publish' === $db->post_status ? $db->ID : 0;
}

/**
 * Read a string field from a registered pattern array.
 *
 * @param array<mixed> $pattern Registered pattern properties.
 * @param string       $key     Field name, e.g. 'name', 'title' or 'content'.
 * @return string The field value, or an empty string when missing or not a string.
 */
function pattern_field( array $pattern, string $key ): string {
	return isset( $pattern[ $key ] ) && is_string( $pattern[ $key ] ) ? $pattern[ $key ] : '';
}

/**
 * Locate the pattern a configured slug refers to.
 *
 * Resolution order:
 *   1. The published Saved Pattern (wp_block) recorded when the settings were saved.
 *   2. File-registered pattern, matched by full registered name.
 *   3. File-registered pattern, matched by the slug portion of the name.
 *
 * Saved Patterns are never looked up by slug here, so one published after the
 * settings were saved can't replace the pattern an administrator chose.
 *
 * @param string $slug       Configured pattern slug or registered name.
 * @param int    $pattern_id Saved Pattern ID recorded at save time, or 0.
 * @return array{source: string, content: string, id: int}|null Source is 'database' or 'file'.
 */
function locate_pattern( string $slug, int $pattern_id ): ?array {
	if ( '' === $slug ) {
		return null;
	}

	$db = $pattern_id > 0 ? get_post( $pattern_id ) : null;
	if ( $db instanceof WP_Post && 'wp_block' === $db->post_type && 'publish' === $db->post_status && '' !== $db->post_content ) {
		return array(
			'source'  => 'database',
			'content' => $db->post_content,
			'id'      => $db->ID,
		);
	}

	$registry = WP_Block_Patterns_Registry::get_instance();

	$file = $registry->get_registered( $slug );
	if ( is_array( $file ) && '' !== pattern_field( $file, 'content' ) ) {
		return array(
			'source'  => 'file',
			'content' => pattern_field( $file, 'content' ),
			'id'      => 0,
		);
	}

	foreach ( $registry->get_all_registered() as $pattern ) {
		$parts = explode( '/', pattern_field( $pattern, 'name' ) );
		if ( end( $parts ) === $slug && '' !== pattern_field( $pattern, 'content' ) ) {
			return array(
				'source'  => 'file',
				'content' => pattern_field( $pattern, 'content' ),
				'id'      => 0,
			);
		}
	}

	return null;
}

/**
 * Build a status badge string for display in the settings table.
 *
 * Returns safe HTML; caller should still pass through wp_kses_post() when echoing.
 *
 * @param string $slug       Configured pattern slug or registered name.
 * @param int    $pattern_id Saved Pattern ID recorded at save time, or 0.
 * @return string
 */
function render_pattern_status( string $slug, int $pattern_id ): string {
	if ( '' === $slug ) {
		return '<span class="blankless-status is-unset">' . esc_html__( 'Not set', 'blankless' ) . '</span>';
	}

	$pattern = locate_pattern( $slug, $pattern_id );

	if ( null === $pattern ) {
		return '<span class="blankless-status is-missing">' . esc_html__( 'Not found', 'blankless' ) . '</span>';
	}

	if ( 'database' !== $pattern['source'] ) {
		return '<span class="blankless-status is-found">' . esc_html__( 'In Code', 'blankless' ) . '</span>';
	}

	$label = wp_is_block_theme()
		? __( 'Saved in Site Editor', 'blankless' )
		: __( 'Saved in Patterns', 'blankless' );

	$html = '<span class="blankless-status is-found">' . esc_html( $label ) . '</span>';

	$edit_url = pattern_edit_url( $pattern['id'] );
	if ( '' !== $edit_url ) {
		$html .= sprintf(
			' <a class="blankless-edit" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'blankless' ),
			esc_html( get_the_title( $pattern['id'] ) )
		);
	}

	return $html;
}

/**
 * Where to edit a Saved Pattern: the Site Editor on block themes, the post editor otherwise.
 *
 * @param int $pattern_id Saved Pattern (wp_block) post ID.
 * @return string Edit URL, or an empty string when the current user can't edit it.
 */
function pattern_edit_url( int $pattern_id ): string {
	if ( ! current_user_can( 'edit_post', $pattern_id ) ) {
		return '';
	}

	if ( wp_is_block_theme() ) {
		return add_query_arg(
			array(
				'postType' => 'wp_block',
				'postId'   => $pattern_id,
				'canvas'   => 'edit',
			),
			admin_url( 'site-editor.php' )
		);
	}

	return (string) get_edit_post_link( $pattern_id, 'raw' );
}

/**
 * Output a datalist of available pattern slugs to suggest in the slug inputs.
 */
function render_pattern_datalist(): void {
	$options = array();

	$db_patterns = get_posts(
		array(
			'post_type'      => 'wp_block',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	foreach ( $db_patterns as $db_pattern ) {
		$options[ $db_pattern->post_name ] = $db_pattern->post_title;
	}

	foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
		$name = pattern_field( $pattern, 'name' );
		if ( '' !== $name && ! isset( $options[ $name ] ) ) {
			$title            = pattern_field( $pattern, 'title' );
			$options[ $name ] = '' !== $title ? $title : $name;
		}
	}

	echo '<datalist id="blankless-patterns">';
	foreach ( $options as $value => $label ) {
		printf( '<option value="%1$s" label="%2$s"></option>', esc_attr( (string) $value ), esc_attr( $label ) );
	}
	echo '</datalist>';
}

/**
 * Add a Settings link to the plugin's row on the Plugins screen.
 *
 * @param array<string|int, string> $links Existing action links.
 * @return array<string|int, string>
 */
function add_settings_link( array $links ): array {
	$settings = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( admin_url( 'themes.php?page=blankless' ) ),
		esc_html__( 'Settings', 'blankless' )
	);
	array_unshift( $links, $settings );
	return $links;
}

// ---------------------------------------------------------------------------
// Default content filter
// ---------------------------------------------------------------------------

/**
 * Pre-populate a new post with the pattern configured for its post type.
 *
 * Content already supplied (e.g. via the `content` query arg or an earlier
 * filter) is left untouched.
 *
 * @param mixed   $content Default post content.
 * @param WP_Post $post    The auto-draft being created.
 * @return mixed
 */
function filter_default_content( $content, $post ) {
	if ( ! $post instanceof WP_Post || ( is_string( $content ) && '' !== trim( $content ) ) ) {
		return $content;
	}

	$setting = get_saved_settings()[ $post->post_type ] ?? null;
	if ( null === $setting ) {
		return $content;
	}

	$pattern = locate_pattern( $setting['slug'], $setting['pattern_id'] );

	return null === $pattern ? $content : $pattern['content'];
}

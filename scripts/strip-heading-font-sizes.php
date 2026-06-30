<?php
/**
 * Remove fixed font sizes from h1/h2/h3 headings in pattern markup so the
 * active block theme controls heading scale via theme.json.
 *
 * Strips:
 *  - `"fontSize":"…"` on `wp:heading` block comments
 *  - `style.typography.fontSize` on `wp:heading` block comments
 *  - `has-*-font-size` classes on `<h1|h2|h3>` tags
 *  - inline `font-size:` in `<h1|h2|h3 style="…">`
 *
 * Usage (from plugin root):
 *   php scripts/strip-heading-font-sizes.php           # patterns only
 *   php scripts/strip-heading-font-sizes.php --pages   # patterns + published WP pages
 *
 * Exit codes: 0 = success, 1 = I/O error.
 */

declare( strict_types = 1 );

/**
 * @param array<string, mixed> $attrs
 * @return array<string, mixed>
 */
function gbp_strip_heading_attrs( array $attrs ): array {
	unset( $attrs['fontSize'] );

	if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
		if ( isset( $attrs['style']['typography'] ) && is_array( $attrs['style']['typography'] ) ) {
			unset( $attrs['style']['typography']['fontSize'] );
			if ( $attrs['style']['typography'] === [] ) {
				unset( $attrs['style']['typography'] );
			}
		}
		if ( $attrs['style'] === [] ) {
			unset( $attrs['style'] );
		}
	}

	return $attrs;
}

function gbp_replace_wp_heading_comments( string $content ): string {
	$marker  = '<!-- wp:heading ';
	$closing = ' -->';
	$out     = '';
	$pos     = 0;

	while ( ( $start = strpos( $content, $marker, $pos ) ) !== false ) {
		$out        .= substr( $content, $pos, $start - $pos );
		$json_start  = $start + strlen( $marker );
		$end         = strpos( $content, $closing, $json_start );
		if ( $end === false ) {
			$out .= substr( $content, $start );
			break;
		}

		$json_str = substr( $content, $json_start, $end - $json_start );
		$attrs    = json_decode( $json_str, true );
		if ( is_array( $attrs ) ) {
			$attrs = gbp_strip_heading_attrs( $attrs );
			$out  .= $marker . json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . $closing;
		} else {
			$out .= substr( $content, $start, $end + strlen( $closing ) - $start );
		}
		$pos = $end + strlen( $closing );
	}

	$out .= substr( $content, $pos );
	return $out;
}

function gbp_strip_heading_html_tags( string $content ): string {
	return (string) preg_replace_callback(
		'/<(h[123])\b([^>]*)>/i',
		static function ( array $m ): string {
			$tag   = $m[1];
			$attrs = $m[2];

			$attrs = (string) preg_replace( '/\s*has-[a-z0-9-]+-font-size/i', '', $attrs );

			if ( preg_match( '/\sstyle="([^"]*)"/i', $attrs, $sm ) ) {
				$parts = array_values(
					array_filter(
						array_map( 'trim', explode( ';', $sm[1] ) ),
						static function ( string $part ): bool {
							return $part !== '' && stripos( $part, 'font-size:' ) !== 0;
						}
					)
				);
				if ( $parts === [] ) {
					$attrs = (string) preg_replace( '/\sstyle="[^"]*"/i', '', $attrs );
				} else {
					$new_style = implode( ';', $parts );
					$attrs     = (string) preg_replace(
						'/\sstyle="[^"]*"/i',
						' style="' . $new_style . '"',
						$attrs
					);
				}
			}

			return '<' . $tag . $attrs . '>';
		},
		$content
	);
}

function gbp_strip_heading_font_sizes_in_markup( string $content ): string {
	$content = gbp_replace_wp_heading_comments( $content );
	$content = gbp_strip_heading_html_tags( $content );
	return $content;
}

if ( PHP_SAPI !== 'cli' || realpath( (string) ( $argv[0] ?? '' ) ) !== realpath( __FILE__ ) ) {
	return;
}

$plugin_root  = dirname( __DIR__ );
$patterns_dir = $plugin_root . '/patterns';
$update_pages = in_array( '--pages', $argv ?? [], true );

if ( ! is_dir( $patterns_dir ) ) {
	fwrite( STDERR, "[strip-heading-font-sizes] patterns/ not found at {$patterns_dir}\n" );
	exit( 1 );
}

$files = glob( $patterns_dir . '/*/content*.html' );
if ( $files === false ) {
	fwrite( STDERR, "[strip-heading-font-sizes] could not list patterns/\n" );
	exit( 1 );
}

$changed_files = 0;
foreach ( $files as $file ) {
	$src = file_get_contents( $file );
	if ( $src === false ) {
		fwrite( STDERR, "[strip-heading-font-sizes] could not read {$file}\n" );
		exit( 1 );
	}

	$new = gbp_strip_heading_font_sizes_in_markup( $src );
	if ( $new === $src ) {
		continue;
	}

	if ( file_put_contents( $file, $new ) === false ) {
		fwrite( STDERR, "[strip-heading-font-sizes] could not write {$file}\n" );
		exit( 1 );
	}

	$changed_files++;
	$slug = basename( dirname( $file ) );
	fwrite( STDOUT, "  pattern {$slug}\n" );
}

fwrite( STDOUT, sprintf( "\nPatterns: %d files updated.\n", $changed_files ) );

if ( ! $update_pages ) {
	exit( 0 );
}

$wp_load = dirname( $plugin_root, 3 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "[strip-heading-font-sizes] wp-load.php not found at {$wp_load}\n" );
	exit( 1 );
}

require_once $wp_load;

$pages = get_posts(
	[
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	]
);

$changed_pages = 0;
foreach ( $pages as $page ) {
	$new = gbp_strip_heading_font_sizes_in_markup( (string) $page->post_content );
	if ( $new === $page->post_content ) {
		continue;
	}

	$result = wp_update_post(
		[
			'ID'           => $page->ID,
			'post_content' => $new,
		],
		true
	);
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, "[strip-heading-font-sizes] failed to update page {$page->ID}: {$result->get_error_message()}\n" );
		exit( 1 );
	}

	$changed_pages++;
	fwrite( STDOUT, "  page {$page->post_name} (#{$page->ID})\n" );
}

fwrite( STDOUT, sprintf( "Pages: %d updated.\n", $changed_pages ) );
exit( 0 );

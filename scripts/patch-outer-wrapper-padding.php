<?php
declare( strict_types=1 );
/**
 * One-off migration: enforce horizontal padding (`var:preset|spacing|30`)
 * on the outermost `<!-- wp:group … alignfull … /-->` wrapper of every
 * non-hero / non-header pattern that ships with the plugin.
 *
 * Most outer wrappers currently declare only top/bottom padding (or
 * `"0","0"` left/right like the user reported in `hero-v3`). When the
 * pattern is rendered through `core/post-content` on a constrained
 * page template, the alignfull section bleeds into the viewport edges
 * and content reads "edge-to-edge" with no breathing room.
 *
 * Run from the plugin root:
 *   php scripts/patch-outer-wrapper-padding.php
 *
 * Idempotent: patterns that already declare a non-zero left/right
 * padding (e.g. `faq-v4`, `process-v1`, `process-v2`, `testimonial-v5`)
 * are left untouched.
 */

const TARGET_SPACING_JSON = 'var:preset|spacing|30';
const TARGET_SPACING_CSS  = 'var(--wp--preset--spacing--30)';

const SKIP_PREFIXES = [ 'hero-', 'header-' ];
const SKIP_EXACT    = [ 'impressum', 'media-text-grid-v1' ];

$root         = dirname( __DIR__ );
$patterns_dir = $root . '/patterns';
$dirs         = glob( $patterns_dir . '/*', GLOB_ONLYDIR ) ?: array();
sort( $dirs );

$counts = array( 'patched' => 0, 'skipped' => 0, 'unchanged' => 0 );

foreach ( $dirs as $dir ) {
	$slug = basename( $dir );
	foreach ( SKIP_PREFIXES as $prefix ) {
		if ( str_starts_with( $slug, $prefix ) ) {
			continue 2;
		}
	}
	if ( in_array( $slug, SKIP_EXACT, true ) ) {
		++$counts['skipped'];
		printf( "  skip   %s (excluded)\n", $slug );
		continue;
	}

	$file = $dir . '/content.html';
	if ( ! is_file( $file ) ) {
		++$counts['skipped'];
		printf( "  skip   %s (no content.html)\n", $slug );
		continue;
	}

	$html    = file_get_contents( $file );
	if ( $html === false || $html === '' ) {
		++$counts['skipped'];
		printf( "  skip   %s (empty)\n", $slug );
		continue;
	}

	$result = patch_outer_wrapper_padding( $html );
	if ( $result === null ) {
		++$counts['unchanged'];
		printf( "  noop   %s (no outer alignfull or already padded)\n", $slug );
		continue;
	}

	file_put_contents( $file, $result );
	++$counts['patched'];
	printf( "  patch  %s\n", $slug );
}

echo "\n";
printf( "patched: %d   unchanged: %d   skipped: %d\n", $counts['patched'], $counts['unchanged'], $counts['skipped'] );

/**
 * Patches the very first `<!-- wp:group {…} -->` block comment in
 * `$html` and the immediately following `<section|div … style="…">`
 * tag with horizontal padding equal to `spacing|30`. Returns the new
 * HTML or `null` if no patch was applied.
 */
function patch_outer_wrapper_padding( string $html ): ?string {
	// Recursive PCRE matches the balanced JSON attribute object so
	// nested `style.spacing.{…}` doesn't trip us up.
	$regex = '/(<!--\s*wp:group\s+)(\{(?:[^{}]++|(?-1))*+\})(\s*-->)/';
	if ( ! preg_match( $regex, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	[ $whole_match, $whole_offset ] = $matches[0];
	$json_str = $matches[2][0];

	$attrs = json_decode( $json_str, true );
	if ( ! is_array( $attrs ) ) {
		return null;
	}
	if ( ( $attrs['align'] ?? null ) !== 'full' ) {
		return null;
	}

	$padding = $attrs['style']['spacing']['padding'] ?? array();
	if ( ! is_array( $padding ) ) {
		$padding = array();
	}

	$current_left  = $padding['left']  ?? '';
	$current_right = $padding['right'] ?? '';
	$needs_left    = ( $current_left  === '' || $current_left  === '0' );
	$needs_right   = ( $current_right === '' || $current_right === '0' );
	if ( ! $needs_left && ! $needs_right ) {
		return null;
	}

	if ( $needs_left ) {
		$padding['left'] = TARGET_SPACING_JSON;
	}
	if ( $needs_right ) {
		$padding['right'] = TARGET_SPACING_JSON;
	}
	$attrs['style']['spacing']['padding'] = $padding;

	$new_json = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( $new_json === false ) {
		return null;
	}

	$new_comment = $matches[1][0] . $new_json . $matches[3][0];
	$html        = substr_replace( $html, $new_comment, $whole_offset, strlen( $whole_match ) );

	// Patch the immediately following opening tag's inline style.
	$after_comment = $whole_offset + strlen( $new_comment );
	if ( preg_match(
		'/<(section|div)\s[^>]*style="([^"]*)"/i',
		$html,
		$tag_match,
		PREG_OFFSET_CAPTURE,
		$after_comment
	) ) {
		$style_str  = $tag_match[2][0];
		$style_off  = $tag_match[2][1];
		$new_style  = patch_inline_padding_style( $style_str, $needs_left, $needs_right );
		if ( $new_style !== $style_str ) {
			$html = substr_replace( $html, $new_style, $style_off, strlen( $style_str ) );
		}
	}

	return $html;
}

/**
 * Rebuilds an inline `style="…"` value so that `padding-left` /
 * `padding-right` are set to `spacing|30`. Preserves all non-padding
 * declarations in their original order and re-emits the four padding
 * sides in canonical top-right-bottom-left order, matching the way
 * Gutenberg re-serializes block markup.
 */
function patch_inline_padding_style( string $style, bool $needs_left, bool $needs_right ): string {
	$pad        = array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' );
	$other      = array();
	$had_padding = false;

	foreach ( array_filter( array_map( 'trim', explode( ';', $style ) ) ) as $piece ) {
		$kv = array_map( 'trim', explode( ':', $piece, 2 ) );
		if ( count( $kv ) !== 2 ) {
			continue;
		}
		[ $key, $value ] = $kv;
		switch ( $key ) {
			case 'padding-top':
				$pad['top']    = $value;
				$had_padding   = true;
				break;
			case 'padding-right':
				$pad['right']  = $value;
				$had_padding   = true;
				break;
			case 'padding-bottom':
				$pad['bottom'] = $value;
				$had_padding   = true;
				break;
			case 'padding-left':
				$pad['left']   = $value;
				$had_padding   = true;
				break;
			default:
				$other[] = $key . ':' . $value;
		}
	}

	if ( ! $had_padding ) {
		return $style;
	}

	if ( $needs_right ) {
		$pad['right'] = TARGET_SPACING_CSS;
	}
	if ( $needs_left ) {
		$pad['left'] = TARGET_SPACING_CSS;
	}

	$rebuilt = $other;
	if ( $pad['top'] !== '' ) {
		$rebuilt[] = 'padding-top:' . $pad['top'];
	}
	if ( $pad['right'] !== '' ) {
		$rebuilt[] = 'padding-right:' . $pad['right'];
	}
	if ( $pad['bottom'] !== '' ) {
		$rebuilt[] = 'padding-bottom:' . $pad['bottom'];
	}
	if ( $pad['left'] !== '' ) {
		$rebuilt[] = 'padding-left:' . $pad['left'];
	}

	return implode( ';', $rebuilt );
}

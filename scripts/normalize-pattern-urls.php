<?php
/**
 * One-off / repeatable migration: normalize any instance-specific plugin asset
 * URL in pattern `content.html` files back to the `__PLUGIN_URL__` placeholder.
 *
 * Mirrors {@see GutenBlock_Pro_Pattern_Loader::to_plugin_url_placeholder()}
 * verbatim so the migration uses the exact same rewrite rules as the live
 * save handler.
 *
 * Usage (from plugin root):
 *   php scripts/normalize-pattern-urls.php
 *
 * Exit codes: 0 = no changes / success, 1 = I/O error.
 */

declare( strict_types = 1 );

$plugin_root = dirname( __DIR__ );
$patterns_dir = $plugin_root . '/patterns';

if ( ! is_dir( $patterns_dir ) ) {
	fwrite( STDERR, "[normalize-pattern-urls] patterns/ not found at {$patterns_dir}\n" );
	exit( 1 );
}

$abs_regex = '#https?://[^\s"\'<>]+?/wp-content/plugins/gutenblock-pro/(assets/images/[^\s"\'<>]+)#i';
$rel_regex = '#(?<![A-Za-z0-9./_-])/wp-content/plugins/gutenblock-pro/(assets/images/[^\s"\'<>]+)#';

$files = glob( $patterns_dir . '/*/content*.html' );
if ( $files === false ) {
	fwrite( STDERR, "[normalize-pattern-urls] could not list patterns/\n" );
	exit( 1 );
}

$changed = 0;
$replacements = 0;
foreach ( $files as $file ) {
	$src = file_get_contents( $file );
	if ( $src === false ) {
		fwrite( STDERR, "[normalize-pattern-urls] could not read {$file}\n" );
		exit( 1 );
	}
	$new = preg_replace( $abs_regex, '__PLUGIN_URL__/$1', $src );
	$new = preg_replace( $rel_regex, '__PLUGIN_URL__/$1', $new );
	if ( $new !== $src ) {
		$count = 0;
		preg_match_all( $abs_regex, $src, $m1 );
		preg_match_all( $rel_regex, $src, $m2 );
		$count = count( $m1[0] ) + count( $m2[0] );
		if ( file_put_contents( $file, $new ) === false ) {
			fwrite( STDERR, "[normalize-pattern-urls] could not write {$file}\n" );
			exit( 1 );
		}
		$changed++;
		$replacements += $count;
		fwrite( STDOUT, sprintf( "  %s  (%d replacements)\n", $file, $count ) );
	}
}

fwrite( STDOUT, sprintf( "\nDone: %d files updated, %d URL replacements.\n", $changed, $replacements ) );
exit( 0 );

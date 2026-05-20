/**
 * Gutenberg sidebar panel for the headless-CMS SEO meta fields
 * (`_meta_title`, `_meta_description`) on the `gbp_content` CPT.
 *
 * These two values flow through the standard WP REST API (via
 * `register_post_meta(..., show_in_rest: true)`) and are consumed by the
 * GutenBlock SaaS in `generateMetadata` to render `<title>` and the
 * `<meta name="description">` for the public page. Both fields fall back
 * to the post title / excerpt when left blank.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.coreData ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var useEntityProp = wp.coreData.useEntityProp;
	var useSelect = wp.data.useSelect;
	var __ = ( wp.i18n && wp.i18n.__ ) || function ( s ) { return s; };

	var TITLE_LIMIT_RECOMMENDED = 60;
	var DESCRIPTION_LIMIT_RECOMMENDED = 160;

	function SeoPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( 'gbp_content' !== postType ) {
			return null;
		}

		var metaProp = useEntityProp( 'postType', postType, 'meta' );
		var meta = metaProp[ 0 ] || {};
		var setMeta = metaProp[ 1 ];

		var title = meta._meta_title || '';
		var description = meta._meta_description || '';

		function updateField( key, value ) {
			var next = {};
			next[ key ] = value;
			setMeta( Object.assign( {}, meta, next ) );
		}

		var titleHelp = title
			? title.length + ' / ' + TITLE_LIMIT_RECOMMENDED + ' ' + __( 'Zeichen', 'gutentheme' )
			: __( 'Leer = Post-Titel wird verwendet.', 'gutentheme' );

		var descriptionHelp = description
			? description.length + ' / ' + DESCRIPTION_LIMIT_RECOMMENDED + ' ' + __( 'Zeichen', 'gutentheme' )
			: __( 'Leer = Excerpt wird verwendet.', 'gutentheme' );

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'gutentheme-cms-seo-panel',
				title: __( 'SEO (Headless SaaS)', 'gutentheme' ),
				className: 'gutentheme-cms-seo-panel',
			},
			createElement( TextControl, {
				label: __( 'Meta-Titel', 'gutentheme' ),
				value: title,
				onChange: function ( value ) { updateField( '_meta_title', value ); },
				help: titleHelp,
				__nextHasNoMarginBottom: true,
			} ),
			createElement( TextareaControl, {
				label: __( 'Meta-Beschreibung', 'gutentheme' ),
				value: description,
				onChange: function ( value ) { updateField( '_meta_description', value ); },
				rows: 3,
				help: descriptionHelp,
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	registerPlugin( 'gutentheme-cms-seo-panel', { render: SeoPanel } );
} )( window.wp );

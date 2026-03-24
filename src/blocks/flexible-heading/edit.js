/**
 * Flexible Heading – Editor
 *
 * @package GutenBlockPro
 */

import {
	useBlockProps,
	useInnerBlocksProps,
	BlockControls,
	InnerBlocks,
	__experimentalUseBlockWrap,
} from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import classnames from 'classnames';

const ALLOWED_BLOCKS = [ 'gutenblock-pro/heading-part' ];

const TEMPLATE = [
	[ 'gutenblock-pro/heading-part', { content: __( 'Kreative ', 'gutenblock-pro' ) } ],
	[ 'gutenblock-pro/heading-part', { content: __( 'Überschrift', 'gutenblock-pro' ) } ],
];

export default function Edit( { attributes, setAttributes } ) {
	const { level, textAlign } = attributes;
	const TagName = `h${ level }`;

	const blockProps = useBlockProps( {
		className: classnames( 'gbp-flexible-heading', {
			[ `has-text-align-${ textAlign }` ]: textAlign,
		} ),
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'horizontal',
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<>
			<BlockControls group="block">
				<ToolbarGroup>
					{ [ 1, 2 ].map( ( lvl ) => (
						<ToolbarButton
							key={ lvl }
							isPressed={ level === lvl }
							onClick={ () => setAttributes( { level: lvl } ) }
							label={ `H${ lvl }` }
						>
							{ `H${ lvl }` }
						</ToolbarButton>
					) ) }
				</ToolbarGroup>
			</BlockControls>
			<TagName { ...innerBlocksProps } />
		</>
	);
}

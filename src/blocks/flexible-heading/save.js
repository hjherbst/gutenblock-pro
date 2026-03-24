/**
 * Flexible Heading – Save
 *
 * @package GutenBlockPro
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import classnames from 'classnames';

export default function Save( { attributes } ) {
	const { level, textAlign } = attributes;
	const TagName = `h${ level }`;

	const blockProps = useBlockProps.save( {
		className: classnames( 'gbp-flexible-heading', {
			[ `has-text-align-${ textAlign }` ]: textAlign,
		} ),
	} );

	return (
		<TagName { ...blockProps }>
			<InnerBlocks.Content />
		</TagName>
	);
}

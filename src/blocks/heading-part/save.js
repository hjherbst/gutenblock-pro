/**
 * Heading Part – Save
 *
 * @package GutenBlockPro
 */

import { RichText, useBlockProps } from '@wordpress/block-editor';

export default function Save( { attributes } ) {
	const { content } = attributes;
	const blockProps = useBlockProps.save();

	return (
		<RichText.Content
			tagName="span"
			{ ...blockProps }
			value={ content }
		/>
	);
}

/**
 * Heading Part – Editor
 *
 * @package GutenBlockPro
 */

import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
	const { content } = attributes;
	const blockProps = useBlockProps();

	return (
		<RichText
			{ ...blockProps }
			tagName="span"
			value={ content }
			onChange={ ( val ) => setAttributes( { content: val } ) }
			placeholder={ __( 'Textteil…', 'gutenblock-pro' ) }
			allowedFormats={ [
				'core/bold',
				'core/italic',
				'core/strikethrough',
				'core/superscript',
				'core/subscript',
				'gutenblock-pro/circle',
				'gutenblock-pro/marker',
			] }
		/>
	);
}

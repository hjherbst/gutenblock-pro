/**
 * Sticky Feature Block – Frontend markup (CodyHouse structure).
 *
 * @package GutenBlockPro
 */

import { useBlockProps } from '@wordpress/block-editor';

export default function Save( { attributes } ) {
	const { items = [], imagePosition = 'right', aspectRatio = 'auto' } = attributes;
	const blockProps = useBlockProps.save( {
		className: 'wp-block-gutenblock-pro-sticky-feature gb-sticky-feature js-sticky-feature',
	} );
	const aspectRatioClass = aspectRatio !== 'auto' ? `gbp-sticky-feature__media-figure--${ aspectRatio }` : '';

	const contentCol = (
		<div className="gbp-sticky-feature__content-col" key="content">
			<ul className="gbp-sticky-feature__content-list gbp-sticky-feature__grid-2 js-sticky-feature__content-list">
				{ items.map( ( item, index ) => (
					<li key={ index } className="gbp-sticky-feature__content-item js-sticky-feature__content-item">
						{ item.imageUrl && (
							<figure className={ `gbp-sticky-feature__content-figure ${ aspectRatioClass }` }>
								<img src={ item.imageUrl } alt={ item.imageAlt || '' } />
							</figure>
						) }
						<h3 className="gbp-sticky-feature__title js-sticky-feature__title">
							{ item.heading }
						</h3>
						{ item.subline && (
							<p className="gbp-sticky-feature__subline">{ item.subline }</p>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);

	const mediaCol = (
		<div className="gbp-sticky-feature__media-col gbp-sticky-feature-display-md" key="media" aria-hidden="true">
			<ul className="gbp-sticky-feature__media-list js-sticky-feature__media-list">
				{ items.map( ( item, index ) => (
					<li key={ index } className="gbp-sticky-feature__media-item js-sticky-feature__media-item">
						<figure className={ `gbp-sticky-feature__media-figure ${ aspectRatioClass }` }>
							{ item.imageUrl ? (
								<img src={ item.imageUrl } alt={ item.imageAlt || '' } />
							) : (
								<div className="gbp-sticky-feature__media-placeholder-inner" aria-hidden="true" />
							) }
						</figure>
					</li>
				) ) }
			</ul>
		</div>
	);

	return (
		<section { ...blockProps }>
			<div className="gbp-sticky-feature__container">
				<div className={ `gbp-sticky-feature__grid-1 gbp-sticky-feature__grid-1--image-${ imagePosition }` }>
					{ imagePosition === 'left' ? [ mediaCol, contentCol ] : [ contentCol, mediaCol ] }
				</div>
			</div>
		</section>
	);
}

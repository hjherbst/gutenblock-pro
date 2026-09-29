/**
 * GutenBlock FSE Agent - Editor Scripts
 *
 * This file is compiled by @wordpress/scripts
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './editor.scss';
import './agent';
import './agent/agent.scss';
import './suppress-starter-pattern-modal';

import './blocks/material-icon';
import './blocks/text-formats';
import './blocks/heading-text-image';
import './blocks/sticky-feature';
import './blocks/flexible-heading';
import './blocks/heading-part';
import './blocks/contact-form';

addFilter(
	'blocks.registerBlockType',
	'gutenblock-pro/premium-block-supports',
	(settings, name) => {
		if (name !== 'core/cover' && name !== 'core/group') {
			return settings;
		}
		return settings;
	},
	999
);

const withPremiumBlockLock = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { attributes, name } = props;

		const hasPremium = window.gutenblockProConfig?.hasPremium || false;
		const upgradeUrl = window.gutenblockProConfig?.upgradeUrl || 'https://app.gutenblock.com/licenses';

		const isOuterBlock = name === 'core/cover' || name === 'core/group';
		const className = attributes?.className || '';
		const premiumSlugs = window.gutenblockProConfig?.premiumPatterns || [];
		const isPremiumPattern = isOuterBlock && premiumSlugs.some((slug) => className.includes(`gb-pattern-${slug}`));

		if (isPremiumPattern && !hasPremium) {
			return (
				<Fragment>
					<BlockEdit {...props} />
					<InspectorControls>
						<PanelBody
							title={__('Premium Pattern', 'gutenblock-pro')}
							initialOpen={true}
							data-gb-premium-notice="true"
						>
							<PanelRow>
								<Notice status="warning" isDismissible={false}>
									<p style={{ marginBottom: '12px' }}>
										<strong>{__('🔒 GutenBlock Pro erforderlich', 'gutenblock-pro')}</strong>
									</p>
									<p style={{ marginBottom: '12px' }}>
										{__('Dieses Pattern kann als Vorschau eingefügt werden, ist aber nur mit GutenBlock Pro bearbeitbar.', 'gutenblock-pro')}
									</p>
									<Button
										isPrimary
										onClick={() => window.open(upgradeUrl, '_blank')}
										style={{ marginTop: '8px' }}
									>
										{__('Jetzt upgraden', 'gutenblock-pro')}
									</Button>
								</Notice>
							</PanelRow>
						</PanelBody>
					</InspectorControls>
				</Fragment>
			);
		}

		return <BlockEdit {...props} />;
	};
}, 'withPremiumBlockLock');

addFilter(
	'editor.BlockEdit',
	'gutenblock-pro/premium-lock',
	withPremiumBlockLock,
	999
);

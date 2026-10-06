import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { ExternalLink, PanelBody } from '@wordpress/components';

const BLOCK_NAME = 'acf/quick-exit';

/**
 * Adds a sidebar link to the site-wide Quick exit settings when that block is selected.
 */
const withQuickExitSettings = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const config = window.piteaQuickExitEditor || {};

        if (props.name !== BLOCK_NAME || !config.settingsUrl) {
            return <BlockEdit {...props} />;
        }

        return (
            <>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody title={config.panelTitle} initialOpen={true}>
                        <p>{config.help}</p>
                        <ExternalLink href={config.settingsUrl}>{config.linkText}</ExternalLink>
                    </PanelBody>
                </InspectorControls>
            </>
        );
    };
}, 'withQuickExitSettings');

addFilter('editor.BlockEdit', 'pitea/quick-exit-settings', withQuickExitSettings);

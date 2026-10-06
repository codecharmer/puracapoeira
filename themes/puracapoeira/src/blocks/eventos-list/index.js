import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Eventos', 'pura' ) }>
			<ToggleControl
				label={ __( 'Mostrar eventos pasados', 'pura' ) }
				checked={ attributes.showPast }
				onChange={ ( showPast ) => setAttributes( { showPast } ) }
			/>
			<RangeControl
				label={ __( 'Máximo de eventos (0 = todos)', 'pura' ) }
				value={ attributes.limit }
				min={ 0 }
				max={ 50 }
				onChange={ ( limit ) => setAttributes( { limit } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

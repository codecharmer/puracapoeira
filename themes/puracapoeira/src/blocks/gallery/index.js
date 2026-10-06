import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Galería', 'pura' ) }>
			<ToggleControl
				label={ __( 'Mostrar filtros', 'pura' ) }
				checked={ attributes.showFilters }
				onChange={ ( showFilters ) => setAttributes( { showFilters } ) }
			/>
			<RangeControl
				label={ __( 'Máximo de elementos', 'pura' ) }
				value={ attributes.limit }
				min={ 4 }
				max={ 120 }
				onChange={ ( limit ) => setAttributes( { limit } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

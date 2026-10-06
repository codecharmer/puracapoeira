import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Sedes', 'pura' ) }>
			<SelectControl
				label={ __( 'Diseño', 'pura' ) }
				value={ attributes.layout }
				options={ [
					{ label: __( 'Tarjetas', 'pura' ), value: 'cards' },
					{ label: __( 'Lista de enlaces', 'pura' ), value: 'links' },
				] }
				onChange={ ( layout ) => setAttributes( { layout } ) }
			/>
			<RangeControl
				label={ __( 'Máximo de sedes (0 = todas)', 'pura' ) }
				value={ attributes.limit }
				min={ 0 }
				max={ 24 }
				onChange={ ( limit ) => setAttributes( { limit } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

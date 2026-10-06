import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, SelectControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Detalle de sede', 'pura' ) }>
			<SelectControl
				label={ __( 'Parte', 'pura' ) }
				value={ attributes.part }
				options={ [
					{ label: __( 'Encabezado', 'pura' ), value: 'hero' },
					{ label: __( 'Cuerpo', 'pura' ), value: 'body' },
				] }
				onChange={ ( part ) => setAttributes( { part } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

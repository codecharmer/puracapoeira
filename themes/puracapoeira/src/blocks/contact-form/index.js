import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, TextControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Formulario', 'pura' ) }>
			<TextControl
				label={ __( 'Texto del botón', 'pura' ) }
				value={ attributes.submitLabel }
				onChange={ ( submitLabel ) => setAttributes( { submitLabel } ) }
			/>
			<TextControl
				label={ __( 'Mensaje de éxito', 'pura' ) }
				value={ attributes.successText }
				onChange={ ( successText ) => setAttributes( { successText } ) }
			/>
			<TextControl
				label={ __( 'Mensaje de error', 'pura' ) }
				value={ attributes.errorText }
				onChange={ ( errorText ) => setAttributes( { errorText } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

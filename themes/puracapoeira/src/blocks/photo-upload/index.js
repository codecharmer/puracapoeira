import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	const { askProfile, footnote } = attributes;

	return (
		<PanelBody title={ __( 'Subida de fotos', 'pura' ) }>
			<ToggleControl
				label={ __( 'Pedir foto de perfil', 'pura' ) }
				checked={ askProfile }
				onChange={ ( value ) => setAttributes( { askProfile: value } ) }
			/>
			<TextareaControl
				label={ __( 'Nota al pie', 'pura' ) }
				value={ footnote }
				onChange={ ( value ) => setAttributes( { footnote: value } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

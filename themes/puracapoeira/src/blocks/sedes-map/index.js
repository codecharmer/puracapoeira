import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Mapa de sedes', 'pura' ) }>
			<TextControl
				label={ __( 'Título', 'pura' ) }
				value={ attributes.title }
				onChange={ ( title ) => setAttributes( { title } ) }
			/>
			<TextControl
				label={ __( 'Texto de ayuda', 'pura' ) }
				value={ attributes.hint }
				onChange={ ( hint ) => setAttributes( { hint } ) }
			/>
			<RangeControl
				label={ __( 'Altura en px (0 = la del tema)', 'pura' ) }
				value={ attributes.height }
				min={ 0 }
				max={ 900 }
				step={ 10 }
				onChange={ ( height ) => setAttributes( { height } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

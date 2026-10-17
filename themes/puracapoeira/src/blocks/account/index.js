import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, TextareaControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Textos', 'pura' ) }>
			<TextareaControl
				label={ __( 'Introducción (sin sesión)', 'pura' ) }
				value={ attributes.introLoggedOut }
				onChange={ ( introLoggedOut ) =>
					setAttributes( { introLoggedOut } )
				}
			/>
			<TextareaControl
				label={ __( 'Sin registros a eventos', 'pura' ) }
				value={ attributes.emptyText }
				onChange={ ( emptyText ) => setAttributes( { emptyText } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

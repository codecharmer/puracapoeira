import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const VARIANTS = [
	{ value: 'header', label: __( 'Cabecera', 'pura' ) },
	{ value: 'overlay', label: __( 'Menú desplegable', 'pura' ) },
];

function Edit( { attributes, setAttributes } ) {
	const { variant } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Selector de idioma', 'pura' ) }>
					<SelectControl
						label={ __( 'Variante', 'pura' ) }
						value={ variant }
						options={ VARIANTS }
						onChange={ ( value ) =>
							setAttributes( { variant: value } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );

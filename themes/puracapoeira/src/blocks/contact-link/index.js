import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Enlace de contacto', 'pura' ) }>
			<SelectControl
				label={ __( 'Canal', 'pura' ) }
				value={ attributes.channel }
				options={ [
					{ label: 'WhatsApp', value: 'whatsapp' },
					{ label: 'Instagram', value: 'instagram' },
					{ label: 'Facebook', value: 'facebook' },
					{ label: 'YouTube', value: 'youtube' },
					{ label: __( 'Álbum de iCloud', 'pura' ), value: 'icloud' },
				] }
				onChange={ ( channel ) => setAttributes( { channel } ) }
			/>
			<TextControl
				label={ __( 'Texto (vacío = nombre del canal)', 'pura' ) }
				value={ attributes.label }
				onChange={ ( label ) => setAttributes( { label } ) }
			/>
			<ToggleControl
				label={ __( 'Mostrar el usuario / número', 'pura' ) }
				checked={ attributes.showValue }
				onChange={ ( showValue ) => setAttributes( { showValue } ) }
			/>
			<SelectControl
				label={ __( 'Estilo', 'pura' ) }
				value={ attributes.variant }
				options={ [
					{ label: __( 'Enlace', 'pura' ), value: 'link' },
					{ label: __( 'Botón', 'pura' ), value: 'btn-primary' },
					{
						label: __( 'Botón fantasma', 'pura' ),
						value: 'btn-ghost',
					},
					{
						label: __( 'Botón contorno', 'pura' ),
						value: 'btn-outline',
					},
				] }
				onChange={ ( variant ) => setAttributes( { variant } ) }
			/>
			{ attributes.channel === 'whatsapp' && (
				<TextControl
					label={ __( 'Mensaje inicial de WhatsApp', 'pura' ) }
					value={ attributes.message }
					onChange={ ( message ) => setAttributes( { message } ) }
				/>
			) }
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

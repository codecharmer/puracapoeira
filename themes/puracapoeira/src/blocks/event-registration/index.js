import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	const {
		eventSlug,
		eventName,
		days,
		askShirtSize,
		price,
		askPaymentProof,
		footnote,
	} = attributes;

	return (
		<PanelBody title={ __( 'Evento', 'pura' ) }>
			<TextControl
				label={ __( 'Nombre del evento', 'pura' ) }
				value={ eventName }
				onChange={ ( value ) => setAttributes( { eventName: value } ) }
			/>
			<TextControl
				label={ __( 'Identificador (sin espacios)', 'pura' ) }
				help={ __(
					'Agrupa los registros en el panel. Solo minúsculas, números y guiones.',
					'pura'
				) }
				value={ eventSlug }
				onChange={ ( value ) =>
					setAttributes( {
						eventSlug: value
							.toLowerCase()
							.replace( /[^a-z0-9-]+/g, '-' ),
					} )
				}
			/>
			<TextControl
				label={ __( 'Días (separados por |)', 'pura' ) }
				value={ days }
				onChange={ ( value ) => setAttributes( { days: value } ) }
			/>
			<ToggleControl
				label={ __( 'Preguntar talla de playera', 'pura' ) }
				checked={ askShirtSize }
				onChange={ ( value ) =>
					setAttributes( { askShirtSize: value } )
				}
			/>
			<TextControl
				label={ __( 'Cuota (texto)', 'pura' ) }
				help={ __( 'Vacío para no mostrar la cuota.', 'pura' ) }
				value={ price }
				onChange={ ( value ) => setAttributes( { price: value } ) }
			/>
			<ToggleControl
				label={ __( 'Pedir comprobante de pago', 'pura' ) }
				checked={ askPaymentProof }
				onChange={ ( value ) =>
					setAttributes( { askPaymentProof: value } )
				}
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

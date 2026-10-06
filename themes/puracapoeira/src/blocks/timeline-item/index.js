import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { year, title, text, i18nKey } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Traducción', 'pura' ) }>
					<TextControl
						label={ __(
							'Clave de traducción (p. ej. tl-1)',
							'pura'
						) }
						value={ i18nKey }
						onChange={ ( value ) =>
							setAttributes( {
								i18nKey: value.replace( /[^a-z0-9-]/g, '' ),
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<li { ...useBlockProps( { className: 'timeline-item' } ) }>
				<RichText
					tagName="span"
					className="timeline-item__year"
					value={ year }
					onChange={ ( value ) => setAttributes( { year: value } ) }
					placeholder={ __( 'Año', 'pura' ) }
					allowedFormats={ [] }
				/>
				<div className="timeline-item__card">
					<RichText
						tagName="h4"
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
						placeholder={ __( 'Título del hito', 'pura' ) }
						allowedFormats={ [ 'core/italic' ] }
					/>
					<RichText
						tagName="p"
						value={ text }
						onChange={ ( value ) =>
							setAttributes( { text: value } )
						}
						placeholder={ __( 'Descripción…', 'pura' ) }
						allowedFormats={ [
							'core/bold',
							'core/italic',
							'core/link',
						] }
					/>
				</div>
			</li>
		</>
	);
}

function Save( { attributes } ) {
	const { year, title, text, i18nKey } = attributes;
	const keyed = ( suffix ) =>
		i18nKey ? { 'data-i18n-key': `${ i18nKey }-${ suffix }` } : {};

	return (
		<li { ...useBlockProps.save( { className: 'timeline-item' } ) }>
			<RichText.Content
				tagName="span"
				className="timeline-item__year"
				value={ year }
			/>
			<div className="timeline-item__card">
				<RichText.Content
					tagName="h4"
					value={ title }
					{ ...keyed( 'title' ) }
				/>
				<RichText.Content
					tagName="p"
					value={ text }
					{ ...keyed( 'text' ) }
				/>
			</div>
		</li>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: Save } );

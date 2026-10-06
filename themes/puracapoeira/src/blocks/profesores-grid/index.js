import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl } from '@wordpress/components';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

function Controls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Profesores', 'pura' ) }>
			<RangeControl
				label={ __( 'Máximo de perfiles (0 = todos)', 'pura' ) }
				value={ attributes.limit }
				min={ 0 }
				max={ 48 }
				onChange={ ( limit ) => setAttributes( { limit } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name, Controls ),
	save: () => null,
} );

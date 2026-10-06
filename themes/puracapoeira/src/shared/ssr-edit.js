/**
 * Editor side of a server-rendered block: optional inspector controls + ServerSideRender preview.
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * @param {string}   name     Block name (metadata.name).
 * @param {Function} Controls Optional component rendering InspectorControls children.
 * @return {Function} Edit component.
 */
export function createServerSideEdit( name, Controls ) {
	return function Edit( { attributes, setAttributes } ) {
		return (
			<>
				{ Controls ? (
					<InspectorControls>
						<Controls
							attributes={ attributes }
							setAttributes={ setAttributes }
						/>
					</InspectorControls>
				) : null }
				<div { ...useBlockProps() }>
					<ServerSideRender
						block={ name }
						attributes={ attributes }
					/>
				</div>
			</>
		);
	};
}

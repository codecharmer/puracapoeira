import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import { createServerSideEdit } from '../../shared/ssr-edit';

registerBlockType( metadata.name, {
	edit: createServerSideEdit( metadata.name ),
	save: () => null,
} );

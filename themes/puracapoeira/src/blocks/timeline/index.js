import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import metadata from './block.json';

const ALLOWED = [ 'pura/timeline-item' ];
const TEMPLATE = [
	[ 'pura/timeline-item', { year: '2005', title: '', text: '' } ],
];

function Edit() {
	const blockProps = useBlockProps( { className: 'timeline' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return <ol { ...innerProps } />;
}

function Save() {
	const blockProps = useBlockProps.save( {
		className: 'timeline reveal-on-scroll',
		'data-reveal-delay': '90',
	} );
	const innerProps = useInnerBlocksProps.save( blockProps );

	return <ol { ...innerProps } />;
}

registerBlockType( metadata.name, { edit: Edit, save: Save } );

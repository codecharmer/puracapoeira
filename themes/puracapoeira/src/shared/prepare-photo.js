/**
 * Getting a photo ready to upload, in the browser: shared by the photo-upload page and the
 * professors' editor.
 */

const MAX_SIDE = 2400; // px — plenty for the web, a fraction of a phone's original.
const JPEG_QUALITY = 0.86;

/**
 * Decode an image file honouring its EXIF orientation. Resolves to something drawImage accepts,
 * or rejects when the browser cannot decode it (HEIC outside Safari, for instance).
 *
 * @param {File} file Image.
 * @return {Promise<ImageBitmap|HTMLImageElement>} Decoded image.
 */
async function decode( file ) {
	if ( typeof window.createImageBitmap === 'function' ) {
		try {
			return await window.createImageBitmap( file, {
				imageOrientation: 'from-image',
			} );
		} catch ( _err ) {
			// fall through to <img>, which applies orientation in every modern browser
		}
	}
	return new Promise( ( resolve, reject ) => {
		const url = URL.createObjectURL( file );
		const img = new window.Image();
		img.onload = () => {
			URL.revokeObjectURL( url );
			resolve( img );
		};
		img.onerror = () => {
			URL.revokeObjectURL( url );
			reject( new Error( 'decode' ) );
		};
		img.src = url;
	} );
}

/**
 * The file to upload: the original when it is small enough, otherwise a JPEG no larger than
 * MAX_SIDE on its longest side. Throws 'too-big' when the file cannot be decoded and is over
 * the limit.
 *
 * With `always`, every photo is drawn again as a JPEG, which bakes in its rotation and drops
 * the data the camera embedded (where it was taken, the device); a file the browser cannot
 * decode then throws 'decode'.
 *
 * @param {File}    file     Original.
 * @param {number}  maxBytes Server limit.
 * @param {boolean} always   Re-encode even a small file.
 * @return {Promise<File>} File to send.
 */
export async function prepare( file, maxBytes, always = false ) {
	let image;
	try {
		image = await decode( file );
	} catch ( _err ) {
		if ( always ) {
			throw new Error( 'decode' );
		}
		if ( file.size <= maxBytes ) {
			return file;
		}
		throw new Error( 'too-big' );
	}

	const width = image.naturalWidth || image.width;
	const height = image.naturalHeight || image.height;
	const longest = Math.max( width, height );
	const needsResize = longest > MAX_SIDE || file.size > maxBytes;
	if ( ! needsResize && ! always ) {
		return file;
	}

	const scale = Math.min( 1, MAX_SIDE / longest );
	const canvas = document.createElement( 'canvas' );
	canvas.width = Math.round( width * scale );
	canvas.height = Math.round( height * scale );
	canvas
		.getContext( '2d' )
		.drawImage( image, 0, 0, canvas.width, canvas.height );
	if ( typeof image.close === 'function' ) {
		image.close();
	}

	const blob = await new Promise( ( resolve ) =>
		canvas.toBlob( resolve, 'image/jpeg', JPEG_QUALITY )
	);
	if ( ! blob ) {
		if ( always ) {
			throw new Error( 'decode' );
		}
		if ( file.size <= maxBytes ) {
			return file;
		}
		throw new Error( 'too-big' );
	}
	if ( blob.size > maxBytes ) {
		throw new Error( 'too-big' );
	}
	const name = file.name.replace( /\.[^.]+$/, '' ) + '.jpg';
	return new File( [ blob ], name, {
		type: 'image/jpeg',
		lastModified: file.lastModified,
	} );
}

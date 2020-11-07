<?php
use Treinetic\ImageArtist\lib\Image;
use Treinetic\ImageArtist\lib\PolygonShape;
use Treinetic\ImageArtist\lib\Text\TextBox;
use Treinetic\ImageArtist\lib\Text\Color;
use Treinetic\ImageArtist\lib\Text\Font;
use Treinetic\ImageArtist\lib\Overlays\Overlay;
use Treinetic\ImageArtist\lib\Text\Write\WriteFactory;
use Treinetic\ImageArtist\lib\Text\Write\GDWritingStrategy;
use Treinetic\ImageArtist\lib\Text\Write\ImagickWritingStrategy;

/*
 *	Imagick
 */
// compare images
// https://stackoverflow.com/questions/37581147/percentage-of-pixels-that-have-changed-in-an-image-in-php#37594159
// https://stackoverflow.com/questions/4684023/how-to-check-if-an-integer-is-within-a-range-of-numbers-in-php
function image_compare_get_distortion($image_path_1=null, $image_path_2=null, $resize=true){
	// check for errors
	foreach ( [$image_path_1, $image_path_2] as $image )
		if ( !$image or !file_exists($image) )
			cli_echo('File not found. ' . $image, ['header' => 'error', 'function' => __FUNCTION__]);

	// load up
	$image1 = new Imagick($image_path_1);
	$image2 = new Imagick($image_path_2);

	$w1 = $image1->getImageWidth();
	$h1 = $image1->getImageHeight();
	$h2 = $image2->getImageHeight();
	$diff = 100;

	if ( $resize and !(($h1-$diff <= $h2) and ($h2 <= $h1+$diff)) ){
		$image2->scaleImage($w1, $h1);
	}

	// compare
	$result = $image1->compareImages($image2,Imagick::METRIC_MEANABSOLUTEERROR);
	$p1 = $image1->getImageProperties();
	return (float) $p1['distortion'];
}

/*
 * Treinetic Image()
 */
function image_crop(string $file, string $output, array $crop){
	$image = new Image($file);

	if ( !$crop or empty($crop) )
		return false;

	$image->crop($crop[0], $crop[1], $crop[2], $crop[3]);

	$image->save($output, exif_imagetype($file), 100);
	
	return $output;
}

function image_scale(string $file, string $output, int $scale){
	$image = new Image($file);

	if ( $scale )
		$image->scale($scale);

	$image->save($output, exif_imagetype($file), 100);

	return $output;
}
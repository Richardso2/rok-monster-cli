<?php
use thiagoalessio\TesseractOCR\TesseractOCR;
use Treinetic\ImageArtist\lib\Image;

/*
	PSM 
	0    Orientation and script detection (OSD) only.
	1    Automatic page segmentation with OSD.
	2    Automatic page segmentation, but no OSD, or OCR.
	3    Fully automatic page segmentation, but no OSD. (Default)
	4    Assume a single column of text of variable sizes.
	5    Assume a single uniform block of vertically aligned text.
	6    Assume a single uniform block of text.
	7    Treat the image as a single text line.
	8    Treat the image as a single word.
	9    Treat the image as a single word in a circle.
	10    Treat the image as a single character.
	11    Sparse text. Find as much text as possible in no particular order.
	12    Sparse text with OSD.
	13    Raw line. Treat the image as a single text line, bypassing hacks that are Tesseract-specific.

	OCR engine mode	Working description
	0	Legacy engine only
	1	Neural net LSTM only
	2	Legacy + LSTM mode only
	3	By Default, based on what is currently available	
*/
function rok_do_ocr(array $args){
	// def
	$def = array(
		// what are we doing
		'job' => null,

		// files
		'input_path' => ROK_PATH_INPUT,
		'output_path' => ROK_PATH_OUTPUT,
		'tmp_path' => ROK_PATH_TMP,
		'offset' => 0,
		'limit' => -1,

		// enable video processing
		'video' => true,

		// image processing
		'compare_to_sample' => true,
		'distortion' => 0,

		// TesseractOCR
		'oem' => 0,
		'psm' => 7,
		'build_user_words' => false,

		// internal vars
		'debug' => false,
		'output' => [],
	);

	// args to vars
    $args = cli_parse_args($args, $def);
	extract($args);

	// always check if job is provided, if not config lookup will fail
	if ( !$job )
		cli_echo('rok_do_ocr() - Missing --job', array('header' => 'error'));
		
	cli_echo('Starting '.$job, array('format' => 'bold'));

	// check if single file or path to dir
	// TODO: fix single file input
	if ( is_file($input_path) ){
		$files_ocr = [$input_path];

	} elseif ( is_dir($input_path) ){
		$files_ocr = rok_get_files_ocr($input_path, $tmp_path);

	} else {
		cli_echo('rok_do_ocr() - Missing $input_path', array('header' => 'error'));

	}

	// if not set, try tmp DIR
	if ( !isset($files_ocr) or !$files_ocr or empty($files_ocr))
		cli_echo('rok_do_ocr() - Missing $files_ocr', array('header' => 'error'));

	// start vars
	$data = [];
	$count = 0;
	
	// process each image file
	foreach ($files_ocr as $file) {
		if ( !is_file($file) ) continue;	// maybe we've already removed this file
		if ( is_dot_file($file) ) continue;	// manually SKIP_DOTS

		// skip non image formats
		if ( !is_mime_content_type($file, 'image') ) continue;

		// persistent
		$count++;
		cli_echo(cli_txt_style('['.basename($file).']', ['fg' => 'light_green']) . ' #' . $count);

		// match image to sample retrieve templates
		if ( $compare_to_sample ){
			$match = false;
			rok_get_config('samples')[$job] ?? die();
			foreach ( rok_get_config('samples')[$job] as $sample => $sample_dist ){
				$image_distortion = image_compare_get_distortion($file, rok_get_public_images($sample, 'sample'));

				cli_echo('Distortion: ' . $image_distortion);
				
				// does not match a template
				if ( $image_distortion >= ($distortion > 0 ? $distortion : $sample_dist) ){
					continue;
	
				} else {
					// matched
					$match = true;
					cli_echo('Match: ' . $sample, ['fg' => 'light_green']);
				}	
			}

			// if no match to sample skip input file
			if ( !$match ){
				cli_echo('Skip...', ['fg' => 'yellow']);
				echo PHP_EOL;	
				continue;
			}	
		}
	
		if ( !$profile = rok_get_config($sample) )
			cli_echo('rok_do_ocr() - Missing --job profile', array('header' => 'error'));

		// start data for file
		$tmp = [
			'_image' => basename($file),
			'_created' => date('m-d-Y H:i:s', filectime($file)),
			'_distortion' => $image_distortion ?? null,
		];

		// slice image for parts
		$images = [];
		foreach ( $profile['ocr_schema'] as $key => $schema ){
			// init for further use
			$tmp[$key] = null;

			// skip img process if no crop available
			if ( empty($schema['crop']) )
				continue;

			// cut image for this key
			$images[$key] = ROK_PATH_TMP . '/' . md5($key.$file) . '.png';
			image_crop($file, $images[$key], $schema['crop']);
		}

		// ocr each image part
		foreach ( $images as $key => $image ){
			// ocr
			$ocr = (new TesseractOCR($image))
				->configFile(($profile['ocr_schema'][$key]['config']??null))
				->whitelist(($profile['ocr_schema'][$key]['whitelist']??null))
				// language, bug with rus
				// ->lang('eng','ara','chi_sim','chi_tra','fra','deu','ind','ita','jpn','kor','msa','por','rus','spa','spa_old','tha','tur', 'vie')
				->lang('eng', 'jpn')

				// our dictionary
				// ->userWords(ROK_PATH_INPUT . '/words.txt')
				// ->userPatterns(ROK_PATH_INPUT . '/patterns.txt')

				// TESTING:
				->oem($oem)       
				->psm($psm)

				// lets go!
				->run();
			
			cli_echo('OCR: ' . $key . ' ' . cli_txt_style(basename($image), ['fg' => 'light_gray']));
			$tmp[$key] = text_remove_extra_lines($ocr);

			if ( isset($profile['ocr_schema'][$key]['callback']) and function_exists($profile['ocr_schema'][$key]['callback']) )
				$tmp[$key] = $profile['ocr_schema'][$key]['callback']($tmp[$key]);

			if ( $debug )
				echo $tmp[$key] . PHP_EOL;
		}

		// add entry to others
		$data[] = $tmp;

		// space for next
		echo PHP_EOL;
	}

	// add user words
	if ( $build_user_words ){
		$user_words_file = ROK_PATH_OUTPUT . '/' . $job . '-user-words.txt';
		$args['output']['user_words_file'] = rok_ocr_build_user_words($user_words, ['name'], $user_words_file);
	}

	// table output
	if ( is_cli() )
		rok_cli_table(($profile['table']??null), $data);

	// csv
	if ( isset($profile['csv_headers']) ){
		$csv_file = ROK_PATH_OUTPUT . '/' . $job . '-' . time() . '.csv';
		if ( !$args['output']['csv'] = rok_build_csv($data, $profile['csv_headers'], $csv_file) )
			cli_echo("Can't close php://output", ['header' => 'error']);
	}

	return ['data' => $data, 'job' => $args];
}

// find interesting scenes
function rok_video_find_scene_change($files_input, $output_path){
	if ( is_string($files_input) )
		$files_input = [$files_input];

	$count = 0;
	$files = [];
	foreach ($files_input as $file) {
		if ( !is_file($file) ) continue;	// maybe we've already removed this file
		if ( is_dot_file($file) ) continue;	// manually SKIP_DOTS

		// skip non video formats
		if ( !is_mime_content_type( $file, 'video') ) continue;

		$count++;

		if ( !is_dir($output_path) and !mkdir($output_path, 0775, true) )
			cli_echo('!mkdir ' . $output_path, ['header' => 'error']);

		cli_echo(cli_txt_style('['.basename($file).']', ['fg' => 'green']) . ' ' . $count);

		rok_do_ffmpeg_cmd(['action' => 'interesting', 'input' => $file, 'output_path' => $output_path, 'frames' => rok_get_config('frames')]);
	}
}

// make CSV
function rok_build_csv($data, $headers, $output){
	if ( !$headers or empty($headers) )
		$headers = array_keys($data[0]);
	
	// build csv
	$csv = [];
	foreach($data as $row) {
		$tmp = [];
		foreach ( array_values($headers) as $key )
			$tmp[] = $row[$key] ?? '';

		$csv[] = $tmp;
	}

	// make file name if non exist
	if ( !$output )
		$output = ROK_PATH_OUTPUT . '/' . time() . '.csv';

	// save to CSV
	$fp = fopen($output, 'w');
	fputcsv($fp, array_keys($headers));
	foreach($csv as $row) {
		fputcsv($fp, $row);
	}

	// on success return path of finished CSV
	if ( fclose($fp) )
		return $output;
		
	// something failed while writing
	return false;
}

// build user words
function rok_ocr_build_user_words($data, $keys, $output){
	foreach ( $data as $entry ) {
		foreach ( $keys as $key ) {
			if ( isset($entry[$key]) )
				$user_words[] = $entry[$key];
		}
	}
	
	// save user words to file
	$output = ROK_PATH_OUTPUT . '/' . $job . '-user-words.txt';
	if ( file_put_contents($output, implode(PHP_EOL, $user_words)) )
		return $output;

	return false;
}

function rok_get_files_ocr($path, $tmp_path=null, $offset=0, $limit=-1){
	// error checks
	if ( !$path or !is_dir($path) )
		cli_echo('rok_get_dir() - DIR does not exist ' . $path, array('header' => 'error'));

	// files
	$files = sort_filesystem_iterator($path, $offset, $limit);
	$files_output = [];
	cli_echo('Files found: '. count($files));

	foreach ( $files as $file ){
		switch ( get_mime_content_type($file) ){
			// add all images
			case 'image':
				$files_output[] = $file;
			break;

			// add exported images from video
			case 'video':
				if ( !$tmp_path )
					$tmp_path = ROK_PATH_TMP;

				$save_to = $tmp_path . '/' . pathinfo($file)['filename']; 
				rok_video_find_scene_change($file, $save_to);

				// add these video files to total files
				$files_output+= rok_get_files_ocr($save_to);
			break;
		}
	}

	return $files_output;
}

function is_mime_content_type($file=null, $type='image'){
	if ( !is_file($file) )
		return false;

	$mime = mime_content_type($file);
	
	if ( substr($mime, 0, strlen($type)) == $type )
		return true;

	return false;
}

function get_mime_content_type($file=null){
	foreach ( ['image', 'video'] as $type )
		if ( is_mime_content_type($file, $type) )
			return $type;

	return false;
}
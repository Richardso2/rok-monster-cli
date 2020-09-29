<?php
/**
 * CLI
 */
function cli_echo($msg=null, $args=array()){
    $def = array(
        // progress
        'done' => null,
        'progress' => false,

        // outputs
        'bg' => null,
        'fg' => null,
        'color_scheme' => null,
        'format' => false,
        'header' => null,
        'show_time' => false,

        // actions
        'echo' => true,
        'exit' => false,
        'write' => true,
        'stdin' => [],
        'debug' => cli_get_arg('debug'),
    );

    // args to vars
    $args = cli_parse_args($args, $def);
    extract($args);

    // vars
    $out = $time = null;
        
    // setup
    if ( $show_time and !$done )
        $time = date('h:i:s A') . ' - ';

    // header 
    if ( $progress )
        $header = 'work';
    
    if ( $header )
        $args['color_scheme'] = $header;

    // color + output
    if ( ($msg or $header) and !$done ){
        switch ($args['header']) {
            case 'error':
                $out = _cli_echo_padding($msg, $args);
                break;

             case 'debug':
                if ( !$debug )
                    return false;

            default:
                $out = $time.cli_txt_style(($header?_cli_echo_header($header):''), $args+['style' => 'bold']).cli_txt_style($msg, $args);
                break;
        }
    }
   
    // append finish
    if ( $done ){
        $out.= '] ';
        $out.= $msg ? $msg : '100%';
    }

    // leave open for progress
    $out.= $progress ? ' [' : PHP_EOL;

    // echo if not surpressed
    if ( $echo )
        echo $out;
    
    // https://stackoverflow.com/questions/6543841/php-cli-getting-input-from-user-and-then-dumping-into-variable-possible#6543936
    if ( $stdin ){
        $handle = fopen (STDIN, 'r');
        $line = fgets($handle);
        return escapeshellcmd(trim($line));
    }
        
    // cya buddy
    if ( $exit )
        die(PHP_EOL);

    // just return it
    return $out;
}

function cli_echo_array($schema=null, $data=null, $args=array()){
    $def = array(
    	// options
    	'header' => false,
    	'footer' => false,
    	'after_item' => false,
    	'multi_line' => false,
    	'echo' => true,

    	// vars
    	'out' => null,
    );

    // args to vars
    $args = cli_parse_args($args, $def);
    extract($args);

    if ( $schema ){
    	$bar = ' +';
		$out.= ' | ';

    	foreach ($schema as $key => $meta) {
    		$bar.= str_pad_unicode('', $meta['size']+2, '-') . '+';
    		$out.= cli_txt_style(str_pad($meta['title'], $meta['size']), ['style' => 'bold']) . ' | ';
    	}
		$out.= PHP_EOL;
    	$bar.= PHP_EOL;
    }

    if ( $data ){
    	$out = null;
    	if ( !$multi_line )
    		$out.= ' | ';

		foreach ($schema as $key => $value) {
			// skip if not in schema
			if ( !isset($data[$key]) )
				continue;

			if ( $multi_line ){
				foreach ( explode_on_rn($data[$key]) as $line )
					$out.= ' | ' . str_pad_unicode($line, $schema[$key]['size']) . ' | ' . PHP_EOL;					

			} else {
				$out.= str_pad_unicode($data[$key], $schema[$key]['size']) . ' | ' ;					

			}
		}
		
		if ( !$multi_line )
    		$out.= PHP_EOL;

    	if ( $after_item )
    		$out.= $bar;		
    }

    // build outputs
	if ( $header ){
		$output = $bar . $out . $bar;

	} elseif ( $footer ){
		$output = $bar;

	} else {
	    $output = $out;

	}

	// echo or return
	if ( $echo )
		echo $output;

	return $output;
}

function cli_txt_style(string $txt, array $args){
    $def = [
        'bg' => null,
        'fg' => null,
        'color_scheme' => null,
        'style' => null,
    ];

    // args to vars
    $args = extract(cli_parse_args($args, $def));

    if ( !$txt )
        return false;

    /**
    *  Foreground Colors            Background Colors
    *
    *  - black                      * light_gray
    *  - dark_gray                  
    *  - blue
    *  - light_blue
    *  - green
    *  - light_green
    *  - cyan
    *  - light_cyan
    *  - red
    *  - light_red
    *  - purple
    *  - light_purple
    *  - brown
    *  - yellow
    *  - light_gray
    *  - white
    */
    switch ($color_scheme) {
        case 'debug':
            $fg = 'black';
            $bg = 'yellow';
            break;

        case 'error':
            // $fg = 'white';
            $bg = 'red';
            break;
        
        case 'warning':
            $fg = 'black';
            $bg = 'yellow';
            break;
    }

    // style
    switch ($style) {
        case 'bold':
            $txt = "\033[1m" . $txt . "\033[0m";
            break;
    }

    // color
    if ( $bg or $fg ){
        $colors = new Wujunze\Colors();
        $txt = $colors->getColoredString($txt, $fg, $bg);
    }

    return $txt;
}

function _cli_echo_padding(string $msg, array $args){
    $out = null;
    $msg.= '  ';
    $text = '  ' . $args['header'] .': ' . $msg;
    $text_array = [
        cli_txt_style(str_pad_unicode(' ', strlen($text)), $args),
        cli_txt_style('  ' . _cli_echo_header($args['header']), $args+['style' => 'bold']).cli_txt_style($msg, $args),
        cli_txt_style(str_pad_unicode(' ', strlen($text)), $args),
    ];
    foreach ($text_array as $txt)
        $out.= '  ' . $txt . PHP_EOL;
    return PHP_EOL . $out;
}

function _cli_echo_header(string $header){
    return strtoupper($header) . ': ';

}

function cli_get_arg($a, $alt=false){
	if ( isset($_GET[$a]) ){
		if ( (string)$_GET[$a] == '0' or strtolower($_GET[$a]) == 'false' )
			return false;
		return $_GET[$a];
	}

	return $alt;
}

function cli_mkdir(string $path=null, bool $exit=true){
    // if we already exist, we're good
    if ( is_dir($path) )
        return true;

    // mask + mkdir
    $oldmask = umask(0);
    mkdir($path, 0777);
    umask($oldmask);

    // did we make the dir, if so we're good
    if( is_dir($path) )
        return true;

    // awesome something went wrong, fail
    if ( $exit )
        cli_echo('cli_mkdir() - '. $path, ['header' => 'error', 'exit' => 1]);
}

function cli_rmdirr(string $path) {
    foreach ( glob("{$path}/*" ) as $file) {
        if ( is_dir($file) ) { 
            rmdirr($file);
        } else {
            unlink($file);
        }
    }
    rmdir($path);
}

function cli_parse_get(){
	global $args, $argv;

	// browser check
	if ( !isset($_SERVER) or (isset($_SERVER) and !isset($_SERVER['HTTP_USER_AGENT'])) )
		$args = $argv;

	if ( isset($args) )	
		array_shift($args);
	
	// Written by Colin Fein
	if ( !empty($args)){foreach($args as $param){if (strpos($param, '--') === 0){$paramString = substr($param, 2);if ( ! empty($paramString)){if (strpos($paramString, '=') !== false){list($key, $value) = explode('=', $paramString);$_GET[strtolower($key)] = $value;}else{$_GET[strtolower($paramString)] = null;}}}}}
}

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 * @since 2.2.0
 * @since 2.3.0 `$args` can now also be an object.
 *
 * @param string|array|object $args     Value to merge with $defaults.
 * @param array               $defaults Optional. Array that serves as the defaults. Default empty.
 * @return array Merged user defined values with defaults.
 */
function cli_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) ) {
		$r = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$r =& $args;
	} else {
		cli_parse_str( $args, $r );
	}

	if ( is_array( $defaults ) ) {
		return array_merge( $defaults, $r );
	}
	return $r;
}

/**
 * Parses a string into variables to be stored in an array.
 *
 * Uses {@link https://secure.php.net/parse_str parse_str()} and stripslashes if
 * {@link https://secure.php.net/magic_quotes magic_quotes_gpc} is on.
 *
 * @since 2.2.1
 *
 * @param string $string The string to be parsed.
 * @param array  $array  Variables will be stored in this array.
 */
function cli_parse_str( $string, &$array ) {
	parse_str( $string, $array );

	return $array;
}

function cli_php_setup(){
	ini_set('memory_limit', '2048M');
	ini_set('default_socket_timeout', '300');

    // php setup    
    set_time_limit(0);

    // TODO: Still testing?
	gc_disable();

    // locale
    date_default_timezone_set('America/New_York');
    setlocale(LC_MONETARY, 'en_US');
    	
	// debug
	if ( cli_get_arg('debug') ){
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
	}
}

function cli_shell_exec(){
    cli_echo();
}

/**
	Copyright (c) 2010, dealnews.com, Inc.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	 * Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.
	 * Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.
	 * Neither the name of dealnews.com, Inc. nor the names of its contributors
	   may be used to endorse or promote products derived from this software
	   without specific prior written permission.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
	AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
	ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
	LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
	CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
 */
/**
 * show a status bar in the console
 * 
 * <code>
 * for($x=1;$x<=100;$x++){
 * 
 *     show_status($x, 100);
 * 
 *     usleep(100000);
 *                           
 * }
 * </code>
 *
 * @param   int     $done   how many items are completed
 * @param   int     $total  how many items are to be done total
 * @param   int     $size   optional size of the status bar
 * @return  void
 *
 */
function cli_show_status($done, $total, $size=50, $brackets=true) {
    static $start_time;
    // original
    // $empty = ' ';
    // $full = '=';
    
    // mod
    $empty = '░';
    $full = '▓';

    // if we go over our bound, just ignore it
    if($done > $total) return;

    if(empty($start_time)) $start_time=time();
    $now = time();

    $perc=(double)($done/$total);

    $bar=floor($perc*$size);

    $status_bar="\r";
    if ( $brackets )
        $status_bar.="[";
    $status_bar.=str_repeat($full, $bar);
    if($bar<$size){
        $status_bar.=">";
        $status_bar.=str_repeat($empty, $size-$bar);
    } else {
        $status_bar.=$full;
    }

    $disp=number_format($perc*100, 0);

    if ( $brackets )    
        $status_bar.="]";

    $status_bar.=" $disp%  $done/$total";

    $rate = ($now-$start_time)/$done;
    $left = $total - $done;
    $eta = round($rate * $left, 2);

    $elapsed = $now - $start_time;

    // $status_bar.= " remaining: ".number_format($eta)." sec. elapsed: ".number_format($elapsed)." sec.";

    echo "$status_bar  ";

    flush();

    // when done, send a newline
    if( $done == $total )
        echo PHP_EOL;
}

function cli_show_status_close($done, $total){
    if( $done != $total )
        echo PHP_EOL;
}

/**
 * Helpers
 */
function _get($opt=null, $alt=false){
    if ( isset($_GET[$opt]) )
        return $_GET[$opt];

    return $alt;
}

function _post($opt=null, $alt=false){
    if ( isset($_POST[$opt]) )
        return $_POST[$opt];

    return $alt;
}

// explodes on new line
function explode_on_rn($str=null){
	return explode(',', str_replace(array("\r\n", "\r", "\n"), ',', $str));
}

// converts bytes to KB, MB, GB, TB
function format_bytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
    // $bytes /= pow(1024, $pow);
    $bytes /= (1 << (10 * $pow)); 

    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

// http://php.net/manual/en/function.next.php
function has_next(array $_array) {
	return next($_array) !== false ?: key($_array) !== null;
}

function is_cli(){
    if ( isset($_SERVER) and isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] )
        return true;

    return false;
}

function is_dot_file($file=null){
	return basename($file)[0] == '.';
}

function iterator_to_array_key($iterator, $key_value='key'){
	// cast to a plain array
	$array = array();
	foreach ($iterator as $key => $value){
		switch ($key_value) {
			case 'key':
				$array[] = $key;
				break;
			
			default:
				$array[] = $value;
				break;
		}
	}

	return $array;
}

function lr_trim($string=null){
	// trim both left + right of extra non-alpha characters
	return ltrim(rtrim(trim($string), '$-_.+!*\'(),{}|\\^~[]`<>#%";/?:@&='), '$-_.+!*\'(),{}|\\^~[]`<>#%";/?:@&=');
}

// https://stackoverflow.com/questions/14773072/php-str-pad-unicode-issue#27194169
function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = NULL){
    $encoding = $encoding === NULL ? mb_internal_encoding() : $encoding;
    $padBefore = $dir === STR_PAD_BOTH || $dir === STR_PAD_LEFT;
    $padAfter = $dir === STR_PAD_BOTH || $dir === STR_PAD_RIGHT;
    $pad_len -= mb_strlen($str, $encoding);
    $targetLen = $padBefore && $padAfter ? $pad_len / 2 : $pad_len;
    $strToRepeatLen = mb_strlen($pad_str, $encoding);
    $repeatTimes = ceil($targetLen / $strToRepeatLen);
    $repeatedString = str_repeat($pad_str, max(0, $repeatTimes)); // safe if used with valid utf-8 strings
    $before = $padBefore ? mb_substr($repeatedString, 0, floor($targetLen), $encoding) : '';
    $after = $padAfter ? mb_substr($repeatedString, 0, ceil($targetLen), $encoding) : '';
    return $before . $str . $after;
}

// https://secure.php.net/manual/en/function.str-pad.php#111147
function str_pad_unicode($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT) {
    $str_len = mb_strlen($str);
    $pad_str_len = mb_strlen($pad_str);
    if (!$str_len && ($dir == STR_PAD_RIGHT || $dir == STR_PAD_LEFT)) {
        $str_len = 1; // @debug
    }
    if (!$pad_len || !$pad_str_len || $pad_len <= $str_len) {
        return $str;
    }
    
    $result = null;
    $repeat = ceil($str_len - $pad_str_len + $pad_len);
    if ($dir == STR_PAD_RIGHT) {
        $result = $str . str_repeat($pad_str, $repeat);
        $result = mb_substr($result, 0, $pad_len);
    } else if ($dir == STR_PAD_LEFT) {
        $result = str_repeat($pad_str, $repeat) . $str;
        $result = mb_substr($result, -$pad_len);
    } else if ($dir == STR_PAD_BOTH) {
        $length = ($pad_len - $str_len) / 2;
        $repeat = ceil($length / $pad_str_len);
        $result = mb_substr(str_repeat($pad_str, $repeat), 0, floor($length)) 
                    . $str 
                       . mb_substr(str_repeat($pad_str, $repeat), 0, ceil($length));
    }
    
    return $result;
}

function sort_filesystem_iterator($files_path=null, $offset=0, $limit=-1){
	// cleaning up inputs
	if ( $offset === false )
		$offset = 0;
	if ( $limit === false )
		$limit = -1;

	// FilesystemIterator()
	$files = new FilesystemIterator($files_path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);

	// sort
	$files = iterator_to_array_key($files, 'key');
	sort($files);

	// add offset/limits
	$files = new LimitIterator(new ArrayIterator($files), $offset, $limit);
	$files = iterator_to_array_key($files, 'value');

	// we're done
	return $files;
}

/*
 * Misc
 */
// https://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago#18602474
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
<?php

/**
 * Utility to generate font definition files
 *
 * @author Olivier Plathey, Jonathan Stoll
 * @version 2.0.0
 */

use fpdf\FPDFException;
use fpdf\TTFParser;

require('TTFParser.php');


function fpdf_print_message(string $txt, string $severity = null) : void
{
	if (PHP_SAPI === 'cli') {
		if ($severity) {
			echo $severity, ': ';
		}
		echo $txt . PHP_EOL;
	} else {
		if ($severity) {
			echo '<strong>', $severity, '</strong>: ';
		}
		echo $txt, '<br>';
	}
}

function fpdf_notice(string $txt) : void
{
	fpdf_print_message($txt, 'Notice');
}

function fpdf_warning(string $txt) : void
{
	fpdf_print_message($txt, 'Warning');
}

function fpdf_load_map(string $enc) : array
{
	$file = __DIR__ . '/' . strtolower($enc) . '.map';
	$a = file($file);
	if (empty($a)) {
		throw new FPDFException('Encoding not found: ' . $enc);
	}

	$map = array_fill(0, 256, array('uv' => -1, 'name' => '.notdef'));

	foreach ($a as $line) {
		$e = explode(' ', rtrim($line));
		$c = hexdec(substr($e[0],1));
		$map[$c] = array(
			'uv' => hexdec(substr($e[1],2)),
			'name' => $e[2]
		);
	}

	return $map;
}

/**
 * @param string $file_name
 * @param bool $embed
 * @param bool $subset
 * @param string[] $map
 * @return array
 */
function fpdf_get_info_from_true_type(string $file_name, bool $embed, bool $subset, array $map) : array
{
	// Return information from a TrueType font
	try {
		$ttf = new TTFParser($file_name);
		$ttf->parse();
	} catch (Exception $e) {
		new FPDFException($e->getMessage(), 0, $e);
	}

	$info = array();

	if ($embed) {
		if (!$ttf->embeddable) {
			throw new FPDFException('Font license does not allow embedding');
		}

		if ($subset) {
			$chars = array();
			foreach ($map as $v) {
				if ($v['name'] !== '.notdef') {
					$chars[] = $v['uv'];
				}
			}
			$ttf->subset($chars);
			$info['Data'] = $ttf->Build();
		} else {
			$info['Data'] = file_get_contents($file_name);
		}
		$info['OriginalSize'] = strlen($info['Data']);
	}

	$k = 1000 / $ttf->units_per_em;
	$info['FontName'] = $ttf->post_script_name;
	$info['Bold'] = $ttf->bold;
	$info['ItalicAngle'] = $ttf->italic_angle;
	$info['IsFixedPitch'] = $ttf->is_fixed_pitch;
	$info['Ascender'] = round($k * $ttf->typo_ascender);
	$info['Descender'] = round($k * $ttf->typo_descender);
	$info['UnderlineThickness'] = round($k * $ttf->underline_thickness);
	$info['UnderlinePosition'] = round($k * $ttf->underline_position);
	$info['FontBBox'] = array(round($k * $ttf->xMin), round($k * $ttf->yMin), round($k * $ttf->xMax), round($k * $ttf->yMax));
	$info['CapHeight'] = round($k * $ttf->cap_height);
	$info['MissingWidth'] = round($k * $ttf->glyphs[0]['w']);
	$widths = array_fill(0, 256, $info['MissingWidth']);

	foreach ($map as $c => $v) {
		if ($v['name'] !== '.notdef') {
			if (isset($ttf->chars[$v['uv']])) {
				$id = $ttf->chars[$v['uv']];
				$w = $ttf->glyphs[$id]['w'];
				$widths[$c] = round($k*$w);
			} else {
				fpdf_warning('Character ' . $v['name'] . ' is missing');
			}
		}
	}
	$info['Widths'] = $widths;
	return $info;
}

/**
 * @param $file
 * @param bool $embed
 * @param string[] $map
 * @return array
 */
function fpdf_get_info_from_type1($file, bool $embed, array $map) : array
{
	$info = array();

	// Return information from a Type1 font
	if ($embed) {
		$f = fopen($file, 'rb');
		if (!$f) {
			throw new FPDFException('Can\'t open font file');
		}
		// Read first segment
		$a = unpack('Cmarker/Ctype/Vsize', fread($f,6));
		if ($a['marker'] != 128) {
			throw new FPDFException('Font file is not a valid binary Type1');
		}
		$size1 = $a['size'];
		$data = fread($f, $size1);
		// Read second segment
		$a = unpack('Cmarker/Ctype/Vsize', fread($f,6));
		if ($a['marker'] != 128) {
			throw new FPDFException('Font file is not a valid binary Type1');
		}
		$size2 = $a['size'];
		$data .= fread($f, $size2);
		fclose($f);
		$info['Data'] = $data;
		$info['Size1'] = $size1;
		$info['Size2'] = $size2;
	}

	$afm = substr($file, 0, -3) . 'afm';
	if (!file_exists($afm)) {
		throw new FPDFException('AFM font file not found: ' . $afm);
	}
	$a = file($afm);
	if (empty($a)) {
		throw new FPDFException('AFM file empty or not readable');
	}
	foreach ($a as $line) {
		$e = explode(' ', rtrim($line));
		if (count($e)<2) {
			continue;
		}
		$entry = $e[0];
		if ($entry === 'C') {
			$w = $e[4];
			$name = $e[7];
			$cw[$name] = $w;
		} elseif ($entry === 'FontName') {
			$info['FontName'] = $e[1];
		} elseif ($entry === 'Weight') {
			$info['Weight'] = $e[1];
		} elseif ($entry === 'ItalicAngle') {
			$info['ItalicAngle'] = (int)$e[1];
		} elseif ($entry === 'Ascender') {
			$info['Ascender'] = (int)$e[1];
		} elseif ($entry === 'Descender') {
			$info['Descender'] = (int)$e[1];
		} elseif ($entry === 'UnderlineThickness') {
			$info['UnderlineThickness'] = (int)$e[1];
		} elseif ($entry === 'UnderlinePosition') {
			$info['UnderlinePosition'] = (int)$e[1];
		} elseif ($entry === 'IsFixedPitch') {
			$info['IsFixedPitch'] = $e[1] === 'true';
		} elseif ($entry === 'FontBBox') {
			$info['FontBBox'] = array((int)$e[1], (int)$e[2], (int)$e[3], (int)$e[4]);
		} elseif ($entry === 'CapHeight') {
			$info['CapHeight'] = (int)$e[1];
		} elseif ($entry === 'StdVW') {
			$info['StdVW'] = (int)$e[1];
		}
	}

	if (!isset($info['FontName'])) {
		throw new FPDFException('FontName missing in AFM file');
	}
	if (!isset($info['Ascender'])) {
		$info['Ascender'] = $info['FontBBox'][3];
	}
	if (!isset($info['Descender'])) {
		$info['Descender'] = $info['FontBBox'][1];
	}
	$info['Bold'] = isset($info['Weight']) && preg_match('/bold|black/i', $info['Weight']);
	$info['MissingWidth'] = $cw['.notdef'] ?? 0;
	$widths = array_fill(0, 256, $info['MissingWidth']);
	foreach($map as $c => $v) {
		if ($v['name'] !== '.notdef') {
			if (isset($cw[$v['name']])) {
				$widths[$c] = $cw[$v['name']];
			} else {
				fpdf_warning('Character ' . $v['name'] . ' is missing');
			}
		}
	}
	$info['Widths'] = $widths;
	return $info;
}

function fpdf_make_font_descriptor(array $info) : string
{
	// Ascent
	$fd = "array('Ascent'=>" . $info['Ascender'];
	// Descent
	$fd .= ",'Descent'=>" . $info['Descender'];
	// CapHeight
	if (!empty($info['CapHeight'])) {
		$fd .= ",'CapHeight'=>" . $info['CapHeight'];
	} else {
		$fd .= ",'CapHeight'=>" . $info['Ascender'];
	}

	// Flags
	$flags = 0;
	if ($info['IsFixedPitch']) {
		$flags += 1 << 0;
	}
	$flags += 1<<5;
	if ($info['ItalicAngle']!=0) {
		$flags += 1 << 6;
	}
	$fd .= ",'Flags'=>" . $flags;

	// FontBBox
	$fbb = $info['FontBBox'];
	$fd .= ",'FontBBox'=>'[" . $fbb[0] . ' ' . $fbb[1] . ' ' . $fbb[2] . ' ' . $fbb[3] . "]'";

	// ItalicAngle
	$fd .= ",'ItalicAngle'=>" . $info['ItalicAngle'];

	// StemV
	if (isset($info['StdVW'])) {
		$stemv = $info['StdVW'];
	} elseif($info['Bold']) {
		$stemv = 120;
	} else {
		$stemv = 70;
	}
	$fd .= ",'StemV'=>".$stemv;
	// MissingWidth
	$fd .= ",'MissingWidth'=>" . $info['MissingWidth'] . ')';

	return $fd;
}

function MakeWidthArray(array $widths) : string
{
	$s = "array(\n\t";
	for ($c = 0; $c <= 255; $c++) {
		if (chr($c) === "'") {
			$s .= "'\\''";
		} elseif (chr($c) === "\\") {
			$s .= "'\\\\'";
		} elseif ($c>=32 && $c<=126) {
			$s .= "'" . chr($c) . "'";
		} else {
			$s .= "chr($c)";
		}

		$s .= '=>' . $widths[$c];
		if ($c<255) {
			$s .= ',';
		}
		if (($c+1)%22 == 0) {
			$s .= "\n\t";
		}
	}
	$s .= ')';

	return $s;
}

function fpdf_make_font_encoding(array $map) : string
{
	// Build differences from reference encoding
	$ref = fpdf_load_map('cp1252');
	$s = '';
	$last = 0;

	for ($c = 32; $c <= 255; $c++) {
		if ($map[$c]['name'] != $ref[$c]['name']) {
			if ($c != $last + 1) {
				$s .= $c . ' ';
			}
			$last = $c;
			$s .= '/' . $map[$c]['name'] . ' ';
		}
	}

	return rtrim($s);
}

function fpdf_make_unicode_array(array $map) : string
{
	// Build mapping to Unicode values
	$ranges = array();
	foreach ($map as $c => $v) {
		$uv = $v['uv'];
		if ($uv != -1) {
			if (isset($range)) {
				if ($c == $range[1] + 1 && $uv == $range[3] + 1) {
					$range[1]++;
					$range[3]++;
				} else {
					$ranges[] = $range;
					$range = array($c, $c, $uv, $uv);
				}
			} else {
				$range = array($c, $c, $uv, $uv);
			}
		}
	}
	$ranges[] = $range;

	foreach($ranges as $range) {
		if (isset($s)) {
			$s .= ',';
		} else {
			$s = 'array(';
		}
		$s .= $range[0] . '=>';
		$nb = $range[1]-$range[0]+1;
		if ($nb>1) {
			$s .= 'array(' . $range[2] . ',' . $nb . ')';
		} else {
			$s .= $range[2];
		}
	}
	$s .= ')';

	return $s;
}

function save_to_file(string $file_name, string $s, string $mode = '') : void
{
	$f = fopen($file_name, 'w' . $mode);
	if (!$f) {
		throw new FPDFException('Can\'t write to file ' . $file_name);
	}
	fwrite($f, $s);
	fclose($f);
}

function fpdf_make_definition_file(string $file_name, string $type, string $enc, bool $embed, bool $subset, array $map, array $info) : void
{
	$s = "<?php\n";
	$s .= '$type = \'' . $type . "';\n";
	$s .= '$name = \'' . $info['FontName'] . "';\n";
	$s .= '$desc = ' . fpdf_make_font_descriptor($info) . ";\n";
	$s .= '$up = ' . $info['UnderlinePosition'] . ";\n";
	$s .= '$ut = ' . $info['UnderlineThickness'] . ";\n";
	$s .= '$cw = ' . MakeWidthArray($info['Widths']) . ";\n";
	$s .= '$enc = \'' . $enc . "';\n";
	$diff = fpdf_make_font_encoding($map);
	if ($diff) {
		$s .= '$diff = \'' . $diff . "';\n";
	}
	$s .= '$uv = ' . fpdf_make_unicode_array($map) . ";\n";
	if ($embed) {
		$s .= '$file = \'' . $info['File'] . "';\n";
		if ($type === 'Type1') {
			$s .= '$size1 = ' . $info['Size1'] . ";\n";
			$s .= '$size2 = ' . $info['Size2'] . ";\n";
		} else {
			$s .= '$originalsize = ' . $info['OriginalSize'] . ";\n";
			if ($subset) {
				$s .= "\$subsetted = true;\n";
			}
		}
	}
	$s .= "?>\n";
	save_to_file($file_name, $s, 't');
}

function fpdf_make_font($fontfile, string $enc = 'cp1252', bool $embed = true, bool $subset = true) : void
{
	// Generate a font definition file
	if (!file_exists($fontfile)) {
		throw new FPDFException('Font file not found: ' . $fontfile);
	}
	$ext = strtolower(substr($fontfile,-3));
	if ($ext === 'ttf' || $ext === 'otf') {
		$type = 'TrueType';
	} elseif ($ext === 'pfb') {
		$type = 'Type1';
	} else {
		throw new FPDFException('Unrecognized font file extension: ' . $ext);
	}

	$map = fpdf_load_map($enc);

	if ($type === 'TrueType') {
		$info = fpdf_get_info_from_true_type($fontfile, $embed, $subset, $map);
	} else {
		$info = fpdf_get_info_from_type1($fontfile, $embed, $map);
	}

	$basename = substr(basename($fontfile), 0, -4);
	if ($embed) {
		if (function_exists('gzcompress')) {
			$file = $basename . '.z';
			save_to_file($file, gzcompress($info['Data']), 'b');
			$info['File'] = $file;
			fpdf_print_message('Font file compressed: ' . $file);
		} else {
			$info['File'] = basename($fontfile);
			$subset = false;
			fpdf_notice('Font file could not be compressed (zlib extension not available)');
		}
	}

	fpdf_make_definition_file($basename . '.php', $type, $enc, $embed, $subset, $map, $info);
	fpdf_print_message('Font definition file generated: ' . $basename . '.php');
}

if (PHP_SAPI === 'cli') {
	// Command-line interface
	ini_set('log_errors', '0');
	if ($argc === 1) {
		throw new FPDFException("Usage: php makefont.php fontfile [encoding] [embed] [subset]\n");
	}
	$fontfile = $argv[1];
	if ($argc >= 3) {
		$enc = $argv[2];
	} else {
		$enc = 'cp1252';
	}

	if ($argc >= 4) {
		$embed = ($argv[3] === 'true' || $argv[3] == '1');
	} else {
		$embed = true;
	}

	if ($argc >= 5) {
		$subset = ($argv[4] === 'true' || $argv[4] == '1');
	} else {
		$subset = true;
	}
	fpdf_make_font($fontfile, $enc, $embed, $subset);
}

?>
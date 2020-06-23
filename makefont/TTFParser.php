<?php

namespace fpdf;


/**
 * Class to parse and subset TrueType fonts
 *
 * @author Olivier Plathey, Jonathan Stoll
 * @version 2.0.0
 */
final class TTFParser
{
	/* @var resource Stream to font file reader */
	private $font_file;

	/* @var array */
	private $tables;

	/* @var int */
	private $number_of_HMetrics;

	/* @var int */
	private $num_glyphs;

	/* @var bool */
	private $glyph_names;

	/* @var int */
	private $index_to_loc_format;

	/* @var array */
	private $subsetted_chars;

	/* @var array */
	private $subsetted_glyphs;

	/* @var int[] */
	public $chars;

	/* @var array */
	public $glyphs;

	/* @var int */
	public $units_per_em;

	/* @var int */
	public $xMin;

	/* @var int */
	public $yMin;

	/* @var int */
	public $xMax;

	/* @var int */
	public $yMax;

	/* @var string */
	public $post_script_name;

	/* @var bool */
	public $embeddable;

	/* @var bool */
	public $bold;

	/* @var int */
	public $typo_ascender;

	/* @var int */
	public $typo_descender;

	/* @var int */
	public $cap_height;

	/* @var int */
	public $italic_angle;

	/* @var int */
	public $underline_position;

	/* @var int */
	public $underline_thickness;

	/* @var bool */
	public $is_fixed_pitch;


	public function __construct(string $file_name)
	{
		$this->font_file = fopen($file_name, 'rb');
		if (!$this->font_file) {
			throw new FPDFException('Could not read font file.');
		}
	}

	public function __destruct()
	{
		if (is_resource($this->font_file)) {
			fclose($this->font_file);
		}
	}

	public function parse() : void
	{
		$this->parseOffsetTable();
		$this->parseHead();
		$this->parseHhea();
		$this->parseMaxp();
		$this->parseHmtx();
		$this->parseLoca();
		$this->parseGlyf();
		$this->parseCmap();
		$this->parseName();
		$this->parseOS2();
		$this->parsePost();
	}

	private function parseOffsetTable() : void
	{
		$version = $this->read(4);
		if ($version === 'OTTO') {
			throw new FPDFException('OpenType fonts based on PostScript outlines are not supported');
		}
		if ($version !== "\x00\x01\x00\x00") {
			throw new FPDFException('Unrecognized file format');
		}

		$numTables = $this->readUShort();
		$this->skip(3 * 2); // searchRange, entrySelector, rangeShift
		$this->tables = array();
		for ($i = 0; $i < $numTables; $i++) {
			$tag = $this->read(4);
			$this->tables[$tag] = array(
				'checkSum'	=> $this->read(4),
				'offset'	=> $this->readULong(),
				'length'	=> $this->readULong()
			);
		}
	}

	private function parseHead() : void
	{
		$this->seek('head');
		$this->skip(3 * 4); // version, fontRevision, checkSumAdjustment
		$magicNumber = $this->readULong();
		if ($magicNumber != 0x5F0F3CF5) {
			throw new FPDFException('Incorrect magic number');
		}
		$this->skip(2); // flags
		$this->units_per_em = $this->readUShort();
		$this->skip(2 * 8); // created, modified
		$this->xMin = $this->readShort();
		$this->yMin = $this->readShort();
		$this->xMax = $this->readShort();
		$this->yMax = $this->readShort();
		$this->skip(3 * 2); // macStyle, lowestRecPPEM, fontDirectionHint
		$this->index_to_loc_format = $this->readShort();
	}

	private function parseHhea() : void
	{
		$this->seek('hhea');
		$this->skip(4 + 15 * 2);
		$this->number_of_HMetrics = $this->readUShort();
	}

	private function parseMaxp() : void
	{
		$this->seek('maxp');
		$this->skip(4);
		$this->num_glyphs = $this->readUShort();
	}

	private function parseHmtx() : void
	{
		$this->seek('hmtx');
		$this->glyphs = array();
		for ($i = 0; $i < $this->number_of_HMetrics; $i++) {
			$advanceWidth = $this->readUShort();
			$lsb = $this->readShort();
			$this->glyphs[$i] = array('w' => $advanceWidth, 'lsb' => $lsb);
		}
		for ($i = $this->number_of_HMetrics; $i < $this->num_glyphs; $i++) {
			$lsb = $this->readShort();
			$this->glyphs[$i] = array('w' => $advanceWidth, 'lsb' => $lsb);
		}
	}

	private function parseLoca() : void
	{
		$this->seek('loca');
		$offsets = array();
		if ($this->index_to_loc_format === 0) {
			// Short format
			for ($i = 0; $i <= $this->num_glyphs; $i++) {
				$offsets[] = 2 * $this->readUShort();
			}
		} else {
			// Long format
			for ($i = 0; $i <= $this->num_glyphs; $i++) {
				$offsets[] = $this->readULong();
			}
		}
		for ($i = 0; $i < $this->num_glyphs; $i++) {
			$this->glyphs[$i]['offset'] = $offsets[$i];
			$this->glyphs[$i]['length'] = $offsets[$i + 1] - $offsets[$i];
		}
	}

	private function parseGlyf() : void
	{
		$tableOffset = $this->tables['glyf']['offset'];
		foreach ($this->glyphs as &$glyph) {
			if ($glyph['length'] > 0) {
				fseek($this->font_file, $tableOffset + $glyph['offset'], SEEK_SET);
				if ($this->readShort() < 0) {
					// Composite glyph
					$this->skip(4 * 2); // xMin, yMin, xMax, yMax
					$offset = 5 * 2;
					$a = array();
					do {
						$flags = $this->readUShort();
						$index = $this->readUShort();
						$a[$offset + 2] = $index;

						if ($flags & 1) { // ARG_1_AND_2_ARE_WORDS
							$skip = 2 * 2;
						} else {
							$skip = 2;
						}

						if ($flags & 8) { // WE_HAVE_A_SCALE
							$skip += 2;
						} elseif ($flags & 64) { // WE_HAVE_AN_X_AND_Y_SCALE
							$skip += 2 * 2;
						} elseif ($flags & 128) { // WE_HAVE_A_TWO_BY_TWO
							$skip += 4 * 2;
						}

						$this->skip($skip);
						$offset += 2 * 2 + $skip;
					} while ($flags & 32); // MORE_COMPONENTS

					$glyph['components'] = $a;
				}
			}
		}
	}

	private function parseCmap() : void
	{
		$this->seek('cmap');
		$this->skip(2); // version
		$numTables = $this->readUShort();
		$offset31 = 0;
		for ($i = 0; $i < $numTables; $i++) {
			$platformID = $this->readUShort();
			$encodingID = $this->readUShort();
			$offset = $this->readULong();
			if ($platformID == 3 && $encodingID == 1) {
				$offset31 = $offset;
			}
		}
		if ($offset31 == 0) {
			throw new FPDFException('No Unicode encoding found');
		}

		$startCount = array();
		$endCount = array();
		$idDelta = array();
		$idRangeOffset = array();
		$this->chars = array();
		fseek($this->font_file, $this->tables['cmap']['offset'] + $offset31, SEEK_SET);
		$format = $this->readUShort();
		if ($format != 4) {
			throw new FPDFException('Unexpected subtable format: ' . $format);
		}
		$this->skip(2 * 2); // length, language
		$segCount = $this->readUShort() / 2;
		$this->skip(3 * 2); // searchRange, entrySelector, rangeShift
		for ($i = 0; $i < $segCount; $i++) {
			$endCount[$i] = $this->readUShort();
		}
		$this->skip(2); // reservedPad
		for ($i = 0; $i < $segCount; $i++) {
			$startCount[$i] = $this->readUShort();
		}
		for ($i = 0; $i < $segCount; $i++) {
			$idDelta[$i] = $this->readShort();
		}
		$offset = ftell($this->font_file);
		for ($i = 0; $i < $segCount; $i++) {
			$idRangeOffset[$i] = $this->readUShort();
		}

		for ($i = 0; $i < $segCount; $i++) {
			$c1 = $startCount[$i];
			$c2 = $endCount[$i];
			$d = $idDelta[$i];
			$ro = $idRangeOffset[$i];
			if ($ro > 0) {
				fseek($this->font_file, $offset + 2 * $i + $ro, SEEK_SET);
			}
			for ($c = $c1; $c <= $c2; $c++) {
				if ($c == 0xFFFF) {
					break;
				}

				if ($ro > 0) {
					$gid = $this->readUShort();
					if ($gid > 0) {
						$gid += $d;
					}
				} else {
					$gid = $c + $d;
				}

				if ($gid >= 65536) {
					$gid -= 65536;
				}

				if ($gid > 0) {
					$this->chars[$c] = $gid;
				}
			}
		}
	}

	private function parseName() : void
	{
		$this->seek('name');
		$tableOffset = $this->tables['name']['offset'];
		$this->post_script_name = '';
		$this->skip(2); // format
		$count = $this->readUShort();
		$stringOffset = $this->readUShort();
		for ($i = 0; $i < $count; $i++) {
			$this->skip(3 * 2); // platformID, encodingID, languageID
			$nameID = $this->readUShort();
			$length = $this->readUShort();
			$offset = $this->readUShort();
			if ($nameID == 6) {
				// PostScript name
				fseek($this->font_file, $tableOffset + $stringOffset + $offset, SEEK_SET);
				$s = $this->read($length);
				$s = str_replace(chr(0), '', $s);
				$s = preg_replace('|[ \[\](){}<>/%]|', '', $s);
				$this->post_script_name = $s;
				break;
			}
		}
		if ($this->post_script_name === '') {
			throw new FPDFException('PostScript name not found');
		}
	}

	private function parseOS2() : void
	{
		$this->seek('OS/2');
		$version = $this->readUShort();
		$this->skip(3 * 2); // xAvgCharWidth, usWeightClass, usWidthClass
		$fsType = $this->readUShort();
		$this->embeddable = ($fsType !== 2) && ($fsType & 0x200) === 0;
		$this->skip(11 * 2 + 10 + 4 * 4 + 4);
		$fsSelection = $this->readUShort();
		$this->bold = ($fsSelection & 32) !== 0;
		$this->skip(2 * 2); // usFirstCharIndex, usLastCharIndex
		$this->typo_ascender = $this->readShort();
		$this->typo_descender = $this->readShort();
		if ($version >= 2) {
			$this->skip(3 * 2 + 2 * 4 + 2);
			$this->cap_height = $this->readShort();
		} else {
			$this->cap_height = 0;
		}
	}

	private function parsePost() : void
	{
		$this->seek('post');
		$version = $this->readULong();
		$this->italic_angle = $this->readShort();
		$this->skip(2); // Skip decimal part
		$this->underline_position = $this->readShort();
		$this->underline_thickness = $this->readShort();
		$this->is_fixed_pitch = $this->readULong() !== 0;
		if ($version == 0x20000) {
			// Extract glyph names
			$this->skip(4 * 4); // min/max usage
			$this->skip(2); // numberOfGlyphs
			$glyphNameIndex = array();
			$names = array();
			$numNames = 0;
			for ($i = 0; $i < $this->num_glyphs; $i++) {
				$index = $this->readUShort();
				$glyphNameIndex[] = $index;
				if ($index >= 258 && $index - 257 > $numNames)
					$numNames = $index - 257;
			}
			for ($i = 0; $i < $numNames; $i++) {
				$len = ord($this->read(1));
				$names[] = $this->read($len);
			}
			foreach ($glyphNameIndex as $i => $index) {
				if ($index >= 258) {
					$this->glyphs[$i]['name'] = $names[$index - 258];
				} else {
					$this->glyphs[$i]['name'] = $index;
				}
			}
			$this->glyph_names = true;
		} else {
			$this->glyph_names = false;
		}
	}

	public function subset($chars) : void
	{
		/*		$chars = array_keys($this->chars);
				$this->subsettedChars = $chars;
				$this->subsettedGlyphs = array();
				for ($i = 0; $i < $this->numGlyphs; $i++) {
					$this->subsettedGlyphs[] = $i;
					$this->glyphs[$i]['ssid'] = $i;
				}*/

		$this->addGlyph(0);

		$this->subsetted_chars = array();
		foreach ($chars as $char) {
			if (isset($this->chars[$char])) {
				$this->subsetted_chars[] = $char;
				$this->addGlyph($this->chars[$char]);
			}
		}
	}

	private function addGlyph($id) : void
	{
		if (!isset($this->glyphs[$id]['ssid'])) {
			$this->glyphs[$id]['ssid'] = count($this->subsetted_glyphs);
			$this->subsetted_glyphs[] = $id;
			if (isset($this->glyphs[$id]['components'])) {
				foreach ($this->glyphs[$id]['components'] as $cid) {
					$this->addGlyph($cid);
				}
			}
		}
	}

	public function build() : string
	{
		$this->buildCmap();
		$this->buildHhea();
		$this->buildHmtx();
		$this->buildLoca();
		$this->buildGlyf();
		$this->buildMaxp();
		$this->buildPost();

		return $this->buildFont();
	}

	private function buildCmap() : void
	{
		if (!isset($this->subsetted_chars)) {
			return;
		}

		// Divide charset in contiguous segments
		$chars = $this->subsetted_chars;
		sort($chars);
		$segments = array();
		$segment = array($chars[0], $chars[0]);

		$num_of_chars = count($chars);
		for ($i = 1; $i < $num_of_chars; $i++) {
			if ($chars[$i] > $segment[1] + 1) {
				$segments[] = $segment;
				$segment = array($chars[$i], $chars[$i]);
			} else {
				$segment[1]++;
			}
		}
		$segments[] = $segment;
		$segments[] = array(0xFFFF, 0xFFFF);
		$num_of_segments = count($segments);

		// Build a Format 4 subtable
		$start_count = array();
		$end_count = array();
		$id_delta = array();
		$id_range_offset = array();
		$glyph_id_array = '';

		for ($i = 0; $i < $num_of_segments; $i++) {
			list($start, $end) = $segments[$i];
			$start_count[] = $start;
			$end_count[] = $end;
			if ($start != $end) {
				// Segment with multiple chars
				$id_delta[] = 0;
				$id_range_offset[] = strlen($glyph_id_array) + ($num_of_segments - $i) * 2;
				for ($c = $start; $c <= $end; $c++) {
					$ssid = $this->glyphs[$this->chars[$c]]['ssid'];
					$glyph_id_array .= pack('n', $ssid);
				}
			} else {
				// Segment with a single char
				if ($start < 0xFFFF) {
					$ssid = $this->glyphs[$this->chars[$start]]['ssid'];
				} else {
					$ssid = 0;
				}
				$id_delta[] = $ssid - $start;
				$id_range_offset[] = 0;
			}
		}

		$entrySelector = 0;
		$n = $num_of_segments;
		while ($n != 1) {
			$n = $n >> 1;
			$entrySelector++;
		}
		$searchRange = (1 << $entrySelector) * 2;
		$rangeShift = 2 * $num_of_segments - $searchRange;
		$cmap = pack('nnnn', 2 * $num_of_segments, $searchRange, $entrySelector, $rangeShift);
		foreach ($end_count as $val) {
			$cmap .= pack('n', $val);
		}
		$cmap .= pack('n', 0); // reservedPad
		foreach ($start_count as $val) {
			$cmap .= pack('n', $val);
		}
		foreach ($id_delta as $val) {
			$cmap .= pack('n', $val);
		}
		foreach ($id_range_offset as $val) {
			$cmap .= pack('n', $val);
		}
		$cmap .= $glyph_id_array;

		$data = pack('nn', 0, 1); // version, numTables
		$data .= pack('nnN', 3, 1, 12); // platformID, encodingID, offset
		$data .= pack('nnn', 4, 6 + strlen($cmap), 0); // format, length, language
		$data .= $cmap;
		$this->setTable('cmap', $data);
	}

	private function buildHhea() : void
	{
		$this->loadTable('hhea');
		$numberOfHMetrics = count($this->subsetted_glyphs);
		$data = substr_replace($this->tables['hhea']['data'], pack('n', $numberOfHMetrics), 4 + 15 * 2, 2);
		$this->setTable('hhea', $data);
	}

	private function buildHmtx() : void
	{
		$data = '';
		foreach ($this->subsetted_glyphs as $id) {
			$glyph = $this->glyphs[$id];
			$data .= pack('nn', $glyph['w'], $glyph['lsb']);
		}
		$this->setTable('hmtx', $data);
	}

	private function buildLoca() : void
	{
		$data = '';
		$offset = 0;

		foreach ($this->subsetted_glyphs as $id) {
			if ($this->index_to_loc_format === 0) {
				$data .= pack('n', $offset / 2);
			} else {
				$data .= pack('N', $offset);
			}
			$offset += $this->glyphs[$id]['length'];
		}

		if ($this->index_to_loc_format === 0) {
			$data .= pack('n', $offset / 2);
		} else {
			$data .= pack('N', $offset);
		}

		$this->setTable('loca', $data);
	}

	private function buildGlyf() : void
	{
		$tableOffset = $this->tables['glyf']['offset'];
		$data = '';
		foreach ($this->subsetted_glyphs as $id) {
			$glyph = $this->glyphs[$id];
			fseek($this->font_file, $tableOffset + $glyph['offset'], SEEK_SET);
			$glyph_data = $this->read($glyph['length']);
			if (isset($glyph['components'])) {
				// Composite glyph
				foreach ($glyph['components'] as $offset => $cid) {
					$ssid = $this->glyphs[$cid]['ssid'];
					$glyph_data = substr_replace($glyph_data, pack('n', $ssid), $offset, 2);
				}
			}
			$data .= $glyph_data;
		}
		$this->setTable('glyf', $data);
	}

	private function buildMaxp() : void
	{
		$this->loadTable('maxp');
		$numGlyphs = count($this->subsetted_glyphs);
		$data = substr_replace($this->tables['maxp']['data'], pack('n', $numGlyphs), 4, 2);
		$this->setTable('maxp', $data);
	}

	private function buildPost() : void
	{
		$this->seek('post');
		if ($this->glyph_names) {
			// Version 2.0
			$numberOfGlyphs = count($this->subsetted_glyphs);
			$numNames = 0;
			$names = '';
			$data = $this->read(2 * 4 + 2 * 2 + 5 * 4);
			$data .= pack('n', $numberOfGlyphs);
			foreach ($this->subsetted_glyphs as $id) {
				$name = $this->glyphs[$id]['name'];
				if (is_string($name)) {
					$data .= pack('n', 258 + $numNames);
					$names .= chr(strlen($name)) . $name;
					$numNames++;
				} else
					$data .= pack('n', $name);
			}
			$data .= $names;
		} else {
			// Version 3.0
			$this->skip(4);
			$data = "\x00\x03\x00\x00";
			$data .= $this->read(4 + 2 * 2 + 5 * 4);
		}
		$this->setTable('post', $data);
	}

	private function buildFont() : string
	{
		$tags = array();
		foreach (array('cmap', 'cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'post', 'prep') as $tag) {
			if (isset($this->tables[$tag])) {
				$tags[] = $tag;
			}
		}
		$numTables = count($tags);
		$offset = 12 + 16 * $numTables;
		foreach ($tags as $tag) {
			if (!isset($this->tables[$tag]['data'])) {
				$this->loadTable($tag);
			}
			$this->tables[$tag]['offset'] = $offset;
			$offset += strlen($this->tables[$tag]['data']);
		}
//		$this->tables['head']['data'] = substr_replace($this->tables['head']['data'], "\x00\x00\x00\x00", 8, 4);

		// Build offset table
		$entrySelector = 0;
		$n = $numTables;
		while ($n != 1) {
			$n = $n >> 1;
			$entrySelector++;
		}
		$searchRange = 16 * (1 << $entrySelector);
		$rangeShift = 16 * $numTables - $searchRange;
		$offsetTable = pack('nnnnnn', 1, 0, $numTables, $searchRange, $entrySelector, $rangeShift);
		foreach ($tags as $tag) {
			$table = $this->tables[$tag];
			$offsetTable .= $tag . $table['checkSum'] . pack('NN', $table['offset'], $table['length']);
		}

		// Compute checkSumAdjustment (0xB1B0AFBA - font checkSum)
		$s = $this->checkSum($offsetTable);
		foreach ($tags as $tag) {
			$s .= $this->tables[$tag]['checkSum'];
		}
		$a = unpack('n2', $this->checkSum($s));
		$high = 0xB1B0 + ($a[1] ^ 0xFFFF);
		$low = 0xAFBA + ($a[2] ^ 0xFFFF) + 1;
		$checkSumAdjustment = pack('nn', $high + ($low >> 16), $low);
		$this->tables['head']['data'] = substr_replace($this->tables['head']['data'], $checkSumAdjustment, 8, 4);

		$font = $offsetTable;
		foreach ($tags as $tag) {
			$font .= $this->tables[$tag]['data'];
		}

		return $font;
	}

	private function loadTable($tag) : void
	{
		$this->seek($tag);
		$length = $this->tables[$tag]['length'];
		$n = $length % 4;
		if ($n > 0) {
			$length += 4 - $n;
		}
		$this->tables[$tag]['data'] = $this->read($length);
	}

	private function setTable($tag, $data) : void
	{
		$length = strlen($data);
		$n = $length % 4;
		if ($n > 0) {
			$data = str_pad($data, $length + 4 - $n, "\x00");
		}
		$this->tables[$tag]['data'] = $data;
		$this->tables[$tag]['length'] = $length;
		$this->tables[$tag]['checkSum'] = $this->checkSum($data);
	}

	private function seek($tag) : void
	{
		if (!isset($this->tables[$tag])) {
			throw new \InvalidArgumentException('Table not found for tag "' . $tag . '"');
		}
		fseek($this->font_file, $this->tables[$tag]['offset'], SEEK_SET);
	}

	private function skip(int $length) : void
	{
		fseek($this->font_file, $length, SEEK_CUR);
	}

	private function read(int $length) : string
	{
		return $length > 0 ? fread($this->font_file, $length) : '';
	}

	private function readUShort() : int
	{
		$a = unpack('nn', fread($this->font_file, 2));
		return $a['n'];
	}

	private function readShort() : int
	{
		$a = unpack('nn', fread($this->font_file, 2));
		$v = $a['n'];
		if ($v >= 0x8000) {
			$v -= 65536;
		}
		return $v;
	}

	private function readULong() : int
	{
		$a = unpack('NN', fread($this->font_file, 4));
		return $a['N'];
	}

	private function checkSum(string $str) : string
	{
		$n = strlen($str);
		$high = 0;
		$low = 0;

		for ($i = 0; $i < $n; $i += 4) {
			$high += (ord($str[$i]) << 8) + ord($str[$i + 1]);
			$low += (ord($str[$i + 2]) << 8) + ord($str[$i + 3]);
		}

		return pack('nn', $high + ($low >> 16), $low);
	}
}


?>
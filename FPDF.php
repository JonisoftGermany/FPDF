<?php

namespace fpdf;


/**
 * FPDF
 *
 * @author Olivier Plathey, Jonathan Stoll
 * @version 2.0.0
 */
class FPDF
{
	public const VERSION = '2.0.0';

	protected $page;               // current page number
	protected $n;                  // current object number
	protected $offsets;            // array of object offsets
	protected $buffer;             // buffer holding in-memory PDF
	protected $pages;              // array containing pages
	protected $state;              // current document state
	protected $compress;           // compression flag
	protected $k;                  // scale factor (number of points in user unit)
	protected $DefOrientation;     // default orientation
	protected $CurOrientation;     // current orientation
	protected $StdPageSizes;       // standard page sizes
	protected $DefPageSize;        // default page size
	protected $CurPageSize;        // current page size
	protected $CurRotation;        // current page rotation
	protected $PageInfo;           // page-related data
	protected $wPt, $hPt;          // dimensions of current page in points
	protected $w, $h;              // dimensions of current page in user unit
	protected $lMargin;            // left margin
	protected $tMargin;            // top margin
	protected $rMargin;            // right margin
	protected $bMargin;            // page break margin
	protected $cMargin;            // cell margin
	protected $x, $y;              // current position in user unit
	protected $lasth;              // height of last printed cell
	protected $LineWidth;          // line width in user unit
	protected $fontpath;           // path containing fonts
	protected $CoreFonts;          // array of core font names
	protected $fonts;              // array of used fonts
	protected $FontFiles;          // array of font files
	protected $encodings;          // array of encodings
	protected $cmaps;              // array of ToUnicode CMaps
	protected $FontFamily;         // current font family
	protected $FontStyle;          // current font style
	protected $underline;          // underlining flag
	protected $CurrentFont;        // current font info
	protected $FontSizePt;         // current font size in points
	protected $FontSize;           // current font size in user unit
	protected $DrawColor;          // commands for drawing color
	protected $FillColor;          // commands for filling color
	protected $TextColor;          // commands for text color
	protected $ColorFlag;          // indicates whether fill and text colors are different
	protected $WithAlpha;          // indicates whether alpha channel is used
	protected $ws;                 // word spacing
	protected $images;             // array of used images
	protected $PageLinks;          // array of links in pages
	protected $links;              // array of internal links
	protected $AutoPageBreak;      // automatic page breaking
	protected $PageBreakTrigger;   // threshold used to trigger page breaks
	protected $InHeader;           // flag set when processing header
	protected $InFooter;           // flag set when processing footer
	protected $AliasNbPages;       // alias for total number of pages
	protected $ZoomMode;           // zoom display mode
	protected $LayoutMode;         // layout display mode
	protected $metadata;           // document properties
	protected $PDFVersion;         // PDF version number

	/*******************************************************************************
	 *                               Public methods                                 *
	 *******************************************************************************/

	public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
	{
		// Some checks
		$this->dochecks();

		// Initialization of properties
		$this->state = 0;
		$this->page = 0;
		$this->n = 2;
		$this->buffer = '';
		$this->pages = array();
		$this->PageInfo = array();
		$this->fonts = array();
		$this->FontFiles = array();
		$this->encodings = array();
		$this->cmaps = array();
		$this->images = array();
		$this->links = array();
		$this->InHeader = false;
		$this->InFooter = false;
		$this->lasth = 0;
		$this->FontFamily = '';
		$this->FontStyle = '';
		$this->FontSizePt = 12;
		$this->underline = false;
		$this->DrawColor = '0 G';
		$this->FillColor = '0 g';
		$this->TextColor = '0 g';
		$this->ColorFlag = false;
		$this->WithAlpha = false;
		$this->ws = 0;

		// Font path
		if (defined('FPDF_FONTPATH')) {
			$this->fontpath = FPDF_FONTPATH;
			if (substr($this->fontpath, -1) !== '/' && substr($this->fontpath, -1) !== '\\') {
				$this->fontpath .= '/';
			}
		} elseif (is_dir(dirname(__FILE__) . '/font')) {
			$this->fontpath = dirname(__FILE__) . '/font/';
		} else {
			$this->fontpath = '';
		}

		// Core fonts
		$this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');

		// Scale factor
		if ($unit === 'pt') {
			$this->k = 1;
		} elseif ($unit === 'mm') {
			$this->k = 72 / 25.4;
		} elseif ($unit === 'cm') {
			$this->k = 72 / 2.54;
		} elseif ($unit === 'in') {
			$this->k = 72;
		} else {
			throw new FPDFException('Incorrect unit: ' . $unit);
		}

		// Page sizes
		$this->StdPageSizes = array(
			'a3' => array(841.89, 1190.55),
			'a4' => array(595.28, 841.89),
			'a5' => array(420.94, 595.28),
			'letter' => array(612, 792),
			'legal' => array(612, 1008)
		);
		$size = $this->getpagesize($size);
		$this->DefPageSize = $size;
		$this->CurPageSize = $size;

		// Page orientation
		$orientation = strtolower($orientation);
		if ($orientation == 'p' || $orientation == 'portrait') {
			$this->DefOrientation = 'P';
			$this->w = $size[0];
			$this->h = $size[1];
		} elseif ($orientation == 'l' || $orientation == 'landscape') {
			$this->DefOrientation = 'L';
			$this->w = $size[1];
			$this->h = $size[0];
		} else {
			throw new FPDFException('Incorrect orientation: ' . $orientation);
		}
		$this->CurOrientation = $this->DefOrientation;
		$this->wPt = $this->w * $this->k;
		$this->hPt = $this->h * $this->k;

		// Page rotation
		$this->CurRotation = 0;

		// Page margins (1 cm)
		$margin = 28.35 / $this->k;
		$this->SetMargins($margin, $margin);

		// Interior cell margin (1 mm)
		$this->cMargin = $margin / 10;

		// Line width (0.2 mm)
		$this->LineWidth = .567 / $this->k;

		// Automatic page break
		$this->SetAutoPageBreak(true, 2 * $margin);

		// Default display mode
		$this->SetDisplayMode('default');

		// Enable compression
		$this->SetCompression(true);

		// Set default PDF version number
		$this->PDFVersion = '1.3';
	}

	public function setMargins($left, $top, $right = null) : void
	{
		// Set left, top and right margins
		$this->lMargin = $left;
		$this->tMargin = $top;
		if ($right === null) {
			$right = $left;
		}
		$this->rMargin = $right;
	}

	public function setLeftMargin($margin) : void
	{
		// Set left margin
		$this->lMargin = $margin;
		if ($this->page > 0 && $this->x < $margin) {
			$this->x = $margin;
		}
	}

	public function setTopMargin($margin) : void
	{
		// Set top margin
		$this->tMargin = $margin;
	}

	public function setRightMargin($margin) : void
	{
		// Set right margin
		$this->rMargin = $margin;
	}

	public function setAutoPageBreak($auto, $margin = 0) : void
	{
		// Set auto page break mode and triggering margin
		$this->AutoPageBreak = $auto;
		$this->bMargin = $margin;
		$this->PageBreakTrigger = $this->h - $margin;
	}

	public function setDisplayMode($zoom, $layout = 'default') : void
	{
		// Set display mode in viewer
		if ($zoom === 'fullpage' || $zoom === 'fullwidth' || $zoom === 'real' || $zoom === 'default' || !is_string($zoom)) {
			$this->ZoomMode = $zoom;
		} else {
			throw new FPDFException('Incorrect zoom display mode: ' . $zoom);
		}

		if ($layout === 'single' || $layout === 'continuous' || $layout === 'two' || $layout === 'default') {
			$this->LayoutMode = $layout;
		} else {
			throw new FPDFException('Incorrect layout display mode: ' . $layout);
		}
	}

	public function setCompression($compress) : void
	{
		// Set page compression
		if (function_exists('gzcompress')) {
			$this->compress = $compress;
		} else {
			$this->compress = false;
		}
	}

	public function setTitle($title, $isUTF8 = false) : void
	{
		// Title of document
		$this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
	}

	public function setAuthor($author, $isUTF8 = false) : void
	{
		// Author of document
		$this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
	}

	public function setSubject($subject, $isUTF8 = false) : void
	{
		// Subject of document
		$this->metadata['Subject'] = $isUTF8 ? $subject : utf8_encode($subject);
	}

	public function setKeywords($keywords, $isUTF8 = false) : void
	{
		// Keywords of document
		$this->metadata['Keywords'] = $isUTF8 ? $keywords : utf8_encode($keywords);
	}

	public function setCreator($creator, $isUTF8 = false) : void
	{
		// Creator of document
		$this->metadata['Creator'] = $isUTF8 ? $creator : utf8_encode($creator);
	}

	public function aliasNbPages($alias = '{nb}') : void
	{
		// Define an alias for total number of pages
		$this->AliasNbPages = $alias;
	}

	public function close() : void
	{
		// Terminate document
		if ($this->state == 3) {
			return;
		}
		if ($this->page == 0) {
			$this->AddPage();
		}

		// Page footer
		$this->InFooter = true;
		$this->footer();
		$this->InFooter = false;

		// Close page
		$this->endpage();

		// Close document
		$this->enddoc();
	}

	public function addPage($orientation = '', $size = '', $rotation = 0) : void
	{
		// Start a new page
		if ($this->state === 3) {
			throw new FPDFException('The document is closed');
		}
		$family = $this->FontFamily;
		$style = $this->FontStyle . ($this->underline ? 'U' : '');
		$fontsize = $this->FontSizePt;
		$lw = $this->LineWidth;
		$dc = $this->DrawColor;
		$fc = $this->FillColor;
		$tc = $this->TextColor;
		$cf = $this->ColorFlag;
		if ($this->page > 0) {
			// Page footer
			$this->InFooter = true;
			$this->footer();
			$this->InFooter = false;
			// Close page
			$this->endpage();
		}

		// Start new page
		$this->beginpage($orientation, $size, $rotation);

		// Set line cap style to square
		$this->out('2 J');

		// Set line width
		$this->LineWidth = $lw;
		$this->out(sprintf('%.2F w', $lw * $this->k));

		// Set font
		if ($family) {
			$this->setFont($family, $style, $fontsize);
		}

		// Set colors
		$this->DrawColor = $dc;
		if ($dc !== '0 G') {
			$this->out($dc);
		}
		$this->FillColor = $fc;
		if ($fc !== '0 g') {
			$this->out($fc);
		}
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;

		// Page header
		$this->InHeader = true;
		$this->header();
		$this->InHeader = false;

		// Restore line width
		if ($this->LineWidth !== $lw) {
			$this->LineWidth = $lw;
			$this->out(sprintf('%.2F w', $lw * $this->k));
		}
		// Restore font
		if ($family) {
			$this->setFont($family, $style, $fontsize);
		}
		// Restore colors
		if ($this->DrawColor !== $dc) {
			$this->DrawColor = $dc;
			$this->out($dc);
		}
		if ($this->FillColor !== $fc) {
			$this->FillColor = $fc;
			$this->out($fc);
		}
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;
	}

	public function header() : void
	{
		// To be implemented in your own inherited class
	}

	public function footer() : void
	{
		// To be implemented in your own inherited class
	}

	public function pageNo() : int
	{
		// Get current page number
		return $this->page;
	}

	public function setDrawColor($r, $g = null, $b = null) : void
	{
		// Set color for all stroking operations
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->DrawColor = sprintf('%.3F G', $r / 255);
		} else {
			$this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
		}

		if ($this->page > 0) {
			$this->out($this->DrawColor);
		}
	}

	public function setFillColor($r, $g = null, $b = null) : void
	{
		// Set color for all filling operations
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->FillColor = sprintf('%.3F g', $r / 255);
		} else {
			$this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
		}

		$this->ColorFlag = $this->FillColor !== $this->TextColor;

		if ($this->page > 0) {
			$this->out($this->FillColor);
		}
	}

	function setTextColor($r, $g = null, $b = null)
	{
		// Set color for text
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->TextColor = sprintf('%.3F g', $r / 255);
		} else {
			$this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
		}

		$this->ColorFlag = $this->FillColor !== $this->TextColor;
	}

	public function getStringWidth($s) : float
	{
		// Get width of a string in the current font
		$s = (string)$s;
		$cw = &$this->CurrentFont['cw'];
		$w = 0;
		$l = strlen($s);
		for ($i = 0; $i < $l; $i++) {
			$w += $cw[$s[$i]];
		}

		return $w * $this->FontSize / 1000;
	}

	public function setLineWidth($width) : void
	{
		// Set line width
		$this->LineWidth = $width;
		if ($this->page > 0) {
			$this->out(sprintf('%.2F w', $width * $this->k));
		}
	}

	public function line($x1, $y1, $x2, $y2) : void
	{
		// Draw a line
		$this->out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
	}

	public function rect($x, $y, $w, $h, $style = '') : void
	{
		// Draw a rectangle
		if ($style === 'F') {
			$op = 'f';
		} elseif ($style === 'FD' || $style === 'DF') {
			$op = 'B';
		} else {
			$op = 'S';
		}

		$this->out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
	}

	public function addFont($family, $style = '', $file = '') : void
	{
		// Add a TrueType, OpenType or Type1 font
		$family = strtolower($family);
		if ($file === '') {
			$file = str_replace(' ', '', $family) . strtolower($style) . '.php';
		}
		$style = strtoupper($style);
		if ($style === 'IB') {
			$style = 'BI';
		}
		$fontkey = $family . $style;
		if (isset($this->fonts[$fontkey])) {
			return;
		}
		$info = $this->loadfont($file);
		$info['i'] = count($this->fonts) + 1;
		if (!empty($info['file'])) {
			// Embedded font
			if ($info['type'] === 'TrueType') {
				$this->FontFiles[$info['file']] = array('length1' => $info['originalsize']);
			} else {
				$this->FontFiles[$info['file']] = array('length1' => $info['size1'], 'length2' => $info['size2']);
			}
		}
		$this->fonts[$fontkey] = $info;
	}

	public function setFont($family, $style = '', $size = 0) : void
	{
		// Select a font; size given in points
		if ($family === '') {
			$family = $this->FontFamily;
		} else {
			$family = strtolower($family);
		}

		$style = strtoupper($style);

		if (strpos($style, 'U') !== false) {
			$this->underline = true;
			$style = str_replace('U', '', $style);
		} else {
			$this->underline = false;
		}

		if ($style === 'IB') {
			$style = 'BI';
		}
		if ($size === 0) {
			$size = $this->FontSizePt;
		}

		// Test if font is already selected
		if ($this->FontFamily === $family && $this->FontStyle === $style && $this->FontSizePt === $size) {
			return;
		}

		// Test if font is already loaded
		$fontkey = $family . $style;
		if (!isset($this->fonts[$fontkey])) {
			// Test if one of the core fonts
			if ($family === 'arial') {
				$family = 'helvetica';
			}
			if (in_array($family, $this->CoreFonts)) {
				if ($family === 'symbol' || $family === 'zapfdingbats') {
					$style = '';
				}
				$fontkey = $family . $style;
				if (!isset($this->fonts[$fontkey])) {
					$this->addFont($family, $style);
				}
			} else {
				throw new FPDFException('Undefined font: ' . $family . ' ' . $style);
			}
		}
		// Select it
		$this->FontFamily = $family;
		$this->FontStyle = $style;
		$this->FontSizePt = $size;
		$this->FontSize = $size / $this->k;
		$this->CurrentFont = &$this->fonts[$fontkey];
		if ($this->page > 0) {
			$this->out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
		}
	}

	public function setFontSize($size) : void
	{
		// Set font size in points
		if ($this->FontSizePt === $size) {
			return;
		}
		$this->FontSizePt = $size;
		$this->FontSize = $size / $this->k;
		if ($this->page > 0) {
			$this->out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
		}
	}

	public function addLink() : int
	{
		// Create a new internal link
		$n = count($this->links) + 1;
		$this->links[$n] = array(0, 0);
		return $n;
	}

	public function setLink($link, $y = 0, $page = -1) : void
	{
		// Set destination of internal link
		if ($y === -1) {
			$y = $this->y;
		}
		if ($page === -1) {
			$page = $this->page;
		}
		$this->links[$link] = array($page, $y);
	}

	public function link($x, $y, $w, $h, $link) : void
	{
		// Put a link on the page
		$this->PageLinks[$this->page][] = array($x * $this->k, $this->hPt - $y * $this->k, $w * $this->k, $h * $this->k, $link);
	}

	public function text($x, $y, $txt) : void
	{
		// Output a string
		if (!isset($this->CurrentFont)) {
			throw new FPDFException('No font has been set');
		}
		$s = sprintf('BT %.2F %.2F Td (%s) Tj ET', $x * $this->k, ($this->h - $y) * $this->k, $this->escape($txt));
		if ($this->underline && $txt !== '') {
			$s .= ' ' . $this->dounderline($x, $y, $txt);
		}
		if ($this->ColorFlag) {
			$s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
		}
		$this->out($s);
	}

	public function acceptPageBreak()
	{
		// Accept automatic page break or not
		return $this->AutoPageBreak;
	}

	public function cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
	{
		// Output a cell
		$k = $this->k;
		if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->acceptPageBreak()) {
			// Automatic page break
			$x = $this->x;
			$ws = $this->ws;
			if ($ws > 0) {
				$this->ws = 0;
				$this->out('0 Tw');
			}
			$this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
			$this->x = $x;
			if ($ws > 0) {
				$this->ws = $ws;
				$this->out(sprintf('%.3F Tw', $ws * $k));
			}
		}
		if ($w === 0) {
			$w = $this->w - $this->rMargin - $this->x;
		}
		$s = '';
		if ($fill || $border === 1) {
			if ($fill) {
				$op = ($border === 1) ? 'B' : 'f';
			} else {
				$op = 'S';
			}

			$s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
		}

		if (is_string($border)) {
			$x = $this->x;
			$y = $this->y;
			if (strpos($border, 'L') !== false) {
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
			}
			if (strpos($border, 'T') !== false) {
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
			}
			if (strpos($border, 'R') !== false) {
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
			}
			if (strpos($border, 'B') !== false) {
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
			}
		}

		if ($txt !== '') {
			if (!isset($this->CurrentFont)) {
				throw new FPDFException('No font has been set');
			}
			if ($align === 'R') {
				$dx = $w - $this->cMargin - $this->getStringWidth($txt);
			} elseif ($align === 'C') {
				$dx = ($w - $this->getStringWidth($txt)) / 2;
			} else {
				$dx = $this->cMargin;
			}

			if ($this->ColorFlag) {
				$s .= 'q ' . $this->TextColor . ' ';
			}
			$s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $k, $this->escape($txt));
			if ($this->underline) {
				$s .= ' ' . $this->dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);
			}
			if ($this->ColorFlag) {
				$s .= ' Q';
			}
			if ($link) {
				$this->link($this->x + $dx, $this->y + .5 * $h - .5 * $this->FontSize, $this->getStringWidth($txt), $this->FontSize, $link);
			}
		}

		if ($s) {
			$this->out($s);
		}

		$this->lasth = $h;

		if ($ln > 0) {
			// Go to next line
			$this->y += $h;
			if ($ln === 1) {
				$this->x = $this->lMargin;
			}
		} else {
			$this->x += $w;
		}
	}

	public function multiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) : void
	{
		// Output text with automatic or explicit line breaks
		if (!isset($this->CurrentFont)) {
			throw new FPDFException('No font has been set');
		}

		$cw = &$this->CurrentFont['cw'];

		if ($w === 0) {
			$w = $this->w - $this->rMargin - $this->x;
		}
		$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
		$s = str_replace("\r", '', $txt);
		$nb = strlen($s);
		if ($nb > 0 && $s[$nb - 1] === "\n") {
			$nb--;
		}
		$b = 0;
		if ($border) {
			if ($border === 1) {
				$border = 'LTRB';
				$b = 'LRT';
				$b2 = 'LR';
			} else {
				$b2 = '';
				if (strpos($border, 'L') !== false) {
					$b2 .= 'L';
				}
				if (strpos($border, 'R') !== false) {
					$b2 .= 'R';
				}
				$b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
			}
		}
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$ns = 0;
		$nl = 1;
		while ($i < $nb) {
			// Get next character
			$c = $s[$i];
			if ($c === "\n") {
				// Explicit line break
				if ($this->ws > 0) {
					$this->ws = 0;
					$this->out('0 Tw');
				}
				$this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if ($border && $nl == 2) {
					$b = $b2;
				}
				continue;
			}

			if ($c === ' ') {
				$sep = $i;
				$ls = $l;
				$ns++;
			}

			$l += $cw[$c];
			if ($l > $wmax) {
				// Automatic line break
				if ($sep === -1) {
					if ($i === $j) {
						$i++;
					}
					if ($this->ws > 0) {
						$this->ws = 0;
						$this->out('0 Tw');
					}
					$this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
				} else {
					if ($align === 'J') {
						$this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
						$this->out(sprintf('%.3F Tw', $this->ws * $this->k));
					}
					$this->cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
					$i = $sep + 1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if ($border && $nl == 2) {
					$b = $b2;
				}
			} else {
				$i++;
			}
		}

		// Last chunk
		if ($this->ws > 0) {
			$this->ws = 0;
			$this->out('0 Tw');
		}

		if ($border && strpos($border, 'B') !== false) {
			$b .= 'B';
		}
		$this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
		$this->x = $this->lMargin;
	}

	public function write($h, $txt, $link = '') : void
	{
		// Output text in flowing mode
		if (!isset($this->CurrentFont)) {
			throw new FPDFException('No font has been set');
		}

		$cw = &$this->CurrentFont['cw'];
		$w = $this->w - $this->rMargin - $this->x;
		$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
		$s = str_replace("\r", '', $txt);
		$nb = strlen($s);
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;
		while ($i < $nb) {
			// Get next character
			$c = $s[$i];
			if ($c === "\n") {
				// Explicit line break
				$this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				if ($nl == 1) {
					$this->x = $this->lMargin;
					$w = $this->w - $this->rMargin - $this->x;
					$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
				}
				$nl++;
				continue;
			}
			if ($c == ' ')
				$sep = $i;
			$l += $cw[$c];
			if ($l > $wmax) {
				// Automatic line break
				if ($sep == -1) {
					if ($this->x > $this->lMargin) {
						// Move to next line
						$this->x = $this->lMargin;
						$this->y += $h;
						$w = $this->w - $this->rMargin - $this->x;
						$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
						$i++;
						$nl++;
						continue;
					}

					if ($i == $j) {
						$i++;
					}

					$this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
				} else {
					$this->cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', false, $link);
					$i = $sep + 1;
				}

				$sep = -1;
				$j = $i;
				$l = 0;
				if ($nl == 1) {
					$this->x = $this->lMargin;
					$w = $this->w - $this->rMargin - $this->x;
					$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
				}
				$nl++;
			} else {
				$i++;
			}
		}

		// Last chunk
		if ($i != $j) {
			$this->cell($l / 1000 * $this->FontSize, $h, substr($s, $j), 0, 0, '', false, $link);
		}
	}

	public function ln($h = null) : void
	{
		// Line feed; default value is the last cell height
		$this->x = $this->lMargin;
		if ($h === null) {
			$this->y += $this->lasth;
		} else {
			$this->y += $h;
		}
	}

	public function image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') : void
	{
		// Put an image on the page
		if ($file === '') {
			throw new FPDFException('Image file name is empty');
		}

		if (!isset($this->images[$file])) {
			// First use of this image, get info
			if ($type === '') {
				$pos = strrpos($file, '.');
				if (!$pos)
					throw new FPDFException('Image file has no extension and no type was specified: ' . $file);
				$type = substr($file, $pos + 1);
			}
			$type = strtolower($type);
			if ($type == 'jpeg') {
				$type = 'jpg';
			}
			$mtd = '_parse' . $type;
			if (!method_exists($this, $mtd)) {
				throw new FPDFException('Unsupported image type: ' . $type);
			}
			$info = $this->$mtd($file);
			$info['i'] = count($this->images) + 1;
			$this->images[$file] = $info;
		} else {
			$info = $this->images[$file];
		}

		// Automatic width and height calculation if needed
		if ($w == 0 && $h == 0) {
			// Put image at 96 dpi
			$w = -96;
			$h = -96;
		}
		if ($w < 0) {
			$w = -$info['w'] * 72 / $w / $this->k;
		}
		if ($h < 0) {
			$h = -$info['h'] * 72 / $h / $this->k;
		}
		if ($w == 0) {
			$w = $h * $info['w'] / $info['h'];
		}
		if ($h == 0) {
			$h = $w * $info['h'] / $info['w'];
		}

		// Flowing mode
		if ($y === null) {
			if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->acceptPageBreak()) {
				// Automatic page break
				$x2 = $this->x;
				$this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
				$this->x = $x2;
			}
			$y = $this->y;
			$this->y += $h;
		}

		if ($x === null) {
			$x = $this->x;
		}
		$this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));
		if ($link) {
			$this->link($x, $y, $w, $h, $link);
		}
	}

	public function getPageWidth()
	{
		// Get current page width
		return $this->w;
	}

	public function getPageHeight()
	{
		// Get current page height
		return $this->h;
	}

	public function getX()
	{
		// Get x position
		return $this->x;
	}

	public function setX($x)
	{
		// Set x position
		if ($x >= 0)
			$this->x = $x;
		else
			$this->x = $this->w + $x;
	}

	public function getY()
	{
		// Get y position
		return $this->y;
	}

	public function setY($y, $resetX = true)
	{
		// Set y position and optionally reset x
		if ($y >= 0) {
			$this->y = $y;
		} else {
			$this->y = $this->h + $y;
		}

		if ($resetX) {
			$this->x = $this->lMargin;
		}
	}

	public function setXY($x, $y)
	{
		// Set x and y positions
		$this->setX($x);
		$this->setY($y, false);
	}

	public function output($dest = '', $name = '', $isUTF8 = false)
	{
		// Output PDF to some destination
		$this->Close();
		if (strlen($name) === 1 && strlen($dest) !== 1) {
			// Fix parameter order
			$tmp = $dest;
			$dest = $name;
			$name = $tmp;
		}
		if ($dest === '') {
			$dest = 'I';
		}
		if ($name === '') {
			$name = 'doc.pdf';
		}

		switch (strtoupper($dest)) {
			case 'I':
				// Send to standard output
				$this->checkoutput();
				if (PHP_SAPI != 'cli') {
					// We send to a browser
					header('Content-Type: application/pdf');
					header('Content-Disposition: inline; ' . $this->httpencode('filename', $name, $isUTF8));
					header('Cache-Control: private, max-age=0, must-revalidate');
					header('Pragma: public');
				}
				echo $this->buffer;
				break;
			case 'D':
				// Download file
				$this->checkoutput();
				header('Content-Type: application/x-download');
				header('Content-Disposition: attachment; ' . $this->httpencode('filename', $name, $isUTF8));
				header('Cache-Control: private, max-age=0, must-revalidate');
				header('Pragma: public');
				echo $this->buffer;
				break;
			case 'F':
				// Save to local file
				if (!file_put_contents($name, $this->buffer))
					throw new FPDFException('Unable to create output file: ' . $name);
				break;
			case 'S':
				// Return as a string
				return $this->buffer;
			default:
				throw new FPDFException('Incorrect output destination: ' . $dest);
		}
		return '';
	}

	/*******************************************************************************
	 *                              Protected methods                               *
	 *******************************************************************************/

	protected function dochecks() : void
	{
		// Check mbstring overloading
		if (ini_get('mbstring.func_overload') & 2) {
			throw new FPDFException('mbstring overloading must be disabled');
		}
	}

	protected function checkoutput() : void
	{
		if (PHP_SAPI !== 'cli') {
			if (headers_sent($file, $line)) {
				throw new FPDFException("Some data has already been output, can't send PDF file (output started at $file:$line)");
			}
		}
		if (ob_get_length()) {
			// The output buffer is not empty
			if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
				// It contains only a UTF-8 BOM and/or whitespace, let's clean it
				ob_clean();
			} else {
				throw new FPDFException('Some data has already been output, can`t send PDF file');
			}
		}
	}

	protected function getpagesize($size)
	{
		if (is_string($size)) {
			$size = strtolower($size);
			if (!isset($this->StdPageSizes[$size]))
				throw new FPDFException('Unknown page size: ' . $size);
			$a = $this->StdPageSizes[$size];
			return array($a[0] / $this->k, $a[1] / $this->k);
		} else {
			if ($size[0] > $size[1]) {
				return array($size[1], $size[0]);
			} else {
				return $size;
			}
		}
	}

	protected function beginpage($orientation, $size, $rotation) : void
	{
		$this->page++;
		$this->pages[$this->page] = '';
		$this->state = 2;
		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
		$this->FontFamily = '';

		// Check page size and orientation
		if ($orientation == '') {
			$orientation = $this->DefOrientation;
		} else {
			$orientation = strtoupper($orientation[0]);
		}

		if ($size === '') {
			$size = $this->DefPageSize;
		} else {
			$size = $this->getpagesize($size);
		}

		if ($orientation != $this->CurOrientation || $size[0] != $this->CurPageSize[0] || $size[1] != $this->CurPageSize[1]) {
			// New size or orientation
			if ($orientation === 'P') {
				$this->w = $size[0];
				$this->h = $size[1];
			} else {
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w * $this->k;
			$this->hPt = $this->h * $this->k;
			$this->PageBreakTrigger = $this->h - $this->bMargin;
			$this->CurOrientation = $orientation;
			$this->CurPageSize = $size;
		}
		if ($orientation != $this->DefOrientation || $size[0] != $this->DefPageSize[0] || $size[1] != $this->DefPageSize[1]) {
			$this->PageInfo[$this->page]['size'] = array($this->wPt, $this->hPt);
		}
		if ($rotation != 0) {
			if ($rotation % 90 != 0) {
				throw new FPDFException('Incorrect rotation value: ' . $rotation);
			}
			$this->CurRotation = $rotation;
			$this->PageInfo[$this->page]['rotation'] = $rotation;
		}
	}

	protected function endpage() : void
	{
		$this->state = 1;
	}

	protected function loadfont($font) : array
	{
		// Load a font definition file from the font directory
		if (strpos($font, '/') !== false || strpos($font, "\\") !== false) {
			throw new FPDFException('Incorrect font definition file name: ' . $font);
		}

		include($this->fontpath . $font);

		if (!isset($name)) {
			throw new FPDFException('Could not include font definition file');
		}
		if (isset($enc)) {
			$enc = strtolower($enc);
		}
		if (!isset($subsetted)) {
			$subsetted = false;
		}

		return get_defined_vars();
	}

	protected function isascii($s) : bool
	{
		// Test if string is ASCII
		$nb = strlen($s);
		for ($i = 0; $i < $nb; $i++) {
			if (ord($s[$i]) > 127) {
				return false;
			}
		}
		return true;
	}

	protected function httpencode($param, $value, $isUTF8) : string
	{
		// Encode HTTP header field parameter
		if ($this->isascii($value)) {
			return $param . '="' . $value . '"';
		}

		if (!$isUTF8) {
			$value = utf8_encode($value);
		}

		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
			return $param . '="' . rawurlencode($value) . '"';
		} else {
			return $param . "*=UTF-8''" . rawurlencode($value);
		}
	}

	protected function UTF8toUTF16($s) : string
	{
		// Convert UTF-8 to UTF-16BE with BOM
		$res = "\xFE\xFF";
		$nb = strlen($s);
		$i = 0;

		while ($i < $nb) {
			$c1 = ord($s[$i++]);
			if ($c1 >= 224) {
				// 3-byte character
				$c2 = ord($s[$i++]);
				$c3 = ord($s[$i++]);
				$res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C) >> 2));
				$res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));
			} elseif ($c1 >= 192) {
				// 2-byte character
				$c2 = ord($s[$i++]);
				$res .= chr(($c1 & 0x1C) >> 2);
				$res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));
			} else {
				// Single-byte character
				$res .= "\0" . chr($c1);
			}
		}

		return $res;
	}

	protected function escape($s)
	{
		// Escape special characters
		if (strpos($s, '(') !== false || strpos($s, ')') !== false || strpos($s, '\\') !== false || strpos($s, "\r") !== false) {
			return str_replace(array('\\', '(', ')', "\r"), array('\\\\', '\\(', '\\)', '\\r'), $s);
		} else {
			return $s;
		}
	}

	protected function textstring($s) : string
	{
		// Format a text string
		if (!$this->isascii($s)) {
			$s = $this->UTF8toUTF16($s);
		}
		return '(' . $this->escape($s) . ')';
	}

	protected function dounderline($x, $y, $txt) : string
	{
		// Underline text
		$up = $this->CurrentFont['up'];
		$ut = $this->CurrentFont['ut'];
		$w = $this->getStringWidth($txt) + $this->ws * substr_count($txt, ' ');
		return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
	}

	protected function parsejpg($file) : array
	{
		// Extract info from a JPEG file
		$a = getimagesize($file);
		if (!$a) {
			throw new FPDFException('Missing or incorrect image file: ' . $file);
		}
		if ($a[2] != 2) {
			throw new FPDFException('Not a JPEG file: ' . $file);
		}

		if (!isset($a['channels']) || $a['channels'] == 3) {
			$colspace = 'DeviceRGB';
		} elseif ($a['channels'] == 4) {
			$colspace = 'DeviceCMYK';
		} else {
			$colspace = 'DeviceGray';
		}

		$bpc = isset($a['bits']) ? $a['bits'] : 8;
		$data = file_get_contents($file);

		return array('w' => $a[0], 'h' => $a[1], 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'DCTDecode', 'data' => $data);
	}

	protected function parsepng($file) : array
	{
		// Extract info from a PNG file
		$f = fopen($file, 'rb');
		if (!$f) {
			throw new FPDFException('Can\'t open image file: ' . $file);
		}
		$info = $this->parsepngstream($f, $file);
		fclose($f);

		return $info;
	}

	protected function parsepngstream($f, $file) : array
	{
		// Check signature
		if ($this->readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
			throw new FPDFException('Not a PNG file: ' . $file);
		}

		// Read header chunk
		$this->readstream($f, 4);
		if ($this->readstream($f, 4) != 'IHDR') {
			throw new FPDFException('Incorrect PNG file: ' . $file);
		}
		$w = $this->readint($f);
		$h = $this->readint($f);
		$bpc = ord($this->readstream($f, 1));
		if ($bpc > 8) {
			throw new FPDFException('16-bit depth not supported: ' . $file);
		}
		$ct = ord($this->readstream($f, 1));
		if ($ct == 0 || $ct == 4) {
			$colspace = 'DeviceGray';
		} elseif ($ct == 2 || $ct == 6) {
			$colspace = 'DeviceRGB';
		} elseif ($ct == 3) {
			$colspace = 'Indexed';
		} else {
			throw new FPDFException('Unknown color type: ' . $file);
		}

		if (ord($this->readstream($f, 1)) != 0) {
			throw new FPDFException('Unknown compression method: ' . $file);
		}
		if (ord($this->readstream($f, 1)) != 0) {
			throw new FPDFException('Unknown filter method: ' . $file);
		}
		if (ord($this->readstream($f, 1)) != 0) {
			throw new FPDFException('Interlacing not supported: ' . $file);
		}
		$this->readstream($f, 4);
		$dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

		// Scan chunks looking for palette, transparency and image data
		$pal = '';
		$trns = '';
		$data = '';
		do {
			$n = $this->readint($f);
			$type = $this->readstream($f, 4);
			if ($type === 'PLTE') {
				// Read palette
				$pal = $this->readstream($f, $n);
				$this->readstream($f, 4);
			} elseif ($type === 'tRNS') {
				// Read transparency info
				$t = $this->readstream($f, $n);
				if ($ct == 0) {
					$trns = array(ord(substr($t, 1, 1)));
				} elseif ($ct == 2) {
					$trns = array(ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1)));
				} else {
					$pos = strpos($t, chr(0));
					if ($pos !== false)
						$trns = array($pos);
				}
				$this->readstream($f, 4);
			} elseif ($type === 'IDAT') {
				// Read image data block
				$data .= $this->readstream($f, $n);
				$this->readstream($f, 4);
			} elseif ($type === 'IEND') {
				break;
			} else {
				$this->readstream($f, $n + 4);
			}
		} while ($n);

		if ($colspace === 'Indexed' && empty($pal)) {
			throw new FPDFException('Missing palette in ' . $file);
		}

		$info = array('w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'dp' => $dp, 'pal' => $pal, 'trns' => $trns);

		if ($ct >= 4) {
			// Extract alpha channel
			if (!function_exists('gzuncompress')) {
				throw new FPDFException('Zlib not available, can\'t handle alpha channel: ' . $file);
			}
			$data = gzuncompress($data);
			$color = '';
			$alpha = '';
			if ($ct == 4) {
				// Gray image
				$len = 2 * $w;
				for ($i = 0; $i < $h; $i++) {
					$pos = (1 + $len) * $i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data, $pos + 1, $len);
					$color .= preg_replace('/(.)./s', '$1', $line);
					$alpha .= preg_replace('/.(.)/s', '$1', $line);
				}
			} else {
				// RGB image
				$len = 4 * $w;
				for ($i = 0; $i < $h; $i++) {
					$pos = (1 + $len) * $i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data, $pos + 1, $len);
					$color .= preg_replace('/(.{3})./s', '$1', $line);
					$alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
				}
			}
			unset($data);
			$data = gzcompress($color);
			$info['smask'] = gzcompress($alpha);
			$this->WithAlpha = true;
			if ($this->PDFVersion < '1.4')
				$this->PDFVersion = '1.4';
		}
		$info['data'] = $data;
		return $info;
	}

	protected function readstream($f, $n) : string
	{
		// Read n bytes from stream
		$res = '';
		while ($n > 0 && !feof($f)) {
			$s = fread($f, $n);
			if ($s === false) {
				throw new FPDFException('Error while reading stream');
			}
			$n -= strlen($s);
			$res .= $s;
		}
		if ($n > 0) {
			throw new FPDFException('Unexpected end of stream');
		}
		return $res;
	}

	protected function readint($f)
	{
		// Read a 4-byte integer from stream
		$a = unpack('Ni', $this->readstream($f, 4));
		return $a['i'];
	}

	protected function parsegif($file) : array
	{
		// Extract info from a GIF file (via PNG conversion)
		if (!function_exists('imagepng')) {
			throw new FPDFException('GD extension is required for GIF support');
		}
		if (!function_exists('imagecreatefromgif')) {
			throw new FPDFException('GD has no GIF read support');
		}
		$im = imagecreatefromgif($file);
		if (!$im) {
			throw new FPDFException('Missing or incorrect image file: ' . $file);
		}
		imageinterlace($im, 0);
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		imagedestroy($im);
		$f = fopen('php://temp', 'rb+');
		if (!$f) {
			throw new FPDFException('Unable to create memory stream');
		}
		fwrite($f, $data);
		rewind($f);
		$info = $this->parsepngstream($f, $file);
		fclose($f);

		return $info;
	}

	protected function out($s) : void
	{
		// Add a line to the document
		if ($this->state === 2) {
			$this->pages[$this->page] .= $s . "\n";
		} elseif ($this->state === 1) {
			$this->put($s);
		} elseif ($this->state === 0) {
			throw new FPDFException('No page has been added yet');
		} elseif ($this->state === 3) {
			throw new FPDFException('The document is closed');
		}
	}

	protected function put($s) : void
	{
		$this->buffer .= $s . "\n";
	}

	protected function getoffset() : int
	{
		return strlen($this->buffer);
	}

	protected function newobj($n = null) : void
	{
		// Begin a new object
		if ($n === null) {
			$n = ++$this->n;
		}

		$this->offsets[$n] = $this->getoffset();
		$this->put($n . ' 0 obj');
	}

	protected function putstream($data) : void
	{
		$this->put('stream');
		$this->put($data);
		$this->put('endstream');
	}

	protected function putstreamobject($data) : void
	{
		if ($this->compress) {
			$entries = '/Filter /FlateDecode ';
			$data = gzcompress($data);
		} else {
			$entries = '';
		}
		$entries .= '/Length ' . strlen($data);
		$this->newobj();
		$this->put('<<' . $entries . '>>');
		$this->putstream($data);
		$this->put('endobj');
	}

	protected function putpage($n) : void
	{
		$this->newobj();
		$this->put('<</Type /Page');
		$this->put('/Parent 1 0 R');

		if (isset($this->PageInfo[$n]['size'])) {
			$this->put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['size'][0], $this->PageInfo[$n]['size'][1]));
		}
		if (isset($this->PageInfo[$n]['rotation'])) {
			$this->put('/Rotate ' . $this->PageInfo[$n]['rotation']);
		}

		$this->put('/Resources 2 0 R');

		if (isset($this->PageLinks[$n])) {
			// Links
			$annots = '/Annots [';
			foreach ($this->PageLinks[$n] as $pl) {
				$rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
				$annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
				if (is_string($pl[4])) {
					$annots .= '/A <</S /URI /URI ' . $this->textstring($pl[4]) . '>>>>';
				} else {
					$l = $this->links[$pl[4]];
					if (isset($this->PageInfo[$l[0]]['size'])) {
						$h = $this->PageInfo[$l[0]]['size'][1];
					} else {
						$h = ($this->DefOrientation == 'P') ? $this->DefPageSize[1] * $this->k : $this->DefPageSize[0] * $this->k;
					}
					$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->PageInfo[$l[0]]['n'], $h - $l[1] * $this->k);
				}
			}
			$this->put($annots . ']');
		}

		if ($this->WithAlpha) {
			$this->put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
		}
		$this->put('/Contents ' . ($this->n + 1) . ' 0 R>>');
		$this->put('endobj');

		// Page content
		if (!empty($this->AliasNbPages)) {
			$this->pages[$n] = str_replace($this->AliasNbPages, $this->page, $this->pages[$n]);
		}
		$this->putstreamobject($this->pages[$n]);
	}

	protected function putpages() : void
	{
		$nb = $this->page;
		for ($n = 1; $n <= $nb; $n++) {
			$this->PageInfo[$n]['n'] = $this->n + 1 + 2 * ($n - 1);
		}
		for ($n = 1; $n <= $nb; $n++) {
			$this->putpage($n);
		}

		// Pages root
		$this->newobj(1);
		$this->put('<</Type /Pages');
		$kids = '/Kids [';
		for ($n = 1; $n <= $nb; $n++) {
			$kids .= $this->PageInfo[$n]['n'] . ' 0 R ';
		}
		$this->put($kids . ']');
		$this->put('/Count ' . $nb);
		if ($this->DefOrientation == 'P') {
			$w = $this->DefPageSize[0];
			$h = $this->DefPageSize[1];
		} else {
			$w = $this->DefPageSize[1];
			$h = $this->DefPageSize[0];
		}
		$this->put(sprintf('/MediaBox [0 0 %.2F %.2F]', $w * $this->k, $h * $this->k));
		$this->put('>>');
		$this->put('endobj');
	}

	protected function putfonts() : void
	{
		foreach ($this->FontFiles as $file => $info) {
			// Font file embedding
			$this->newobj();
			$this->FontFiles[$file]['n'] = $this->n;
			$font = file_get_contents($this->fontpath . $file, true);
			if (!$font) {
				throw new FPDFException('Font file not found: ' . $file);
			}
			$compressed = substr($file, -2) === '.z';
			if (!$compressed && isset($info['length2'])) {
				$font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
			}
			$this->put('<</Length ' . strlen($font));
			if ($compressed) {
				$this->put('/Filter /FlateDecode');
			}
			$this->put('/Length1 ' . $info['length1']);
			if (isset($info['length2'])) {
				$this->put('/Length2 ' . $info['length2'] . ' /Length3 0');
			}
			$this->put('>>');
			$this->putstream($font);
			$this->put('endobj');
		}

		foreach ($this->fonts as $k => $font) {
			// Encoding
			if (isset($font['diff'])) {
				if (!isset($this->encodings[$font['enc']])) {
					$this->newobj();
					$this->put('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $font['diff'] . ']>>');
					$this->put('endobj');
					$this->encodings[$font['enc']] = $this->n;
				}
			}
			// ToUnicode CMap
			if (isset($font['uv'])) {
				if (isset($font['enc'])) {
					$cmapkey = $font['enc'];
				} else {
					$cmapkey = $font['name'];
				}

				if (!isset($this->cmaps[$cmapkey])) {
					$cmap = $this->tounicodecmap($font['uv']);
					$this->putstreamobject($cmap);
					$this->cmaps[$cmapkey] = $this->n;
				}
			}
			// Font object
			$this->fonts[$k]['n'] = $this->n + 1;
			$type = $font['type'];
			$name = $font['name'];
			if ($font['subsetted']) {
				$name = 'AAAAAA+' . $name;
			}

			if ($type === 'Core') {
				// Core font
				$this->newobj();
				$this->put('<</Type /Font');
				$this->put('/BaseFont /' . $name);
				$this->put('/Subtype /Type1');
				if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
					$this->put('/Encoding /WinAnsiEncoding');
				}
				if (isset($font['uv'])) {
					$this->put('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
				}
				$this->put('>>');
				$this->put('endobj');
			} elseif ($type === 'Type1' || $type === 'TrueType') {
				// Additional Type1 or TrueType/OpenType font
				$this->newobj();
				$this->put('<</Type /Font');
				$this->put('/BaseFont /' . $name);
				$this->put('/Subtype /' . $type);
				$this->put('/FirstChar 32 /LastChar 255');
				$this->put('/Widths ' . ($this->n + 1) . ' 0 R');
				$this->put('/FontDescriptor ' . ($this->n + 2) . ' 0 R');

				if (isset($font['diff'])) {
					$this->put('/Encoding ' . $this->encodings[$font['enc']] . ' 0 R');
				} else {
					$this->put('/Encoding /WinAnsiEncoding');
				}

				if (isset($font['uv'])) {
					$this->put('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
				}
				$this->put('>>');
				$this->put('endobj');

				// Widths
				$this->newobj();
				$cw = &$font['cw'];
				$s = '[';
				for ($i = 32; $i <= 255; $i++) {
					$s .= $cw[chr($i)] . ' ';
				}
				$this->put($s . ']');
				$this->put('endobj');
				// Descriptor
				$this->newobj();
				$s = '<</Type /FontDescriptor /FontName /' . $name;

				foreach ($font['desc'] as $k => $v) {
					$s .= ' /' . $k . ' ' . $v;
				}

				if (!empty($font['file'])) {
					$s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->FontFiles[$font['file']]['n'] . ' 0 R';
				}
				$this->put($s . '>>');
				$this->put('endobj');
			} else {
				// Allow for additional types
				$mtd = '_put' . strtolower($type);
				if (!method_exists($this, $mtd)) {
					throw new FPDFException('Unsupported font type: ' . $type);
				}
				$this->$mtd($font);
			}
		}
	}

	protected function tounicodecmap($uv) : string
	{
		$ranges = '';
		$nbr = 0;
		$chars = '';
		$nbc = 0;
		foreach ($uv as $c => $v) {
			if (is_array($v)) {
				$ranges .= sprintf("<%02X> <%02X> <%04X>\n", $c, $c + $v[1] - 1, $v[0]);
				$nbr++;
			} else {
				$chars .= sprintf("<%02X> <%04X>\n", $c, $v);
				$nbc++;
			}
		}
		$s = "/CIDInit /ProcSet findresource begin\n";
		$s .= "12 dict begin\n";
		$s .= "begincmap\n";
		$s .= "/CIDSystemInfo\n";
		$s .= "<</Registry (Adobe)\n";
		$s .= "/Ordering (UCS)\n";
		$s .= "/Supplement 0\n";
		$s .= ">> def\n";
		$s .= "/CMapName /Adobe-Identity-UCS def\n";
		$s .= "/CMapType 2 def\n";
		$s .= "1 begincodespacerange\n";
		$s .= "<00> <FF>\n";
		$s .= "endcodespacerange\n";
		if ($nbr > 0) {
			$s .= "$nbr beginbfrange\n";
			$s .= $ranges;
			$s .= "endbfrange\n";
		}
		if ($nbc > 0) {
			$s .= "$nbc beginbfchar\n";
			$s .= $chars;
			$s .= "endbfchar\n";
		}
		$s .= "endcmap\n";
		$s .= "CMapName currentdict /CMap defineresource pop\n";
		$s .= "end\n";
		$s .= "end";

		return $s;
	}

	protected function putimages() : void
	{
		foreach (array_keys($this->images) as $file) {
			$this->putimage($this->images[$file]);
			unset($this->images[$file]['data']);
			unset($this->images[$file]['smask']);
		}
	}

	protected function putimage(&$info) : void
	{
		$this->newobj();
		$info['n'] = $this->n;
		$this->put('<</Type /XObject');
		$this->put('/Subtype /Image');
		$this->put('/Width ' . $info['w']);
		$this->put('/Height ' . $info['h']);

		if ($info['cs'] === 'Indexed') {
			$this->put('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
		} else {
			$this->put('/ColorSpace /' . $info['cs']);
			if ($info['cs'] === 'DeviceCMYK') {
				$this->put('/Decode [1 0 1 0 1 0 1 0]');
			}
		}

		$this->put('/BitsPerComponent ' . $info['bpc']);
		if (isset($info['f'])) {
			$this->put('/Filter /' . $info['f']);
		}
		if (isset($info['dp'])) {
			$this->put('/DecodeParms <<' . $info['dp'] . '>>');
		}
		if (isset($info['trns']) && is_array($info['trns'])) {
			$trns = '';
			for ($i = 0; $i < count($info['trns']); $i++) {
				$trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
			}
			$this->put('/Mask [' . $trns . ']');
		}

		if (isset($info['smask'])) {
			$this->put('/SMask ' . ($this->n + 1) . ' 0 R');
		}
		$this->put('/Length ' . strlen($info['data']) . '>>');
		$this->putstream($info['data']);
		$this->put('endobj');

		// Soft mask
		if (isset($info['smask'])) {
			$dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
			$smask = array(
				'w' => $info['w'],
				'h' => $info['h'],
				'cs' => 'DeviceGray',
				'bpc' => 8,
				'f' => $info['f'],
				'dp' => $dp,
				'data' => $info['smask']
			);
			$this->putimage($smask);
		}

		// Palette
		if ($info['cs'] == 'Indexed') {
			$this->putstreamobject($info['pal']);
		}
	}

	protected function putxobjectdict() : void
	{
		foreach ($this->images as $image) {
			$this->put('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
		}
	}

	protected function putresourcedict() : void
	{
		$this->put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
		$this->put('/Font <<');
		foreach ($this->fonts as $font) {
			$this->put('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
		}
		$this->put('>>');
		$this->put('/XObject <<');
		$this->putxobjectdict();
		$this->put('>>');
	}

	protected function putresources() : void
	{
		$this->putfonts();
		$this->putimages();

		// Resource dictionary
		$this->newobj(2);
		$this->put('<<');
		$this->putresourcedict();
		$this->put('>>');
		$this->put('endobj');
	}

	protected function putinfo() : void
	{
		$this->metadata['Producer'] = 'FPDF ' . self::VERSION;
		$this->metadata['CreationDate'] = 'D:' . @date('YmdHis');
		foreach ($this->metadata as $key => $value)
			$this->put('/' . $key . ' ' . $this->textstring($value));
	}

	protected function putcatalog() : void
	{
		$n = $this->PageInfo[1]['n'];
		$this->put('/Type /Catalog');
		$this->put('/Pages 1 0 R');

		if ($this->ZoomMode === 'fullpage') {
			$this->put('/OpenAction [' . $n . ' 0 R /Fit]');
		} elseif ($this->ZoomMode === 'fullwidth') {
			$this->put('/OpenAction [' . $n . ' 0 R /FitH null]');
		} elseif ($this->ZoomMode === 'real') {
			$this->put('/OpenAction [' . $n . ' 0 R /XYZ null null 1]');
		} elseif (!is_string($this->ZoomMode)) {
			$this->put('/OpenAction [' . $n . ' 0 R /XYZ null null ' . sprintf('%.2F', $this->ZoomMode / 100) . ']');
		}

		if ($this->LayoutMode === 'single') {
			$this->put('/PageLayout /SinglePage');
		} elseif ($this->LayoutMode === 'continuous') {
			$this->put('/PageLayout /OneColumn');
		} elseif ($this->LayoutMode === 'two') {
			$this->put('/PageLayout /TwoColumnLeft');
		}
	}

	protected function putheader() : void
	{
		$this->put('%PDF-' . $this->PDFVersion);
	}

	protected function puttrailer() : void
	{
		$this->put('/Size ' . ($this->n + 1));
		$this->put('/Root ' . $this->n . ' 0 R');
		$this->put('/Info ' . ($this->n - 1) . ' 0 R');
	}

	protected function enddoc() : void
	{
		$this->putheader();
		$this->putpages();
		$this->putresources();

		// Info
		$this->newobj();
		$this->put('<<');
		$this->putinfo();
		$this->put('>>');
		$this->put('endobj');

		// Catalog
		$this->newobj();
		$this->put('<<');
		$this->putcatalog();
		$this->put('>>');
		$this->put('endobj');

		// Cross-ref
		$offset = $this->getoffset();
		$this->put('xref');
		$this->put('0 ' . ($this->n + 1));
		$this->put('0000000000 65535 f ');
		for ($i = 1; $i <= $this->n; $i++) {
			$this->put(sprintf('%010d 00000 n ', $this->offsets[$i]));
		}

		// Trailer
		$this->put('trailer');
		$this->put('<<');
		$this->puttrailer();
		$this->put('>>');
		$this->put('startxref');
		$this->put($offset);
		$this->put('%%EOF');
		$this->state = 3;
	}
}


?>
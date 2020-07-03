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

	/* @var string[] Fonts which are shipped by FPDF */
	private const CORE_FONTS = array(
		'courier',
		'helvetica',
		'times',
		'symbol',
		'zapfdingbats'
	);

	/* @var string[] Allowed values for $layout_mode */
	private const ALLOWED_LAYOUT_MODES = array(
		'single',
		'continuous',
		'two',
		'default'
	);

	/** @var string[] Allowed string-values for $zoom_mode */
	private const ALLOWED_ZOOM_MODES = array(
		'fullpage',
		'fullwidth',
		'real',
		'default'
	);

	/* @var array Common known page sizes */
	private const PAGE_SIZES = array(
		'a3' => array(841.89, 1190.55),
		'a4' => array(595.28, 841.89),
		'a5' => array(420.94, 595.28),
		'letter' => array(612, 792),
		'legal' => array(612, 1008)
	);

	/* @var float[] Scale factors for common units */
	private const SCALE_FACTORS = array(
		'pt' => 1,
		'mm' => 72 / 25.4,
		'cm' => 72 / 2.54,
		'in' => 72
	);

	/* @var int Current page number */
	protected $page = 0;

	/* @var int Current object number */
	protected $n = 2;

	/* @var array Array of object offsets */
	protected $offsets;

	/* Buffer holding in-memory PDF */
	protected $buffer = '';

	/* @var array Pages of PDF */
	protected $pages = array();

	/* @var int Document state */
	protected $state = 0;

	/* @var bool Compression flag */
	protected $compress;

	/* @var float Scale factor (number of points in user unit, see SCALE_FACTORS) */
	protected $scale_factor;

	/* @var string Default orientation (P=portrait, L=landscape) */
	protected $def_orientation;

	/* @var string Current orientation (P=portrait, L=landscape) */
	protected $cur_orientation;

	/* @var float[] Default page size (two floating point numbers) */
	protected $def_page_size;

	/* @var float[] Current page size (two floating point numbers) */
	protected $cur_page_size;

	/* @var int Current page rotation (Only numbers divided by 90 are allowed, example: 0, 90, 180, ...) */
	protected $cur_rotation = 0;

	/* @var array Page-related data */
	protected $page_info = array();

	/* @var float $wPt Dimensions of current page in points */
	/* @var float $hPt Dimensions of current page in points */
	protected $wPt, $hPt;

	/* @var float $w Dimensions of current page in user unit */
	/* @var float $h Dimensions of current page in user unit */
	protected $w, $h;

	/* @var float Left margin */
	protected $left_margin;

	/* @var float Top margin */
	protected $top_margin;

	/* @var float Right margin */
	protected $right_margin;

	/*  @var float Page break margin */
	protected $break_margin;

	/* @var float Cell margin */
	protected $cMargin;

	/* @var float $x Current position in user unit */
	/* @var float $y Current position in user unit */
	protected $x, $y;

	/* @var float Height of last printed cell */
	protected $lasth = 0;

	/* @var float Line width in user unit */
	protected $line_width;

	/* @var string Path containing fonts */
	protected $fontpath;

	/* @var array Array of used fonts */
	protected $fonts = array();

	/* @var array Array of font files */
	protected $font_files = array();

	/* @var array Array of encodings */
	protected $encodings = array();

	/* @var array Array of ToUnicode CMaps */
	protected $cmaps = array();

	/* @var string Current font family */
	protected $font_family = '';

	/* @var string Current font style */
	protected $font_style = '';

	/* @var bool Underlining flag */
	protected $underline = false;

	/* @var array Current font info */
	protected $current_font;

	/* @var float Current font size in points */
	protected $font_size_pt = 12;

	/* @var float Current font size in user unit */
	protected $font_size;

	/* @var string Commands for drawing color */
	protected $draw_color = '0 G';

	/* @var string Commands for filling color */
	protected $fill_color = '0 g';

	/* @var string Commands for text color */
	protected $text_color = '0 g';

	/* @var bool Indicates whether fill and text colors are different */
	protected $color_flag = false;

	/* @var bool Indicates whether alpha channel is used */
	protected $with_alpha = false;

	/* @var float Word spacing */
	protected $ws = 0;

	/* @var array Array of used images */
	protected $images = array();

	/* @var array Array of links in pages */
	protected $page_links = array();

	/* @var array Array of internal links */
	protected $links;

	/* @var bool Automatic page breaking */
	protected $auto_page_break;

	/* @var float Threshold used to trigger page breaks */
	protected $page_break_trigger;

	/* @var bool Flag set when processing header */
	protected $in_header = false;

	/* @var bool Flag set when processing footer */
	protected $in_footer = false;

	/* @var string Alias for total number of pages */
	protected $alias_NbPages;

	/* @var int|string Zoom mode. Predefined zoom mode (see ALLOWED_ZOOM_MODES) or zoom in percentage */
	protected $zoom_mode;

	/* @var string Layout display mode */
	protected $layout_mode;

	/** @var string|null Author of document (metadata) */
	private $document_author;

	/** @var string|null Creator of document (metadata) */
	private $document_creator;

	/** @var string|null Keywords describing the document (metadata) */
	private $document_keywords;

	/** @var string|null Subject of document (metadata) */
	private $document_subject;

	/** @var string|null Title of document (metadata) */
	private $document_title;

	/* @var string PDF version number */
	protected $PDFVersion = '1.3';


	/**
	 * FPDF constructor.
	 *
	 * @param string $orientation
	 * @param string $unit Common size unit (see SCALE_FACTORS), Default: 'mm' for millimeter
	 * @param float[]|string $size Common known size name (see PAGE_SIZES) or float-array containing (0=width, 1=height)
	 */
	public function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
	{
		// Font path
		if (defined('FPDF_FONTPATH')) {
			$this->fontpath = FPDF_FONTPATH;

			$last_char = substr($this->fontpath, -1);
			if ($last_char !== '/' && $last_char !== '\\') {
				$this->fontpath .= '/';
			}
		} elseif (is_dir(__DIR__ . '/font')) {
			$this->fontpath = __DIR__ . '/font/';
		} else {
			$this->fontpath = '';
		}

		// Scale factor
		if (array_key_exists($unit, self::SCALE_FACTORS)) {
			$this->scale_factor = self::SCALE_FACTORS[$unit];
		} else {
			throw new \InvalidArgumentException('Unknown unit: ' . $unit);
		}

		// Page sizes
		$size = $this->getPageSize($size);
		$this->def_page_size = $size;
		$this->cur_page_size = $size;

		// Page orientation
		$orientation = strtolower($orientation);
		if ($orientation === 'p' || $orientation === 'portrait') {
			$this->def_orientation = 'P';
			$this->w = $size[0];
			$this->h = $size[1];
		} elseif ($orientation === 'l' || $orientation === 'landscape') {
			$this->def_orientation = 'L';
			$this->w = $size[1];
			$this->h = $size[0];
		} else {
			throw new \InvalidArgumentException('Incorrect orientation: ' . $orientation);
		}
		$this->cur_orientation = $this->def_orientation;
		$this->wPt = $this->w * $this->scale_factor;
		$this->hPt = $this->h * $this->scale_factor;

		// Page margins (1 cm)
		$margin = 28.35 / $this->scale_factor;
		$this->SetMargins($margin, $margin);

		// Interior cell margin (1 mm)
		$this->cMargin = $margin / 10;

		// Line width (0.2 mm)
		$this->line_width = .567 / $this->scale_factor;

		// Automatic page break
		$this->setAutoPageBreak(true, 2 * $margin);

		// Default display mode
		$this->setDisplayMode('default');

		// Enable compression
		$this->setCompression(true);
	}

	/**
	 * Defines the left, top and right margins.
	 * By default, they equal 1 cm.
	 * Call this method to change them.
	 *
	 * @param float $left Left margin
	 * @param float $top Top margin
	 * @param float|null $right Right margin. Default value is the left one.
	 */
	public function setMargins(float $left, float $top, float $right = null) : void
	{
		// Set left, top and right margins
		$this->left_margin = $left;
		$this->top_margin = $top;
		if ($right === null) {
			$right = $left;
		}
		$this->right_margin = $right;
	}

	/**
	 * Defines the left margin.
	 * The method can be called before creating the first page.
	 *
	 * If the current abscissa gets out of page, it is brought back to the margin.
	 *
	 * @param float $margin The margin
	 */
	public function setLeftMargin(float $margin) : void
	{
		// Set left margin
		$this->left_margin = $margin;
		if ($this->page > 0 && $this->x < $margin) {
			$this->x = $margin;
		}
	}

	/**
	 * Defines the top margin.
	 * The method can be called before creating the first page.
	 *
	 * @param float $margin The margin
	 */
	public function setTopMargin(float $margin) : void
	{
		// Set top margin
		$this->top_margin = $margin;
	}

	/**
	 * Defines the right margin.
	 * The method can be called before creating the first page.
	 *
	 * @param float $margin The margin
	 */
	public function setRightMargin(float $margin) : void
	{
		// Set right margin
		$this->right_margin = $margin;
	}

	/**
	 * Enables or disables the automatic page breaking mode.
	 * When enabling, the second parameter is the distance from the bottom of the page that defines the triggering limit.
	 * By default, the mode is on and the margin is 2 cm.
	 *
	 * @param bool $auto Boolean indicating if mode should be on or off.
	 * @param float|int $margin Distance from the bottom of the page.
	 */
	public function setAutoPageBreak(bool $auto, float $margin = 0) : void
	{
		// Set auto page break mode and triggering margin
		$this->auto_page_break = $auto;
		$this->break_margin = $margin;
		$this->page_break_trigger = $this->h - $margin;
	}

	/**
	 * Defines the way the document is to be displayed by the viewer.
	 * The zoom level can be set: pages can be displayed entirely on screen, occupy the full width of the window, use real size, be scaled by a specific zooming factor or use viewer default (configured in the Preferences menu of Adobe Reader).
	 * The page layout can be specified too: single at once, continuous display, two columns or viewer default.
	 *
	 * @param int|string $zoom Predefined zoom (see ALLOWED_ZOOM_MODES) or zoom in percentage
	 * @param string $layout Layout (see ALLOWED_LAYOUT_MODES)
	 */
	public function setDisplayMode($zoom, string $layout = 'default') : void
	{
		// Set display mode in viewer
		if (is_int($zoom) || in_array($zoom, self::ALLOWED_ZOOM_MODES, true)) {
			$this->zoom_mode = $zoom;
		} else {
			throw new \InvalidArgumentException('Incorrect zoom display mode: ' . $zoom);
		}

		if (in_array($layout, self::ALLOWED_LAYOUT_MODES, true)) {
			$this->layout_mode = $layout;
		} else {
			throw new \InvalidArgumentException('Incorrect layout display mode: ' . $layout);
		}
	}

	/**
	 * Enables or disables compression.
	 * When enabled, the internal representation of each page is compressed, which leads to a compression ratio of about 2 for the resulting document.
	 *
	 * Compression is only available when zlib support is enabled.
	 *
	 * @see https://www.php.net/manual/en/book.zlib.php
	 *
	 * @param bool $compress Indicating if compression will be enabled
	 */
	public function setCompression(bool $compress) : void
	{
		// Compression only available when zlib support is enabled
		if (function_exists('gzcompress')) {
			$this->compress = $compress;
		} else {
			$this->compress = false;
		}
	}

	/**
	 * Defines the title of the document.
	 *
	 * @param string $title The title
	 * @param bool $isUTF8 Indicates if the string is encoded in ISO-8859-1 (false) or UTF-8 (true). Default value: false.
	 */
	final public function setTitle(string $title, bool $isUTF8 = false) : void
	{
		$this->document_title = $isUTF8 ? $title : utf8_encode($title);
	}

	/**
	 * Defines the author of the document.
	 *
	 * @param string $author The name of the author
	 * @param bool $isUTF8 Indicates if the string is encoded in ISO-8859-1 (false) or UTF-8 (true). Default value: false.
	 */
	final public function setAuthor(string $author, bool $isUTF8 = false) : void
	{
		$this->document_author = $isUTF8 ? $author : utf8_encode($author);
	}

	/**
	 * Defines the subject of the document.
	 *
	 * @param string $subject The subject
	 * @param bool $isUTF8 Indicates if the string is encoded in ISO-8859-1 (false) or UTF-8 (true). Default value: false.
	 */
	final public function setSubject(string $subject, bool $isUTF8 = false) : void
	{
		$this->document_subject = $isUTF8 ? $subject : utf8_encode($subject);
	}

	/**
	 * Associates keywords with the document, generally in the form 'keyword1 keyword2 ...'.
	 *
	 * @param string $keywords The list of keywords
	 * @param bool $isUTF8 Indicates if the string is encoded in ISO-8859-1 (false) or UTF-8 (true). Default value: false.
	 */
	final public function setKeywords(string $keywords, bool $isUTF8 = false) : void
	{
		$this->document_keywords = $isUTF8 ? $keywords : utf8_encode($keywords);
	}

	/**
	 * Defines the creator of the document.
	 * This is typically the name of the application that generates the PDF.
	 *
	 * @param string $creator The name of the creator
	 * @param bool $isUTF8 Indicates if the string is encoded in ISO-8859-1 (false) or UTF-8 (true). Default value: false.
	 */
	final public function setCreator(string $creator, bool $isUTF8 = false) : void
	{
		$this->document_creator = $isUTF8 ? $creator : utf8_encode($creator);
	}

	/**
	 * Defines an alias for the total number of pages. It will be substituted as the document is closed.
	 *
	 * @param string $alias The alias. Default value: {nb}.
	 */
	public function aliasNbPages(string $alias = '{nb}') : void
	{
		// Define an alias for total number of pages
		$this->alias_NbPages = $alias;
	}

	/**
	 * Terminates the PDF document.
	 * It is not necessary to call this method explicitly because Output() does it automatically.
	 * If the document contains no page, AddPage() is called to prevent from getting an invalid document.
	 */
	public function close() : void
	{
		// Terminate document
		if ($this->state === 3) {
			return;
		}
		if ($this->page === 0) {
			$this->AddPage();
		}

		// Page footer
		$this->in_footer = true;
		$this->footer();
		$this->in_footer = false;

		// Close page
		$this->endPage();

		// Close document
		$this->endDoc();
	}

	/**
	 * Adds a new page to the document.
	 * If a page is already present, the Footer() method is called first to output the footer.
	 * Then the page is added, the current position set to the top-left corner according to the left and top margins, and Header() is called to display the header.
	 *
	 * The font which was set before calling is automatically restored.
	 * There is no need to call SetFont() again if you want to continue with the same font.
	 * The same is true for colors and line width.
	 *
	 * The origin of the coordinate system is at the top-left corner and increasing ordinates go downwards.
	 *
	 * @param string $orientation Page orientation. Possible values are (case insensitive): Portrait (P), Landscape (L). The default value is the one passed to the constructor.
	 * @param mixed $size Page size. It can be either one of the following values (case insensitive): A3, A4, A5, Letter, Legal or an array containing the width and the height (expressed in user unit). The default value is the one passed to the constructor.
	 * @param int $rotation Angle by which to rotate the page. It must be a multiple of 90; positive values mean clockwise rotation. The default value is 0.
	 */
	public function addPage(string $orientation = '', $size = '', int $rotation = 0) : void
	{
		// Start a new page
		if ($this->state === 3) {
			throw new FPDFException('The document is closed');
		}

		$family = $this->font_family;
		$style = $this->font_style . ($this->underline ? 'U' : '');
		$fontsize = $this->font_size_pt;
		$lw = $this->line_width;
		$dc = $this->draw_color;
		$fc = $this->fill_color;
		$tc = $this->text_color;
		$cf = $this->color_flag;

		if ($this->page > 0) {
			// Page footer
			$this->in_footer = true;
			$this->footer();
			$this->in_footer = false;

			// Close page
			$this->endPage();
		}

		// Start new page
		$this->beginPage($orientation, $size, $rotation);

		// Set line cap style to square
		$this->out('2 J');

		// Set line width
		$this->line_width = $lw;
		$this->out(sprintf('%.2F w', $lw * $this->scale_factor));

		// Set font
		if ($family) {
			$this->setFont($family, $style, $fontsize);
		}

		// Set colors
		$this->draw_color = $dc;
		if ($dc !== '0 G') {
			$this->out($dc);
		}
		$this->fill_color = $fc;
		if ($fc !== '0 g') {
			$this->out($fc);
		}
		$this->text_color = $tc;
		$this->color_flag = $cf;

		// Page header
		$this->in_header = true;
		$this->header();
		$this->in_header = false;

		// Restore line width
		if ($this->line_width !== $lw) {
			$this->line_width = $lw;
			$this->out(sprintf('%.2F w', $lw * $this->scale_factor));
		}
		// Restore font
		if ($family) {
			$this->setFont($family, $style, $fontsize);
		}
		// Restore colors
		if ($this->draw_color !== $dc) {
			$this->draw_color = $dc;
			$this->out($dc);
		}
		if ($this->fill_color !== $fc) {
			$this->fill_color = $fc;
			$this->out($fc);
		}
		$this->text_color = $tc;
		$this->color_flag = $cf;
	}

	/**
	 * This method is used to render the page header.
	 * It is automatically called by AddPage() and should not be called directly by the application.
	 * The implementation in FPDF is empty, so you have to subclass it and override the method if you want a specific processing.
	 */
	public function header() : void
	{
		// To be implemented in your own inherited class
	}

	/**
	 * This method is used to render the page footer.
	 * It is automatically called by AddPage() and Close() and should not be called directly by the application.
	 * The implementation in FPDF is empty, so you have to subclass it and override the method if you want a specific processing.
	 */
	public function footer() : void
	{
		// To be implemented in your own inherited class
	}

	/**
	 * Returns the current page number.
	 *
	 * @return int
	 */
	public function pageNo() : int
	{
		// Get current page number
		return $this->page;
	}

	/**
	 * Defines the color used for all drawing operations (lines, rectangles and cell borders).
	 * It can be expressed in RGB components or gray scale.
	 * The method can be called before the first page is created and the value is retained from page to page.
	 *
	 * @param int $r If g et b are given, red component; if not, indicates the gray level. Value between 0 and 255.
	 * @param int|null $g Green component (between 0 and 255)
	 * @param int|null $b Blue component (between 0 and 255)
	 */
	public function setDrawColor(int $r, int $g = null, int $b = null) : void
	{
		// Set color for all stroking operations
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->draw_color = sprintf('%.3F G', $r / 255);
		} else {
			$this->draw_color = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
		}

		if ($this->page > 0) {
			$this->out($this->draw_color);
		}
	}

	/**
	 * Defines the color used for all filling operations (filled rectangles and cell backgrounds).
	 * It can be expressed in RGB components or gray scale.
	 * The method can be called before the first page is created and the value is retained from page to page.
	 *
	 * @param int $r If g and b are given, red component; if not, indicates the gray level. Value between 0 and 255.
	 * @param int|null $g Green component (between 0 and 255)
	 * @param int|null $b Blue component (between 0 and 255)
	 */
	public function setFillColor(int $r, int $g = null, int $b = null) : void
	{
		// Set color for all filling operations
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->fill_color = sprintf('%.3F g', $r / 255);
		} else {
			$this->fill_color = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
		}

		$this->color_flag = $this->fill_color !== $this->text_color;

		if ($this->page > 0) {
			$this->out($this->fill_color);
		}
	}

	/**
	 * Defines the color used for text.
	 * It can be expressed in RGB components or gray scale.
	 * The method can be called before the first page is created and the value is retained from page to page.
	 *
	 * @param int $r If g et b are given, red component; if not, indicates the gray level. Value between 0 and 255.
	 * @param int|null $g Green component (between 0 and 255)
	 * @param int|null $b Blue component (between 0 and 255)
	 */
	function setTextColor(int $r, int $g = null, int $b = null) : void
	{
		// Set color for text
		if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
			$this->text_color = sprintf('%.3F g', $r / 255);
		} else {
			$this->text_color = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
		}

		$this->color_flag = $this->fill_color !== $this->text_color;
	}

	/**
	 * Returns the length of a string in user unit.
	 * A font must be selected.
	 *
	 * @param string $s The string whose length is to be computed.
	 *
	 * @return float
	 */
	public function getStringWidth(string $s) : float
	{
		// Get width of a string in the current font
		$cw = $this->current_font['cw'];
		$width = 0;

		$string_length = strlen($s);
		for ($i = 0; $i < $string_length; $i++) {
			$width += $cw[$s[$i]];
		}

		return $width * $this->font_size / 1000;
	}

	/**
	 * Defines the line width.
	 * By default, the value equals 0.2 mm.
	 * The method can be called before the first page is created and the value is retained from page to page.
	 *
	 * @param float $width The width
	 */
	public function setLineWidth(float $width) : void
	{
		// Set line width
		$this->line_width = $width;
		if ($this->page > 0) {
			$this->out(sprintf('%.2F w', $width * $this->scale_factor));
		}
	}

	/**
	 * Draws a line between two points.
	 *
	 * @param float $x1 Abscissa of first point
	 * @param float $y1 Ordinate of first point
	 * @param float $x2 Abscissa of second point
	 * @param float $y2 Ordinate of second point
	 */
	public function line(float $x1, float $y1, float $x2, float $y2) : void
	{
		// Draw a line
		$this->out(
			sprintf(
				'%.2F %.2F m %.2F %.2F l S',
				$x1 * $this->scale_factor,
				($this->h - $y1) * $this->scale_factor,
				$x2 * $this->scale_factor,
				($this->h - $y2) * $this->scale_factor
			)
		);
	}

	/**
	 * Outputs a rectangle.
	 * It can be drawn (border only), filled (with no border) or both.
	 *
	 * @param float $x Abscissa of upper-left corner
	 * @param float $y Ordinate of upper-left corner
	 * @param float $w Width
	 * @param float $h Height
	 * @param string $style Style of rendering. Possible values are: draw (D, default), fill (F), draw and fill (DF/FD) or empty string
	 */
	public function rect(float $x, float $y, float $w, float $h, string $style = '') : void
	{
		// Draw a rectangle
		if ($style === 'F') {
			$op = 'f';
		} elseif ($style === 'FD' || $style === 'DF') {
			$op = 'B';
		} else {
			$op = 'S';
		}

		$this->out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->scale_factor, ($this->h - $y) * $this->scale_factor, $w * $this->scale_factor, -$h * $this->scale_factor, $op));
	}

	/**
	 * Imports a TrueType, OpenType or Type1 font and makes it available.
	 * It is necessary to generate a font definition file first with the MakeFont utility.
	 *
	 * The definition file (and the font file itself when embedding) must be present in the font directory.
	 * If it is not found, the error "Could not include font definition file" is raised.
	 *
	 * @example $pdf->AddFont('Comic','I');
	 * @example $pdf->AddFont('Comic','I','comici.php');
	 *
	 * @param string $family Font family. The name can be chosen arbitrarily. If it is a standard family name, it will override the corresponding font.
	 * @param string $style Font style. Possible values are (case insensitive): empty string: regular, bold (B), italic (I), bold italic (BI/IB). The default value is regular.
	 * @param string $file The font definition file. By default, the name is built from the family and style, in lower case with no space.
	 */
	public function addFont(string $family, string $style = '', string $file = '') : void
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
				$this->font_files[$info['file']] = array('length1' => $info['originalsize']);
			} else {
				$this->font_files[$info['file']] = array('length1' => $info['size1'], 'length2' => $info['size2']);
			}
		}
		$this->fonts[$fontkey] = $info;
	}

	/**
	 * Sets the font used to print character strings.
	 * It is mandatory to call this method at least once before printing text or the resulting document would not be valid.
	 *
	 * The font can be either a standard one or a font added via the AddFont() method. Standard fonts use the Windows encoding cp1252 (Western Europe).
	 *
	 * The method can be called before the first page is created and the font is kept from page to page.
	 *
	 * If you just wish to change the current font size, it is simpler to call SetFontSize().
	 *
	 * Note: the font definition files must be accessible. They are searched successively in:
	 *
	 * The directory defined by the FPDF_FONTPATH constant (if this constant is defined)
	 * The font directory located in the same directory as fpdf.php (if it exists)
	 * The directories accessible through include()
	 *
	 * Example using FPDF_FONTPATH:
	 * define('FPDF_FONTPATH','/home/www/font');
	 * require('fpdf.php');
	 *
	 * If the file corresponding to the requested font is not found, the error "Could not include font definition file" is raised.
	 *
	 * @param string $family Family font. It can be either a name defined by AddFont() or one of the standard families (case insensitive): Courier (fixed-width), Helvetica or Arial (synonymous; sans serif), Times (serif), Symbol (symbolic), ZapfDingbats (symbolic). It is also possible to pass an empty string. In that case, the current family is kept.
	 * @param string $style Font style. Possible values are (case insensitive): regular (empty string), bold (B), italic (I), underline (U) or any combination. The default value is regular. Bold and italic styles do not apply to Symbol and ZapfDingbats.
	 * @param float $size Font size in points. The default value is the current size. If no size has been specified since the beginning of the document, the value taken is 12.
	 */
	public function setFont(string $family, string $style = '', float $size = 0) : void
	{
		// Select a font; size given in points
		if ($family === '') {
			$family = $this->font_family;
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
			$size = $this->font_size_pt;
		}

		// Test if font is already selected
		if ($this->font_family === $family && $this->font_style === $style && $this->font_size_pt === $size) {
			return;
		}

		// Test if font is already loaded
		$fontkey = $family . $style;
		if (!isset($this->fonts[$fontkey])) {
			// Test if one of the core fonts
			if ($family === 'arial') {
				$family = 'helvetica';
			}
			if (in_array($family, self::CORE_FONTS)) {
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
		$this->font_family = $family;
		$this->font_style = $style;
		$this->font_size_pt = $size;
		$this->font_size = $size / $this->scale_factor;
		$this->current_font = &$this->fonts[$fontkey];
		if ($this->page > 0) {
			$this->out(sprintf('BT /F%d %.2F Tf ET', $this->current_font['i'], $this->font_size_pt));
		}
	}

	/**
	 * Defines the size of the current font.
	 *
	 * @param float $size The size (in points)
	 */
	public function setFontSize(float $size) : void
	{
		// Set font size in points
		if ($this->font_size_pt === $size) {
			return;
		}
		$this->font_size_pt = $size;
		$this->font_size = $size / $this->scale_factor;
		if ($this->page > 0) {
			$this->out(sprintf('BT /F%d %.2F Tf ET', $this->current_font['i'], $this->font_size_pt));
		}
	}

	/**
	 * Creates a new internal link and returns its identifier.
	 * An internal link is a clickable area which directs to another place within the document.
	 * The identifier can then be passed to Cell(), Write(), Image() or Link().
	 * The destination is defined with SetLink().
	 *
	 * @return int
	 */
	public function addLink() : int
	{
		// Create a new internal link
		$n = count($this->links) + 1;
		$this->links[$n] = array(0, 0);
		return $n;
	}

	/**
	 * Defines the page and position a link points to.
	 *
	 * @param int $link The link identifier returned by AddLink()
	 * @param float $y Ordinate of target position; -1 indicates the current position. The default value is 0 (top of page).
	 * @param int $page Number of target page; -1 indicates the current page. This is the default value.
	 */
	public function setLink(int $link, float $y = 0, int $page = -1) : void
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

	/**
	 * Puts a link on a rectangular area of the page.
	 * Text or image links are generally put via Cell(), Write() or Image(), but this method can be useful for instance to define a clickable area inside an image.
	 *
	 * @param float $x Abscissa of the upper-left corner of the rectangle
	 * @param float $y Ordinate of the upper-left corner of the rectangle
	 * @param float $w Width of the rectangle
	 * @param float $h Height of the rectangle
	 * @param mixed $link URL or identifier returned by AddLink()
	 */
	public function link(float $x, float $y, float $w, float $h, $link) : void
	{
		// Put a link on the page
		$this->page_links[$this->page][] = array(
			$x * $this->scale_factor,
			$this->hPt - $y * $this->scale_factor,
			$w * $this->scale_factor,
			$h * $this->scale_factor,
			$link
		);
	}

	/**
	 * Prints a character string.
	 * The origin is on the left of the first character, on the baseline.
	 * This method allows to place a string precisely on the page, but it is usually easier to use Cell(), MultiCell() or Write() which are the standard methods to print text.
	 *
	 * @param float $x Abscissa of the origin
	 * @param float $y Ordinate of the origin
	 * @param string $txt String to print
	 */
	public function text(float $x, float $y, string $txt) : void
	{
		// Output a string
		if (!isset($this->current_font)) {
			throw new FPDFException('No font has been set');
		}
		$s = sprintf('BT %.2F %.2F Td (%s) Tj ET', $x * $this->scale_factor, ($this->h - $y) * $this->scale_factor, $this->escape($txt));
		if ($this->underline && $txt !== '') {
			$s .= ' ' . $this->dounderline($x, $y, $txt);
		}
		if ($this->color_flag) {
			$s = 'q ' . $this->text_color . ' ' . $s . ' Q';
		}
		$this->out($s);
	}

	/**
	 * Whenever a page break condition is met, the method is called, and the break is issued or not depending on the returned value.
	 * The default implementation returns a value according to the mode selected by SetAutoPageBreak().
	 * This method is called automatically and should not be called directly by the application.
	 *
	 * @return bool
	 */
	public function acceptPageBreak() : bool
	{
		// Accept automatic page break or not
		return $this->auto_page_break;
	}

	/**
	 * Prints a cell (rectangular area) with optional borders, background color and character string.
	 * The upper-left corner of the cell corresponds to the current position.
	 * The text can be aligned or centered.
	 * After the call, the current position moves to the right or to the next line.
	 * It is possible to put a link on the text.
	 *
	 * If automatic page breaking is enabled and the cell goes beyond the limit, a page break is done before outputting.
	 *
	 * @param float $w Cell width. If 0, the cell extends up to the right margin.
	 * @param float $h Cell height. Default value: 0.
	 * @param string $txt String to print. Default value: empty string.
	 * @param mixed $border Indicates if borders must be drawn around the cell. The value can be either a number: no border (0), frame (1) or a string containing some or all of the following characters (in any order): left (L), top (T), right (R), bottom (B). Default value: 0.
	 * @param int $ln Indicates where the current position should go after the call. Possible values are: to the right (0), to the beginning of the next line (1), below (2). Putting 1 is equivalent to putting 0 and calling Ln() just after. Default value: 0.
	 * @param string $align Allows to center or align the text. Possible values are: left align (L, default value), center (C), right align (R) or empty string.
	 * @param bool $fill Indicates if the cell background must be painted (true) or transparent (false). Default value: false.
	 * @param mixed $link URL or identifier returned by AddLink().
	 */
	public function cell(float $w, float $h = 0, string $txt = '', $border = 0, int $ln = 0, string $align = '', bool $fill = false, $link = '') : void
	{
		// Output a cell
		$k = $this->scale_factor;
		if ($this->y + $h > $this->page_break_trigger && !$this->in_header && !$this->in_footer && $this->acceptPageBreak()) {
			// Automatic page break
			$x = $this->x;
			$ws = $this->ws;
			if ($ws > 0) {
				$this->ws = 0;
				$this->out('0 Tw');
			}
			$this->AddPage($this->cur_orientation, $this->cur_page_size, $this->cur_rotation);
			$this->x = $x;
			if ($ws > 0) {
				$this->ws = $ws;
				$this->out(sprintf('%.3F Tw', $ws * $k));
			}
		}
		if ($w === 0) {
			$w = $this->w - $this->right_margin - $this->x;
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
			if (!isset($this->current_font)) {
				throw new FPDFException('No font has been set');
			}
			if ($align === 'R') {
				$dx = $w - $this->cMargin - $this->getStringWidth($txt);
			} elseif ($align === 'C') {
				$dx = ($w - $this->getStringWidth($txt)) / 2;
			} else {
				$dx = $this->cMargin;
			}

			if ($this->color_flag) {
				$s .= 'q ' . $this->text_color . ' ';
			}
			$s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->font_size)) * $k, $this->escape($txt));
			if ($this->underline) {
				$s .= ' ' . $this->dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->font_size, $txt);
			}
			if ($this->color_flag) {
				$s .= ' Q';
			}
			if ($link) {
				$this->link($this->x + $dx, $this->y + .5 * $h - .5 * $this->font_size, $this->getStringWidth($txt), $this->font_size, $link);
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
				$this->x = $this->left_margin;
			}
		} else {
			$this->x += $w;
		}
	}

	/**
	 * This method allows printing text with line breaks.
	 * They can be automatic (as soon as the text reaches the right border of the cell) or explicit (via the \n character).
	 * As many cells as necessary are output, one below the other.
	 *
	 * Text can be aligned, centered or justified.
	 * The cell block can be framed and the background painted.
	 *
	 * @param float $w Width of cells. If 0, they extend up to the right margin of the page
	 * @param float $h Height of cells
	 * @param string $txt String to print
	 * @param mixed $border Indicates if borders must be drawn around the cell block. The value can be either a number: no border (0), frame (1) or a string containing some or all of the following characters (in any order): left (L), top (T), right (R), bottom (B). Default value: 0.
	 * @param string $align Sets the text alignment. Possible values are: left alignment (L), center (C), right alignment (R), justification (J, default value)
	 * @param bool $fill Indicates if the cell background must be painted (true) or transparent (false). Default value: false.
	 */
	public function multiCell(float $w, float $h, string $txt, $border = 0, string $align = 'J', bool $fill = false) : void
	{
		// Output text with automatic or explicit line breaks
		if (!isset($this->current_font)) {
			throw new FPDFException('No font has been set');
		}

		$cw = &$this->current_font['cw'];

		if ($w === 0) {
			$w = $this->w - $this->right_margin - $this->x;
		}
		$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->font_size;
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
						$this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->font_size / ($ns - 1) : 0;
						$this->out(sprintf('%.3F Tw', $this->ws * $this->scale_factor));
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
		$this->x = $this->left_margin;
	}

	/**
	 * This method prints text from the current position.
	 * When the right margin is reached (or the \n character is met) a line break occurs and text continues from the left margin.
	 * Upon method exit, the current position is left just at the end of the text.
	 * It is possible to put a link on the text.
	 *
	 * @param float $h Line height
	 * @param string $txt String to print
	 * @param mixed $link URL or identifier returned by AddLink()
	 */
	public function write(float $h, string $txt, $link = '') : void
	{
		// Output text in flowing mode
		if (!isset($this->current_font)) {
			throw new FPDFException('No font has been set');
		}

		$cw = &$this->current_font['cw'];
		$w = $this->w - $this->right_margin - $this->x;
		$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->font_size;
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
				if ($nl === 1) {
					$this->x = $this->left_margin;
					$w = $this->w - $this->right_margin - $this->x;
					$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->font_size;
				}
				$nl++;
				continue;
			}
			if ($c === ' ') {
				$sep = $i;
			}
			$l += $cw[$c];
			if ($l > $wmax) {
				// Automatic line break
				if ($sep === -1) {
					if ($this->x > $this->left_margin) {
						// Move to next line
						$this->x = $this->left_margin;
						$this->y += $h;
						$w = $this->w - $this->right_margin - $this->x;
						$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->font_size;
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
					$this->x = $this->left_margin;
					$w = $this->w - $this->right_margin - $this->x;
					$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->font_size;
				}
				$nl++;
			} else {
				$i++;
			}
		}

		// Last chunk
		if ($i != $j) {
			$this->cell($l / 1000 * $this->font_size, $h, substr($s, $j), 0, 0, '', false, $link);
		}
	}

	/**
	 * Performs a line break.
	 * The current abscissa goes back to the left margin and the ordinate increases by the amount passed in parameter.
	 *
	 * @param float|null $h The height of the break. By default, the value equals the height of the last printed cell.
	 */
	public function ln(float $h = null) : void
	{
		// Line feed; default value is the last cell height
		$this->x = $this->left_margin;
		if ($h === null) {
			$this->y += $this->lasth;
		} else {
			$this->y += $h;
		}
	}

	/**
	 * Puts an image. The size it will take on the page can be specified in different ways:
	 * explicit width and height (expressed in user unit or dpi),
	 * one explicit dimension, the other being calculated automatically in order to keep the original proportions,
	 * no explicit dimension, in which case the image is put at 96 dpi.
	 *
	 * Supported formats are JPEG, PNG and GIF. The GD extension is required for GIF.
	 *
	 * For JPEGs, all flavors are allowed:
	 * gray scales,
	 * true colors (24 bits),
	 * CMYK (32 bits).
	 *
	 * For PNGs, are allowed:
	 * gray scales on at most 8 bits (256 levels),
	 * indexed colors,
	 * true colors (24 bits).
	 *
	 * For GIFs: in case of an animated GIF, only the first frame is displayed.
	 * Transparency is supported.
	 * The format can be specified explicitly or inferred from the file extension.
	 * It is possible to put a link on the image.
	 *
	 * Remark: if an image is used several times, only one copy is embedded in the file.
	 *
	 * @param string $file Path or URL of the image.
	 * @param float|null $x Abscissa of the upper-left corner. If not specified or equal to null, the current abscissa is used.
	 * @param float|null $y Ordinate of the upper-left corner. If not specified or equal to null, the current ordinate is used; moreover, a page break is triggered first if necessary (in case automatic page breaking is enabled) and, after the call, the current ordinate is moved to the bottom of the image.
	 * @param float $w Width of the image in the page. There are three cases: If the value is positive, it represents the width in user unit, If the value is negative, the absolute value represents the horizontal resolution in dpi, If the value is not specified or equal to zero, it is automatically calculated.
	 * @param float $h Height of the image in the page. There are three cases: If the value is positive, it represents the height in user unit, If the value is negative, the absolute value represents the vertical resolution in dpi, If the value is not specified or equal to zero, it is automatically calculated.
	 * @param string $type Image format. Possible values are (case insensitive): JPG, JPEG, PNG and GIF. If not specified, the type is inferred from the file extension.
	 * @param mixed $link URL or identifier returned by AddLink().
	 */
	public function image(string $file, float $x = null, float $y = null, float $w = 0, float $h = 0, string $type = '', $link = '') : void
	{
		// Put an image on the page
		if ($file === '') {
			throw new \InvalidArgumentException('Image file name is empty');
		}

		if (!isset($this->images[$file])) {
			// First use of this image, get info
			if ($type === '') {
				$pos = strrpos($file, '.');
				if (!$pos) {
					throw new FPDFException('Image file has no extension and no type was specified: ' . $file);
				}
				$type = substr($file, $pos + 1);
			}
			$type = strtolower($type);
			if ($type === 'jpeg') {
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
		if ($w === 0 && $h === 0) {
			// Put image at 96 dpi
			$w = -96;
			$h = -96;
		}
		if ($w < 0) {
			$w = -$info['w'] * 72 / $w / $this->scale_factor;
		}
		if ($h < 0) {
			$h = -$info['h'] * 72 / $h / $this->scale_factor;
		}
		if ($w === 0) {
			$w = $h * $info['w'] / $info['h'];
		}
		if ($h === 0) {
			$h = $w * $info['h'] / $info['w'];
		}

		// Flowing mode
		if ($y === null) {
			if ($this->y + $h > $this->page_break_trigger && !$this->in_header && !$this->in_footer && $this->acceptPageBreak()) {
				// Automatic page break
				$x2 = $this->x;
				$this->AddPage($this->cur_orientation, $this->cur_page_size, $this->cur_rotation);
				$this->x = $x2;
			}
			$y = $this->y;
			$this->y += $h;
		}

		if ($x === null) {
			$x = $this->x;
		}
		$this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->scale_factor, $h * $this->scale_factor, $x * $this->scale_factor, ($this->h - ($y + $h)) * $this->scale_factor, $info['i']));
		if ($link) {
			$this->link($x, $y, $w, $h, $link);
		}
	}

	/**
	 * Returns the current page width.
	 *
	 * @return float
	 */
	public function getPageWidth() : float
	{
		// Get current page width
		return $this->w;
	}

	/**
	 * Returns the current page height.
	 *
	 * @return float
	 */
	public function getPageHeight() : float
	{
		// Get current page height
		return $this->h;
	}

	/**
	 * Returns the abscissa of the current position.
	 *
	 * @return float
	 */
	public function getX() : float
	{
		// Get x position
		return $this->x;
	}

	/**
	 * Defines the abscissa of the current position.
	 * If the passed value is negative, it is relative to the right of the page.
	 *
	 * @param float $x The value of the abscissa
	 */
	public function setX(float $x)
	{
		// Set x position
		if ($x >= 0) {
			$this->x = $x;
		} else {
			$this->x = $this->w + $x;
		}
	}

	/**
	 * Returns the ordinate of the current position.
	 *
	 * @return float
	 */
	public function getY() : float
	{
		// Get y position
		return $this->y;
	}

	/**
	 * Sets the ordinate and optionally moves the current abscissa back to the left margin.
	 * If the value is negative, it is relative to the bottom of the page.
	 *
	 * @param float $y The value of the ordinate
	 * @param bool $resetX Whether to reset the abscissa. Default value: true.
	 */
	public function setY(float $y, bool $resetX = true)
	{
		// Set y position and optionally reset x
		if ($y >= 0) {
			$this->y = $y;
		} else {
			$this->y = $this->h + $y;
		}

		if ($resetX) {
			$this->x = $this->left_margin;
		}
	}

	/**
	 * Defines the abscissa and ordinate of the current position.
	 * If the passed values are negative, they are relative respectively to the right and bottom of the page.
	 *
	 * @param float $x The value of the abscissa
	 * @param float $y The value of the ordinate
	 */
	public function setXY(float $x, float $y) : void
	{
		// Set x and y positions
		$this->setX($x);
		$this->setY($y, false);
	}

	/**
	 * Send the document to a given destination: browser, file or string.
	 * In the case of a browser, the PDF viewer may be used or a download may be forced.
	 * The method first calls Close() if necessary to terminate the document.
	 *
	 * @param string $dest Destination where to send the document. It can be one of the following: "I" sends the file inline to the browser (default). The PDF viewer is used if available, "D" sends to the browser and force a file download with the name given by name, "F":" saves to a local file with the name given by name (may include a path), "S":" returns the document as a string.
	 * @param string $name The name of the file. It is ignored in case of destination S. The default value is "doc.pdf".
	 * @param bool $isUTF8 Indicates if name is encoded in ISO-8859-1 (false) or UTF-8 (true). Only used for destinations I and D. The default value is false.
	 *
	 * @return string
	 */
	public function output(string $dest = '', string $name = 'doc.pdf', bool $isUTF8 = false) : string
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

		switch (strtoupper($dest)) {
			case 'I':
				// Send to standard output
				$this->checkoutput();
				if (PHP_SAPI !== 'cli') {
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
				if (!file_put_contents($name, $this->buffer)) {
					throw new FPDFException('Unable to create output file: ' . $name);
				}
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

	/**
	 * @param float[] | string $size Known page size as string or two-dimensional array containing size
	 * @return float[]
	 */
	protected function getPageSize($size) : array
	{
		if (is_string($size)) {
			$size = strtolower($size);
			if (!array_key_exists($size, self::PAGE_SIZES)) {
				throw new \InvalidArgumentException('Unknown page size: ' . $size);
			}

			$found_size = self::PAGE_SIZES[$size];
			return array($found_size[0] / $this->scale_factor, $found_size[1] / $this->scale_factor);
		} elseif (is_array($size) && isset($size[0]) && isset($size[1])) {
			if ($size[0] > $size[1]) {
				return array($size[1], $size[0]);
			} else {
				return $size;
			}
		} else {
			throw new \InvalidArgumentException('Size no string nor float-array');
		}
	}

	protected function beginPage(string $orientation, $size, int $rotation = 0) : void
	{
		$this->page++;
		$this->pages[$this->page] = '';
		$this->state = 2;
		$this->x = $this->left_margin;
		$this->y = $this->top_margin;
		$this->font_family = '';

		// Check page size and orientation
		if ($orientation === '') {
			$orientation = $this->def_orientation;
		} else {
			$orientation = strtoupper($orientation[0]);
		}

		if ($size === '') {
			$size = $this->def_page_size;
		} else {
			$size = $this->getPageSize($size);
		}

		if ($orientation !== $this->cur_orientation || $size[0] !== $this->cur_page_size[0] || $size[1] !== $this->cur_page_size[1]) {
			// New size or orientation
			if ($orientation === 'P') {
				$this->w = $size[0];
				$this->h = $size[1];
			} else {
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w * $this->scale_factor;
			$this->hPt = $this->h * $this->scale_factor;
			$this->page_break_trigger = $this->h - $this->break_margin;
			$this->cur_orientation = $orientation;
			$this->cur_page_size = $size;
		}
		if ($orientation !== $this->def_orientation || $size[0] !== $this->def_page_size[0] || $size[1] !== $this->def_page_size[1]) {
			$this->page_info[$this->page]['size'] = array($this->wPt, $this->hPt);
		}

		// Page rotation
		if ($rotation % 90 !== 0) {
			throw new \InvalidArgumentException('Incorrect rotation value: ' . $rotation . '. Only integers divided by 90 are allowed here');
		}
		$this->cur_rotation = $rotation;
		$this->page_info[$this->page]['rotation'] = $rotation;
	}

	protected function endPage() : void
	{
		$this->state = 1;
	}

	protected function isAscii(string $str) : bool
	{
		// Test if string is ASCII
		$nb = strlen($str);
		for ($i = 0; $i < $nb; $i++) {
			if (ord($str[$i]) > 127) {
				return false;
			}
		}
		return true;
	}

	protected function httpencode(string $param, string $value, bool $isUTF8) : string
	{
		// Encode HTTP header field parameter
		if ($this->isAscii($value)) {
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

	final protected function escape(string $s) : string
	{
		// Escape special characters
		if (strpos($s, '(') !== false || strpos($s, ')') !== false || strpos($s, '\\') !== false || strpos($s, "\r") !== false) {
			return str_replace(array('\\', '(', ')', "\r"), array('\\\\', '\\(', '\\)', '\\r'), $s);
		} else {
			return $s;
		}
	}

	protected function textstring(string $s) : string
	{
		// Format a text string
		if (!$this->isAscii($s)) {
			$s = $this->UTF8toUTF16($s);
		}
		return '(' . $this->escape($s) . ')';
	}

	protected function dounderline($x, $y, string $txt) : string
	{
		// Underline text
		$up = $this->current_font['up'];
		$ut = $this->current_font['ut'];
		$w = $this->getStringWidth($txt) + $this->ws * substr_count($txt, ' ');
		return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->scale_factor, ($this->h - ($y - $up / 1000 * $this->font_size)) * $this->scale_factor, $w * $this->scale_factor, -$ut / 1000 * $this->font_size_pt);
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

		return array(
			'w' => $a[0],
			'h' => $a[1],
			'cs' => $colspace,
			'bpc' => $bpc,
			'f' => 'DCTDecode',
			'data' => $data
		);
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
					$trns = array(
						ord(substr($t, 1, 1)),
						ord(substr($t, 3, 1)),
						ord(substr($t, 5, 1))
					);
				} else {
					$pos = strpos($t, chr(0));
					if ($pos !== false) {
						$trns = array($pos);
					}
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

		$info = array(
			'w' => $w,
			'h' => $h,
			'cs' => $colspace,
			'bpc' => $bpc,
			'f' => 'FlateDecode',
			'dp' => $dp,
			'pal' => $pal,
			'trns' => $trns
		);

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
			$this->with_alpha = true;
			if ($this->PDFVersion < '1.4') {
				$this->PDFVersion = '1.4';
			}
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

	final protected function out(string $s) : void
	{
		// Add a line to the document
		if ($this->state === 2) {
			$this->pages[$this->page] .= $s . PHP_EOL;
		} elseif ($this->state === 1) {
			$this->buffer .= $s . PHP_EOL;
		} elseif ($this->state === 0) {
			throw new FPDFException('No page has been added yet');
		} elseif ($this->state === 3) {
			throw new FPDFException('The document is closed');
		}
	}

	final protected function bufferOutput(string $s) : void
	{
		$this->buffer .= $s . PHP_EOL;
	}

	final protected function bufferSize() : int
	{
		return strlen($this->buffer);
	}

	final protected function bufferPreparation(int $n = null) : void
	{
		// Begin a new object
		if ($n === null) {
			$n = ++$this->n;
		}

		$this->offsets[$n] = $this->bufferSize();
		$this->bufferOutput($n . ' 0 obj');
	}

	private function bufferStream(string $data) : void
	{
		$this->buffer .=
				'stream' . PHP_EOL
				. $data . PHP_EOL
				. 'endstream' . PHP_EOL;
	}

	private function bufferStreamObject(string $data) : void
	{
		if ($this->compress) {
			$entries = '/Filter /FlateDecode ';
			$data = gzcompress($data);
		} else {
			$entries = '';
		}
		$entries .= '/Length ' . strlen($data);
		$this->bufferPreparation();
		$this->bufferOutput('<<' . $entries . '>>');
		$this->bufferStream($data);
		$this->bufferOutput('endobj');
	}

	private function bufferPage(int $n) : void
	{
		$this->bufferPreparation();
		$this->bufferOutput('<</Type /Page');
		$this->bufferOutput('/Parent 1 0 R');

		if (isset($this->page_info[$n]['size'])) {
			$this->bufferOutput(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->page_info[$n]['size'][0], $this->page_info[$n]['size'][1]));
		}
		if (isset($this->page_info[$n]['rotation'])) {
			$this->bufferOutput('/Rotate ' . $this->page_info[$n]['rotation']);
		}

		$this->bufferOutput('/Resources 2 0 R');

		if (isset($this->page_links[$n])) {
			// Links
			$annots = '/Annots [';
			foreach ($this->page_links[$n] as $pl) {
				$rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
				$annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
				if (is_string($pl[4])) {
					$annots .= '/A <</S /URI /URI ' . $this->textstring($pl[4]) . '>>>>';
				} else {
					$l = $this->links[$pl[4]];
					if (isset($this->page_info[$l[0]]['size'])) {
						$h = $this->page_info[$l[0]]['size'][1];
					} else {
						$h = ($this->def_orientation === 'P') ? $this->def_page_size[1] * $this->scale_factor : $this->def_page_size[0] * $this->scale_factor;
					}
					$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->page_info[$l[0]]['n'], $h - $l[1] * $this->scale_factor);
				}
			}
			$this->bufferOutput($annots . ']');
		}

		if ($this->with_alpha) {
			$this->bufferOutput('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
		}
		$this->bufferOutput('/Contents ' . ($this->n + 1) . ' 0 R>>');
		$this->bufferOutput('endobj');

		// Page content
		if (!empty($this->alias_NbPages)) {
			$this->pages[$n] = str_replace($this->alias_NbPages, $this->page, $this->pages[$n]);
		}
		$this->bufferStreamObject($this->pages[$n]);
	}

	private function bufferPages() : void
	{
		$nb = $this->page;
		for ($n = 1; $n <= $nb; $n++) {
			$this->page_info[$n]['n'] = $this->n + 1 + 2 * ($n - 1);
		}
		for ($n = 1; $n <= $nb; $n++) {
			$this->bufferPage($n);
		}

		// Pages root
		$this->bufferPreparation(1);
		$this->bufferOutput('<</Type /Pages');
		$kids = '/Kids [';
		for ($n = 1; $n <= $nb; $n++) {
			$kids .= $this->page_info[$n]['n'] . ' 0 R ';
		}
		$this->bufferOutput($kids . ']');
		$this->bufferOutput('/Count ' . $nb);
		if ($this->def_orientation === 'P') {
			$w = $this->def_page_size[0];
			$h = $this->def_page_size[1];
		} else {
			$w = $this->def_page_size[1];
			$h = $this->def_page_size[0];
		}
		$this->bufferOutput(sprintf('/MediaBox [0 0 %.2F %.2F]', $w * $this->scale_factor, $h * $this->scale_factor));
		$this->bufferOutput('>>');
		$this->bufferOutput('endobj');
	}

	private function bufferFonts() : void
	{
		foreach ($this->font_files as $file => $info) {
			// Font file embedding
			$this->bufferPreparation();
			$this->font_files[$file]['n'] = $this->n;
			$font = file_get_contents($this->fontpath . $file, true);
			if (!$font) {
				throw new FPDFException('Font file not found: ' . $file);
			}
			$compressed = substr($file, -2) === '.z';
			if (!$compressed && isset($info['length2'])) {
				$font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
			}
			$this->bufferOutput('<</Length ' . strlen($font));
			if ($compressed) {
				$this->bufferOutput('/Filter /FlateDecode');
			}
			$this->bufferOutput('/Length1 ' . $info['length1']);
			if (isset($info['length2'])) {
				$this->bufferOutput('/Length2 ' . $info['length2'] . ' /Length3 0');
			}
			$this->bufferOutput('>>');
			$this->bufferStream($font);
			$this->bufferOutput('endobj');
		}

		foreach ($this->fonts as $font_key => $font) {
			// Encoding
			if (isset($font['diff'])) {
				if (!isset($this->encodings[$font['enc']])) {
					$this->bufferPreparation();
					$this->bufferOutput('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $font['diff'] . ']>>');
					$this->bufferOutput('endobj');
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
					$cmap = $this->toUnicodecMap($font['uv']);
					$this->bufferStreamObject($cmap);
					$this->cmaps[$cmapkey] = $this->n;
				}
			}
			// Font object
			$this->fonts[$font_key]['n'] = $this->n + 1;
			$type = $font['type'];
			$name = $font['name'];
			if ($font['subsetted']) {
				$name = 'AAAAAA+' . $name;
			}

			if ($type === 'Core') {
				// Core font
				$this->bufferPreparation();
				$this->bufferOutput('<</Type /Font');
				$this->bufferOutput('/BaseFont /' . $name);
				$this->bufferOutput('/Subtype /Type1');
				if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
					$this->bufferOutput('/Encoding /WinAnsiEncoding');
				}
				if (isset($font['uv'])) {
					$this->bufferOutput('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
				}
				$this->bufferOutput('>>');
				$this->bufferOutput('endobj');
			} elseif ($type === 'Type1' || $type === 'TrueType') {
				// Additional Type1 or TrueType/OpenType font
				$this->bufferPreparation();
				$this->bufferOutput('<</Type /Font');
				$this->bufferOutput('/BaseFont /' . $name);
				$this->bufferOutput('/Subtype /' . $type);
				$this->bufferOutput('/FirstChar 32 /LastChar 255');
				$this->bufferOutput('/Widths ' . ($this->n + 1) . ' 0 R');
				$this->bufferOutput('/FontDescriptor ' . ($this->n + 2) . ' 0 R');

				if (isset($font['diff'])) {
					$this->bufferOutput('/Encoding ' . $this->encodings[$font['enc']] . ' 0 R');
				} else {
					$this->bufferOutput('/Encoding /WinAnsiEncoding');
				}

				if (isset($font['uv'])) {
					$this->bufferOutput('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
				}
				$this->bufferOutput('>>');
				$this->bufferOutput('endobj');

				// Widths
				$this->bufferPreparation();
				$cw = &$font['cw'];
				$s = '[';
				for ($i = 32; $i <= 255; $i++) {
					$s .= $cw[chr($i)] . ' ';
				}
				$this->bufferOutput($s . ']');
				$this->bufferOutput('endobj');
				// Descriptor
				$this->bufferPreparation();
				$s = '<</Type /FontDescriptor /FontName /' . $name;

				foreach ($font['desc'] as $desc_key => $desc_val) {
					$s .= ' /' . $desc_key . ' ' . $desc_val;
				}

				if (!empty($font['file'])) {
					$s .= ' /FontFile' . ($type === 'Type1' ? '' : '2') . ' ' . $this->font_files[$font['file']]['n'] . ' 0 R';
				}
				$this->bufferOutput($s . '>>');
				$this->bufferOutput('endobj');
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

	private function toUnicodecMap(array $uv) : string
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
		$s .= 'end';

		return $s;
	}

	private function bufferImages() : void
	{
		foreach (array_keys($this->images) as $file) {
			$this->bufferImage($this->images[$file]);
			unset($this->images[$file]['data']);
			unset($this->images[$file]['smask']);
		}
	}

	private function bufferImage(array &$info) : void
	{
		$this->bufferPreparation();
		$info['n'] = $this->n;
		$this->buffer .= '<</Type /XObject' . PHP_EOL;
		$this->buffer .= '/Subtype /Image' . PHP_EOL;
		$this->buffer .= '/Width ' . $info['w'] . PHP_EOL;
		$this->buffer .= '/Height ' . $info['h'] . PHP_EOL;

		if ($info['cs'] === 'Indexed') {
			$this->bufferOutput('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
		} else {
			$this->bufferOutput('/ColorSpace /' . $info['cs']);
			if ($info['cs'] === 'DeviceCMYK') {
				$this->bufferOutput('/Decode [1 0 1 0 1 0 1 0]');
			}
		}

		$this->bufferOutput('/BitsPerComponent ' . $info['bpc']);
		if (isset($info['f'])) {
			$this->bufferOutput('/Filter /' . $info['f']);
		}
		if (isset($info['dp'])) {
			$this->bufferOutput('/DecodeParms <<' . $info['dp'] . '>>');
		}
		if (isset($info['trns']) && is_array($info['trns'])) {
			$trns = '';
			foreach ($info['trns'] as $info_trns) {
				$trns .= $info_trns . ' ' . $info_trns . ' ';
			}
			$this->bufferOutput('/Mask [' . $trns . ']');
		}

		if (isset($info['smask'])) {
			$this->bufferOutput('/SMask ' . ($this->n + 1) . ' 0 R');
		}
		$this->bufferOutput('/Length ' . strlen($info['data']) . '>>');
		$this->bufferStream($info['data']);
		$this->bufferOutput('endobj');

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
			$this->bufferImage($smask);
		}

		// Palette
		if ($info['cs'] === 'Indexed') {
			$this->bufferStreamObject($info['pal']);
		}
	}

	private function bufferXObjectDict() : void
	{
		foreach ($this->images as $image) {
			$this->buffer .= '/I' . $image['i'] . ' ' . $image['n'] . ' 0 R' . PHP_EOL;
		}
	}

	private function bufferResourceDict() : void
	{
		$this->buffer .= '/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]' . PHP_EOL;
		$this->buffer .= '/Font <<' . PHP_EOL;
		foreach ($this->fonts as $font) {
			$this->buffer .= '/F' . $font['i'] . ' ' . $font['n'] . ' 0 R' . PHP_EOL;
		}
		$this->buffer .= '>>' . PHP_EOL;
		$this->buffer .= '/XObject <<' . PHP_EOL;
		$this->bufferXObjectDict();
		$this->buffer .= '>>' . PHP_EOL;
	}

	private function bufferResources() : void
	{
		$this->bufferFonts();
		$this->bufferImages();

		// Resource dictionary
		$this->bufferPreparation(2);
		$this->buffer .= '<<' . PHP_EOL;
		$this->bufferResourceDict();
		$this->buffer .= '>>' . PHP_EOL;
		$this->buffer .= 'endobj' . PHP_EOL;
	}

	private function bufferMetadata() : void
	{
		$this->bufferPreparation();
		$this->buffer .= '<<' . PHP_EOL;

		$this->buffer .= '/Producer ' . $this->textstring('FPDF ' . self::VERSION) . PHP_EOL;
		$this->buffer .= '/CreationDate ' . $this->textstring(date('YmdHis')) . PHP_EOL;

		if ($this->document_author !== null) {
			$this->buffer .= '/Author ' . $this->textstring($this->document_author) . PHP_EOL;
		}
		if ($this->document_creator !== null) {
			$this->buffer .= '/Creator ' . $this->textstring($this->document_creator) . PHP_EOL;
		}
		if ($this->document_keywords !== null) {
			$this->buffer .= '/Keywords ' . $this->textstring($this->document_keywords) . PHP_EOL;
		}
		if ($this->document_subject !== null) {
			$this->buffer .= '/Subject ' . $this->textstring($this->document_subject) . PHP_EOL;
		}
		if ($this->document_title !== null) {
			$this->buffer .= '/Title ' . $this->textstring($this->document_title) . PHP_EOL;
		}

		$this->buffer .= '>>' . PHP_EOL;
		$this->buffer .= 'endobj' . PHP_EOL;
	}

	private function bufferCatalog() : void
	{
		$this->bufferPreparation();
		$this->buffer .= '<<' . PHP_EOL;

		$n = $this->page_info[1]['n'];
		$this->buffer .= '/Type /Catalog' . PHP_EOL;
		$this->buffer .= '/Pages 1 0 R' . PHP_EOL;

		if ($this->zoom_mode === 'fullpage') {
			$this->buffer .= '/OpenAction [' . $n . ' 0 R /Fit]' . PHP_EOL;
		} elseif ($this->zoom_mode === 'fullwidth') {
			$this->buffer .= '/OpenAction [' . $n . ' 0 R /FitH null]' . PHP_EOL;
		} elseif ($this->zoom_mode === 'real') {
			$this->buffer .= '/OpenAction [' . $n . ' 0 R /XYZ null null 1]' . PHP_EOL;
		} elseif (is_int($this->zoom_mode)) {
			$this->buffer .= '/OpenAction [' . $n . ' 0 R /XYZ null null ' . sprintf('%.2F', $this->zoom_mode / 100) . ']' . PHP_EOL;
		}

		if ($this->layout_mode === 'single') {
			$this->buffer .= '/PageLayout /SinglePage' . PHP_EOL;
		} elseif ($this->layout_mode === 'continuous') {
			$this->buffer .= '/PageLayout /OneColumn' . PHP_EOL;
		} elseif ($this->layout_mode === 'two') {
			$this->buffer .= '/PageLayout /TwoColumnLeft' . PHP_EOL;
		}

		$this->buffer .= '>>' . PHP_EOL;
		$this->buffer .= 'endobj' . PHP_EOL;
	}

	private function bufferTrailer() : void
	{
		$this->buffer .= 'trailer' . PHP_EOL;
		$this->buffer .= '<<' . PHP_EOL;

		$this->buffer .= '/Size ' . ($this->n + 1) . PHP_EOL;
		$this->buffer .= '/Root ' . $this->n . ' 0 R' . PHP_EOL;
		$this->buffer .= '/Info ' . ($this->n - 1) . ' 0 R' . PHP_EOL;

		$this->buffer .= '>>' . PHP_EOL;
	}

	private function endDoc() : void
	{
		$this->buffer .= '%PDF-' . $this->PDFVersion . PHP_EOL;

		$this->bufferPages();
		$this->bufferResources();

		// Metadata
		$this->bufferMetadata();

		// Catalog
		$this->bufferCatalog();

		// Cross-ref
		$buffer_size = $this->bufferSize();
		$this->buffer .= 'xref' . PHP_EOL;
		$this->buffer .= '0 ' . ($this->n + 1) . PHP_EOL;
		$this->buffer .= '0000000000 65535 f ' . PHP_EOL;
		for ($i = 1; $i <= $this->n; $i++) {
			$this->buffer .= sprintf('%010d 00000 n ', $this->offsets[$i]) . PHP_EOL;
		}

		// Trailer
		$this->bufferTrailer();

		$this->buffer .= 'startxref' . PHP_EOL;
		$this->buffer .= $buffer_size . PHP_EOL;
		$this->buffer .= '%%EOF' . PHP_EOL;

		$this->state = 3;
	}
}


?>
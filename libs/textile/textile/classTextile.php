<?php

/**
 * Textile - A Humane Web Text Generator
 *
 * Copyright (c) 2003-2004, Dean Allen <dean@textism.com>
 * All rights reserved.
 *
 * Thanks to Carlo Zottmann <carlo@g-blog.net> for refactoring
 * Textile's procedural code into a class framework
 *
 * Additions and fixes Copyright (c) 2006    Alex Shiels       http://thresholdstate.com/
 * Additions and fixes Copyright (c) 2010    Stef Dawson       http://stefdawson.com/
 * Additions and fixes Copyright (c) 2010-13 Netcarver         http://github.com/netcarver
 * Additions and fixes Copyright (c) 2011    Jeff Soo          http://ipsedixit.net
 * Additions and fixes Copyright (c) 2012    Robert Wetzlmayr  http://wetzlmayr.com/
 * Additions and fixes Copyright (c) 2012-13 Jukka Svahn       http://rahforum.biz/
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * * Neither the name Textile nor the names of its contributors may be used to
 * endorse or promote products derived from this software without specific
 * prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/*
_________
U S A G E

Block modifier syntax:

    Header: h(1-6).
    Paragraphs beginning with 'hn. ' (where n is 1-6) are wrapped in header tags.
    Example: h1. Header... -> <h1>Header...</h1>

    Paragraph: p. (also applied by default)
    Example: p. Text -> <p>Text</p>

    Blockquote: bq.
    Example: bq. Block quotation... -> <blockquote>Block quotation...</blockquote>

    Blockquote with citation: bq.:http://citation.url
    Example: bq.:http://textism.com/ Text...
    ->    <blockquote cite="http://textism.com">Text...</blockquote>

    Footnote: fn(1-100).
    Example: fn1. Footnote... -> <p id="fn1">Footnote...</p>

    Numeric list: #, ##
    Consecutive paragraphs beginning with # are wrapped in ordered list tags.
    Example: <ol><li>ordered list</li></ol>

    Bulleted list: *, **
    Consecutive paragraphs beginning with * are wrapped in unordered list tags.
    Example: <ul><li>unordered list</li></ul>

    Definition list:
        Terms ;, ;;
        Definitions :, ::
    Consecutive paragraphs beginning with ; or : are wrapped in definition list tags.
    Example: <dl><dt>term</dt><dd>definition</dd></dl>

    Redcloth-style Definition list:
        - Term1 := Definition1
        - Term2 := Extended
          definition =:

Phrase modifier syntax:

           _emphasis_    ->     <em>emphasis</em>
           __italic__    ->     <i>italic</i>
             *strong*    ->     <strong>strong</strong>
             **bold**    ->     <b>bold</b>
         ??citation??    ->     <cite>citation</cite>
       -deleted text-    ->     <del>deleted</del>
      +inserted text+    ->     <ins>inserted</ins>
        ^superscript^    ->     <sup>superscript</sup>
          ~subscript~    ->     <sub>subscript</sub>
               @code@    ->     <code>computer code</code>
          %(bob)span%    ->     <span class="bob">span</span>

        ==notextile==    ->     leave text alone (do not format)

       "linktext":url    ->     <a href="url">linktext</a>
"linktext(title)":url    ->     <a href="url" title="title">linktext</a>
              "$":url    ->     <a href="url">url</a>
       "$(title)":url    ->     <a href="url" title="title">url</a>

           !imageurl!    ->     <img src="imageurl" />
 !imageurl(alt text)!    ->     <img src="imageurl" alt="alt text" />
   !imageurl!:linkurl    ->     <a href="linkurl"><img src="imageurl" /></a>

ABC(Always Be Closing)   ->     <acronym title="Always Be Closing">ABC</acronym>


Linked Notes:
============

    Allows the generation of an automated list of notes with links.

    Linked notes are composed of three parts, a set of named _definitions_, a set of
    _references_ to those definitions and one or more _placeholders_ indicating where
    the consolidated list of notes is to be placed in your document.

    Definitions.
    -----------

    Each note definition must occur in its own paragraph and should look like this...

    note#mynotelabel. Your definition text here.

    You are free to use whatever label you wish after the # as long as it is made up
    of letters, numbers, colon(:) or dash(-).

    References.
    ----------

    Each note reference is marked in your text like this[#mynotelabel] and
    it will be replaced with a superscript reference that links into the list of
    note definitions.

    List Placeholder(s).
    -------------------

    The note list can go anywhere in your document. You have to indicate where
    like this...

    notelist.

    notelist can take attributes (class#id) like this: notelist(class#id).

    By default, the note list will show each definition in the order that they
    are referenced in the text by the _references_. It will show each definition with
    a full list of backlinks to each reference. If you do not want this, you can choose
    to override the backlinks like this...

    notelist(class#id)!.    Produces a list with no backlinks.
    notelist(class#id)^.    Produces a list with only the first backlink.

    Should you wish to have a specific definition display backlinks differently to this
    then you can override the backlink method by appending a link override to the
    _definition_ you wish to customise.

    note#label.    Uses the citelist's setting for backlinks.
    note#label!.   Causes that definition to have no backlinks.
    note#label^.   Causes that definition to have one backlink (to the first ref.)
    note#label*.   Causes that definition to have all backlinks.

    Any unreferenced notes will be left out of the list unless you explicitly state
    you want them by adding a '+'. Like this...

    notelist(class#id)!+. Giving a list of all notes without any backlinks.

    You can mix and match the list backlink control and unreferenced links controls
    but the backlink control (if any) must go first. Like so: notelist^+. , not
    like this: notelist+^.

    Example...
        Scientists say[#lavader] the moon is small.

        note#other. An unreferenced note.

        note#lavader(myliclass). "Proof":url of a small moon.

        notelist(myclass#myid)+.

        Would output (the actual IDs used would be randomised)...

        <p>Scientists say<sup><a href="#def_id_1" id="ref_id_1a">1</sup> the moon is small.</p>

        <ol class="myclass" id="myid">
            <li class="myliclass"><a href="#ref_id_1a"><sup>a</sup></a><span id="def_id_1"> </span><a href="url">Proof</a> of a small moon.</li>
            <li>An unreferenced note.</li>
        </ol>

        The 'a b c' backlink characters can be altered too.
        For example if you wanted the notes to have numeric backlinks starting from 1:

        notelist:1.

Table syntax:

    Simple tables:

        |a|simple|table|row|
        |And|Another|table|row|
        |With an||empty|cell|

        |=. My table caption goes here  (NB. Table captions *must* be the first line of the table else treated as a center-aligned cell.)
        |_. A|_. table|_. header|_.row|
        |A|simple|table|row|

    Tables with attributes:

        table{border:1px solid black}. My table summary here
        {background:#ddd;color:red}. |{}| | | |

    To specify thead / tfoot / tbody groups, add one of these on its own line
    above the row(s) you wish to wrap (you may specify attributes before the dot):

        |^.     # thead
        |-.     # tbody
        |~.     # tfoot

    Column groups:

        |:\3. 100|

        Becomes:
            <colgroup span="3" width="100"></colgroup>

        You can omit either or both of the \N or width values. You may also
        add cells after the colgroup definition to specify col elements with
        span, width, or standard Textile attributes:

        |:. 50|(firstcol). |\2. 250||300|

        Becomes:
            <colgroup width="50">
                <col class="firstcol" />
                <col span="2" width="250" />
                <col />
                <col width="300" />
            </colgroup>

        (Note that, per the HTML specification, you should not add span
        to the colgroup if specifying col elements.)

Applying Attributes:

    Most anywhere Textile code is used, attributes such as arbitrary css style,
    css classes, and ids can be applied. The syntax is fairly consistent.

    The following characters quickly alter the alignment of block elements:

        <  ->  left align     ex. p<. left-aligned para
        >  ->  right align         h3>. right-aligned header 3
        =  ->  centred             h4=. centred header 4
        <> ->  justified         p<>. justified paragraph

    These will change vertical alignment in table cells:

        ^  ->  top           ex. |^. top-aligned table cell|
        -  ->  middle           |-. middle aligned|
        ~  ->  bottom           |~. bottom aligned cell|

    Plain (parentheses) inserted between block syntax and the closing dot-space
    indicate classes and ids:

        p(hector). paragraph -> <p class="hector">paragraph</p>

        p(#fluid). paragraph -> <p id="fluid">paragraph</p>

        (classes and ids can be combined)
        p(hector#fluid). paragraph -> <p class="hector" id="fluid">paragraph</p>

    Curly {brackets} insert arbitrary css style

        p{line-height:18px}. paragraph -> <p style="line-height:18px">paragraph</p>

        h3{color:red}. header 3 -> <h3 style="color:red">header 3</h3>

    Square [brackets] insert language attributes

        p[no]. paragraph -> <p lang="no">paragraph</p>

        %[fr]phrase% -> <span lang="fr">phrase</span>

    Usually Textile block element syntax requires a dot and space before the block
    begins, but since lists don't, they can be styled just using braces

        #{color:blue} one  ->  <ol style="color:blue">
        # big                    <li>one</li>
        # list                    <li>big</li>
                                <li>list</li>
                               </ol>

    Using the span tag to style a phrase

        It goes like this, %{color:red}the fourth the fifth%
              -> It goes like this, <span style="color:red">the fourth the fifth</span>

Ordered List Start & Continuation:

    You can control the start attribute of an ordered list like so;

        #5 Item 5
        # Item 6

    You can resume numbering list items after some intervening anonymous block like so...

        #_ Item 7
        # Item 8
*/

/**
 * Textile parser.
 *
 * @example
 * $parser = new Parser();
 * echo $parser->textileThis('h1. Hello World!');
 */
class Textile
{
    /**
     * Version number.
     *
     * @var string
     */
    protected $ver = '3.5.1';

    /**
     * Pattern for horizontal align.
     *
     * @var string
     */
    protected $hlgn;

    /**
     * Pattern for vertical align.
     *
     * @var string
     */
    protected $vlgn;

    /**
     * Pattern for HTML classes and IDs.
     *
     * @var string
     */
    protected $clas;

    /**
     * Pattern for language attribute.
     *
     * @var string
     */
    protected $lnge;

    /**
     * Pattern for style attribute.
     *
     * @var string
     */
    protected $styl;

    /**
     * ?
     *
     * @var string
     */
    protected $cspn;

    /**
     * ?
     *
     * @var string
     */
    protected $rspn;

    /**
     * ?
     *
     * @var string
     */
    protected $a;

    /**
     * ?
     *
     * @var string
     */
    protected $s;

    /**
     * ?
     *
     * @var string
     */
    protected $c;

    /**
     * Pattern for punctation.
     *
     * @var string
     */
    protected $pnct;

    /**
     * HTML rel attribute used for links.
     *
     * @var string
     */
    protected $rel;

    /**
     * Array of footnotes
     *
     * @var array
     */
    protected $fn;

    /**
     * Shelved content.
     *
     * Stores fragments of the source text that have been parsed
     * and require no more processing.
     *
     * @var array
     */
    protected $shelf = array();

    /**
     * Restricted mode.
     *
     * @var bool
     */
    protected $restricted = false;

    /**
     * Disallow images.
     *
     * @var bool
     */
    protected $noimage = false;

    /**
     * Lite mode.
     *
     * @var bool
     */
    protected $lite = false;

    /**
     * Accepted link protocols.
     *
     * @var array
     */
    protected $url_schemes = array();

    /**
     * Patterns for finding glyphs.
     *
     * An array of regex patterns used to find text features
     * such as apostrophes, fractions and em-dashes. Each
     * entry in this array must have a corresponding entry in
     * the $glyph_replace array.
     *
     * @var null|array
     * @see Parser::$glyph_replace
     */
    protected $glyph_search  = null;

    /**
     * Glyph replacements.
     *
     * An array of replacements used to insert typographic glyphs
     * into the text. Each entry must have a corresponding entry in
     * the $glyph_search array and may refer to values captured in
     * the corresponding search regex.
     *
     * @var null|array
     * @see Parser::$glyph_search
     */
    protected $glyph_replace = null;

    /**
     * Indicates whether glyph substitution is required.
     *
     * Dirty flag, set by setSymbol(), indicating the parser needs to
     * rebuild the glyph substitutions before the next parse.
     *
     * @var bool
     * @see Parser::setSymbol()
     */
    protected $rebuild_glyphs = true;

    /**
     * Relative image path.
     *
     * @var string
     */
    protected $relativeImagePrefix = '';

    /**
     * Maximum nesting level for inline elements.
     *
     * @var int
     */
    protected $max_span_depth = 5;

    /**
     * Server document root.
     *
     * @var string
     */
    protected $doc_root;

    /**
     * Target document type.
     *
     * @var string
     */
    protected $doctype;

    /**
     * Substitution symbols.
     *
     * @var array
     */
    protected $symbols;

    /**
     * Dimensionless images flag.
     *
     * @var bool
     */
    protected $dimensionless_images = false;


    /**
     * Constructor.
     *
     * @param string $doctype The output document type, either 'xhtml' or 'html5'
     */
    public function __construct($doctype = 'xhtml')
    {
        $doctype_whitelist = array(
            'xhtml',
            'html5',
        );
        $doctype = strtolower($doctype);
        if (!in_array($doctype, $doctype_whitelist)) {
            $this->doctype = 'xhtml';
        } else {
            $this->doctype = $doctype;
        }

        // Basic symbols used in textile glyph replacements. To override these, call
        // setSymbol('symbol_name', 'new_string') before calling textileThis() or
        // textileRestricted().
        $this->symbols = array(
            'quote_single_open'  => '&#8216;',
            'quote_single_close' => '&#8217;',
            'quote_double_open'  => '&#8220;',
            'quote_double_close' => '&#8221;',
            'apostrophe'         => '&#8217;',
            'prime'              => '&#8242;',
            'prime_double'       => '&#8243;',
            'ellipsis'           => '&#8230;',
            'emdash'             => '&#8212;',
            'endash'             => '&#8211;',
            'dimension'          => '&#215;',
            'trademark'          => '&#8482;',
            'registered'         => '&#174;',
            'copyright'          => '&#169;',
            'half'               => '&#189;',
            'quarter'            => '&#188;',
            'threequarters'      => '&#190;',
            'degrees'            => '&#176;',
            'plusminus'          => '&#177;',
            'fn_ref_pattern'     => '<sup{atts}>{marker}</sup>',
            'fn_foot_pattern'    => '<sup{atts}>{marker}</sup>',
            'nl_ref_pattern'     => '<sup{atts}>{marker}</sup>',
        );

        $this->hlgn  = "(?:\<(?!>)|&lt;&gt;|&gt;|&lt;|(?<!<)\>|\<\>|\=|[()]+(?! ))";
        $this->vlgn  = "[\-^~]";
        $this->clas  = "(?:\([^)\n]+\))";    // Don't allow classes/ids/languages/styles to span across newlines if used in a dotall regex
        $this->lnge  = "(?:\[[^]\n]+\])";
        $this->styl  = "(?:\{[^}\n]+\})";
        $this->cspn  = "(?:\\\\\d+)";
        $this->rspn  = "(?:\/\d+)";
        $this->a     = "(?:$this->hlgn|$this->vlgn)*";
        $this->s     = "(?:$this->cspn|$this->rspn)*";
        $this->c     = "(?:$this->clas|$this->styl|$this->lnge|$this->hlgn)*";
        $this->lc    = "(?:$this->clas|$this->styl|$this->lnge)*";
        $this->pnct  = '[\!"#\$%&\'()\*\+,\-\./:;<=>\?@\[\\\]\^_`{\|}\~]';
        $this->urlch = '[\w"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]';
        $this->syms  = '¤§µ¶†‡•∗∴◊♠♣♥♦';

        $pnc = '[[:punct:]]';
        $this->mb   = is_callable('mb_strlen');
        $this->cmap = array(0x0080, 0xffff, 0, 0xffff);

        $this->restricted_url_schemes   = array('http','https','ftp','mailto');
        $this->unrestricted_url_schemes = array('http','https','ftp','mailto','file','tel','callto','sftp');

        if (@preg_match('/\pL/u', 'a')) {
            $this->regex_snippets = array(
                'acr' => '\p{Lu}\p{Nd}',
                'abr' => '\p{Lu}',
                'nab' => '\p{Ll}',
                'wrd' => '(?:\p{L}|\p{M}|\p{N}|\p{Pc})',
                'mod' => 'u', // Make sure to mark the unicode patterns as such, Some servers seem to need this.
                'cur' => '\p{Sc}',
            );
        } else {
            $this->regex_snippets = array(
                'acr' => 'A-Z0-9',
                'abr' => 'A-Z',
                'nab' => 'a-z',
                'wrd' => '\w',
                'mod' => '',
                'cur' => '',
            );
        }
        extract($this->regex_snippets);
        $this->urlch = '['.$wrd.'"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]';

        $this->span_tags = array(
            '*'  => 'strong',
            '**' => 'b',
            '??' => 'cite',
            '_'  => 'em',
            '__' => 'i',
            '-'  => 'del',
            '%'  => 'span',
            '+'  => 'ins',
            '~'  => 'sub',
            '^'  => 'sup',
        );

        if (defined('DIRECTORY_SEPARATOR')) {
            $this->ds = constant('DIRECTORY_SEPARATOR');
        } else {
            $this->ds = '/';
        }

        $this->doc_root = @$_SERVER['DOCUMENT_ROOT'];
        if (!$this->doc_root) {
            $this->doc_root = @$_SERVER['PATH_TRANSLATED']; // IIS
        }

        $this->doc_root = rtrim($this->doc_root, $this->ds).$this->ds;
    }


    /**
     * Defines a substitution symbol.
     *
     * Call this you need to redefine a substitution symbol to
     * be used when parsing a textile document.
     *
     * @param  string $name  Name of the symbol to assign a new value to.
     * @param  string $value New value for the symbol.
     * @return object $this
     */
    public function setSymbol($name, $value)
    {
        $this->symbols[$name] = $value;
        $this->rebuild_glyphs = true;
        return $this;
    }


    /**
     * Gets a symbol definitions.
     *
     * This method can be used to get a symbol definition, or an
     * array containg the full symbol table.
     *
     * @param  string|null  $name The name of the symbol, or NULL if requesting the symbol table
     * @return array|string The symbol table or the requested symbol
     */
    public function getSymbol($name=null)
    {
        return ($name) ? @$this->symbols['name'] : $this->symbols;
    }


    /**
     * Sets base image directory path.
     *
     * This is used when Textile is supplied with a relative image path.
     * Allows client systems to have textile convert relative image paths to
     * absolute or prefixed paths. This method is used to set that base path,
     * usually a absolute HTTP address pointing to a directory.
     *
     * @param  string $prefix  The string to prefix all relative image paths with
     * @return object $this
     * $parser->setRelativeImagePrefix('http://static.example.com/');
     **/
    public function setRelativeImagePrefix($prefix='')
    {
        $this->relativeImagePrefix = $prefix;
        return $this;
    }


    /**
     * Allows a client to better support responsive designs by turning on or off
     * image dimensions when parsing a textile image tag with a relative path.
     *
     * By default (if this method is not called) image dimensions will be included
     * for relative images (if possible).
     *
     * @param  bool   $dimensionless true=>omit image dimensions, false=>include dimensions
     * @return object $this
     * @example
     * $parser = new Parser();
     * $html = $parser->setDimensionlessImages()->textileThis($input);
     */
    public function setDimensionlessImages($dimensionless=true)
    {
        $this->dimensionless_images = $dimensionless;
        return $this;
    }


    /**
     * Allows access to the dimensionless images flag.
     *
     * @return bool state of the $dimensionless_images flag.
     */
    public function getDimensionlessImages()
    {
        return $this->dimensionless_images;
    }


    /**
     * Returns the internal version of this instance of textile.
     *
     * @access public
     * @return string Version
     **/
    public function getVersion()
    {
        return $this->ver;
    }


    /**
     * Encodes the given text.
     *
     * @param  string $text The text to be encoded
     * @return string The encoded text
     * @example
     * $parser = new Parser();
     * $parser->textileEncode('Some content to encode.');
     */
    public function textileEncode($text)
    {
        $text = preg_replace("/&(?![#a-z0-9]+;)/i", "x%x%", $text);
        $text = str_replace("x%x%", "&amp;", $text);
        return $text;
    }


    /**
     * Parses the given Textile input in un-restricted mode.
     *
     * @param  string $text    The Textile input to parse
     * @param  bool   $lite    Switch to lite mode
     * @param  bool   $encode  Encode input and return
     * @param  bool   $noimage Disables images
     * @param  bool   $strict  FALSE to strip whitespace before parsing
     * @param  string $rel     Relationship attribute applied to generated links
     * @return string Parsed $text
     * @see    Parser::textileRestricted()
     * @example
     * $parser = new Parser();
     * echo $parser->textileThis('h1. Hello World!');
     */
    public function textileThis($text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '')
    {
        $this->prepare($lite, $noimage, $rel);
        $this->url_schemes = $this->unrestricted_url_schemes;

        // Use of the $encode flag is discouraged. Calling textileEncode() is prefered.
        if ($encode) {
            return $this->textileEncode($text);
        }

        if (!$strict) {
            $text = $this->cleanWhiteSpace($text);
        }

        return $this->textileCommon($text, $lite);
    }


    /**
     * Parses the given Textile input in restricted mode.
     *
     * This method should be used for any untrusted user input,
     * including comments or forum posts.
     *
     * This method escapes any raw HTML input, links only whitelisted URL
     * schemes and by default also prevents the use of images and
     * extra Textile formatting, accepting only paragraphs
     * and blockquotes as valid block tags.
     *
     * @param  string $text    The Textile input to parse
     * @param  bool   $lite    Controls lite mode, allowing extra formatting
     * @param  bool   $noimage Allow images
     * @param  string $rel     Relationship attribute applied to generated links
     * @return string Parsed $text
     * @see    Parser::textileThis()
     * @example
     * $parser = new Parser();
     * echo $parser->textileRestricted('h1. Hello World!');
     */
    public function textileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
    {
        $this->prepare($lite, $noimage, $rel);
        $this->url_schemes = $this->restricted_url_schemes;
        $this->restricted = true;

        // Escape any raw html
        $text = $this->encodeHTML($text, 0);
        $text = $this->cleanWhiteSpace($text);

        return $this->textileCommon($text, $lite);
    }


    /**
     * Parses Textile syntax.
     *
     * This method performs common parse actions.
     *
     * @param  string $text The input to parses
     * @param  bool   $lite Controls lite mode
     * @return string Parsed input
     */
    protected function textileCommon($text, $lite)
    {
        if ($lite) {
            $this->blocktag_whitelist = array('bq', 'p');
            $text = $this->blocks($text."\n\n");
        } else {
            $this->blocktag_whitelist = array('bq', 'p', 'bc', 'notextile', 'pre', 'h[1-6]', 'fn\d+', '###');
            $text = $this->blocks($text);
            $text = $this->placeNoteLists($text);
        }

        $text = $this->retrieve($text);
        $text = $this->replaceGlyphs($text);
        $text = $this->retrieveTags($text);
        $text = $this->retrieveURLs($text);

        $text = str_replace("<br />", "<br />\n", $text);

        return $text;
    }


    /**
     * Prepares the glyph patterns from the symbol table.
     *
     * @see Parser::setSymbol()
     * @see Parser::getSymbol()
     */
    protected function prepGlyphs()
    {
        if ((null!==$this->glyph_search) && (null!==$this->glyph_replace) && !$this->rebuild_glyphs) {
            return;
        }

        extract($this->symbols, EXTR_PREFIX_ALL, 'txt');
        extract($this->regex_snippets );
        $pnc = '[[:punct:]]';

        if ($cur) {
            $cur = '(?:['.$cur.']\s*)?';
        }

        $this->glyph_search = array(
            '/([0-9]+[\])]?[\'"]? ?)[xX]( ?[\[(]?)(?=[+-]?'.$cur.'[0-9]*\.?[0-9]+)/'.$mod,   // Dimension sign
            '/('.$wrd.'|\))\'('.$wrd.')/'.$mod,     // I'm an apostrophe
            '/(\s)\'(\d+'.$wrd.'?)\b(?![.]?['.$wrd.']*?\')/'.$mod,    // Back in '88/the '90s but not in his '90s', '1', '1.' '10m' or '5.png'
            "/([([{])'(?=\S)/",                     // Single open following open bracket
            '/(\S)\'(?=\s|'.$pnc.'|<|$)/',          // Single closing
            "/'/",                                  // Default single opening
            '/([([{])"(?=\S)/',                     // Double open following an open bracket. Allows things like Hello ["(Mum) & dad"]
            '/(\S)"(?=\s|'.$pnc.'|<|$)/',           // Double closing
            '/"/',                                  // Default double opening
            '/\b(['.$abr.']['.$acr.']{2,})\b(?:[(]([^)]*)[)])/'.$mod,  // 3+ uppercase acronym
            '/(?<=\s|^|[>(;-])(['.$abr.']{3,})(['.$nab.']*)(?=\s|'.$pnc.'|<|$)(?=[^">]*?(<|$))/'.$mod,  // 3+ uppercase
            '/([^.]?)\.{3}/',                       // Ellipsis
            '/--/',                                 // em dash
            '/ - /',                                // en dash
            '/(\b ?|\s|^)[([]TM[])]/i',             // Trademark
            '/(\b ?|\s|^)[([]R[])]/i',              // Registered
            '/(\b ?|\s|^)[([]C[])]/i',              // Copyright
            '/[([]1\/4[])]/',                       // 1/4
            '/[([]1\/2[])]/',                       // 1/2
            '/[([]3\/4[])]/',                       // 3/4
            '/[([]o[])]/',                          // Degrees -- that's a small 'oh'
            '/[([]\+\/-[])]/',                      // Plus minus
        );

        $this->glyph_replace = array(
            '$1'.$txt_dimension.'$2',               // Dimension sign
            '$1'.$txt_apostrophe.'$2',              // I'm an apostrophe
            '$1'.$txt_apostrophe.'$2',              // Back in '88
            '$1'.$txt_quote_single_open,            // Single open following open bracket
            '$1'.$txt_quote_single_close,           // Single closing
            $txt_quote_single_open,                 // Default single opening
            '$1'.$txt_quote_double_open,            // Double open following open bracket
            '$1'.$txt_quote_double_close,           // Double closing
            $txt_quote_double_open,                 // Default double opening
            (('html5' === $this->doctype) ? '<abbr title="$2">$1</abbr>' : '<acronym title="$2">$1</acronym>'),     // 3+ uppercase acronym
            '<span class="caps">glyph:$1</span>$2', // 3+ uppercase
            '$1'.$txt_ellipsis,                     // Ellipsis
            $txt_emdash,                            // em dash
            ' '.$txt_endash.' ',                    // en dash
            '$1'.$txt_trademark,                    // Trademark
            '$1'.$txt_registered,                   // Registered
            '$1'.$txt_copyright,                    // Copyright
            $txt_quarter,                           // 1/4
            $txt_half,                              // 1/2
            $txt_threequarters,                     // 3/4
            $txt_degrees,                           // Degrees
            $txt_plusminus,                         // Plus minus
        );

        $this->rebuild_glyphs = false; // No need to rebuild next run unless a symbol is redefined
    }


    /**
     * Prepares the parser for parsing.
     *
     * This method prepares the transient internal state of
     * Textile parser in preparation for parsing a new document.
     *
     * @param  bool   $lite    Controls lite mode
     * @param  bool   $noimage Disallow images
     * @param  string $rel     A relationship attribute applied to links
     */
    protected function prepare($lite, $noimage, $rel)
    {
        $this->unreferencedNotes = array();
        $this->notelist_cache    = array();
        $this->notes      = array();
        $this->urlshelf   = array();
        $this->urlrefs    = array();
        $this->shelf      = array();
        $this->fn         = array();
        $this->span_depth = 0;
        $this->tag_index  = 1;
        $this->note_index = 1;
        $this->rel        = $rel;
        $this->lite       = $lite;
        $this->noimage    = $noimage;
        $this->prepGlyphs();
    }


    /**
     * Cleans a HTML attribute value.
     *
     * This method checks for presence of URL encoding in the value.
     * If the number encoded characters exceeds the thereshold,
     * the input is discarded. Otherwise the encoded
     * instances are decoded.
     *
     * This method also strips any ", ' and = characters
     * from the given value. This method does not guarantee
     * valid HTML or full sanitization.
     *
     * @param  string $in The input string
     * @return string Cleaned string
     */
    protected function cleanAttribs($in)
    {
        $tmp    = $in;
        $before = -1;
        $after  =  0;
        $max    =  3;
        $i      =  0;

        while (($after != $before) && ($i < $max)) {
            $before = strlen($tmp);
            $tmp    = rawurldecode($tmp);
            $after  = strlen($tmp);
            $i++;
        }

        if ($i === $max) {
            // If we hit the max allowed decodes, assume the input is tainted and consume it.
            $out = '';
        } else {
            $out = str_replace(array('"', "'", '='), '', $tmp);
        }

        return $out;
    }


    /**
     * Constructs a HTML tag from an object.
     *
     * This is a helper method that creates a new
     * instance of \Netcarver\Textile\Tag.
     *
     * @param  string $name        The HTML element name
     * @param  array  $atts        HTML attributes applied to the tag
     * @param  bool   $selfclosing Determines if the tag should be selfclosing
     * @return \Netcarver\Textile\Tag
     */
    protected function newTag($name, $atts, $selfclosing = true)
    {
        return new \Netcarver\Textile\Tag($name, $atts, $selfclosing);
    }


    /**
     * Parses Textile attributes.
     *
     * @param  string $in         The Textile attribute string to be parsed
     * @param  string $element    Focus the routine to interpret the attributes as applying to a specific HTML tag
     * @param  int    $include_id A value interpreted as a true when cast to bool allows ids to be included in the output
     * @param  string $autoclass  An additional class or classes to be applied to the output
     * @return string HTML attribute list
     * @see    Parser::parseAttribsToArray()
     */
    protected function parseAttribs($in, $element = "", $include_id = 1, $autoclass = '')
    {
        $out = '';
        $o = $this->parseAttribsToArray($in, $element, $include_id, $autoclass);
        ksort($o);

        if (count($o)) {
            foreach ($o as $k => $v) {
                $out .= " $k=\"$v\"";
            }
        }

        return $out;
    }


    /**
     * Parses Textile attributes into an array.
     *
     * @param  string $in         The Textile attribute string to be parsed
     * @param  string $element    Focus the routine to interpret the attributes as applying to a specific HTML tag
     * @param  int    $include_id A value interpreted as a true when cast to bool allows ids to be included in the output
     * @param  string $autoclass  An additional class or classes to be applied to the output
     * @return array  HTML attributes as key => value mappings
     * @see    Parser::parseAttribs()
     */
    protected function parseAttribsToArray($in, $element = "", $include_id = 1, $autoclass = '')
    {
        $style = '';
        $class = '';
        $lang = '';
        $colspan = '';
        $rowspan = '';
        $span = '';
        $width = '';
        $id = '';
        $atts = '';
        $align = '';

        $matched = $in;
        if ($element == 'td') {
            if (preg_match("/\\\\(\d+)/", $matched, $csp)) {
                $colspan = $csp[1];
            }

            if (preg_match("/\/(\d+)/", $matched, $rsp)) {
                $rowspan = $rsp[1];
            }
        }

        if ($element == 'td' or $element == 'tr') {
            if (preg_match("/($this->vlgn)/", $matched, $vert)) {
                $style[] = "vertical-align:" . $this->vAlign($vert[1]);
            }
        }

        if (preg_match("/\{([^}]*)\}/", $matched, $sty)) {
            $style[] = rtrim($sty[1], ';');
            $matched = str_replace($sty[0], '', $matched);
        }

        if (preg_match("/\[([^]]+)\]/U", $matched, $lng)) {
            $matched = str_replace($lng[0], '', $matched);    // Consume entire lang block -- valid or invalid...
            if (preg_match("/\[([a-zA-Z]{2}(?:[\-\_][a-zA-Z]{2})?)\]/U", $lng[0], $lng)) {
                $lang = $lng[1];
            }
        }

        if (preg_match("/\(([^()]+)\)/U", $matched, $cls)) {

            $class_regex = "/^([-a-zA-Z 0-9_\.]*)$/";

            $matched = str_replace($cls[0], '', $matched);    // Consume entire class block -- valid or invalid...
            // Only allow a restricted subset of the CSS standard characters for classes/ids. No encoding markers allowed...
            if (preg_match("/\(([-a-zA-Z 0-9_\.\:\#]+)\)/U", $cls[0], $cls)) {
                $hashpos = strpos($cls[1], '#');
                // If a textile class block attribute was found with a '#' in it
                // split it into the css class and css id...
                if (false !== $hashpos) {
                    if (preg_match("/#([-a-zA-Z0-9_\.\:]*)$/", substr($cls[1], $hashpos), $ids)) {
                        $id = $ids[1];
                    }

                    if (preg_match($class_regex, substr($cls[1], 0, $hashpos), $ids)) {
                        $class = $ids[1];
                    }
                } else {
                    if (preg_match($class_regex, $cls[1], $ids)) {
                        $class = $ids[1];
                    }
                }
            }
        }

        if (preg_match("/([(]+)/", $matched, $pl)) {
            $style[] = "padding-left:" . strlen($pl[1]) . "em";
            $matched = str_replace($pl[0], '', $matched);
        }

        if (preg_match("/([)]+)/", $matched, $pr)) {
            $style[] = "padding-right:" . strlen($pr[1]) . "em";
            $matched = str_replace($pr[0], '', $matched);
        }

        if (preg_match("/($this->hlgn)/", $matched, $horiz)) {
            $style[] = "text-align:" . $this->hAlign($horiz[1]);
        }

        if ($element == 'col') {
            if (preg_match("/(?:\\\\(\d+))?\s*(\d+)?/", $matched, $csp)) {
                $span = isset($csp[1]) ? $csp[1] : '';
                $width = isset($csp[2]) ? $csp[2] : '';
            }
        }

        if ($this->restricted) {
            $o = array();
            $class = trim($autoclass);
            if ($class) {
                $o['class'] = $this->cleanAttribs($class);
            }

            if ($lang) {
                $o['lang']  = $this->cleanAttribs($lang);
            }

            return $o;
        } else {
            $class = trim($class . ' ' . $autoclass);
        }

        $o = '';
        if ($style) {
            $tmps = array();
            foreach ($style as $s) {
                $parts = explode(';', $s);
                foreach ($parts as $p) {
                    $tmps[] = $p;
                }
            }

            sort($tmps);
            foreach ($tmps as $p) {
                if (!empty($p)) {
                    $o .= $p.';';
                }
            }
            $style = trim(str_replace(array("\n", ';;'), array('', ';'), $o));
        }

        $o = array();
        if ($class) {
            $o['class'] = $this->cleanAttribs($class);
        }

        if ($colspan) {
            $o['colspan'] = $this->cleanAttribs($colspan);
        }

        if ($id && $include_id) {
            $o['id'] = $this->cleanAttribs($id);
        }

        if ($lang) {
            $o['lang'] = $this->cleanAttribs($lang);
        }

        if ($rowspan) {
            $o['rowspan'] = $this->cleanAttribs($rowspan);
        }

        if ($span) {
            $o['span'] = $this->cleanAttribs($span);
        }

        if ($style) {
            $o['style'] = $this->cleanAttribs($style);
        }

        if ($width) {
            $o['width'] = $this->cleanAttribs($width);
        }

        return $o;
    }


    /**
     * Checks whether the text is not enclosed by a block tag.
     *
     * @param  string $text The input string
     * @return bool   TRUE if the text is not enclosed
     */
    protected function hasRawText($text)
    {
        $r = trim(preg_replace('@<(p|blockquote|div|form|table|ul|ol|dl|pre|h\d)[^>]*?'.chr(62).'.*</\1>@s', '', trim($text)));
        $r = trim(preg_replace('@<(hr|br)[^>]*?/>@', '', $r));
        return '' != $r;
    }


    /**
     * Parses textile table structures into HTML.
     *
     * @param  string $text The textile input
     * @return string The parsed text
     */
    protected function tables($text)
    {
        $text = $text . "\n\n";
        return preg_replace_callback("/^(?:table(_?{$this->s}{$this->a}{$this->c})\.(.*)?\n)?^({$this->a}{$this->c}\.? ?\|.*\|)[\s]*\n\n/smU", array(&$this, "fTable"), $text);
    }


    /**
     * Constructs a HTML table from a textile table structure.
     *
     * This method is used by Parser::tables() to process
     * found table structures.
     *
     * @param  array  $matches
     * @return string HTML table
     * @see    Parser::tables()
     */
    protected function fTable($matches)
    {
        $tatts = $this->parseAttribs($matches[1], 'table');

        $sum = trim($matches[2]) ? ' summary="'.htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8').'"' : '';
        $cap       = '';
        $colgrp    = '';
        $last_rgrp = '';
        $c_row     = 1;
        foreach (preg_split("/\|\s*?$/m", $matches[3], -1, PREG_SPLIT_NO_EMPTY) as $row) {

            $row = ltrim($row);

            // Caption -- can only occur on row 1, otherwise treat '|=. foo |...' as a normal center-aligned cell.
            if (($c_row <= 1) && preg_match("/^\|\=($this->s$this->a$this->c)\. ([^\n]*)(.*)/s", ltrim($row), $cmtch)) {
                $capts = $this->parseAttribs($cmtch[1]);
                $cap = "\t<caption".$capts.">".trim($cmtch[2])."</caption>\n";
                $row = ltrim($cmtch[3]);
                if (empty($row)) {
                    continue;
                }
            }
            $c_row += 1;

            // Colgroup
            if (preg_match("/^\|:($this->s$this->a$this->c\. .*)/m", ltrim($row), $gmtch)) {
                $nl = strpos($row, "\n");    // Is this colgroup def missing a closing pipe? If so, there will be a newline in the middle of $row somewhere.
                $idx=0;
                foreach (explode('|', str_replace('.', '', $gmtch[1])) as $col) {
                    $gatts = $this->parseAttribs(trim($col), 'col');
                    $colgrp .= "\t<col".(($idx==0) ? "group".$gatts.">" : $gatts." />")."\n";
                    $idx++;
                }
                $colgrp .= "\t</colgroup>\n";

                if ($nl === false) {
                    continue;
                } else {
                    $row = ltrim(substr($row, $nl));        // Recover from our missing pipe and process the rest of the line...
                }
            }

            preg_match("/(:?^\|($this->vlgn)($this->s$this->a$this->c)\.\s*$\n)?^(.*)/sm", ltrim($row), $grpmatch);

            // Row group
            $rgrp = isset($grpmatch[2]) ? (($grpmatch[2] == '^') ? 'head' : (($grpmatch[2] == '~') ? 'foot' : (($grpmatch[2] == '-') ? 'body' : ''))) : '';
            $rgrpatts = isset($grpmatch[3]) ? $this->parseAttribs($grpmatch[3]) : '';
            $row = $grpmatch[4];

            if (preg_match("/^($this->a$this->c\. )(.*)/m", ltrim($row), $rmtch)) {
                $ratts = $this->parseAttribs($rmtch[1], 'tr');
                $row = $rmtch[2];
            } else {
                $ratts = '';
            }

            $cells = array();
            $cellctr = 0;
            foreach (explode("|", $row) as $cell) {
                $ctyp = "d";
                if (preg_match("/^_(?=[\s[:punct:]])/", $cell)) {
                    $ctyp = "h";
                }

                if (preg_match("/^(_?$this->s$this->a$this->c\. )(.*)/", $cell, $cmtch)) {
                    $catts = $this->parseAttribs($cmtch[1], 'td');
                    $cell = $cmtch[2];
                } else {
                    $catts = '';
                }

                if (!$this->lite) {
                    $a = array();
                    if (preg_match('/(\s*)(.*)/s', $cell, $a)) {
                        $cell = $this->redclothLists($a[2]);
                        $cell = $this->textileLists($cell);
                        $cell = $a[1] . $cell;
                    }
                }

                if ($cellctr>0) {
                    // Ignore first 'cell': it precedes the opening pipe
                    $cells[] = $this->doTagBr("t$ctyp", "\t\t\t<t$ctyp$catts>$cell</t$ctyp>");
                }

                $cellctr++;
            }
            $grp = (($rgrp && $last_rgrp) ? "\t</t".$last_rgrp.">\n" : '') . (($rgrp) ? "\t<t".$rgrp.$rgrpatts.">\n" : '');
            $last_rgrp = ($rgrp) ? $rgrp : $last_rgrp;
            $rows[] = $grp."\t\t<tr$ratts>\n" . join("\n", $cells) . ($cells ? "\n" : "") . "\t\t</tr>";
            unset($cells, $catts);
        }

        return "<table{$tatts}{$sum}>\n" .$cap. $colgrp. join("\n", $rows) . "\n".(($last_rgrp) ? "\t</t".$last_rgrp.">\n" : '')."</table>\n\n";
    }


    /**
     * Parses RedCloth-style definition lists into HTML.
     *
     * @param  string $text The textile input
     * @return string The parsed text
     */
    protected function redclothLists($text)
    {
        return preg_replace_callback("/^([-]+$this->lc[ .].*:=.*)$(?![^-])/smU", array(&$this, "fRedclothList"), $text);
    }


    /**
     * Constructs a HTML definition list from a RedCloth-style definition structure.
     *
     * This method is used by Parser::redclothLists() to process
     * found definition list structures.
     *
     * @param  array  $m
     * @return string HTML definition list
     * @see    Parser::redclothLists()
     */
    protected function fRedclothList($m)
    {
        $in = $m[0];
        $out = array();
        $text = preg_split('/\n(?=[-])/m', $in);
        foreach ($text as $nr => $line) {
            $m = array();
            if (preg_match("/^[-]+($this->lc)\.? (.*)$/s", $line, $m)) {
                list(, $atts, $content) = $m;
                $content = trim($content);
                $atts = $this->parseAttribs($atts);

                if (!preg_match("/^(.*?)[\s]*:=(.*?)[\s]*(=:|:=)?[\s]*$/s", $content, $xm)) {
                    $xm = array( $content, $content, '' );
                }

                list(, $term, $def,) = $xm;
                $term = trim($term);
                $def  = trim($def, ' ');

                if (empty($out)) {
                    if (''==$def) {
                        $out[] = "<dl$atts>";
                    } else {
                        $out[] = '<dl>';
                    }
                }

                if ('' != $term) {
                    $pos = strpos($def, "\n");
                    $def = str_replace("\n", "<br />", trim($def));
                    if (0 === $pos) {
                        $def  = '<p>' . $def . '</p>';
                    }
                    $term = str_replace("\n", "<br />", $term);

                    $term = $this->graf($term);
                    $def  = $this->graf($def);

                    $out[] = "\t<dt$atts>$term</dt>";

                    if ($def) {
                        $out[] = "\t<dd>$def</dd>";
                    }
                }
            }
        }
        $out[] = '</dl>';
        return implode("\n", $out);
    }


    /**
     * Parses textile list structures into HTML.
     *
     * Searches for ordered, un-ordered and definition lists in the
     * textile input and generates HTML lists for them.
     *
     * @param  string $text The textile input
     * @return string The parsed text
     */
    protected function textileLists($text)
    {
        return preg_replace_callback("/^((?:[*;:]+|[*;:#]*#(?:_|\d+)?)$this->lc[ .].*)$(?![^#*;:])/smU", array(&$this, "fTextileList"), $text);
    }


    /**
     * Constructs a HTML list from a textile list structure.
     *
     * This method is used by Parser::textileLists() to process
     * found list structures.
     *
     * @param  array  $m
     * @return string HTML list
     * @see    Parser::textileLists()
     */
    protected function fTextileList($m)
    {
        $text = preg_split('/\n(?=[*#;:])/m', $m[0]);
        $pt = '';
        foreach ($text as $nr => $line) {
            $nextline = isset($text[$nr+1]) ? $text[$nr+1] : false;
            if (preg_match("/^([#*;:]+)(_|\d+)?($this->lc)[ .](.*)$/s", $line, $m)) {
                list(, $tl, $st, $atts, $content) = $m;
                $content = trim($content);
                $nl = '';
                $ltype = $this->liType($tl);
                $litem = (strpos($tl, ';') !== false) ? 'dt' : ((strpos($tl, ':') !== false) ? 'dd' : 'li');
                $showitem = (strlen($content) > 0);

                if ('o' === $ltype) {
                    // Handle list continuation/start attribute on ordered lists...
                    if (!isset($this->olstarts[$tl])) {
                        $this->olstarts[$tl] = 1;
                    }

                    if (strlen($tl) > strlen($pt)) {
                        // First line of this level of ol -- has a start attribute?
                        if ('' == $st) {
                            $this->olstarts[$tl] = 1;           // No => reset count to 1.
                        } elseif ('_' !== $st) {
                            $this->olstarts[$tl] = (int) $st;   // Yes, and numeric => reset to given.
                                                                // TRICKY: the '_' continuation marker just means
                                                                // output the count so don't need to do anything
                                                                // here.
                        }
                    }

                    if ((strlen($tl) > strlen($pt)) && '' !== $st) {
                        // Output the start attribute if needed...
                        $st = ' start="' . $this->olstarts[$tl] . '"';
                    }

                    if ($showitem) {
                        // TRICKY: Only increment the count for list items; not when a list definition line is encountered.
                        $this->olstarts[$tl] += 1;
                    }
                }

                if (preg_match("/^([#*;:]+)(_|[\d]+)?($this->lc)[ .].*/", $nextline, $nm)) {
                    $nl = $nm[1];
                }

                if ((strpos($pt, ';') !== false) && (strpos($tl, ':') !== false)) {
                    $lists[$tl] = 2; // We're already in a <dl> so flag not to start another
                }

                $tabs = str_repeat("\t", strlen($tl));
                $atts = $this->parseAttribs($atts);
                if (!isset($lists[$tl])) {
                    $lists[$tl] = 1;
                    $line = "$tabs<" . $ltype . "l$atts$st>" . (($showitem) ? "\n$tabs\t<$litem>" . $content : '');
                } else {
                    $line = ($showitem) ? "$tabs\t<$litem$atts>" . $content : '';
                }

                if ((strlen($nl) <= strlen($tl))) {
                    $line .= (($showitem) ? "</$litem>" : '');
                }

                foreach (array_reverse($lists) as $k => $v) {
                    if (strlen($k) > strlen($nl)) {
                        $line .= ($v==2) ? '' : "\n$tabs</" . $this->liType($k) . "l>";
                        if ((strlen($k) > 1) && ($v != 2)) {
                            $line .= "</".$litem.">";
                        }
                        unset($lists[$k]);
                    }
                }
                $pt = $tl; // Remember the current Textile tag
            } else {
                $line .= "\n";
            }

            $out[] = $line;
        }

        $out = implode("\n", $out);
        return $this->doTagBr($litem, $out);
    }


    /**
     * Determines the list type from the textile input symbol.
     *
     * @param  string $in Textile input containing the possible list marker
     * @return string Either 'd', 'o', 'u'
     */
    protected function liType($in)
    {
        $m = array();
        $type = 'd';
        if (preg_match('/^([#*]+)/', $in, $m)) {
            $type = ('#' === substr($m[1], -1)) ? 'o' : 'u';
        }
        return $type;
    }


    protected function doTagBr($tag, $in)
    {
        return preg_replace_callback('@<('.preg_quote($tag).')([^>]*?)>(.*)(</\1>)@s', array(&$this, 'fBr'), $in);
    }


    protected function doPBr($in)
    {
        return preg_replace_callback('@<(p)([^>]*?)>(.*)(</\1>)@s', array(&$this, 'fPBr'), $in);
    }


    /**
     * Less restrictive version of fBr() ... used only in paragraphs where the next
     * row may start with a smiley or perhaps something like '#8 bolt...' or '*** stars...'
     */
    protected function fPBr($m)
    {
        $content = preg_replace("@((?!<br>\n|<br />\n))+\n(?![\s|])@", '$1<br />', $m[3]);
        return '<'.$m[1].$m[2].'>'.$content.$m[4];
    }


    protected function fBr($m)
    {
        $content = preg_replace("@(.+)(?<!<br>|<br />)\n(?![#*;:\s|])@", '$1<br />', $m[3]);
        return '<'.$m[1].$m[2].'>'.$content.$m[4];
    }


    /**
     * Splits the input textile text into blocks on double line-break boundaries and then
     * processes each block.
     *
     * @param  string $text Textile source text
     * @return string Input text with blocks processed
     */
    protected function blocks($text)
    {
        $blocktags = join('|', $this->blocktag_whitelist);

        $text = explode("\n\n", $text);

        $tag  = 'p';
        $atts = '';
        $cite = '';
        $graf = '';
        $ext  = '';
        $eat  = false;

        $out  = array();

        foreach ($text as $line) {
            $anon = 0;
            if (preg_match("/^($blocktags)($this->a$this->c)\.(\.?)(?::(\S+))? (.*)$/Ss", $line, $m)) {
                // Last block was extended, so close it
                if ($ext) {
                    $out[count($out)-1] .= $c1;
                }

                // New block
                list(,$tag,$atts,$ext,$cite,$graf) = $m;
                list($o1, $o2, $content, $c2, $c1, $eat) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$graf));

                // Leave off c1 if this block is extended, we'll close it at the start of the next block
                if ($ext) {
                    $line = $o1.$o2.$content.$c2;
                } else {
                    $line = $o1.$o2.$content.$c2.$c1;
                }
            } else {
                // Anonymous block
                $anon = 1;
                if ($ext or !preg_match('/^ /', $line)) {
                    list($o1, $o2, $content, $c2, $c1, $eat) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$line));
                    // Skip $o1/$c1 because this is part of a continuing extended block
                    if ($tag == 'p' and !$this->hasRawText($content)) {
                        $line = $content;
                    } else {
                        $line = $o2.$content.$c2;
                    }
                } else {
                    $line = $this->graf($line);
                }
            }

            $line = $this->doPBr($line);
            $line = preg_replace('/<br>/', '<br />', $line);

            if ($ext and $anon) {
                $out[count($out)-1] .= "\n".$line;
            } elseif (!$eat) {
                $out[] = $line;
            }

            if (!$ext) {
                $tag  = 'p';
                $atts = '';
                $cite = '';
                $graf = '';
                $eat  = false;
            }
        }

        if ($ext) {
            $out[count($out)-1] .= $c1;
        }

        return join("\n\n", $out);
    }


    protected function fBlock($m)
    {
        list(, $tag, $att, $ext, $cite, $content) = $m;
        $atts = $this->parseAttribs($att);

        $o1  = '';
        $o2  = '';
        $c2  = '';
        $c1  = '';
        $eat = false;

        if ($tag === 'p') {
            // Is this an anonymous block with a note definition?
            $notedef = preg_replace_callback("/
                    ^note\#               #  start of note def marker
                    ([^%<*!@#^([{ \s.]+)  # !label
                    ([*!^]?)              # !link
                    ({$this->c})          # !att
                    \.?                   #  optional period.
                    [\s]+                 #  whitespace ends def marker
                    (.*)$                 # !content
                /x".$this->regex_snippets['mod'], array(&$this, "fParseNoteDefs"), $content);

            if ('' === $notedef) {
                // It will be empty if the regex matched and ate it.
                return array($o1, $o2, $notedef, $c2, $c1, true);
            }
        }

        if (preg_match("/fn(\d+)/", $tag, $fns)) {
            $tag = 'p';
            $fnid = empty($this->fn[$fns[1]]) ? $fns[1] : $this->fn[$fns[1]];

            // If there is an author-specified ID goes on the wrapper & the auto-id gets pushed to the <sup>
            $supp_id = '';
            if (strpos($atts, 'class=') === false) {
                $atts .= ' class="footnote"';
            }

            if (strpos($atts, ' id=') === false) {
                $atts .= ' id="fn' . $fnid . '"';
            } else {
                $supp_id = ' id="fn' . $fnid . '"';
            }

            $sup = (strpos($att, '^') === false) ? $this->formatFootnote($fns[1], $supp_id) : $this->formatFootnote('<a href="#fnrev' . $fnid . '">'.$fns[1] .'</a>', $supp_id);

            $content = $sup . ' ' . $content;
        }

        if ($tag == "bq") {
            $cite = $this->shelveURL($cite);
            $cite = ($cite != '') ? ' cite="' . $cite . '"' : '';
            $o1 = "<blockquote$cite$atts>\n";
            $o2 = "\t\t<p".$this->parseAttribs($att, '', 0).">";
            $c2 = "</p>";
            $c1 = "\n</blockquote>";
        } elseif ($tag == 'bc') {
            $o1 = "<pre$atts>";
            $o2 = "<code>";
            $c2 = "</code>";
            $c1 = "</pre>";
            $content = $this->shelve($this->rEncodeHTML(rtrim($content, "\n")."\n"));
        } elseif ($tag == 'notextile') {
            $content = $this->shelve($content);
            $o1 = '';
            $o2 = '';
            $c1 = '';
            $c2 = '';
        } elseif ($tag == 'pre') {
            $content = $this->shelve($this->rEncodeHTML(rtrim($content, "\n")."\n"));
            $o1 = "<pre$atts>";
            $o2 = '';
            $c2 = '';
            $c1 = "</pre>";
        } elseif ($tag == '###') {
            $eat = true;
        } else {
            $o2 = "<$tag$atts>";
            $c2 = "</$tag>";
        }

        $content = (!$eat) ? $this->graf($content) : '';

        return array($o1, $o2, $content, $c2, $c1, $eat);
    }


    protected function formatFootnote($marker, $atts='', $anchor=true)
    {
        $pattern = ($anchor) ? $this->symbols['fn_foot_pattern'] : $this->symbols['fn_ref_pattern'];
        return $this->replaceMarkers($pattern, array('atts' => $atts, 'marker' => $marker));
    }


    protected function replaceMarkers($text, $replacements)
    {
        if (!empty($replacements)) {
            foreach ($replacements as $k => $r) {
                $text = str_replace('{'.$k.'}', $r, $text);
            }
        }
        return $text;
    }


    protected function getHTMLComments($text)
    {
        $text = preg_replace_callback("/
            \<!--    #  start
            (.*?)    # !content *not* greedy
            -->      #  end
        /sx", array(&$this, "fParseHTMLComments"), $text);
        return $text;
    }


    protected function fParseHTMLComments($m)
    {
        list(, $content) = $m;
        if ($this->restricted) {
            $content = $this->shelve($this->rEncodeHTML($content));
        } else {
            $content = $this->shelve($content);
        }

        return "<!--$content-->";
    }


    protected function graf($text)
    {
        // Handle normal paragraph text
        if (!$this->lite) {
            $text = $this->noTextile($text);       // Notextile blocks and inlines
            $text = $this->code($text);            // Handle code
        }

        $text = $this->getHTMLComments($text);     // HTML comments --
        $text = $this->getRefs($text);             // Consume link aliases
        $text = $this->links($text);               // Generate links

        if (!$this->noimage) {
            $text = $this->images($text);          // Handle images (if permitted)
        }

        if (!$this->lite) {
            $text = $this->tables($text);          // Handle tables
            $text = $this->redclothLists($text);   // Handle redcloth-style definition lists
            $text = $this->textileLists($text);    // Handle ordered & unordered lists plus txp-style definition lists
        }

        $text = $this->spans($text);               // Inline markup (em, strong, sup, sub, del etc)

        if (!$this->lite) {
            // Turn footnote references into supers or links. As footnote blocks are banned in lite mode there is no point generating links for them
            $text = $this->footnoteRefs($text);

            // Turn note references into links
            $text = $this->noteRefs($text);
        }

        $text = $this->glyphs($text);              // Glyph level substitutions (mainly typographic -- " & ' => curly quotes, -- => em-dash etc.

        return rtrim($text, "\n");
    }


    /**
     * Replaces textile spans with their equivalent HTML inline tags.
     *
     * @param  string $text The textile document to perform the replacements in.
     * @return string       The textile document with spans replaced by their HTML inline equivalents
     */
    protected function spans($text)
    {
        $span_tags = array_keys($this->span_tags);
        $pnct = ".,\"'?!;:‹›«»„“”‚‘’";
        $this->span_depth++;

        if ($this->span_depth <= $this->max_span_depth) {
            foreach ($span_tags as $f) {
                $f = preg_quote($f);
                $text = preg_replace_callback("/
                    (^|(?<=[\s>$pnct\(])|[{[])            # pre
                    ($f)(?!$f)                            # tag
                    ({$this->lc})                         # atts - do not use horizontal alignment; it kills html tags within inline elements.
                    (?::(\S+))?                           # cite
                    ([^\s$f]+|\S.*?[^\s$f\n])             # content
                    ([$pnct]*)                            # end
                    $f
                    ($|[\[\]}<]|(?=[$pnct]{1,2}[^0-9]|\s|\)))  # tail
                /x".$this->regex_snippets['mod'], array(&$this, "fSpan"), $text);
            }
        }
        $this->span_depth--;
        return $text;
    }


    protected function fSpan($m)
    {
        list(, $pre, $tag, $atts, $cite, $content, $end, $tail) = $m;

        $tag  = $this->span_tags[$tag];
        $atts = $this->parseAttribs($atts);
        $atts .= ($cite != '') ? 'cite="' . $cite . '"' : '';

        $content = $this->spans($content);

        $opentag = '<'.$tag.$atts.'>';
        $closetag = '</'.$tag.'>';
        $tags = $this->storeTags($opentag, $closetag);
        $out = "{$tags['open']}{$content}{$end}{$tags['close']}";

        if (($pre and !$tail) or ($tail and !$pre)) {
            $out = $pre.$out.$tail;
        }

        return $out;
    }


    protected function storeTags($opentag,$closetag='')
    {
        $key = ($this->tag_index++);

        $key = str_pad((string) $key, 10, '0', STR_PAD_LEFT).'z'; // $key must be of fixed length to allow proper matching in retrieveTags
        $this->tagCache[$key] = array('open' => $opentag, 'close' => $closetag);
        $tags = array(
            'open'  => "textileopentag{$key} ",
            'close' => " textileclosetag{$key}",
        );
        return $tags;
    }


    protected function retrieveTags($text)
    {
        $text = preg_replace_callback('/textileopentag([\d]{10}z) /', array(&$this, 'fRetrieveOpenTags'), $text);
        $text = preg_replace_callback('/ textileclosetag([\d]{10}z)/', array(&$this, 'fRetrieveCloseTags'), $text);
        return $text;
    }


    protected function fRetrieveOpenTags($m)
    {
        return $this->tagCache[$m[1]]['open'];
    }


    protected function fRetrieveCloseTags($m)
    {
        return $this->tagCache[$m[1]]['close'];
    }


    protected function placeNoteLists($text)
    {
        extract($this->regex_snippets);

        // Sequence all referenced definitions...
        if (!empty($this->notes)) {
            $o = array();
            foreach ($this->notes as $label => $info) {
                $i = @$info['seq'];
                if (!empty($i)) {
                    $info['seq'] = $label;
                    $o[$i] = $info;
                } else {
                    $this->unreferencedNotes[] = $info;    // Unreferenced definitions go here for possible future use.
                }
            }

            if (!empty($o)) {
                ksort($o);
            }

            $this->notes = $o;
        }

        // Replace list markers...
        $text = preg_replace_callback("@<p>notelist({$this->c})(?:\:([$wrd|{$this->syms}]))?([\^!]?)(\+?)\.?[\s]*</p>@U$mod", array(&$this, "fNoteLists"), $text);

        return $text;
    }


    protected function fNoteLists($m)
    {
        list(, $att, $start_char, $g_links, $extras) = $m;
        if (!$start_char) {
            $start_char = 'a';
        }

        $index = $g_links.$extras.$start_char;

        if (empty($this->notelist_cache[$index])) {
            // If not in cache, build the entry...
            $o = array();

            if (!empty($this->notes)) {
                foreach ($this->notes as $seq => $info) {
                    $links = $this->makeBackrefLink($info, $g_links, $start_char);
                    $atts = '';
                    if (!empty($info['def'])) {
                        $id = $info['id'];
                        extract($info['def']);
                        $o[] = "\t".'<li'.$atts.'>'.$links.'<span id="note'.$id.'"> </span>'.$content.'</li>';
                    } else {
                        $o[] = "\t".'<li'.$atts.'>'.$links.' Undefined Note [#'.$info['seq'].'].</li>';
                    }
                }
            }

            if ('+' == $extras && !empty($this->unreferencedNotes)) {
                foreach ($this->unreferencedNotes as $seq => $info) {
                    if (!empty($info['def'])) {
                        extract($info['def']);
                        $o[] = "\t".'<li'.$atts.'>'.$content.'</li>';
                    }
                }
            }

            $this->notelist_cache[$index] = join("\n", $o);
        }

        $_ = ($this->notelist_cache[$index]) ? $this->notelist_cache[$index] : '';

        if (!empty($_)) {
            $list_atts = $this->parseAttribs($att);
            $_ = "<ol$list_atts>\n$_\n</ol>";
        }

        return $_;
    }


    protected function makeBackrefLink(&$info, $g_links, $i)
    {
        $link    = '';
        $atts    = '';
        $content = '';
        $id      = '';
        @extract($info['def']);
        $backlink_type = ($link) ? $link : $g_links;
        $allow_inc = (false === strpos($this->syms, $i));

        $i_ = str_replace(array('&', ';', '#'), '', $this->encodeHigh($i));
        $decode = (strlen($i) !== strlen($i_));

        if ($backlink_type === '!') {
            return '';
        } elseif ($backlink_type === '^') {
            return '<sup><a href="#noteref'.$info['refids'][0].'">'.$i.'</a></sup>';
        } else {
            $_ = array();
            foreach ($info['refids'] as $id) {
                $_[] = '<sup><a href="#noteref'.$id.'">'. (($decode) ? $this->decodeHigh($i_) : $i_) .'</a></sup>';
                if ($allow_inc) {
                    $i_++;
                }
            }
            $_ = join(' ', $_);
            return $_;
        }
    }


    protected function fParseNoteDefs($m)
    {
        list(, $label, $link, $att, $content) = $m;
        // Assign an id if the note reference parse hasn't found the label yet.
        $id = @$this->notes[$label]['id'];
        if (!$id) {
            $this->notes[$label]['id'] = uniqid(rand());
        }

        // Ignores subsequent defs using the same label
        if (empty($this->notes[$label]['def'])) {
            $this->notes[$label]['def'] = array(
                'atts'    => $this->parseAttribs($att),
                'content' => $this->graf($content),
                'link'    => $link,
            );
        }
        return '';
    }


    protected function noteRefs($text)
    {
        $text = preg_replace_callback("/
            \[                   #  start
            ({$this->c})         # !atts
            \#
            ([^\]!]+?)           # !label
            ([!]?)               # !nolink
            \]
        /Ux", array(&$this, "fParseNoteRefs"), $text);
        return $text;
    }


    protected function fParseNoteRefs($m)
    {
        //   By the time this function is called, all the defs will have been processed
        // into the notes array. So now we can resolve the link numbers in the order
        // we process the refs...

        list(, $atts, $label, $nolink) = $m;
        $atts = $this->parseAttribs($atts);
        $nolink = ($nolink === '!');

        // Assign a sequence number to this reference if there isn't one already...
        $num = @$this->notes[$label]['seq'];
        if (!$num) {
            $num = $this->notes[$label]['seq'] = ($this->note_index++);
        }

        // Make our anchor point & stash it for possible use in backlinks when the
        // note list is generated later...
        $refid = uniqid(rand());
        $this->notes[$label]['refids'][] = $refid;

        // If we are referencing a note that hasn't had the definition parsed yet, then assign it an ID...
        $id = @$this->notes[$label]['id'];
        if (!$id) {
            $id = $this->notes[$label]['id'] = uniqid(rand());
        }

        // Build the link (if any)...
        $_ = '<span id="noteref'.$refid.'">'.$num.'</span>';
        if (!$nolink) {
            $_ = '<a href="#note'.$id.'">'.$_.'</a>';
        }

        // Build the reference...
        $_ = $this->replaceMarkers($this->symbols['nl_ref_pattern'], array('atts' => $atts, 'marker' => $_));

        return $_;
    }


    /**
     * Parses URI into component parts.
     *
     * This method splits a URI-like string apart into component parts, while
     * also providing validation.
     *
     * @param  string $uri The string to pick apart (if possible)
     * @param  array  $m   Reference to an array the URI component parts are assigned to
     * @return bool   TRUE if the string validates as a URI
     * @link   http://tools.ietf.org/html/rfc3986#appendix-B
     */
    protected function parseURI($uri, &$m)
    {
        $r = "@^((?P<scheme>[^:/?#]+):)?(//(?P<authority>[^/?#]*))?(?P<path>[^?#]*)(\?(?P<query>[^#]*))?(#(?P<fragment>.*))?@";
        //      12                      3  4                       5               6  7                 8 9
        //
        //    scheme    = $2
        //    authority = $4
        //    path      = $5
        //    query     = $7
        //    fragment  = $9

        $ok = preg_match($r, $uri, $m);
        return $ok;
    }


    /**
     * Checks whether a component part can be added to a URI.
     *
     * @param  array  $mask  An array of allowed component parts
     * @param  string $name  The component to add
     * @param  array  $parts An array of existing components to modify
     * @return bool   TRUE if the component can be added
     */
    protected function addPart(&$mask, $name, &$parts)
    {
        return (in_array($name, $mask) && isset($parts[$name]) && '' !== $parts[$name]);
    }


    /**
     * Rebuild a URI from parsed parts and a mask.
     * Algorithm based on example from http://tools.ietf.org/html/rfc3986#section-5.3
     *
     * @param  array  $parts  Full array of URI parts
     * @param  string $mask   Comma separated list of URI parts to include in the rebuilt URI
     * @param  bool   $encode Flag to control encoding of the path part of the rebuilt URI
     * @return string         The rebuilt URI
     */
    protected function rebuildURI($parts, $mask='scheme,authority,path,query,fragment', $encode=true)
    {
        $mask = explode(',', $mask);
        $out  = '';

        if ($this->addPart($mask, 'scheme', $parts)) {
            $out .= $parts['scheme'] . ':';
        }

        if ($this->addPart($mask, 'authority', $parts)) {
            $out .= '//' . $parts['authority'];
        }

        if ($this->addPart($mask, 'path', $parts)) {
            if (!$encode) {
                $out .= $parts['path'];
            } else {
                $pp = explode('/', $parts['path']);
                foreach ($pp as &$p) {
                    $p = str_replace(array('%25', '%40'), array('%', '@'), rawurlencode($p));
                    if (!in_array($parts['scheme'], array('tel','mailto'))) {
                        $p = str_replace('%2B', '+', $p);
                    }
                }

                $pp = implode('/', $pp);
                $out .= $pp;
            }
        }

        if ($this->addPart($mask, 'query', $parts)) {
            $out .= '?' . $parts['query'];
        }

        if ($this->addPart($mask, 'fragment', $parts)) {
            $out .= '#' . $parts['fragment'];
        }

        return $out;
    }


    /**
     * Initiates recognition and conversion of links in the source document.
     *
     * @param  string $text  The source textile text
     * @return string        The document with tokens inserted representing the links
     */
    protected function links($text)
    {
        return preg_replace_callback('/
            (^|(?<=[\s>.\(\|])|[{[])   # $pre
            "                          #  start
            (' . $this->c . ')         # $atts
            ([^"]+?)                   # $text
            (?:\(([^)]+?)\)(?="))?     # $title
            ":
            ('.$this->urlch.'+?)       # $url
            (\/)?                      # $slash
            ([^'.$this->regex_snippets['wrd'].'\/]*?)  # $post
            ([\]}]|(?=\s|$|\)|\|))     # $tail
            /x'.$this->regex_snippets['mod'], array(&$this, "fLink"), $text);
    }


    protected function fLink($m)
    {
        list(, $pre, $atts, $text, $title, $url, $slash, $post, $tail) = $m;

        // Strip any ':' or '?' characters from the end of the url and return them to $post. This seems to be needed
        // when using the unicode version of the word character class in the regex.
        $a = array();
        if (preg_match('/^(.*)([?:]+)$/', $url, $a)) {
            $url   = $a[1];
            $post .= $a[2];
        }

        $uri_parts = array();
        $this->parseURI($url, $uri_parts);

        $scheme         = $uri_parts['scheme'];
        $scheme_in_list = in_array($scheme, $this->url_schemes);
        $scheme_ok      = ('' === $scheme) || $scheme_in_list;

        if (!$scheme_ok) {
            return $m[0];
        }

        if ('$' === $text) {
            if ($scheme_in_list) {
                $text = ltrim($this->rebuildURI($uri_parts, 'authority,path,query,fragment', false), '/');
            } else {
                if (isset($this->urlrefs[$url])) {
                    $url = urldecode($this->urlrefs[$url]);
                }
                $text = $url;
            }
        }

        $text  = trim($text);
        $title = $this->encodeHTML($title);

        // If the text was in parenthesis and there was no title then the regex will have an empty $text and a non-empty $title so...
        if (empty($text) && !empty($title)) {
            $text  = "($title)";
            $title = '';
        }

        if (!$this->noimage) {
            $text = $this->images($text);
        }

        $text = $this->spans($text);
        $text = $this->glyphs($text);
        $url  = $this->shelveURL($this->rebuildURI($uri_parts) . $slash);

        $a    = $this->newTag('a', $this->parseAttribsToArray($atts), false)->title($title)->href($url, true)->rel($this->rel);
        $tags = $this->storeTags((string) $a, '</a>');
        $out  = $tags['open'].$text.$tags['close'];

        if (($pre and !$tail) or ($tail and !$pre)) {
            $out = $pre.$out.$post.$tail;
            $post = '';
        }

        return $this->shelve($out).$post;
    }


     /**
      * Finds URI aliases within the textile source text and pulls them out of it and stores them in an internal
      * link alias cache so that they can be referenced from any links in the document.
      *
      * This parse happens before the link parsing takes place.
      *
      * @param  string $text textile document
      * @return string The textile document with any URI aliases/refs removed.
      */
    protected function getRefs($text)
    {
        if ($this->restricted) {
            $pattern = "/^\[(.+)\]((?:https?:\/\/|\/)\S+)(?=\s|$)/Um";
        } else {
            $pattern = "/^\[(.+)\]((?:https?:\/\/|tel:|file:|ftp:\/\/|sftp:\/\/|mailto:|callto:|\/)\S+)(?=\s|$)/Um";
        }

        return preg_replace_callback($pattern, array(&$this, "refs"), $text);
    }


    protected function refs($m)
    {
        list(, $flag, $url) = $m;
        $uri_parts = array();
        $this->parseURI($url, $uri_parts);
        $url = ltrim($this->rebuildURI($uri_parts)); // Encodes URL if needed.
        $this->urlrefs[$flag] = $url;
        return '';
    }


    /**
     * Shelves parsed URLs.
     *
     * Stores away a URL fragments that have been parsed
     * and requires no more processing.
     *
     * @param  string $text The URL
     * @return string The fragment's unique reference ID
     */
    protected function shelveURL($text)
    {
        if ('' === $text) {
            return '';
        }

        $ref = md5($text).'z';
        $this->urlshelf[$ref] = $text;
        return 'urlref:'.$ref;
    }


    protected function retrieveURLs($text)
    {
        return preg_replace_callback('/urlref:(\w{32}z)/', array(&$this, "retrieveURL"), $text);
    }


    protected function retrieveURL($m)
    {
        $ref = $m[1];
        if (!isset($this->urlshelf[$ref])) {
            return $ref;
        }

        $url = $this->urlshelf[$ref];
        if (isset($this->urlrefs[$url])) {
            $url = $this->urlrefs[$url];
        }

        return $this->rEncodeHTML($this->relURL($url));
    }


    protected function relURL($url)
    {
        $parts = @parse_url(urldecode($url));
        if ((empty($parts['scheme']) or @$parts['scheme'] == 'http') and empty($parts['host']) and preg_match('/^\w/', @$parts['path'])) {
            $url = $this->relativeImagePrefix.$url;
        }

        if ($this->restricted and !empty($parts['scheme']) and !in_array($parts['scheme'], $this->url_schemes)) {
            return '#';
        }

        return $url;
    }


    /**
     * Checks if an URL is relative.
     *
     * The given URL is considered relative if it doesn't
     * contain scheme and hostname.
     *
     * @param  string $url The URL
     * @return bool   TRUE if relative, FALSE otherwise
     */
    protected function isRelURL($url)
    {
        $parts = @parse_url($url);
        return (empty($parts['scheme']) and empty($parts['host']));
    }


    /**
     * Parses the input textile document for images and generates img HTML tags for each one found, caching the
     * generated img tag internally and replacing the textile image with a token to the cached tag.
     *
     * @param  string $text Textile source text
     * @return string The input document with images pulled out and replaced with tokens
     */
    protected function images($text)
    {
        return preg_replace_callback('/
            (?:[[{])?                  # pre
            \!                         # opening !
            (\<|\=|\>)?                # optional alignment              $algn
            ('.$this->lc.')            # optional style,class atts       $atts
            (?:\.\s)?                  # optional dot-space
            ([^\s(!]+)                 # presume this is the src         $url
            \s?                        # optional space
            (?:\(([^\)]+)\))?          # optional title                  $title
            \!                         # closing
            (?::(\S+)(?<![\]).,]))?    # optional href sans final punct. $href
            (?:[\]}]|(?=[.,\s)|]|$))   # lookahead: space , . ) | or end of string ... "|" needed if image in table cell
        /x', array(&$this, "fImage"), $text);
    }


    protected function fImage($m)
    {
        $extras = '';
        $align  = '';
        $alt    = '';
        $height = '';
        $width  = '';
        $size   = false;

        list(, $algn, $atts, $url, $title, $href) = array_pad($m, 6, null);
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        if ('' !== $algn) {
            $vals = array('<' => 'left', '=' => 'center', '>' => 'right');
            if (isset($vals[$algn])) {
                if ('html5' === $this->doctype) {
                    $extras = "align-{$vals[$algn]}";
                } else {
                    $align = $vals[$algn];
                }
            }
        }

        if ($title) {
            $title = $this->encodeHTML($title);
            $alt   = $title;
        }

        if (!$this->dimensionless_images && $this->isRelUrl($url)) {
            $real_location = realpath($this->doc_root.ltrim($url, $this->ds));

            if ($real_location) {
                $size = getimagesize($real_location);
            }
        }

        if ($size) {
            $height = $size[1];
            $width  = $size[0];
        }

        $img  = $this
                    ->newTag('img', $this->parseAttribsToArray($atts, '', 1, $extras))
                    ->align($align)
                    ->alt($alt, true)
                    ->height($height)
                    ->src($this->shelveURL($url), true)
                    ->title($title)
                    ->width($width)
                    ;

        $out = (string) $img;
        if ($href) {
            $href = $this->shelveURL($href);
            $link = $this
                        ->newTag('a', array(), false)
                        ->href($href)
                        ->rel($this->rel);
            $out  = (string) $link . "$img</a>";
        }

        return $this->shelve($out);
    }


    protected function code($text)
    {
        $text = $this->doSpecial($text, '<code>', '</code>', 'fCode');
        $text = $this->doSpecial($text, '@', '@', 'fCode');
        $text = $this->doSpecial($text, '<pre>', '</pre>', 'fPre');
        return $text;
    }


    protected function fCode($m)
    {
        list(, $before, $text, $after) = array_pad($m, 4, '');
        return $before.$this->shelve('<code>'.$this->rEncodeHTML($text).'</code>').$after;
    }


    protected function fPre($m)
    {
        list(, $before, $text, $after) = array_pad($m, 4, '');
        return $before.'<pre>'.$this->shelve($this->rEncodeHTML($text)).'</pre>'.$after;
    }


    /**
     * Shelves parsed content.
     *
     * Stores away a fragment of the source text that have been parsed
     * and requires no more processing.
     *
     * @param  string $val The content
     * @return string The fragment's unique reference ID
     */
    protected function shelve($val)
    {
        $i = uniqid(rand()).'z';
        $this->shelf[$i] = $val;
        return $i;
    }


    protected function retrieve($text)
    {
        if (is_array($this->shelf)) {
            do {
                $old = $text;
                $text = str_replace(array_keys($this->shelf), $this->shelf, $text);
            } while ($text != $old);
        }

        return $text;
    }


    /**
     * Cleans up the textile input text, removing BOM and unifying line endings etc.
     *
     * @param  string $text Input textile text
     * @return string Cleaned version of the input
     */
    protected function cleanWhiteSpace($text)
    {
        $out = preg_replace("/^\xEF\xBB\xBF|\x1A/", '', $text); // Byte order mark (if present)
        $out = preg_replace("/\r\n?/", "\n", $out);             // DOS and MAC line endings to *NIX style endings
        $out = preg_replace("/^[ \t]*\n/m", "\n", $out);        // lines containing only whitespace
        $out = preg_replace("/\n{3,}/", "\n\n", $out);          // 3 or more line ends
        $out = preg_replace("/^\n*/", "", $out);                // leading blank lines
        return $out;
    }


    protected function doSpecial($text, $start, $end, $method='fSpecial')
    {
        return preg_replace_callback('/(^|\s|[|[({>])'.preg_quote($start, '/').'(.*?)'.preg_quote($end, '/').'(\s|$|[\])}|])?/ms', array(&$this, $method), $text);
    }


    protected function fSpecial($m)
    {
        // A special block like notextile or code
        list(, $before, $text, $after) = array_pad($m, 4, '');
        return $before.$this->shelve($this->encodeHTML($text)).$after;
    }


    protected function noTextile($text)
    {
         $text = $this->doSpecial($text, '<notextile>', '</notextile>', 'fTextile');
         return $this->doSpecial($text, '==', '==', 'fTextile');
    }


    protected function fTextile($m)
    {
        list(, $before, $notextile, $after) = array_pad($m, 4, '');
        return $before.$this->shelve($notextile).$after;
    }


    protected function footnoteRefs($text)
    {
        return preg_replace_callback('/(?<=\S)\[(\d+)(!?)\]\s?/U', array(&$this, 'footnoteID'), $text);
    }


    protected function footnoteID($m)
    {
        list(, $id, $nolink) = array_pad($m, 3, '');
        $backref = ' class="footnote"';

        if (empty($this->fn[$id])) {
            $this->fn[$id] = $a = uniqid(rand());
            $backref .= " id=\"fnrev$a\"";
        }

        $fnid = $this->fn[$id];
        $footref = ('!' == $nolink) ? $id : '<a href="#fn'.$fnid.'">'.$id.'</a>';
        $footref = $this->formatFootnote($footref, $backref, false);

        return $footref;
    }


    /**
     * Perfoms typographical glyph replacements within the input textile text.
     * The source text is split across HTML-like tags in order to avoid attempting glyph
     * replacements within tags.
     *
     * @param  string $text Input textile source text
     * @return string
     */
    protected function glyphs($text)
    {
        // Fix: hackish -- adds a space if final char of text is a double quote.
        $text = preg_replace('/"\z/', "\" ", $text);

        $text = preg_split("@(<[\w/!?].*>)@Us", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $i = 0;
        foreach ($text as $line) {
            // Text tag text tag text ...
            if (++$i % 2) {
                // Raw < > & chars are already entity encoded in restricted mode
                if (!$this->restricted) {
                    $line = preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $line);
                    $line = str_replace(array('<', '>'), array('&lt;', '&gt;'), $line);
                }
                $line = preg_replace($this->glyph_search, $this->glyph_replace, $line);
            }
            $glyph_out[] = $line;
        }
        return join('', $glyph_out);
    }


    protected function replaceGlyphs($text)
    {
        return preg_replace('/glyph:([^<]+)/', '$1', $text);
    }


    /**
     * Translates alignment tag into corresponding CSS text-align property value.
     *
     * @param  string $in The Textile alignment tag
     * @return string CSS text-align value
     */
    protected function hAlign($in)
    {
        $vals = array(
            '&lt;'     => 'left',
            '&gt;'     => 'right',
            '&lt;&gt;' => 'justify',
            '<'        => 'left',
            '='        => 'center',
            '>'        => 'right',
            '<>'       => 'justify');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }


    /**
     * Translates vertical alignment tag into corresponding CSS vertical-align property value.
     *
     * @param  string $in The Textile alignment tag
     * @return string CSS vertical-align value
     */
    protected function vAlign($in)
    {
        $vals = array(
            '^' => 'top',
            '-' => 'middle',
            '~' => 'bottom');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }


    protected function encodeHigh($text, $charset = "UTF-8")
    {
        return ($this->mb) ? mb_encode_numericentity($text, $this->cmap, $charset) : htmlentities($text, ENT_NOQUOTES, $charset);
    }


    protected function decodeHigh($text, $charset = "UTF-8")
    {
        $text = (ctype_digit($text)) ? "&#$text;" : "&$text;" ;
        return ($this->mb) ? mb_decode_numericentity($text, $this->cmap, $charset) : html_entity_decode($text, ENT_NOQUOTES, $charset);
    }


    /**
     * Convert special characters to HTML entities.
     *
     * This method's functinality is identical to PHP's own
     * htmlspecialchars(). In Textile this is used for sanitizing
     * the input.
     *
     * @param  string $str    The string to encode
     * @param  bool   $quotes Encode quotes
     * @return string Encoded string
     * @see    htmlspecialchars()
     */
    protected function encodeHTML($str, $quotes=1)
    {
        $a = array(
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        );
        if ($quotes) {
            $a = $a + array(
                "'" => '&#39;', // Numeric, as in htmlspecialchars
                '"' => '&quot;',
            );
        }

        return str_replace(array_keys($a), $a, $str);
    }


    /**
     * Convert special characters to HTML entities.
     *
     * This is identical to encodeHTML(), but takes restricted
     * mode into account. When in restricted mode, only encodes
     * quotes no matter what the $quotes is set to.
     *
     * @param  string $str    The string to encode
     * @param  bool   $quotes Encode quotes
     * @return string Encoded string
     * @see    Parser::encodeHTML()
     */
    protected function rEncodeHTML($str, $quotes=1)
    {
        // In restricted mode, all input but quotes has already been escaped
        if ($this->restricted) {
            return str_replace('"', '&quot;', $str);
        }

        return $this->encodeHTML($str, $quotes);
    }
}

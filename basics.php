<?php

namespace UCode;

//
// [url], supports embedded images
//
class LinkTag extends \XBBC\SimpleTag {
	protected $embedded_img = null;
	
	public function __construct() {
		parent::__construct(null, '</a>');
	}
	
	protected function __create() {
		if($this->arg && $this->arg[0] != '#')
			$this->arg = \XBBC\TagTools::SanitizeURL($this->arg);
	}
	
	public function CanShift($tag) {
		// We can't shift more than one image (with embedded mode)
		if($this->embedded_img)
			return false;
		
		// Hook ourself on the first [img] tag if arg is undefined
		if($tag instanceof ImageTag && !$this->arg) {
			$this->embedded_img = $tag;
			return true;
		}
		
		return $this->arg ? parent::CanShift($tag) : false;
	}
	
	public function GetURL() {
		if($this->embedded_img)
			return htmlspecialchars($this->embedded_img->GetURL());
		else
			return $this->arg
				? htmlspecialchars($this->arg)
				: ($this->content[0] == '#'
					? htmlspecialchars($this->Content())
					: \XBBC\TagTools::SanitizeURL($this->Content(), true));
	}
	
	public function Reduce() {
		if($url = $this->GetURL())
			$this->before = '<a href="'.$url.'">';
		
		return parent::Reduce();
	}
	
	public function ReducePlaintext() {
		$url = $this->GetURL();
		$title = parent::ReducePlaintext();
		
		if($url && $url != $title) {
			return "[$url ($title)]";
		} else {
			return "[$title]";
		}
	}
}

//
// [img]
//
class ImageTag extends ArgAsContentTag {
	public function __construct() {
		parent::__construct();
		$this->buffer_escape = false;
	}
	
	public function Reduce() {
		// Image URL
		$url = $this->GetURL();
		
		// Alt text
		$alt = isset($this->xargs['alt']) ? $this->xargs['alt'] : basename($url);
		
		// Image styles
		$styles = array();
		
		if(isset($this->xargs['width']) && $size = \XBBC\TagTools::FormatSize($this->xargs['width']))
			$styles[] = 'width:'.$size;
		
		if(isset($this->xargs['height']) && $size = \XBBC\TagTools::FormatSize($this->xargs['height']))
			$styles[] = 'height:'.$size;
		
		if(isset($this->xargs['valign']) && preg_match('/^(-?[0-9]+(px)?|top|bottom|middle|baseline)$/', $this->xargs['valign'])) {
			if(preg_match('/^-?[0-9]+$/', $this->xargs['valign'])) $this->xargs['valign'] .= 'px';
			$styles[] = 'vertical-align:'.$this->xargs['valign'];
		}
		
		if(isset($this->xargs['noshadow']))
			$styles[] = 'box-shadow:none;';
		
		$styles = !empty($styles) ? 'style="'.implode(';', $styles).'" ' : '';
		
		// Final tag
		return '<img src="'.htmlspecialchars($url).'" alt="'.htmlspecialchars($alt).'" '.$styles.'/>';
	}
	
	//
	// Return the unescaped URL for this image
	//
	public function GetURL() {
		return \XBBC\TagTools::SanitizeURL(parent::Reduce());
	}
	
	public function ReducePlaintext() {
		return '{'.$this->GetURL().'}';
	}
}

//
// [c]
//
class CTag extends \XBBC\LeafTag {
	public function __construct() {
		parent::__construct('<code>', '</code>', false, '`');
	}
	
	public function StripWhitespaces() { return true; }
}

//
// [latex]
//
class LatexTag extends \XBBC\LeafTag {
	public function __construct() {
		parent::__construct(null, null, false);
	}
	
	public function Reduce() {
		$latex = urlencode($this->Content());
		
		if(!isset($this->xargs['size'])) {
			$this->xargs['size'] = 0;
		}
		
		switch($this->xargs['size']) {
			case '5': $size = '&s=4'; break;
			case '4': $size = '&s=3'; break;
			case '3': $size = '&s=2'; break;
			case '2': $size = '&s=1'; break;
			default:  $size = ''; break;
		}
		
		if(!isset($this->xargs['bg'])) {
			$this->xargs['bg'] = 0;
		}
		
		switch($this->xargs['bg']) {
			case '2':
				$background = '111111';
				break;
				
			case '1':
				$background = '161616';
				break;
				
			default:
				$background = '1a1a1a';
				break;
		}
		
		$url = "http://s0.wp.com/latex.php?latex=$latex&bg=$background&fg=bbbbbb$size";
		return '<img src="'.$url.'" alt="" class="latex" />';
	}
}

//
// [color]
//
class ColorTag extends \XBBC\SimpleTag {
	public function __construct() {
		parent::__construct(null, '</span>');
	}
	
	protected function __create() {
		if($wh_class = WowheadTag::ColorAsClass($this->arg)) {
			$this->before = "<span class=\"$wh_class\">";
		} elseif($color = self::ParseColor($this->arg)) {
			$this->before = "<span style=\"color:$color\">";
		} else {
			return false;
		}
	}
	
	public static function ParseColor($color) {
		$color = preg_replace('/\s/', '', $color);
		return preg_match('/^([a-z\-]+|#[0-9a-f]{3}|#[0-9a-f]{6})$/i', $color) ? $color : false;
	}
}

//
// [quote], emulate a new document root
//
class QuoteTag extends \XBBC\RootTag {
	public function __construct() {
		parent::__construct();
		$this->before = '<blockquote>';
		$this->after = '</blockquote>';
		$this->display = \XBBC\DISPLAY_BLOCK;
	}
	
	public function MaxNesting() {
		return 3;
	}
	
	public function Reduce() {
		if($author = $this->GetAuthorString()) {
			$this->content = '<div class="quote-author">'.htmlspecialchars($author).'</div>'.$this->content;
		}
		
		return parent::Reduce();
	}
	
	public function ReducePlaintext() {
		return $this->GetAuthorString()."\n".\XBBC\TagTools::PrefixLines(parent::ReducePlaintext(), '| ');
	}
	
	public function GetAuthorString() {
		return $this->arg ? ucfirst($this->arg).' a écrit :' : false;
	}
}

//
// [blizzquote]
//
class BlizzquoteTag extends QuoteTag {
	public function __construct() {
		parent::__construct();
		$this->before = '<blockquote class="blizzquote">';
	}
	
	public function GetAuthorString() {
		return 'Blizzard :';
	}
}

//
// [mmoquote]
//
class MmoquoteTag extends QuoteTag {
	public function __construct() {
		parent::__construct();
		$this->before = '<blockquote class="blizzquote mmoquote">';
	}
	
	public function GetAuthorString() {
		return 'MMO-Champion :';
	}
}

//
// [h1] ... [h4]
//
class TitleTag extends \XBBC\SimpleTag {
	protected $level;
	
	protected static $IDS_CACHE = array();
	
	public function __construct($level) {
		parent::__construct("<h$level>", "</h$level>", true);
		$this->level = $level;
	}
	
	public function Reduce() {
		$id = 'h-'.sluggify(strip_tags($this->Content()));
		
		if(isset(self::$IDS_CACHE[$id])) {
			$id = $id.'-'.(self::$IDS_CACHE[$id]++);
		} else {
			self::$IDS_CACHE[$id] = 1;
		}
		
		$notoc = (isset($this->xargs['notoc'])) ? ' class="notoc"' : '';
		
		$this->before = "<h{$this->level} id=\"$id\"$notoc>";
		return parent::Reduce();
	}
	
	public function ReducePlaintext() {
		return strtoupper(parent::ReducePlaintext());
	}
}

//
// [list]
//
class ListTag extends \XBBC\SimpleTag {
	protected $max_nesting = 0;
	
	public function __construct() {
		parent::__construct("<ul>", "</ul>", true);
	}
	
	public function AllowText() {
		return false;
	}
	
	public function CanShift($tag) {
		return $tag instanceof ListItemTag;
	}
	
	public function Reduce() {
		switch(strtolower($this->arg)) {
			case '1';
				$this->before = '<ol>';
				$this->after = '</ol>';
				break;
				
			case 'a';
				$this->before = '<ol style="list-style-type: lower-alpha;">';
				$this->after = '</ol>';
				break;
				
			case 'i';
				$this->before = '<ol style="list-style-type: upper-roman">';
				$this->after = '</ol>';
				break;
		}
		
		return parent::Reduce();
	}
}

//
// [*]
//
class ListItemTag extends \XBBC\SimpleTag {
	protected $max_nesting = 0;
	
	public function __construct() {
		parent::__construct('<li>', '</li>');
		$this->display = \XBBC\DISPLAY_SPECIAL;
	}
	
	public function CanShift($tag) {
		if($tag instanceof self) {
			$this->ctx->Reduce($this->element());
			return true;
		}
		
		return parent::CanShift($tag);
	}
	
	public function StripWhitespaces() {
		return false;
	}
	
	public function ReducePlaintext() {
		return '* '.$this->Content();
	}
}

//
// [a]
//
class AnchorTag extends \XBBC\SingleTag {
	public function __construct() {
		parent::__construct(null, true);
	}
	
	protected function __create() {
		parent::__create();
		$this->html = '<div id="'.htmlspecialchars($this->arg).'"></div>';
	}
	
	public function ReducePlaintext() {
		return '#'.$this->arg;
	}
}

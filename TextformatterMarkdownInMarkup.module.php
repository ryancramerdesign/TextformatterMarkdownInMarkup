<?php namespace ProcessWire;

/**
 * ProcessWire 3.x Textformatter: Markdown in Markup/CKEditor
 * 
 * Copyright 2021 by Ryan Cramer | MPL 2.0
 * 
 * @property string $tagClasses Newline separated string of `tag:class` to specify class attribute by tag inserted.
 * @property string $disableTypes Names of tags/types to enable.
 * @property bool|int $defaultOff Is markdown processing off by default, requiring a `markdown=on` to enable in text?
 * 
 * @todo definition lists
 * @todo footnotes
 * 
 */
class TextformatterMarkdownInMarkup extends Textformatter implements Module, ConfigurableModule {
	
	public static function getModuleInfo() {
		return array(
			'title' => 'Markdown in Markup/HTML', 
			'version' => 1, 
			'summary' => 'Enables markdown to be used in existing markup/HTML like that from CKEditor.', 
			'requires' => 'ProcessWire>=3.0.164',
		); 
	}
	
	/**
	 * @var array|null
	 * 
	 */
	protected $tagClassesArray = null;

	/**
	 * @var array|null
	 * 
	 */
	protected $disableTypesArray = null;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->set('tagClasses', "pre:hljs\na:link"); 
		$this->set('disableTypes', ''); 
		$this->set('defaultOff', false);
		parent::__construct();
	}

	/**
	 * Cached value from getFindReplace method
	 * 
	 * @var array
	 * 
	 */
	protected $findReplace = array();
	
	/**
	 * Allow given markdown type/tag?
	 *
	 * @param string $type Typically the tag name the markdown type translates to, i.e. 'pre', 'code', 'strong', etc.
	 * @return bool
	 *
	 */
	public function allowType($type) {
		if($this->disableTypesArray === null) {
			foreach(explode(' ', $this->disableTypes) as $tag) {
				if(empty($tag)) continue;
				$tag = strtolower($tag);
				$this->disableTypesArray[$tag] = $tag;
			}
		}
		return !isset($this->disableTypesArray[$type]);
	}

	/**
	 * Get class attribute string configured for given tag
	 * 
	 * @param string $tag Tag name i.e. 'img', 'pre', etc.
	 * @param bool $getArray Get array rather than string? (default=false)
	 * @return string|array
	 * 
	 */
	public function getTagClass($tag, $getArray = false) {
		
		if($this->tagClassesArray === null) {
			foreach(explode("\n", $this->tagClasses) as $line) {
				if(strpos($line, ':') === false) continue;
				list($tag, $class) = explode(':', $line, 2);
				while(strpos($class, '  ') !== false) $class = str_replace('  ', ' ', $class);
				$this->tagClassesArray[trim(strtolower($tag))] = trim($class);
			}
		}
		
		if(!isset($tagClassesArray[$tag])) return ($getArray ? array() : '');
		
		$classes = $this->tagClassesArray[$tag];
		
		return $getArray ? explode(' ', $classes) : $classes;
	}

	/**
	 * Set the find/replace commands array
	 * 
	 * The $findReplace array should be indexed by command name (usually same HTML tag name) and 
	 * each item should contain the following in an associative array: 
	 * 
	 * - `test` (string): Test string passed to stripos() to check if find/replace should be attempted
	 * 
	 * - `find` (string): Regular expression to perform find, passed to preg_replace($find, …),
	 *    Or specify blank string if you will supply a callable to $replace and do not need a $matches 
	 *    argument passed to it.
	 * 
	 * - `replace` (string|callable): Replacement string passed to preg_replace($find, $replace, …),
	 *    can reference capture indexes from the $find string as $1, $2, etc. Or specify callable 
	 *    function to have it perform the replacement instead. Function will receive `&$value` argument
	 *    that may be modified directly. It will also receive a $matches array argument containing 
	 *    matches from $find, assuming a blank string was not provided for the $find argument. 
	 * 
	 * - `usage` (string): Short string indicating usag (see existing examples). 
	 * 
	 * @param array $findReplace Find/replace commands (associative array of associative arrays)
	 * @param bool $overwrite Specify true to completely replace existing values, or omit to array_merge 
	 * 
	 */
	public function setFindReplace(array $findReplace, $overwrite = false) {
		if($overwrite) {
			$this->findReplace = $findReplace;
		} else {
			$this->findReplace = array_merge($this->getFindReplace(), $findReplace);
		}
	}
	
	/**
	 * Get all find/replace commands
	 * 
	 * @return array
	 * 
	 */
	public function getFindReplace() {
		$module = $this;
		if(empty($this->findReplace)) $this->findReplace = array(
			'url' => array(
				'title' => 'Bracketed URL',
				'test' => '<http',
				'find' => '!<(https?://[^><:\s"\']+)>!i',
				'replace' => '[$1]($1)',
				'usage' => '<https://processwire.com>',
			), 
			'a' => array(
				'title' => 'Link',
				'test' => '](',
				'find' => '/(?<!!)\[([^]]+)\]\(([^)"\']+)\)/',
				'replace' => function(&$value, $matches) use($module) {
					$module->formatLinks($value, $matches);
				}, 
				'usage' => '[url](text)', 
			),
			'img' => array(
				'title' => 'Image',
				'test' => '![', 
				'find' => '/!\[(.*?)\]\(([^)]+)\)/', 
				'replace' => function(&$value, $matches) use($module) {
					$module->formatImages($value, $matches);
				}, 
				'usage' => '![alt](/path/file.jpg)', 
			),
			'strong' => array(
				'title' => 'Strong text',
				'test' => '**',
				'find' => '@[*]{2}(.+?)[*]{2}@',
				'replace' => '<strong class="pwt-strong">$1</strong>',
				'usage' => '**text**', 
			),
			'em' => array(
				'title' => 'Emphasis text',
				'test' => '*',
				'find' => '@(?<!\*)\*([^*\s][^*]+?)\*(?!\*)@',
				'replace' => '<em class="pwt-em">$1</em>',
				'usage' => '*text*', 
			),
			'u' => array(
				'title' => 'Underline text',
				'test' => '__',
				'find' => '@(?<!_)__(.+?)__(?!_)@',
				'replace' => '<u class="pwt-u">$1</u>',
				'usage' => '__text__', 
			),
			's' => array(
				'title' => 'Strikethrough text',
				'test' => '~~',
				'find' => '!~~([^~].*?)~~!',
				'replace' => '<s class="pwt-s">$1</s>',
				'usage' => '~~text~~', 
			),
			'hr' => array(
				'title' => 'Horizontal rule',
				'test' => array('<p>---', '---'),
				'find' => '!<p>\s*[-]{3,}\s*</p>)!m',
				'replace' => '<hr class="pwt-hr" />',
				'usage' => '---', 
			),
			'blockquote' => array(
				'title' => 'Blockquote',
				'test' => '<p>&gt;',
				'find' => '!<p>&gt;\s*([^>].+?)</p>!s',
				'replace' => '<blockquote class="pwt-blockquote"><p>$1</p></blockquote>',
				'usage' => '> quoted text',
			),
			'h' => array(
				'title' => 'Headline (h1-h6)',
				'test' => array('<p>#', '# '),
				'find' => '!<(?:p|h\d)>\s*([#]+)(.+?)</(?:p|h\d)>!i',
				'replace' => function(&$value, $matches) use($module) {
					$module->formatHeadings($value, $matches);
				},
				'usage' => "# heading 1\n\n## heading 2\n\n### heading 3",
			),
			'pre' => array(
				'title' => 'Fenced code block',
				'test' => array('<p>```', '<p>~~~'), 
				'find' => '!<p>[`~]{3,}([a-z]*\s*)(.+?)\s*[`~]{3,}\s*</p>!is',
				'replace' => function(&$value, $matches) use($module) {
					$module->formatPre($value, $matches);
				},
				'usage' => "```\n// some code\n```\n\n~~~php\n// some PHP code\n~~~",
			),
			'code' => array( // must come after pre-code
				'title' => 'Inline code',
				'test' => '`',
				'find' => '/`([^`]+)`/is',
				'replace' => '<code class="pwt-code">$1</code>',
				'usage' => '`code`',
				 
			),
			'ul' => array(
				'title' => 'Unordered list',
				'test' => array('<p>- ', '<p>* '),
				'find' => '',
				'replace' => function(&$value) use($module) {
					$module->formatLists($value);
				},
				'usage' => "- red\n- green\n- blue",
			),
			'ol' => array(
				'title' => 'Ordered list',
				'test' => '<p>1. ',
				'find' => '',
				'replace' => function(&$value) use($module) {
					if($module->allowType('ul')) return; // <ol> already processed with <ul>
					$module->formatLists($value);
				},
				'usage' => "1. one\n2. two\n3. three", 
			),
			/*
			'footnote' => array(
				'title' => 'Footnote',
				'test' => '[^', 
				'find' => '!\[^([-_.\w\d]+)\]!', 
				'replace' => function(&$value, $matches) use($module) {
					$module->formatFootnotes($value, $matches);
				}, 
			),
			*/
		);
		
		return $this->findReplace;
	}

	/**
	 * Format links
	 *
	 * @param string $value
	 * @param array $matches
	 *
	 */
	public function formatLinks(&$value, array $matches) {
		// pattern: /(?<!!)\[([^]]+)\]\(([^)"']+)\)/
		$a = array();
		$sanitizer = $this->wire()->sanitizer;
		$purifier = $sanitizer->purifier();
		foreach($matches[0] as $key => $fullMatch) {
			$text = $matches[1][$key];
			$href = $sanitizer->entities1($matches[2][$key]);
			$link = "<a href=\"$href\">$text</a>";
			$link = $purifier->purify($link);
			if(!$link) continue;
			$link = str_replace('<a ', '<a class="pwt-a" ', $link);
			$a[$fullMatch] = $link;
		}
		if(count($a)) $value = str_replace(array_keys($a), array_values($a), $value);
	}

	/**
	 * Format links
	 *
	 * @param string $value
	 * @param array $matches
	 *
	 */
	public function formatImages(&$value, array $matches) {
		// pattern: /!\[(.*?)\]\(([^)]+)\)/
		$a = array();
		$sanitizer = $this->wire()->sanitizer;
		$purifier = $sanitizer->purifier();
		foreach($matches[0] as $key => $fullMatch) {
			$alt = $sanitizer->entities1($matches[1][$key]);
			$src = $sanitizer->entities1($matches[2][$key]);
			$img = "<img src=\"$src\" alt=\"$alt\" />";
			$img = $purifier->purify($img); 
			if(!$img) continue;
			$img = str_replace('<img ', '<img class="pwt-img" ', $img);
			$a[$fullMatch] = $img;
			
		}
		if(count($a)) $value = str_replace(array_keys($a), array_values($a), $value);
	}

	/**
	 * Format headings
	 * 
	 * @param string $value
	 * @param array $matches
	 * 
	 */
	public function formatHeadings(&$value, array $matches) {
		// pattern: <(?:p|h\d)>\s*([#]+)(.+?)</(?:p|h\d)>
		$a = array();
		foreach($matches[0] as $key => $fullMatch) {
			$h = 'h' . strlen($matches[1][$key]);
			$text = trim($matches[2][$key]);
			$class = $this->getTagClass($h);
			if(empty($class)) $class = 'pwt-h';
			$attr = " class=\"$class\"";
			if(strpos($text, '{#') && preg_match('!\{#([^}]+)\}!', $text, $m)) {
				// custom #id attribute, i.e. ### My text {#my-id}
				$attr .= ' id="' . trim($m[1]) . '"';
				$text = trim(str_replace($m[0], '', $text));
			}
			$a[$fullMatch] = "<$h$attr>$text</$h>";
		}
		$value = str_ireplace(array_keys($a), array_values($a), $value);
	}

	/**
	 * Format fenced code blocks
	 * 
	 * @param string $value
	 * @param array $matches
	 * 
	 */
	public function formatPre(&$value, array $matches) {
		// pattern: !<p>[`~]{3,}([a-z]*\s*)(.+?)\s*[`~]{3,}\s*</p>!is
		$a = array();
		foreach($matches[0] as $key => $fullMatch) {
			$class = $this->getTagClass('pre');
			$lang = trim($matches[1][$key]);
			if(strlen($lang)) {
				if(strpos($class, '{lang}') !== false) {
					$class = str_replace('{lang}', $lang, $class);
				} else {
					$class .= ($class ? ' ' : '') . $lang;
				}
			}
			$code = $matches[2][$key];
			$code = preg_replace('!<br[ /]*>\n?!i', "\n", $code);
			$code = str_ireplace(array('<p>', '</p>'), array("\n\n", ''), $code);
			$pre = $class ? "pre class=\"$class\"" : "pre";
			$a[$fullMatch] = "<$pre><code>" . trim($code) . "</code></pre>";
		}
		$value = str_replace(array_keys($a), array_values($a), $value);
	}

	/**
	 * Format footnotes (to-do)
	 * 
	 * @param string $value
	 * @param array $matches
	 * 
	 */
	public function formatFootnotes(&$value, array $matches) {
		
		$footnotes = array();
		$endnotes = array();
		$replacements = array();
		$num = 0;
		
		foreach($matches[0] as $key => $fullMatch) {
			$name = $matches[1][$key];
			if(strpos($value, "$fullMatch:") === false) continue;
			$footnotes[++$num] = $name;
			$endnotes[$num] = "[endnote^$name]:";
			$replacements["$fullMatch:"] = str_replace("[^", "[endnote^" , $fullMatch);
			$replacements["$fullMatch"] =
				"<sup id=\"fnref:$name\" role=\"doc-noteref\">" .
				"<a href=\"#fn:$name\" class=\"pwt-footnote\" rel=\"footnote\">$num</a>" .
				"</sup>";
			// @todo continue from here
		}
	}

	/**
	 * Apply markdown lists
	 * 
	 * @param string $value
	 * 
	 */
	public function formatLists(&$value) {
	
		$matchTypes = array();
		if($this->allowType('ul')) $matchTypes = array('\- ', '\* '); 
		if($this->allowType('ol')) $matchTypes[] = '\d+\. ';
		if(!count($matchTypes)) return;
		
		$matchTypes = implode('|', $matchTypes);
		$items = preg_split('!(<p>|<br />)\s*(' . $matchTypes . ')!is', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
		$out = array_shift($items); // text before list
		$itemNum = 0;
		$listType = '';
		
		do {
			$itemNum++;
			$itemTag = trim(array_shift($items), '< />'); // 2: i.e. 'p' or 'br'
			$itemType = strlen(trim(array_shift($items))) === 1 ? 'ul' : 'ol'; // either '-' or '1.', 2.', etc.
			$itemText = array_shift($items); // 3: Text for <li> and potentially </p><p>text after list
			
			if($listType && $itemType != $listType) {
				// if list of one type was already open and list of another type started
				$out .= "\n</$listType>";
				$itemNum = 1;
			}	
			
			if($itemNum === 1) {
				// first item in new list
				$listType = $itemType;
				$out .= "\n<$listType class=\"pwt-$listType\">";
			}
			
			if(strpos($itemText, '</p>')) {
				// end of item or end of list
				list($itemText, $textAfterItem) = explode('</p>', $itemText, 2);
				if($itemTag !== 'br') $itemText = "<$itemTag>$itemText</$itemTag>";
				$out .= "\n\t<li>$itemText</li>";
				if(strlen(trim($textAfterItem))) {
					// end of list
					$out .= "\n</$listType>\n$textAfterItem";
					$itemNum = 0;
					$listType = '';
				} else {
					// just end of item
				}
			} else {
				$out .= "\n\t<li>$itemText</li>";
			}
			
		} while(count($items));
		
		if($itemNum) $out .= "\n</$listType>";
	
		$value = $out;
	}

	/**
	 * Format a block of markup
	 * 
	 * @param string $value
	 * 
	 */
	protected function formatBlock(&$value) {
		
		$typesApplied = array();
		$replacements = array();

		foreach($this->getFindReplace() as $name => $item) {

			if(!$this->allowType($name)) continue; // type is not allowed
			
			if($name === 'ol' && isset($typesApplied['ul'])) {
				// ol already processed with ul
				$typesApplied['ol'] = 'ol';
				continue;
			}

			if(is_array($item['test'])) {
				$found = false;
				foreach($item['test'] as $test) {
					if(stripos($value, $test) !== false) $found = true;
					if($found) break;
				}
				if(!$found) continue; // no tests matched

			} else if(stripos($value, $item['test']) === false) {
				continue; // test does not match
			}

			if(is_string($item['replace'])) {
				$value = preg_replace($item['find'], $item['replace'], $value);

			} else if(is_callable($item['replace'])) {
				$func = $item['replace'];
				if(strlen($item['find'])) {
					if(preg_match_all($item['find'], $value, $matches)) {
						$func($value, $matches);
					}
				} else {
					$func($value);
				}

			} else {
				continue;
			}

			$typesApplied[$name] = $name;
		}

		foreach($typesApplied as $name) {
			$class = $this->getTagClass($name);
			$find = " class=\"pwt-$name\"";
			$replace = $class ? " class=\"$class\"" : "";
			$replacements[$find] = $replace;
		}

		if(count($replacements)) {
			$value = str_replace(array_keys($replacements), array_values($replacements), $value);
		}
	}

	/**
	 * Format the given $value 
	 * 
	 * @param string $value
	 * 
	 */
	public function format(&$value) {
		
		$off = (int) $this->defaultOff;
		
		if(stripos($value, 'markdown=') === false) {
			// no markdown commands are present in value
			if(!$off) $this->formatBlock($value);
			return;
		}
			
		$blocks = preg_split('!<p>\s*markdown=(on|off|\d)\s*</p>!i', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
		if(!count($blocks)) return;
		$value = '';
		
		while(count($blocks)) {
			$blockValue = array_shift($blocks); // text before <p>markdown=...
			if(!$off && strlen($blockValue)) $this->formatBlock($blockValue);
			$value .= $blockValue;
			$cmd = count($blocks) ? strtolower(array_shift($blocks)) : '';
			$off = $cmd === 'off' || $cmd === '0';
		}
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$modules = $this->wire()->modules;
		$findReplace = $this->getFindReplace();
		$types = implode(' ', array_keys($findReplace));
		$types = str_replace(' h ', ' h h1 h2 h3 h4 h5 h6 ', $types);
	
		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'disableTypes'); 
		$f->label = $this->_('Type/tag names to disable'); 
		$f->description = $this->_('Specify a space separated string of types/tags to disable from markdown processing.');
		$f->notes = sprintf($this->_('Can be any of the following: `%s`'), $types); 
		$f->val($this->disableTypes);
		$inputfields->add($f);
	
		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'tagClasses');
		$f->label = $this->_('Tag classes');
		$f->description = 
			$this->_('Enter one per line of `tag:class` where `tag` is the name of a tag inserted by markdown and `class` is a class attribute to add.') . ' ' . 
			$this->_('For example: `ul:uk-list` will add the `uk-list` class to `ul` tags inserted by markdown.');
		$f->notes = 
			sprintf($this->_('The `tag` portion can be any of the following: `%s`.'), $types) . ' ' . 
			$this->_('The `class` portion can be one or more space separated class names.'); 
		$f->val($this->tagClasses);
		$inputfields->add($f);

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'defaultOff'); 
		$f->label = $this->_('Disable markdown processing by default?'); 
		$f->description = 
			$this->_('When “Yes” is selected, markdown will not be processed except after a paragraph containing only `markdown=on` appears.') . ' ' . 
			$this->_('When “No” is selected, markdown will be processed automatically, unless/until a paragraph containing only `markdown=off` appears.') . ' ' . 
			$this->_('Regardless of selection, you can enter `markdown=on` or `markdown=off` to turn on/off as needed.') . ' ' . 
			$this->_('The `markdown=on|off` commands are always removed from the output. The “on” or “off” can also be specified as `1` or `0`.');
		$f->val((int) $this->defaultOff ? 1 : 0);
		$inputfields->add($f);

		$notes = array(
			'ul' => 'Optionally place a list item in its own paragraph to convert to <li><p>text</p></li>. Nested lists not supported.',
			'ol' => 'Optionally place a list item in its own paragraph to convert to <li><p>1. one</p></li>. Nested lists not supported.',
			'pre' => 'You can use backtick or tilde style (with or without language like “php”)',
		);
		
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->name = '_usage';
		$f->label = $this->_('Supported tags and usage');
		/** @var MarkupAdminDataTable $table */
		$table = $modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		$table->headerRow(array(
			'Title',
			'Tag/name',
			'Usage example',
		));
		foreach($findReplace as $name => $info) {
			$test = $info['test']; 
			if(is_array($test)) $test = implode(' ', $test);
			$para = stripos($test, '<p>') !== false;
			$note = isset($notes[$name]) ? $notes[$name] : '';
			if($para) $info['title'] .= ' *';
			if($name != 'url') $name = '&lt;' . $name . '&gt;';
			$table->row(array(
				"$info[title]",
				"<code>$name</code>", 
				"<pre style='margin:0'>" . htmlspecialchars($info['usage']) . "</pre>" . 
					($note ? "<div style='margin-top:5px' class='detail'>" . htmlspecialchars($note) . "</div>" : ""),
			)); 
		}
		$f->notes = trim('* Block level elements must be in their own <p>paragraph</p> to be converted.');
		$f->value = $table->render();
		$inputfields->add($f);
	}
	
}

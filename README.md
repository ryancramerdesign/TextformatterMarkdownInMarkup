# Markdown in Markup/HTML

**ProcessWire 3.x Textformatter module that enables you to use Markdown
formatting codes in rich text fields like CKEditor.**

A significant amount of content that is populated into the “bodycopy” field
of websites is not actually written in the CMS and instead originates from 
text editors, word processors, and other tools outside of the CMS. It then 
gets pasted into a richtext editor like CKEditor, and then manually 
formatted into HTML using tools in the richtext editor. This process of 
manually converting links, lists, headlines, bold, and italic text and more 
in the richtext editor can be sometimes very time consuming. 

This module provides a time saving alternative, enabling use of markdown
formatted text in a richtext editor. It remains as markdown formatted 
text in the editor, but as soon as it is rendered on the front-end it is 
automatically formatted as HTML. This means that text like `**this**`
gets converted to **this**, links like `[this](https://processwire.com)`
get converted to [this](https://processwire.com), and so on for most types
of [markdown formatting](https://www.markdownguide.org/cheat-sheet/). 

This enables markdown formatted text to be written anywhere and the
formatting to be rendered properly in your body copy when viewed on your 
website. Using this module means that you can mix richtext and markdown 
in the same copy. No longer do you have to manually convert all of the 
links, lists, bold/italic, and so on in pasted in text. 

- **Where to use it**: Use on fields that already contain HTML markup, like 
  CKEditor fields. 

- **Where NOT to use it:** Don’t use it on text fields that do not 
  already contain markup. If you want to use Markdown on those, use 
  ProcessWire’s core Markdown/Parsedown Textformatter module.

### Why not just use the core Markdown/Parsedown module? 

Because most of it doesn’t work if the text is already HTML (like CKEditor 
output). This is because traditional markdown parsing is looking for anchor 
points like newlines rather than boundaries of block-level markup tags, for 
example. In HTML, whitespace collapses, whereas in traditional markdown text, 
whitespace has meaning, such as indentation at the beginning of a line,
or 2 spaces at the end of a line, etc. To enable some markdown support in 
HTML it was necessary to build a markdown parser for this purpose from 
scratch, as it's an entirely different kind of parsing, even if the 
intended output is the same. 

## Supported markdown

- Bracketed URLs: `<https://processwire.com>` i.e. <https://processwire.com>
- Links: `[Example](https://processwire.com)` i.e. [Example](https://processwire.com)
- Strong text: `**example**` i.e. **example**
- Underline text: `__example__` i.e. <u>example</u> <small>(CUSTOM)</small>
- Emphasized text: `*example*` i.e. *example* 
- Strikethrough text: `~~example~~` i.e. <s>example</s> <small>(EXTENDED)</small>
- Image: `![alt text](/url/to/image.jpg)`.
- Blockquote: `> quoted text`
- Headings: `# Heading 1`, `## Heading 2`, `### Heading 3`, etc.
- Headings with ID: `## Heading {#custom-id}` <small>(EXTENDED)</small>
- Inline code: <code>\`example\`</code> converts to `<code>example</code>`
- Horizontal rule `---` converts to `<hr />`
- Ordered lists where each list item is identified with a leading hyphen
  i.e. `- List item`  
- Unordered lists where each list item is identified with a digit and period
  i.e. `1. List item` 
- Fenced code blocks opened/closed with `~~~` on their own line.
  <small>(EXTENDED)</small>
- Language specific fenced code blocks opened with `~~~php` where `php` can
  be any supported language name. <small>(EXTENDED)</small>
  
<small>(CUSTOM):</small> *Indicates items that are not officially part of markdown.*    
<small>(EXTENDED):</small> *Indicates items that are part of extended syntax markdown.*

*For more details on supported markdown, examples and related information,
see the module configuration screen.*

### Please note

In order to be converted to HTML, block level elements like headlines, 
blockquote, code blocks, horizontal rules, etc., must be written in their
own paragraph in the rich text editor. For example, `<p>---</p>` would convert
to a horizontal rule, but `<p>hi---</p>` would not. 
  
Nested `<ul>` or `<ol>` lists are not supported. These require whitespace 
indentation which is something that does not directly translate to or survive
HTML outside of a `<pre>` block. So when/if you need nested lists, use the 
tools of the editor rather than markdown. For the same reason, you cannot use
indented preformatted code blocks, but you can use fenced code blocks. 

You can turn markdown processing off (or on) in any part of text by typing
`markdown=off` or `markdown=on` in your text (in separate paragraph). These
commands are recognized by the parser and automatically removed from the 
output as well. 

Please keep an eye out for unintended markdown translation and open an issue
report if you come across any instances. 



## How to install

1. Copy the files for this module to:
   `/site/modules/TextformatterMarkdownInMarkup/`
   
2. In your admin go to *Modules > Refresh*.    

3. Click “Install” for *Textformatter > Markdown in Markup*. Note the 
   configuration settings and decide if you want this module enabled or
   disabled by default. 

4. Go to *Setup > Fields > [any CKEditor field]*, and on the “Details” tab
   select this module for the “Textformatters”. Save.

5. Edit a page using the field, enter some markdown, save and view. View 
   some other pages to make sure there is no unintentional markdown 
   translation occurring. If there is, you may want to have markdown 
   disabled by default (see setting in module config). 

## Module configuration  

- **Type/tag names to disable:** Specify a space separated string of 
  types/tags to disable from markdown processing. If you only intend to use
  this module for inline formatting like bold, italic, links, then you may
  choose to use this setting to disable other kinds of markdown translations.
  For instance, entering `img hr blockquote h pre ul ol` would disable all
  of the block-level formatting and leave the inline formatting active.
  
- **Tag classes:** Enter one per line of `tag:class` where tag is the name of 
  a tag inserted by markdown and `class` is a class attribute to add. For 
  example: `ul:uk-list` will add the `uk-list` class to `ul` tags inserted 
  by the markdown parser. `pre:hljs` would add the `hljs` class to all fenced
  preformatted code blocks. 
  
- **Disable markdown processing by default?** When “Yes” is selected, markdown 
  will not be processed except after a paragraph containing only `markdown=on`
  appears. When “No” is selected, markdown will be processed automatically, 
  unless/until a paragraph containing only `markdown=off` appears. Regardless 
  of selection, you can enter `markdown=on` or `markdown=off` to turn on/off 
  as needed in your text/copy. The `markdown=on|off` commands are always 
  removed from the output. The “on” or “off” can also be specified as 1 or 0. 

## Future versions

Currently we don't support footnotes or definition lists (extended markdown), and 
I would like to add support for both. These are both things that aren't easily 
accomplished in CKEditor, so could be especially useful here.

---
Copyright 2021 by Ryan Cramer


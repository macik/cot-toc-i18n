# Table of Contents plugin for Cotonti

Renders contents tree for a given category.

> **Note ** This extended version of original [«TOC» Plugin](https://github.com/trustmaster/cot-toc) by [Trustmaster](https://github.com/trustmaster).

## New features

* **I18n compatibility layer** — now plugin support «i18n» data if corresponding plugin installed. If support enabled (in Plugin configuration menu) all current locale translation data will be merged and available for output. It's possible to output locale flags with `{ROW_FLAG}` tag.
* **Support all page and translation data fields** — not only `title` and `description`.

## Installation

1. Download.
2. Copy 'toc' to your plugins folder.
3. Install via Administration / Extensions.

## Demo

To see the plugin in action visit [Cotonti Documentation](http://www.cotonti.com/docs/). Both the documentation index page and sidebar on the right on regular pages like [this](http://www.cotonti.com/docs/ext/extensions/extdevguide) are generated with this plugin.

## Usage

The plugin provides a callback/widget that can be used in page.list.tpl and page.tpl files (including category-specific files).

The following will print entire table of contents for category with code 'docs':

```
{PHP|toc('docs')}
```

By default 'toc.tpl' is used to generate a TOC, but you can use custom TPL files for different widgets. The following will generate a TOC using toc.main.tpl:

```
{PHP|toc('docs', 'toc.main')}
```

In subcategories and on regular pages you wouldn't like to include the full tree. You can tell the widget to show pages for current category only:

```
{PHP|toc('docs', 'toc', 1)}
```

This will leave all the non-current categories collapsed.

Use CSS to style the TOC. Here is an example:

```css
ul.toc {
	list-style: none;
	margin: 10px 10px 10px 20px;
	display: block;
	clear: both;
}

ul.toc a.current {
	background-color: lightYellow;
}

ul.toc-main {
	list-style: none;
	margin: 10px 10px 10px 40px;
	display: block;
	clear: both;
}

ul.toc-main a.level-1.cat {
	font-size: 180%;
}

ul.toc-main a.level-2.cat {
	font-size: 150%;
}

ul.toc-main a.level-3.cat {
	font-size: 120%;
}
```

For more information see 'toc.php' source and PHPDoc blocks in it.

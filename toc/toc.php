<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=page.list.tags,page.tags
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_langfile('toc', 'plug');

/**
 * Generates Table of Contents (TOC) for a given category
 * @param  string  $cat           Root category for TOC
 * @param  string  $tpl           Template code
 * @param  boolean $only_siblings Include pages for current category only
 * @return string                 Rendered widget HTML
 */
function toc($cat, $tpl = 'toc', $only_siblings = FALSE)
{
	global $cache, $cfg, $lang, $i18n_locale;

	$cache_ttl = is_numeric($cfg['plugin']['toc']['cache_ttl']) ? (int) $cfg['plugin']['toc']['cache_ttl'] : 0;

	// Load the structure tree
	$cache_loaded = false;
	if ($cache && $cache_ttl)
	{

		if ($cfg['plugin']['toc']['use_i18n'] && cot_plugin_active('i18n') && $i18n_locale)
		{
			$toc_lang = 'i18n-'.$i18n_locale;
		}
		else
		{
			$toc_lang = $lang;
		}
		$sort_field = $cfg['plugin']['toc']['sort_field'];
		$sort_order = $cfg['plugin']['toc']['sort_way'];

		$cache_name = 'toc_tree_' . implode('-', array($cat, $toc_lang, $sort_field, $sort_order));
		global $$cache_name;
		if ($$cache_name)
		{
			$toc_tree = $$cache_name;
			$cache_loaded = true;
		}
	}

	if (!$cache_loaded)
	{
		$toc_tree = array();
		$cats = cot_structure_children('page', $cat, FALSE, FALSE, FALSE, FALSE);
		foreach ($cats as $cat)
		{
			$toc_tree[] = toc_load_cat($cat);
		}

		if ($cache && $cache_ttl)
		{
			$cache->db->store($cache_name, $toc_tree, 'cot', $cache_ttl);
		}
	}

	// Render the template
	$t = new XTemplate(cot_tplfile($tpl, 'plug'));

	$num = 1;
	foreach ($toc_tree as $item)
	{
		toc_display($t, $tpl, $item, 1, $num, $only_siblings);
		$num++;
	}
	$t->assign('LIST_LEVEL', 0);
	$t->parse('LIST');
	return $t->text('LIST');
}

/**
 * Renders a TOC item recursively
 * @param  XTemplate $t             Template object
 * @param  array     $item          TOC tree node
 * @param  int       $level         Depth from root
 * @param  string    $number        TOC reference number
 * @param  boolean   $only_siblings Show only current category pages
 */
function toc_display($t, $tpl, $item, $level, $number, $only_siblings = FALSE)
{
	global $c, $id, $pag;

	$current_cat = defined('COT_LIST') ? $c : $pag['page_cat'];
	$view_page = false;
	if ($item['type'] == 'cat')
	{
		// Check permissions
		if (!cot_auth('page', $item['code'], 'R'))
			continue;

		// Render subtrees
		if (count($item['items']) > 0)
		{
			$t1 = new XTemplate(cot_tplfile($tpl, 'plug'));
			$num = 1;
			foreach ($item['items'] as $sub)
			{
				if (!$only_siblings || $sub['type'] == 'cat' || $current_cat == $item['code'])
				{
					toc_display($t1, $tpl, $sub, $level + 1, "$number.$num", $only_siblings);
					$num++;
					$view_page = true;
				}
			}
			if ($view_page){
				$t1->assign('LIST_LEVEL', $level);
				$t1->parse('LIST');
			}
			// Nest the list
			$t->assign('ROW_ITEMS', $t1->text('LIST'));
			unset($t1);
			unset($item['items']);
		}
		else
		{
			$t->assign('ROW_ITEMS', '');
		}
	}
	else
	{
		$t->assign('ROW_ITEMS', '');
	}

	// Render the item itself
	$is_curent = $item['type'] == 'page' ? $id == $item['id'] : $current_cat == $item['code'];
	$current = $is_curent ? 'current' : '';
	$row = array(
		'ROW_LEVEL'    => $level,
		'ROW_CURRENT'  => $current,
		'ROW_NUMBER'   => $number,
		'ROW_FLAG'     => $item['locale'] ? cot_rc('icon_flag', array('code' => $item['locale'], 'alt' => $item['locale'])) : '',
	);
	foreach ($item as $field => $data)
	{
		if (!is_array($data)) $row['ROW_'.strtoupper($field)] = htmlspecialchars($data);
	}
	$t->assign($row);
	$t->parse('LIST.ROW');
}

/**
 * Loads a given category subtree
 * @param  string $code Category code
 * @return array        Tree
 */
function toc_load_cat($code)
{
	global $structure, $cfg;
	$scat = $structure['page'][$code];
	$cat = array(
		'type'  => 'cat',
		'code'  => $code,
		'url'   => cot_url('page', array('c' => $code)),
		'title' => $scat['title'],
		'desc'  => $scat['desc'],
		'count' => $scat['count'],
		'items'  => array()
	);
	// get i18n data
	if ($cfg['plugin']['toc']['use_i18n']) $cat = toc_i18n_cat($cat);

	// Load child subtrees
	$subs = cot_structure_children('page', $code, FALSE, FALSE, FALSE, FALSE);
	foreach ($subs as $subcat)
	{
		$cat['items'][] = toc_load_cat($subcat);
	}
	$cat['items'] = array_merge($cat['items'], toc_load_pages($code));

	return $cat;
}

/**
 * Return array of pages data
 * @param string $code Category code
 */
function toc_load_pages($code)
{
	global $cfg, $db, $db_pages, $sys, $i18n_locale, $db_i18n_pages, $i18n_read;

	static $sort_field = null;
	static $sort_order = null;
	static $second_sort = '';

	$pages = array();
	$use_i18n = $cfg['plugin']['toc']['use_i18n'];

	// get i18n info if available
	if ($use_i18n && cot_plugin_active('i18n') && $i18n_locale && $i18n_read && cot_i18n_enabled($code))
	{
		$join_columns .= ',i18n.*';
		$join_condition .= " LEFT JOIN $db_i18n_pages AS i18n ON i18n.ipage_id = p.page_id AND i18n.ipage_locale = '$i18n_locale' AND i18n.ipage_id IS NOT NULL";
		$i18n_active = true;
	}

	if (!$sort_field)
	{
		$sf_cfg = $cfg['plugin']['toc']['sort_field'];
		if ($sf_cfg)
		{
			// check for sorting_field is exists
			$res = $db->query("SELECT p.* $join_columns FROM $db_pages as p $join_condition
				WHERE page_state = 0 AND page_begin <= {$sys['now']} AND (page_expire = 0 OR page_expire > {$sys['now']})
				AND page_cat = ?
				LIMIT 1", array($code));
			$row = $res->fetch();
			if (!array_key_exists($sf_cfg, $row))
			{
				$def_sorting = true;
			}
			else
			{
				$sort_field = $sf_cfg;
				$sort_order = $cfg['plugin']['toc']['sort_way'];
			}
		}
		else
		{
			$def_sorting = true;
		}
		if ($def_sorting)
		{
			$sort_field = 'page_id';
			$sort_order = 'ASC';
		}
		else
		{
			$second_sort =  ', page_id ASC';
		}
	}
	// Load child pages
	$res = $db->query("SELECT p.* $join_columns FROM $db_pages as p $join_condition
		WHERE page_state = 0 AND page_begin <= {$sys['now']} AND (page_expire = 0 OR page_expire > {$sys['now']})
		AND page_cat = ?
		ORDER BY $sort_field $sort_order $second_sort", array($code));

	foreach ($res->fetchAll() as $row)
	{
		$pag = array();
		$urlp = empty($row['page_alias']) ? array('c' => $code, 'id' => $row['page_id']) : array('c' => $code, 'al' => $row['page_alias']) ;
		$pag['url'] = cot_url('page', $urlp);
		$pag['type'] = 'page';
		foreach ($row as $name => $data)
		{
			list($prefix, $field) = explode('_', $name, 2);
			if (in_array($field, array('url','type'))) $field = '_'.$field; // for compatibility with old TOC tags
			if (is_string($data)) $data = cot_cutstring($data, 255);
			if ($prefix == 'page')
			{
				$pag[$field] = $data;
			}
			elseif ($use_i18n && $prefix == 'ipage')
			{
				if (!in_array($field, array('id')))
				{
					if (!isset($pag[$field]) || isset($data)) $pag[$field] = $data;
				}
			}
		}
		if (!$pag['locale']) $pag['locale'] = $cfg['defaultlang'];
		$pages[] = $pag;
	}
	return $pages;
}

/**
 * Updates category info with i18n data
 * @param array $cat Category data array
 * @return array Updated category data
 */
function toc_i18n_cat($cat)
{
	global $cfg, $structure, $i18n_structure, $i18n_locale, $i18n_read;

	if (cot_plugin_active('i18n') && $i18n_read && is_array($cat))
	{
		$code = $cat['code'];
		if (cot_i18n_enabled($code) && $i18n_locale && is_array($i18n_structure))
		{
			$i18n_cat = $i18n_structure[$code][$i18n_locale];
			if (is_array($i18n_cat)) $cat = array_merge($cat, $i18n_cat);
			$cat['locale'] = $i18n_locale;
		}
	}
	return $cat;
}
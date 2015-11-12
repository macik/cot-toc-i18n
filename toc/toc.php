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
	global $cache, $cfg, $lang;

	// Load the structure tree
	$cache_loaded = false;
	if ($cache && $cfg['plugin']['toc']['cache'])
	{
		$cache_name = 'toc_tree_' . $cat . '-' . $lang;
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

		if ($cache && $cfg['plugin']['toc']['cache'])
		{
			$cache->db->store($cache_name, $toc_tree, 'cot', 300);
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
	$t->assign(array(
		'ROW_TYPE'    => $item['type'],
		'ROW_LEVEL'   => $level,
		'ROW_CURRENT' => $current,
		'ROW_NUMBER'  => $number,
		'ROW_URL'     => $item['url'],
		'ROW_TITLE'   => htmlspecialchars($item['title']),
		'ROW_DESC'    => htmlspecialchars($item['desc'])
	));
	$t->parse('LIST.ROW');
}

/**
 * Loads a given category subtree
 * @param  string $code Category code
 * @return array        Tree
 */
function toc_load_cat($code)
{
	global $cfg, $db, $db_pages, $structure, $sys;
	$cat = array(
		'type'  => 'cat',
		'code'  => $code,
		'url'   => cot_url('page', array('c' => $code)),
		'title' => $structure['page'][$code]['title'],
		'desc'  => $structure['page'][$code]['desc'],
		'count' => $structure['page'][$code]['count'],
		'items'  => array()
	);

	// Load child subtrees
	$subs = cot_structure_children('page', $code, FALSE, FALSE, FALSE, FALSE);
	foreach ($subs as $subcat)
	{
		$cat['items'][] = toc_load_cat($subcat);
	}

	// Load child pages
	$res = $db->query("SELECT * FROM $db_pages
		WHERE page_state = 0 AND page_begin <= {$sys['now']} AND (page_expire = 0 OR page_expire > {$sys['now']})
			AND page_cat = ?
		ORDER BY {$cfg['plugin']['toc']['sort_field']} {$cfg['plugin']['toc']['sort_way']}", array($code));

	foreach ($res->fetchAll() as $row)
	{
		$urlp = empty($row['page_alias']) ? array('c' => $code, 'id' => $row['page_id']) : array('c' => $code, 'al' => $row['page_alias']) ;
		$pag = array(
			'type'  => 'page',
			'id'    => $row['page_id'],
			'alias' => $row['page_alias'],
			'url'   => cot_url('page', $urlp),
			'title' => $row['page_title'],
			'desc'  => $row['page_desc']
		);
		$cat['items'][] = $pag;
	}
	return $cat;
}

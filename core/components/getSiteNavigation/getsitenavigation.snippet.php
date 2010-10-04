<?php
/*
 * Copyright (C) 2010 Mihai Şucan.
 *
 * This file is part of getSiteNavigation.
 *
 * getSiteNavigation is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * getSiteNavigation is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with getSiteNavigation.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * getSiteNavigation - generate web site navigation menus.
 *
 * @author Mihai Șucan (mihai.sucan.ro)
 * @contributors Dennis Schubert (dennis-schubert.de)
 * @version 0.1-alpha1
 */

/**
 * Properties:
 *
 * &startIdLevels the ID of the root resource from which each level of menus is 
 * displayed. Default is ID 0 for menu level 0. For the deeper levels, the 
 * default is the active resource from the upper level.
 *    Example: &startIdLevels=`0,,7,121`
 *             which reads:
 *             level 0: root ID is 0
 *             level 1: root ID is the active resource from level 1
 *             level 2: root ID is 7
 *             level 3: root ID is 121
 *
 * &sortBy menuindex|pagetitle or some other field. Default is menuindex.
 *
 * &sortOrder ASC|DESC Default is ASC.
 *
 * &showHidden 1|0 show resources hidden from the menu? Default is 0.
 *
 * &id - resource ID to consider as the current page. Default is the current 
 * resource ID.
 *
 * &depth the depth of menus. Default is 2.
 *
 * &showPageNav Show the level 2 and 3 of sub menus for the current 
 * page. Possible values: 'n' to not do that, 'y' for yes, and a numeric 
 * value to say yes, but with a different resource ID for the root of level 2. 
 * Default value: 'y'.
 *
 * &urlScheme abs|full the URL scheme to use for makeUrl(). Default is 'abs'.
 *
 * &classActive the class name for active menu items. Default is 'active'.
 *
 * &accesskeys 1|0 do you want accesskeys? Default is 1.
 *
 * &tplLevelNContainer container chunk for level N of menus.
 *
 * &tplLevelNRow chunk for menu items in level N.
 *
 * &tplLevelNActiveContainer container chunk for level N of active sub menus.
 *
 * &tplLevelNActiveRow chunk for level N of active sub menu items.
 *
 * &phLevels placeholders to use for each level of menus. Default is 
 * "sitenav.level0".
 *   Example: &phLevels=`sitenav.level0,,mylevel2, hislevel3`
 *
 * &alwaysExpandLevels list of level numbers, comma separated, that should 
 * always expand, irrespective of the parent resource being active or not.
 */

if (!function_exists('robod_getChunk')) {
function robod_getChunk(&$modx, &$cfg, &$defaults, $name, &$ph) {
  $chunk = null;
  if (!empty($cfg["tpl$name"])) {
    $chunk = $modx->getObject('modChunk', array('name' => $cfg["tpl$name"]));
  }
  if (!empty($chunk)) {
    $chunk->setCacheable(false);
    return $chunk->process($ph);
  } else {
    $chunk = $modx->newObject('modChunk');
    $chunk->setCacheable(false);
    $chunk->setContent($defaults[$name]);
    return $chunk->process($ph);
  }
}
}

if (!function_exists('robod_getSiteNavigation')) {
function robod_getSiteNavigation(&$modx, &$cfg) {
  if (empty($cfg['sortBy'])) {
    $cfg['sortBy'] = 'menuindex';
  }
  if (empty($cfg['sortOrder'])) {
    $cfg['sortOrder'] = 'ASC';
  }
  if (empty($cfg['id'])) {
    $cfg['id'] = $modx->resource->get('id');
  }

  if (empty($cfg['depth']) || !is_numeric($cfg['depth']) ||
      $cfg['depth'] < 1) {
    $cfg['depth'] = 2;
  }

  if (empty($cfg['startIdLevels'])) {
    $cfg['startIdLevels'] = array(0 => 0);
  } else {
    $cfg['startIdLevels'] = explode(',', $cfg['startIdLevels']);
  }

  if (empty($cfg['showPageNav'])) {
    $cfg['showPageNav'] = 'y';
  }

  if ($cfg['showPageNav'] != 'n') {
    $cfg['depth'] = 4;
    if (is_numeric($cfg['showPageNav'])) {
      $cfg['startIdLevels'][2] = $cfg['showPageNav'];
    }
  }

  if (empty($cfg['urlScheme'])) {
    $cfg['urlScheme'] = 'abs';
  }
  if (empty($cfg['classActive'])) {
    $cfg['classActive'] = 'active';
  }
  if (!isset($cfg['accesskeys'])) {
    $cfg['accesskeys'] = true;
  }

  if (isset($cfg['phLevels'])) {
    $cfg['phLevels'] = explode(',', $cfg['phLevels']);
    if (empty($cfg['phLevels'][0])) {
      $cfg['phLevels'][0] = 'sitenav.level0';
    }
  } else {
    $cfg['phLevels'] = array('sitenav.level0');
  }

  if (!empty($cfg['alwaysExpandLevels'])) {
    $cfg['alwaysExpandLevels'] = explode(',', $cfg['alwaysExpandLevels']);
  } else {
    $cfg['alwaysExpandLevels'] = array();
  }

  // default chunks
  $chunks = array();
  for ($i = 0; $i < $cfg['depth']; $i++) {
    $chunks["Level{$i}Container"] = '<ul class="menus-level' . $i . '">[[+menu.subs]]</ul>';
    $chunks["Level{$i}Row"] = '<li[[+item.classes]]>
<a href="[[+item.link]]"[[+item.accesskey]][[+item.linktitle]]>[[+item.menutitle]]</a>
[[+item.subs]]
</li>';
  }

  $parentIds = $modx->getParentIds($cfg['id']);
  $parentIds[] = $cfg['id'];

  $level_ids = array();
  $level_ids[0] = $modx->getChildIds($cfg['startIdLevels'][0], 1);
  $all_ids = $level_ids[0];

  for($i = 1; $i < $cfg['depth']; $i++) {
    if (isset($cfg['startIdLevels'][$i])) {
      $level_ids[$i] = $modx->getChildIds($cfg['startIdLevels'][$i], 1);
    } else {
      $level_ids[$i] = array();
      $expand_ = in_array($i-1, $cfg['alwaysExpandLevels']);
      for ($y = 0, $n = count($level_ids[$i-1]); $y < $n; $y++) {
        $id_ = $level_ids[$i-1][$y];
        if ($expand_) {
          $level_ids[$i] = array_merge($level_ids[$i],
            $modx->getChildIds($id_, 1));
        } else {
          if (in_array($id_, $parentIds)) {
            $level_ids[$i] = $modx->getChildIds($id_, 1);
            break;
          }
        }
      }
    }

    $all_ids = array_merge($all_ids, $level_ids[$i]);
  }

  unset($id_, $expand_);

  $query = $modx->newQuery('modResource');

  $query->select($modx->getSelectColumns('modResource','modResource','',
    array('id', 'parent', 'pagetitle', 'menutitle', 'longtitle', 'menuindex', 'class_key')));

  $where = array(
    'id:IN' => $all_ids,
    'published' => 1,
    'deleted' => 0
  );

  if (empty($cfg['showHidden'])) {
    $where['hidemenu'] = 0;
  }

  $query->where($where);
  $query->sortby($cfg['sortBy'], $cfg['sortOrder']);
  $collection = $modx->getCollection('modResource', $query);

  unset($query, $where); // cleanup

  // each resource indexed by id, inside its own level
  // level > resourceId:data
  $resources = array();

  // process each resource
  for ($i = 0, $n = count($collection); $i < $n; $i++) {
    $resource = $collection[$i]->toArray();

    // generate link
    if ($resource['class_key'] == 'modWebLink') {
      if (empty($resource['content'])) {
        $resource['content'] = $collection[$i]->get('content');
      }
      if (is_numeric($resource['content'])) {
        $resource['_link'] = $modx->makeUrl(intval($resource['content']), '', '',
          $cfg['urlScheme']);
      } else {
        $resource['_link'] = $resource['content'];
      }
    } else {
      $resource['_link'] = $modx->makeUrl($resource['id'], '', '', $cfg['urlScheme']);
    }

    $resource['_active'] = in_array($resource['id'], $parentIds);

    // determine resource depth/level
    for ($y = 0; $y < $cfg['depth']; $y++) {
      if (in_array($resource['id'], $level_ids[$y])) {
        $resource['_level'] = $y;
        break;
      }
    }

    // done, index the resource by level and id
    if (!isset($resources[$resource['_level']])) {
      $resources[$resource['_level']] = array();
    }

    $resources[$resource['_level']][$resource['id']] = $resource;
  }

  // cleanup
  unset($resource, $resourceObj, $collection, $parentIds, $level_ids, $all_ids);

  // level > parent id > row html
  $results = array();

  // accesskeys for each level
  if ($cfg['accesskeys']) {
    $accesskeys = array(
      0 => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 'pos' => -1),
      1 => array('q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', 'pos' => -1),
    );
  } else {
    $accesskeys = array();
  }

  // generate html code for each resource, going from highest level to lowest.
  for ($l = count($resources)-1; $l >= 0; $l--) {
    $results[$l] = array();

    $r = 0;
    // generate the html for each resource item
    foreach ($resources[$l] as $id => $resource) {
      $parent = $resource['parent'];
      if (isset($resources[$l-1][$parent])) {
        $parent_active = $resources[$l-1][$parent]['_active'];
      } else {
        $parent_active = false;
      }

      $active = $resource['_active'];

      // change chunk name, when parent is active.
      if ($parent_active && !empty($cfg["tplLevel{$l}ActiveRow"])) {
        $chunkName = "Level{$l}ActiveRow";
      } else {
        $chunkName = "Level{$l}Row";
      }

      // placeholders
      $ph = array(
        'item.id' => $id,
        'item.parent' => $parent,
        'item.link' => $resource['_link'],
        'item.classes' => '',
        'item.linktitle' => '',
        'item.accesskey' => '',
      );

      if ($active) {
        $ph['item.classes'] = ' class="' . $cfg['classActive'] . '"';
      }

      // access keys for each level
      if (($parent_active || $l == 0) && isset($accesskeys[$l])) {
        $pos = ++$accesskeys[$l]['pos'];
        if (isset($accesskeys[$l][$pos])) {
          $ph['item.accesskey'] = ' accesskey="' . $accesskeys[$l][$pos] . '"';
        }
      }

      if (!empty($resource['menutitle'])) {
        $ph['item.menutitle'] = $resource['menutitle'];
      } else if (!empty($resource['pagetitle'])) {
        $ph['item.menutitle'] = $resource['pagetitle'];
      }

      if (!empty($resource['longtitle'])) {
        $ph['item.linktitle'] = ' title="' . $resource['longtitle'] . '"';
      } else if (!empty($resource['pagetitle']) &&
                 $ph['item.menutitle'] != $resource['pagetitle']) {
        $ph['item.linktitle'] = ' title="' . $resource['pagetitle'] . '"';
      }

      // add the subs, but for level 0 we want the active submenus (level 1) to 
      // not go inside, we want it out...
      $show_subs = isset($results[$l+1][$id]);

      if ($show_subs && !empty($cfg['phLevels'][$l+1])) {
        $show_subs = !$active;
      }

      if ($show_subs) {
        if ($active && !empty($cfg['tplLevel' . ($l+1) . 'ActiveContainer'])) {
          $containerChunk = 'Level' . ($l+1) . 'ActiveContainer';
        } else {
          $containerChunk = 'Level' . ($l+1) . 'Container';
        }

        $containerPh = array(
          'menu.subs' => $results[$l+1][$id],
        );

        $ph['item.subs'] = robod_getChunk($modx, $cfg, $chunks, $containerChunk, $containerPh);

        unset($containerChunk, $containerPh, $results[$l+1][$id]); // cleanup
      } else {
        $ph['item.subs'] = '';
      }

      // and we are done
      if (!isset($results[$l][$parent])) {
        $results[$l][$parent] = '';
      }

      $results[$l][$parent] .= robod_getChunk($modx, $cfg, $chunks, $chunkName, $ph);
      $r++;
    }
  }
  unset($accesskeys, $resources);

  for ($i = 0; $i < $cfg['depth']; $i++) {
    if (!empty($cfg['phLevels'][$i]) && !empty($results[$i])) {
      $ph = array('menu.subs' => implode("\n", array_values($results[$i])));

      if ($i == 0 || empty($cfg["tplLevel{$i}ActiveContainer"])) {
        $chunkName = "Level{$i}Container";
      } else {
        $chunkName = "Level{$i}ActiveContainer";
      }

      $modx->setPlaceholder($cfg['phLevels'][$i],
        robod_getChunk($modx, $cfg, $chunks, $chunkName, $ph));
    }
  }

  unset($ph, $chunkName, $results, $cfg);
}
}

return robod_getSiteNavigation($modx, $scriptProperties);

// vim:set fo=anqrowcb tw=80 ts=2 sw=2 sts=2 sta et noai nocin ff=unix:

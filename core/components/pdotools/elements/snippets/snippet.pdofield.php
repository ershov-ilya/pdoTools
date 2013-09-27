<?php
/* @var array $scriptProperties */
if (!empty($input)) {$id = $input;}
if (!isset($default)) {$default = '';}
if (!isset($output)) {$output = '';}
$class = 'modResource';

if (empty($field)) {$field = 'pagetitle';}
if (!empty($options)) {
	$options = trim($options);
	if ($options[0] == '{') {
		$tmp = $modx->fromJSON($options);
		if (is_array($tmp)) {
			extract($tmp);
			$scriptProperties = array_merge($scriptProperties, $tmp);
		}
	}
	else {
		$field = $options;
	}
}
if (empty($id)) {$id = $modx->resource->id;}
if (!isset($context)) {$context = '';}

if (!empty($top) || !empty($topLevel)) {
	// Select needed context for parents functionality
	if (empty($context)) {
		$q = $modx->newQuery($class, $id);
		$q->select('context_key');
		if ($q->prepare() && $q->stmt->execute()) {
			$context = $q->stmt->fetch(PDO::FETCH_COLUMN);
		}
	}
	// This algorithm taken from snippet "UltimateParent"
	$top = isset($top) && intval($top) ? $top : 0;
	$topLevel= isset($topLevel) && intval($topLevel) ? intval($topLevel) : 0;
	$pid = $id;
	$pids = $modx->getParentIds($id, 10, array('context' => $context));
	if (!$topLevel || count($pids) >= $topLevel) {
		while ($parentIds= $modx->getParentIds($id, 1, array('context' => $context))) {
			$pid = array_pop($parentIds);
			if ($pid == $top) {
				break;
			}
			$id = $pid;
			$parentIds = $modx->getParentIds($id, 10, array('context' => $context));
			if ($topLevel && count($parentIds) < $topLevel) {
				break;
			}
		}
	}
}

/* @var pdoFetch $pdoFetch */
if (!$modx->loadClass('pdofetch', MODX_CORE_PATH . 'components/pdotools/model/pdotools/', false, true)) {return false;}
$pdoFetch = new pdoFetch($modx, $scriptProperties);
$pdoFetch->addTime('pdoTools loaded');

$where = array($class.'.id' => $id);
// Add custom parameters
foreach (array('where') as $v) {
	if (!empty($scriptProperties[$v])) {
		$tmp = $modx->fromJSON($scriptProperties[$v]);
		if (is_array($tmp)) {
			$$v = array_merge($$v, $tmp);
		}
	}
	unset($scriptProperties[$v]);
}
$pdoFetch->addTime('Conditions prepared');

// Fields to select
$resourceColumns = array_keys($modx->getFieldMeta($class));
$field = strtolower($field);
if (in_array($field, $resourceColumns)) {
	$scriptProperties['select'] = array($class => $field);
	$scriptProperties['includeTVs'] = '';
}
else {
	$scriptProperties['select'] = array($class => 'id');
	$scriptProperties['includeTVs'] = $field;
}
// Additional default field
if (!empty($default)) {
	$default = strtolower($default);
	if (in_array($default, $resourceColumns)) {
		$scriptProperties['select'][$class] .= ','.$default;
	}
	else {
		$scriptProperties['includeTVs'] = empty($scriptProperties['includeTVs'])
			? $default
			: $scriptProperties['includeTVs'] . ',' . $default;
	}
}

if ($row = $pdoFetch->getObject($class, $where, $scriptProperties)) {
	foreach ($row as $k => $v) {
		$k = strtolower($k);
		if ($k == $field && $v != '') {
			$output = $v;
			break;
		}
		elseif ($k == $default && $v != '') {
			$output = $v;
			break;
		}
	}
}

return $output;
<?php
contentBox('orphans', 'container p-3 after-content');
$sheet = getSheet('sitemap', false);
$pieces = [];
foreach ($sheet->rows as $item) {
	$n = urlize($sheet->getValue($item, 'Name'));
	$pieces[$n] = getEnrichedPieceObj($item, $sheet);
}

$delta = __get_delta($sheet, $pieces);

foreach(['matches', 'missing-in-sitemap', 'missing-in-folder'] as $key) {
	$array = $delta[$key];
	echo '<h3>' . humanize($key) . ' (' . count($array) . ')</h3>';
	echo '<textarea '.($key != 'matches' ? 'class="autofit" ' : '') . 'style="width: 100%; min-height: 150px">' . implode("\r\n", $array) . '</textarea><hr />';
}

contentBox('end');

function __get_delta($sheet, $items) {
	$pieces = []; $dupes = []; $missing = []; $matches = [];
	foreach ($sheet->rows as $item) {
		$n = urlize($sheet->getValue($item, 'Name'));
		if (isset($pieces[$n])) $dupes[] = $item;
		$pieces[$n] = $sheet->getValue($item, 'Work') . '	' . $sheet->getValue($item, 'Collection') . '	' . $n;
	}

	$taxonomy = getSheet('taxonomy', 'type');
	$works = $taxonomy->group['work'];
	$allCollections = arrayGroupBy($sheet->rows, $sheet->columns['Collection']);
	$typeByWork = arrayGroupBy($sheet->rows, $sheet->columns['Work']);
	foreach ($works as $workRow) {
		$work = $taxonomy->getValue($workRow, 'slug');
		$collections = __getCollections($work, $allCollections, $sheet->columns['Work']);
		foreach ($collections as $coll) {
			$wkType = $typeByWork[$work][0][$sheet->columns['Type']];
			$files = scandir(SITEPATH . '/' . $wkType . '/' . ($wkType == 'prose' ? $work . '/' : '') . $coll);
			$rb = $wkType . '	' . $work . '	' . $coll . '	';
			foreach ($files as $f) {
				if ($f[0] == '.' || endsWith($f, '.jpg') || $f == '_toc.tsv') continue;
				$f = str_replace('.txt', '', $f);
				if (isset($pieces[$f])) {
					unset($pieces[$f]);
					$matches[] = $rb . $f;
				} else {
					$missing[] = $rb . $f;
				}
			}
		}
	}
	
	return [
		'matches' => $matches,
		'missing-in-sitemap' => $missing,
		'missing-in-folder' => $pieces,
	];
}

function __getCollections($work, $allCollections, $index) {
	$result = [];
	foreach ($allCollections as $key => $items)
		if ($items[0][$index] == $work) $result[] = $key;
	return $result;
}

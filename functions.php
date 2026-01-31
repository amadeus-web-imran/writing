<?php
DEFINE('TAXONOMY', 'site-taxonomy');

variable(TAXONOMY, [
	'works' => 'Work',
	'collections' => 'Collection',
	'categories' => 'Category',
	//'for-person' => 'Dedication',
	//remove?
	'for' => 'Dedication',
	//'people' => 'Dedication',
]);

function renderMetaPage($slug) {
	$name = getQueryParameter(VARQueryName);
	$headings = getQueryParameter(VARQueryHeadings);
	$wantsImg = in_array($slug, ['works', 'collections']);

	echo tagUX::selfClosetag(tagUX::HorizontalRule, cssUX::m2);
	$suffix = $name ? ' &mdash;> ' . humanize($name) : '';
	printSpacer(humanizeThis() . $suffix);

	contentBox($slug, 'container');

	$tax = variable(TAXONOMY);
	if (!isset($tax[$slug])) showDebugging('cms.php', 'TAXONOMY for ' . $slug . ' not defined', true);
	$groupBy = $tax[$slug];

	$sheet = sitemapTsv::read($groupBy);
	variable('sheet', $sheet);

	$op = [];
	$imgStart = '<div class="text-center after-content p-3 rounded-3"><img class="img-fluid img-max-400" src="'; $imgEnd = '" /></div>' . NEWLINE;

	if ($name || $headings) echo getLink(' == ALL == ', pageUrl($slug), 'btn btn-primary my-3');
	if (!$headings) echo getLink(' == ONLY HEADINGS == ', pageUrl($slug) . '?headings=1', 'btn btn-success my-3');

	foreach ($sheet->group as $key => $rows) {
		$text = humanize($key);
		$url = urlize($key);

		if ($name && $name != $url) continue;

		$count = '<span class="float-right">Count: ' . count($rows) . '</span>';
		$title = getLink($count . $text, pageUrl($slug . '/' . $url));
		$onlyMe = getLink('**', pageUrl($slug . '/?name=' . $url), 'btn btn-outline-info');
		$img = $wantsImg && disk_file_exists(SITEPATH . '/assets/cdn/' . 
			($jpg = 'taxonomy/' . $slug . '-' . $url . '.jpg')) ? $imgStart . getHtmlVariable('cdn') . $jpg . $imgEnd : '';
		$res = $img . h2($title . ' &mdash; ' . $onlyMe, '', true);

		if (!$headings) $res .= NEWLINE . implode(NEWLINE, array_map(function($piece) {
			$sheet = variable('sheet');
			return getLink($sheet->getValue($piece, 'SNo') . ' ' . $sheet->getValue($piece, 'Name'),
				$link = pageUrl(urlize($sheet->getValue($piece, 'Name'))), 'btn btn-outline-info m-2 ms-0')
				. ' ' . getLinkWithCustomAttr('**', $link . '?content=1', ' data-lightbox="iframe" class="btn btn-outline-info"')
				. ' ' . $sheet->getDescription($piece) . BRTAG;
		}, $rows));

		$res .= cbCloseAndOpen('container');
		$op[$key] = $res;
	}

	ksort($op);
	echo implode(NEWLINES2, $op);

	contentBox('end');
}

//retain .txt and use .md for the deep dives

function printPiece($item, $where, $xofy = false, $relative = '') {
	contentBox('', 'container');
	if ($relative) echo '<span class="right-button">' . $relative . '</span>';

	$heading = '<!--noop-->' . $item['SNo'] . '. ' . ($name = $item['Name']);
	if ($where != 'before') $heading = getLink($heading, urlFromSlugs($item['Name']));

	if ($xofy) echo '<span style="float: right">' . $xofy . '</span>';
	h2($heading, 'm-0 p-0');

	echo BRNL . '<p class="mb-3 p-3 content-box after-content">' . $item['Description'] . '</p>';

	echo '<div class="large-list with-labels"><ul class="p-0 mb-0"><li>' . NEWLINE . implode('</li>' . NEWLINE . '	<li>', [
		'<label>Date: </label> '       . $item['Date'],
		'<label>Category: </label> '   . getLink(_getTaxonomyText($item['Category'], 'category'), urlFromSlugs('categories', $item['Category'])),
		'<label>Dedication: </label> ' . getLink($item['Dedication'], urlFromSlugs('for', $item['Dedication'])),
		'<label>Collection: </label> ' . getLink(_getTaxonomyText($item['Collection'], 'collection'), urlFromSlugs('collections', $item['Collection'])),
		'<label>Work: </label> ' . getLink(_getTaxonomyText($item['Work'], 'work'), urlFromSlugs('works', $item['Work'])),
		'<label>Rhymes: </label> ' . $item['RhymeScheme'],
	]) . NEWLINE . '</li></div>' . BRNL;

	//TODO: if matching image / meta

	if ($where != 'before')
		contentBox('end');
}

function _getTaxonomyText($val, $type) {
	$sheet = getSheet('taxonomy', 'type');
	$groupKey = 'types_' . $type;
	if (!($group = variable($groupKey))) {
		$group = arrayGroupBy($sheet->group[$type], $sheet->columns['slug'], true);
		variable($groupKey, $group);
	}

	$key = urlize($val);
	if (!isset($group[$key])) return $val;

	$item = $group[$key][0];
	return $sheet->getValue($item, 'text');
}

//sets inner node for more/with-ai/
function site_before_render() {
	if (getQueryParameter(VARQueryContent)) {
		add_body_class(cssUX::pt4);
		setSubTheme(VARSubthemeContentOnly);
	}

	$section = variable(SECTIONVAR);
	$node = variable(NODEVAR);

	if (true || $section == $node) return;

	DEFINE('NODEPATH', SITEPATH . '/' . variable(SECTIONVAR) . '/' . $node);
	variables([
		VARNodeSiteName => humanizeThis(),
		VARNodeSafeName => $node,
		VARSubmenuAtNode => true,
		VARNodesHaveFiles => true,
	]);
}

//================================================

class sitemapTsv extends sheet {
	const expected = 'EXPECTED!!';

	static function read($groupBy, $urlize = false) {
		return new sitemapTsv('sitemap', $groupBy, $urlize);
	}

	function enrichedPiece($item) {
		$result = $this->asObject($item);
		$type = $result['Type'];
		$workFol = $type == 'prose' ? '/' . $this->getWork($item, true) : '';
		$result['File'] = concatArgsWithSlash(
			SITEPATH, $type . $workFol,
			$this->getCollection($item),
			$this->getName($item, true) . '.txt',
		);
		return $result;
	}

	function getWork($item, $urlize = false) {
		return $this->getValue($item, 'Work', self::expected, $urlize);
	}

	function getCollection($item) {
		return $this->getValue($item, 'Collection');
	}

	function getName($item, $urlize = false) {
		return $this->getValue($item, 'Name', self::expected, $urlize);
	}

	function getDescription($item) {
		return $this->getValue($item, 'Description');
	}
}

//1 - piece checking happens here
function beforeSectionSet() {
	$node = variable(VARNode);

	$tax = variable(TAXONOMY);
	$isHelper = startsWith($node, '_');
	$isPseudo = in_array($node, ['all', 'poems', 'prose']);
	$isTax = in_array($node, array_keys($tax));

	$byWork = sitemapTsv::read('Work');

	if (!$isPseudo && !$isTax && !$isHelper) {
		$sheet = sitemapTsv::read('Name', true);

		if (!isset($sheet->group[$node]))
			return false;

		$item = $sheet->group[$node][0];

		$ofSameWork = $byWork->group[$sheet->getWork($item)];
		$indicesByName = [];
		foreach ($ofSameWork as $ix => $row) $indicesByName[$byWork->getName($row)] = $ix;

		$currentIndex = $indicesByName[$sheet->getName($item)];
		$current = $sheet->enrichedPiece($item);
		$previous = $currentIndex > 0 ? $sheet->enrichedPiece($ofSameWork[$currentIndex - 1]) : false;
		$next = $currentIndex < count($ofSameWork) -1 ? $sheet->enrichedPiece($ofSameWork[$currentIndex + 1]) : false;

		variables([
			'file' => $current['File'],
			'hasPiece' => true,
			'currentPiece' => $current,
			'previousPiece' => $previous,
			'nextPiece' => $next,
		]);

		afterSectionSet();
		return true;
	}

	if ($node == 'works')
		$sheet = $byWork;
	else if (array_key_exists($node, $tax))
		$sheet = sitemapTsv::read($tax[$node], true);
	else if ($isPseudo || $isHelper)
		$sheet = sitemapTsv::read(false);

	$on = getPageParameterAt(1);

	if ($isTax) {
		if (!isset($sheet->group[$on]))
			return false;
		if ($on)
			variable('skip-content-render', true);
	}

	$items = $isPseudo || $isHelper ? $sheet->rows : $sheet->group[$on];
	$pieces = [];
	foreach ($items as $item) {
		$obj = $sheet->enrichedPiece($item);
		if ($isHelper) continue;
		if ($isPseudo && $node != 'all' && $node != $obj['Type']) continue;
		$pieces[] = $obj;
	}

	variables([
		'hasPieces' => true,
		'currentPieces' => $pieces,
	]);

	afterSectionSet();
	return true;
}

function getParentSlug($sectionFor) {
	if (getPageParameterAt(1)) return '';
	$piece = in_array($sectionFor, ['poems', 'prose', 'essays']);
	if ($piece) return $sectionFor;
	
	return '';
}

function getParentSlugForMenuItem($sectionFor, $item) {
	$piece = in_array($sectionFor, ['poems', 'prose', 'essays']);
	if ($piece) return 'collections/';

	$alias = in_array($sectionFor, ['works']);
	if ($alias) return 'works';
	
	return '';
};

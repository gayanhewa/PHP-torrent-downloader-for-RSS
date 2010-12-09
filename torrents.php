<?php

define('DEFAULT_CONFIG', dirname(__FILE__) . '/' . basename(__FILE__, '.php') . '.ini');
define('INI_PROCESS_SECTIONS', true);

/**
 * Load configuration from INI file
 *
 * @param string $configFile Path to configuration INI file
 * @return array
 */
function getConfig($configFile) {
	$result = array();

	if (is_file($configFile) && is_readable($configFile)) {
		$result = parse_ini_file($configFile, INI_PROCESS_SECTIONS);
	}

	return $result;
}

/**
 * Get the list of shows to download
 *
 * @param array $config Configuration array
 * @return string
 */
function getShows($config) {
	$result = '';

	if (!empty($config['shows']['show'])) {
		$content = implode('|', $config['shows']['show']);
		$result = '(' . $content . ')';
	}

	return $result;
}

/**
 * Get the list of excludes
 *
 * @param array $config Configuration array
 * @return string
 */
function getExcludes($config) {
	$result = '';

	if (!empty($config['excludes']['exclude'])) {
		$content = implode('|', $config['excludes']['exclude']);
		$result = '(' . $content . ')';
	}

	return $result;
}


function getFeeds($config) {
	$result = array();

	if (!empty($config['feeds']['feed'])) {
		$result = $config['feeds']['feed'];
	}

	return $result;
}

function downloadFeed($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);

	return $result;	
}	

function getFeedItems($url) {
	$result = array();

	libxml_use_internal_errors(true);
	$xml = simplexml_load_string(downloadFeed($url));
	if ($xml) {
		foreach ($xml->channel->item as $item) {
			$link = ($item->link) ? $item->link : $item->guid;
			$result[ (string) $link ] = (string) $item->title;
		}
	}

	return $result;
}

function cleanFeedItems($feedItems, $shows, $exclude) {
	$result = array();

	foreach ($feedItems as $link => $title) {
		// Ignore exludes
		if (preg_match("/$exclude/is", $title)) {
			continue;
		}

		if (preg_match("/$shows\s(.*?)S([0-9]+?)E([0-9]+?)\s/is", $title, $m)){
			$episode = "S".$m[3]."E".$m[4];
			preg_match("/(.*?)$episode(.*?)/is", $title, $cleanTitle);
			$result[] = array(
				'show' => ucfirst(trim($cleanTitle[1])),
				'url' => $link,
				'episode' => $episode,
			);
		}			
	}

	return $result;
}

function downloadTorrent($url, $config){
	$fileName = basename($url);
	$fileExtension = $config['paths']['torrents_extension'];
	$fileFolder = $config['paths']['torrents_folder'];

	if (!preg_match("/$fileExtension$/", $fileName)) {
		$fileName .= $fileExtension;
	}

	if (!file_exists($fileFolder)) {
		mkdir($fileFolder);
	}

	if (file_exists($fileFolder) && is_dir($fileFolder) && is_writable($fileFolder)) {
		$fileName = $fileFolder . $fileName;
	}

	echo "Downloading: ". $fileName . "\n";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
	$fp = fopen($fileName, 'w+');
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_exec($ch);
	curl_close($ch);
	fclose($fp);
}

function getHistory($config) {
	$result = array();

	$historyFile = $config['paths']['history'];
	if (is_file($historyFile) && file_exists($historyFile) && is_readable($historyFile)) {
		$result = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	}

	return $result;
}

function saveHistory($config, $history) {
	$historyFile = $config['paths']['history'];
	return file_put_contents($historyFile, implode("\n", $history));
}


function processFeeds($feeds, $config) {
	$result = array();

	$shows = getShows($config);
	$excludes = getExcludes($config);
	foreach ($feeds as $feed) {
		$feedItems = getFeedItems($feed);
		$cleanItems = cleanFeedItems($feedItems, $shows, $excludes);
		$history = getHistory($config);
		
		foreach($cleanItems as $d){
			$entry = $d['show']." ".$d['episode'];
			if (!in_array($entry,$history)) {
				downloadTorrent($d['url'], $config);
				$history[] = $entry;
				$result[$feed][] = $entry;
			}
		}

		saveHistory($config, $history);
	}

	return $result;
}

$config = getConfig(DEFAULT_CONFIG);

if (empty($config)) {
	die("Empty config. Nothing to do. Work on your " . DEFAULT_CONFIG);
}

$feeds = getFeeds($config);
$result = processFeeds($feeds, $config);
print_r($result);


?>

<?php
	define('FILE_SHOWS', dirname(__FILE__) . '/shows.txt');
	define('FILE_HISTORY', dirname(__FILE__) . '/history.log');

	// Path to download torrrent files to
	define('AUTOTORRENTS_PATH','torrents/');
	// RSS Feed of torrents (based on torrentleech.org)
	//$url = "http://rss.torrentleech.org/KEY";
	$url = "http://www.torrentz.com/feed";
	// Exlude these matches
	$exclude = "(720p|brrip|1080p|dvdrip|hebsub|repack|dvdr)";

	/**
	 * Get the list of shows to download
	 *
	 * @return string
	 */
	function getShows() {
		$result = '';

		if (is_file(FILE_SHOWS) && file_exists(FILE_SHOWS) && is_readable(FILE_SHOWS)) {
			$content = file_get_contents(FILE_SHOWS);
			$content = preg_replace('/^\s*$/', '', $content);  // ignore empty lines
			$content = preg_replace('/^#.*$/', '', $content);  // ignore commented lines (starting with #)
			if (!empty($content)) {
				$content = preg_replace("/[\r\n]/", '|', $content);	
				$content = preg_replace('/\|+/', '|', $content);  // get rid of multiple separators
				$content = preg_replace('/\|$/', '', $content);    // get rid of the trailing separator
				$result = '(' . $content . ')';
			}
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

	function downloadTorrent($url){
		$fileName = basename($url);
		if (!preg_match('/\.torrent/', $fileName)) {
			$fileName .= '.torrent';
		}

		if (!file_exists(AUTOTORRENTS_PATH)) {
			mkdir(AUTOTORRENTS_PATH);
		}

		if (file_exists(AUTOTORRENTS_PATH) && is_dir(AUTOTORRENTS_PATH) && is_writable(AUTOTORRENTS_PATH)) {
			$fileName = AUTOTORRENTS_PATH . $fileName;
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

	function getHistory() {
		$result = array();

		if (is_file(FILE_HISTORY) && file_exists(FILE_HISTORY) && is_readable(FILE_HISTORY)) {
			$result = file(FILE_HISTORY, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}

		return $result;
	}

	function saveHistory($history) {
		return file_put_contents(FILE_HISTORY, implode("\n", $history));
	}

	$shows = getShows();

	$feedItems = getFeedItems($url);
	$cleanItems = cleanFeedItems($feedItems, $shows, $exclude);
	$history = getHistory();
	
	foreach($cleanItems as $d){
		$entry = $d['show']." ".$d['episode'];
		if (!in_array($entry,$history)) {
			downloadTorrent($d['url']);
			$history[] = $entry;
		}
	}

	saveHistory($history);
	
?>

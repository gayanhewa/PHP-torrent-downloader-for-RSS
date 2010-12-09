<?php
	define('FILE_SHOWS', dirname(__FILE__) . '/shows.txt');

	// Path to download torrrent files to
	define('AUTOTORRENTS_PATH',"");
	// RSS Feed of torrents (based on torrentleech.org)
	$url = "http://rss.torrentleech.org/KEY";
	// Exlude these matches
	$exclude = "(720p|brrip|1080p|dvdrip|hebsub|repack|dvdr)";
	// File to keep history of downloaded torrrents
	$historyFile = AUTOTORRENTS_PATH."history.txt";

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

	$shows = getShows();

	$downloadList = array();

	
	function curl($file,$download=0){
		if ($download){
			echo "Downloading: ". AUTOTORRENTS_PATH.basename($file). "\n";
			$ch = curl_init($file);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
			$fp = fopen(AUTOTORRENTS_PATH.basename($file), 'w+');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		} else {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
    		curl_setopt($ch, CURLOPT_URL, $file);
		    curl_setopt($ch, CURLOPT_HEADER, 0);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 500);	
		    $output = curl_exec($ch);
    		curl_close($ch);
    		return $output;	
		}
	}

	
	$xml = simplexml_load_string(curl($url),"SimpleXMLElement",LIBXML_NOCDATA);
//	$xml = simplexml_load_string(file_get_contents('test.xml'),"SimpleXMLElement",LIBXML_NOCDATA);
	

	foreach($xml->channel->item as $i){
		$torrent = $i->link;
		$title = $i->title;
		
		if (!preg_match("/$exclude/is",$title)){ 
			if (preg_match("/$shows\s(.*?)S([0-9]+?)E([0-9]+?)\s/is",$title,$m)){
				$episode = "S".$m[3]."E".$m[4];
				preg_match("/(.*?)$episode(.*?)/is",$title,$cleanTitle);
				$downloadList[] = array(ucfirst(trim($cleanTitle[1])),$torrent,$episode);
			}			
		}
	}
	
	//print_r($downloadList);exit;
	$lines = file($historyFile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach($downloadList as $d){
		$entry = $d[0]." ".$d[2];
		$download = true;
		if (in_array($entry,$lines)) $download = false;
		if ($download) {	
			curl($d[1][0],true);
			$history = fopen ($historyFile, 'a');
			fwrite($history,$entry."\n");
			fclose($history);
		}
	}
	
?>

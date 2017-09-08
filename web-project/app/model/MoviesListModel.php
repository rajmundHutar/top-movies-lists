<?php

namespace App\Model;

use Nette\InvalidArgumentException;
use Nette\UnexpectedValueException;

class MoviesListModel {

	use CacheTrait;

	const IMDB_LIST = "imdbTop250";
	const CSFD_LIST = "csfdTop300";
	const BBC_TOP_21_CENTURY_LIST = "bbcTop21CenturyList";
	const BBC_TOP_COMEDIES_LIST = "bbcTopComediesList";
	const MSBD_LIST = "mustSeeBeforDieList";

	/** @var TheMovieDbApi */
	protected $theMovieDbApi;

	public function __construct(TheMovieDbApi $theMovieDbApi) {
		$this->theMovieDbApi = $theMovieDbApi;
	}

	public function getList($name) {

		$cache = $this->getCache($name);
		$data = $cache->load('completeList');
		if (!$data) {
			switch ($name) {
				case self::IMDB_LIST:
					$data = $this->prepareImdb();
					break;
				case self::CSFD_LIST:
					$data = $this->prepareCsfd();
					break;
				case self::BBC_TOP_21_CENTURY_LIST:
					$data = $this->prepareBbc21CenturyList();
					break;
				case self::BBC_TOP_COMEDIES_LIST:
					$data = $this->prepareBbcComediesList();
					break;
				case self::MSBD_LIST:
					$data = $this->prepareMustSeeBeforeDieList();
					break;
				default:
					throw new InvalidArgumentException("Unknown list " . $name);
			}
			$cache->save('completeList', $data, [
				$cache::EXPIRE => '24 hours',
			]);
		}

		return $data;
	}

	/**
	 * Loads Top 250 list from IMDB and find this movies on TheMovieDb
	 */
	public function prepareImdb() {

		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => "Accept-language: en",
			],
		];

		$context = stream_context_create($opts);

		$content = file_get_contents('http://www.imdb.com/chart/top', false, $context);
		$content = preg_replace('~\n~i', "", $content);
		preg_match_all('~<tr.*?>.*?<td.*?titleColumn.>.*?<a.*?title\/(.*?)\/.*?>(.*?)<\/a>.*?<span.*?secondaryInfo.>\((.*?)\).*?<\/tr~i', $content, $matches);

		if (empty($matches[1])) {
			throw new UnexpectedValueException('Loaded list from IMDB.com is empty');
		}

		$movies = [];
		foreach ($matches[1] as $key => $originalId) {
			$movies[$key] = [
				'originalId' => $originalId,
				'originalOrder' => $key + 1,
				'originalTitle' => $matches[2][$key],
				'originalYear' => $matches[3][$key],
				'origin' => self::IMDB_LIST,
			];
		}

		return $this->decorateMovies($movies);

	}

	public function prepareCsfd() {

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => "https://www.csfd.cz/zebricky/nejlepsi-filmy/?show=complete",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_POSTFIELDS => "{}",
		]);
		$content = curl_exec($curl);
		curl_close($curl);

		$content = preg_replace('~\n~i', "", $content);
		preg_match_all('~<tr>.*?<td.*?film.*?id=\"chart-(.*?)\".*?<a.*?>(.*?)<\/a>.*?<span.*?film-year.>\((.*?)\).*?<\/tr~i', $content, $matches);

		if (empty($matches[1])) {
			throw new UnexpectedValueException('Loaded list from CSFD.cz is empty');
		}

		$movies = [];
		foreach ($matches[1] as $key => $csfdId) {
			$movies[$key] = [
				'originalId' => $csfdId,
				'originalOrder' => $key + 1,
				'originalTitle' => $matches[2][$key],
				'originalYear' => $matches[3][$key],
				'origin' => self::CSFD_LIST,
			];
		}

		return $this->decorateMovies($movies);

	}

	public function prepareBbc21CenturyList() {

		$content = file_get_contents(__DIR__ . "/../data/BbcGreatestFilms.txt");
		$content = explode(PHP_EOL, $content);
		$content = array_reverse($content);

		if (!$content || !is_array($content)) {
			throw new UnexpectedValueException('Loaded list from app/data/BbcGreatestFilms.txt is corrupted');
		}

		$movies = [];
		foreach ($content as $key => $line) {

			preg_match('~^([0-9]+)..([^\(]*) \((.*)\,.([0-9]{4})\)~i', $line, $matches);

			$movies[] = [
				'originalId' => $key,
				'originalOrder' => $matches[1],
				'originalTitle' => $matches[2],
				'originalYear' => $matches[4],
				'origin' => self::BBC_TOP_21_CENTURY_LIST,
			];

		}

		return $this->decorateMovies($movies);

	}

	public function prepareBbcComediesList() {
		$content = file_get_contents(__DIR__ . "/../data/BbcGreatestComedies.txt");
		$content = explode(PHP_EOL, $content);
		$content = array_reverse($content);

		if (!$content || !is_array($content)) {
			throw new UnexpectedValueException('Loaded list from app/data/BbcGreatestComedies.txt is corrupted');
		}

		$movies = [];
		foreach ($content as $key => $line) {

			preg_match('~^([0-9]+)..([^\(]*) \((.*)\,.([0-9]{4})\)~i', $line, $matches);

			$movies[] = [
				'originalId' => $key,
				'originalOrder' => $matches[1],
				'originalTitle' => $matches[2],
				'originalYear' => $matches[4],
				'origin' => self::BBC_TOP_COMEDIES_LIST,
			];

		}
		return $this->decorateMovies($movies);

	}

	/**
	 * @return array
	 */
	public function prepareMustSeeBeforeDieList(): array {

		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => "Accept-language: en",
			],
		];

		$context = stream_context_create($opts);

		$content = file_get_contents('http://1001films.wikia.com/wiki/The_List', false, $context);
		$content = preg_replace('~\n~i', "", $content);

		preg_match_all('~<li><b>(<a.*?>)?([^\(]*?)(\(([^\)]+)\))? ?(\(([0-9]{4})\))?(<\/a>)?<\/b><\/li~i', $content, $matches);

		if (empty($matches[2])) {
			throw new UnexpectedValueException('Loaded list from 1001films.wikia.com is empty');
		}

		$movies = [];
		foreach ($matches[2] as $key => $title) {

			$movies[$key] = [
				'originalId' => $key + 1,
				'originalOrder' => $key + 1,
				'originalTitle' => html_entity_decode(trim($title)),
				'originalOriginalTitle' => trim($matches[4][$key]),
				'originalYear' => trim($matches[6][$key]),
				'origin' => self::MSBD_LIST,
			];
		}

		$movies = array_reverse($movies);
		return $this->decorateMovies($movies);

	}

	/**
	 * @param array $movies
	 * @return array
	 */
	protected function decorateMovies(array $movies): array {

		// Walk through and check on theMovieDb
		foreach ($movies as $id => $movie) {
			$tMDMovie = null;
			if (isset($movie['origin']) && $movie['origin'] == self::IMDB_LIST) {
				$tMDMovie = $this->theMovieDbApi->findOneMovie($movie['originalId']);
			}
			if (!$tMDMovie) {
				$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['originalTitle'], $movie['originalYear']);
			}
			if (!$tMDMovie && !empty($movie['originalOriginalTitle'])) {
				$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['originalOriginalTitle'], $movie['originalYear']);
			}

			if (!$tMDMovie) {
				dump('Can\'t load:', $movie);
			}

			$movies[$id] = $tMDMovie + $movie;
		}

		return $movies;

	}

}

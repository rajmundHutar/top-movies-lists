<?php

namespace App\Model;

use Nette\UnexpectedValueException;

class MoviesListModel {

	use CacheTrait;

	/** @var TheMovieDbApi */
	protected $theMovieDbApi;

	public function __construct(TheMovieDbApi $theMovieDbApi) {
		$this->theMovieDbApi = $theMovieDbApi;
	}

	/**
	 * Loads Top 250 list from IMDB and find this movies on TheMovieDb
	 */
	public function prepareImdb() {

		$key = "list";
		$cache = $this->getCache('imdbTop');
		if (($movies = $cache->load($key)) === null) {

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
			foreach ($matches[1] as $key => $imdbId) {
				$movies[$key] = [
					'imdbId' => $imdbId,
					'imdbTitle' => $matches[2][$key],
					'imdbYear' => $matches[3][$key],
				];
			}

			$cache->save($key, $movies, [
				$cache::EXPIRE => '24 hours',
			]);

		}

		// Walk through and check on theMovieDb
		foreach ($movies as $id => $movie) {
			$tMDMovie = $this->theMovieDbApi->findOneMovie($movie['imdbId']);
			if (!$tMDMovie) {
				dump('Can\'t load:', $movie);
			}
			$movies[$id] = $tMDMovie + $movie;
		}

		// Save into DB or JSON or Cache
		$cache->save('completeList', $movies, [
			$cache::EXPIRE => '24 hours',
		]);

		return $movies;

	}

	public function getImdbList() {
		$cache = $this->getCache('imdbTop');
		$data = $cache->load('completeList');
		if (!$data) {
			$data = $this->prepareImdb();
		}

		return $data;
	}

	public function prepareCsfd() {

		$key = "list";
		$cache = $this->getCache('csfdTop');
		if (($movies = $cache->load($key)) === null) {

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
					'csfdId' => $csfdId,
					'csfdTitle' => $matches[2][$key],
					'csfdYear' => $matches[3][$key],
				];
			}

			$cache->save($key, $movies, [
				$cache::EXPIRE => '24 hours',
			]);

		}

		// Walk through and check on theMovieDb
		foreach ($movies as $id => $movie) {
			$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['csfdTitle'], $movie['csfdYear']);
			if (!$tMDMovie) {
				dump('Can\'t load:', $movie);
			}
			$movies[$id] = $tMDMovie + $movie;
		}

		// Save into DB or JSON or Cache
		$cache->save('completeList', $movies, [
			$cache::EXPIRE => '24 hours',
		]);

		return $movies;

	}

	public function getCsfdList() {

		$cache = $this->getCache('csfdTop');
		$data = $cache->load('completeList');
		if (!$data) {
			$data = $this->prepareCsfd();
		}

		return $data;

	}

	public function prepareBbc21CenturyList() {

		$content = file_get_contents(__DIR__ . "/../data/BbcGreatestFilms.txt");
		$content = explode(PHP_EOL, $content);
		$content = array_reverse($content);

		if (!$content || !is_array($content)) {
			throw new UnexpectedValueException('Loaded list from app/data/BbcGreatestFilms.txt is corrupted');
		}

		$movies = [];
		foreach ($content as $line) {

			preg_match('~^([0-9]+)..([^\(]*) \((.*)\,.([0-9]{4})\)~i', $line, $matches);

			$movies[] = [
				'bbc21CenturyOrder' => $matches[1],
				'bbc21CenturyTitle' => $matches[2],
				'bbc21CenturyYear' => $matches[4],
			];

		}

		// Walk through and check on theMovieDb
		foreach ($movies as $id => $movie) {
			$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['bbc21CenturyTitle'], $movie['bbc21CenturyYear']);
			if (!$tMDMovie) {
				dump('Can\'t load:', $movie);
			}
			$movies[$id] = $tMDMovie + $movie;
		}

		$cache = $this->getCache('bbcList');
		// Save into DB or JSON or Cache
		$cache->save('completeList', $movies, [
			$cache::EXPIRE => '100 days',
		]);

		return $movies;

	}

	public function getBbc21CenturyList() {

		$cache = $this->getCache('bbcList');
		$data = $cache->load('completeList');
		if (!$data) {
			$data = $this->prepareBbc21CenturyList();
		}

		return $data;

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
				'msbdId' => $key + 1,
				'msbdTitle' => html_entity_decode(trim($title)),
				'msbdOriginalTitle' => trim($matches[4][$key]),
				'msbdYear' => trim($matches[6][$key]),
			];
		}

		$movies = array_reverse($movies);

		// Walk through and check on theMovieDb
		foreach ($movies as $id => $movie) {
			$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['msbdTitle'], $movie['msbdYear']);
			if (!$tMDMovie && $movie['msbdOriginalTitle']) {
				$tMDMovie = $this->theMovieDbApi->searchMovieByNameAndYear($movie['msbdOriginalTitle'], $movie['msbdYear']);
			}

			if (!$tMDMovie) {
				dump('Can\'t load:', $movie);
			}

			$movies[$id] = $tMDMovie + $movie;
		}

		return $movies;

	}

	/**
	 * @return array
	 */
	public function getMustSeeBeforeDieList(): array {

		$cache = $this->getCache('msbdList');

		$data = $cache->load('completeList');
		if (!$data) {

			$data = $this->prepareMustSeeBeforeDieList();
			// Save into DB or JSON or Cache
			$cache->save('completeList', $data, [
				$cache::EXPIRE => '10 days',
			]);

		}

		return $data;

	}

}
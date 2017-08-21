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

}
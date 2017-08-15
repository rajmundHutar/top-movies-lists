<?php

namespace App\Model;

use Nette\Utils\DateTime;

class TheMovieDbApi {

	use CacheTrait;

	const BASE_URL = "https://api.themoviedb.org/3/";
	/** @var string */
	protected $apiKey;

	public function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	public function find($externalId) {

		$key = "find-{$externalId}";
		$cache = $this->getCache('theMovieDb');

		if (($data = $cache->load($key)) === null) {

			$queryData = [
				"api_key" => $this->apiKey,
				"language" => "en-US",
				"external_source" => "imdb_id",
			];

			$url = self::BASE_URL . "find/{$externalId}?" . http_build_query($queryData);

			$content = file_get_contents($url);
			$data = json_decode($content, true);
			$cache->save($key, $data, [
				$cache::EXPIRE => '1 hours',
			]);

		}

		return $data;

	}

	public function findOneMovie($externalId) {

		$res = $this->find($externalId);
		return array_pop($res['movie_results']);

	}

	public function searchMovie($query) {

		$key = "search-{$query}";
		$cache = $this->getCache('theMovieDb');

		if (($data = $cache->load($key)) === null) {

			$queryData = [
				"query" => $query,
				"api_key" => $this->apiKey,
			];

			$url = self::BASE_URL . "search/movie?" . http_build_query($queryData);

			$content = file_get_contents($url);
			$data = json_decode($content, true);
			$cache->save($key, $data, [
				$cache::EXPIRE => '1 hours',
			]);
		}

		return $data;

	}

	public function searchMovieByNameAndYear($movie, $year) {

		$results = $this->searchMovie($movie);
		$possibleCandidateByYear = null;
		foreach ($results['results'] as $result) {
			$movieYear = (new DateTime($result['release_date']))->format('Y');
			// If matches title or original title and year return immediately
			if (($result['title'] == $movie || $result['original_title'] == $movie) && $movieYear == $year){
				return $result;
			}
			// If matches based on year save for later
			if ($movieYear == $year) {
				$possibleCandidateByYear = $result;
			}
		}

		if ($possibleCandidateByYear) {
			return $possibleCandidateByYear;
		}

		// If nothing match return first
		if ($results['results']){
			return array_shift($results['results']);
		}

		return [];

	}

}
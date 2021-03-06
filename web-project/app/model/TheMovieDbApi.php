<?php

namespace App\Model;

use Nette\UnexpectedValueException;
use Nette\Utils\DateTime;

class TheMovieDbApi {

	use CacheTrait;

	const BASE_URL = "https://api.themoviedb.org/3/";
	const KNOWN_MOVIES = [
		'Nae meorisogui jiugae' => '15859',
		'Kristián' => '50795',
		'Yi Yi: A One and a Two' => '25538',
		'Great Epectations' => '14320',
		'Whiskey Galore!' => '16665',
		'The Fireman\'s Ball' => '38442',
		'Pink Flamingoes' => '692',
		'Sullivan’s Travels' => '16305',
		'Ruróni Kenšin: Meidži kenkaku rómantan – Cuioku hen' => '127533',

	];
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

		if (isset(self::KNOWN_MOVIES[$query])) {
			return [
				'results' => [$this->movie(self::KNOWN_MOVIES[$query])],
			];
		}

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
				$cache::EXPIRE => '10 days',
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
			if (($result['title'] == $movie || $result['original_title'] == $movie) && $movieYear == $year) {
				return $result;
			}
			// If matches based on year save for later
			if ($movieYear == $year && !$possibleCandidateByYear) {
				$possibleCandidateByYear = $result;
			}
		}

		if ($possibleCandidateByYear) {
			return $possibleCandidateByYear;
		}

		// If nothing match return first
		if ($results['results']) {
			return array_shift($results['results']);
		}

		return [];

	}

	public function movie($movieId) {

		$key = "movie-{$movieId}";
		$cache = $this->getCache('theMovieDb');

		if (($data = $cache->load($key)) === null) {

			$queryData = [
				"api_key" => $this->apiKey,
			];
			$url = self::BASE_URL . "movie/{$movieId}?" . http_build_query($queryData);

			$content = file_get_contents($url);
			$data = json_decode($content, true);
			$cache->save($key, $data, [
				$cache::EXPIRE => '10 days',
			]);
		}

		return $data;

	}

	public function createRequestToken() {

		$queryData = [
			"api_key" => $this->apiKey,
		];
		$url = self::BASE_URL . "authentication/token/new?" . http_build_query($queryData);
		$content = file_get_contents($url);
		$data = json_decode($content, true);

		if (isset($data['request_token'])) {
			return $data['request_token'];
		}

		throw new UnexpectedValueException('Can\'t create request token');

	}

	public function createSessionId($requestToken) {

		$queryData = [
			"api_key" => $this->apiKey,
			"request_token" => $requestToken,
		];

		$url = self::BASE_URL . "authentication/session/new?" . http_build_query($queryData);
		$content = file_get_contents($url);
		$data = json_decode($content, true);
		return $data['session_id'];

	}

	public function getAccountRatedMovies($sessionId) {

		$key = "account-rated-movies-{$sessionId}";
		$cache = $this->getCache('theMovieDb');

		if (($results = $cache->load($key)) === null) {

			$page = 1;
			$results = [];

			do {

				$queryData = [
					"api_key" => $this->apiKey,
					"session_id" => $sessionId,
					"page" => $page,
				];

				$url = self::BASE_URL . "account/account/rated/movies?" . http_build_query($queryData);
				$content = file_get_contents($url);
				$data = json_decode($content, true);

				foreach ($data['results'] as $value) {
					$results[$value['id']] = $value;
				}

				$totalPages = $data['total_pages'];
				$page++;
			} while ($page <= $totalPages);

			$cache->save($key, $results, [
				$cache::EXPIRE => '24 hours',
			]);
		}

		return $results;

	}

}
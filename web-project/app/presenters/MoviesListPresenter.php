<?php

namespace App\Presenters;

use App\Model\MoviesListModel;
use App\Model\TheMovieDbApi;
use App\Model\UserModel;
use Nette\Application\UI\Presenter;
use Nette\InvalidArgumentException;

class MoviesListPresenter extends Presenter {

	/** @var MoviesListModel */
	protected $moviesListModel;

	/** @var TheMovieDbApi */
	protected $theMovieDbApi;

	/** @var UserModel */
	protected $userModel;

	public function __construct(MoviesListModel $moviesListModel, TheMovieDbApi $theMovieDbApi, UserModel $userModel) {
		$this->moviesListModel = $moviesListModel;
		$this->theMovieDbApi = $theMovieDbApi;
		$this->userModel = $userModel;
	}

	public function renderUserList($userId, $listName) {

		$userData = $this->userModel->getUserData($userId);
		$seenMovies = $this->theMovieDbApi->getAccountRatedMovies($userData['sessionId']);
		$seenMoviesIds = $this->getIdsFromList($seenMovies);

		$list = $this->moviesListModel->getList($listName);

		$listIds = $this->getIdsFromList($list);
		$listSeenIds = array_intersect($listIds, $seenMoviesIds);;
		$listSeenIds = array_combine($listSeenIds, $listSeenIds);

		$this->template->list = $list;
		$this->template->seenMovies = $seenMovies;
		$this->template->listSeenIds = $listSeenIds;

	}

	public function renderPrepare($id) {

		$list = $this->moviesListModel->getList($id);

		$this->template->list = $list;

	}

	protected function getIdsFromList($list) {
		return array_map(function ($item) {
			if (!isset($item['id'])){
				return null;
			}
			return $item['id'];
		}, $list);
	}

}
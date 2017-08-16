<?php

namespace App\Presenters;

use App\Model\MoviesListModel;
use App\Model\TheMovieDbApi;
use Nette\Application\UI\Presenter;

class MoviesListPresenter extends Presenter {

	/** @var MoviesListModel  */
	protected $moviesListModel;

	/** @var TheMovieDbApi  */
	protected $theMovieDbApi;

	public function __construct(MoviesListModel $moviesListModel, TheMovieDbApi $theMovieDbApi) {
		$this->moviesListModel = $moviesListModel;
		$this->theMovieDbApi= $theMovieDbApi;
	}

	public function renderPrepare($id) {

		switch ($id){
			case "imdb":
				$list = $this->moviesListModel->getImdbList();
				break;
			case "csfd":
				$list = $this->moviesListModel->getCsfdList();
				break;
		}

		$this->template->list = $list;



	}

}
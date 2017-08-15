<?php

namespace App\Presenters;

use App\Model\MoviesListModel;
use Nette\Application\UI\Presenter;

class MoviesListPresenter extends Presenter {

	/** @var MoviesListModel  */
	protected $moviesListModel;

	public function __construct(MoviesListModel $moviesListModel) {
		$this->moviesListModel = $moviesListModel;
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
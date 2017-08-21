<?php

namespace App\Presenters;

use App\Model\CacheTrait;
use App\Model\MoviesListModel;
use App\Model\TheMovieDbApi;
use App\Model\UserModel;
use Nette\Application\UI\Presenter;
use Nette\InvalidStateException;

class TheMovieDbPresenter extends Presenter {

	use CacheTrait;

	/** @var TheMovieDbApi */
	protected $theMovieDbApi;

	/** @var UserModel */
	protected $userModel;

	/** @var MoviesListModel */
	protected $moviesListModel;

	public function __construct(TheMovieDbApi $theMovieDbApi, UserModel $userModel, MoviesListModel $moviesListModel) {
		$this->theMovieDbApi = $theMovieDbApi;
		$this->userModel = $userModel;
		$this->moviesListModel = $moviesListModel;
	}

	public function actionLogin() {

		$userData = $this->userModel->getUserData(1);

		$token = $this->theMovieDbApi->createRequestToken();
		$link = $this->link("//approved");

		$userData['requestToken'] = $token;
		$this->userModel->saveUserData(1, $userData);

		$httpResponse = $this->getHttpResponse();
		$httpResponse->redirect("https://www.themoviedb.org/authenticate/{$token}?redirect_to={$link}");
		exit;

	}

	public function actionApproved() {

		$userData = $this->userModel->getUserData(1);

		if (!isset($userData['requestToken'])) {
			throw new InvalidStateException('Missing request token for creating session id');
		}

		// User approved request, now we can get sessionId
		$sessionId = $this->theMovieDbApi->createSessionId($userData['requestToken']);
		$userData['sessionId'] = $sessionId;
		$this->userModel->saveUserData(1, $userData);

		$this->redirect('Homepage:default');

	}

}
<?php

namespace App\Presenters;

use App\Model\UserModel;
use Nette;

class HomepagePresenter extends Nette\Application\UI\Presenter {

	/** @var  UserModel */
	protected $userModel;

	public function __construct(UserModel $userModel) {
		$this->userModel = $userModel;
	}

	public function renderDefault() {

		$this->template->userData = $this->userModel->getUserData(1);

	}

}

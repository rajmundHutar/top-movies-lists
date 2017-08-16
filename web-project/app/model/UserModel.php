<?php

namespace App\Model;

class UserModel {

	use CacheTrait;

	public function getUserData($userId) {

		$cache = $this->getCache('users');
		$usersData = $cache->load('usersData');
		if (!$usersData) {
			$usersData = [];
		}

		if (!isset($usersData[$userId])) {
			$usersData[$userId] = [
				'id' => $userId,
			];
			$cache->save('usersData', $usersData);
		}

		return $usersData[$userId];

	}

	public function saveUserData($userId, $data) {

		$cache = $this->getCache('users');
		$usersData = $cache->load('usersData');
		if (!$usersData) {
			$usersData = [];
		}

		$usersData[$userId] = $data;
		$cache->save('usersData', $usersData);

	}

}
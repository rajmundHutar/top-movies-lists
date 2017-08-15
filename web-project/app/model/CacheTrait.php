<?php

namespace App\Model;

use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nette\Utils\Strings;

trait CacheTrait {

	protected $caches = [];

	/**
	 * @param $namespace
	 * @return Cache
	 */
	public function getCache($namespace) {

		if (!isset($this->caches[$namespace])) {

			$namespace = Strings::webalize($namespace);
			$cacheDir = dirname(__DIR__) . '/../temp/cache/';

			$storage = new FileStorage($cacheDir);
			$this->caches[$namespace] = new Cache($storage, $namespace);

		}

		return $this->caches[$namespace];

	}

}
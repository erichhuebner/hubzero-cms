<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Hubzero\Cache\Storage;

use Hubzero\Error\Exception\RuntimeException;

/**
 * File storage for Cache manager
 */
class File extends None
{
	/**
	 * The file cache directory
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * Create a new file cache store instance.
	 *
	 * @param   array  $options
	 * @return  void
	 */
	public function __construct(array $options = array())
	{
		parent::__construct($options);

		if (!isset($this->options['chmod']))
		{
			$this->options['chmod'] = null;
		}

		$this->directory = $this->cleanPath($this->options['cachebase']);

		if (!is_dir($this->directory) || !is_readable($this->directory) || !is_writable($this->directory))
		{
			throw new RuntimeException('Cache path should be directory with available read/write access.');
		}
	}

	/**
	 * Test to see if the cache storage is available.
	 *
	 * @return  boolean  True on success, false otherwise.
	 */
	public static function isAvailable()
	{
		$conf = new \Hubzero\Config\Repository('site');
		return is_writable($conf->get('cache_path', JPATH_CACHE));
	}

	/**
	 * Add an item to the cache only if it doesn't already exist
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   int     $minutes
	 * @return  void
	 */
	public function add($key, $value, $minutes)
	{
		if ($this->has($key))
		{
			return false;
		}

		return $this->put($key, $value, $minutes);
	}

	/**
	 * Store an item in the cache for a given number of minutes.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   int     $minutes
	 * @return  void
	 */
	public function put($key, $value, $minutes)
	{
		$file = $this->path($key);

		$data = array(
			'time' => time(),
			'data' => $value,
			'ttl'  => $this->expiration($minutes)
		);

		return $this->writeCacheFile($file, $data);
	}

	/**
	 * Store an item in the cache indefinitely.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @return  void
	 */
	public function forever($key, $value)
	{
		return $this->put($key, $value, 0);
	}

	/**
	 * Retrieve an item from the cache by key.
	 *
	 * @param   string  $key
	 * @return  mixed
	 */
	public function get($key)
	{
		$file = $this->path($key);

		if (!file_exists($file))
		{
			return null;
		}

		$data = @unserialize(file_get_contents($file));

		if (!$data)
		{
			throw new RuntimeException('Cache file is invalid.');
		}

		if ($this->isDataExpired($data))
		{
			$this->forget($key);
			return null;
		}

		return $data['value'];
	}

	/**
	 * Check if an item exists in the cache
	 *
	 * @param   string  $key
	 * @return  bool
	 */
	public function has($key)
	{
		$file = $this->path($key);

		if (!file_exists($file))
		{
			return false;
		}

		$data = @unserialize(file_get_contents($file));

		if (!$data)
		{
			throw new RuntimeException('Cache file is invalid.');
		}

		if ($this->isDataExpired($data))
		{
			$this->forget($key);
			return false;
		}

		return true;
	}

	/**
	 * Remove an item from the cache.
	 *
	 * @param   string  $key
	 * @return  bool
	 */
	public function forget($key)
	{
		$file = $this->path($key);

		if (file_exists($file))
		{
			return unlink($file);
		}

		return false;
	}

	/**
	 * Remove all items from the cache.
	 *
	 * @param   string  $group
	 * @return  void
	 */
	public function clean($group = null)
	{
		$path = $this->directory . ($group ? DS . $group : '');

		if (is_dir($path))
		{
			$skip = array('.svn', 'cvs', '.ds_store', '__macosx', 'index.html');

			foreach (new DirectoryIterator($path) as $file)
			{
				if (!$file->isDot() && !in_array(strtolower($file->getFilename()), $skip))
				{
					unlink($file->getPathname());
				}
			}
		}
	}

	/**
	 * Get the expiration time based on the given minutes.
	 *
	 * @param   integer  $minutes
	 * @return  integer
	 */
	protected function expiration($minutes)
	{
		if ($minutes === 0) return 9999999999;

		return time() + ($minutes * 60);
	}

	/**
	 * Get the working directory of the cache.
	 *
	 * @return  string
	 */
	public function getDirectory()
	{
		return $this->directory;
	}

	/**
	 * Get the full path for the given cache key.
	 *
	 * @param   string  $key
	 * @return  string
	 */
	protected function path($key)
	{
		$parts = explode('.', $key);
		$name = array_pop($parts);

		$path = implode(DS, $parts);
		$path = $this->directory . ($path ? DS . $this->cleanPath($path) : '');

		return $path . DS . $this->id($name) . '.php';
	}

	/**
	 * Strip additional / or \ in a path name
	 *
	 * @param   string  $path  The path to clean
	 * @param   string  $ds    Directory separator (optional)
	 * @return  string  The cleaned path
	 */
	protected function cleanPath($path, $ds = DIRECTORY_SEPARATOR)
	{
		$path = trim($path);

		// Remove double slashes and backslahses and convert
		// all slashes and backslashes to DIRECTORY_SEPARATOR
		return preg_replace('#[/\\\\]+#', $ds, $path);
	}

	/**
	 * Get the full path for the given cache key.
	 *
	 * @param   string  $key
	 * @return  string
	 */
	protected function writeCacheFile($filename, $data)
	{
		$dir = pathinfo($filename, PATHINFO_DIRNAME);

		if (!file_exists($dir))
		{
			$mod = $this->options['chmod'] ? $this->options['chmod'] : 0777;
			mkdir($dir, $mod);
		}

		$isNew  = !file_exists($filename);
		$result = file_put_contents($filename, serialize($data), LOCK_EX) !== false;

		if ($isNew && $result !== false && $this->options['chmod'])
		{
			chmod($filename, $this->options['chmod']);
		}

		return $result;
	}

	/**
	 * Check if the given data is expired
	 *
	 * @param   array    $data
	 * @return  boolean
	 */
	protected function isDataExpired(array $data)
	{
		return $data['ttl'] !== 0 && time() - $data['time'] > $data['ttl'];
	}
}

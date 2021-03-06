<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Citations\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Citations\Models\Format as CitationFormat;
use Request;
use Notify;
use Lang;
use App;

/**
 * Controller class for citation format
 */
class Format extends AdminController
{
	/**
	 * List formats
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// get the first item, will use as default if not set.
		$firstResult = CitationFormat::all()
			->where('style', 'NOT LIKE', 'custom-group-%')
			->limit(1)
			->row();

		// see if the component config has a value.
		if ($this->config->get('default_citation_format') != null)
		{
			$currentFormat = CitationFormat::all()
				->where('style', 'LIKE', strtolower($this->config->get('default_citation_format')))
				->limit(1)
				->row();
		}
		else
		{
			$currentFormat = $firstResult;
		}

		// Get formatter object
		$formats = CitationFormat::all();

		// Output the HTML
		$this->view
			->set('currentFormat', $currentFormat)
			->set('formats', $formats)
			->display();
	}

	/**
	 * Save a format
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		//get format
		$format = Request::getArray('citationFormat', array());

		// create or update custom format
		$model = CitationFormat::oneOrNew($format['id']);

		if ($model->style == 'Hub Custom' || $model->isNew() === true)
		{
			$model->set(array(
				'style'  => 'Hub Custom',
				'format' => \Hubzero\Utility\Sanitize::clean($format['format'])
			));
		}
		else
		{
			$model->set(array(
				'format' => \Hubzero\Utility\Sanitize::clean($format['format'])
			));
		}

		if (!$model->save())
		{
			// redirect with error message
			Notify::error($model->getError());
		}
		else
		{
			Notify::success(Lang::txt('CITATION_FORMAT_SAVED') . ' ' . $model->style);
		}

		// successfully set the default value, redirect
		$this->cancelTask();
	}
}

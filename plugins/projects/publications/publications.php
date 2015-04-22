<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
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
 * @author    Alissa Nedossekina <alisa@purdue.edu>
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

include_once(PATH_CORE . DS . 'components' . DS . 'com_publications'
	. DS . 'models' . DS . 'publication.php');

/**
 * Project publications
 */
class plgProjectsPublications extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Store redirect URL
	 *
	 * @var	   string
	 */
	protected $_referer = NULL;

	/**
	 * Store output message
	 *
	 * @var	   array
	 */
	protected $_message = NULL;

	/**
	 * Component name
	 *
	 * @var  string
	 */
	protected $_option = 'com_projects';

	/**
	 * Store internal message
	 *
	 * @var	   array
	 */
	protected $_msg = NULL;

	/**
	 * Event call to determine if this plugin should return data
	 *
	 * @return     array   Plugin name and title
	 */
	public function &onProjectAreas($alias = NULL)
	{
		$area = array();

		// Check if plugin is restricted to certain projects
		$projects = $this->params->get('restricted') ? \Components\Projects\Helpers\Html::getParamArray($this->params->get('restricted')) : array();

		if (!empty($projects) && $alias)
		{
			if (!in_array($alias, $projects))
			{
				return $area;
			}
		}

		$area = array(
			'name'    => 'publications',
			'title'   => Lang::txt('COM_PROJECTS_TAB_PUBLICATIONS'),
			'submenu' => NULL,
			'show'    => true
		);

		return $area;
	}

	/**
	 * Event call to return count of items
	 *
	 * @param      object  $model 		Project
	 * @return     array   integer
	 */
	public function &onProjectCount( $model )
	{
		// Get this area details
		$this->_area = $this->onProjectAreas();

		if (empty($this->_area) || !$model->exists())
		{
			return $counts['publications'] = 0;
		}
		else
		{
			$database = JFactory::getDBO();

			// Instantiate project publication
			$objP = new \Components\Publications\Tables\Publication( $database );

			$filters = array();
			$filters['project']  		= $model->get('id');
			$filters['ignore_access']   = 1;
			$filters['dev']   	 		= 1;

			$counts['publications'] = $objP->getCount($filters);
			return $counts;
		}
	}

	/**
	 * Event call to return data for a specific project
	 *
	 * @param      object  $model           Project model
	 * @param      string  $action			Plugin task
	 * @param      string  $areas  			Plugins to return data
	 * @return     array   Return array of html
	 */
	public function onProject ( $model, $action = '', $areas = null )
	{
		$returnhtml = true;

		$arr = array(
			'html'      =>'',
			'metadata'  =>'',
			'message'   =>'',
			'error'     =>''
		);

		// Get this area details
		$this->_area = $this->onProjectAreas();

		// Check if our area is in the array of areas we want to return results for
		if (is_array( $areas ))
		{
			if (empty($this->_area) || !in_array($this->_area['name'], $areas))
			{
				return;
			}
		}

		// Check authorization
		if ($model->exists() && !$model->access('member'))
		{
			return $arr;
		}

		// Model
		$this->model = $model;

		// Incoming
		$this->_task = Request::getVar('action', '');
		$this->_pid  = Request::getInt('pid', 0);
		if (!$this->_task)
		{
			$this->_task = $this->_pid ? 'publication' : $action;
		}

		$this->_uid       = User::get('id');
		$this->_database  = JFactory::getDBO();
		$this->_config    = $this->model->config();
		$this->_pubconfig = Component::params( 'com_publications' );

		// Common extensions (for gallery)
		$this->_image_ext = \Components\Projects\Helpers\Html::getParamArray(
			$this->params->get('image_types', 'bmp, jpeg, jpg, png' ));
		$this->_video_ext = \Components\Projects\Helpers\Html::getParamArray(
			$this->params->get('video_types', 'avi, mpeg, mov, wmv' ));

		// Hubzero library classes
		$this->fileSystem = new \Hubzero\Filesystem\Filesystem();

		// Temp
		$this->_project   = $model->project();

		// Check if exists or new
		if (!$this->model->exists())
		{
			// Contribute process outside of projects
			$this->model->set('provisioned', 1);

			$ajax_tasks  = array('showoptions', 'save', 'showitem');
			$this->_task = $action == 'start' ? 'start' : 'contribute';
			if ($action == 'publication')
			{
				$this->_task = 'publication';
			}
			elseif (in_array($action, $ajax_tasks))
			{
				$this->_task = $action;
			}
		}
		elseif ($this->model->isProvisioned())
		{
			// No browsing within provisioned project
			$this->_task = $action == 'browse' ? 'contribute' : $action;
		}

		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'publications');
		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'publications','css/curation');
		\Hubzero\Document\Assets::addPluginScript('projects', 'publications', 'js/curation');

		// Actions
		switch ($this->_task)
		{
			case 'browse':
			default:
				$arr['html'] = $this->browse();
				break;

			case 'start':
			case 'new':
				$arr['html'] = $this->startDraft();
				break;

			case 'edit':
			case 'publication':
			case 'continue':
			case 'review':
				$arr['html'] = $this->editDraft();
				break;

			case 'newversion':
			case 'savenew':
				$arr['html'] = $this->newVersion();
				break;

			case 'checkstatus':
				$arr['html'] = $this->checkStatus();
				break;

			case 'select':
				$arr['html'] = $this->select();
				break;

			case 'saveparam':
				$arr['html'] = $this->saveParam();
				break;

			// Change publication state
			case 'publish':
			case 'republish':
			case 'archive':
			case 'revert':
			case 'post':
				$arr['html'] = $this->publishDraft();
				break;

			case 'apply':
			case 'save':
			case 'rewind':
			case 'reorder':
			case 'deleteitem':
			case 'additem':
			case 'dispute':
			case 'skip':
			case 'undispute':
			case 'saveitem':
				$arr['html'] = $this->saveDraft();
				break;

			// Individual items editing
			case 'edititem':
			case 'editauthor':
				$arr['html'] = $this->editItem();
				break;

			case 'suggest_license':
			case 'save_license':
				$arr['html'] = $this->_suggestLicense();
				break;

			// Show all publication versions
			case 'versions':
				$arr['html'] = $this->versions();
				break;

			// Unpublish/delete
			case 'cancel':
				$arr['html'] = $this->cancelDraft();
				break;

			// Contribute process outside of projects
			case 'contribute':
				$arr['html'] = $this->contribute();
				break;

			// Show stats
			case 'stats':
				$arr['html'] = $this->_stats();
				break;

			case 'diskspace':
				$arr['html'] = $this->pubDiskSpace($this->model);
				break;

			// Handlers
			case 'handler':
				$arr['html'] = $this->handler();
				break;
		}

		$arr['referer'] = $this->_referer;
		$arr['msg']     = $this->_message;

		// Return data
		return $arr;
	}

	/**
	 * Browse publications
	 *
	 * @return     string
	 */
	public function browse()
	{
		// Build query
		$filters = array();
		$filters['limit'] 	 		= Request::getInt('limit', 25);
		$filters['start'] 	 		= Request::getInt('limitstart', 0);
		$filters['sortby']   		= Request::getVar( 't_sortby', 'title');
		$filters['sortdir']  		= Request::getVar( 't_sortdir', 'ASC');
		$filters['project']  		= $this->model->get('id');
		$filters['ignore_access']   = 1;
		$filters['dev']   	 		= 1; // get dev versions

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder' =>'projects',
				'element'=>'publications',
				'name'   =>'browse'
			)
		);

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );

		// Get all publications
		$view->rows = $objP->getRecords($filters);

		// Get total count
		$view->total = $objP->getCount($filters);

		// Areas required for publication
		$view->required = array('content', 'description', 'license', 'authors');

		// Get master publication types
		$mt = new \Components\Publications\Tables\MasterType( $this->_database );
		$choices = $mt->getTypes('alias', 1);

		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'files','css/diskspace');
		\Hubzero\Document\Assets::addPluginScript('projects', 'files','js/diskspace');

		// Get used space
		$view->dirsize = \Components\Publications\Helpers\Html::getDiskUsage($view->rows);
		$view->params  = new JParameter( $this->_project->params );
		$view->quota   = $view->params->get('pubQuota')
						? $view->params->get('pubQuota')
						: \Components\Projects\Helpers\Html::convertSize(floatval($this->model->config()->get('pubQuota', '1')), 'GB', 'b');

		// Output HTML
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project 		= $this->_project;
		$view->uid 			= $this->_uid;
		$view->filters 		= $filters;
		$view->config 		= $this->model->config();
		$view->pubconfig 	= $this->_pubconfig;
		$view->choices 		= $choices;
		$view->title		= $this->_area['title'];

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * Handler manager
	 *
	 * @return     string
	 */
	public function handler()
	{
		// Incoming
		$props  = Request::getVar( 'p', '' );
		$ajax   = Request::getInt( 'ajax', 0 );
		$pid    = Request::getInt( 'pid', 0 );
		$vid    = Request::getInt( 'vid', 0 );
		$handler= trim(Request::getVar( 'h', '' ));
		$action = trim(Request::getVar( 'do', '' ));

		// Parse props for curation
		$parts   = explode('-', $props);
		$block   = (isset($parts[0])) ? $parts[0] : 'content';
		$blockId = (isset($parts[1]) && is_numeric($parts[1]) && $parts[1] > 0) ? $parts[1] : 1;
		$element = (isset($parts[2]) && is_numeric($parts[2]) && $parts[2] > 0) ? $parts[2] : 0;

		// Output HTML
		$view = new \Hubzero\Component\View(array(
			'base_path' => PATH_CORE . DS . 'components' . DS . 'com_publications' . DS . 'site',
			'name'   => 'handlers',
			'layout' => 'editor',
		));

		$view->publication = new \Components\Publications\Models\Publication( $pid, NULL, $vid );

		if (!$view->publication->exists())
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_SELECTOR_ERROR_NO_PUBID'));
			// Output error
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'	=>'projects',
					'element'	=>'files',
					'name'		=>'error'
				)
			);

			$view->title  = '';
			$view->option = $this->_option;
			$view->setError( $this->getError() );
			return $view->loadTemplate();
		}

		// Set curation
		$view->publication->setCuration();

		// Set block
		if (!$view->publication->_curationModel->setBlock( $block, $blockId ))
		{
			$view->setError( Lang::txt('PLG_PROJECTS_PUBLICATIONS_SELECTOR_ERROR_LOADING_CONTENT') );
		}

		// Load handler
		$modelHandler = new \Components\Publications\Models\Handlers($this->_database);
		$view->handler = $modelHandler->ini($handler);
		if (!$view->handler)
		{
			$this->setError( Lang::txt('PLG_PROJECTS_PUBLICATIONS_ERROR_LOADING_HANDLER') );
		}
		else
		{
			// Perform request
			if ($action)
			{
				$modelHandler->update($view->handler, $view->publication, $element, $action);
			}

			// Load editor
			$view->editor = $modelHandler->loadEditor($view->handler, $view->publication, $element);
		}

		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->uid 			= $this->_uid;
		$view->ajax			= $ajax;
		$view->task			= $this->_task;
		$view->element		= $element;
		$view->block		= $block;
		$view->blockId 		= $blockId;
		$view->props		= $props;
		$view->config		= $this->_pubconfig;

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * View for selecting items (currently used for license selection)
	 *
	 * @return     string
	 */
	public function select()
	{
		// Incoming
		$props  = Request::getVar( 'p', '' );
		$ajax   = Request::getInt( 'ajax', 0 );
		$pid    = Request::getInt( 'pid', 0 );
		$vid    = Request::getInt( 'vid', 0 );
		$filter = urldecode(Request::getVar( 'filter', '' ));

		// Parse props for curation
		$parts   = explode('-', $props);
		$block   = (isset($parts[0])) ? $parts[0] : 'content';
		$blockId = (isset($parts[1]) && is_numeric($parts[1]) && $parts[1] > 0) ? $parts[1] : 1;
		$element = (isset($parts[2]) && is_numeric($parts[2]) && $parts[2] > 0) ? $parts[2] : 0;

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'	=>'projects',
				'element'	=>'publications',
				'name'		=>'selector'
			)
		);

		$view->publication = new \Components\Publications\Models\Publication( $pid, NULL, $vid );

		if (!$view->publication->exists())
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_SELECTOR_ERROR_NO_PUBID'));
			// Output error
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'	=>'projects',
					'element'	=>'files',
					'name'		=>'error'
				)
			);

			$view->title  = '';
			$view->option = $this->_option;
			$view->setError( $this->getError() );
			return $view->loadTemplate();
		}

		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'publications','/css/selector');

		// Set curation
		$view->publication->setCuration();

		// Set block
		if (!$view->publication->_curationModel->setBlock( $block, $blockId ))
		{
			// Output error
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'	=>'projects',
					'element'	=>'files',
					'name'		=>'error'
				)
			);

			$view->title  = '';
			$view->option = $this->_option;
			$view->setError( Lang::txt('PLG_PROJECTS_PUBLICATIONS_SELECTOR_ERROR_LOADING_CONTENT') );
			return $view->loadTemplate();
		}

		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project 		= $this->model;
		$view->uid 			= $this->_uid;
		$view->ajax			= $ajax;
		$view->task			= $this->_task;
		$view->element		= $element;
		$view->block		= $block;
		$view->blockId 		= $blockId;
		$view->props		= $props;
		$view->filter		= $filter;

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();

	}

	/**
	 * Save param in version table (AJAX)
	 *
	 * @return     string
	 */
	public function saveParam()
	{
		// Incoming
		$pid  	= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$vid  	= Request::getInt('vid', 0);
		$param  = Request::getVar('param', '');
		$value  = urldecode(Request::getVar('value', ''));

		// Load publication
		$publication = new \Components\Publications\Models\Publication( $pid, NULL, $vid );

		if ($result = $publication->saveParam($param, $value))
		{
			return json_encode(array('success' => true, 'result' => $result));
		}
		else
		{
			$this->setError(Lang::txt('Failed to save a setting'));
			return json_encode(array('error' => $this->getError(), 'result' => $result));
		}
	}

	/**
	 * Check completion status for a section via AJAX call
	 *
	 * @return     string
	 */
	public function checkStatus()
	{
		// Incoming
		$pid  		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$version 	= Request::getVar( 'version', 'default' );
		$ajax 		= Request::getInt('ajax', 0);
		$block  	= Request::getVar( 'section', '' );
		$blockId  	= Request::getInt( 'step', 0 );
		$element  	= Request::getInt( 'element', 0 );
		$props  	= Request::getVar( 'p', '' );
		$parts   	= explode('-', $props);

		// Parse props for curation
		if (!$block || !$blockId)
		{
			$block   	 = (isset($parts[0])) ? $parts[0] : 'content';
			$blockId    = (isset($parts[1]) && is_numeric($parts[1]) && $parts[1] > 0) ? $parts[1] : 1;
			$element 	 = (isset($parts[2]) && is_numeric($parts[2]) && $parts[2] > 0) ? $parts[2] : 1;
		}

		// Load publication
		$pub = new \Components\Publications\Models\Publication( $pid, $version );

		$status = new \Components\Publications\Models\Status();

		// If publication not found, raise error
		if (!$pub->exists())
		{
			return json_encode($status);
		}

		// Set curation
		$pub->setCuration();

		if ($element && $block)
		{
			// Get block element status
			$status = $pub->_curationModel->getElementStatus($block, $element, $pub, $blockId);
		}
		elseif ($block)
		{
			// Getting block status
			$status = $pub->_curationModel->getStatus($block, $pub, $blockId);
		}

		return json_encode($status);
	}

	/**
	 * Save publication draft
	 *
	 * @return     string
	 */
	public function saveDraft()
	{
		// Incoming
		$pid      = $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$version = Request::getVar( 'version', 'dev' );
		$block   = Request::getVar( 'section', '' );
		$blockId = Request::getInt( 'step', 0 );
		$element = Request::getInt( 'element', 0 );
		$next    = Request::getInt( 'next', 0 );
		$json    = Request::getInt( 'json', 0 );
		$move    = Request::getVar( 'move', '' ); // draft flow?
		$back    = Request::getVar( 'backUrl', Request::getVar('HTTP_REFERER', NULL, 'server') );
		$new	 = false;
		$props   = Request::getVar( 'p', '' );
		$parts   = explode('-', $props);

		// Parse props for curation
		if ($this->_task == 'saveitem'
			|| $this->_task == 'deleteitem'
			|| (!$block || !$blockId))
		{
			$block   	 = (isset($parts[0])) ? $parts[0] : 'content';
			$blockId    = (isset($parts[1]) && is_numeric($parts[1]) && $parts[1] > 0) ? $parts[1] : 1;
			$element 	 = (isset($parts[2]) && is_numeric($parts[2]) && $parts[2] > 0) ? $parts[2] : 0;
		}

		// Load publication
		$pub = new \Components\Publications\Models\Publication( $pid, $version );

		// Error loading publication record
		if (!$pub->exists() && $new == false)
		{
			$this->_referer = Route::url($pub->link('editbase'));
			$this->_message = array(
				'message' => Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'),
				'type' => 'error');
			return;
		}

		// Get curation
		$pub->setCuration();

		// Make sure block exists, else redirect to status
		if (!$pub->_curationModel->setBlock( $block, $blockId ))
		{
			$block = 'status';
		}

		// Save incoming
		switch ($this->_task)
		{
			case 'additem':
				$pub->_curationModel->addItem($this->_uid, $element);
				break;

			case 'saveitem':
				$pub->_curationModel->saveItem($this->_uid, $element);
				break;

			case 'deleteitem':
				$pub->_curationModel->deleteItem($this->_uid, $element);
				break;

			case 'reorder':
				$pub->_curationModel->reorder($this->_uid, $element);
				$json = 1; // return result as json
				break;

			case 'dispute':
				$pub->_curationModel->dispute($this->_uid, $element);
				break;

			case 'skip':
				$pub->_curationModel->skip($this->_uid, $element);
				break;

			case 'undispute':
				$pub->_curationModel->undispute($this->_uid, $element);
				break;

			default:
				if ($this->_task != 'rewind')
				{
					$pub->_curationModel->saveBlock($this->_uid, $element);
				}
				break;
		}

		// Save new version label
		if ($block == 'status')
		{
			$pub->_curationModel->saveVersionLabel($this->_uid);
		}

		// Pick up error messages
		if ($pub->_curationModel->getError())
		{
			$this->setError($pub->_curationModel->getError());
		}

		// Pick up success message
		$this->_msg = $pub->_curationModel->get('_message')
			? $pub->_curationModel->get('_message')
			: Lang::txt(ucfirst($block) . ' information successfully saved');

		// Record action, notify team
		$this->onAfterSave( $pub );

		// Report only status action
		if ($json)
		{
			return json_encode(
				array(
					'success' => 1,
					'error'   => $this->getError(),
					'message' => $this->_msg
				)
			);
		}

		// Go back to panel after changes to individual attachment
		if ($this->_task == 'saveitem' || $this->_task == 'deleteitem')
		{
			$this->_referer = $back;
			return;
		}

		// Get blockId & count
		$blockId = $pub->_curationModel->_blockorder;
		$total	 = $pub->_curationModel->_blockcount;

		// Get next element
		if ($next)
		{
			$next = $pub->_curationModel->getNextElement($block, $blockId, $element);
		}

		// What's next?
		$nextnum 	 = $pub->_curationModel->getNextBlock($block, $blockId);
		$nextsection = isset($pub->_curationModel->_blocks->$nextnum)
					 ? $pub->_curationModel->_blocks->$nextnum->name : 'status';

		// Get previous section
		$prevnum 	 = $pub->_curationModel->getPreviousBlock($block, $blockId);
		$prevsection = isset($pub->_curationModel->_blocks->$prevnum)
					 ? $pub->_curationModel->_blocks->$prevnum->name : 'status';

		// Build route
		$route  = $pub->link('edit');
		$route .= $move ? '&move=continue' : '';

		// Append version label
		$route .= $version != 'default' ? '&version=' . $version : '';

		// Determine which panel to go to
		if ($this->_task == 'apply' || !$move)
		{
			// Stay where you were
			$route .= '&section=' . $block;
			$route .= $block == 'status' ? '' : '&step=' . $blockId;

			if ($next)
			{
				if ($next == $element)
				{
					// Move to next block
					$route .= '&section=' . $nextsection;
					$route .= $nextnum ? '&step=' . $nextnum : '';
				}
				else
				{
					$route .= '&el=' . $next . '#element' . $next;
				}
			}
			elseif ($element)
			{
				$route .= '&el=' . $element . '#element' . $element;
			}
		}
		elseif ($this->_task == 'rewind')
		{
			// Go back one step
			$route .= '&section=' . $prevsection;
			$route .= $prevnum ? '&step=' . $prevnum : '';
		}
		else
		{
			// Move next
			$route .= '&section=' . $nextsection;
			$route .= $nextnum ? '&step=' . $nextnum : '';

			if ($next)
			{
				$route .= '&el=' . $next . '#element' . $next;
			}
		}

		// Redirect
		$this->_referer = htmlspecialchars_decode(Route::url($route));
		return;
	}

	/**
	 * Actions after publication draft is saved
	 *
	 * @return     string
	 */
	public function onAfterSave( $pub, $versionNumber = 1 )
	{
		// No afterSave actions when backing one step
		if ($this->_task == 'rewind')
		{
			return false;
		}

		// Pass success or error message
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
		}
		elseif (isset($this->_msg) && $this->_msg)
		{
			$this->_message = array('message' => $this->_msg, 'type' => 'success');
		}

		// Record activity
		if ($this->get('_activity'))
		{
			$pubTitle = \Hubzero\Utility\String::truncate($pub->title, 100);
			$aid = $this->model->recordActivity(
				   $this->get('_activity'), $pub->id, $pubTitle,
				   Route::url('index.php?option=' . $this->_option .
				   '&alias=' . $this->model->get('alias') . '&active=publications' .
				   '&pid=' . $pub->id) . '/?version=' . $versionNumber, 'publication', 1 );
		}

	}

	/**
	 * Actions after publication draft is started
	 *
	 * @return     string
	 */
	public function onAfterCreate($pub)
	{
		// Record activity
		if (!$this->model->isProvisioned() && $pub->exists() && !$this->getError())
		{
			$aid   = $this->model->recordActivity(
				   Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_STARTED_NEW_PUB')
					.' (id ' . $pub->get('id') . ')', $pub->get('id'), 'publication',
				   Route::url($pub->link('edit')), 'publication', 1 );
		}

		// Notify project managers
		$objO = new \Components\Projects\Tables\Owner($this->_database);
		$managers = $objO->getIds($this->model->get('id'), 1, 1);
		if (!empty($managers) && !$this->model->isProvisioned())
		{
			$sef = Route::url($pub->link());
			$sef = trim($sef, DS);

			\Components\Projects\Helpers\Html::sendHUBMessage(
				'com_projects',
				$this->model->config(),
				$this->model->project(),
				$managers,
				Lang::txt('COM_PROJECTS_EMAIL_MANAGERS_NEW_PUB_STARTED'),
				'projects_admin_notice',
				'publication',
				User::get('name') . ' '
					. Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_STARTED_NEW_PUB')
					.' (id ' . $pub->get('id') . ')' . ' - ' . Request::base()
					. $sef . '/?version=' . $pub->get('version_number')
			);
		}
	}

	/**
	 * Start a new publication draft
	 *
	 * @return     string
	 */
	public function startDraft()
	{
		// Get contributable types
		$mt = new \Components\Publications\Tables\MasterType( $this->_database );
		$choices = $mt->getTypes('*', 1, 0, 'ordering', $this->model->config());

		// Contribute process outside of projects
		if (!$this->model->exists())
		{
			$this->model->set('provisioned', 1);
		}

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'	=>'projects',
				'element'	=>'publications',
				'name'		=>'draft',
				'layout'	=>'start'
			)
		);

		// Build pub url
		$view->route = $this->model->isProvisioned()
					? 'index.php?option=com_publications&task=submit'
					: 'index.php?option=com_projects&alias=' . $this->model->get('alias') . '&active=publications';
		$view->url = Route::url($view->route);

		// Do we have a choice?
		if (count($choices) <= 1 )
		{
			$this->_referer = Route::url($view->route . '&action=edit');
			return;
		}

		// Append breadcrumbs
		Pathway::append(
			stripslashes(Lang::txt('PLG_PROJECTS_PUBLICATIONS_START_PUBLICATION')),
			$view->url . '?action=start'
		);

		// Output HTML
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project 		= $this->model;
		$view->uid 			= $this->_uid;
		$view->config 		= $this->model->config();
		$view->choices 		= $choices;
		$view->title		= $this->_area['title'];

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * Provision a new publication draft
	 *
	 * @return     object
	 */
	public function createDraft()
	{
		// Incoming
		$base = Request::getVar( 'base', 'files' );

		// Load publication & version classes
		$objP = new \Components\Publications\Tables\Publication( $this->_database );
		$objC = new \Components\Publications\Tables\Category( $this->_database );
		$mt   = new \Components\Publications\Tables\MasterType( $this->_database );

		// Determine publication master type
		$choices  = $mt->getTypes('alias', 1);
		if (count($choices) == 1)
		{
			$base = $choices[0];
		}

		// Default to file type
		$mastertype = in_array($base, $choices) ? $base : 'files';

		// Need to provision a project
		if (!$this->model->exists())
		{
			$alias = 'pub-' . strtolower(\Components\Projects\Helpers\Html::generateCode(10, 10, 0, 1, 1));
			$this->model->set('provisioned', 1);
			$this->model->set('alias', $alias);
			$this->model->set('title', $alias);
			$this->model->set('type', 2); // publication
			$this->model->set('state', 1);
			$this->model->set('setup_stage', 3);
			$this->model->set('created', Date::toSql());
			$this->model->set('created_by_user', $this->_uid);
			$this->model->set('owned_by_user', $this->_uid);
			$this->model->set('params', $this->model->type()->params);

			// Save changes
			if (!$this->model->store())
			{
				$this->setError( $this->model->getError() );
				return false;
			}
		}

		// Get type params
		$mType = $mt->getType($mastertype);

		// Make sure we got type info
		if (!$mType)
		{
			throw new Exception(Lang::txt('Error loading publication type'));
			return false;
		}

		// Get curation model for the type
		$curationModel = new \Components\Publications\Models\Curation($mType->curation);

		// Get default category from manifest
		$cat = isset($curationModel->_manifest->params->default_category)
				? $curationModel->_manifest->params->default_category : 1;
		if (!$objC->load($cat))
		{
			$cat = 1;
		}

		// Get default title from manifest
		$title = isset($curationModel->_manifest->params->default_title)
				? $curationModel->_manifest->params->default_title : Lang::txt('Untitled Draft');

		// Make a new publication entry
		$objP->master_type 		= $mType->id;
		$objP->category 		= $cat;
		$objP->project_id 		= $this->model->get('id');
		$objP->created_by 		= $this->_uid;
		$objP->created 			= Date::toSql();
		$objP->access 			= 0;
		if (!$objP->store())
		{
			throw new Exception( $objP->getError() );
			return false;
		}
		if (!$objP->id)
		{
			$objP->checkin();
		}
		$this->_pid = $objP->id;

		// Initizalize Git repo and transfer files from member dir
		if ($this->model->isProvisioned())
		{
			if (!$this->_prepDir())
			{
				// Roll back
				$this->model->delete();
				$objP->delete();

				throw new Exception( Lang::txt('PLG_PROJECTS_PUBLICATIONS_ERROR_FAILED_INI_GIT_REPO') );
				return false;
			}
			else
			{
				// Add creator as project owner
				$objO = $this->model->table('Owner');
				if (!$objO->saveOwners ( $this->model->get('id'),
					$this->_uid, $this->_uid,
					0, 1, 1, 1 ))
				{
					// File auto ticket to report this - TBD
					//*******
					$this->setError( Lang::txt('COM_PROJECTS_ERROR_SAVING_AUTHORS').': '.$objO->getError() );
					return false;
				}
			}
		}

		// Make a new dev version entry
		$row 					= new \Components\Publications\Tables\Version( $this->_database );
		$row->publication_id 	= $this->_pid;
		$row->title 			= $row->getDefaultTitle($this->model->get('id'), $title);
		$row->state 			= 3; // dev
		$row->main 				= 1;
		$row->created_by 		= $this->_uid;
		$row->created 			= Date::toSql();
		$row->version_number 	= 1;
		$row->license_type 		= 0;
		$row->access 			= 0;
		$row->secret 			= strtolower(\Components\Projects\Helpers\Html::generateCode(10, 10, 0, 1, 1));

		if (!$row->store())
		{
			// Roll back
			$objP->delete();

			throw new Exception( $row->getError(), 500 );
			return false;
		}
		if (!$row->id)
		{
			$row->checkin();
		}

		// Models\Publication
		$pub = new \Components\Publications\Models\Publication( $this->_pid, 'dev' );

		// Record action, notify team
		$this->onAfterCreate($pub);

		// Return publication object
		return $pub;
	}

	/**
	 * View/Edit publication draft
	 *
	 * @return     string
	 */
	public function editDraft()
	{
		// Incoming
		$pid 		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$version 	= Request::getVar( 'version', 'dev' );
		$block  	= Request::getVar( 'section', 'status' );
		$blockId  	= Request::getInt( 'step', 0 );

		// Provision draft
		if (!$pid)
		{
			$pub = $this->createDraft();
			if (!$pub || !$pub->exists())
			{
				throw new Exception(Lang::txt('Error creating a publication draft'), 500);
				return;
			}

			// Get curation model
			$pub->setCuration();
			$blockId 	   = $pub->_curationModel->getFirstBlock();
			$firstBlock    = $pub->_curationModel->_blocks->$blockId->name;

			// Redirect to first block
			$this->_referer = Route::url($pub->link('edit')
				. '&move=continue&step=' . $blockId . '&section=' . $firstBlock);
			return;
		}
		else
		{
			$pub = new \Components\Publications\Models\Publication( $pid, $version );
		}

		// If publication not found, raise error
		if (!$pub->exists() || $pub->isDeleted())
		{
			$this->_referer = Route::url($pub->link('editbase'));
			$this->_message = array(
				'message'   => Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'),
				'type'      => 'error');
			return;
		}

		// Get curation model
		$pub->setCuration();

		// For publications created in a non-curated flow - convert
		$pub->_curationModel->convertToCuration($pub, $this->_uid);

		// Go to last incomplete section
		if ($this->_task == 'continue')
		{
			$blocks 	= $pub->_curationModel->_progress->blocks;
			$blockId	= $pub->_curationModel->_progress->firstBlock;
			$block		= $blockId ? $blocks->$blockId->name : 'status';
		}

		// Go to review screen
		if ($this->_task == 'review'
			|| ($this->_task == 'continue' && $pub->_curationModel->_progress->complete == 1)
		)
		{
			$blockId	= $pub->_curationModel->_progress->lastBlock;
			$block		= 'review';
		}

		// Certain publications go to status page
		if ($pub->state == 5 || $pub->state == 0 || ($block == 'review' && $pub->state == 1))
		{
			$block = 'status';
			$blockId = 0;
		}

		// Make sure block exists, else redirect to status
		if (!$pub->_curationModel->setBlock( $block, $blockId ))
		{
			$block = 'status';
		}

		// Get requested block
		$name = $block == 'status' ? 'status' : 'draft';

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'	=>'projects',
				'element'	=>'publications',
				'name'		=> $name,
			)
		);

		// Output HTML
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project		= $this->model;
		$view->uid 			= $this->_uid;
		$view->config 		= $this->model->config();
		$view->title		= $this->_area['title'];
		$view->active		= $block;
		$view->pub 			= $pub;
		$view->pubconfig 	= $this->_pubconfig;
		$view->task			= $this->_task;

		// Append breadcrumbs
		$this->_appendBreadcrumbs( $pub->get('title'), $pub->link('edit'), $version);

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * Edit content item
	 *
	 * @return     string
	 */
	public function editItem()
	{
		// Incoming
		$id 	= Request::getInt( 'aid', 0 );
		$props  = Request::getVar( 'p', '' );

		// Parse props for curation
		$parts   = explode('-', $props);
		$block   = (isset($parts[0])) ? $parts[0] : 'content';
		$step    = (isset($parts[1]) && is_numeric($parts[1]) && $parts[1] > 0) ? $parts[1] : 1;
		$element = (isset($parts[2]) && is_numeric($parts[2]) && $parts[2] > 0) ? $parts[2] : 0;

		if ($this->_task == 'editauthor')
		{
			// Get author information
			$row 	= new \Components\Publications\Tables\Author( $this->_database );
			$error 	= Lang::txt('PLG_PROJECTS_PUBLICATIONS_CONTENT_ERROR_LOAD_AUTHOR');
			$layout = 'author';
		}
		else
		{
			// Load attachment
			$row 	= new \Components\Publications\Tables\Attachment( $this->_database );
			$error 	= Lang::txt('PLG_PROJECTS_PUBLICATIONS_CONTENT_ERROR_EDIT_CONTENT');
			$layout = 'attachment';
		}

		// We need attachment record
		if (!$id || !$row->load($id))
		{
			$this->setError($error);
		}

		// Load publication
		$pub = new \Components\Publications\Models\Publication(NULL, NULL, $row->publication_version_id);
		if (!$pub->exists())
		{
			$this->setError($error);
		}

		// On error
		if ($this->getError())
		{
			// Output error
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'	=>'projects',
					'element'	=>'publications',
					'name'		=>'error'
				)
			);

			$view->title  = '';
			$view->option = $this->_option;
			$view->setError( $this->getError() );
			return $view->loadTemplate();
		}

		// Set curation
		$pub->setCuration();

		// On success
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'	=> 'projects',
				'element'	=> 'publications',
				'name'		=> 'edititem',
				'layout'	=> $layout
			)
		);

		// Get project path
		if ($this->_task != 'editauthor')
		{
			$view->path = $pub->project()->repo()->get('path');
		}

		$view->step 	= $step;
		$view->block	= $block;
		$view->element  = $element;
		$view->database = $this->_database;
		$view->option 	= $this->_option;
		$view->project 	= $this->model;
		$view->pub		= $pub;
		$view->row		= $row;
		$view->backUrl	= Request::getVar('HTTP_REFERER', NULL, 'server');
		$view->ajax		= Request::getInt( 'ajax', 0 );
		$view->props	= $props;

		return $view->loadTemplate();
	}

	/**
	 *  Append breadcrumbs
	 *
	 * @return   void
	 */
	protected function _appendBreadcrumbs( $title, $url, $version = 'default')
	{
		// Append breadcrumbs
		$url = $version != 'default' ? $url . '&version=' . $version : $url;
		Pathway::append(
			stripslashes($title),
			$url
		);
	}

	/**
	 *  Publication stats
	 *
	 * @return     string
	 */
	protected function _stats()
	{
		// Incoming
		$pid 		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$version 	= Request::getVar( 'version', '' );

		// Load publication & version classes
		$objP = new \Components\Publications\Tables\Publication( $this->_database );
		$row  = new \Components\Publications\Tables\Version( $this->_database );

		// Check that version exists
		$version = $row->checkVersion($pid, $version) ? $version : 'default';

		// Add stylesheet
		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'publications','css/impact');

		require_once( PATH_CORE . DS . 'components'. DS
				.'com_publications' . DS . 'tables' . DS . 'logs.php');

		$view = new \Hubzero\Plugin\View(
			array(
				'folder'=>'projects',
				'element'=>'publications',
				'name'=>'stats'
			)
		);

		// Get pub stats for each publication
		$pubLog = new \Components\Publications\Tables\Log($this->_database);
		$view->pubstats = $pubLog->getPubStats($this->model->get('id'), $pid);

		// Get date of first log
		$view->firstlog = $pubLog->getFirstLogDate();

		// Test
		$view->totals = $pubLog->getTotals($this->model->get('id'), 'project');

		// Output HTML
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project 		= $this->_project;
		$view->uid 			= $this->_uid;
		$view->pid 			= $pid;
		$view->pub			= $objP->getPublication($pid, $version, $this->model->get('id'));
		$view->task 		= $this->_task;
		$view->config 		= $this->model->config();
		$view->pubconfig 	= $this->_pubconfig;
		$view->version 		= $version;
		$view->route 		= $this->model->isProvisioned()
					? 'index.php?option=com_publications&task=submit'
					: 'index.php?option=com_projects&alias=' . $this->model->get('alias')
					. '&active=publications';
		$view->url 			= $pid ? Route::url($view->route . '&pid=' . $pid) : Route::url($view->route);
		$view->title		= $this->_area['title'];

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * Suggest licence
	 *
	 * @return     string
	 */
	protected function _suggestLicense()
	{
		// Incoming
		$pid  		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$version 	= Request::getVar( 'version', 'default' );
		$ajax 		= Request::getInt('ajax', 0);

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );
		$row = new \Components\Publications\Tables\Version( $this->_database );

		// If publication not found, raise error
		$pub = $objP->getPublication($pid, $version, $this->model->get('id'));
		if (!$pub)
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'));
			$this->_task = '';
			return $this->browse();
		}

		// Build pub url
		$route = $this->model->isProvisioned()
			? 'index.php?option=com_publications&task=submit'
			: 'index.php?option=com_projects&alias=' . $this->model->get('alias') . '&active=publications';
		$url = Route::url($route . '&pid=' . $pid);

		if ($this->_task == 'save_license')
		{
			$l_title 	= htmlentities(Request::getVar('license_title', '', 'post'));
			$l_url 		= htmlentities(Request::getVar('license_url', '', 'post'));
			$l_text 	= htmlentities(Request::getVar('details', '', 'post'));

			if (!$l_title && !$l_url && !$l_text)
			{
				$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_SUGGESTION_ERROR'));
			}
			else
			{
				// Include support scripts
				include_once( PATH_CORE . DS . 'administrator' . DS . 'components'
					. DS . 'com_support' . DS . 'tables' . DS . 'ticket.php' );
				include_once( PATH_CORE . DS . 'administrator' . DS . 'components'
					. DS . 'com_support' . DS . 'tables' . DS . 'comment.php' );

				// Load the support config
				$sparams = Component::params('com_support');

				$row = new \Components\Support\Tables\Ticket( $this->_database );
				$row->created = Date::toSql();
				$row->login = User::get('username');
				$row->email = User::get('email');
				$row->name  = User::get('name');
				$row->summary = Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_SUGGESTION_NEW');

				$report 	 	= Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_TITLE') . ': '. $l_title ."\r\n";
				$report 	   .= Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_URL') . ': '. $l_url ."\r\n";
				$report 	   .= Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_COMMENTS') . ': '. $l_text ."\r\n";
				$row->report 	= $report;
				$row->referrer 	= Request::getVar('HTTP_REFERER', NULL, 'server');
				$row->type	 	= 0;
				$row->severity	= 'normal';

				$admingroup = $this->model->config()->get('admingroup', '');
				$group = \Hubzero\User\Group::getInstance($admingroup);
				$row->group = $group ? $group->get('cn') : '';

				if (!$row->store())
				{
					$this->setError($row->getError());
				}
				else
				{
					$ticketid = $row->id;

					// Notify project admins
					$message  = $row->name . ' ' . Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_SUGGESTED')."\r\n";
					$message .= '----------------------------'."\r\n";
					$message .=	$report;
					$message .= '----------------------------'."\r\n";

					if ($ticketid)
					{
						$juri = JURI::getInstance();

						$message .= Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_TICKET_PATH') ."\n";
						$message .= $juri->base() . 'support/ticket/' . $ticketid . "\n\n";
					}

					if ($group)
					{
						$members 	= $group->get('members');
						$managers 	= $group->get('managers');
						$admins 	= array_merge($members, $managers);
						$admins 	= array_unique($admins);

						// Send out email to admins
						if (!empty($admins))
						{
							\Components\Projects\Helpers\Html::sendHUBMessage(
								$this->_option,
								$this->model->config(),
								$this->_project,
								$admins,
								Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_SUGGESTION_NEW'),
								'projects_new_project_admin',
								'admin',
								$message
							);
						}
					}

					$this->_msg = Lang::txt('PLG_PROJECTS_PUBLICATIONS_LICENSE_SUGGESTION_SENT');
				}
			}
		}
		else
		{
			 $view = new \Hubzero\Plugin\View(
				array(
					'folder'=>'projects',
					'element'=>'publications',
					'name'=>'suggestlicense'
				)
			);

			// Output HTML
			$view->option 		= $this->_option;
			$view->database 	= $this->_database;
			$view->project 		= $this->_project;
			$view->uid 			= $this->_uid;
			$view->pid 			= $pid;
			$view->pub 			= $pub;
			$view->task 		= $this->_task;
			$view->config 		= $this->model->config();
			$view->pubconfig 	= $this->_pubconfig;
			$view->ajax 		= $ajax;
			$view->route 		= $route;
			$view->version 		= $version;
			$view->url 			= $url;
			$view->title		= $this->_area['title'];

			// Get messages	and errors
			$view->msg = $this->_msg;
			if ($this->getError())
			{
				$view->setError( $this->getError() );
			}
			return $view->loadTemplate();
		}

		// Pass success or error message
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
		}
		elseif (isset($this->_msg) && $this->_msg)
		{
			$this->_message = array('message' => $this->_msg, 'type' => 'success');
		}

		// Redirect
		$this->_referer = $url . '?version=' . $version . '&section=license';
		return;
	}

	/**
	 * Start/save a new version (curation flow)
	 *
	 * @return     string
	 */
	public function makeNewVersion($pub, $oldVersion, $newVersion)
	{
		// Get authors
		$pAuthors 			= new \Components\Publications\Tables\Author( $this->_database );
		$pub->_authors 		= $pAuthors->getAuthors($pub->version_id);
		$pub->_submitter 	= $pAuthors->getSubmitter($pub->version_id, $pub->created_by);

		// Get attachments
		$pContent = new \Components\Publications\Tables\Attachment( $this->_database );
		$pub->_attachments = $pContent->sortAttachments ( $pub->version_id );

		// Transfer data
		$pub->_curationModel->transfer($pub, $oldVersion, $newVersion);

		// Set response message
		$this->set('_msg', Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NEW_VERSION_STARTED'));

		// Set activity message
		$pubTitle = \Hubzero\Utility\String::truncate($newVersion->title, 100);
		$action   = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_STARTED_VERSION')
					. ' ' . $newVersion->version_label . ' ';
		$action .=  Lang::txt('PLG_PROJECTS_PUBLICATIONS_OF_PUBLICATION') . ' "' . $pubTitle . '"';
		$this->set('_activity', $action);

		// Record action, notify team
		$this->onAfterSave( $pub, $newVersion->version_number );

	}

	/**
	 * Start/save a new version
	 *
	 * @return     string
	 */
	protected function newVersion()
	{
		// Incoming
		$pid  = $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$ajax = Request::getInt('ajax', 0);
		$label = trim(Request::getVar( 'version_label', '', 'post' ));

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );
		$row  = new \Components\Publications\Tables\Version( $this->_database );

		// If publication not found, raise error
		$pub = $objP->getPublication($pid, 'default', $this->model->get('id'));
		if (!$pub)
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'));
			$this->_task = '';
			return $this->browse();
		}

		// Load master type
		$mt   			= new \Components\Publications\Tables\MasterType( $this->_database );
		$pub->_type   	= $mt->getType($pub->base);
		$pub->_project 	= $this->model;

		// Get curation model
		$pub->_curationModel = new \Components\Publications\Models\Curation($pub->_type->curation);

		// Set pub assoc and load curation
		$pub->_curationModel->setPubAssoc($pub);

		// Build pub url
		$route = $this->model->isProvisioned()
			? 'index.php?option=com_publications&task=submit'
			: 'index.php?option=com_projects&alias=' . $this->model->get('alias') . '&active=publications';
		$url = Route::url($route . '&pid=' . $pid);

		// Check if dev version is already there
		if ($row->checkVersion($pid, 'dev'))
		{
			// Redirect
			$this->_referer = $url.'?version=dev';
			return;
		}

		// Load default version
		$row->loadVersion($pid, 'default');
		$oldid = $row->id;
		$now = Date::toSql();

		// Can't start a new version if there is a finalized or submitted draft
		if ($row->state == 4 || $row->state == 5 || $row->state == 7)
		{
			// Determine redirect path
			$this->_referer = $url . '?version=default';
			return;
		}

		// Saving new version
		if ($this->_task == 'savenew')
		{
			$used_labels = $row->getUsedLabels( $pid, 'dev');
			if (!$label)
			{
				$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VERSION_LABEL_NONE') );
			}
			elseif ($label && in_array($label, $used_labels))
			{
				$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VERSION_LABEL_USED') );
			}
			else
			{
				// Create new version
				$new 				=  new \Components\Publications\Tables\Version( $this->_database );
				$new 				= $row; // copy of default version
				$new->id 			= 0;
				$new->created 		= $now;
				$new->created_by 	= $this->_uid;
				$new->modified 		= $now;
				$new->modified_by 	= $this->_uid;
				$new->rating 		= '0.0';
				$new->state 		= 3;
				$new->version_label = $label;
				$new->doi 			= '';
				$new->secret 		= strtolower(\Components\Projects\Helpers\Html::generateCode(10, 10, 0, 1, 1));
				$new->version_number= $pub->versions + 1;
				$new->main 			= 0;
				$new->release_notes = NULL; // Release notes will need to be different
				$new->submitted 	= NULL;
				$new->reviewed 		= NULL;
				$new->reviewed_by   = 0;
				$new->curation		= NULL; // Curation manifest needs to reflect any new requirements
				$new->params		= NULL; // Accept fresh configs

				if ($new->store())
				{
					$newid = $new->id;

					$this->makeNewVersion($pub, $row, $new);

					// Redirect
					$this->_referer = $url . '?version=dev';
					return;



					// Copy audience info
					$pAudience = new \Components\Publications\Tables\Audience( $this->_database );
					if ($pAudience->loadByVersion($oldid))
					{
						$pAudienceNew = new \Components\Publications\Tables\Audience( $this->_database );
						$pAudienceNew = $pAudience;
						$pAudienceNew->publication_version_id = $newid;
						$pAudienceNew->store();
					}

					$this->_msg = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NEW_VERSION_STARTED');

					// Record activity
					$pubtitle = \Hubzero\Utility\String::truncate($new->title, 100);
					$action  = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_STARTED_VERSION').' '.$new->version_label.' ';
					$action .=  Lang::txt('PLG_PROJECTS_PUBLICATIONS_OF_PUBLICATION').' "'.$pubtitle.'"';
					$objAA = new \Components\Projects\Tables\Activity ( $this->_database );
					$aid = $objAA->recordActivity( $this->model->get('id'), $this->_uid,
						   $action, $pid, $pubtitle,
						   Route::url('index.php?option=' . $this->_option . a .
						   'alias=' . $this->model->get('alias') . '&active=publications' . a .
						   'pid=' . $pid) . '/?version=' . $new->version_number, 'publication', 1 );
				}
				else
				{
					$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ERROR_SAVING_NEW_VERSION') );
				}
			}
		}
		// Need to ask for new version label
		else
		{
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'=>'projects',
					'element'=>'publications',
					'name'=>'newversion'
				)
			);

			// Output HTML
			$view->option 		= $this->_option;
			$view->database 	= $this->_database;
			$view->project 		= $this->_project;
			$view->uid 			= $this->_uid;
			$view->pid 			= $pid;
			$view->pub 			= $pub;
			$view->task 		= $this->_task;
			$view->config 		= $this->model->config();
			$view->pubconfig 	= $this->_pubconfig;
			$view->ajax 		= $ajax;
			$view->route 		= $route;
			$view->url 			= $url;
			$view->title		= $this->_area['title'];

			// Get messages	and errors
			$view->msg = $this->_msg;
			if ($this->getError())
			{
				$view->setError( $this->getError() );
			}
			return $view->loadTemplate();
		}

		// Pass success or error message
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
		}
		elseif (isset($this->_msg) && $this->_msg)
		{
			$this->_message = array('message' => $this->_msg, 'type' => 'success');
		}

		// Redirect
		$this->_referer = $url.'?version=dev';
		return;
	}

	/**
	 * Check if there is available space for publishing
	 *
	 * @return     string
	 */
	protected function _overQuota()
	{
		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );

		// Get all publications
		$rows = $objP->getRecords(array('project' => $this->model->get('id'), 'dev' => 1, 'ignore_access' => 1));

		// Get used space
		$dirsize 	   = \Components\Publications\Helpers\Html::getDiskUsage($rows);
		$params  	   = new JParameter( $this->_project->params );
		$quota   	   = $params->get('pubQuota')
						? $params->get('pubQuota')
						: \Components\Projects\Helpers\Html::convertSize(floatval($this->model->config()->get('pubQuota', '1')), 'GB', 'b');

		if (($quota - $dirsize) <= 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Change publication status
	 *
	 * @return     string
	 */
	public function publishDraft()
	{
		// Incoming
		$pid 		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$confirm 	= Request::getInt('confirm', 0);
		$version 	= Request::getVar('version', 'dev');
		$agree   	= Request::getInt('agree', 0);
		$pubdate 	= Request::getVar('publish_date', '', 'post');
		$submitter 	= Request::getInt('submitter', $this->_uid, 'post');
		$notify 	= 1;

		$block  	= Request::getVar( 'section', '' );
		$blockId  	= Request::getInt( 'step', 0 );
		$element  	= Request::getInt( 'element', 0 );

		// Load review step
		if (!$confirm && $this->_task != 'revert')
		{
			$this->_task = 'review';
			return $this->editDraft();
		}

		// Start url
		$route = $this->model->isProvisioned()
					? 'index.php?option=com_publications&task=submit'
					: 'index.php?option=com_projects&alias='
						. $this->model->get('alias') . '&active=publications';

		// Determine redirect path
		$url = Route::url($route . '&pid=' . $pid);

		// Agreement to terms is required
		if ($confirm && !$agree)
		{
			$url .= '/?action= ' . $this->_task . '&version=' . $version;
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_REVIEW_AGREE_TERMS_REQUIRED') );
			$this->_message = array('message' => $this->getError(), 'type' => 'error');

			// Redirect
			$this->_referer = $url;
			return;
		}

		// Check against quota
		if ($this->_overQuota())
		{
			$url .= '/?action= ' . $this->_task . '&version=' . $version;
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NO_DISK_SPACE') );
			$this->_message = array('message' => $this->getError(), 'type' => 'error');

			// Redirect
			$this->_referer = $url;
			return;
		}

		// Load publication model
		$model  = new \Components\Publications\Models\Publication( $pid, $version);

		// Error loading publication record
		if (!$model->exists())
		{
			$this->_referer = Route::url($route);
			$this->_message = array(
				'message' => Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'),
				'type' => 'error');
			return;
		}

		$model->setCuration();
		$complete = $model->_curationModel->_progress->complete;

		// Require DOI?
		$requireDoi = isset($model->_curationModel->_manifest->params->require_doi)
					? $model->_curationModel->_manifest->params->require_doi : 0;

		// Make sure the publication belongs to the project
		if ($this->model->get('id') != $model->_project->id)
		{
			$this->_referer = Route::url($route);
			$this->_message = array(
				'message' => Lang::txt('Oups! The publication you are trying to change is hosted by another project.'),
				'type' => 'error');
			return;
		}

		// Check that version label was not published before
		$used_labels = $model->version->getUsedLabels( $pid, $version );
		if (!$model->version->version_label || in_array($model->version->version_label, $used_labels))
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VERSION_LABEL_USED') );
		}

		// Is draft complete?
		if (!$complete && $this->_task != 'revert')
		{
			$this->setError( Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_ALLOWED') );
		}

		// Is revert allowed?
		$revertAllowed = $this->_pubconfig->get('graceperiod', 0);
		if ($revertAllowed && $model->version->state == 1
			&& $model->version->accepted && $model->version->accepted != '0000-00-00 00:00:00')
		{
			$monthFrom = JFactory::getDate($model->version->accepted . '+1 month')->toSql();
			if (strtotime($monthFrom) < strtotime(JFactory::getDate()))
			{
				$revertAllowed = 0;
			}
		}

		// Embargo?
		if ($pubdate)
		{
			$pubdate = $this->parseDate($pubdate);

			$tenYearsFromNow = JFactory::getDate(strtotime("+10 years"))->toSql();

			// Stop if more than 10 years from now
			if ($pubdate > $tenYearsFromNow)
			{
				$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ERROR_EMBARGO') );
			}
		}

		// Main version?
		$main = $this->_task == 'republish' ? $model->version->main : 1;
		$main_vid = $model->version->getMainVersionId($pid); // current default version

		// Save version before changes
		$originalStatus = $model->version->state;

		// Checks
		if ($this->_task == 'republish' && $model->version->state != 0)
		{
			// Can only re-publish unpublished version
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_CANNOT_REPUBLISH') );
		}
		elseif ($this->_task == 'revert' && $model->version->state != 5 && !$revertAllowed)
		{
			// Can only revert a pending resource
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_CANNOT_REVERT') );
		}

		// On error
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
			$this->_referer = $url;
			return;
		}

		// Determine state
		$state = 5; // Default - pending approval
		if ($this->_task == 'share' || $this->_task == 'revert')
		{
			$state = 4; // No approval needed
		}
		elseif ($this->_task == 'republish')
		{
			$state = 1; // No approval needed
		}
		else
		{
			$model->version->submitted = Date::toSql();

			// Save submitter
			$pa = new \Components\Publications\Tables\Author( $this->_database );
			$pa->saveSubmitter($model->version->id, $submitter, $this->model->get('id'));

			if ($this->_pubconfig->get('autoapprove') == 1 )
			{
				$state = 1;
			}
			else
			{
				$apu = $this->_pubconfig->get('autoapproved_users');
				$apu = explode(',', $apu);
				$apu = array_map('trim',$apu);

				if (in_array(User::get('username'),$apu))
				{
					// Set status to published
					$state = 1;
				}
				else
				{
					// Set status to pending review (submitted)
					$state = 5;
				}
			}
		}

		// Save state
		$model->version->state 		= $state;
		$model->version->main 		= $main;
		if ($this->_task != 'revert')
		{
			$model->version->rating 		= '0.0';
			$model->version->published_up   = $this->_task == 'republish'
				? $model->version->published_up : Date::toSql();
			$model->version->published_up  	= $pubdate ? $pubdate : $model->version->published_up;
			$model->version->published_down = '';
		}
		$model->version->modified    = Date::toSql();
		$model->version->modified_by = $this->_uid;

		// Issue DOI
		if ($requireDoi > 0 && $this->_task == 'publish' && !$model->version->doi)
		{
			// Get DOI service
			$doiService = new \Components\Publications\Models\Doi($model);
			$extended = $state == 5 ? false : true;
			$doi = $doiService->register($extended);

			// Store DOI
			if ($doi)
			{
				$model->version->doi = $doi;
			}

			// Can't proceed without a valid DOI
			if (!$doi || $doiService->getError())
			{
				$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_ERROR_DOI')
					. ' ' . $doiService->getError());
			}
		}

		// Proceed if no error
		if (!$this->getError())
		{
			if ($state == 1)
			{
				$model->version->curation = json_encode($this->model->_curationModel->_manifest);
			}

			// Save data
			if (!$model->version->store())
			{
				throw new Exception(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_FAILED'), 403);
				return;
			}

			// Remove main flag from previous default version
			if ($main && $main_vid && $main_vid != $model->version->id)
			{
				$model->version->removeMainFlag($main_vid);
			}

			// Mark as curated
			$model->version->saveParam($model->version->id, 'curated', 1);
		}

		// OnAfterPublish
		$this->onAfterChangeState( $model, $model->version, $originalStatus );

		// Redirect
		$this->_referer = $url;
		return;
	}

	/**
	 * On after change status
	 *
	 * @return     string
	 */
	public function onAfterChangeState( $pub, $row, $originalStatus = 3 )
	{
		$state  = $row->state;
		$notify = 1; // Notify administrators/curators?

		// Log activity in curation history
		if (isset($pub->_curationModel))
		{
			$pub->_curationModel->saveHistory($pub, $this->_uid, $originalStatus, $state, 0 );
		}

		// Display status message
		switch ($state)
		{
			case 1:
			default:
				$this->_msg = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_SUCCESS_PUBLISHED');
				$action 	= $this->_task == 'republish'
							? Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_REPUBLISHED')
							: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_PUBLISHED');
				break;

			case 4:
				$this->_msg = $this->_task == 'revert'
							? Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_SUCCESS_REVERTED')
							: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_SUCCESS_SAVED') ;
				$action 	= $this->_task == 'revert'
							? Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_REVERTED')
							: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_SAVED');
				$notify = 0;
				break;

			case 5:
				$this->_msg = $originalStatus == 7
							? Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_SUCCESS_PENDING_RESUBMITTED')
							: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_SUCCESS_PENDING');
				$action 	= $originalStatus == 7
							? Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_RESUBMITTED')
							: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_ACTIVITY_SUBMITTED');
				break;
		}
		$this->_msg .= ' <a href="'.Route::url('index.php?option=com_publications' . a .
			    'id=' . $row->publication_id ) .'">'. Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VIEWIT').'</a>';

		$pubtitle = \Hubzero\Utility\String::truncate($row->title, 100);
		$action .= ' ' . $row->version_label . ' ';
		$action .=  Lang::txt('PLG_PROJECTS_PUBLICATIONS_OF_PUBLICATION') . ' "' . html_entity_decode($pubtitle).'"';
		$action  = htmlentities($action, ENT_QUOTES, "UTF-8");

		// Record activity
		if (!$this->model->isProvisioned() && !$this->getError())
		{
			$objAA = new \Components\Projects\Tables\Activity ( $this->_database );
			$aid = $objAA->recordActivity( $this->model->get('id'), $this->_uid,
					$action, $row->publication_id, $pubtitle,
					Route::url('index.php?option=' . $this->_option . a .
					'alias=' . $this->model->get('alias') . '&active=publications' . a .
					'pid=' . $row->publication_id) . '/?version=' . $row->version_number,
					'publication', 1 );
		}

		// Send out notifications
		$profile = \Hubzero\User\Profile::getInstance($this->_uid);
		$actor 	 = $profile
				? $profile->get('name')
				: Lang::txt('PLG_PROJECTS_PUBLICATIONS_PROJECT_MEMBER');
		$juri 	 = JURI::getInstance();
		$sef	 = 'publications' . DS . $row->publication_id . DS . $row->version_number;
		$link 	 = rtrim($juri->base(), DS) . DS . trim($sef, DS);
		$message = $actor . ' ' . html_entity_decode($action) . '  - ' . $link;

		// Notify admin group
		if ($notify)
		{
			$admingroup = $this->model->config()->get('admingroup', '');
			$group = \Hubzero\User\Group::getInstance($admingroup);
			$admins = array();

			if ($admingroup && $group)
			{
				$members 	= $group->get('members');
				$managers 	= $group->get('managers');
				$admins 	= array_merge($members, $managers);
				$admins 	= array_unique($admins);

				\Components\Projects\Helpers\Html::sendHUBMessage(
					'com_projects',
					$this->model->config(),
					$this->_project,
					$admins,
					Lang::txt('COM_PROJECTS_EMAIL_ADMIN_NEW_PUB_STATUS'),
					'projects_new_project_admin',
					'publication',
					$message
				);
			}

			// Notify curators by email
			$curatorMessage = ($state == 5) ? $message . "\n" . "\n" . Lang::txt('PLG_PROJECTS_PUBLICATIONS_EMAIL_CURATORS_REVIEW') . ' ' . rtrim($juri->base(), DS) . DS . 'publications/curation' : $message;

			$curatorgroups = array($pub->_type->curatorgroup);
			if ($this->_pubconfig->get('curatorgroup', ''))
			{
				$curatorgroups[] = $this->_pubconfig->get('curatorgroup', '');
			}
			$admins = array();
			foreach ($curatorgroups as $curatorgroup)
			{
				if (trim($curatorgroup) && $group = \Hubzero\User\Group::getInstance($curatorgroup))
				{
					$members 	= $group->get('members');
					$managers 	= $group->get('managers');
					$admins 	= array_merge($members, $managers, $admins);
					$admins 	= array_unique($admins);
				}
			}
			\Components\Publications\Helpers\Html::notify(
				$this->_pubconfig,
				$pub,
				$admins,
				Lang::txt('PLG_PROJECTS_PUBLICATIONS_EMAIL_CURATORS'),
				$curatorMessage
			);
		}

		// Notify project managers (in all cases)
		$objO = new \Components\Projects\Tables\Owner($this->_database);
		$managers = $objO->getIds($this->model->get('id'), 1, 1);
		if (!$this->model->isProvisioned() && !empty($managers))
		{
			\Components\Projects\Helpers\Html::sendHUBMessage(
				'com_projects',
				$this->model->config(),
				$this->_project,
				$managers,
				Lang::txt('COM_PROJECTS_EMAIL_MANAGERS_NEW_PUB_STATUS'),
				'projects_admin_notice',
				'publication',
				$message
			);
		}

		// Produce archival package
		if ($state == 1 || $state == 5)
		{
			$pub->_curationModel->package();
		}

		// Pass success or error message
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
		}
		elseif (isset($this->_msg) && $this->_msg)
		{
			$this->_message = array('message' => $this->_msg, 'type' => 'success');
		}

		return;
	}

	/**
	 * Parse embargo date
	 *
	 * @return     string
	 */
	public function parseDate( $pubdate )
	{
		$date = explode('-', $pubdate);
		if (count($date) == 3)
		{
			$year 	= $date[0];
			$month 	= $date[1];
			$day 	= $date[2];
			if (intval($month) && intval($day) && intval($year))
			{
				if (strlen($day) == 1)
				{
					$day = '0' . $day;
				}

				if (strlen($month) == 1)
				{
					$month = '0' . $month;
				}
				if (checkdate($month, $day, $year))
				{
					$pubdate = JFactory::getDate(gmmktime(0, 0, 0, $month, $day, $year))->toSql();
				}
				// Prevent date before current
				if ($pubdate < Date::toSql())
				{
					$pubdate = Date::toSql();
				}
			}
		}

		return $pubdate;
	}

	/**
	 * Unpublish version/delete draft
	 *
	 * @return     string
	 */
	public function cancelDraft()
	{
		// Incoming
		$pid 		= $this->_pid ? $this->_pid : Request::getInt('pid', 0);
		$confirm 	= Request::getInt('confirm', 0);
		$version 	= Request::getVar('version', 'default');
		$ajax 		= Request::getInt('ajax', 0);

		// Determine redirect path
		$route = $this->model->isProvisioned()
			? 'index.php?option=com_publications&task=submit'
			: 'index.php?option=com_projects&alias=' . $this->model->get('alias') . '&active=publications';
		$url = Route::url($route . '&pid=' . $pid);

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );

		// If publication not found, raise error
		$pub = $objP->getPublication($pid, $version, $this->model->get('id'));
		if (!$pub)
		{
			if ($pid)
			{
				$this->_referer = $url;
				return;
			}
			else
			{
				throw new Exception(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'), 404);
				return;
			}
		}

		// Instantiate publication version
		$row = new \Components\Publications\Tables\Version( $this->_database );
		if (!$row->loadVersion($pid, $version))
		{
			throw new Exception(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VERSION_NOT_FOUND'), 404);
			return;
		}

		// Save version ID
		$vid = $row->id;

		// Append breadcrumbs
		if (!$ajax)
		{
			Pathway::append(
				stripslashes($pub->title),
				$url
			);
		}

		// Can only unpublish published version or delete a draft
		if ($pub->state != 1 && $pub->state != 3 && $pub->state != 4)
		{
			$this->setError(Lang::txt('PLG_PROJECTS_PUBLICATIONS_CANT_DELETE'));
		}

		// Get published versions count
		$objV = new \Components\Publications\Tables\Version( $this->_database );
		$publishedCount = $objV->getPublishedCount($pid);

		// Unpublish/delete version
		if ($confirm)
		{
			if (!$this->getError())
			{
				$pubtitle = \Hubzero\Utility\String::truncate($row->title, 100);
				$objAA = new \Components\Projects\Tables\Activity ( $this->_database );

				if ($pub->state == 1)
				{
					// Unpublish published version
					$row->published_down 	= Date::toSql();
					$row->modified 			= Date::toSql();
					$row->modified_by 		= $this->_uid;
					$row->state 			= 0;

					if (!$row->store())
					{
						throw new Exception( Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_UNPUBLISH_FAILED'), 403);
						return;
					}
					else
					{
						$this->_msg = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_VERSION_UNPUBLISHED');

						// Add activity
						$action  = Lang::txt('PLG_PROJECTS_PUBLICATIONS_ACTIVITY_UNPUBLISHED');
						$action .= ' '.strtolower(Lang::txt('version')).' '.$row->version_label.' '
						.Lang::txt('PLG_PROJECTS_PUBLICATIONS_OF').' '.strtolower(Lang::txt('publication')).' "'
						.$pubtitle.'" ';

						$aid = $objAA->recordActivity( $this->model->get('id'), $this->_uid,
							   $action, $pid, $pubtitle,
							   Route::url('index.php?option=' . $this->_option . a .
							   'alias=' . $this->model->get('alias') . '&active=publications' . a .
							   'pid=' . $pid) . '/?version=' . $row->version_number, 'publication', 0 );
					}
				}
				elseif ($pub->state == 3 || $pub->state == 4)
				{
					$vlabel = $row->version_label;

					// Delete draft version
					if (!$row->delete())
					{
						throw new Exception( Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_DELETE_DRAFT_FAILED'), 403);
						return;
					}

					// Delete authors
					$pa = new \Components\Publications\Tables\Author( $this->_database );
					$authors = $pa->deleteAssociations($vid);

					// Delete attachments
					$pContent = new \Components\Publications\Tables\Attachment( $this->_database );
					$pContent->deleteAttachments($vid);

					// Delete screenshots
					$pScreenshot = new \Components\Publications\Tables\Screenshot( $this->_database );
					$pScreenshot->deleteScreenshots($vid);

					jimport('joomla.filesystem.file');
					jimport('joomla.filesystem.folder');

					// Build publication path
					$path    =  PATH_APP . DS . trim($this->_pubconfig->get('webpath'), DS)
							. DS .  \Hubzero\Utility\String::pad( $pid );

					// Build version path
					$vPath = $path . DS . \Hubzero\Utility\String::pad( $vid );

					// Delete all version files
					if (is_dir($vPath))
					{
						JFolder::delete($vPath);
					}

					// Delete access accosiations
					$pAccess = new \Components\Publications\Tables\Access( $this->_database );
					$pAccess->deleteGroups($vid);

					// Delete audience
					$pAudience = new \Components\Publications\Tables\Audience( $this->_database );
					$pAudience->deleteAudience($vid);

					// Delete publication existence
					if ($pub->versions == 0)
					{
						// Delete all files
						if (is_dir($path))
						{
							JFolder::delete($path);
						}

						$objP->delete($pid);
						$objP->deleteExistence($pid);
						$url  = Route::url($route);

						// Delete related publishing activity from feed
						$objAA = new \Components\Projects\Tables\Activity( $this->_database );
						$objAA->deleteActivityByReference($this->model->get('id'), $pid, 'publication');
					}

					// Add activity
					$action  = Lang::txt('PLG_PROJECTS_PUBLICATIONS_ACTIVITY_DRAFT_DELETED');
					$action .= ' '.$vlabel.' ';
					$action .=  Lang::txt('PLG_PROJECTS_PUBLICATIONS_OF_PUBLICATION').' "'.$pubtitle.'"';

					$aid = $objAA->recordActivity( $this->model->get('id'), $this->_uid,
						   $action, $pid, '', '', 'publication', 0 );

					$this->_msg = Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_DRAFT_DELETED');
				}
			}
		}
		else
		{
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'=>'projects',
					'element'=>'publications',
					'name'=>'cancel'
				)
			);

			// Output HTML
			$view->option 			= $this->_option;
			$view->database 		= $this->_database;
			$view->project 			= $this->_project;
			$view->uid 				= $this->_uid;
			$view->pid 				= $pid;
			$view->version 			= $version;
			$view->pub 				= $pub;
			$view->publishedCount 	= $publishedCount;
			$view->task 			= $this->_task;
			$view->config 			= $this->model->config();
			$view->pubconfig 		= $this->_pubconfig;
			$view->ajax 			= $ajax;
			$view->route			= $route;
			$view->url 				= $url;
			$view->title		  	= $this->_area['title'];

			// Get messages	and errors
			$view->msg = $this->_msg;
			if ($this->getError())
			{
				$view->setError( $this->getError() );
			}
			return $view->loadTemplate();
		}

		// Pass success or error message
		if ($this->getError())
		{
			$this->_message = array('message' => $this->getError(), 'type' => 'error');
		}
		elseif (isset($this->_msg) && $this->_msg)
		{
			$this->_message = array('message' => $this->_msg, 'type' => 'success');
		}

		$url.= $version != 'default' ? '?version='.$version : '';
		$this->_referer = $url;
		return;
	}

	/**
	 * Show publication versions
	 *
	 * @return     string (html)
	 */
	public function versions()
	{
		// Incoming
		$pid = $this->_pid ? $this->_pid : Request::getInt('pid', 0);

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $this->_database );
		$objV = new \Components\Publications\Tables\Version( $this->_database );

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder' =>'projects',
				'element'=>'publications',
				'name'   =>'versions'
			)
		);

		$view->pub = $objP->getPublication($pid, 'default', $this->model->get('id'));
		if (!$view->pub)
		{
			throw new Exception(Lang::txt('PLG_PROJECTS_PUBLICATIONS_PUBLICATION_NOT_FOUND'), 404);
			return;
		}

		// Build pub url
		$view->route = $this->model->isProvisioned()
			? 'index.php?option=com_publications&task=submit'
			: 'index.php?option=com_projects&alias=' . $this->model->get('alias') . '&active=publications';
		$view->url = Route::url($view->route . '&pid=' . $pid);

		// Append breadcrumbs
		Pathway::append(
			stripslashes($view->pub->title),
			$view->url
		);

		// Get versions
		$view->versions = $objV->getVersions( $pid, $filters = array('withdev' => 1));

		// Output HTML
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->project 		= $this->_project;
		$view->uid 			= $this->_uid;
		$view->pid 			= $pid;
		$view->task 		= $this->_task;
		$view->config 		= $this->model->config();
		$view->pubconfig 	= $this->_pubconfig;
		$view->title		= $this->_area['title'];

		// Get messages	and errors
		$view->msg = $this->_msg;
		if ($this->getError())
		{
			$view->setError( $this->getError() );
		}
		return $view->loadTemplate();
	}

	/**
	 * Contribute from outside projects
	 *
	 * @return     string (html)
	 */
	public function contribute()
	{
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  =>'projects',
				'element' =>'publications',
				'name'    =>'browse',
				'layout'  =>'intro'
			)
		);
		$view->option  		= $this->_option;
		$view->pubconfig 	= $this->_pubconfig;
		$view->outside  	= 1;
		$view->juser   		= User::getRoot();
		$view->uid 			= $this->_uid;
		$view->database		= $this->_database;

		// Get publications
		if (!User::isGuest())
		{
			$view->filters = array();

			// Get user projects
			$obj = new \Components\Projects\Tables\Project( $this->_database );
			$view->filters['projects']  = $obj->getUserProjectIds(User::get('id'), 0, 1);

			$view->filters['mine']		= User::get('id');
			$view->filters['dev']		= 1;
			$view->filters['sortby']	= 'mine';
			$view->filters['limit']  	= Request::getInt( 'limit', 3 );

			// Get publications created by user
			$objP = new \Components\Publications\Tables\Publication( $this->_database );
			$view->mypubs = $objP->getRecords( $view->filters );

			// Get pub count
			$view->mypubs_count = $objP->getCount( $view->filters );

			// Get other pubs that user can manage
			$view->filters['coauthor'] = 1;
			$view->coauthored = $objP->getRecords( $view->filters );
			$view->coauthored_count = $objP->getCount( $view->filters );
		}

		return $view->loadTemplate();
	}

	/**
	 * Get member path
	 *
	 * @return     string
	 */
	protected function _getMemberPath()
	{
		// Get members config
		$mconfig = Component::params( 'com_members' );

		// Build upload path
		$dir  = \Hubzero\Utility\String::pad( $this->_uid );
		$path = DS . trim($mconfig->get('webpath', '/site/members'), DS) . DS . $dir . DS . 'files';

		if (!is_dir( PATH_APP . $path ))
		{
			if (!$this->fileSystem->makeDirectory( PATH_APP . $path ))
			{
				$this->setError(\Lang::txt('UNABLE_TO_CREATE_UPLOAD_PATH'));
				return;
			}
		}

		return PATH_APP . $path;
	}

	/**
	 * Prep file directory (provisioned project)
	 *
	 * @param      boolean		$force
	 *
	 * @return     boolean
	 */
	protected function _prepDir($force = true)
	{
		if (!$this->model->exists())
		{
			$this->setError( Lang::txt('UNABLE_TO_CREATE_UPLOAD_PATH') );
			return;
		}

		// Get member files path
		$memberPath = $this->_getMemberPath();

		// Create and initialize local repo
		if (!$this->model->repo()->iniLocal())
		{
			$this->setError( Lang::txt('UNABLE_TO_CREATE_UPLOAD_PATH') );
			return;
		}

		// Copy files from member directory
		if (!$this->fileSystem->copyDirectory($memberPath, $this->model->repo()->get('path')))
		{
			$this->setError( Lang::txt('COM_PROJECTS_FAILED_TO_COPY_FILES') );
			return false;
		}

		// Read copied files
		$get = $this->fileSystem->files($this->model->repo()->get('path'));

		$num = count($get);
		$checkedin = 0;

		// Check-in copied files
		if ($get)
		{
			foreach ($get as $file)
			{
				$file = str_replace($this->model->repo()->get('path') . DS, '', $file);
				if (is_file($this->model->repo()->get('path') . DS . $file))
				{
					// Checkin into repo
					$this->model->repo()->call('checkin', array(
						'file'   => $this->model->repo()->getMetadata($filename, 'file', array())
						)
					);
					$checkedin++;
				}
			}
		}
		if ($num == $checkedin)
		{
			// Clean up member files
			$this->fileSystem->deleteDirectory($memberPath);
			return true;
		}

		return false;
	}

	/**
	 * Get disk space
	 *
	 * @param      string	$option
	 * @param      object  	$project
	 * @param      string  	$case
	 * @param      integer  $by
	 * @param      string  	$action
	 * @param      object 	$config
	 * @param      string  	$app
	 *
	 * @return     string
	 */
	public function pubDiskSpace($model)
	{
		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  =>'projects',
				'element' =>'publications',
				'name'    =>'diskspace'
			)
		);

		// Include styling and js
		\Hubzero\Document\Assets::addPluginStylesheet('projects', 'files','css/diskspace');
		\Hubzero\Document\Assets::addPluginScript('projects', 'files','js/diskspace');

		$database = JFactory::getDBO();

		// Build query
		$filters = array();
		$filters['limit'] 	 		= Request::getInt('limit', 25);
		$filters['start'] 	 		= Request::getInt('limitstart', 0);
		$filters['sortby']   		= Request::getVar( 't_sortby', 'title');
		$filters['sortdir']  		= Request::getVar( 't_sortdir', 'ASC');
		$filters['project']  		= $model->get('id');
		$filters['ignore_access']   = 1;
		$filters['dev']   	 		= 1; // get dev versions

		// Instantiate project publication
		$objP = new \Components\Publications\Tables\Publication( $database );

		// Get all publications
		$view->rows = $objP->getRecords($filters);

		// Get used space
		$view->dirsize = \Components\Publications\Helpers\Html::getDiskUsage($view->rows);
		$view->params  = $model->params;
		$view->quota   = $view->params->get('pubQuota')
						? $view->params->get('pubQuota')
						: \Components\Projects\Helpers\Html::convertSize(floatval($model->config()->get('pubQuota', '1')), 'GB', 'b');

		// Get total count
		$view->total = $objP->getCount($filters);

		$view->project 	= $model;
		$view->option 	= $this->_option;
		$view->title	= isset($this->_area['title']) ? $this->_area['title'] : '';

		return $view->loadTemplate();
	}

	/**
	 * Serve publication-related file (via public link)
	 *
	 * @param   int  	$projectid
	 * @return  void
	 */
	public function serve( $type = '', $projectid = 0, $query = '')
	{
		$this->_area = $this->onProjectAreas();
		if ($type != $this->_area['name'])
		{
			return false;
		}
		$data = json_decode($query);

		if (!isset($data->pid) || !$projectid)
		{
			return false;
		}

		$disp 	 = isset($data->disp) ? $data->disp : 'inline';
		$type 	 = isset($data->type) ? $data->type : 'file';
		$folder  = isset($data->folder) ? $data->folder : 'wikicontent';
		$fpath	 = isset($data->path) ? $data->path : 'inline';
		$limited = isset($data->limited) ? $data->limited : 0;

		if ($type != 'file')
		{
			return false;
		}

		$database = JFactory::getDBO();

		// Instantiate a project
		$model = new \Components\Projects\Models\Project($projectid);

		if (!$model->exists() || ($limited == 1 && !$model->access('member')))
		{
			// Throw error
			throw new Exception(Lang::txt('COM_PROJECTS_ERROR_ACTION_NOT_AUTHORIZED'), 403);
			return;
		}

		// Get referenced path
		$pubconfig = Component::params( 'com_publications' );
		$base_path = $pubconfig->get('webpath');
		$pubPath = \Components\Publications\Helpers\Html::buildPubPath($data->pid, $data->vid, $base_path, $folder, $root = 0);

		$serve = PATH_APP . $pubPath . DS . $fpath;

		// Ensure the file exist
		if (!file_exists($serve))
		{
			// Throw error
			throw new Exception(Lang::txt('COM_PROJECTS_FILE_NOT_FOUND'), 404);
			return;
		}

		// Initiate a new content server and serve up the file
		$xserver = new \Hubzero\Content\Server();
		$xserver->filename($serve);
		$xserver->disposition($disp);
		$xserver->acceptranges(false); // @TODO fix byte range support
		$xserver->saveas(basename($fpath));

		if (!$xserver->serve())
		{
			// Should only get here on error
			throw new Exception(Lang::txt('COM_PUBLICATIONS_SERVER_ERROR'), 404);
		}
		else
		{
			exit;
		}

		return;
	}
}
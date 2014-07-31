<?php
/**
 * @package		Joomla.Site
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// Set flag that this is a parent file.
define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);

if (file_exists(dirname(__FILE__) . '/defines.php')) {
	include_once dirname(__FILE__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
	define('JPATH_BASE', dirname(__FILE__));
	require_once JPATH_BASE.'/includes/defines.php';
}

require_once JPATH_BASE.'/includes/framework.php';

JLoader::import('Hubzero.Factory');

// Mark afterLoad in the profiler.
JPROFILE ? $_PROFILER->mark('afterLoad') : null;

// Instantiate the application.
$app = JFactory::getApplication('site');

// Initialise the application.
$app->initialise();

// Mark afterIntialise in the profiler.
JPROFILE ? $_PROFILER->mark('afterInitialise') : null;

// Route the application.
$app->route();

// Mark afterRoute in the profiler.
JPROFILE ? $_PROFILER->mark('afterRoute') : null;

// Dispatch the application.
$app->dispatch();

// Mark afterDispatch in the profiler.
JPROFILE ? $_PROFILER->mark('afterDispatch') : null;

// Render the application.
$app->render();

// Mark afterRender in the profiler.
JPROFILE ? $_PROFILER->mark('afterRender') : null;

// Return the response.
echo $app;

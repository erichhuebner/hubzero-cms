<?php
// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

//----------------------------------------------------------

$config = JFactory::getConfig();

//if ($config->getValue('config.debug')) {
	error_reporting(E_ALL);
	@ini_set('display_errors','1');
//}

jimport('joomla.application.component.helper');
jimport('joomla.application.component.view');
jimport('joomla.application.component.controller');

require_once( JPATH_COMPONENT.DS.'livefeed.html.php' );
require_once( JPATH_COMPONENT.DS.'controller.php' );

// Instantiate controller
$controller = new LivefeedController();
$controller->mainframe = $mainframe;
$controller->execute();
$controller->redirect();
?>

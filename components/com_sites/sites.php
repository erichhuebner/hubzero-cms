<?php
/**
 * Bootstrap for the facility component. It all begins here. 
 * 
 * @package		NEEShub 
 * @author		David Benham (dbenha@purdue.edu)
 * @copyright	Copyright 2010 by NEESCommIT
 */
set_include_path("/usr/local/propel/runtime/classes" . PATH_SEPARATOR . get_include_path());
set_include_path("api/org/phpdb/propel/central/classes" . PATH_SEPARATOR . get_include_path());
set_include_path("api/org/nees" . PATH_SEPARATOR . get_include_path());

spl_autoload_register('__autoload');
require_once 'propel/Propel.php';
Propel::init("api/org/phpdb/propel/central/conf/central-conf.php");


require_once 'lib/data/EquipmentPeer.php';
require_once 'lib/data/FacilityPeer.php';
require_once 'lib/data/FacilityDataFilePeer.php';
require_once 'lib/data/Facility.php';
require_once 'lib/security/Authorizer.php';
require_once 'lib/security/PermissionsViewPeer.php';
require_once 'lib/security/Permissions.php';
require_once 'lib/data/PersonEntityRolePeer.php';
require_once 'lib/data/PersonPeer.php';
require_once 'lib/data/Role.php';
require_once 'lib/data/SensorPeer.php';
require_once 'lib/util/DataFileBrowserSimple.php';
require_once 'lib/util/DomainEntityType.php';

require_once 'components/com_sites/lib/siteshelper.php';



// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
 
// Require the base controller
require_once( JPATH_COMPONENT.DS.'controller.php' );

$doc =& JFactory::getDocument();
$doc->addStyleSheet( 'components/com_sites/css/sites.css' );

// Root breadkcrumb entry
$mainframe = &JFactory::getApplication();
$document  = &JFactory::getDocument();          
$pathway   =& $mainframe->getPathway();

// Joomla sets the breadcrumb when selecting our component from the menu... but we need to manage the 
// breadcrumb ourselves, so we need to remove this to make things work. Without this, we get doubled 
// root entries when selecting the facilities from the menu
unset($pathway->_pathway[0]);

// Add our 'Root' facilities homepage
$pathway->addItem( "NEES Sites", "/sites" );

$controller   = new SitesController();
 
// Perform the Request task
$controller->execute(JRequest::getCmd('task'));
 
// Redirect if set by the controller
$controller->redirect();

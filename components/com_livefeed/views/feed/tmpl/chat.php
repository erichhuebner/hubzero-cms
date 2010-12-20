<!-- Chat view PHP file-->
<?php defined('_JEXEC') or die('Restricted access'); ?>
<?php $user = $this->user?>
<?php $area = $this->area?>
<!--
//
// Flash Core Functions
//
//-->
<div style="height:600px;">
<script src="/webapps/hubcaster/AC_OETags.js" language="javascript"></script>
<script type="text/javascript">
// -----------------------------------------------------------------------------
// Globals For Hub Comms
// -----------------------------------------------------------------------------

var hubUserName = "<?php echo $user->name?>";
var hubAreaName = "<?php echo $area['name']?>";
var hubAreaUsers = <?php echo $area['users']?>;
var hubAllowVoice = <?php if (JRequest::getVar( 'voice', '' ) == "true") echo 'true'; else echo 'false';?>;
var hubAllowCam = <?php if (JRequest::getVar( 'camera', '' ) == "true") echo 'true'; else echo 'false';?>;

function GetHubArea()
    {   
        return hubAreaName;
    }
function GetHubUserName()
    {
    	return hubUserName;
    }
function GetAreaUsers()
    {
	    return hubAreaUsers;
    }
function GetVoiceAllowed()
{
	return hubAllowVoice;
}
function GetCamera()
{
	return hubAllowCam;
}

// -----------------------------------------------------------------------------
// Globals
// Major version of Flash required
var requiredMajorVersion = 10;
// Minor version of Flash required
var requiredMinorVersion = 0;
// Minor version of Flash required
var requiredRevision = 0;
// -----------------------------------------------------------------------------
//
</script>
<!--
//
// Flash Embed
//
// -->
</script>
<script language="JavaScript" type="text/javascript">


// Version check for the Flash Player that has the ability to start Player Product Install (6.0r65)
var hasProductInstall = DetectFlashVer(6, 0, 65);

// Version check based upon the values defined in globals
var hasRequestedVersion = DetectFlashVer(requiredMajorVersion, requiredMinorVersion, requiredRevision);


// Check to see if a player with Flash Product Install is available and the version does not meet the requirements for playback
if ( hasProductInstall && !hasRequestedVersion ) {
	// MMdoctitle is the stored document.title value used by the installation process to close the window that started the process
	// This is necessary in order to close browser windows that are still utilizing the older version of the player after installation has completed
	// DO NOT MODIFY THE FOLLOWING FOUR LINES
	// Location visited after installation is complete if installation is required
	var MMPlayerType = (isIE == true) ? "ActiveX" : "PlugIn";
	var MMredirectURL = window.location;
    document.title = document.title.slice(0, 47) + " - Flash Player Installation";
    var MMdoctitle = document.title;

	AC_FL_RunContent(
		"src", "/webapps/hubcaster/playerProductInstall",
		"FlashVars", "MMredirectURL="+MMredirectURL+'&MMplayerType='+MMPlayerType+'&MMdoctitle='+MMdoctitle+"",
		"width", "100%",
		"height", "100%",
		"align", "middle",
		"id", "SmartVideoConference",
		"quality", "high",
		"bgcolor", "#869ca7",
		"wmode", "transparent",
		"name", "SmartVideoConference",
		"allowFullScreen","true",
		"allowScriptAccess","sameDomain",
		"type", "application/x-shockwave-flash",
		"pluginspage", "http://www.adobe.com/go/getflashplayer"
	);
} else if (hasRequestedVersion) {
	// if we've detected an acceptable version
	// embed the Flash Content SWF when all tests are passed
	AC_FL_RunContent(
			"src", "/webapps/hubcaster/SmartVideoConference",
			"width", "100%",
			"height", "100%",
			"align", "middle",
			"id", "SmartVideoConference",
			"quality", "high",
			"wmode", "transparent",
			"bgcolor", "#869ca7",
			"name", "SmartVideoConference",
			"allowFullScreen","true",
			"allowScriptAccess","always",
			"type", "application/x-shockwave-flash",
			"pluginspage", "http://www.adobe.com/go/getflashplayer"
	);
  } else {  // flash is too old or we can't detect the plugin
    var alternateContent = 'Alternate HTML content should be placed here. '
  	+ 'This content requires the Adobe Flash Player. '
   	+ '<a href=http://www.adobe.com/go/getflash/>Get Flash</a>';
    document.write(alternateContent);  // insert non-flash content
  }
</script>
<noscript>
  	<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
			id="SmartVideoConference" width="100%" height="100%"
			codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
			<param name="movie" value="/webapps/hubcaster/SmartVideoConference.swf" />
			<param name="quality" value="high" />
			<param name="bgcolor" value="#869ca7" />
			<param name="allowScriptAccess" value="sameDomain" />
			<param name="allowFullScreen" value="true" />
			<param name="wmode" value="transparent" />
			<embed src="SmartVideoConference.swf" quality="high" bgcolor="#869ca7"
				width="100%" height="100%" name="SmartVideoConference" align="middle"
				play="true"
				loop="false"
				quality="high"
				allowScriptAccess="sameDomain"
				type="application/x-shockwave-flash"
				allowFullScreen="true" 
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed>
	</object>
</noscript>
</div>
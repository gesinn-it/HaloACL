<?php
/*
 * Copyright (C) Vulcan Inc., DIQA-Projektmanagement GmbH
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup HaloACL
 *
 * This file contains global functions that are called from the Halo-Access-Control-List
 * extension.
 *
 * @author Thomas Schweitzer
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the HaloACL extension. It is not a valid entry point.\n" );
}

$haclgDoEnableTitleCheck = haclfDisableTitlePatch();


/**
 * Switch on Halo Access Control Lists. This function must be called in
 * LocalSettings.php after HACL_Initialize.php was included and default values
 * that are defined there have been modified.
 * For readability, this is the only global function that does not adhere to the
 * naming conventions.
 *
 * This function installs the extension, sets up all autoloading, special pages
 * etc.
 */
function enableHaloACL() {
	global $haclgIP, $wgExtensionFunctions, $wgAutoloadClasses, $wgSpecialPages, $wgSpecialPageGroups, $wgHooks, $wgExtensionMessagesFiles, $wgJobClasses, $wgExtensionAliasesFiles;

	require_once("$haclgIP/includes/HACL_ParserFunctions.php");

	$wgExtensionFunctions[] = 'haclfSetupExtension';
	$wgHooks['LanguageGetMagic'][] = 'haclfAddMagicWords'; // setup names for parser functions (needed here)
	$wgExtensionMessagesFiles['HaloACL'] = $haclgIP . '/languages/HACL_Messages.php'; // register messages (requires MW=>1.11)

	// Register special pages aliases file
	$wgExtensionAliasesFiles['HaloACL'] = $haclgIP . '/languages/HACL_Aliases.php';

	///// Set up autoloading; essentially all classes should be autoloaded!
	$wgAutoloadClasses['HACLEvaluator'] = $haclgIP . '/includes/HACL_Evaluator.php';
	$wgAutoloadClasses['HaloACLSpecial'] = $haclgIP . '/specials/HACL_ACLSpecial.php';
	$wgAutoloadClasses['HACLStorage'] = $haclgIP . '/includes/HACL_Storage.php';
	if (defined('SMW_VERSION')) {
		$wgAutoloadClasses['HACLSMWStore'] = $haclgIP . '/includes/HACL_SMWStore.php';
	}
	$wgAutoloadClasses['HACLGroup'] = $haclgIP . '/includes/HACL_Group.php';
	$wgAutoloadClasses['HACLDynamicMemberCache'] = $haclgIP . '/includes/HACL_DynamicMemberCache.php';
	$wgAutoloadClasses['HACLSecurityDescriptor'] = $haclgIP . '/includes/HACL_SecurityDescriptor.php';
	$wgAutoloadClasses['HACLRight'] = $haclgIP . '/includes/HACL_Right.php';
	$wgAutoloadClasses['HACLWhitelist'] = $haclgIP . '/includes/HACL_Whitelist.php';
	$wgAutoloadClasses['HACLDefaultSD'] = $haclgIP . '/includes/HACL_DefaultSD.php';
	$wgAutoloadClasses['HACLResultFilter'] = $haclgIP . '/includes/HACL_ResultFilter.php';
	$wgAutoloadClasses['HACLQueryRewriter'] = $haclgIP . '/includes/HACL_QueryRewriter.php';
	$wgAutoloadClasses['HACLQuickacl'] = $haclgIP . '/includes/HACL_Quickacl.php';
	$wgAutoloadClasses['HACLLanguageEn'] = $haclgIP . '/languages/HACL_LanguageEn.php';
	$wgAutoloadClasses['HACLGroupPermissions'] = $haclgIP . '/includes/HACL_GroupPermissions.php';
	$wgAutoloadClasses['HACLMemcache'] = $haclgIP . '/includes/HACL_Memcache.php';

	// UI
	$wgAutoloadClasses['HACL_GenericPanel'] = $haclgIP . '/includes/HACL_GenericPanel.php';
	$wgAutoloadClasses['HACL_helpPopup'] = $haclgIP . '/includes/HACL_helpPopup.php';
	$wgAutoloadClasses['HACLUIGroupPermissions'] = $haclgIP . '/includes/UI/HACL_UIGroupPermissions.php';

	//--- Autoloading for exception classes ---
	$wgAutoloadClasses['HACLException']        = $haclgIP . '/exceptions/HACL_Exception.php';
	$wgAutoloadClasses['HACLStorageException'] = $haclgIP . '/exceptions/HACL_StorageException.php';
	$wgAutoloadClasses['HACLGroupException']   = $haclgIP . '/exceptions/HACL_GroupException.php';
	$wgAutoloadClasses['HACLSDException']      = $haclgIP . '/exceptions/HACL_SDException.php';
	$wgAutoloadClasses['HACLRightException']   = $haclgIP . '/exceptions/HACL_RightException.php';
	$wgAutoloadClasses['HACLWhitelistException'] = $haclgIP . '/exceptions/HACL_WhitelistException.php';
	$wgAutoloadClasses['HACLGroupPermissionsException'] = $haclgIP . '/exceptions/HACL_GroupPermissionException.php';

	return true;
}

/**
 * Do the actual initialisation of the extension. This is just a delayed init that
 * makes sure MediaWiki is set up properly before we add our stuff.
 *
 * The main things this function does are: register all hooks, set up extension
 * credits, and init some globals that are not for configuration settings.
 */
function haclfSetupExtension() {
	wfProfileIn('haclfSetupExtension');
	global $haclgIP, $wgHooks, $wgParser, $wgExtensionCredits,
	$wgLanguageCode, $wgVersion, $wgRequest, $wgContLang;

	// Initialize group permissions
	global $haclgUseFeaturesForGroupPermissions;
	if ($haclgUseFeaturesForGroupPermissions === true) {
		HACLGroupPermissions::initDefaultPermissions();
		HACLGroupPermissions::initPermissionsFromDB();
	}

	haclfInitSemanticStores();

	global $haclgDoEnableTitleCheck;
	haclfRestoreTitlePatch($haclgDoEnableTitleCheck);

	//--- Register hooks ---
	global $wgHooks;
	$wgHooks['userCan'][] = 'HACLEvaluator::userCan';
	$wgHooks['TitleReadWhitelist'][] = 'HACLEvaluator::whitelist';

	//wfLoadExtensionMessages('HaloACL');
	///// Register specials pages
	global $wgSpecialPages, $wgSpecialPageGroups;
	$wgSpecialPages['HaloACL']      = array('HaloACLSpecial');
	$wgSpecialPageGroups['HaloACL'] = 'hacl_group';

	$wgHooks['ArticleSaveComplete'][]  = 'HACLParserFunctions::articleSaveComplete';
	$wgHooks['ArticleSaveComplete'][]  = 'HACLDefaultSD::articleSaveComplete';
	$wgHooks['ArticleDelete'][]        = 'HACLParserFunctions::articleDelete';
	$wgHooks['OutputPageBeforeHTML'][] = 'HACLParserFunctions::outputPageBeforeHTML';
	$wgHooks['IsFileCacheable'][]      = 'haclfIsFileCacheable';
	$wgHooks['PageRenderingHash'][]    = 'haclfPageRenderingHash';
	$wgHooks['TitleMoveComplete'][]	   = 'HACLParserFunctions::articleMove';
	$wgHooks['SkinTemplateContentActions'][] = 'haclfRemoveProtectTab';
	$wgHooks['UserEffectiveGroups'][]  = 'HACLGroupPermissions::onUserEffectiveGroups';
	$wgHooks['BeforeParserFetchTemplateAndtitle'][] = 'HACLEvaluator::onBeforeParserFetchTemplateAndtitle';


	$wgHooks['FilterQueryResults'][] = 'HACLResultFilter::filterResult';


	global $haclgProtectProperties;
	if ($haclgProtectProperties === true) {
		$wgHooks['RewriteQuery'][]       = 'HACLQueryRewriter::rewriteQuery';
		//$wgHooks['DiffViewHeader'][]     = 'HACLEvaluator::onDiffViewHeader';
		$wgHooks['EditFilter'][]         = 'HACLEvaluator::onEditFilter';
		$wgHooks['PropertyBeforeOutput'][] = 'HACLEvaluator::onPropertyBeforeOutput';
		$wgHooks['BeforeDerivedPropertyQuery'][] = 'haclfAllowVariableForPredicate';
		$wgHooks['AfterDerivedPropertyQuery'][] = 'haclfDisallowVariableForPredicate';

	}
	
	// Setup memcache hooks
	HACLMemcache::setupHooks();

	global $haclgNewUserTemplate, $haclgDefaultQuickAccessRights;
	if (isset($haclgNewUserTemplate) ||
	isset($haclgDefaultQuickAccessRightMasterTemplates)) {
		$wgHooks['UserLoginComplete'][] = 'HACLDefaultSD::newUser';
	}

	#	$wgHooks['InternalParseBeforeLinks'][] = 'SMWParserExtensions::onInternalParseBeforeLinks'; // parse annotations in [[link syntax]]

	/*
	 if( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
		$wgHooks['ParserFirstCallInit'][] = 'SMWParserExtensions::registerParserFunctions';
		} else {
		if ( class_exists( 'StubObject' ) && !StubObject::isRealObject( $wgParser ) ) {
		$wgParser->_unstub();
		}
		SMWParserExtensions::registerParserFunctions( $wgParser );
		}
		*/

	haclfSetupScriptAndStyleModule();
	$spns_text = $wgContLang->getNsText(NS_SPECIAL);
	// register AddHTMLHeader functions for special pages
	// to include javascript and css files (only on special page requests).
	global $wgCommandLineMode;
	if ((!$wgCommandLineMode && stripos($wgRequest->getRequestURL(), $spns_text.":HaloACL") !== false)
	|| (!$wgCommandLineMode && stripos($wgRequest->getRequestURL(), $spns_text."%3AHaloACL") !== false)) {
		$wgHooks['BeforePageDisplay'][]='haclAddHTMLHeader';
		global $wgOut;
		$wgOut->addModules('ext.HaloACL.SpecialPage');
	} else {
		$wgHooks['BeforePageDisplay'][]='addNonSpecialPageHeader';
	}

	//-- Hooks for ACL toolbar--
	//	$wgHooks['EditPageBeforeEditButtons'][] = 'haclfAddToolbarForEditPage';
	$wgHooks['EditPage::showEditForm:fields'][] = 'haclfAddToolbarForEditPage';
	$wgHooks['sfHTMLBeforeForm'][]     		= 'haclfAddToolbarForSemanticForms';
	$wgHooks['sfSetTargetName'][]           = 'haclfOnSfSetTargetName';
	$wgHooks['sfUserCanEditPage'][]         = 'HACLEvaluator::onSfUserCanEditPage';


	//-- includes for Ajax calls --
	global $wgUseAjax, $wgRequest;
	if ($wgUseAjax && $wgRequest->getVal('action') == 'ajax' ) {
		$funcName = isset( $_POST["rs"] )
		? $_POST["rs"]
		: (isset( $_GET["rs"] ) ? $_GET["rs"] : NULL);
		if (strpos($funcName, 'hacl') === 0) {
			require_once('HACL_Toolbar.php');
			require_once('HACL_AjaxConnector.php');
			require_once('HACL_AjaxAccessRights.php');
		}
	}

	//--- credits (see "Special:Version") ---
	$wgExtensionCredits['other'][]= array(
        'name'=>'HaloACL',
        'version'=>HACL_HALOACL_VERSION,
        'author'=>"Authors: W.Breiter (KIT), [http://diqa-pm.com DIQA GmbH], ontoprise GmbH.", 
        'url'=>'http://www.mediawiki.org/wiki/Extension:Access_Control_List',
        'description' => 'Protect the content of your wiki.');

	// Register autocompletion icon
	$wgHooks['smwhACNamespaceMappings'][] = 'haclfRegisterACIcon';


	// Handle input fields of Semantic Forms
	$wgHooks['sfCreateFormField'][] = 'haclfHandleFormField';

	wfProfileOut('haclfSetupExtension');
	return true;
}

/**
 * Creates a module for the resource loader that contains all scripts and styles
 * that are needed for this extension.
 */
function haclfSetupScriptAndStyleModule() {
	global $wgResourceModules;

	$wgResourceModules['ext.HaloACL.yui'] = array(
	// JavaScript and CSS styles. To combine multiple file, just list them as an array.
		'scripts' => array(
	       '/yui/yahoo-min.js',
		   '/yui/yahoo-min.binding.js',
	       '/yui/yuiloader-min.js',
	       '/yui/event-min.js',
	       '/yui/dom-min.js',
	       '/yui/treeview-min.js',
	       '/yui/logger-min.js',
	       '/yui/element-min.js',
	       '/yui/button-min.js',
	       '/yui/connection-min.js',
	       '/yui/json-min.js',
	       '/yui/yahoo-dom-event.js',
	       '/yui/animation-min.js',
	       '/yui/tabview-min.js',
	       '/yui/datasource-min.js',
	       '/yui/datatable-min.js',
	       '/yui/paginator-min.js',
	       '/yui/container-min.js',
	       '/yui/dragdrop-min.js',
	       '/yui/autocomplete-min.js'
	       ),
	       	
		'styles' => array(
            '/yui/container.css',
            '/yui/autocomplete.css'
            ),

		'localBasePath' => dirname(__FILE__).'/../',
		'remoteExtPath' => 'HaloACL'
		);

		$wgResourceModules['ext.prototype'] = array(
		// JavaScript and CSS styles. To combine multiple file, just list them as an array.
		'scripts' => array(
			"scripts/prototype.js",
			"scripts/prototype.binding.js"
			
	        ),
		'styles' => array(
           
            ),

		'dependencies' => array( ),
             
		'localBasePath' => dirname(__FILE__).'/../',
		'remoteExtPath' => 'HaloACL'
		);
		
		$wgResourceModules['ext.HaloACL.SpecialPage'] = array(
		// JavaScript and CSS styles. To combine multiple file, just list them as an array.
		'scripts' => array(
			
			"scripts/haloacl.js",
			"scripts/groupuserTree.js",
			"scripts/rightsTree.js",
			"scripts/userTable.js",
			"scripts/pageTable.js",
			"scripts/manageUserTree.js",
			"scripts/whitelistTable.js",
			"scripts/autoCompleter.js",
			"scripts/notification.js",
			"scripts/quickaclTable.js",
			"scripts/jsTree.v.0.9.9a/jquery.tree.min.js",
			"scripts/HACL_GroupTree.js",
			"scripts/HACL_GroupPermission.js",
	        "scripts/HACL_GroupTree.js",
	        "scripts/HACL_GroupPermission.js"
	        ),
		'styles' => array(
            'skins/haloacl.css',
            'skins/haloacl_group_permissions.css',
            'scripts/jsTree.v.0.9.9a/themes/haloacl/style.css'
            ),

		'dependencies' => array( 'ext.prototype', 'ext.HaloACL.Language', 'ext.HaloACL.yui'),
             
		'localBasePath' => dirname(__FILE__).'/../',
		'remoteExtPath' => 'HaloACL'
		);


		$wgResourceModules['ext.HaloACL.Toolbar'] = array(
		// JavaScript and CSS styles. To combine multiple file, just list them as an array.
		'scripts' => array(
			
			"yui/yahoo-min.js",
			"yui/yahoo-min.binding.js",
			"yui/event-min.js",
        	"scripts/toolbar.js",
			"yui/yuiloader-min.js",
			"yui/event-min.js",
			"yui/dom-min.js",
			"yui/treeview-min.js",
			"yui/element-min.js",
			"yui/button-min.js",
			"yui/connection-min.js",
			"yui/json-min.js",
			"yui/yahoo-dom-event.js",
			"yui/animation-min.js",
			"yui/tabview-min.js",
			"yui/datasource-min.js",
			"yui/datatable-min.js",
			"yui/paginator-min.js",
			"yui/container-min.js",
			"yui/dragdrop-min.js",
	        "scripts/haloacl.js",
	        "scripts/groupuserTree.js",
	        "scripts/userTable.js",
	        "scripts/notification.js"
	        ),
		'styles' => array(
			'skins/haloacl.css',
            'skins/haloacl_toolbar.css',
            'yui/container.css'
            ),
		'dependencies' => array( 'ext.prototype', 'ext.HaloACL.Language' ),

		'localBasePath' => dirname(__FILE__).'/../',
		'remoteExtPath' => 'HaloACL'
		);

		//        if(get_class($wgUser->getSkin()) == "SkinMonoBook") {
		//            $out->addLink(array(
		//                'rel'   => 'stylesheet',
		//                'type'  => 'text/css',
		//                'media' => 'screen, projection',
		//                'href'  => $haclgHaloScriptPath . '/skins/mono-fix.css'
		//            ));
		//        }

		haclAddJSLanguageScripts();

}

/**
 *  adding headers for non-special-pages
 *  atm only used for toolbar-realted stuff
 *
 * @global <type> $haclgHaloScriptPath
 * @param <type> $out
 * @return <type>
 */
function addNonSpecialPageHeader(&$out) {
	global $wgRequest, $wgContLang, $wgOut;
	// scripts are needed at Special:FormEdit
	$spns_text = $wgContLang->getNsText(NS_SPECIAL);
	if ( ($wgRequest->getText('action', 'view') == 'view')
	&& stripos($wgRequest->getRequestURL(), $spns_text.":FormEdit") == false
	&& stripos($wgRequest->getRequestURL(), $spns_text."%3AFormEdit") == false ) {
		return true;
	}

	$wgOut->addModules('ext.HaloACL.Toolbar');
	return true;
}

/**
 * Adds Javascript and CSS files
 *
 * @param OutputPage $out
 * @return true
 */
function haclAddHTMLHeader(&$out) {

	global $wgTitle,$wgUser;

	global $haclgHaloScriptPath, $smwgDeployVersion;

	if ($wgTitle->getNamespace() != NS_SPECIAL) {
		return true;
	} else {
		// Add global JS variables
		global $haclgAllowLDAPGroupMembers;
		$globalJSVar = "var haclgAllowLDAPGroupMembers = "
		. (($haclgAllowLDAPGroupMembers == true) ? 'true' : 'false')
		.';';
		 
		$out->addHeadItem('HaloACLGlobalVar',"\n".'<script type="text/javascript">'.$globalJSVar.'</script>'."\n");
		 
		// ---- SPECIAL-PAGE related stuff ---
		//		haclfAddScriptsLinks($out, 'Prototype',
		//		                     $haclgHaloScriptPath."/scripts/lib/", array(
		//			                     	'prototype.js'));
		return true;
	}
}

/**
 * Adds the given scripts as link to the output. Each script is prepended with the
 * given base path.
 *
 * @param {OutputPage} $out
 * 		The output object
 * @param {String} $baseID
 * 		An ID for naming the given group of scripts
 * @param {String} $basePath
 * 		This path is prepended to each script in $scripts
 * @param {array(String)} $scripts
 * 		All scripts that shall be added.
 *
 */
function haclfAddScriptsLinks($out, $baseID, $basePath, $scripts) {
	$scriptsHTML = '';
	foreach ($scripts as $script) {
		$scriptsHTML .= Html::linkedScript("$basePath$script")."\n";
	}
	$out->addHeadItem($baseID, "\n".$scriptsHTML."\n");
}

/**********************************************/
/***** namespace settings                 *****/
/**********************************************/

/**
 * Init the additional namespaces used by HaloACL. The
 * parameter denotes the least unused even namespace ID that is
 * greater or equal to 100.
 */
function haclfInitNamespaces() {
	global $haclgNamespaceIndex, $wgExtraNamespaces, $wgNamespaceAliases,
	$wgNamespacesWithSubpages, $wgLanguageCode, $haclgContLang;

	if (!isset($haclgNamespaceIndex)) {
		$haclgNamespaceIndex = 300;
	}

	define('HACL_NS_ACL',       $haclgNamespaceIndex);
	define('HACL_NS_ACL_TALK',  $haclgNamespaceIndex+1);

	haclfInitContentLanguage($wgLanguageCode);

	// Register namespace identifiers
	if (!is_array($wgExtraNamespaces)) {
		$wgExtraNamespaces=array();
	}
	$namespaces = $haclgContLang->getNamespaces();
	$namespacealiases = $haclgContLang->getNamespaceAliases();
	$wgExtraNamespaces = $wgExtraNamespaces + $namespaces;
	$wgNamespaceAliases = $wgNamespaceAliases + $namespacealiases;

	// Support subpages for the namespace ACL
	$wgNamespacesWithSubpages = $wgNamespacesWithSubpages + array(
	HACL_NS_ACL => true,
	HACL_NS_ACL_TALK => true
	);
}


/**********************************************/
/***** language settings                  *****/
/**********************************************/

/**
 * Set up (possibly localised) names for HaloACL
 */
function haclfAddMagicWords(&$magicWords, $langCode) {
	//	$magicWords['ask']     = array( 0, 'ask' );
	return true;
}

/**
 * Initialise a global language object for content language. This
 * must happen early on, even before user language is known, to
 * determine labels for additional namespaces. In contrast, messages
 * can be initialised much later when they are actually needed.
 */
function haclfInitContentLanguage($langcode) {
	global $haclgIP, $haclgContLang;
	if (!empty($haclgContLang)) {
		return;
	}
	wfProfileIn('haclfInitContentLanguage');

	$haclContLangFile = 'HACL_Language' . str_replace( '-', '_', ucfirst( $langcode ) );
	$haclContLangClass = 'HACLLanguage' . str_replace( '-', '_', ucfirst( $langcode ) );
	if (file_exists($haclgIP . '/languages/'. $haclContLangFile . '.php')) {
		include_once( $haclgIP . '/languages/'. $haclContLangFile . '.php' );
	}

	// fallback if language not supported
	if ( !class_exists($haclContLangClass)) {
		include_once($haclgIP . '/languages/HACL_LanguageEn.php');
		$haclContLangClass = 'HACLLanguageEn';
	}
	$haclgContLang = new $haclContLangClass();

	wfProfileOut('haclfInitContentLanguage');
}

/**
 * Returns the ID and name of the given user.
 *
 * @param User/string/int $user
 * 		User-object, name of a user or ID of a user. If <null> (which is the
 *      default), the currently logged in user is assumed.
 *      There are two special user names:
 * 			'*' - anonymous user (ID:0)
 *			'#' - all registered users (ID: -1)
 * @return array(int,string)
 * 		(Database-)ID of the given user and his name. For the sake of
 *      performance the name is not retrieved, if the ID of the user is
 * 		passed in parameter $user.
 * @throws
 * 		HACLException(HACLException::UNKOWN_USER)
 * 			...if the user does not exist.
 */
function haclfGetUserID($user = null) {
	$userID = false;
	$userName = '';
	if ($user === null) {
		// no user given
		// => the current user's ID is requested
		global $wgUser;
		$userID = $wgUser->getId();
		$userName = $wgUser->getName();
	} else if (is_int($user) || is_numeric($user)) {
		// user-id given
		$userID = (int) $user;
	} else if (is_string($user)) {
		if ($user == '#') {
			// Special name for all registered users
			$userID = -1;
		} else if ($user == '*') {
			// Anonymous user
			$userID = 0;
		} else {
			// name of user given
			$etc = haclfDisableTitlePatch();
			$userID = (int) User::idFromName($user);
			haclfRestoreTitlePatch($etc);
			if (!$userID) {
				$userID = false;
			}
			$userName = $user;
		}
	} else if (is_a($user, 'User')) {
		// User-object given
		$userID = $user->getId();
		$userName = $user->getName();
	}

	if ($userID === 0) {
		//Anonymous user
		$userName = '*';
	} else if ($userID === -1) {
		// all registered users
		$userName = '#';
	}

	if ($userID === false) {
		// invalid user
		throw new HACLException(HACLException::UNKOWN_USER,'"'.$user.'"');
	}

	return array($userID, $userName);

}

/**
 * Pages in the namespace ACL are not cacheable
 *
 * @param Article $article
 * 		Check, if this article can be cached
 *
 * @return bool
 * 		<true>, for articles that are not in the namespace ACL
 * 		<false>, otherwise
 */
function haclfIsFileCacheable($article) {
	return $article->getTitle()->getNamespace() != HACL_NS_ACL;
}

/**
 * The hash for the page cache depends on the user.
 *
 * @param string $hash
 * 		A reference to the hash. This the ID of the current user is appended
 * 		to this hash.
 *
 *
 */
function haclfPageRenderingHash($hash) {

	global $wgUser, $wgTitle;
	if (is_object($wgUser)) {
		$hash .= '!'.$wgUser->getId();
	}
	if (is_object($wgTitle)) {
		if ($wgTitle->getNamespace() == HACL_NS_ACL) {
			// How often do we have to say that articles in the namespace ACL
			// can not be cached ?
			$hash .= '!'.wfTimestampNow();
		}

	}
	return true;
}

/**
 * A patch in the Title-object checks for each creation of a title, if access
 * to this title is granted. While the rights for a title are evaluated, this
 * may lead to a recursion. So the patch can be switched off. After the critical
 * operation (typically Title::new... ), the patch should be switched on again with
 * haclfRestoreTitlePatch().
 *
 * @return bool
 * 		The current state of the Title-patch. This value has to be passed to
 * 		haclfRestoreTitlePatch().
 */
function haclfDisableTitlePatch() {
	global $haclgEnableTitleCheck;
	$etc = $haclgEnableTitleCheck;
	$haclgEnableTitleCheck = false;
	return $etc;
}

/**
 * See documentation of haclfDisableTitlePatch
 *
 * @param bool $etc
 * 		The former state of the title patch.
 */
function haclfRestoreTitlePatch($etc) {
	global $haclgEnableTitleCheck;
	$haclgEnableTitleCheck = $etc;
}

/**
 * Returns the article ID for a given article name. This function has a special
 * handling for Special pages, which do not have an article ID. HaloACL stores
 * special IDs for these pages. Their IDs are always negative while the IDs of
 * normal pages are positive.
 *
 * @param string $articleName
 * 		Name of the article
 * @param int $defaultNS
 * 		The default namespace if no namespace is given in the name
 *
 * @return int
 * 		ID of the article:
 * 		>0: ID of an article in a normal namespace
 * 		=0: Name of the article is invalid
 * 		<0: ID of a Special Page
 *
 */
function haclfArticleID($articleName, $defaultNS = NS_MAIN) {
	$etc = haclfDisableTitlePatch();
	$t = Title::newFromText($articleName, $defaultNS);
	haclfRestoreTitlePatch($etc);
	if (is_null($t)) {
		return 0;
	}
	$id = $t->getArticleID();
	if ($id === 0) {
		$id = $t->getArticleID(Title::GAID_FOR_UPDATE);
	}
	if ($id == 0 && $t->getNamespace() == NS_SPECIAL) {
		$id = HACLStorage::getDatabase()->idForSpecial($articleName);
	}
	return $id;

}

/**
 * If SMW is present, its semantic store is wrapped so that access to
 * properties and protected pages can be restricted.
 * The stores of SMW and the Halo extension are wrapped.
 */
function haclfInitSemanticStores() {
	if (!defined('SMW_VERSION')) {
		return;
	}

	if (!defined('SMW_HALO_VERSION')) {

		// Wrap the semantic store of SMW
		global $smwgMasterStore;
		$smwStore = smwfGetStore();
		$wrapper = new HACLSMWStore($smwStore);
		$smwgMasterStore = $wrapper;
	} else {
		smwfAddStore('HACLSMWStore');
	}

}



/**
 * Add appropriate JS language script
 */
function haclAddJSLanguageScripts() {
	global $haclgIP, $haclgHaloScriptPath, $wgUser, $wgResourceModules;

	// content language file
	$lngScript = '/scripts/Language/HaloACL_LanguageEn.js';
	$lng = '/scripts/Language/HaloACL_Language';
	if (isset($wgUser)) {
		$lng .= ucfirst($wgUser->getOption('language')).'.js';
		if (file_exists($haclgIP . $lng)) {
			$lngScript = $lng;
		}
	}

	$wgResourceModules['ext.HaloACL.Language'] = array(
	// JavaScript and CSS styles. To combine multiple file, just list them as an array.
		'scripts' => array(
			"scripts/Language/HaloACL_Language.js",
	$lngScript
	),
	 
	// ResourceLoader needs to know where your files are; specify your
	// subdir relative to "/extensions" (or $wgExtensionAssetsPath)
		'localBasePath' => dirname(__FILE__).'/../',
		'remoteExtPath' => 'HaloACL'
		);

}

/**
 * This function is called from the hook 'EditPageBeforeEditButtons'. It adds the
 * ACL toolbar to edited pages.
 *
 */
function haclfAddToolbarForEditPage ($content_actions) {
	if ($content_actions->mArticle->mTitle->mNamespace == HACL_NS_ACL) {
		return $content_actions;
	}

	global $wgHooks, $wgOut,$haclgHaloScriptPath;
	// Add some scripts to the bottom of the document
	$wgHooks['SkinAfterBottomScripts'][] = 'haclfAddToolbarForEditPageAfterBottomScripts';
	$wgOut->addModules('ext.HaloACL.Toolbar');

	//	haclfAddScriptsLinks($wgOut, 'Prototype',
	//	                     $haclgHaloScriptPath."/scripts/lib/", array(
	//	                     	'prototype.js'));
	return true;
}

/**
 * Adds script code for the HaloACL toolbar to the bottom of the document.
 *
 * @param $skin
 * @param $bottomScriptText
 */
function haclfAddToolbarForEditPageAfterBottomScripts($skin, &$bottomScriptText) {

	global $wgOut;
	$title = $wgOut->getTitle();
	$title = $title->getText();
	$html = <<<HTML
        <script>
            YAHOO.haloacl.toolbar.actualTitle = '$title';
            YAHOO.haloacl.toolbar.loadContentToDiv('content','haclGetHACLToolbar',{title:"$title"});
        </script>
HTML;
	$bottomScriptText .= $html;
	return true;
}

/**
 * This function is called from the hook 'EditPageBeforeEditButtons'. It adds the
 * ACL toolbar to a semantic form.
 *
 */
function haclfAddToolbarForSemanticForms($pageTitle, $html) {

	global $wgHooks, $wgOut,$haclgHaloScriptPath;
	// Add some scripts to the bottom of the document
	$wgHooks['SkinAfterBottomScripts'][] = 'haclfAddToolbarForEditPageAfterBottomScripts';
	$wgOut->addModules('ext.HaloACL.Toolbar');

	//	haclfAddScriptsLinks($wgOut, 'Prototype',
	//	                     $haclgHaloScriptPath."/scripts/lib/", array(
	//	                     	'prototype.js'));

	return true;
}

/**
 * This function is called from the hook 'sfSetTargetName' in SemanticForms. It adds a
 * JavaScript line that initializes a variable with the namespace number of the
 * current title.
 *
 * @param string $titleName
 * 	Name of the article that is edited with Semantic Forms
 *
 */
function haclfOnSfSetTargetName($titleName) {
	global $wgOut, $wgJsMimeType;
	if (!empty($titleName)) {
		$t = Title::newFromText($titleName);
		$namespace = $t->getNamespace();
		$script = "<script type= \"$wgJsMimeType\">/*<![CDATA[*/\n";
		$script .= "sfgTargetNamespaceNumber = $namespace;";
		$script .= "\n/*]]>*/</script>\n";
			
		$wgOut->addScript($script);
	}
	return true;
}

/**
 * Normally the query rewriter does not allow queries with a variable for a
 * predicate. This function turns this protection off.
 */
function haclfAllowVariableForPredicate() {
	HACLQueryRewriter::allowVariableForPredicate(true);
	return true;
}

/**
 * Normally the query rewriter does not allow queries with a variable for a
 * predicate. This function turns this protection on.
 */
function haclfDisallowVariableForPredicate() {
	HACLQueryRewriter::allowVariableForPredicate(false);
	return true;
}

/**
 * Registers the icon for Auto Completion.
 *
 * @param $namespaceMappings
 */
function haclfRegisterACIcon(& $namespaceMappings) {
	global $haclgIP;
	$namespaceMappings[HACL_NS_ACL]="/extensions/HaloACL/skins/images/ACL_AutoCompletion.gif";
	return true;
}

/**
 * Removes the tab "Protect"
 *
 * @param $content_actions
 */
function haclfRemoveProtectTab( &$content_actions ) {
	if (array_key_exists('protect', $content_actions))
	unset($content_actions['protect']);
	return true;
}

// encrypt() and decrypt() functions copied from
// http://us2.php.net/manual/en/ref.mcrypt.php#52384
function haclfEncrypt($string) {
	global $haclgEncryptionKey;
	$result = '';
	for($i=0; $i<strlen($string); $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($haclgEncryptionKey, ($i % strlen($haclgEncryptionKey))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result .= $char;
	}
	return base64_encode($result);
}

function haclfDecrypt($string) {
	global $haclgEncryptionKey;
	$result = '';
	$string = base64_decode($string);
	for($i=0; $i<strlen($string); $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($haclgEncryptionKey, ($i % strlen($haclgEncryptionKey))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result .= $char;
	}
	return $result;
}

function haclfHandleFormField($form_field, $cur_value, $form_submitted) {
	$property_name = $form_field->template_field->getSemanticProperty();
	if (! empty($property_name)) {
		$property_title = Title::makeTitleSafe(SMW_NS_PROPERTY, $property_name);
		if (!isset($property_title)) {
			return true;
		}
		if ($property_title->exists()) {
			$form_field->is_disabled = false;
			if (! $property_title->userCan('propertyread')) {
				if ($form_submitted) {
					$cur_value = haclfDecrypt($cur_value);
				} else {
					$form_field->is_hidden = true;
					$cur_value = haclfEncrypt($cur_value);
				}
			} elseif ((! $property_title->userCan('propertyedit')) && (! $property_title->userCan('propertyformedit'))) {
				$form_field->is_disabled = true;
			}
		}
	}
	return true;
}


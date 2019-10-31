<?php
use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

require_once('lib/init.php');

// Initialise session and database
$db = NULL;
$ok = init($db, TRUE);
// Initialise parameters
$req_id = 0;
if ($ok) {
	$action = '';
	// Check for item id and action parameters
	if (isset($_REQUEST['req_id'])) {
		$req_id = intval($_REQUEST['req_id']);
	}
	if (isset($_REQUEST['do'])) {
		$action = $_REQUEST['do'];
	}

	if ($action == 'add' && isset($_REQUEST['module_path']) && isset($_REQUEST['theme_id']) && $_SESSION['isStaff']) {
		$ok = TRUE;
		$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
		$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
		if (is_null($_SESSION['resource_pk'])) {
			$resource_link = ToolProvider\ResourceLink::fromConsumer($consumer, $_SESSION['resource_id']);
			$ok = $resource_link->save();
		} else {
			$resource_link = ToolProvider\ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);
		}
		if ($ok) {
			$_SESSION['resource_pk'] = $resource_link->getRecordId();
			$ok = selectModule($db, $_SESSION['resource_pk'], $_REQUEST['module_path'], $_REQUEST['theme_id'][$_REQUEST['module_path']]);
		}
		if ($ok) {
			$_SESSION['message'] = 'The module has been selected.';
		} else {
			$_SESSION['error_message'] = 'Unable to select module; please try again';
		}
		header('Location: ./');
		exit;
	} else if ($action == 'delete' && !empty($req_id) && $_SESSION['isStaff']) {
		if (deleteModule($db, $_SESSION['resource_pk'], $req_id)) {
			$_SESSION['message'] = 'The module has been successfully deselected.';
		} else {
			$_SESSION['error_message'] = 'Unable to remove module; please try again.';
		}
		header('Location: ./');
		exit;
	} else if ($action == 'content_saveall' && $_SESSION['isStaff']) {
		if (isset($_REQUEST['content']) and isset($_SESSION['resource_pk'])) {
			$ok = TRUE;
			$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
			if(!isset($selected_module)){
				$ok = FALSE;
			}
			foreach($_REQUEST['content'] as $path => $content_item){
				$start = empty($content_item['start_datetime'])?NULL:$content_item['start_datetime'];
				$end = empty($content_item['end_datetime'])?NULL:$content_item['end_datetime'];
				$hidden=0;
				if (isset($content_item['checked'])){
					$hidden = (strcmp('on',$content_item['checked'])==0)?1:0;
				} 
				if($ok) {
					$ok = updateContentOverrides($db, $selected_module->selected_id,
							$path, $start, $end, $hidden);
				}
			}
			if ($ok) {
				$_SESSION['message'] = 'Content successfully updated.';
			} else {
				$_SESSION['error_message'] = 'Unable to update content; please try again';
			}
			header('Location: ./');
			exit;
		}
	} else if ($action == 'content_clearall' && $_SESSION['isStaff']) {
		if (isset($_SESSION['resource_pk'])) {
			$ok = TRUE;
			$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
			if(!isset($selected_module)){
				$ok = FALSE;
			}
			$ok = deleteContentOverrides($db, $selected_module->selected_id);
			if ($ok) {
				$_SESSION['message'] = 'Adaptive release schedule successfully set back to its defaults.';
			} else {
				$_SESSION['error_message'] = 'Unable to update content; please try again';
			}
			header('Location: ./');
			exit;
		}
	} else if ($action == 'options_save' && $_SESSION['isStaff']){
		if (isset($_SESSION['resource_pk']) && $_SESSION['isStaff']) {
			$options = getResourceOptions($db, $_SESSION['resource_pk']);
			$new_opt = array();
			foreach ($options as $key => $value){
				if(isset($_POST["opt"][$key])){
					$new_opt[$key] = $_POST["opt"][$key];
				} else {
					$new_opt[$key] = false;
				}
			}
			$ok = updateResourceOptions($db, $_SESSION['resource_pk'], $new_opt);
			if ($ok) {
				$_SESSION['message'] = 'Options updated!';
			} else {
				$_SESSION['error_message'] = 'Unable to update options; please try again';
			}
			header('Location: ./?dashpage=opt');
			exit;
		}
	}

	if (isset($_SESSION['resource_pk'])) {
		$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
		if(isset($selected_module)){
			$selected_module->apply_content_overrides($db, $_SESSION['resource_pk']);
		}
	} else {
		$ok = false;
	}

}
#print_r($_SERVER);
#print_r($_SESSION);
#print_r($_COOKIE);

if ($ok && $_SESSION['isStudent']) {
	if (isset($selected_module)) {
		$page = new StudentPage();
	} else {
		$page = new ModuleNotSelectedPage();
	}
} else if($ok && !in_array($_SESSION['user_id'],AUTH_USER_IDS)
			&& !in_array(strtolower($_SESSION['user_email']),AUTH_USER_EMAILS)){
	if (isset($selected_module)) {
		$page = new StudentPage();
	} else {
		$page = new LandingPage();
	}
} else if ($ok && $_SESSION['isStaff']) {
	$page = new DashboardPage();
	$page->setResource($_SESSION['resource_pk']);
} else {
	$page = new ErrorPage();
}

// Handle messages
if (isset($_SESSION['error_message'])) {
	$page->addAlert($_SESSION['error_message'], 'danger');
	unset($_SESSION['error_message']);
}
if (isset($_SESSION['message'])) {
	$page->addAlert($_SESSION['message']);
	unset($_SESSION['message']);
}


$page->setDB($db);
if(isset($selected_module)){
	$page->setModule($selected_module, $_SESSION['resource_pk']);
}


if(isset($_REQUEST['req_content'])){
	$authLevel = 0;
	if($ok && $_SESSION['isStaff'] && isset($_COOKIE["auth_level"])){
		$authLevel = $_COOKIE["auth_level"];
	}
	if($ok && $_SESSION['isStaff'] && isset($_REQUEST['auth_level'])){
		setcookie("auth_level", $_REQUEST['auth_level'], time()+3600);
		$authLevel = $_REQUEST['auth_level'];
	}
	$requestReplyError = $page->requestContent($_REQUEST['req_content'], $authLevel);
	if(!empty($requestReplyError)) $page->addAlert($requestReplyError, 'danger');
}

$page->render();
exit;
?>

<?php
require_once('settings.php');
require_once('lib/kaltura_client.php');
require_once('lib/kaltura_helpers.php');
require_once('lib/kaltura_model.php');  
 
// comments filter
if (KalturaHelpers::compareWPVersion("2.5", "=")) 
	// in wp 2.5 there was a bug in wptexturize which corrupted our tag with unicode html entities
	// thats why we run our filter before (using lower priority)
	add_filter('comment_text', 'kaltura_the_comment', -1);
else
	// in wp 2.5.1 and higher we can use the default priority
	add_filter('comment_text', 'kaltura_the_comment');

// tag shortcode
add_shortcode('kaltura-widget', 'kaltura_shortcode');
add_shortcode('x7video', 'x7video_shortcode');

if (KalturaHelpers::videoCommentsEnabled()) {
	add_action('comment_form', 'kaltura_comment_form');
}

// js
add_filter('print_scripts_array', 'kaltura_print_js'); // print js files
add_action('wp_print_scripts', 'kaltura_register_js'); // register js files

// css
add_action('wp_head', 'kaltura_head'); // print css

// footer
add_action('wp_footer', 'kaltura_footer');

// admin css
add_filter('admin_head', 'kaltura_add_admin_css'); // print admin css

if (KalturaHelpers::compareWPVersion("2.7", ">="))
	add_action('load-media_page_interactive_video_library', 'kaltura_library_page_load'); // to enqueue scripts and css
else
	add_action('load-manage_page_interactive_video_library', 'kaltura_library_page_load'); // to enqueue scripts and css

// admin menu & tabs
add_action('admin_menu', 'kaltura_add_admin_menu'); // add kaltura admin menu

add_filter("media_buttons_context", "kaltura_add_media_button"); // will add button over the rich text editor
add_filter("media_upload_tabs", "kaltura_add_upload_tab"); // will add tab to the modal media box

add_action("media_upload_kaltura_upload", "kaltura_upload_tab");
add_action("media_upload_kaltura_browse", "kaltura_browse_tab");

if (KalturaHelpers::compareWPVersion("2.6", "<")) {
	add_action("admin_head_kaltura_tab_content", "media_admin_css");
	add_action("admin_head_kaltura_tab_browse_content", "media_admin_css");
}

// tiny mce
add_filter('mce_external_plugins', 'kaltura_add_mce_plugin'); // add the kaltura mce plugin
add_filter('tiny_mce_version', 'kaltura_mce_version');

/*
 * Occures when publishing the post, and on every save while the post is published
 * 
 * @param $postId
 * @param $post
 * @return unknown_type
 */
function kaltura_publish_post($post_id, $post)
{
	require_once("lib/kaltura_wp_model.php");

	$content = $post->post_content;

	$shortcode_tags = array();
	
	global $kaltura_post_id, $kaltura_widgets_in_post;
	$kaltura_post_id = $post_id;
	$kaltura_widgets_in_post = array();
	KalturaHelpers::runKalturaShortcode($content, "_kaltura_find_post_widgets");

	// delete all widgets that doesn't exists in the post anymore
	KalturaWPModel::deleteUnusedWidgetsByPost($kaltura_post_id, $kaltura_widgets_in_post);
}

add_action("publish_post", "kaltura_publish_post", 10, 2);
add_action("publish_page", "kaltura_publish_post", 10, 2);


/*
 * Occures on evey status change, we need to mark our widgets as unpublished when status of the post is not publish
 * 
 * @param $oldStatus
 * @param $newStatus
 * @param $post
 * @return unknown_type
 */
function kaltura_post_status_change($new_status, $old_status, $post)
{
	// get all widgets linked to this post and mark them as not published
	$statuses = array("inherit", "publish");
	// we don't handle "inherit" status because it not the real post, but the revision
	// we don't handle "publish" status because it's handled in: "kaltura_publish_post"
	if (!in_array($new_status, $statuses))
	{
		require_once("lib/kaltura_wp_model.php");
		$widgets = KalturaWPModel::getWidgetsByPost($post->ID);
		KalturaWPModel::unpublishWidgets($widgets);
	}
}

add_action("transition_post_status", "kaltura_post_status_change", 10, 3); 


/*
 * Occures on post delete, and deleted all widgets for that post
 * 
 * @param $post_id
 */
function kaltura_delete_post($post_id)
{
	require_once("lib/kaltura_wp_model.php");
	KalturaWPModel::deleteUnusedWidgetsByPost($post_id, array());
}

add_action("deleted_post", "kaltura_delete_post", 10, 1); 


/*
 * Occures when comment status is changed
 * @param $comment_id
 * @param $status
 * @return unknown_type
 */
function kaltura_set_comment_status($comment_id, $status)
{
	require_once("lib/kaltura_wp_model.php");

	switch ($status)
	{
		case "approve":
			kaltura_comment_post($comment_id, 1);
			break;
		default:
			KalturaWPModel::deleteWidgetsByComment($comment_id);
	}
}

add_action("wp_set_comment_status", "kaltura_set_comment_status", 10, 2);


/*
 * Occured when posting a comment
 * @param $comment_id
 * @param $approved
 * @return unknown_type
 */
function kaltura_comment_post($comment_id, $approved)
{
	if ($approved) 
	{
		require_once("lib/kaltura_wp_model.php");

		global $kaltura_comment_id;
		$kaltura_comment_id = $comment_id;
		
		$comment = get_comment($comment_id);
		KalturaHelpers::runKalturaShortcode($comment->comment_content, "_kaltura_find_comment_widgets");
	}
}

add_action("comment_post", "kaltura_comment_post", 10, 2);

/*
 * Occures when the plugin is activated 
 * @return unknown_type
 */
function kaltura_activate()
{
	update_option("kaltura_default_player_type", "whiteblue");
	update_option("kaltura_comments_player_type", "whiteblue");

	require_once("kaltura_db.php");
	kaltura_install_db();
}

register_activation_hook(KALTURA_PLUGIN_FILE, 'kaltura_activate');


function kaltura_admin_page()
{
	require_once("lib/kaltura_model.php");
	require_once('admin/kaltura_admin_controller.php');
}

function kaltura_library_page()
{
	$_GET["kaction"] = isset($_GET["kaction"]) ? $_GET["kaction"] : "entries";
	require_once("lib/kaltura_library_controller.php");
}

function kaltura_video_library_video_posts_page()
{
	require_once("lib/kaltura_library_controller.php");
}

function kaltura_library_page_load()
{
	if (KalturaHelpers::compareWPVersion("2.6", ">="))
		add_thickbox();
	else
		wp_enqueue_script('thickbox');
}

function kaltura_add_mce_plugin($content) {
	$pluginUrl = KalturaHelpers::getPluginUrl();
	$content["kaltura"] = $pluginUrl . "/tinymce/kaltura_tinymce.js?v".kaltura_get_version();
	return $content;
}

function kaltura_mce_version($content) 
{
	return $content . '_k'.kaltura_get_version();
}
  
function kaltura_add_admin_menu() 
{
	add_options_page('All in One Video', 'All in One Video', 8, 'interactive_video', 'kaltura_admin_page');
	
	$args = array('All in One Video', 'All in One Video', 8, 'interactive_video_library', 'kaltura_library_page');
	// because of the change in wordpress 2.7 menu structure, we move the library page under "Media" tab
	if (KalturaHelpers::compareWPVersion("2.7", ">=")) 
		call_user_func_array("add_media_page", $args);
	else
		call_user_func_array("add_management_page", $args);
}

function kaltura_the_content($content) 
{
	return _kaltura_replace_tags($content, false);
}

function kaltura_the_comment($content) 
{
	global $shortcode_tags;
	
	// we want to run our shortcode and not all
	$shortcode_tags_backup = $shortcode_tags;
	$shortcode_tags = array();
	
	add_shortcode('kaltura-widget', 'kaltura_shortcode');
	$content = do_shortcode($content);
	
	// restore the original array
	$shortcode_tags = $shortcode_tags_backup;
	
	return $content;
}

function kaltura_print_js($content) 
{
	$content[] = 'kaltura';
	$content[] = 'jquery';
	$content[] = 'kaltura_swfobject_1.5';
	$content[] = 'swfobject-script';
	$content[] = 'jquerytools';
	$content[] = 'jqueryui-script';
	$content[] = 'shadowbox-script';
	$content[] = 'x7ugc-script';
	$content[] = 'x7validator-script';
	$content[] = 'x7datatables-script';
	if (is_admin())
		$content[] = 'kadmin';
	KalturaHelpers::addWPVersionJS();
	
	return $content;
}

function kaltura_register_js() 
{
	$plugin_url = KalturaHelpers::getPluginUrl();
	wp_register_script('kaltura', $plugin_url . '/js/kaltura.js?v'.kaltura_get_version());
	//register swfobject for flash embedding
    wp_register_script('swfobject-script', 'http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js', false, false, true);
    //unregister WP's jquery and register newest jquery
    wp_deregister_script('jquery');
    //includes jquery tools ui
    wp_register_script('jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js', false, false, true);
    wp_register_script('jquerytools', $plugin_url . '/js/jquery.tools.min.js', false, false, true);
    //register newest jquery ui
	wp_register_script('jqueryui-script', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js', false, false, true);
	//register shadowbox
	wp_register_script('shadowbox-script', $plugin_url . '/js/shadowbox.js', false, false, true);
    //register custom x7js
	wp_register_script('x7ugc-script', $plugin_url . '/js/x7js.js', false, false, true);
	//register form validator
	wp_register_script('x7validator-script', $plugin_url . '/js/validator.js', false, false, true);
	//register datatables
	wp_register_script('x7datatables-script', $plugin_url . '/js/jquery.dataTables.min.js', false, false, true);
	if (is_admin())
		wp_register_script('kadmin', $plugin_url . '/js/kadmin.js?v'.kaltura_get_version());
	wp_register_script('kaltura_swfobject_1.5', $plugin_url . '/js/swfobject.js?v'.kaltura_get_version(), array(), '1.5');
}

//enqueue styles and scripts
add_action('after_setup_theme', 'enqueue_my_styles');
function enqueue_my_styles(){
	//jquery ui style
	wp_enqueue_style('jqueryui-style', plugins_url( 'css/smoothness/jquery-ui-1.8.4.custom.css', __FILE__ ));
	//shadowbox (lightbox) style
	wp_enqueue_style('shadowbox-style', plugins_url( 'css/sbox/shadowbox.css', __FILE__ ));
	//x7video custom style
	wp_enqueue_style('x7video-style', plugins_url( 'css/x7style.css', __FILE__ ));
	//datatables
	wp_enqueue_style('datatables-style', plugins_url( 'css/datatables/css/demo_table_jui.css', __FILE__ ));
}

function kaltura_head() 
{
	$plugin_url = KalturaHelpers::getPluginUrl();
	echo('<link rel="stylesheet" href="' . $plugin_url . '/css/kaltura.css?v'.kaltura_get_version().'" type="text/css" />');
}

function kaltura_footer() 
{
	$plugin_url = KalturaHelpers::getPluginUrl();
	echo ' 
	<script type="text/javascript">
		function handleGotoContribWizard (widgetId, entryId) {
			KalturaModal.openModal("contribution_wizard", "' . $plugin_url . '/page_contribution_wizard_front_end.php?wid=" + widgetId + "&entryId=" + entryId, { width: 680, height: 360 } );
			jQuery("#contribution_wizard").addClass("modalContributionWizard");
		}
	
		function handleGotoEditorWindow (widgetId, entryId) {
			KalturaModal.openModal("simple_editor", "' . $plugin_url . '/page_simple_editor_front_end.php?wid=" + widgetId + "&entryId=" + entryId, { width: 890, height: 546 } );
			jQuery("#simple_editor").addClass("modalSimpleEditor");
		}
		
		function gotoContributorWindow(entryId) {
			handleGotoContribWizard("", entryId);
		}
		
		function gotoEditorWindow(entryId) {
			handleGotoEditorWindow("", entryId);
		}
	</script>
	
	';
}

function kaltura_add_admin_css($content) 
{
	$plugin_url = KalturaHelpers::getPluginUrl();
	$content .= '<link rel="stylesheet" href="' . $plugin_url . '/css/kaltura.css?v'.kaltura_get_version().'" type="text/css" />' . "\n";
	echo $content;
}

function kaltura_create_tab() 
{
	require_once('tab_create.php');
}

function kaltura_add_media_button($content)
{
	global $post_ID, $temp_ID;
	$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
	$media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
	$kaltura_iframe_src = apply_filters('kaltura_iframe_src', "$media_upload_iframe_src&amp;tab=kaltura_upload");
	$kaltura_browse_iframe_src = apply_filters('kaltura_iframe_src', "$media_upload_iframe_src&amp;tab=kaltura_browse");
	$kaltura_title = __('Add Interactive Video');
	$kaltura_button_src = KalturaHelpers::getPluginUrl() . '/images/interactive_video_button.gif';
	$content .= <<<EOF
		<a href="{$kaltura_iframe_src}&amp;TB_iframe=true&amp;height=500&amp;width=640" class="thickbox" title='$kaltura_title'><img src='$kaltura_button_src' alt='$kaltura_title' /></a>
EOF;

	return $content;
}

function kaltura_add_upload_tab($content)
{
	$content["kaltura_upload"] = __("All in One Video");
	return $content;
}

function kaltura_add_upload_tab_interactive_video_only($content)
{
	$content = array();
	$content["kaltura_upload"] = __("Add Interactive Video");
	$content["kaltura_browse"] = __("Browse Interactive Videos");
	return $content;
}

function kaltura_upload_tab()
{
	// only for 2.6 and higher
	if (KalturaHelpers::compareWPVersion("2.6", ">="))
		wp_enqueue_style('media');
	
	wp_iframe('kaltura_upload_tab_content');
}

function kaltura_browse_tab()
{
	// only for 2.6 and higher
	if (KalturaHelpers::compareWPVersion("2.6", ">="))
		wp_enqueue_style('media');
		
	wp_iframe('kaltura_browse_tab_content');
}

function kaltura_upload_tab_content()
{
	unset($GLOBALS['wp_filter']['media_upload_tabs']); // remove all registerd filters for the tabs
	add_filter("media_upload_tabs", "kaltura_add_upload_tab_interactive_video_only"); // register our filter for the tabs
	media_upload_header(); // will add the tabs menu
	
	if (!isset($_GET["kaction"]))
		$_GET["kaction"] = "upload";
	require_once("lib/kaltura_library_controller.php");
}

function kaltura_browse_tab_content()
{
	unset($GLOBALS['wp_filter']['media_upload_tabs']); // remove all registerd filters for the tabs
	add_filter("media_upload_tabs", "kaltura_add_upload_tab_interactive_video_only"); // register our filter for the tabs
	media_upload_header(); // will add the tabs menu
	
	if (!isset($_GET["kaction"]))
		$_GET["kaction"] = "browse";
	require_once("lib/kaltura_library_controller.php");
}

function kaltura_comment_form($post_id) 
{
	$user = wp_get_current_user();
	if (!$user->ID && !KalturaHelpers::anonymousCommentsAllowed())
	{
		echo "You must be <a href=" . get_option('siteurl') . "/wp-login.php?redirect_to=" . urlencode(get_permalink()) . ">logged in</a> to post a <br /> video comment.";
	}
	else
	{
		$plugin_url = KalturaHelpers::getPluginUrl();
		$js_click_code = "Kaltura.openCommentCW('".$plugin_url."'); ";
		echo "<input type=\"button\" id=\"kaltura_video_comment\" name=\"kaltura_video_comment\" tabindex=\"6\" value=\"Add Video Comment\" onclick=\"" . $js_click_code . "\" />";
	}
}

//PHP REST helper function that does not use CURL
function rest_helper($url, $params = null, $verb = 'POST', $format = 'xml')
{
  $cparams = array(
    'http' => array(
      'method' => $verb,
      'ignore_errors' => true
    )
  );
  if ($params !== null) {
    $params = http_build_query($params);
    if ($verb == 'POST') {
      $cparams['http']['content'] = $params;
    } else {
      $url .= '?' . $params;
    }
  }

  $context = stream_context_create($cparams);
  $fp = fopen($url, 'rb', false, $context);
  if (!$fp) {
    $res = false;
  } else {
    // If you're trying to troubleshoot problems, try uncommenting the
    // next two lines; it will show you the HTTP response headers across
    // all the redirects:
    // $meta = stream_get_meta_data($fp);
    // var_dump($meta['wrapper_data']);
    $res = stream_get_contents($fp);
  }

  if ($res === false) {
    throw new Exception("$verb $url failed: $php_errormsg");
  }

  switch ($format) {
    case 'json':
      $r = json_decode($res);
      if ($r === null) {
        throw new Exception("failed to decode $res as json");
      }
      return $r;

    case 'xml':
      $r = simplexml_load_string($res);
      if ($r === null) {
        throw new Exception("failed to decode $res as xml");
      }
      return $r;
  }
  return $res;
}

// create custom plugin settings menu
add_action('admin_menu', 'x7_create_menu');

function x7_create_menu() {

	//create new top-level menu
	add_menu_page('x7Host Videox7 UGC Plugin Settings', 'x7 UGC Settings', 'administrator', __FILE__, 'x7_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'register_mysettings' );
}

function register_mysettings() {
	//register our settings
	register_setting( 'x7-settings-group', 'x7uiconfid' );
	register_setting( 'x7-settings-group', 'x7pluiconfid' );
	register_setting( 'x7-settings-group', 'x7adminuiconfid' );
	register_setting( 'x7-settings-group', 'x7kcwuiconfid' );
	register_setting( 'x7-settings-group', 'x7allowposts' );
	register_setting( 'x7-settings-group', 'x7allowstandard' );
	register_setting( 'x7-settings-group', 'x7allowadvanced' );
}

function x7video_shortcode($atts)
{
   extract( shortcode_atts( array(
      'widget' => 'kcw',
      'bar' => 'whatev',
      ), $atts ) );

	//First, master check for logged in wordpress user.  ALL widgets will not function if this fails.
if (is_user_logged_in()){
	//Set add scripts global to true, which results in javascripts printing in footer
	global $add_my_script;
	$add_my_script = true;
	global $current_user;
        get_currentuserinfo();

	$user_login = $current_user->user_login;
	$user_ID = $current_user->ID;
	$x7kalpartnerid = get_option("kaltura_partner_id");
	$x7kalsubpartnerid = $x7kalpartnerid . "00";
	$x7server = KalturaHelpers::getServerUrl();
	$x7serverget = urlencode($x7server);
	$x7kaladminsecret = get_option("kaltura_admin_secret");
	$x7kalusersecret = get_option("kaltura_admin_secret");
	
	$x7bloghome = get_bloginfo('url');
	$x7bloghomeget = urlencode($x7bloghome);
	
	$x7uiconfid = get_option('x7uiconfid');
	$x7pluiconfid = get_option('x7pluiconfid');
	$x7adminuiconfid = get_option('x7adminuiconfid');
	$x7kcwuiconfid = get_option('x7kcwuiconfid');
	$x7allowposts = get_option('x7allowposts');
	$x7allowstandard = get_option('x7allowstandard');
	$x7allowadvanced = get_option('x7allowadvanced');

/***********************************************************************************************************************
 * UPLOAD NEW MEDIA SHORTCODE *
 * ********************************************************************************************************************/	
if ($widget=="kcw"){
		//Kaltura Contribution Wizard (Uploader)
		//Start Kaltura "User" Session
		$kmodel = KalturaModel::getInstance();
		$ks = $kmodel->getClientSideSession("",86400,$user_login);
		if (!$ks)
			wp_die(__('Failed to start new session.<br/><br/>'.$closeLink));
		//Embed the KCW
		$return .= '<div id="kcw"></div>';
		$return .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js'></script>";
		$return .= <<<X7KCW
		<script type="text/javascript">
		var params = {
			allowScriptAccess: "always",
			allowNetworking: "all",
			wmode: "opaque"
		};
		var flashVars = {"Permissions":"1","partnerId":"$x7kalpartnerid","uid":"$user_login","ks":"$ks","showCloseButton":"false"};
		swfobject.embedSWF("$x7server/kcw/ui_conf_id/1727883", "kcw", "680", "360", "9.0.0", false, flashVars, params);
		</script>
X7KCW;

	} //end if widget is kcw
/***********************************************************************************************************************
 * USER UPLOADED MEDIA SHORTCODE *
 * ********************************************************************************************************************/
if ($widget=="useruploads"){
		//This widget displays the logged in user's Kaltura uploads and offers the ability to
		//play, edit (remix), delete and post them as drafts to the wordpress blog
		//Start Kaltura "Admin" Session
		$kmodel = KalturaModel::getInstance();
		$ks = $kmodel->getAdminSession("","$user_login");
		if (!$ks)
			wp_die(__('Failed to start new session.<br/><br/>'.$closeLink));
		$ksget = urlencode($ks);
		
		//SET RPCURL XMLRPC FILE VALUE
		$x7rpcurl = $x7bloghome . "/xmlrpc.php";
		$x7fullplugurl = plugins_url('/ixr.php', __FILE__);
		$playurl = plugins_url('x7vidplayer.php', __FILE__);
                        $pluginurl = plugins_url();
                        $pluginurlget = urlencode($pluginurl);
                        $advancedediturl = plugins_url('x7advancededitor.php', __FILE__);
			$standardediturl = plugins_url('x7standardeditor.php', __FILE__);
		//GET CATEGORIES LIST
		$categories = get_categories('hide_empty=0'); 
			foreach ($categories as $cat) {
				$option .= "<option value=\"$cat->cat_name\">$cat->cat_name</option>";
			}
		
		//EMBED DELETE JAVASCRIPT FUNCTION AND POST FUNCTION AND GET VARIABLE READER
		$return.= <<<DELETE_JS
		
		<style type="text/css">
		#x7form { width: 500px; }
		.tooltip {
				display:none;
				background:transparent url($pluginurl/all-in-one-video-pack/images/black_arrow.png);
				font-size:12px;
				height:70px;
				width:160px;
				padding:25px;
				color:#fff;	
			}
		</style>

		<script type="text/javascript">
		
			function x7VidPlay()
			{
				var eid = jQuery("a#x7aplaychange").attr("title");
				var playurl = '$playurl'+'?eid='+eid+'&x7kalpartnerid=$x7kalpartnerid&x7bloghomeget=$x7bloghomeget&x7server=$x7serverget&x7uiconfid=$x7adminuiconfid';
				Shadowbox.open({
					content: playurl,
					player: "iframe",
					height: "370",
					width: "405"
				});
			}
			
			function x7VidEditStandard()
			{
				var eid = jQuery("a#x7aeditchange").attr("title");
				var name = jQuery("a#x7aeditchange").attr("name");
				jQuery.post(
					"$pluginurl/all-in-one-video-pack/x7mixcreate.php",
					{'x7bloghome': '$x7bloghome', 'x7server': "$x7server", 'ks': "$ks", 'x7editortype': '1', 'eid': eid, 'x7name': name, 'x7kalpartnerid': "$x7kalpartnerid", 'user_login': "$user_login"},
					function ( response ){
						jQuery('div#x7form').hide('slow');
						jQuery('div#x7tablewrap').show('slow');
						var editurl = '$standardediturl'+'?entryId='+response+'&ks=$ksget&x7kalpartnerid=$x7kalpartnerid&x7bloghomeget=$x7bloghomeget&userlogin=$user_login&x7server=$x7serverget&pluginurl=$pluginurlget';
						Shadowbox.open({
						content: editurl,
						player: "iframe",
						height: "600",
						width: "1000"
					});
				});
			}
			
			function x7VidEditAdvanced()
			{
				var eid = jQuery("a#x7aedit2change").attr("title");
				var name = jQuery("a#x7aedit2change").attr("name");
				jQuery.post(
					"$pluginurl/all-in-one-video-pack/x7mixcreate.php",
					{'x7bloghome': '$x7bloghome', 'x7server': "$x7server", 'ks': "$ks", 'x7editortype': '2', 'eid': eid, 'x7name': name, 'x7kalpartnerid': "$x7kalpartnerid", 'user_login': "$user_login"},
					function ( response ){
						jQuery('div#x7form').hide('slow');
						jQuery('div#x7tablewrap').show('slow');
						var editurl = '$advancedediturl'+'?entryId='+response+'&ks=$ksget&x7bloghomeget=$x7bloghomeget&x7kalpartnerid=$x7kalpartnerid&userlogin=$user_login&x7server=$x7serverget&pluginurl=$pluginurlget';
						Shadowbox.open({
						content: editurl,
						player: "iframe",
						height: "600",
						width: "1000"
					});
				});
			}
		
			function x7VidDelete()
			{
				var delid = jQuery("a#x7adelchange").attr("title");
				if (confirm("Warning! This will affect all mixes that include entry ID: " + delid + ". Continue?"))
				{ 
				    jQuery.post(
				       "$pluginurl/x7video/x7delete.php",
				       {'x7bloghome': '$x7bloghome', 'ks': "$ks", 'x7entrytype': 'media', 'eid': delid, 'x7server': "$x7server"},
				       function ( response ){
					      jQuery("#x7entriestable tbody tr [title="+delid+"]").remove();
					      jQuery('div#x7form').hide('slow');
						jQuery('div#x7tablewrap').show('slow');
						alert("Entry successfully deleted.");
					      //var x7nodes = x7Table.fnGetNodes();
					      //TODO NEED TO REMOVE APPROPRIATE ROW FROM THE TABLE AND REFRESH TABLE
				       });//end post
				} //end confirm
			} //end x7VidDelete
			var postout;
			postout = 'false';
			function x7VidPost(eid, name)
			{
				if (postout == 'false'){
					formValidate();
					var thumburl = '$x7server/p/1/sp/10000/thumbnail/entry_id/'+eid+'/width/150/height/120';
					var embedcode = '<object id="kaltura_player" name="kaltura_player" type="application/x-shockwave-flash" allowFullScreen="true" allowNetworking="all" allowScriptAccess="always" height="330" width="400" xmlns:dc="http://purl.org/dc/terms/" xmlns:media="http://search.yahoo.com/searchmonkey/media/" rel="media:video" resource="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'" data="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'"><param name="allowFullScreen" value="true" /><param name="allowNetworking" value="all" /><param name="allowScriptAccess" value="always" /><param name="bgcolor" value="#000000" /><param name="flashVars" value="&" /><param name="movie" value="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'" /><a href="http://corp.kaltura.com">video platform</a> <a href="http://corp.kaltura.com/technology/video_management">video management</a> <a href="http://corp.kaltura.com/solutions/overview">video solutions</a> <a href="http://corp.kaltura.com/technology/video_player">video player</a> <a rel="media:thumbnail" href="$x7server/p/$x7kalpartnerid/sp/$x7kalsubpartnerid/thumbnail/entry_id/'+eid+'/width/120/height/90/bgcolor/000000/type/2" /> <span property="dc:description" content="" /><span property="media:title" content="x7Video" /> <span property="media:width" content="400" /><span property="media:height" content="330" /> <span property="media:type" content="application/x-shockwave-flash" /><span property="media:duration" content="{DURATION}" /> </object>';
					
					jQuery('a#x7aplaychange').attr("title",eid);
					jQuery('a#x7aeditchange').attr("title",eid);
					jQuery('a#x7aedit2change').attr("title",eid);
					jQuery('a#x7aeditchange').attr("name",name);
					jQuery('a#x7aedit2change').attr("name",name);
					jQuery('a#x7adelchange').attr("title",eid);
					jQuery('textarea#x7embedchange').val(embedcode);
					jQuery(':input#x7hiddeneidchange').val(eid);
					jQuery('img#x7imgchange').attr("src",thumburl);
					
					Shadowbox.init();
					jQuery('div#x7tablewrap').hide('slow');
					jQuery('div#x7form').show('slow');
					var allowpost = '$x7allowposts';
					if (allowpost=='yes'){
						jQuery('div#x7postform').show('slow');
					}
					postout = 'true';
				} else if(postout == 'true')
				{
					jQuery('div#x7form').hide('slow');
					jQuery('div#x7tablewrap').show('slow');
					postout = 'false';
				}
			}
			function formValidate()
			{
				var title = new LiveValidation('title', {onlyOnSubmit: true });
				title.add( Validate.Presence );
				var keywords = new LiveValidation('keywords', {onlyOnSubmit: true });
				//Pattern matches for comma delimited string
				keywords.add( Validate.Format, { pattern: /([^\"]+?)\",?|([^,]+),?|,/ } );
				var password = new LiveValidation('password', {onlyOnSubmit: true });
				password.add( Validate.Presence );
			}
			//Function that retrieves URL get variables
			function getUrlVars()
			{
				var vars = [], hash;
				var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
				for(var i = 0; i < hashes.length; i++)
				    {
				        hash = hashes[i].split('=');
				        vars.push(hash[0]);
				        vars[hash[0]] = hash[1];
				    }
				return vars;
			}
			var map = getUrlVars();
			//Shows the user success or failure feedback
			function addSuccessDiv() {
			if (map.result=="success"){
				jQuery("#x7loading").prepend("<div class='ui-state-error'>Success! Your post has now been queued for moderation by an administrator.</div><br><br>");
			}
			if (map.result=="fail") {
				jQuery("#x7loading").prepend("<div class='ui-state-error'>Post failed! Please try again.</div><br><br>");
			}
			}
			jQuery(document).ready(function() {
				addSuccessDiv();
				jQuery("td.tt[title]").tooltip();
			
			jQuery("#x7entriestable").dataTable({
				"bJQueryUI": true,
				"bPaginate": true,
				"bProcessing": true,
				"bSort": true,
				"sScrollY": "300px",
				"iDisplayLength": 10,
				"sPaginationType": "full_numbers"
			});
			
			jQuery("#x7entriestable tbody tr").live('click', function() {
				var eid = jQuery(this).attr("title");
				var name = jQuery(this).attr("name");
				x7VidPost(eid, name);
			});
			
			}); //end document ready
DELETE_JS;

		$return .= '</script>';
		//ADD X7LOADING DIV
			$return .= "<div id='x7loading' style='display:none'><p><img border='0' src='$pluginurl/all-in-one-video-pack/images/x7loader.gif'></p></div><br /><br />";
		
		//Embed user uploads
		$xmlresult = rest_helper("$x7server/api_v3/?service=media&action=list",
					 array(
						'ks' => $ks,
						'filter:userIdEqual' => $user_login,
						'filter:orderBy' => '-createdAt'
					 ), 'POST'
					 );
						
			//ADD post form
			$return .= <<<X7POSTFORM
			<div class="ui-widget ui-state-highlight ui-corner-all" style="display:none" id="x7form">
				<span style="float:right"><strong>Embed code:</strong><br><textarea id="x7embedchange" cols="25" rows="5"></textarea></span>
				<a onClick="x7VidPlay()" id="x7aplaychange" title=""><strong>Media Entry Details<br><br>
				<img id="x7imgchange" src=""><br><br>[PLAY]</a> |
				<a id="x7aeditchange" name="" title="" onClick="x7VidEditStandard()">[CREATE STANDARD MIX]</a> |
				<a id="x7aedit2change" name="" title="" onClick="x7VidEditAdvanced()">[CREATE ADVANCED MIX]</a> |
				<a id="x7adelchange" title="" onClick="x7VidDelete()">[DELETE]</a>
				<br><br>
				<div id="x7postform" style="display:none">
				<form name="x7postdraft" id="x7postdraft" action="$pluginurl/all-in-one-video-pack/x7post.php" method="post">
				<input type="hidden" name="x7server" id="x7server" value="$x7server" >
				<input type="hidden" name="x7kalpartnerid" id="x7kalpartnerid" value="$x7kalpartnerid" >
				<input type="hidden" name="x7uiconfid" id="x7uiconfid" value="$x7uiconfid" >
				<input type="hidden" name="eid" id="x7hiddeneidchange" value="" >
				<input type="hidden" name="rpcurl" id="rpcurl" value="$x7rpcurl" >
				<input type="hidden" name="username" id="username" value="$user_login" >
				<input type="hidden" name="x7fullplugurl" id="x7fullplugurl" value="$x7fullplugurl" >
				<input type="hidden" name="x7bloghome" id="x7bloghome" value="$x7bloghome" >
				<label for="title">Title of Post:</label><br />
				<input type="text" size="25" name="title" id="title" value="" class="" ><br />
				<label for="category">Category(ies):</label><br />
				<select name="category[]" id="category" multiple="multiple" class="">
				$option
				</select><br />
				<label for="description">Description:</label><br />
				<textarea cols="35" rows="4" name="description" id="description" class="" />Another new video from $user_login!</textarea><br />
				<label for="keywords">Tags (comma delimited):</label><br />
				<input type="text" size="25" name="keywords" id="keywords" value="" class="" ><br />
				<label for="password">Wordpress Password:</label><br />
				<input type="password" name="password" id="password" size="20" ><br />
				<input type="submit" value="[Post]" name="submit" id="submit" ></form>
				<a onClick="x7VidPost();">[Cancel]</a>
				</div>
			</div>
X7POSTFORM;

			$return .= "<div id='x7tablewrap'><table id='x7entriestable'><thead><tr><th>Name</th><th>ID</th><th>Description</th><th>Duration</th><th>When Created</th></tr></thead><tbody>";
			
		foreach ($xmlresult->result->objects->item as $mixentry) {
			$eid = $mixentry->id;
			$thumb = $mixentry->thumbnailUrl;
			$userId = $mixentry->userId;
			$name = $mixentry->name;
			$description = $mixentry->description;
			$duration = $mixentry ->duration;
			$createdat = (string) $mixentry->createdAt;
			$createdat = date(DATE_RFC822, $createdat);
                        //only add if the current user is the uploader
			//if ($userId == $user_login) {
				$return .= <<<ENTRY_DIV
				<tr title="$eid" name="$name">
					<td class="tt" title="Click me to open administration menu!">$name</td>
					<td>$eid</td>
					<td>$description</td>
					<td>$duration</td>
					<td>$createdat</td>
				</tr>
ENTRY_DIV;
			//} //end if user login
		} //end foreach
		//End x7entries table
		$return .= "</tbody></table></div>";
	} //end if widget is user upload gallery
/***********************************************************************************************************************
 * USER CREATED MIXES SHORTCODE *
 * ********************************************************************************************************************/
	if ($widget=="usermixes"){
		//This widget displays the logged in user's Kaltura uploads and offers the ability to
		//play, edit (remix), delete and post them as drafts to the wordpress blog
		//Start Kaltura "Admin" Session
		$kmodel = KalturaModel::getInstance();
		$ks = $kmodel->getAdminSession("","$user_login");
		if (!$ks)
			wp_die(__('Failed to start new session.<br/><br/>'.$closeLink));
		$ksget = urlencode($ks);
		
		//SET RPCURL XMLRPC FILE VALUE
		$x7rpcurl = $x7bloghome . "/xmlrpc.php";
		$x7fullplugurl = plugins_url('/ixr.php', __FILE__);
		$playurl = plugins_url('x7vidplayer.php', __FILE__);
                        $pluginurl = plugins_url();
                        $pluginurlget = urlencode($pluginurl);
                        $advancedediturl = plugins_url('x7advancededitor.php', __FILE__);
			$standardediturl = plugins_url('x7standardeditor.php', __FILE__);
		//GET CATEGORIES LIST
		$categories = get_categories('hide_empty=0'); 
			foreach ($categories as $cat) {
				$option .= "<option value=\"$cat->cat_name\">$cat->cat_name</option>";
			}
		
		//EMBED DELETE JAVASCRIPT FUNCTION AND POST FUNCTION AND GET VARIABLE READER
		$return.= <<<DELETE_JS
		
		<style type="text/css">
		#x7form { width: 500px; }
		.tooltip {
				display:none;
				background:transparent url($pluginurl/all-in-one-video-pack/images/black_arrow.png);
				font-size:12px;
				height:70px;
				width:160px;
				padding:25px;
				color:#fff;	
			}
		</style>

		<script type="text/javascript">
		
			function x7VidPlay()
			{
				var eid = jQuery("a#x7aplaychange").attr("title");
				var playurl = '$playurl'+'?eid='+eid+'&x7kalpartnerid=$x7kalpartnerid&x7server=$x7serverget&x7uiconfid=$x7adminuiconfid';
				Shadowbox.open({
					content: playurl,
					player: "iframe",
					height: "370",
					width: "405"
				});
			}
			
			function x7VidEdit()
			{
				var eid = jQuery("a#x7aeditchange").attr("title");
				var name = jQuery("a#x7aeditchange").attr("name");
				var type = jQuery("a#x7aeditchange").attr("type");
				jQuery('div#x7form').hide('slow');
				jQuery('div#x7tablewrap').show('slow');
				if (type == "1"){
					var editurl = '$standardediturl'+'?entryId='+eid+'&ks=$ksget&x7bloghomeget=$x7bloghomeget&x7kalpartnerid=$x7kalpartnerid&userlogin=$user_login&x7server=$x7serverget&pluginurl=$pluginurlget';
				}
				if (type == "2"){
					var editurl = '$advancedediturl'+'?entryId='+eid+'&ks=$ksget&x7bloghomeget=$x7bloghomeget&x7kalpartnerid=$x7kalpartnerid&userlogin=$user_login&x7server=$x7serverget&pluginurl=$pluginurlget';
				}
				Shadowbox.open({
					content: editurl,
					player: "iframe",
					height: "600",
					width: "1000"
				});
			}
		
			function x7VidDelete()
			{
				var delid = jQuery("a#x7adelchange").attr("title");
				if (confirm("Warning!  This will affect all playlists that contain mix id: " + delid + ". Continue?"))
				{ 
				    jQuery.post(
				       "$pluginurl/x7video/x7delete.php",
				       {'x7bloghome': '$x7bloghome', 'ks': "$ks", 'x7entrytype': 'mix', 'eid': delid, 'x7server': "$x7server"},
				       function ( response ){
					      jQuery("#x7entriestable tbody tr [title="+delid+"]").remove();
					      jQuery('div#x7form').hide('slow');
						jQuery('div#x7tablewrap').show('slow');
						alert("Mix successfully deleted. Reloading table...");
						window.location.reload();
					      //var x7nodes = x7Table.fnGetNodes();
					      //TODO NEED TO REMOVE APPROPRIATE ROW FROM THE TABLE AND REFRESH TABLE
				       });//end post
				} //end confirm
			} //end x7VidDelete
			var postout;
			postout = 'false';
			function x7VidPost(eid, name, type)
			{
				if (postout == 'false'){
					formValidate();
					var thumburl = '$x7server/p/1/sp/$x7kalpartnerid/thumbnail/entry_id/'+eid+'/width/150/height/120';
					var embedcode = '<object id="kaltura_player" name="kaltura_player" type="application/x-shockwave-flash" allowFullScreen="true" allowNetworking="all" allowScriptAccess="always" height="330" width="400" xmlns:dc="http://purl.org/dc/terms/" xmlns:media="http://search.yahoo.com/searchmonkey/media/" rel="media:video" resource="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'" data="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'"><param name="allowFullScreen" value="true" /><param name="allowNetworking" value="all" /><param name="allowScriptAccess" value="always" /><param name="bgcolor" value="#000000" /><param name="flashVars" value="&" /><param name="movie" value="$x7server/index.php/kwidget/cache_st/1283996450/wid/_100/uiconf_id/$x7uiconfid/entry_id/'+eid+'" /><a href="http://corp.kaltura.com">video platform</a> <a href="http://corp.kaltura.com/technology/video_management">video management</a> <a href="http://corp.kaltura.com/solutions/overview">video solutions</a> <a href="http://corp.kaltura.com/technology/video_player">video player</a> <a rel="media:thumbnail" href="$x7server/p/$x7kalpartnerid/sp/$x7kalsubpartnerid/thumbnail/entry_id/'+eid+'/width/120/height/90/bgcolor/000000/type/2" /> <span property="dc:description" content="" /><span property="media:title" content="x7Video" /> <span property="media:width" content="400" /><span property="media:height" content="330" /> <span property="media:type" content="application/x-shockwave-flash" /><span property="media:duration" content="{DURATION}" /> </object>';
					
					jQuery('a#x7aplaychange').attr("title",eid);
					jQuery('a#x7aeditchange').attr("title",eid);
					jQuery('a#x7aeditchange').attr("name",name);
					jQuery('a#x7aeditchange').attr("type",type);
					jQuery('a#x7adelchange').attr("title",eid);
					jQuery('textarea#x7embedchange').val(embedcode);
					jQuery(':input#x7hiddeneidchange').val(eid);
					jQuery('img#x7imgchange').attr("src",thumburl);
					
					Shadowbox.init();
					jQuery('div#x7tablewrap').hide('slow');
					jQuery('div#x7form').show('slow');
					var allowpost = '$x7allowposts';
					if (allowpost=='yes'){
						jQuery('div#x7postform').show('slow');
					}
					postout = 'true';
				} else if(postout == 'true')
				{
					jQuery('div#x7form').hide('slow');
					jQuery('div#x7tablewrap').show('slow');
					postout = 'false';
				}
			}
			function formValidate()
			{
				var title = new LiveValidation('title', {onlyOnSubmit: true });
				title.add( Validate.Presence );
				var keywords = new LiveValidation('keywords', {onlyOnSubmit: true });
				//Pattern matches for comma delimited string
				keywords.add( Validate.Format, { pattern: /([^\"]+?)\",?|([^,]+),?|,/ } );
				var password = new LiveValidation('password', {onlyOnSubmit: true });
				password.add( Validate.Presence );
			}
			//Function that retrieves URL get variables
			function getUrlVars()
			{
				var vars = [], hash;
				var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
				for(var i = 0; i < hashes.length; i++)
				    {
				        hash = hashes[i].split('=');
				        vars.push(hash[0]);
				        vars[hash[0]] = hash[1];
				    }
				return vars;
			}
			var result = getUrlVars()["result"];
			//Shows the user success or failure feedback
			function addSuccessDiv() {
			if (result=="success"){
				alert("Success! Your post has now been queued for moderation by an administrator.");
			}
			if (result=="fail") {
				alert("Post failed! Please try again.");
			}
			}
			jQuery(document).ready(function() {
				addSuccessDiv();
				jQuery("td.tt[title]").tooltip();
				
			x7table = jQuery("#x7entriestable").dataTable({
				"bJQueryUI": true,
				"bPaginate": true,
				"bProcessing": true,
				"bSort": true,
				"sScrollY": "300px",
				"iDisplayLength": 10,
				"sPaginationType": "full_numbers"
			});
			
			jQuery("#x7entriestable tbody tr").live('click', function() {
				var eid = jQuery(this).attr("title");
				var name = jQuery(this).attr("name");
				var type = jQuery(this).attr("type");
				x7VidPost(eid, name, type);
			});
			
			}); //end document ready
DELETE_JS;

		$return .= '</script>';
		//ADD X7LOADING DIV
			$return .= "<div id='x7loading' style='display:none'><p><img border='0' src='$pluginurl/all-in-one-video-pack/images/x7loader.gif'></p></div><br /><br />";
		
		//Embed user uploads
		$xmlresult = rest_helper("$x7server/api_v3/?service=mixing&action=list",
					 array(
						'ks' => $ks,
						'filter:userIdEqual' => $user_login,
						'filter:orderBy' => '-createdAt'
					 ), 'POST'
					 );
						
			//ADD post form
			$return .= <<<X7POSTFORM
			<div class="ui-widget ui-state-highlight ui-corner-all" style="display:none" id="x7form">
				<span style="float:right"><strong>Embed code:</strong><br><textarea id="x7embedchange" cols="25" rows="5"></textarea></span>
				<a onClick="x7VidPlay()" id="x7aplaychange" title=""><strong>Mix Details<br><br>
				<img id="x7imgchange" src=""><br><br>[PLAY]</a> |
				<a id="x7aeditchange" name="" title="" onClick="x7VidEdit()">[EDIT MIX]</a> |
				<a id="x7adelchange" title="" onClick="x7VidDelete()">[DELETE]</a> |
				<a onClick="x7VidPost();">[CANCEL]</a>
				<br><br>
				<div id="x7postform" style="display:none">
				<form name="x7postdraft" id="x7postdraft" action="$pluginurl/all-in-one-video-pack/x7post.php" method="post">
				<input type="hidden" name="x7server" id="x7server" value="$x7server" >
				<input type="hidden" name="x7uiconfid" id="x7uiconfid" value="$x7uiconfid" >
				<input type="hidden" name="eid" id="x7hiddeneidchange" value="" >
				<input type="hidden" name="rpcurl" id="rpcurl" value="$x7rpcurl" >
				<input type="hidden" name="username" id="username" value="$user_login" >
				<input type="hidden" name="x7fullplugurl" id="x7fullplugurl" value="$x7fullplugurl" >
				<input type="hidden" name="x7bloghome" id="x7bloghome" value="$x7bloghome" >
				<label for="title">Title of Post:</label><br />
				<input type="text" size="25" name="title" id="title" value="" class="" ><br />
				<label for="category">Category(ies):</label><br />
				<select name="category[]" id="category" multiple="multiple" class="">
				$option
				</select><br />
				<label for="description">Description:</label><br />
				<textarea cols="35" rows="4" name="description" id="description" class="" />Another new mix from $user_login!</textarea><br />
				<label for="keywords">Tags (comma delimited):</label><br />
				<input type="text" size="25" name="keywords" id="keywords" value="" class="" ><br />
				<label for="password">Wordpress Password:</label><br />
				<input type="password" name="password" id="password" size="20" ><br />
				<input type="submit" value="[Post]" name="submit" id="submit" ></form>
				</div>
			</div>
X7POSTFORM;

			$return .= "<div id='x7tablewrap'><table id='x7entriestable'><thead><tr><th>Name</th><th>ID</th><th>Description</th><th>Duration (s)</th><th>Editor Type</th><th>When Created</th></tr></thead><tbody>";
			
		foreach ($xmlresult->result->objects->item as $mixentry) {
			$eid = $mixentry->id;
			$thumb = $mixentry->thumbnailUrl;
			$userId = $mixentry->userId;
			$name = $mixentry->name;
			$description = $mixentry->description;
			$duration = $mixentry ->duration;
			$editortype = (string) $mixentry->editorType;
			if ($editortype == "1"){
				$editortypestr = "Simple";
				};
			if ($editortype == "2"){
				$editortypestr = "Advanced";
				};
			$createdat = (string) $mixentry->createdAt;
			$createdat = date(DATE_RFC822, $createdat);
                        //only add if the current user is the uploader
			//if ($userId == $user_login) {
				$return .= <<<ENTRY_DIV
				<tr title="$eid" name="$name" type="$editortype">
					<td class="tt" title="Click me to open administration menu!">$name</td>
					<td>$eid</td>
					<td>$description</td>
					<td>$duration</td>
					<td>$editortypestr</td>
					<td>$createdat</td>
				</tr>
ENTRY_DIV;
			//} //end if user login
		} //end foreach
		//End x7entries table
		$return .= "</tbody></table></div>";
	} //end if widget is user mixes
/***********************************************************************************************************************
 * USER SUBMITTED POSTS SHORTCODE *
 * ********************************************************************************************************************/
if ($widget=="userposts"){
		//gotta use wpdb global here to query the database
		global $wpdb;
		//explain that this will only show draft posts
		$return .= <<<WARNING
		
		<style type="text/css">
		.x7drafts { width:680px; }
		</style>
		<div class="ui-widget">
      <div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;"> 
	    <p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span> 
	    <strong>Posts submitted by you and approved for publishing:</strong>
      </p></div>
</div>
<br />
WARNING;

		// Extract drafts from database based on parameters
		$posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_status = 'publish' AND post_author = '$user_ID'");
		// Loop through and output results
		//$return .= var_export($drafts);
		if ($posts) {
			//setup drafts master div
			$return .= "<div class='x7drafts'>";
			foreach ($posts as $post) {
				setup_postdata($post);
				$postid = get_the_id();
				$title = get_the_title($postid);
				$content = get_the_content($postid);
				$author = get_the_author($postid);
				$tags = get_the_tags($postid);
				$cats = get_the_category($postid);
				$date = get_the_date();
				$return .= "<div class='ui-widget ui-state-highlight ui-corner-all' style='padding:10px;'><p>";
				$return .= "Post Title: " . $title . "<br>";
				$return .= "Date Submitted: " . $date . "<br>";
				$return .= "Status: Awaiting Moderation<br>";
				$return .= "Content of post:<br><br><br>";
				//$return .= "Tags: " . foreach ($tags as $tag){echo($tag . ', ')} . "<br>";
				//$return .= "Category(ies): " . foreach ($cats as $cat){echo($cat . ', ')} . "<br><br>";
				$return .= $content . "<br><br>";
				$return .="</p></div>";
			} // end foreach
			//close master drafts div
			$return .= "</div><br />";
			} // end if drafts
		$return .= <<<DRAFTS
		<div class="ui-widget">
      <div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;"> 
	    <p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span> 
	    <strong>Posts submitted by you and awaiting approval:</strong>
      </p></div>
</div>
<br />
DRAFTS;

			// Extract drafts from database based on parameters
		$drafts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_status = 'draft' AND post_author = '$user_ID'");
		// Loop through and output results
		//$return .= var_export($drafts);
		if ($drafts) {
			//setup drafts master div
			$return .= "<div class='x7drafts'>";
			foreach ($drafts as $post) {
				setup_postdata($post);
				$postid = get_the_id();
				$title = get_the_title($postid);
				$content = get_the_content($postid);
				$author = get_the_author($postid);
				$tags = get_the_tags($postid);
				$cats = get_the_category($postid);
				$date = get_the_date();
				$return .= "<div class='ui-widget ui-state-highlight ui-corner-all' style='padding:10px;'><p>";
				$return .= "Post Title: " . $title . "<br>";
				$return .= "Date Submitted: " . $date . "<br>";
				$return .= "Status: Awaiting Moderation<br>";
				$return .= "Content of post:<br><br><br>";
				//$return .= "Tags: " . foreach ($tags as $tag){echo($tag . ', ')} . "<br>";
				//$return .= "Category(ies): " . foreach ($cats as $cat){echo($cat . ', ')} . "<br><br>";
				$return .= $content . "<br><br>";
				$return .="</p></div>";
			} // end foreach
			//close master drafts div
			$return .= "</div>";
			} // end if drafts
		} //end if widget is user posts
/***********************************************************************************************************************
 * MAKE PLAYLIST WIDGET SHORTCODE *
 * ********************************************************************************************************************/
if ($widget=="makeplaylist"){
		//Start Kaltura admin session
		$kmodel = KalturaModel::getInstance();
		$ks = $kmodel->getAdminSession("","$user_login");
		if (!$ks)
			wp_die(__('Failed to start new session.<br/><br/>'.$closeLink));
		$ksget = urlencode($ks);
		$plugin_url = KalturaHelpers::getPluginUrl();
		
		//add javascript and info box TODO - pull out all CSS and put into external file
		$return .= <<<INFOBOX

		<style type="text/css">
			#vidlist { list-style-type: none; margin: 0px; padding: 0px; border: dashed; border-width: thin; }
			#playlist { width: 153px; height: 110px; list-style-type: none; margin: 0px; padding: 0px; border: dashed; border-width: thin; }
			#playlist li, #vidlist li { padding: 5px; font-size: 1.2em; width: 135px; height: 100px; border: dashed; border-width: thin; }
			#vidlistdiv, #betweendiv, #playlistdiv { float: left; padding:15px; }
			#betweendiv { padding-top: 120px; }
			textarea#listname { width: 160px; height: 20px; border: 3px solid #cccccc; padding: 5px; font-family: Tahoma, sans-serif; }
		</style>
		<script type="text/javascript">
		
		//list users created playlists
		jQuery(document).ready(function() {
		//jQuery('#x7loading').html('<p><img border="0" src="$plugin_url/images/x7loader.gif"></p>');
		jQuery("#vidlist, #playlist").sortable({
			connectWith: '.connectedSortable',
			revert: 'true',
			tolerance: 'pointer',
			placeholder: 'ui-state-highlight'
		}).disableSelection();
		jQuery(".draggable").draggable({
			cursor: 'crosshair',
			cursorAt: { top: 50, left: 50 },
			opacity: '0.6',
			containment: '#x7wrapdiv',
			revert: 'valid',
			revertDuration: '1000',
		});
		jQuery("#listname").val("Playlist Name Here");
	    });//end document ready
		
		function x7ListPreview()
		{
			var valError = "noerror";
			arrEids = []; //clear out the eids array
			var listname;
			jQuery("#playlist li").each(
				function( intindex ){
					arrEids[intindex] = jQuery( this ).attr("eid");
				});
			if (arrEids.length < 2)
			{
				valError = "error";
				alert("New playlists must contain at least two videos!");
			}
			listname = jQuery("#listname").val(); //get entered listname text
			if (listname.length < 5)
			{
				valError = "error";
				alert("Playlist name must contain at least five characters!");
			}
			if (valError != "error")
			{
				jQuery('#x7loading').html('<p><img border="0" src="$plugin_url/images/x7loader.gif"></p>');
				jQuery.post(
					"$plugin_url/x7listadd.php",
					{'x7server': "$x7server", 'x7kalpartnerid': "$x7kalpartnerid", 'ks': "$ks", 'eids[]': arrEids, 'listname': listname, 'ul': "$user_login", 'x7bloghome': "$x7bloghome"},
					function ( data ){
						jQuery("#x7loading").html('');
						if (data != "error"){
							var theUrl;
							theUrl = "$plugin_url/x7listplayer.php";
							Shadowbox.open({
							content:    theUrl + "?listid=" + data + "&x7kalpartnerid=$x7kalpartnerid&x7serverget=$x7serverget&x7pluiconfid=$x7pluiconfid",
							player:     "iframe",
							height:     400,
							width:      800
							});
						} else {
							alert("Error creating playlist.");
						}; //end if not server data returned error
					}); //end post
	    };//end if not valerror error
		}//end x7listpreview
		
		</script>
		<div class="ui-widget">
      <div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"> 
	    <p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
	    <strong>Quick tip:  </strong>This page lets you make playlists only from videos and mixes that you yourself have uploaded and made.</p>
      </div>
		</div>
		<br>
		<br>
		<div id="wrapdiv" style="width:500px;margin-left:auto;margin-right:auto;">
		<div id="vidlistdiv" style="height:1000px">
			<h3>Your Movies</h3>
			<ul id="vidlist" class="connectedSortable">
INFOBOX;
		$mediaresult = rest_helper("$x7server/api_v3/?service=media&action=list",
					 array(
						'ks' => $ks,
						'filter:userIdEqual' => $user_login,
						'filter:orderBy' => '-createdAt'
					 ), 'POST'
					 );
		foreach ($mediaresult->result->objects->item as $mediaentry) {
			$eid = $mediaentry->id;
			$thumb = $mediaentry->thumbnailUrl;
			$userId = $mediaentry->userId;
			$name = $mediaentry->name;
			$description = $mediaentry->description;
			$duration = $mediaentry ->duration;
			$return .= <<<ENTRY_DIV
				<li class="ui-state-default" eid="$eid">
				<img style="padding-top:10px" eid="$eid" title="Media - $name, $duration seconds" src="$thumb">
				</li>
ENTRY_DIV;
		} //end foreach
		$mixresult = rest_helper("$x7server/api_v3/?service=mixing&action=list",
					 array(
						'ks' => $ks,
						'filter:userIdEqual' => $user_login,
						'filter:orderBy' => '-createdAt'
					 ), 'POST'
					 );
		foreach ($mixresult->result->objects->item as $mixentry) {
			$eid = $mixentry->id;
			$thumb = $mixentry->thumbnailUrl;
			$userId = $mixentry->userId;
			$name = $mixentry->name;
			$description = $mixentry->description;
			$duration = $mixentry ->duration;
			$return .= <<<ENTRY_DIV2
				<li class="ui-state-default" eid="$eid">
				<img style="padding-top:10px" eid="$eid" title="Mix - $name, $duration seconds" src="$thumb">
				</li>
ENTRY_DIV2;
		} //end foreach
		$return .= <<<INFOBOX2
		
			</ul>
		</div>
		<div id="betweendiv">
			<div id="x7loading"></div>
			DRAG==><br>AND<br>
			<==DROP!
		</div>
		<div id="playlistdiv">
			<h3>New Playlist</h3>
			<a onclick="x7ListPreview()">[Save and Preview]</a><br />
			<textarea id="listname"></textarea>
			<ul id="playlist" class="connectedSortable">
			</ul>
		</div>
		</div>
INFOBOX2;
		
	} // end if widget is makeplaylist
/***********************************************************************************************************************
 * VIEW USER PLAYLISTS WIDGET SHORTCODE *
 * ********************************************************************************************************************/
if ($widget=="userplaylists"){
	
		//Start Kaltura admin session
		$kmodel = KalturaModel::getInstance();
		$ks = $kmodel->getAdminSession("","$user_login");
		if (!$ks)
			wp_die(__('Failed to start new session.<br/><br/>'.$closeLink));
		$ksget = urlencode($ks);
		$pluginurl = KalturaHelpers::getPluginUrl();
		$pluginurlget = urlencode($pluginurl);
		
		//SET RPCURL XMLRPC FILE VALUE
		$x7rpcurl = $x7bloghome . "/xmlrpc.php";
		$x7fullplugurl = plugins_url('/ixr.php', __FILE__);
		$playurl = plugins_url('x7vidplayer.php', __FILE__);
                $editurl = plugins_url('x7advancededitor.php', __FILE__);
		//GET CATEGORIES LIST
		$categories = get_categories('hide_empty=0'); 
			foreach ($categories as $cat) {
				$option .= "<option value=\"$cat->cat_name\">$cat->cat_name</option>";
			}
		
		//add javascript and styles
		$return .= <<<USERPLJS
		
		<style type="text/css">
		
			#x7wrapdiv, #x7loading { display: none; }
			#sortable, #trash { list-style-type: none; margin: 0; padding: 0; width: 100px; }
			#sortable li, #trash li { margin: 0 5px 5px 5px; padding: 5px; width: 90px; height: 60px; }
			html>body #sortable li { height: 61px; line-height: 1.2em; }
			html>body #trash li { height: 91px; line-height: 1.2em; }
			.ui-state-highlight { height: 1.5em; line-height: 1.2em; }
			textarea#listname { width: 160px; height: 20px; border: 3px solid #cccccc; padding: 5px; font-family: Tahoma, sans-serif; }	    
			/* root element for scrollable */
.vertical {  
	
	/* required settings */
	position:relative;
	overflow:hidden;	

	/* vertical scrollers have typically larger height than width */	
	height: 270px;	 
	width: 550px;
	border-top:1px solid #ddd;	
}

/* root element for scrollable items */
.items {	
	position:absolute;
	
	/* this time we have very large space for height */	
	height:20000em;	
	margin: 0px;
}

/* single scrollable item */
.item {
	border-bottom:1px solid #ddd;
	margin:10px 0;
	padding:15px;
	font-size:12px;
	height:100px;
}

/* elements inside single item */
.item img {
	float:left;
	margin-right:20px;
	height:90px;
	width:110px;
}

.item h3 {
	margin:0 0 5px 0;
	font-size:16px;
	color:#456;
	font-weight:normal;
}

/* the action buttons above the scrollable */
#actions {
	width:500px;
	margin:30px 0 10px 0;	
}

#actions a {
	font-size:11px;		
	cursor:pointer;
	color:#666;
}

#actions a:hover {
	text-decoration:underline;
	color:#000;
}

.disabled {
	visibility:hidden;		
}

.next {
	float:right;
}	
		</style>
		<script type="text/javascript">
		//Function that retrieves URL get variables
			function getUrlVars()
			{
				var vars = [], hash;
				var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
				for(var i = 0; i < hashes.length; i++)
				    {
				        hash = hashes[i].split('=');
				        vars.push(hash[0]);
				        vars[hash[0]] = hash[1];
				    }
				return vars;
			}
			var map = getUrlVars();
			//Shows the user success or failure feedback
			function addSuccessDiv() {
			if (map.result=="success"){
				alert("Success! Your post has now been queued for moderation by an administrator.");
			}
			if (map.result=="fail") {
				alert("Post failed! Please try again.");
			}
			} //end addsuccessdiv
			
			function formValidate()
			{
				var title = new LiveValidation('title', {onlyOnSubmit: true });
				title.add( Validate.Presence );
				var keywords = new LiveValidation('keywords', {onlyOnSubmit: true });
				//Pattern matches for comma delimited string
				keywords.add( Validate.Format, { pattern: /([^\"]+?)\",?|([^,]+),?|,/ } );
				var password = new LiveValidation('password', {onlyOnSubmit: true });
				password.add( Validate.Presence );
			} //end validate
			
		jQuery(document).ready(function() {
			addSuccessDiv();
			//jQuery('#x7loading').html('<p><img border="0" src="$pluginurl/images/x7loader.gif"></p>');
			
			//make playlist entries sortable
			jQuery("#sortable").sortable({
				placeholder: 'ui-state-highlight'
			});
			jQuery("#sortable").disableSelection();
			
			//make playlists scrollable
			jQuery(".scrollable").scrollable({
				    vertical:true
			      });
			
			jQuery(".item").mouseover(function(){
				jQuery(this).addClass("ui-state-default");  
			}).mouseout(function(){
				jQuery(this).removeClass("ui-state-default");  
			});
			
			jQuery("#droppable").droppable({
				activeClass: 'ui-state-hover',
				hoverClass: 'ui-state-active',
				drop: function(event, ui) {
					var eid = ui.draggable.attr("eid");
					jQuery("#sortable li[eid="+ eid +"]").hide('slow');
					jQuery("#sortable li[eid="+ eid +"]").remove();
				}
			});
			}); //end document ready
		function x7ListPost(eid)
		{
				formValidate();
				jQuery(':input#x7hiddeneidchange').val(eid);
				jQuery('#x7listwrap').hide('slow');
				jQuery("#x7form").show('slow');
				postout='true';
		}
		
		function x7FormClose()
		{
			jQuery("#x7form").hide('slow');
			jQuery('#x7listwrap').show('slow');
		}
	  
		function x7VidDelete(delid)
			{
				if (confirm("Are you sure you want to delete playlist ID: " + delid))
				{ 
				    jQuery.post(
				       "$pluginurl/x7delete.php",
				       {'x7bloghome': '$x7bloghome', 'ks': "$ks", 'x7entrytype': 'playlist', 'eid': delid, 'x7server': "$x7server"},
				       function ( response ){
					      jQuery("div#"+delid).remove();
						alert("Playlist ID: "+delid+" successfully deleted.");
				       });//end post
				} //end confirm
			} //end x7VidDelete
			
			function x7VidPlay(theEntry)
			{
				x7EditClose();
				var theUrl;
				theUrl = "$pluginurl/x7listplayer.php";
			    Shadowbox.open({
			    content:    theUrl + "?listid=" + theEntry + "&x7kalpartnerid=$x7kalpartnerid&x7serverget=$x7serverget&x7pluiconfid=$x7pluiconfid",
			    player:     "iframe",
			    height:     330,
			    width:      740
			    });
			}
			
			function x7VidEdit(eid, name)
			{
				var theUrl = "$pluginurl/x7pledit.php";
				Shadowbox.open({
					content: theUrl + "?ks=$ksget&x7bloghomeget=$x7bloghomeget&x7server=$x7serverget&x7kalpartnerid=$x7kalpartnerid&pluginurl=$pluginurlget&eid="+eid+"&listname="+name,
					player: "iframe",
					height: 700,
					width: 400
				});
			}//end x7videdit
		
		function x7EditClose()
		{
			jQuery("#x7wrapdiv").hide('slow');
			jQuery('#x7listwrap').show('slow');
		}
			</script>
			
			<div class="ui-widget ui-state-highlight ui-corner-all ui-helper-clearfix" style="display:none;width:auto;height:auto;" id="x7form">
				<div id="x7postform">
				<form name="x7postlist" id="x7postlist" action="$pluginurl/x7plpost.php" method="post">
				<input type="hidden" name="x7server" id="x7server" value="$x7server" >
				<input type="hidden" name="x7kalpartnerid" id="x7kalpartnerid" value="$x7kalpartnerid" >
				<input type="hidden" name="x7pluiconfid" id="x7pluiconfid" value="$x7pluiconfid" >
				<input type="hidden" name="eid" id="x7hiddeneidchange" value="" >
				<input type="hidden" name="rpcurl" id="rpcurl" value="$x7rpcurl" >
				<input type="hidden" name="username" id="username" value="$user_login" >
				<input type="hidden" name="x7fullplugurl" id="x7fullplugurl" value="$x7fullplugurl" >
				<input type="hidden" name="x7bloghome" id="x7bloghome" value="$x7bloghome" >
				<label for="title">Title of Post:</label><br />
				<input type="text" size="25" name="title" id="title" value="" class="" ><br />
				<label for="category">Category(ies):</label><br />
				<select name="category[]" id="category" multiple="multiple" class="">
				$option
				</select><br />
				<label for="description">Description:</label><br />
				<textarea cols="35" rows="4" name="description" id="description" class="" />Another new playlist from $user_login!</textarea><br />
				<label for="keywords">Tags (comma delimited):</label><br />
				<input type="text" size="25" name="keywords" id="keywords" value="" class="" ><br />
				<label for="password">Wordpress Password:</label><br />
				<input type="password" name="password" id="password" size="20" ><br />
				<input type="submit" value="[Post]" name="submit" id="submit" ></form>
				<a onClick="x7FormClose();">[Cancel]</a>
				</div>
			</div>
			
			<div id="x7loading"></div><br><br>
			
<div id="x7listwrap">
<div id="actions">
	<a class="prev">&laquo; Previous</a>
	<a class="next">More playlists &raquo;</a>
</div>
<div class="scrollable vertical">
<div class="items">
USERPLJS;
$plresult = rest_helper("$x7server/api_v3/?service=playlist&action=list",
					 array(
						'ks' => $ks,
						'filter:userIdEqual' => $user_login,
						'filter:orderBy' => '-createdAt'
					 ), 'POST'
					 );
		foreach ($plresult->result->objects->item as $plentry) {
			$eid = $plentry->id;
			$thumb = $plentry->thumbnailUrl;
			$userId = $plentry->userId;
			$name = $plentry->name;
			$description = $plentry->description;
			$duration = $plentry ->duration;
			$return .= <<<ENTRY_DIV3
				<div class="item ui-widget-content ui-corner-all" id="$eid">
				<strong>Name</strong>: $name<br>
				<img alt="$eid" class="tt" src="$pluginurl/images/playlist.png" height="90" width="90">
				<a onClick="x7VidPlay('$eid')">[PLAY]</a> |
				<a onClick="x7VidEdit('$eid', '$name')">[EDIT]</a> |
				<a onClick="x7VidDelete('$eid')">[DELETE]</a> |
				<a onClick="x7ListPost('$eid')">[POST]</a>
				<span style="margin-left:20px;float:right;">
				<strong>Embed code:</strong><br>
				<textarea cols="20" rows="2"><object id="kaltura_player" name="kaltura_player" type="application/x-shockwave-flash" allowFullScreen="true" allowNetworking="all" allowScriptAccess="always" height="620" width="400" xmlns:dc="http://purl.org/dc/terms/" xmlns:media="http://search.yahoo.com/searchmonkey/media/" rel="media:video" resource="$x7server/index.php/kwidget/cache_st/1284005068/wid/_$x7kalpartnerid/uiconf_id/$x7pluiconfid" data="$x7server/index.php/kwidget/cache_st/1284005068/wid/_$x7kalpartnerid/uiconf_id/$x7pluiconfid"><param name="allowFullScreen" value="true" /><param name="allowNetworking" value="all" /><param name="allowScriptAccess" value="always" /><param name="bgcolor" value="#000000" /><param name="flashVars" value="playlistAPI.autoContinue=true&playlistAPI.autoInsert=true&playlistAPI.kpl0Name=test&playlistAPI.kpl0Url=$x7serverget%2Findex.php%2Fpartnerservices2%2Fexecuteplaylist%3Fuid%3D%26partner_id%3D$x7kalpartnerid%26subp_id%3D$x7kalsubpartnerid%26format%3D8%26ks%3D%7Bks%7D%26playlist_id%3D$eid&" /><param name="movie" value="$x7server/index.php/kwidget/cache_st/1284005068/wid/_$x7kalpartnerid/uiconf_id/$x7pluiconfid" /><a href="http://corp.kaltura.com">video platform</a> <a href="http://corp.kaltura.com/technology/video_management">video management</a> <a href="http://corp.kaltura.com/solutions/overview">video solutions</a> <a href="http://corp.kaltura.com/technology/video_player">video player</a> {SEO} </object></textarea>
				</span>
				</div>
ENTRY_DIV3;

		} //end foreach
		$return .= "</div></div></div>";
	
	}//end if widget is user playlists
	
} else { //not logged in
	$return = "Sorry, but you must be a logged in registered user for access.";
} //end logged in check
	return "$return";
} //end x7video shortcode

function kaltura_shortcode($attrs) 
{
	// for wordpress 2.5, in wordpress 2.6+ shortcodes are striped in rss feedds
	if (is_feed())
		return "";

	// prevent xss
	foreach($attrs as $key => $value)
	{
		$attrs[$key] = js_escape($value);
	}
	
	// get the embed options from the attributes
	$embedOptions = _kaltura_get_embed_options($attrs);

	$isComment		= (@$attrs["size"] == "comments") ? true : false;
	$wid 			= $embedOptions["wid"];
	$entryId 		= $embedOptions["entryId"];
	$width 			= $embedOptions["width"];
	$height 		= $embedOptions["height"];
	$randId 		= md5($wid . $entryId . rand(0, time()));
	$divId 			= "kaltura_wrapper_" . $randId;
	$thumbnailDivId = "kaltura_thumbnail_" . $randId;
	$playerId 		= "kaltura_player_" . $randId;

	$link = '';
	$link .= '<a href="http://corp.kaltura.com/">open source video</a>, ';
	$link .= '<a href="http://corp.kaltura.com/">online video platform</a>, ';
	$link .= '<a href="http://corp.kaltura.com/video_platform/video_streaming">video streaming</a>, ';
	$link .= '<a href="http://corp.kaltura.com/solutions/video_solutions">video solutions</a>';
	
	$powerdByBox ='<div class="poweredByKaltura" style="width: ' . $embedOptions["width"] . 'px; "><div><a href="http://corp.kaltura.com/video_platform/video_player" target="_blank">Video Player</a> by <a href="http://corp.kaltura.com/" target="_blank">Kaltura</a></div></div>';
	
	if ($isComment)
	{
		$thumbnailPlaceHolderUrl = KalturaHelpers::getCommentPlaceholderThumbnailUrl($wid, $entryId, 240, 180, null);

		$embedOptions["flashVars"] .= "&autoPlay=true";
		$html = '
				<div id="' . $thumbnailDivId . '" style="width:'.$width.'px;height:'.$height.'px;" class="kalturaHand" onclick="Kaltura.activatePlayer(\''.$thumbnailDivId.'\',\''.$divId.'\');">
					<img src="' . $thumbnailPlaceHolderUrl . '" style="" />
				</div>
				<div id="' . $divId . '" style="height: '.$height.'px"">'.$link.'</div>
				<script type="text/javascript">
					jQuery("#'.$divId.'").hide();
					var kaltura_swf = new SWFObject("' . $embedOptions["swfUrl"] . '", "' . $playerId . '", "' . $width . '", "' . $height . '", "9", "#000000");
					kaltura_swf.addParam("wmode", "opaque");
					kaltura_swf.addParam("flashVars", "' . $embedOptions["flashVars"] . '");
					kaltura_swf.addParam("allowScriptAccess", "always");
					kaltura_swf.addParam("allowFullScreen", "true");
					kaltura_swf.addParam("allowNetworking", "all");
					kaltura_swf.write("' . $divId . '");
				</script>
		';
	}
	else
	{
		$style = '';
		$style .= 'width:' . $embedOptions["width"] .'px;';
		$style .= 'height:' . ($embedOptions["height"] + 10) . 'px;'; // + 10 is for the powered by div
		if (@$embedOptions["align"])
			$style .= 'float:' . $embedOptions["align"] . ';';
			
		// append the manual style properties
		if (@$embedOptions["style"])
			$style .= $embedOptions["style"];
			
		$html = '
				<span id="'.$divId.'" style="'.$style.'">'.$link.'</span>
				<script type="text/javascript">
					var kaltura_swf = new SWFObject("' . $embedOptions["swfUrl"] . '", "' . $playerId . '", "' . $embedOptions["width"] . '", "' . $embedOptions["height"] . '", "9", "#000000");
					kaltura_swf.addParam("wmode", "opaque");
					kaltura_swf.addParam("flashVars", "' . $embedOptions["flashVars"] . '");
					kaltura_swf.addParam("allowScriptAccess", "always");
					kaltura_swf.addParam("allowFullScreen", "true");
					kaltura_swf.addParam("allowNetworking", "all");
					kaltura_swf.write("' . $divId . '");
				';
		if (KalturaHelpers::compareWPVersion("2.6", ">=")) {
			$html .= '
					jQuery("#'.$divId.'").append("'.str_replace("\"", "\\\"", $powerdByBox).'"); 
				';
			//                                              ^ escape quotes for javascript ^
		}
		$html .= '</script>'; 
	}
		
	return $html;
}
function kaltura_get_version() 
{
	$plugin_data = implode( '', file( str_replace('all_in_one_video_pack.php', 'interactive_video.php', __FILE__)));
	if ( preg_match( "|Version:(.*)|i", $plugin_data, $version ))
		$version = trim( $version[1] );
	else
		$version = '';
	
	return $version;
}

function _kaltura_get_embed_options($params) 
{
	if (@$params["size"] == "comments") // comments player
	{
		if (get_option('kaltura_comments_player_type'))
			$type = get_option('kaltura_comments_player_type');
		else
			$type = get_option('kaltura_default_player_type'); 
			
		// backward compatibility
		if ($type == "whiteblue")
			$params["uiconfid"] = 530;
		elseif ($type == "dark")
			$params["uiconfid"] = 531;
		elseif ($type == "grey")
			$params["uiconfid"] = 532;
		elseif ($type)
			$params["uiconfid"] = $type;
		else 
		{
			global $KALTURA_DEFAULT_PLAYERS;
			$params["uiconfid"] = $KALTURA_DEFAULT_PLAYERS[0]["id"];
		}
			
		$params["width"] = 250;
		$params["height"] = 244;
		$layoutId = "tinyPlayer";
	}
	else 
	{ 
		// backward compatibility
		switch($params["size"])
		{
			case "large":
				$params["width"] = 410;
				$params["height"] = 364;
				break;
			case "small":
				$params["width"] = 250;
				$params["height"] = 244;
				break;
		}
		
		// if width is missing set some default
		if (!@$params["width"]) 
			$params["width"] = 400;

		// if height is missing, recalculate it
		if (!@$params["height"])
		{
			require_once("lib/kaltura_model.php");
			$params["height"] = KalturaHelpers::calculatePlayerHeight(get_option('kaltura_default_player_type'), $params["width"]);
		}
			
		// check the permissions
		$kdp3LayoutFlashVars = "";
		$externalInterfaceDisabled = null;
		if (KalturaHelpers::userCanEdit(@$params["editpermission"]))
		{
			$layoutId = "full";
			$externalInterfaceDisabled = false;
			$kdp3LayoutFlashVars .= _kdp3_upload_layout_flashvars(true);
			$kdp3LayoutFlashVars .= "&";
			$kdp3LayoutFlashVars .= _kdp3_edit_layout_flashvars(true);
		}
		else if (KalturaHelpers::userCanAdd(@$params["addpermission"]))
		{
			$layoutId = "addOnly";
			$externalInterfaceDisabled = false;
			$kdp3LayoutFlashVars .= _kdp3_upload_layout_flashvars(true);
			$kdp3LayoutFlashVars .= "&";
			$kdp3LayoutFlashVars .= _kdp3_edit_layout_flashvars(false);
		}
		else
		{ 
			$layoutId = "playerOnly";
			$kdp3LayoutFlashVars .= _kdp3_upload_layout_flashvars(false);
			$kdp3LayoutFlashVars .= "&";
			$kdp3LayoutFlashVars .= _kdp3_edit_layout_flashvars(false);
		}
			
		if ($params["size"] == "large_wide_screen")  // FIXME: temp hack
			$layoutId .= "&wideScreen=1";
	}
	
	// align
	switch ($params["align"])
	{
		case "r":
		case "right":
			$align = "right";
			break;
		case "m": 
		case "center":
			$align = "center";
			break;
		case "l":
		case "left":
			$align = "left";
			break;
		default:
			$align = "";			
	}
		
	if ($_SERVER["SERVER_PORT"] == 443)
		$protocol = "https://";
	else
		$protocol = "http://";
		 
	$postUrl = $protocol . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

	$flashVarsStr = "";
	$flashVarsStr .=  "layoutId=" . $layoutId;
	$flashVarsStr .= ("&" . $kdp3LayoutFlashVars);
	if ($externalInterfaceDisabled === false)
		$flashVarsStr .= "&externalInterfaceDisabled=false";

	
	$wid = $params["wid"];
	$swfUrl = KalturaHelpers::getSwfUrlForWidget($wid);

	if (isset($params["uiconfid"]))
		$swfUrl .= "/uiconf_id/".$params["uiconfid"];
		
	$entryId = null;
	if (isset($params["entryid"]))
	{
		$entryId = $params["entryid"];
		$swfUrl .= "/entry_id/".$entryId;
	}
		
	return array(
		"flashVars" => $flashVarsStr,
		"height" => $params["height"],
		"width" => $params["width"],
		"align" => $align,
		"style" => @$params["style"],
		"wid" => $wid,
		"entryId" => $entryId,
		"swfUrl" => $swfUrl
	);
}

function _kaltura_find_post_widgets($args) 
{
	$wid = isset($args["wid"]) ? $args["wid"] : null;
	$entryId = isset($args["entryid"]) ? $args["entryid"] : null;
	if (!$wid && !$entryId)
		return;
		
	global $kaltura_post_id;
	global $kaltura_widgets_in_post;
	$kaltura_widgets_in_post[] = array($wid, $entryId); // later will use it to delete the widgets that are not in the post 
	
	$widget = array();
	$widget["id"] = $wid;
	$widget["entry_id"] = $entryId;
	$widget["type"] = KALTURA_WIDGET_TYPE_POST;
	$widget["add_permissions"] = $args["addpermission"];
	$widget["edit_permissions"] = $args["editpermission"];
	$widget["post_id"] = $kaltura_post_id;
	$widget["status"] = KALTURA_WIDGET_STATUS_PUBLISHED;
	$widget = KalturaWPModel::insertOrUpdateWidget($widget);
}

function _kaltura_find_comment_widgets($args)
{
	$wid = isset($args["wid"]) ? $args["wid"] : null;
	$entryId = isset($args["entryid"]) ? $args["entryid"] : null;
	if (!$wid && !$entryId)
		return;
		
	if (!$wid)
		$wid = "_" . get_option("kaltura_partner_id");
		
	global $kaltura_comment_id;
	$comment = get_comment($kaltura_comment_id);
	
	// add new widget
	$widget = array();
	$widget["id"] = $wid;
	$widget["entry_id"] = $entryId;
	$widget["type"] = KALTURA_WIDGET_TYPE_COMMENT;
	$widget["post_id"] = $comment->comment_post_ID;
	$widget["comment_id"] = $kaltura_comment_id;
	$widget["status"] = KALTURA_WIDGET_STATUS_PUBLISHED;
	
	$widget = KalturaWPModel::insertOrUpdateWidget($widget);
}

function _kdp3_edit_layout_flashvars($enabled) {
	$enabled = ($enabled) ? 'true' : 'false';
	$params = array(
		"editBtnControllerScreen.includeInLayout" => $enabled,
		"editBtnControllerScreen.visible" => $enabled,
		"editBtnStartScreen.includeInLayout" => $enabled,
		"editBtnStartScreen.visible" => $enabled,
		"editBtnPauseScreen.includeInLayout" => $enabled,
		"editBtnPauseScreen.visible" => $enabled,
		"editBtnPlayScreen.includeInLayout" => $enabled,
		"editBtnPlayScreen.visible" => $enabled,
		"editBtnEndScreen.includeInLayout" => $enabled,
		"editBtnEndScreen.visible" => $enabled,
	);
	return http_build_query($params);
}

function _kdp3_upload_layout_flashvars($enabled) {
	$enabled = ($enabled) ? 'true' : 'false';
	$params = array(
		"uploadBtnControllerScreen.includeInLayout" => $enabled,
		"uploadBtnControllerScreen.visible" => $enabled,
		"uploadBtnStartScreen.includeInLayout" => $enabled,
		"uploadBtnStartScreen.visible" => $enabled,
		"uploadBtnPauseScreen.includeInLayout" => $enabled,
		"uploadBtnPauseScreen.visible" => $enabled,
		"uploadBtnPlayScreen.includeInLayout" => $enabled,
		"uploadBtnPlayScreen.visible" => $enabled,
		"uploadBtnEndScreen.includeInLayout" => $enabled,
		"uploadBtnEndScreen.visible" => $enabled,
	);
	return http_build_query($params);
}
		
if ( !get_option('kaltura_partner_id') && !isset($_POST['submit']) && !strpos($_SERVER["REQUEST_URI"], "page=interactive_video")) {
	function kaltura_warning() {
		echo "
		<div class='updated fade'><p><strong>".__('To complete the All in One Video Pack installation, <a href="'.get_settings('siteurl').'/wp-admin/options-general.php?page=interactive_video">you must get a Partner ID.</a>')."</strong></p></div>
		";
	}
	add_action('admin_notices', 'kaltura_warning');
}
//SETTINGS PAGE!!!
function x7_settings_page() {
?>
<div class="wrap">
<h2>x7Host UGC Video Plugin Settings</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'x7-settings-group' ); ?>
    <table class="form-table">
	<strong>ALL options fields must be filled in for proper operation of the plugin.</strong>
        
	<tr valign="top">
        <th scope="row">KalturaCE Default Video Player UIConfID (Single Video)</th>
        <td><input type="text" name="x7uiconfid" value="<?php echo get_option('x7uiconfid'); ?>" /></td>
        <td><em>Example: 172876 (find your UIConfID in the Application Studio of <a href="<?php echo get_option('x7server'); ?>/kmc" target="_new">your KMC</a>)</em></td>
		</tr>
	
	<tr valign="top">
        <th scope="row">KalturaCE Default Video Player UIConfID (Playlist)</th>
        <td><input type="text" name="x7pluiconfid" value="<?php echo get_option('x7pluiconfid'); ?>" /></td>
        <td><em>Example: 172877 (find your UIConfID in the Application Studio of <a href="<?php echo get_option('x7server'); ?>/kmc" target="_new">your KMC</a>)</em></td>
		</tr>
	
	<tr valign="top">
        <th scope="row">KalturaCE Default Video Player Admin UIConfID (Logged In Users)</th>
        <td><input type="text" name="x7adminuiconfid" value="<?php echo get_option('x7adminuiconfid'); ?>" /></td>
        <td><em>Example: 172878 (this is the player that you configure with extra abilities, such as downloading and capturing thumbnails, displayed only to your logged in users - it can be the same as your regular single video player)</em></td>
		</tr>
		
	<tr valign="top">
        <th scope="row">KalturaCE Default KCW UIConfID</th>
        <td><input type="text" name="x7kcwuiconfid" value="<?php echo get_option('x7kcwuiconfid'); ?>" /></td>
        <td><em>Example: 1727883 (Default KalturaCE KCW UIConfID is 1727883, change this to use custom UIConf)</em></td>
		</tr>
	
	<tr valign="top">
        <th scope="row">Allow User Posts?</th>
        <td><input type="text" name="x7allowposts" value="<?php echo get_option('x7allowposts'); ?>" /></td>
        <td><em>Example: MUST be either "yes" or "no" to allow or disallow user posting of entries, mixes and playlists.</em></td>
		</tr>
	
	<tr valign="top">
        <th scope="row">Allow Standard Editor?</th>
        <td><input type="text" name="x7allowstandard" value="<?php echo get_option('x7allowstandard'); ?>" /></td>
        <td><em>Example: MUST be either "yes" or "no" to allow or disallow use of the Standard Editor.</em></td>
		</tr>
	
	<tr valign="top">
        <th scope="row">Allow Advanced Editor?</th>
        <td><input type="text" name="x7allowadvanced" value="<?php echo get_option('x7allowadvanced'); ?>" /></td>
        <td><em>Example: MUST be either "yes" or "no" to allow or disallow use of the Advanced Editor.</em></td>
		</tr>
	
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php } ?>
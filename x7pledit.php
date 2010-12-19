<?php

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

$ks = $_GET['ks'];
$ks = urldecode($ks);
$x7server = $_GET['x7server'];
$x7server = urldecode($x7server);
$pluginurl = $_GET['pluginurl'];
$pluginurl = urldecode($pluginurl);
$eid = $_GET['eid'];
$listname = $_GET['listname'];
$x7kalpartnerid = $_GET['x7kalpartnerid'];
$x7kalsubpartnerid = $x7kalpartnerid . "00";
$x7bloghome = urldecode($_GET['x7bloghomeget']);

if ( eregi ( "$x7bloghome", $_SERVER['HTTP_REFERER'] ) )
{
    //get list of entries for editing current playlist
$plentryresult = rest_helper("$x7server/api_v3/?service=playlist&action=get",
					 array(
						'ks' => $ks,
						'id' => $eid
					 ), 'POST'
					 );
} else {
    exit;
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head profile="http://gmpg.org/xfn/11">
<title>x7 Playlist Editor</title>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js"></script>
<script type="text/javascript" src='http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js'></script>
<script type='text/javascript' src="<?php echo($pluginurl); ?>/js/jquery.tools.min.js"></script>
<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js?ver=3.0.1'></script>
<script type='text/javascript' src='<?php echo($pluginurl); ?>/js/shadowbox.js?ver=3.0.1'></script>
<script type='text/javascript' src='<?php echo($pluginurl); ?>/js/x7js.js?ver=3.0.1'></script>
<script type='text/javascript' src='<?php echo($pluginurl); ?>/js/validator.js?ver=3.0.1'></script>
<style type="text/css">
    #sortable, #trash { list-style-type: none; margin: 0; padding: 0; width: 100px; }
    #sortable li, #trash li { margin: 0 5px 5px 5px; padding: 5px; width: 90px; height: 60px; }
    html>body #sortable li { height: 61px; line-height: 1.2em; }
    html>body #trash li { height: 91px; line-height: 1.2em; }
    .ui-state-highlight { height: 1.5em; line-height: 1.2em; }
    textarea#listname { width: 160px; height: 20px; border: 3px solid #cccccc; padding: 5px; font-family: Tahoma, sans-serif; }
    html>body { background-color: white; }
    #sb-loading-inner { display: none; }
</style>

<script type="text/javascript">
    jQuery(document).ready(function() {
    	//make playlist entries sortable
	jQuery("#sortable").sortable({
	    placeholder: 'ui-state-highlight'
	});
	jQuery("#sortable").disableSelection();		
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
			
	function x7ListPreview()
	{
	    var valError = "noerror";
	    arrEids = []; //clear out the eids array
	    var listname;
	    jQuery("#sortable li").each(
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
	    var plid;
	    plid = jQuery("#plid").html();
	    plid = String(plid);
	    if (plid.length < 10)
	    {
		  valError = "error";
		  alert("Error - Playlist ID not identified.  Please try again.");
	    }
	    
	    if (valError != "error")
	    {
	    strEids = arrEids.join(",");
	    jQuery.post(
		       "<?php echo($pluginurl) ?>/x7plupdate.php",
		       {'x7bloghome': "<?php echo($x7bloghome) ?>", 'ks': "<?php echo($ks) ?>", 'eid': plid, 'x7server': "<?php echo($x7server) ?>", 'name': listname, 'plcontent': strEids},
		       function ( response ){
			alert('Playlist ID: '+response+' updated.');
                        parent.Shadowbox.close();
		       }); //end post
	    };//end if not valerror error
      }//end x7listpreview
      
      function x7EditClose() {
	    parent.Shadowbox.close();
	}
</script>
</head>
        <body>
	    <div id="x7wrapdiv" style="width:500px;margin-left:60px;margin-top:30px;" class="ui-helper-clearfix">
                <div id="playlistdiv">
                    <strong>Playlist ID: <span id="plid"><?php echo($eid) ?></span><br>
                    <strong>Playlist name:  </strong><textarea id="listname"><?php echo($listname) ?></textarea><br><br>
                    <a onclick="x7ListPreview()" id="preview"><strong>[SAVE AND PREVIEW]</strong></a>  <a onclick="x7EditClose()">[CANCEL]</a><br><br>
                    <ul id="trash">
                        <li id="droppable" style="background-color: #F2D285"><strong>Drop an item here to remove.</strong></li>
                    </ul>
                    <ul id="sortable">
                        <?php
                            $content = (string) $plentryresult->result->playlistContent;
                            $plids = explode(",", $content);
                            foreach ($plids as $plid){
				echo "<li class='ui-state-default' eid='$plid'><img title='Drag me!' src='$x7server/p/$x7kalpartnerid/sp/$x7kalsubpartnerid/thumbnail/entry_id/$plid/width/90/height/60'/></li>";
                            } // end foreach
                        ?>
                    </ul>
                </div>
            </div>
        </body>
</html>
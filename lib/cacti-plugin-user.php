<?php

class WeatherMapCactiUserPlugin
{
    public $config;

    public $handlers = array(
        'viewthumb' => array( 'handler'=>'handleBigThumb', 'args'=>array() ),
        'viewthumb48' => array( 'handler'=>'handleLittleThumb', 'args'=>array() ),
        'viewimage' => array( 'handler'=>'handleImage', 'args'=>array() ),
        'viewmap' => array( 'handler'=>'handleViewCycle', 'args'=>array() ),
        'viewmapcycle' => array( 'handler'=>'handleView', 'args'=>array() ),
        ':: DEFAULT ::' => array( 'handler'=>'handleMainView', 'args'=>array() )
    );

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function dispatch($action, $request)
    {
        $handler = null;

        if (array_key_exists($action, $this->handlers)) {
            $handler = $this->handlers[$action];
        }
        if (array_key_exists(":: DEFAULT ::", $this->handlers)) {
            $handler = $this->handlers[":: DEFAULT ::"];
        }
        if (null === $handler) {
            return;
        }

        // TODO - add argument parse/validation in here

        $handlerMethod = $handler['handler'];
        $this->$handlerMethod($request);
    }

    /**
     * @param $request
     * @internal param $config
     */
    public function handleMainView($request)
    {
        global $config;

        require_once $config["base_path"] . "/include/top_graph_header.php";
        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $group_id = -1;
        if (isset($request['group_id']) && (is_numeric($request['group_id']))) {
            $group_id = intval($request['group_id']);
            $_SESSION['wm_last_group'] = $group_id;
        } else {
            if (isset($_SESSION['wm_last_group'])) {
                $group_id = intval($_SESSION['wm_last_group']);
            }
        }

        $tabs = $this->getValidTabs();
        $tab_ids = array_keys($tabs);

        if (($group_id == -1) && (sizeof($tab_ids) > 0)) {
            $group_id = $tab_ids[0];
        }

        if (read_config_option("weathermap_pagestyle") == 0) {
            $this->wmuiThumbnailView($group_id);
        }

        if (read_config_option("weathermap_pagestyle") == 1) {
            $this->wmuiFullMapView(false, false, $group_id);
        }

        if (read_config_option("weathermap_pagestyle") == 2) {
            $this->wmuiFullMapView(false, true, $group_id);
        }

        $this->outputVersionBox();
        require_once($config["base_path"] . "/include/bottom_footer.php");
    }

    public function handleView($request)
    {
        global $config;

        require_once $config["base_path"] . "/include/top_graph_header.php";
        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $mapID = $this->deduceMapID($request);

        if ($mapID >= 0) {
            $this->wmuiSingleMapView($mapID);
        }

        $this->outputVersionBox();

        require_once $config["base_path"] . "/include/bottom_footer.php";
    }

    /**
     * @param $request
     * @return int
     */
    public function deduceMapID($request)
    {
        $mapID = -1;

        if (isset($request['id']) && (!is_numeric($request['id']) || strlen($request['id']) == 20)) {
            $mapID = $this->translateHashToID($request['id']);
        }

        if (isset($request['id']) && is_numeric($request['id'])) {
            $mapID = intval($request['id']);
            return $mapID;
        }
        return $mapID;
    }

    public function handleViewCycle($request)
    {
        global $config;

        $fullscreen = false;
        if ((isset($request['fullscreen']) && is_numeric($request['fullscreen']))) {
            if (intval($request['fullscreen']) == 1) {
                $fullscreen = true;
            }
        }

        if ($fullscreen === true) {
            print "<!DOCTYPE html>\n";
            print "<html><head>";
            print '<LINK rel="stylesheet" type="text/css" media="screen" href="cacti-resources/weathermap.css">';
            print "</head><body id='wm_fullscreen'>";
        } else {
            include_once $config["base_path"] . "/include/top_graph_header.php";
        }

        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $groupid = -1;
        if ((isset($request['group']) && is_numeric($request['group']))) {
            $groupid = intval($request['group']);
        }

        $this->wmuiFullMapView(true, false, $groupid, $fullscreen);

        if ($fullscreen === true) {
            print "</body></html>";
        } else {
            $this->outputVersionBox();
            include_once $config["base_path"] . "/include/bottom_footer.php";
        }
    }

    public function handleImage($request)
    {

    }

    public function handleBigThumb($request)
    {

    }

    public function handleLittleThumb($request)
    {

    }

    /**
     * @param $action
     */
    public function wmuiHandleImageOutput($request)
    {
        $mapID = $this->deduceMapID($request);

        if ($mapID < 0) {
            return;
        }
        $imageFormat = strtolower(read_config_option("weathermap_output_format"));

        $userID = $this->getCactiUserID();

        $map = db_fetch_assoc("select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and (userid=" . $userID . " or userid=0) and  active='on' and weathermap_maps.id=" . $mapID . " LIMIT 1");

        if (sizeof($map) != 1) {
            // in the management view, a disabled map will fail the query above, so generate *something*
            header('Content-type: image/png');
            $this->outputGreyPNG(48, 48);
        }

        $insert = ".";
        if ($action == 'viewthumb') {
            $insert = ".thumb.";
        }

        if ($action == 'viewthumb48') {
            $insert = ".thumb48.";
        }

        $imageFileName = dirname(__FILE__) . '/../output/' . $map[0]['filehash'] . $insert . $imageFormat;

        header('Content-type: image/png');

        if (file_exists($imageFileName)) {
            readfile($imageFileName);
        } else {
            $this->outputGreyPNG(48, 48);
        }
    }

    public function wmuiSingleMapView($mapid)
    {
        global $colors;

        $is_wm_admin = false;

        $outputDirectory = dirname(__FILE__) . '/../output/';

        $userid = wmGetCactiUserID();
        $map = db_fetch_assoc("select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $mapid);


        if (sizeof($map)) {
            print do_hook_function('weathermap_page_top', '');

            $htmlFileName = $outputDirectory . $map[0]['filehash'] . ".html";
            $mapTitle = ($map[0]['titlecache'] == "" ? "Map for config file: " . $map[0]['configfile'] : $map[0]['titlecache']);

            wmGenerateMapSelectorBox($mapid);

            html_graph_start_box(1, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>">
                <td>
                    <table width="100%" cellpadding="0"
                           cellspacing="0">
                        <tr>
                            <td class="textHeader"
                                nowrap><?php print $mapTitle;

                                if ($is_wm_admin) {
                                    print "<span style='font-size: 80%'>";
                                    print "[ <a href='weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid . "'>Map Settings</a> |";
                                    print "<a href='weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=" . $mapid . "'>Map Permissions</a> |";
                                    print "<a href=''>Edit Map</a> ]";
                                    print "</span>";
                                }

                                ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr><td>
            <?php

            if (file_exists($htmlFileName)) {
                include $htmlFileName;
            } else {
                print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.";

                global $user_auth_realm_filenames;
                $realm_id2 = 0;

                if (isset($user_auth_realm_filenames[basename('weathermap-cacti-plugin.php')])) {
                    $realm_id2 = $user_auth_realm_filenames[basename('weathermap-cacti-plugin.php')];
                }

                $userid = $this->getCactiUserID();

                if ((db_fetch_assoc(
                        "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $userid . "' and user_auth_realm.realm_id='$realm_id2'"
                    )) || (empty($realm_id2))
                ) {
                    print " (If this message stays here for more than one poller cycle, then check your cacti.log file for errors!)";
                }
                print "</em></div>";
            }
            print "</td></tr>";
            html_graph_end_box();
        }
    }

    public function wmuiThumbnailView($limit_to_group = -1)
    {
        global $colors;

        $userid = $this->getCactiUserID();
        $maplist_SQL = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";

        if ($limit_to_group > 0) {
            $maplist_SQL .= " weathermap_maps.group_id=" . $limit_to_group . " and ";
        }

        $maplist_SQL .= " (userid=" . $userid . " or userid=0) order by sortorder, id";

        $maplist = db_fetch_assoc($maplist_SQL);

        // if there's only one map, ignore the thumbnail setting and show it fullsize
        if (sizeof($maplist) == 1) {
            $pagetitle = "Network Weathermap";
            $this->wmuiFullMapView(false, false, $limit_to_group);
        } else {
            $pagetitle = "Network Weathermaps";

            html_graph_start_box(2, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>">
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="textHeader" nowrap> <?php print $pagetitle; ?></td>
                            <td align="right">
                                automatically cycle between full-size maps (<?php
                                if ($limit_to_group > 0) {
                                    print '<a href = "?action=viewmapcycle&group=' . intval($limit_to_group) . '">within this group</a>, or ';
                                }
                                print ' <a href = "?action=viewmapcycle">all maps</a>'; ?>)
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td><i>Click on thumbnails for a full view (or you can <a href="?action=viewmapcycle">automatically
                            cycle</a> between full-size maps)</i></td>
            </tr>
            <?php
            html_graph_end_box();
            $showlivelinks = intval(read_config_option("weathermap_live_view"));

            $this->generateGroupTabs($limit_to_group);
            $i = 0;
            if (sizeof($maplist) > 0) {
                $outdir = dirname(__FILE__) . '/../output/';

                $imageformat = strtolower(read_config_option("weathermap_output_format"));

                html_graph_start_box(1, false);
                print "<tr><td class='wm_gallery'>";
                foreach ($maplist as $map) {
                    $i++;

                    $imgsize = "";
                    $thumbfile = $outdir . $map['filehash'] . ".thumb." . $imageformat;
                    $thumburl = "?action=viewthumb&id=" . $map['filehash'] . "&time=" . time();
                    if ($map['thumb_width'] > 0) {
                        $imgsize = ' WIDTH="' . $map['thumb_width'] . '" HEIGHT="' . $map['thumb_height'] . '" ';
                    }
                    $maptitle = $map['titlecache'];
                    if ($maptitle == '') {
                        $maptitle = "Map for config file: " . $map['configfile'];
                    }
                    print '<div class="wm_thumbcontainer" style="margin: 2px; border: 1px solid #bbbbbb; padding: 2px; float:left;">';
                    if (file_exists($thumbfile)) {
                        print '<div class="wm_thumbtitle" style="font-size: 1.2em; font-weight: bold; text-align: center">' . $maptitle . '</div><a href="weathermap-cacti-plugin.php?action=viewmap&id=' . $map['filehash'] . '"><img class="wm_thumb" ' . $imgsize . 'src="' . $thumburl . '" alt="' . $maptitle . '" border="0" hspace="5" vspace="5" title="' . $maptitle . '"/></a>';
                    } else {
                        print "(thumbnail for map not created yet)";
                    }
                    if ($showlivelinks == 1) {
                        print "<a href='?action=liveview&id=" . $map['filehash'] . "'>(live)</a>";
                    }
                    print '</div> ';
                }
                print "</td></tr>";
                html_graph_end_box();

            } else {
                print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em></div>\n";
            }
        }
    }

    public function wmuiFullMapView($cycle = false, $firstonly = false, $limit_to_group = -1, $fullscreen = false)
    {
        global $colors;

        $_SESSION['custom'] = false;

        $userid = $this->getCactiUserID();

        $maplist_SQL = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";

        if ($limit_to_group > 0) {
            $maplist_SQL .= " weathermap_maps.group_id=" . $limit_to_group . " and ";
        }

        $maplist_SQL .= " (userid=" . $userid . " or userid=0) order by sortorder, id";

        if ($firstonly) {
            $maplist_SQL .= " LIMIT 1";
        }

        $maplist = db_fetch_assoc($maplist_SQL);

        if (sizeof($maplist) == 1) {
            $pagetitle = "Network Weathermap";
        } else {
            $pagetitle = "Network Weathermaps";
        }

        $class = "";
        if ($cycle) {
            $class = "inplace";
        }
        if ($fullscreen) {
            $class = "fullscreen";
        }

        if ($cycle) {
            print "<script src='vendor/jquery/dist/jquery.min.js'></script>";
            print "<script src='vendor/jquery-idletimer/dist/idle-timer.min.js'></script>";
            $extra = "";
            if ($limit_to_group > 0) {
                $extra = " in this group";
            }
            ?>
            <div id="wmcyclecontrolbox" class="<?php print $class ?>">
                <div id="wm_progress"></div>
                <div id="wm_cyclecontrols">
                    <a id="cycle_stop" href="?action="><img border="0" src="cacti-resources/img/control_stop_blue.png"
                                                            width="16" height="16"/></a>
                    <a id="cycle_prev" href="#"><img border="0" src="cacti-resources/img/control_rewind_blue.png"
                                                     width="16" height="16"/></a>
                    <a id="cycle_pause" href="#"><img border="0" src="cacti-resources/img/control_pause_blue.png"
                                                      width="16" height="16"/></a>
                    <a id="cycle_next" href="#"><img border="0" src="cacti-resources/img/control_fastforward_blue.png"
                                                     width="16" height="16"/></a>
                    <a id="cycle_fullscreen"
                       href="?action=viewmapcycle&fullscreen=1&group=<?php echo $limit_to_group; ?>"><img border="0"
                                                                                                          src="cacti-resources/img/arrow_out.png"
                                                                                                          width="16"
                                                                                                          height="16"/></a>
                    Showing <span id="wm_current_map">1</span> of <span id="wm_total_map">1</span>.
                    Cycling all available maps<?php echo $extra; ?>.
                </div>
            </div>
            <?php
        }

        // only draw the whole screen if we're not cycling, or we're cycling without fullscreen mode
        if ($cycle === false || $fullscreen === false) {
            html_graph_start_box(2, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>">
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="textHeader" nowrap> <?php print $pagetitle; ?> </td>
                            <td align="right">
                                <?php
                                if (!$cycle) {
                                    ?>
                                    (automatically cycle between full-size maps (<?php

                                    if ($limit_to_group > 0) {
                                        print '<a href = "?action=viewmapcycle&group=' . intval($limit_to_group)
                                            . '">within this group</a>, or ';
                                    }
                                    print ' <a href = "?action=viewmapcycle">all maps</a>';
                                    ?>)

                                    <?php
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php
            html_graph_end_box();

            $this->generateGroupTabs($limit_to_group);
        }

        if (sizeof($maplist) > 0) {
            print "<div class='all_map_holder $class'>";

            $outdir = dirname(__FILE__) . '/../output/';
        foreach ($maplist as $map) {
            $htmlfile = $outdir . $map['filehash'] . ".html";
            $maptitle = $map['titlecache'];
            if ($maptitle == '') {
                $maptitle = "Map for config file: " . $map['configfile'];
            }

            print '<div class="weathermapholder" id="mapholder_' . $map['filehash'] . '">';
        if ($cycle === false || $fullscreen === false) {
            html_graph_start_box(1, true);

            ?>
            <tr bgcolor="#<?php echo $colors["header_panel"] ?>">
                <td colspan="3">
                    <table width="100%" cellspacing="0" cellpadding="3" border="0">
                        <tr>
                            <td align="left" class="textHeaderDark">
                                <a name="map_<?php echo $map['filehash']; ?>">
                                </a><?php print htmlspecialchars($maptitle); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
            <td>
        <?php
        }

        if (file_exists($htmlfile)) {
            include($htmlfile);
        } else {
            print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.</em></div>";
        }


        if ($cycle === false || $fullscreen === false) {
            print '</td></tr>';
            html_graph_end_box();
        }
        print '</div>';
        }
        print "</div>";

        if ($cycle) {
        $refreshtime = read_config_option("weathermap_cycle_refresh");
        $poller_cycle = read_config_option("poller_interval"); ?>
            <script type="text/javascript" src="cacti-resources/map-cycle.js"></script>
            <script type="text/javascript">
                $(document).ready(public function () {
                    WMcycler.start({
                        fullscreen: <?php echo ($fullscreen ? "1" : "0"); ?>,
                        poller_cycle: <?php echo $poller_cycle * 1000; ?>,
                        period: <?php echo $refreshtime  * 1000; ?>
                    });
                });
            </script>
            <?php
        }
        } else {
            print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em></div>\n";
        }
    }

    public function translateHashToID($idname)
    {
        $SQL = "select id from weathermap_maps where configfile='" . mysql_real_escape_string($idname)
            . "' or filehash='" . mysql_real_escape_string($idname) . "'";
        $map = db_fetch_assoc($SQL);

        return $map[0]['id'];
    }

    public function outputVersionBox()
    {
        global $WEATHERMAP_VERSION, $colors;
        global $user_auth_realm_filenames;

        $pagefoot = "Powered by <a href=\"http://www.network-weathermap.com/?v=$WEATHERMAP_VERSION\">"
            . "PHP Weathermap version $WEATHERMAP_VERSION</a>";

        $realm_id2 = 0;

        if (isset($user_auth_realm_filenames['weathermap-cacti-plugin-mgmt.php'])) {
            $realm_id2 = $user_auth_realm_filenames['weathermap-cacti-plugin-mgmt.php'];
        }
        $userid = $this->getCactiUserID();

        if ((db_fetch_assoc(
                "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='"
                . $userid . "' and user_auth_realm.realm_id='$realm_id2'"
            )) || (empty($realm_id2))
        ) {
            $pagefoot .= " --- <a href='weathermap-cacti-plugin-mgmt.php' title='Go to the map management page'>";
            $pagefoot .= "Weathermap Management</a> | <a target=\"_blank\" href=\"docs/\">Local Documentation</a>";
            $pagefoot .= " | <a target=\"_blank\" href=\"editor.php\">Editor</a>";
        }


        html_graph_start_box(1, true);
        ?>
        <tr bgcolor="<?php print $colors["panel"];?>">
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="textHeader" nowrap> <?php print $pagefoot; ?> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php
        html_graph_end_box();
    }


    public function streamBinaryFile($filename)
    {
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk

        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            $buffer = fread($handle, $chunksize);
            echo $buffer;
        }
        $status = fclose($handle);
        return $status;
    }

    public function generateMapSelectorBox($current_id = 0)
    {
        global $colors;

        $show_selector = intval(read_config_option("weathermap_map_selector"));

        if ($show_selector == 0) {
            return false;
        }

        $userid = $this->getCactiUserID();
        $maps = db_fetch_assoc("select distinct weathermap_maps.*,weathermap_groups.name, weathermap_groups.sortorder as gsort from weathermap_groups,weathermap_auth,weathermap_maps where weathermap_maps.group_id=weathermap_groups.id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by gsort, sortorder");

        if (sizeof($maps) > 1) {
            /* include graph view filter selector */
            html_graph_start_box(3, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>" class="noprint">
                <form name="weathermap_select" method="post" action="">
                    <input name="action" value="viewmap" type="hidden">
                    <td class="noprint">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr class="noprint">
                                <td nowrap style='white-space: nowrap;' width="40">
                                    &nbsp;<strong>Jump To Map:</strong>&nbsp;
                                </td>
                                <td>
                                    <select name="id">
                                        <?php

                                        $ngroups = 0;
                                        $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                        foreach ($maps as $map) {
                                            if ($current_id == $map['id']) {
                                                $nullhash = $map['filehash'];
                                            }
                                            if ($map['name'] != $lastgroup) {
                                                $ngroups++;
                                                $lastgroup = $map['name'];
                                            }
                                        }

                                        $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                        foreach ($maps as $map) {
                                            if ($ngroups > 1 && $map['name'] != $lastgroup) {
                                                print "<option style='font-weight: bold; font-style: italic' value='$nullhash'>" . htmlspecialchars($map['name']) . "</option>";
                                                $lastgroup = $map['name'];
                                            }
                                            print '<option ';
                                            if ($current_id == $map['id']) {
                                                print " SELECTED ";
                                            }
                                            print 'value="' . $map['filehash'] . '">';
                                            // if we're showing group headings, then indent the map names
                                            if ($ngroups > 1) {
                                                print " - ";
                                            }
                                            print htmlspecialchars($map['titlecache']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    &nbsp;<input type="image" src="../../images/button_go.gif" alt="Go"
                                                 border="0" align="absmiddle">
                                </td>
                            </tr>
                        </table>
                    </td>
                </form>
            </tr>
            <?php
            html_graph_end_box(false);
        }

        return true;
    }

    public function getValidTabs()
    {
        $tabs = array();
        $userid = $this->getCactiUserID();
        $maps = db_fetch_assoc("select weathermap_maps.*, weathermap_groups.name as group_name from weathermap_auth,weathermap_maps, weathermap_groups where weathermap_groups.id=weathermap_maps.group_id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by weathermap_groups.sortorder");

        foreach ($maps as $map) {
            $tabs[$map['group_id']] = $map['group_name'];
        }

        return ($tabs);
    }

    /**
     * @return int
     */
    public function getCactiUserID()
    {
        $userid = (isset($_SESSION["sess_user_id"]) ? intval($_SESSION["sess_user_id"]) : 1);

        return $userid;
    }

    public function generateGroupTabs($current_tab)
    {
        $tabs = $this->getValidTabs();

        if (sizeof($tabs) > 1) {
            /* draw the categories tabs on the top of the page */
            print "<p></p><table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

            if (sizeof($tabs) > 0) {
                $show_all = intval(read_config_option("weathermap_all_tab"));
                if ($show_all == 1) {
                    $tabs['-2'] = "All Maps";
                }

                foreach (array_keys($tabs) as $tab_short_name) {
                    print "<td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'")
                        . " nowrap='nowrap' width='" . (strlen($tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
                    <span class='textHeader'><a
                    href='weathermap-cacti-plugin.php?group_id=$tab_short_name'>$tabs[$tab_short_name]</a></span>
                    </td>\n
                    <td width='1'></td>\n";
                }
            }

            print "<td></td>\n</tr></table>\n";

            return (true);
        } else {
            return (false);
        }
    }

    public function outputGreyPNG($w, $h)
    {
        $imageRef = imagecreate($w, $h);
        $shade = 240;
        // The first colour allocated becomes the background colour of the image. No need to fill
        imagecolorallocate($imageRef, $shade, $shade, $shade);
        imagepng($imageRef);
    }
}
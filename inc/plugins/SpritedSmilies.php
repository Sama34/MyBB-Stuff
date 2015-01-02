<?php
    defined('IN_MYBB') or die('Nope');

    define('SMILIE_FILE', 'images/SpritedSmilies-%s.png');
    define('SMILIE_URL', SMILIE_FILE); # For CDN usage.

    function SpritedSmilies_info() {
        return array(
            'name'          => 'Sprited Smilies',
            'description'   => 'Generates the smilies into a sprite for less amount requests and "better" cache abilities.',
            'author'        => 'Rakes / Cake / Zalvie',
            'authorsite'    => 'https://keybase.io/to',
            'website'       => 'https://github.com/Zalvie',
            'version'       => '0.9',
            'compatibility' => '18*',
            'codename'      => 'spritedsmilies'
        );
    }

    function SpritedSmilies_activate() {
        if (!extension_loaded('gd')) {
            flash_message('This plugin requires PHP-GD to be enabled.', 'error');
            admin_redirect('index.php?module=config-plugins');
        }

        require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

        find_replace_templatesets(
            "smilie",
            "#^(.*?)$#",
            '<i alt="{$smilie[\'name\']}" title="{$smilie[\'name\']}" class="smilie smilie-{$smilie[\'sid\']}{$extra_class}"{$onclick}></i>'
        );

        SpritedSmilies_generate();
    }

    function SpritedSmilies_deactivate() {
        require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

        find_replace_templatesets(
            "smilie",
            "#^(.*?)$#",
            '<img src="{$smilie[\'image\']}" alt="{$smilie[\'name\']}" title="{$smilie[\'name\']}" class="smilie smilie_{$smilie[\'sid\']}{$extra_class}"{$onclick} />'
        );

        SpritedSmilies_delete();
        SpritedSmilies_delete_stylesheet('SpritedSmilies');
    }

    function SpritedSmilies_delete() {
        foreach (glob(sprintf(MYBB_ROOT . SMILIE_FILE, '*')) as $f)
            if (file_exists($f) && get_extension($f) == 'png')
                @unlink($f);
    }

    function SpritedSmilies_generate() {
        global $cache;

        SpritedSmilies_delete();

        $x = $height = $width = 0;

        $images = [];

        $smilies = $cache->read('smilies');

        foreach ($smilies as $smilie) {
            if (($imageSize = @getimagesize(MYBB_ROOT . $smilie['image'])) && $imageSize === FALSE)
                continue;

            list($itemWidth, $itemHeight, $itemType) = $imageSize;

            $images[$smilie['sid']] = ['height' => $itemHeight,
                                       'width'  => $itemWidth,
                                       'x'      => $x,
                                       'image'  => $smilie['image'],
                                       'ext'    => image_type_to_extension($itemType, FALSE)];

            if ($images[$smilie['sid']]['ext'] == 'gif')
                continue;

            if ($itemHeight > $height)
                $height = $itemHeight;

            $width += $itemWidth;

            $x += $itemWidth;
        }

        !empty($images) or die('Failed to fetch the smilies');

        $css = '[class^=smilie-],[class*=" smilie-"] {background-image:url(' . sprintf(SMILIE_URL, time()) . ');background-position:0 0;background-repeat:no-repeat;display:inline-block;height: 0px; width: 0px;}';
        $dest = imagecreatetruecolor($width, $height);

        imagesavealpha($dest, TRUE);
        imagefill($dest, 0, 0, imagecolorallocatealpha($dest, 0, 0, 0, 127));

        foreach ($images as $id => $smilie) {
            if ($smilie['ext'] == 'gif') {
                $css .= sprintf('.smilie-%d{background-image:url(/%s);background-position:0;width:%dpx;height:%dpx;}', $id, $smilie['image'], $smilie['width'], $smilie['height']);
            } else {
                $imgCreateFunc = 'imagecreatefrom' . $smilie['ext'];

                if (!function_exists($imgCreateFunc))
                    continue;

                $src = $imgCreateFunc(MYBB_ROOT . $smilie['image']);

                imagealphablending($src, TRUE);
                imagesavealpha($src, TRUE);
                imagecopy($dest, $src, $smilie['x'], 0, 0, 0, $smilie['width'], $smilie['height']);
                imagedestroy($src);

                $css .= sprintf('.smilie-%d{background-position:-%dpx 0;width:%dpx;height:%dpx;}', $id, $smilie['x'], $smilie['width'], $smilie['height']);
            }
        }

        imagepng($dest, MYBB_ROOT . sprintf(SMILIE_FILE, time()));

        SpritedSmilies_update_stylesheet('SpritedSmilies', $css);
    }

    // Shamefully borrowed and reworked from PluginLibrary

    function SpritedSmilies_update_stylesheet_list($stylesheet = FALSE) {
        # Cuz who even updates their admin_dir setting these days, silly pluginlibrary.
        $theme_functions = implode('', array_slice((array)glob(MYBB_ROOT . '*/inc/functions_themes.php'), 0, 1));

        file_exists($theme_functions) OR die('Could not find the theme functions');

        require_once $theme_functions;

        if ($stylesheet)
            cache_stylesheet(1, $stylesheet['cachefile'], $stylesheet['stylesheet']);

        update_theme_stylesheet_list(1, FALSE, TRUE);
    }

    // Shamefully borrowed and reworked from PluginLibrary

    function SpritedSmilies_update_stylesheet($name, $style) {
        global $db;

        if (substr($name, -4) != ".css")
            $name .= '.css';

        $stylesheet = ['name'         => $name,
                       'tid'          => 1,
                       'stylesheet'   => $style,
                       'cachefile'    => $name,
                       'lastmodified' => TIME_NOW];

        $dbstylesheet = array_map([$db, 'escape_string'], $stylesheet);

        if (($sid = (int)$db->fetch_field($db->simple_select('themestylesheets', 'sid', "tid=1 AND cachefile='{$name}'"), 'sid')) && $sid)
            $db->update_query('themestylesheets', $dbstylesheet, 'sid=' . $sid);
        else
            $db->insert_query('themestylesheets', $dbstylesheet);

        SpritedSmilies_update_stylesheet_list($stylesheet);
    }

    // Shamefully borrowed and reworked from PluginLibrary

    function SpritedSmilies_delete_stylesheet($name) {
        global $db;

        if (substr($name, -4) == ".css")
            $name = substr($name, 0, -4);

        $where = sprintf('name="%s.css"', $db->escape_string($name));

        $query = $db->simple_select('themestylesheets', 'tid,name', $where);

        while ($stylesheet = $db->fetch_array($query)) {
            @unlink(MYBB_ROOT . "cache/themes/{$stylesheet['tid']}_{$stylesheet['name']}");
            @unlink(MYBB_ROOT . "cache/themes/theme{$stylesheet['tid']}/{$stylesheet['name']}");
        }

        $db->delete_query('themestylesheets', $where);

        SpritedSmilies_update_stylesheet_list();
    }

    $plugins->add_hook('admin_config_smilies_delete_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_edit_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_add_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_mass_edit_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_add_multiple_commit', 'SpritedSmilies_generate');

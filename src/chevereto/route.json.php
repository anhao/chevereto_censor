<?php

/* --------------------------------------------------------------------

  Chevereto
  http://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
            <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  http://chevereto.com/license

  --------------------------------------------------------------------- */

$route = function ($handler) {
    try {
        // CSRF protection
        if (!$handler::checkAuthToken($_REQUEST['auth_token'])) {
            throw new Exception(_s('Request denied'), 400);
        }

        $logged_user = CHV\Login::getUser();
        $logged_user_source_db = [
            'user_name'     => $logged_user['name'],
            'user_username' => $logged_user['username'],
            'user_email'    => $logged_user['email'],
        ];

        $doing = $_REQUEST['action'];
        if ($logged_user and $logged_user['status'] !== 'valid') {
            $doing = 'deny';
        }

        if (in_array($doing, ['importAdd', 'importStats', 'importProcess', 'importEdit', 'importDelete']) && $logged_user['is_admin'] == false) {
            throw new Exception(_s('Request denied'), 400);
        } else {
            $import = new CHV\Import();
        }

        switch ($doing) {
            case 'deny':
                throw new Exception(_s('Request denied'), 403);
                break;

            case 'upload': // EX 100
                // Is upload allowed anyway?
                if (!$handler::getCond('upload_allowed')) {
                    throw new Exception(_s('Request denied'), 401);
                }

                $source = $_REQUEST['type'] == 'file' ? $_FILES['source'] : $_REQUEST['source'];
                $type = $_REQUEST['type'];
                $owner_id = !empty($_REQUEST['owner']) ? CHV\decodeID($_REQUEST['owner']) : $logged_user['id'];

                if (in_array($_REQUEST['what'], ['avatar', 'background'])) {
                    if (!$logged_user) {
                        throw new Exception(_s('Login needed'), 403);
                    }

                    if (!$handler::getCond('content_manager') and $owner_id !== $logged_user['id']) {
                        throw new Exception('Invalid content owner request', 115);
                    }

                    try {
                        $user_picture_upload = CHV\User::uploadPicture($owner_id == $logged_user['id'] ? $logged_user : $owner_id, $_REQUEST['what'], $source);
                        $json_array['success'] = ['image' => $user_picture_upload, 'message' => sprintf('%s picture uploaded', ucfirst($type)), 'code' => 200];
                        // image inside success??
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode());
                    }
                    break;
                }

                // Inject the system level privacy override
                if ($handler::getCond('forced_private_mode')) {
                    $_REQUEST['privacy'] = CHV\getSetting('website_content_privacy_mode');
                }

                // Fix some encoded stuff
                if (!empty($_REQUEST['album_id'])) {
                    $_REQUEST['album_id'] = CHV\decodeID($_REQUEST['album_id']);
                }

                if (CHV\getSetting('akismet')) {
                    CHV\Akismet::checkImage($_REQUEST['title'], $_REQUEST['description'], $logged_user_source_db);
                }
                // add image censor
                if (CHV\getSetting('censor_type') != 0) {
                    \CHV\Censor::censor($source, $type);
                }

                // Upload to website
                $uploaded_id = CHV\Image::uploadToWebsite($source, $logged_user, $_REQUEST);
                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'image uploaded', 'code' => 200];
                $json_array['image'] = CHV\Image::formatArray(CHV\Image::getSingle($uploaded_id, 0, 0), 1);
                break;

            case 'get-album-contents':
            case 'list': // EX 200

                if ($doing == 'get-album-contents') {
                    if (!$logged_user) {
                        throw new Exception(_s('Login needed'), 403);
                    }
                    $list_request = 'images';
                    $aux = $_REQUEST['albumid'];
                    $_REQUEST = null; // We don't need anything else
                    $_REQUEST['albumid'] = $aux;
                } else {
                    $list_request = $_REQUEST['list'];
                }

                if (!in_array($list_request, ['images', 'albums', 'users'])) {
                    throw new Exception('Invalid list request', 100);
                }

                $output_tpl = $list_request;

                // Params hidden handler
                if ($_REQUEST['params_hidden'] && is_array($_REQUEST['params_hidden'])) {
                    $params_hidden = [];
                    foreach ($_REQUEST['params_hidden'] as $k => $v) {
                        if (isset($_REQUEST[$k])) {
                            $params_hidden[$k] = $v;
                        }
                    }
                }

                switch ($list_request) {
                    case 'images':

                        $binds = [];
                        $where = '';

                        if (!empty($_REQUEST['like_user_id'])) {
                            $where .= ($where == '' ? 'WHERE' : ' AND').' like_user_id=:image_user_id';
                            $binds[] = [
                                'param' => ':image_user_id',
                                'value' => CHV\decodeID($_REQUEST['like_user_id']),
                            ];
                        }

                        if (!empty($_REQUEST['follow_user_id'])) {
                            $where .= ($where == '' ? 'WHERE' : ' AND').' follow_user_id=:image_user_id';
                            $binds[] = [
                                'param' => ':image_user_id',
                                'value' => CHV\decodeID($_REQUEST['follow_user_id']),
                            ];
                        }

                        if (!empty($_REQUEST['userid'])) {
                            $owner_id = CHV\decodeID($_REQUEST['userid']);
                            $where .= ($where == '' ? 'WHERE' : ' AND').' image_user_id=:image_user_id';
                            $binds[] = [
                                'param' => ':image_user_id',
                                'value' => $owner_id,
                            ];
                        }

                        if (!empty($_REQUEST['albumid'])) {
                            $album_id = CHV\decodeID($_REQUEST['albumid']);
                            $where .= ($where == '' ? 'WHERE' : ' AND').' image_album_id=:image_album_id';
                            $binds[] = [
                                'param' => ':image_album_id',
                                'value' => $album_id,
                            ];
                            $album = CHV\Album::getSingle($album_id);
                            if ($album['user']['id']) {
                                $owner_id = $album['user']['id'];
                            }
                            if ($album['privacy'] == 'password' && (!$handler::getCond('content_manager') && $owner_id !== $logged_user['id'] && !CHV\Album::checkSessionPassword($album))) {
                                throw new Exception(_s('Request denied'), 403);
                            }
                        }

                        if (!empty($_REQUEST['category_id']) and is_numeric($_REQUEST['category_id'])) {
                            $category = $_REQUEST['category_id'];
                        }

                        switch ($_REQUEST['from']) {
                            case 'user':
                                $output_tpl = 'user/images';
                                break;
                            case 'album':
                                $output_tpl = 'album/images';
                                break;
                        }

                        break;

                    case 'albums':

                        $binds = [];
                        $where = '';

                        if (!empty($_REQUEST['userid'])) {
                            $owner_id = CHV\decodeID($_REQUEST['userid']);
                            $where .= ($where == '' ? 'WHERE' : ' AND').' album_user_id=:album_user_id';
                            $binds[] = [
                                'param' => ':album_user_id',
                                'value' => $owner_id,
                            ];
                        }

                        switch ($_REQUEST['from']) {
                            case 'user':
                                $output_tpl = 'user/albums';
                                break;
                            case 'album':
                                $output_tpl = 'album';
                                break;
                        }

                        break;

                    case 'users':
                        $where = '';
                        if (CHV\getSetting('enable_followers') and (!empty($_REQUEST['following_user_id']) or !empty($_REQUEST['followers_user_id']))) {
                            $doing = !empty($_REQUEST['following_user_id']) ? 'following' : 'followers';
                            $user_id = CHV\decodeID($doing == 'following' ? $_REQUEST['following_user_id'] : $_REQUEST['followers_user_id']);
                            $where = 'WHERE follow'.($doing == 'following' ? null : '_followed').'_user_id=:user_id';
                            $binds[] = [
                                'param' => ':user_id',
                                'value' => $user_id,
                            ];
                        }
                        break;
                }

                if (!empty($_REQUEST['q'])) {
                    // Build search params
                    $search = new CHV\Search();
                    $search->q = $_REQUEST['q'];
                    $search->type = $list_request;
                    $search->request = $_REQUEST;
                    $search->requester = CHV\Login::getUser();
                    $search->build();

                    if (!G\check_value($search->q)) {
                        throw new Exception('Missing search term', 400);
                    }

                    $where .= $where == '' ? $search->wheres : preg_replace('/WHERE /', ' AND ', $search->wheres, 1);
                    $binds = array_merge((array) $binds, $search->binds);
                }

                $list_params = CHV\Listing::getParams(true);

                if ($list_params['sort'][0] == 'likes' && !CHV\getSetting('enable_likes')) {
                    throw new Exception(_s('Request denied'), 403);
                }

                if ($doing == 'get-album-contents') {
                    $album_fetch = min(1000, $album['image_count']);
                    $list_params = [
                        'items_per_page' => $album_fetch,
                        'page'           => 0,
                        'limit'          => $album_fetch,
                        'offset'         => 0,
                        'sort'           => ['date', 'desc'],
                    ];
                }

                $list = new CHV\Listing();
                $list->setType($list_request); // images | users | albums
                $list->setReverse($list_params['reverse']);
                $list->setSeek($list_params['seek']);
                $list->setOffset($list_params['offset']);
                $list->setLimit($list_params['limit']); // how many results?
                $list->setSortType($list_params['sort'][0]); // date | size | views | likes
                $list->setSortOrder($list_params['sort'][1]); // asc | desc
                if ($category) {
                    $list->setCategory($category);
                }
                // Ugly way to port index stuff here
                if (CHV\Settings::get('homepage_style') == 'split' && $home_uids = CHV\getSetting('homepage_uids') && isset($_POST['params_hidden']['route']) && $_POST['params_hidden']['route'] == 'index') {
                    $home_uid_is_null = ($home_uids == '' or $home_uids == '0' ? true : false);
                    $home_uid_arr = !$home_uid_is_null ? explode(',', $home_uids) : false;
                    if (is_array($home_uid_arr)) {
                        $home_uid_bind = [];
                        foreach ($home_uid_arr as $k => $v) {
                            $home_uid_bind[] = ':user_id_'.$k;
                            if ($v == 0) {
                                $home_uid_is_null = true;
                            }
                        }
                        $home_uid_bind = implode(',', $home_uid_bind);
                    }
                    if (is_array($home_uid_arr)) {
                        $prefix = CHV\DB::getFieldPrefix($list_request);
                        $where = 'WHERE '.$prefix.'_user_id IN('.$home_uid_bind.')';
                        if ($home_uid_is_null) {
                            $where .= ' OR '.$prefix.'_user_id IS NULL';
                        }
                        // $list->setWhere($where);
                        foreach ($home_uid_arr as $k => $v) {
                            $list->bind(':user_id_'.$k, $v);
                        }
                    }
                }
                // Ugly ending
                $list->setWhere($where);
                $list->setOwner($owner_id);
                $list->setRequester($logged_user);
                if (in_array($list_request, ['images', 'albums']) && ($handler::getCond('content_manager') || ($logged_user !== null && $owner_id == $logged_user['id']))) {
                    $list->setTools(true);
                }
                if (!empty($params_hidden)) {
                    $list->setParamsHidden($params_hidden);
                }

                if ($list_request == 'images' && !empty($_REQUEST['albumid'])) {
                    if ($handler::getCond('forced_private_mode')) { // Remeber this override...
                        $album['privacy'] = CHV\getSetting('website_content_privacy_mode');
                    }
                    $list->setPrivacy($album['privacy']);
                }

                if (is_array($binds)) {
                    foreach ($binds as $bind) {
                        $list->bind($bind['param'], $bind['value']);
                    }
                }
                $list->exec();

                $json_array['status_code'] = 200;

                if ($doing == 'get-album-contents') {
                    $json_array['album'] = G\array_filter_array($album, ['id', 'creation_ip', 'password', 'user', 'privacy_extra', 'privacy_notes'], 'rest');
                    $contents = [];
                    foreach ($list->output_assoc as $v) {
                        $contents[] = G\array_filter_array($v, ['title', 'id_encoded', 'url', 'url_short', 'url_viewer', 'filename', 'medium', 'thumb'], 'exclusion');
                    }
                    $json_array['is_output_truncated'] = $album['image_count'] > $album_fetch ? 1 : 0;
                    $json_array['contents'] = $contents;
                } else {
                    $json_array['html'] = $list->htmlOutput($output_tpl);
                }

                $json_array['seekEnd'] = $list->seekEnd;

                break;

            case 'edit': // EX 3X

                if (!$logged_user) {
                    throw new Exception(_s('Login needed'), 403);
                }

                $editing_request = $_REQUEST['editing'];
                $editing = $editing_request;
                $type = $_REQUEST['edit'];
                $owner_id = !empty($_REQUEST['owner']) ? CHV\decodeID($_REQUEST['owner']) : $logged_user['id'];

                if (!in_array($type, ['image', 'album', 'images', 'albums', 'category', 'storage', 'ip_ban'])) {
                    throw new Exception('Invalid edit request', 100);
                }

                if (is_null($editing['id'])) {
                    throw new Exception('Missing edit target id', 100);
                } else {
                    $id = CHV\decodeID($editing['id']);
                }

                $editing['new_album'] = $editing['new_album'] == 'true';

                $allowed_to_edit = [
                    'image'    => ['name', 'category_id', 'title', 'description', 'album_id', 'nsfw'],
                    'album'    => ['name', 'privacy', 'album_id', 'description', 'password'],
                    'category' => ['name', 'description', 'url_key'],
                    'storage'  => ['name', 'bucket', 'region', 'url', 'server', 'capacity', 'is_https', 'is_active', 'api_id', 'key', 'secret', 'account_id', 'account_name'],
                    'ip_ban'   => ['ip', 'expires', 'message'],
                ];
                $allowed_to_edit['images'] = $allowed_to_edit['image'];
                $allowed_to_edit['albums'] = $allowed_to_edit['album'];

                if ($editing['new_album']) {
                    $new_album = ['new_album', 'album_name', 'album_privacy', 'album_password', 'album_description'];
                    $allowed_to_edit['image'] = array_merge($allowed_to_edit['image'], $new_album);
                    $allowed_to_edit['album'] = array_merge($allowed_to_edit['album'], $new_album);
                }

                $editing = G\array_filter_array($editing, $allowed_to_edit[$type], 'exclusion');

                // Inject the system level privacy override
                if ($handler::getCond('forced_private_mode') and in_array($type, ['album', 'image'])) {
                    $editing[$type == 'album' ? 'privacy' : 'album_privacy'] = CHV\getSetting('website_content_privacy_mode');
                }

                if (count($editing) == 0) {
                    throw new Exception('Invalid edit request', 403);
                }

                if (!is_null($editing['album_id'])) {
                    $editing['album_id'] = CHV\decodeID($editing['album_id']);
                }

                switch ($type) {
                    // Single image/album edit
                    case 'image':

                        $source_image_db = CHV\Image::getSingle($id, false, false);

                        if (!$source_image_db) {
                            throw new Exception("Image doesn't exists", 100);
                        }

                        if (!$handler::getCond('content_manager') && $source_image_db['image_user_id'] !== $logged_user['id']) {
                            throw new Exception('Invalid content owner request', 101);
                        }

                        if ($editing['new_album']) {
                            if (CHV\getSetting('akismet')) {
                                CHV\Akismet::checkAlbum($editing['album_name'], $editing['album_description'], $source_image_db);
                            }
                            $inserted_album = CHV\Album::insert($editing['album_name'], $source_image_db['image_user_id'], $editing['album_privacy'], $editing['album_description'], $editing['album_password']);
                            $editing['album_id'] = $inserted_album;
                        }

                        // Validate category
                        if (!empty($editing['category_id']) and !array_key_exists($editing['category_id'], $handler::getVar('categories'))) {
                            throw new Exception('Invalid category', 102);
                        }

                        unset($editing['album_privacy'], $editing['new_album'], $editing['album_name']);

                        if (CHV\getSetting('akismet')) {
                            CHV\Akismet::checkImage($editing['title'], $editing['description'], $source_image_db);
                        }

                        // Submit image DB edit
                        CHV\Image::update($id, $editing);

                        // Get the edited image
                        $image_edit_db = CHV\Image::getSingle($id, false, false);

                        // Changed album, get the slice
                        if ($source_image_db['image_album_id'] !== $image_edit_db['image_album_id'] && $image_edit_db['image_album_id']) {
                            global $image_album_slice, $image_id;
                            $image_album_slice = CHV\Image::getAlbumSlice($id, $image_edit_db['image_album_id'], 2);
                            $image_id = $image_edit_db['image_id'];
                        }

                        $album_id = $image_edit_db['image_album_id'];

                        $json_array['status_code'] = 200;
                        $json_array['success'] = ['message' => 'Image edited', 'code' => 200];

                        // Editing response
                        $json_array['editing'] = $editing_request;
                        $json_array['image'] = CHV\Image::formatArray($image_edit_db, true); // Safe formatted image

                        // Append the HTML slice
                        if ($image_album_slice) {
                            // Add the album URL to the slice
                            $image_album_slice['url'] = CHV\Album::getUrl(CHV\encodeID($album_id));

                            ob_start();
                            G\Render\include_theme_file('snippets/image_album_slice');
                            $html = ob_get_contents();
                            ob_end_clean();

                            $json_array['image']['album']['slice'] = [
                                'next' => $image_album_slice['next']['url_viewer'],
                                'prev' => $image_album_slice['prev']['url_viewer'],
                                'html' => $html,
                            ];
                        } else {
                            $json_array['image']['album']['slice'] = null;
                        }

                        break;

                    case 'album':

                        if ($id) {
                            $source_album_db = CHV\Album::getSingle($id, false, false); // Farso farso!!
                            if (!$source_album_db) {
                                throw new Exception("Album doesn't exists", 100);
                            }
                            if (!$handler::getCond('content_manager') and $source_album_db['album_user_id'] !== $logged_user['id']) {
                                throw new Exception('Invalid content owner request', 102);
                            }
                        }

                        // We want to move contents or edit?
                        if (!empty($editing['album_id']) or $editing['new_album']) {
                            $album_move = true;
                            if ($editing['new_album']) {
                                if (CHV\getSetting('akismet')) {
                                    CHV\Akismet::checkAlbum($editing['album_name'], $editing['album_description'], $source_album_db);
                                }
                                $editing['album_id'] = CHV\Album::insert($editing['album_name'], $source_album_db['album_user_id'], $editing['album_privacy'], $editing['album_description'], $editing['album_password']);
                            }
                            CHV\Album::moveContents($id, $editing['album_id']);
                        } else {
                            unset($editing['album_privacy'], $editing['new_album'], $editing['album_name']);
                            if (CHV\getSetting('akismet')) {
                                CHV\Akismet::checkAlbum($editing['name'], $editing['description'], $source_album_db);
                            }
                            CHV\Album::update($id, $editing);
                        }

                        // Get the edited album
                        $album_edited = CHV\Album::getSingle($editing['album_id'] ? $editing['album_id'] : $id);

                        if (!$album_edited) {
                            throw new Exception("Edited album doesn't exists", 100);
                        }

                        $json_array['status_code'] = 200;
                        $json_array['success'] = ['message' => 'Album edited', 'code' => 200];

                        // New moved album or current edited album
                        $json_array['album'] = $album_edited;

                        if ($album_move) {
                            $json_array['old_album'] = CHV\Album::formatArray(CHV\Album::getSingle($id, false, false), true); // Safe formatted album
                            $json_array['album']['html'] = CHV\Listing::getAlbumHtml($album_edited['id']);
                            $json_array['old_album']['html'] = CHV\Listing::getAlbumHtml($id);
                        }

                        break;

                    case 'category':
                        if (!$logged_user['is_admin']) {
                            throw new Exception('Invalid content owner request', 107);
                        }
                        // Validate ID
                        $id = $_REQUEST['editing']['id'];
                        if (!array_key_exists($id, $handler::getVar('categories'))) {
                            throw new Exception('Invalid target category', 100);
                        }
                        // Validate name
                        if (!$editing['name']) {
                            throw new Exception('Invalid category name', 101);
                        }
                        // Validate URL key
                        if (!preg_match('/^[\-\w]+$/', $editing['url_key'])) {
                            throw new Exception('Invalid category URL key', 102);
                        }

                        //  Category URL key being used?
                        if ($handler::getVar('categories')) {
                            foreach ($handler::getVar('categories') as $v) {
                                if ($v['id'] == $id) {
                                    continue;
                                }
                                if ($v['url_key'] == $editing['url_key']) {
                                    $category_error = true;
                                    break;
                                }
                            }
                        }
                        if ($category_error) {
                            throw new Exception('Category URL key already being used.', 103);
                        }

                        G\nullify_string($editing['description']);

                        $update_category = CHV\DB::update('categories', $editing, ['id' => $id]);

                        if (!$update_category) {
                            throw new Exception('Failed to edit category', 400);
                        }

                        $category = CHV\DB::get('categories', ['id' => $id])[0];
                        $category['category_url'] = G\get_base_url('category/'.$category['category_url_key']);
                        $category = CHV\DB::formatRow($category);

                        $json_array['status_code'] = 200;
                        $json_array['success'] = ['message' => 'Category edited', 'code' => 200];
                        $json_array['category'] = $category;

                        break;

                    case 'ip_ban':
                        if (!$handler::getCond('content_manager')) {
                            throw new Exception('Invalid content owner request', 108);
                        }

                        $id = $_REQUEST['editing']['id'];

                        // Validate IP
                        CHV\Ip_ban::validateIP($editing['ip']);

                        // Validate expiration
                        if (!empty($editing['expires']) and !preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/', $editing['expires'])) {
                            throw new Exception('Invalid expiration date format', 102);
                        }

                        try {
                            // Already banned?
                            $ip_already_banned = CHV\Ip_ban::getSingle(['ip' => $editing['ip']]);

                            if ($ip_already_banned and $ip_already_banned['id'] !== $id) {
                                throw new Exception(_s('IP address already banned'), 103);
                            }

                            // Fix expiration
                            if (empty($editing['expires'])) {
                                $editing['expires'] = null;
                            }

                            // OK to go
                            $editing = array_merge($editing, ['expires_gmt' => is_null($editing['expires']) ? null : gmdate('Y-m-d H:i:s', strtotime($editing['expires']))]);

                            if (!CHV\Ip_ban::update(['id' => $id], $editing)) {
                                throw new Exception('Failed to edit IP ban', 400);
                            }

                            $json_array['status_code'] = 200;
                            $json_array['success'] = ['message' => 'IP ban edited', 'code' => 200];
                            $json_array['ip_ban'] = CHV\Ip_ban::getSingle(['id' => $id]);
                        } catch (Exception $e) {
                            $json_array = [
                                'status_code' => 403,
                                'error'       => ['message' => $e->getMessage(), $e->getCode()],
                            ];
                            break;
                        }

                        break;

                    case 'storage':

                        if (!$logged_user['is_admin']) {
                            throw new Exception('Invalid content owner request', 109);
                        }

                        $id = $_REQUEST['editing']['id'];

                        try {
                            $storage_update = CHV\Storage::update($id, $editing);
                        } catch (Exception $e) {
                            $json_array = [
                                'status_code' => 403,
                                'error'       => ['message' => $e->getMessage(), 403],
                            ];
                            break;
                        }

                        $storage = CHV\Storage::getSingle($id);

                        $json_array['status_code'] = 200;
                        $json_array['success'] = ['message' => 'Storage edited', 'code' => 200];
                        $json_array['storage'] = $storage;

                        break;
                }

                break;

            case 'add-user':

                // Must be admin
                if (!$logged_user['is_admin']) {
                    throw new Exception(_s('Request denied'), 403);
                }

                $user = $_REQUEST['user'];

                foreach (['username', 'email', 'password', 'role'] as $v) {
                    if ($user[$v] == '') {
                        throw new Exception(_s('Missing values'), 100);
                    }
                }

                // Validate username
                if (!CHV\User::isValidUsername($user['username'])) {
                    throw new Exception(_s('Invalid username'), 101);
                }

                // Validate email
                if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception(_s('Invalid email'), 102);
                }

                // Validate password
                if (!preg_match('/'.CHV\getSetting('user_password_pattern').'/', $user['password'])) {
                    throw new Exception(_s('Invalid password'), 103);
                }

                // Validate role
                if (!in_array($user['role'], ['user', 'manager', 'admin'])) {
                    throw new Exception(_s('Invalid role'), 104);
                }

                // Username already being used?
                if (CHV\DB::get('users', ['username' => $user['username']])) {
                    throw new Exception(_s('Username already being used'), 200);
                }

                // Email already being used?
                if (CHV\DB::get('users', ['email' => $user['email']])) {
                    throw new Exception(_s('Email already being used'), 200);
                }

                // Ok to create user
                $is_manager = 0;
                $is_admin = 0;
                switch ($user['role']) {
                    case 'manager':
                        $is_manager = 1;
                        break;
                    case 'admin':
                        $is_admin = 1;
                        break;
                }

                $add_user = CHV\User::insert([
                    'username'   => $user['username'],
                    'email'      => $user['email'],
                    'is_admin'   => $is_admin,
                    'is_manager' => $is_manager,
                ]);

                // Add the password
                if ($add_user) {
                    CHV\Login::addPassword($add_user, $user['password'], false);
                }

                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'User added', 'code' => 200];

                break;

            case 'add-category':
                // Must be admin
                if (!$logged_user['is_admin']) {
                    throw new Exception(_s('Request denied'), 403);
                }

                $category = $_REQUEST['category'];

                foreach (['name', 'url_key'] as $v) {
                    if ($category[$v] == '') {
                        throw new Exception(_s('Missing values'), 100);
                    }
                }

                // Validate URL key
                if (!preg_match('/^[-\w]+$/', $category['url_key'])) {
                    throw new Exception('Invalid category URL key', 102);
                }

                // Category URL key being used?
                if ($handler::getVar('categories')) {
                    foreach ($handler::getVar('categories') as $v) {
                        if ($v['url_key'] == $category['url_key']) {
                            $category_error = true;
                            break;
                        }
                    }
                }
                if ($category_error) {
                    throw new Exception('Category URL key already being used.', 103);
                }

                G\nullify_string($category['description']);

                $category = G\array_filter_array($category, ['name', 'url_key', 'description'], 'exclusion');

                // Ok to add category
                $add_category = CHV\DB::insert('categories', $category);

                $category = CHV\DB::get('categories', ['id' => $add_category])[0];
                $category['category_url'] = G\get_base_url('category/'.$category['category_url_key']);
                $category = CHV\DB::formatRow($category);

                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'Category added', 'code' => 200];
                $json_array['category'] = $category;

                break;

            case 'add-ip_ban':
                if (!$handler::getCond('content_manager')) {
                    throw new Exception(_s('Request denied'), 403);
                }

                $ip_ban = G\array_filter_array($_REQUEST['ip_ban'], ['ip', 'expires', 'message'], 'exclusion');

                // Validate IP
                CHV\Ip_ban::validateIP($ip_ban['ip']);

                // Validate expiration
                if (!empty($ip_ban['expires']) and !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ip_ban['expires'])) {
                    throw new Exception('Invalid expiration date format', 102);
                }

                try {
                    // Already banned?
                    if (CHV\Ip_ban::getSingle(['ip' => $ip_ban['ip']])) {
                        throw new Exception(_s('IP address already banned'), 103);
                    }

                    // Fix expiration
                    if (empty($ip_ban['expires'])) {
                        $ip_ban['expires'] = null;
                    }

                    // OK to go
                    $ip_ban = array_merge($ip_ban, ['date' => G\datetime(), 'date_gmt' => G\datetimegmt(), 'expires_gmt' => is_null($ip_ban['expires']) ? null : gmdate('Y-m-d H:i:s', strtotime($ip_ban['expires']))]);
                    $add_ip_ban = CHV\Ip_ban::insert($ip_ban);
                } catch (Exception $e) {
                    $json_array = [
                        'status_code' => 403,
                        'error'       => ['message' => $e->getMessage(), $e->getCode()],
                    ];
                    break;
                }

                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'IP ban added', 'code' => 200];
                $json_array['ip_ban'] = CHV\Ip_ban::getSingle(['id' => $add_ip_ban]);

                break;

            case 'add-storage':
                // Must be admin
                if (!$logged_user['is_admin']) {
                    throw new Exception(_s('Request denied'), 403);
                }

                $storage = $_REQUEST['storage'];

                try {
                    $add_storage = CHV\Storage::insert($storage);
                } catch (Exception $e) {
                    $json_array = [
                        'status_code' => 403,
                        'error'       => ['message' => $e->getMessage(), 'code' => 403],
                    ];
                    break;
                }

                $storage = CHV\Storage::getSingle($add_storage);
                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'Storage added', 'code' => 200];
                $json_array['storage'] = $storage;

                break;

            case 'edit-category':
            case 'flag-safe':
            case 'flag-unsafe':

                if (!$logged_user) {
                    throw new Exception(_s('Login needed'), 403);
                }

                $editing = $_REQUEST['editing'];
                $owner_id = $logged_user['id'];

                // Admin
                if (!$handler::getCond('content_manager') and $owner_id !== $logged_user['id']) {
                    throw new Exception('Invalid content owner request', 110);
                }

                $ids = [];
                foreach ($editing['ids'] as $id) {
                    $ids[] = CHV\decodeID($id);
                }

                $images = CHV\Image::getMultiple($ids);
                $images_ids = [];

                foreach ($images as $image) {
                    if (!$handler::getCond('content_manager') and $image['image_user_id'] != $logged_user['id']) {
                        continue;
                    }
                    $images_ids[] = $image['image_id'];
                }

                if (!$images_ids) {
                    throw new Exception('Invalid content owner request', 111);
                }

                // There is no CHV\Image::editMultiple, so we must cast manually the editing

                switch ($doing) {
                    case 'flag-safe':
                    case 'flag-unsafe':
                        $query_field = 'nsfw';
                        $prop = $editing['nsfw'] == 1 ? 1 : 0;
                        $msg = 'Content flag changed';
                        break;
                    case 'edit-category':
                        $query_field = 'category_id';
                        $prop = $editing['category_id'] ?: null;
                        $msg = 'Content category edited';
                        break;
                }

                $db = CHV\DB::getInstance();
                $db->query('UPDATE `'.CHV\DB::getTable('images').'` SET `image_'.$query_field.'`=:prop WHERE `image_id` IN ('.implode(',', $images_ids).')');
                $db->bind(':prop', $prop);
                $db->exec();

                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => $msg, 'code' => 200];

                if ($query_field == 'category_id') {
                    $json_array['category_id'] = $prop;
                }

                break;

            case 'move':
            case 'create-album':

                $type = $_REQUEST['type'];

                if (!in_array($type, ['images', 'album', 'albums'])) {
                    throw new Exception('Invalid album '.($doing == 'move' ? 'move' : 'create').' request', 100);
                }

                $album = $_REQUEST['album'];
                $album['new'] = $album['new'] == 'true';
                $owner_id = !empty($_REQUEST['owner']) ? CHV\decodeID($_REQUEST['owner']) : $logged_user['id'];

                if (!$handler::getCond('content_manager') && $owner_id !== $logged_user['id']) {
                    throw new Exception('Invalid content owner request', 112);
                }

                if ($logged_user == false) {
                    if ($album['new'] == false) {
                        throw new Exception('Invalid request', 403);
                    }
                }

                // Inject the system level privacy override
                if ($handler::getCond('forced_private_mode')) {
                    $album['privacy'] = CHV\getSetting('website_content_privacy_mode');
                }

                if (CHV\getSetting('akismet') && $album['new']) {
                    CHV\Akismet::checkAlbum($album['name'], $album['description'], $owner_id == $logged_user['id'] ? $logged_user_source_db : null);
                }

                // Had to create an album ?
                $album_id = $album['new'] ? CHV\Album::insert($album['name'], $owner_id, $album['privacy'], $album['description'], $album['password']) : CHV\decodeID($album['id']);
                $album_db = CHV\Album::getSingle($album_id, false, false);

                if (is_array($album['ids'])) {
                    if (count($album['ids']) == 0) {
                        throw new Exception('Invalid source album ids '.($doing == 'move' ? 'move' : 'create').' request', 100);
                    }
                    if (count($album['ids']) > 0) {
                        $ids = [];
                        foreach ($album['ids'] as $id) {
                            $ids[] = CHV\decodeID($id);
                        }
                    }
                }
                // IF $ids then append these contents
                if (is_array($ids) && count($ids) > 0) {
                    // Move by type
                    if ($type == 'images') {
                        $images = CHV\Image::getMultiple($ids);
                        $images_ids = [];

                        foreach ($images as $image) {
                            if ($logged_user == false && in_array($image['image_id'], $_SESSION['guest_images']) == false) {
                                continue;
                            }
                            if (!$handler::getCond('content_manager') && $image['image_user_id'] != $logged_user['id']) {
                                continue;
                            }
                            $images_ids[] = $image['image_id'];
                        }

                        if (!$images_ids) {
                            throw new Exception('Invalid content owner request', 104);
                        }

                        $album_add = CHV\Album::addImages($album_db['album_id'], $images_ids);
                    } else {
                        $album_move = true;

                        $albums = CHV\Album::getMultiple($ids);
                        $albums_ids = [];

                        foreach ($albums as $album) {
                            if (!$handler::getCond('content_manager') && $album['album_user_id'] != $logged_user['id']) {
                                continue;
                            }
                            $albums_ids[] = $album['album_id'];
                        }

                        if (!$albums_ids) {
                            throw new Exception('Invalid content owner request', 105);
                        }

                        $album_to_album = CHV\Album::moveContents($albums_ids, $album_id);
                    }
                }

                $album_move_db = $album_db['album_id'] ? CHV\Album::getSingle($album_db['album_id'], false, false) : CHV\User::getStreamAlbum($owner_id);

                $json_array['status_code'] = 200;
                $json_array['success'] = ['message' => 'Content added to album', 'code' => 200];

                // Moving to album
                if ($album_move_db) {
                    $json_array['album'] = CHV\Album::formatArray($album_move_db, true);
                    $json_array['album']['html'] = CHV\Listing::getAlbumHtml($album_move_db['album_id']);
                }

                // Add the old albums to the object
                if ($type == 'albums') {
                    $json_array['albums_old'] = [];
                    foreach ($ids as $album_id) {
                        $album_item = CHV\Album::formatArray(CHV\Album::getSingle($album_id, false, false), true);
                        $album_item['html'] = CHV\Listing::getAlbumHtml($album_id);
                        $json_array['albums_old'][] = $album_item;
                    }
                }

                break;

            case 'delete':

                if (!$logged_user) {
                    throw new Exception(_s('Login needed'), 403);
                }

                $deleting = $_REQUEST['deleting'];
                $type = $_REQUEST['delete'];

                if (!$handler::getCond('content_manager') && !CHV\getSetting('enable_user_content_delete') && (G\starts_with('image', $type) || G\starts_with('album', $type))) {
                    throw new Exception('Forbidden action', 403);
                }

                $owner_id = $_REQUEST['owner'] != null ? CHV\decodeID($_REQUEST['owner']) : $logged_user['id'];

                $multiple = $_REQUEST['multiple'] == 'true';
                $single = $_REQUEST['single'] == 'true';
                if (!$multiple) {
                    $single = true;
                }

                // Admin
                if (in_array($type, ['avatar', 'background', 'user', 'category', 'ip_ban']) and !$handler::getCond('content_manager') and $owner_id !== $logged_user['id']) {
                    throw new Exception('Invalid content owner request', 113);
                }

                if (in_array($type, ['avatar', 'background'])) {
                    try {
                        CHV\User::deletePicture($owner_id == $logged_user['id'] ? $logged_user : $owner_id, $type);
                        $json_array['status_code'] = 200;
                        $json_array['success'] = ['message' => 'Profile background deleted', 'code' => 200];
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode());
                    }
                    break;
                }

                if ($type == 'user') {
                    $delete_user_id = $owner_id == $logged_user['id'] ? $logged_user : $owner_id;
                    $delete_user = CHV\User::getSingle($delete_user_id, 'id');
                    // Only admins can touch other admins and managers
                    if ($delete_user['is_content_manager'] && CHV\Login::isAdmin() == false) {
                        throw new Exception("Can't touch this!", 666);
                    }
                    CHV\User::delete($delete_user_id);
                    break;
                }

                if ($single) {
                    if (is_null($deleting['id'])) {
                        throw new Exception('Missing delete target id', 100);
                    }
                } else {
                    if (is_array($deleting['ids']) && count($deleting['ids']) == 0) {
                        throw new Exception('Missing delete target ids', 100);
                    }
                }

                if ($type == 'category') {
                    if (!array_key_exists($deleting['id'], $handler::getVar('categories'))) {
                        throw new Exception('Invalid target category', 100);
                    }
                    $delete_category = CHV\DB::delete('categories', ['id' => $deleting['id']]);
                    if ($delete_category) {
                        $update_images = CHV\DB::update('images', ['category_id' => null], ['category_id' => $deleting['id']]);
                    } else {
                        throw new Exception('Error deleting category', 400);
                    }
                    break;
                }

                if ($type == 'ip_ban') {
                    if (!CHV\Ip_ban::delete(['id' => $deleting['id']])) {
                        throw new Exception('Error deleting IP ban', 400);
                    }
                    break;
                }

                if (!in_array($type, ['image', 'album', 'images', 'albums'])) {
                    throw new Exception('Invalid delete request', 100);
                }

                $db_field_prefix = in_array($type, ['image', 'images']) ? 'image' : 'album';

                switch ($type) {
                    case 'image':
                    case 'images':
                        $Class_fn = 'CHV\\Image';
                        break;

                    case 'album':
                    case 'albums':
                        $Class_fn = 'CHV\\Album';
                        break;
                }

                if ($single) {
                    if (is_null($deleting['id'])) {
                        throw new Exception('Missing delete target id', 100);
                    } else {
                        $id = CHV\decodeID($deleting['id']);
                    }

                    $content_db = $Class_fn::getSingle($id, false, false);

                    if ($content_db) {
                        if (!$handler::getCond('content_manager') and $content_db[$db_field_prefix.'_user_id'] !== $logged_user['id']) {
                            throw new Exception('Invalid content owner request', 114);
                        }
                        $delete = $Class_fn::delete($id);
                    } else {
                        throw new Exception("Content doesn't exists", 100);
                    }

                    $affected = $delete;
                } else {
                    if (!is_array($deleting['ids'])) {
                        throw new Exception('Expecting ids array values, '.gettype($deleting['ids']).' given', 100);
                    }

                    if (count($deleting['ids']) > 0) {
                        $ids = [];
                        foreach ($deleting['ids'] as $id) {
                            $ids[] = CHV\decodeID($id);
                        }
                    }

                    $contents_db = $Class_fn::getMultiple($ids);
                    $owned_ids = [];

                    foreach ($contents_db as $content_db) {
                        if (!$handler::getCond('content_manager') and $content_db[$db_field_prefix.'_user_id'] != $logged_user['id']) {
                            continue;
                        }
                        $owned_ids[] = $content_db[$db_field_prefix.'_id'];
                    }

                    if (!$owned_ids) {
                        throw new Exception('Invalid content owner request', 106);
                    }

                    $delete = $Class_fn::deleteMultiple($owned_ids);

                    $affected = $delete;
                }

                $json_array['success'] = [
                    'message'  => ucfirst($type).' deleted',
                    'code'     => 200,
                    'affected' => $affected,
                ];

                break;

            case 'disconnect': // admin only

                if (!$logged_user) {
                    throw new Exception(_s('Login needed'), 403);
                }

                $disconnect = strtolower($_REQUEST['disconnect']);
                $disconnect_label = ucfirst($disconnect);
                $user_id = $_REQUEST['user_id'] ? CHV\decodeID($_REQUEST['user_id']) : null; // Optional param (allow admin to disconnect any user)

                if (!$logged_user['is_admin'] and $user_id and $user_id !== $logged_user['id']) {
                    throw new Exception('Invalid request', 403);
                }

                $user = !$user_id ? $logged_user : CHV\User::getSingle($user_id, 'id');

                $login_connection = $user['login'][$disconnect];

                // Invalid disconnect request
                if (!array_key_exists($disconnect, CHV\Login::getSocialServices(['get' => 'enabled']))) {
                    throw new Exception('Invalid disconnect value', 10);
                }

                // Login connection never existed
                if (!$login_connection) {
                    throw new Exception("Login connection doesn't exists", 11);
                }

                // This login connection is the only one for this user
                if ($user['login_rows'] == 1) {
                    throw new Exception(_s('Add a password or another social connection before deleting %s', $disconnect_label), 12);
                }

                // Lets count this user social connections...
                $user_social_conn = 0;
                foreach (CHV\Login::getSocialServices(['flat' => true]) as $k) {
                    if (array_key_exists($k, $user['login'])) {
                        $user_social_conn++;
                    }
                }

                // So this user has one social conn. + a password... Does this user has an email?
                if ($user_social_conn == 1 and array_key_exists('password', $user['login'])) {
                    if (CHV\getSetting('require_user_email_confirmation') and !$user['email']) {
                        throw new Exception(_s('Add an email or another social connection before deleting %s', $disconnect_label), 12);
                    }
                }

                // Do the thing
                $delete_connection = CHV\Login::delete(['type' => $disconnect, 'user_id' => $user['id']]);
                $delete_cookies = CHV\Login::delete(['type' => 'cookie_'.$disconnect, 'user_id' => $user['id']]);

                if ($delete_connection) {
                    if (in_array($disconnect, ['twitter', 'facebook'])) {
                        CHV\User::update($user['id'], [$disconnect.'_username' => null]);
                    }
                    $json_array['success'] = ['message' => _s('%s has been disconnected.', $disconnect_label), 'code' => 200];
                } else {
                    throw new Exception('Error deleting connection', 666);
                }

                break;

            case 'testEmail':
                if (!$logged_user['is_admin']) {
                    throw new Exception('Invalid request', 403);
                }
                // Validate email
                if (!filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception(_s('Invalid email'), 100);
                }
                CHV\send_mail($_REQUEST['email'], _s('Test email from %s @ %t', ['%s' => CHV\getSetting('website_name'), '%t' => G\datetime()]), '<p>'._s('This is just a test').'</p>');
                $json_array['success'] = [
                    'message' => _s('Test email sent to %s.', $_REQUEST['email']),
                    'code'    => 200,
                ];
                break;

            case 'encodeId':
            case 'decodeId':
                if (!$logged_user['is_admin']) {
                    throw new Exception('Invalid request', 403);
                }
                if ($_REQUEST['id'] == null) {
                    throw new Exception('Invalid request', 100);
                }
                $thing = str_replace('Id', null, $doing);
                $id = $_REQUEST['id'];
                $fn = 'CHV\\'.$thing.'ID';
                $res = $fn($id);
                $json_array['success'] = [
                    'message' => $id.' == '.$res,
                    'code'    => 200,
                    $thing    => $res,
                ];
                break;

            case 'exportUser':
                if (!$logged_user['is_admin']) {
                    throw new Exception('Invalid request', 403);
                }
                // Validate id
                if ($_REQUEST['username'] == null) {
                    throw new Exception(_s('Invalid username'), 100);
                }
                $user = CHV\User::getSingle($_REQUEST['username'], 'username', false);
                if ($user == false) {
                    throw new Exception(_s('Invalid username'), 101);
                }
                $user = CHV\DB::formatRow($user);
                if ($_REQUEST['download'] == 0) {
                    $json_array['success'] = [
                        'message'  => _s('Downloading %s data', "'".$user['username']."'"),
                        'code'     => 200,
                        'redirURL' => G\get_current_url().'&action=exportUser&download=1',
                    ];
                } else {
                    $filename = $user['username'].'.json';
                    $user = G\array_filter_array($user, ['name', 'username', 'email', 'facebook_username', 'twitter_username', 'website', 'bio', 'timezone', 'language', 'is_private', 'newsletter_subscribe']);
                    $user = json_encode($user, JSON_PRETTY_PRINT);
                    header('Content-type: application/json');
                    header('Content-Disposition: attachment; filename='.$filename);
                    header('Last-Modified: '.G\datetimegmt('D, d M Y H:i:s').' UTC');
                    header('Cache-Control: must-revalidate, pre-check=0, post-check=0, max-age=0');
                    header('Pragma: anytextexeptno-cache', true);
                    header('Cache-control: private', false);
                    header('Expires: 0');
                    echo $user;
                    die();
                }
                break;

            case 'follow':
            case 'unfollow':
                if (!$logged_user || !CHV\getSetting('enable_followers') || $logged_user['is_private']) {
                    throw new Exception('Invalid request', 403);
                }
                $follow_array = [
                    'user_id'          => $logged_user['id'],
                    'followed_user_id' => CHV\decodeID($_REQUEST[$doing]['id']),
                ];
                $return = $doing == 'follow' ? CHV\Follow::insert($follow_array) : CHV\Follow::delete($follow_array);
                if ($return) {
                    unset($return['id']);
                    $json_array['success'] = [
                        'message' => $doing == 'follow' ? _s('User %s followed', $return['username']) : _s('User %s unfollowed', $return['username']),
                        'code'    => 200,
                    ];
                    $json_array['user_followed'] = $return;
                }
                break;

            case 'like':
            case 'dislike':
                if (!$logged_user || !CHV\getSetting('enable_likes')) {
                    throw new Exception('Invalid request', 403);
                }
                $like_array = [
                    'user_id'      => $logged_user['id'],
                    'content_id'   => CHV\decodeID($_REQUEST[$doing]['id']),
                    'content_type' => $_REQUEST[$doing]['object'],
                ];
                $return = $doing == 'like' ? CHV\Like::insert($like_array) : CHV\Like::delete($like_array);
                if ($return) {
                    $return['id_encoded'] = CHV\encodeID($return['id']);
                    unset($return['id']);
                    $json_array['success'] = [
                        'message' => $doing == 'like' ? _s('Content liked', $return['content']['id_encoded']) : _s('Content disliked', $return['content']['id_encoded']),
                        'code'    => 200,
                    ];
                    $json_array['content'] = $return;
                }
                break;

            case 'regenStorageStats':
                if (!$logged_user['is_admin']) {
                    throw new Exception('Invalid request', 403);
                }
                $res = CHV\Storage::regenStorageStats($_REQUEST['storageId']);
                $json_array['success'] = [
                    'message' => $res,
                    'code'    => 200,
                ];
                break;

            case 'migrateStorage':
                if (!$logged_user['is_admin']) {
                    throw new Exception('Invalid request', 403);
                }
                $res = CHV\Storage::migrateStorage($_REQUEST['sourceStorageId'], $_REQUEST['targetStorageId']);
                $json_array['success'] = [
                    'message' => $res,
                    'code'    => 200,
                ];
                break;

            case 'notifications':
                if (!$logged_user) {
                    throw new Exception('Invalid request', 403);
                }
                $notification_array = [
                    'user_id' => $logged_user['id'],
                ];
                $notifications = CHV\Notification::get($notification_array);
                CHV\Notification::markAsRead($notification_array);
                $json_array['status_code'] = 200;
                if ($notifications) {
                    $json_array['html'] = '';
                    $template = '<li%class>%avatar<span class="notification-text">%message</span><span class="how-long-ago">%how_long_ago</span></li>';
                    $avatar_src_tpl = [
                        0 => '<span class="user-image default-user-image"><span class="icon icon-user2"></span></span>',
                        1 => '<img class="user-image" src="%user_avatar_url" alt="%user_name_short_html">',
                    ];
                    $avatar_tpl = [
                        0 => $avatar_src_tpl[0],
                        1 => '<a href="%user_url">%user_avatar</a>',
                    ];
                    foreach ($notifications as $k => $v) {
                        $content_type = $v['content_type'];
                        switch ($v['type']) {
                            case 'like':
                                $message = _s('%u liked your %t %c', [
                                    '%t' => _s($content_type),
                                    '%c' => '<a href="'.$v[$content_type]['url_viewer'].'">'.$v[$content_type][($content_type == 'image' ? 'title' : 'name').'_truncated_html'].'</a>',
                                ]);
                                break;
                            case 'follow':
                                $message = _s('%u is now following you');
                                break;
                        }
                        $v['message'] = strtr($message, [
                            '%u' => $v['user']['is_private'] ? _s('A private user') : ('<a href="'.$v['user']['url'].'">'.$v['user']['name_short_html'].'</a>'),
                        ]);
                        if ($v['user']['is_private']) {
                            $avatar = $avatar_tpl[0];
                        } else {
                            $avatar = strtr($avatar_tpl[1], [
                                '%user_url'    => $v['user']['url'],
                                '%user_avatar' => strtr($avatar_src_tpl[isset($v['user']['avatar']) ? 1 : 0], [
                                    '%user_avatar_url'      => $v['user']['avatar']['url'],
                                    '%user_name_short_html' => $v['user']['name_short_html'],
                                ]),
                            ]);
                        }
                        $json_array['html'] .= strtr($template, [
                            '%class'        => !$v['is_read'] ? ' class="new"' : null,
                            '%avatar'       => $avatar,
                            '%user_url'     => $v['user']['url'],
                            '%message'      => $v['message'],
                            '%how_long_ago' => CHV\time_elapsed_string($v['date_gmt']),
                        ]);
                    }
                    unset($content_type);
                } else {
                    $json_array['html'] = null;
                }
                break;

            // Adds the importer job (path+options)
            case 'importAdd':
                if ($_REQUEST['path'] == false) {
                    throw new Exception('Missing path parameter', 100);
                }
                $import->path = $_REQUEST['path'];
                if ($_REQUEST['options'] != false) {
                    $import->options = $_REQUEST['options'];
                }
                $import->add();
                $import->get();
                $json_array['status_code'] = 200;
                $json_array['import'] = $import->parsedImport;
                break;
            // Common operations
            case 'importStats':
            case 'importProcess':
            case 'importEdit':
            case 'importDelete':
                if ($_REQUEST['id'] == false) {
                    throw new Exception('Missing id parameter', 100);
                }
                $import->id = (int) $_REQUEST['id'];
                $import->get();
                break;
            default: // EX X
                throw new Exception(!G\check_value($doing) ? 'empty action' : 'invalid action', !G\check_value($doing) ? 0 : 1);
                break;
        }
        if (isset($import->id)) {
            switch ($doing) {
                // Check the importer stats (id)
                case 'importStats':
                    $json_array['status_code'] = 200;
                    $json_array['import'] = $import->parsedImport;
                    break;
                // Issue/Resume import operation (id+thread)
                case 'importProcess':
                    $import->thread = (int) $_REQUEST['thread'] ?: 1;
                    $import->process();
                    $json_array['status_code'] = 200;
                    break;
                // Edit import job (id,values)
                case 'importEdit':
                    if ($_REQUEST['values'] == false) {
                        throw new Exception('Missing values parameter', 101);
                    }
                    if (is_array($_REQUEST['values']) == false) {
                        throw new Exception('Expecting array values', 102);
                    }
                    $import->edit($_REQUEST['values']);
                    $import->get();
                    $json_array['import'] = $import->parsedImport;
                    $json_array['status_code'] = 200;
                    break;
                case 'importDelete':
                    $import->delete();
                    $json_array['status_code'] = 200;
                    $json_array['import'] = $import->parsedImport;
                    break;
            }
        }
        // Inject any missing status_code
        if (isset($json_array['success']) and !isset($json_array['status_code'])) {
            $json_array['status_code'] = 200;
        }
        $json_array['request'] = $_REQUEST;
        G\Render\json_output($json_array);
    } catch (Exception $e) {
        // G\debug($e);
        $json_array = G\json_error($e);
        $json_array['request'] = $_REQUEST;
        G\Render\json_output($json_array);
    }
};

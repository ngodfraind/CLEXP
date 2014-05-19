<?php 

use Symfony\Component\Yaml\Yaml;

 /*******
  * SQL *
  ******/

function claro_export_user_list($courseCode)
{    
    $tbl_mdb_names = claro_sql_get_main_tbl();
    $tbl_rel_course_user = $tbl_mdb_names['rel_course_user'  ];
    $tbl_users           = $tbl_mdb_names['user'             ];
    
    $sqlGetUsers = "SELECT `user`.`user_id`  AS `user_id`,
                       `user`.`nom`          AS `nom`,
                       `user`.`prenom`       AS `prenom`,
                       `user`.`email`        AS `email`,
                       `user`.`username`     AS `username`,
                       `user`.`isPlatformAdmin`,
                       `user`.`isCourseCreator`,
                       `course_user`.`profile_id`,
                       `course_user`.`isCourseManager`,
                       `course_user`.`tutor`  AS `tutor`,
                       `course_user`.`role`   AS `role`
               FROM `" . $tbl_users . "`           AS user,
                    `" . $tbl_rel_course_user . "` AS course_user
               WHERE `user`.`user_id`=`course_user`.`user_id`
               AND   `course_user`.`code_cours`='" . claro_sql_escape($courseCode) . "'
               ORDER BY `user`.`nom`,  `user`.`prenom` ;";
    
    return claro_sql_query_fetch_all_rows($sqlGetUsers);
}

function claro_export_forum_list($course, $categoryId)
{
    $tbl = get_module_course_tbl(array('bb_forums'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_forums']}`
        WHERE `bb_forums`.`cat_id` = {$categoryId}  
        ORDER BY forum_order";
    
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_category_list($course)
{
    $tbl = get_module_course_tbl(array('bb_categories'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_categories']}`
        ORDER BY cat_order";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_topic_list($course, $forumId)
{
    $tbl = get_module_course_tbl(array('bb_topics'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_topics']}`
        WHERE `bb_topics`.`forum_id` = {$forumId}";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_forum_post($course, $topicId)
{
    $tbl = get_module_course_tbl(array('bb_posts', 'bb_posts_text'), $course);
    $tblPosts     = $tbl['bb_posts'];
    $tblPostsText = $tbl['bb_posts_text'];
    
    $sql = "SELECT `post`.`poster_id`,
        `post`.`nom` as `last_name`,
        `post`.`prenom` as `first_name`,
        `post`.`post_time` as `post_time`,
        `post_text`.`post_text`
        FROM `" . $tblPosts . "` AS post,
        `" . $tblPostsText . "`  AS post_text
        WHERE `post_text`.`post_id` = `post`.`post_id`
        AND `post`.`topic_id` = {$topicId}";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_groups($course)
{
    $tbl = get_module_course_tbl(array('group_team'), $course);
    $tblGroups = $tbl['group_team'];
    
    $sql = "SELECT * FROM `" . $tblGroups . "`  ";
    
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_link_list($course, $locator)
{
    $tbl = get_module_course_tbl(array('lnk_links', 'lnk_resources'), $course);
    $tblLinks     = $tbl['lnk_links'];
    $tblResources = $tbl['lnk_resources'];

    $sql = "SELECT `dest`.`crl` AS `crl`, `dest`.`title` AS `title`\n"
        . "FROM `{$tblLinks}` AS `lnk`,\n"
        . "`{$tblResources}` AS `dest`,\n"
        . "`{$tblResources}` AS `src`\n"
        . "WHERE `src`.`crl` = " . Claroline::getDatabase()->quote( $locator->__toString() ) . "\n"
        . "AND `dest`.`id` = `lnk`.`dest_id`\n"
        . "AND `src`.`id` = `lnk`.`src_id`\n"
    ;

    return claro_sql_query_fetch_all_rows($sql);
}

 /********
  * YAML *
  *******/
  
  function export_users($course, $exportGroups)
{
    $users = claro_export_user_list($course);
    $export = [];

    foreach ($users as $user) {
        $el = array('user' => array('username' => $user['username'] ));
        
        $roles = array();
        $roles[] = array('name' => 'ROLE_WS_COLLABORATOR');
        
        if ($user['isCourseManager']) {
            $roles[] = array('name' => 'ROLE_WS_MANAGER');
        }
        
        if ($user['isPlatformAdmin']) {
            $roles[] = array('name' => 'ROLE_ADMIN');
        }
        
        if ($user['isCourseCreator']) {
            $roles[] = array('name' => 'ROLE_WS_CREATOR');
        }
        
        if ($exportGroups) {
            //do something
        }
        
        $el['user']['roles'] = $roles;
        $export[] = $el;
    }
    
    
    return $export;
}

function export_roles($course, $exportGroups)
{
    $roles = array(
        array(
            'role' => array(
                'name' => 'ROLE_WS_COLLABORATOR', 
                'translation' => 'collaborator', 
                'is_base_role' => false
            )
        )
    );
    
    if ($exportGroups) {
        $groups = claro_export_groups($course);
    
        foreach ($groups as $group) {
            $roles[] = array(
                'role' => array(
                    'name' => 'ROLE_WS_' . $group['secretDirectory'],
                    'translation' => $group['name'],
                    'is_base_role' => false
                )
            );
        }
    }
    
    return $roles;
}

function export_tools($course, $exportUser, $exportGroups)
{
    $resmData = export_resource_manager($course, $exportUser, $exportGroups);
    $home = export_home($course, $resmData);
    $tools = array();
    
    $home = array(
        'tool' => array(
            'type' => 'home',
            'translation' => 'accueil',
            'roles' => array(array('name' => 'ROLE_WS_COLLABORATOR')),
            'data' => $home
        )
    );
    
    $resm = array(
        'tool' => array(
            'type' => 'resource_manager',
            'translation' => 'ressources', 
            'roles' => array(array('name' => 'ROLE_WS_COLLABORATOR')),
            'data' => $resmData
        )
    );
    
    $tools[] = $home;
    $tools[] = $resm;
    
    return $tools;
}

function export_home($course, $resmData)
{
    $toolsIntroductions = new ToolIntroductionIterator($course);

    $editorialTab = array(
        'name' => 'Editorial'
    );

    @mkdir(__DIR__ . "/{$course}/home");
    @mkdir(__DIR__ . "/{$course}/home/editorial");

    foreach ($toolsIntroductions as $toolsIntroduction)
    {
        $uniqid = uniqid() . '.txt';
        $content = $toolsIntroduction->getContent();
        $locator = new ClarolineResourceLocator($course, 'CLINTRO', $toolsIntroduction->getId());
        $links = claro_export_link_list($course, $locator);

        foreach ($links as $link) {
            $el = ClarolineResourceLocator::parse($link['crl']);

            //pour le moment, je ne supporte que les pièces jointes de type "document".
            //Je laisse tomber les vrais liens pour le moment
            if ($el->getModuleLabel() === 'CLDOC') {
                $ids = findUidByPath($el->getResourceId(), $resmData);
                $content .= '</br>[[resource:' . $ids[0] . ':' . $ids[1] . ']]';
            }
        }

        file_put_contents(
            __DIR__ . "/{$course}/home/editorial/{$uniqid}",
            utf8_encode($content)
        );

        $editorialTab['widgets'][$toolsIntroduction->getRank()] = array(
            'widget' => array(
                'name' => $toolsIntroduction->getTitle(),
                'type' => 'simple_text',
                'data' => array(
                    array(
                        'locale' => 'fr',
                        'content' => "home/editorial/{$uniqid}"
                    )
                )
            )
        );
    }

    $data = array(array('tab' => $editorialTab));

    return $data;
}

function export_resource_manager($course, $exportUser, $exportGroups)
{
    $rootDir = __DIR__ . "/../../courses/{$course}/document";
    $uid = 1;
    $iid = 1;
    $directories = array();
    $items = array();
    $roles = array(get_resource_base_role());
    export_directory($rootDir, $directories, $items, $uid, $iid, $course, $roles);
    $items = export_forums($course, $items, $exportUser, $iid);
    
    if ($exportGroups) {
        $groups = claro_export_groups($course);
        
        foreach ($groups as $group) {
            $roles = array(
                array(
                    'role' => array(
                        'name' => 'ROLE_WS_' . $group['secretDirectory'],
                        'rights' => array(
                            'open' => true,
                            'export' => true
                        )
                    )
                )
            );
            
            $directories[] = array(
                'directory' => array(
                    'name' => utf8_encode($group['name']),
                    'creator' => null,
                    'parent' => 0,
                    'uid' => $uid,
                    'roles' => $roles
                )
            );
            
            $uid++;
            $rootDir = __DIR__ . "/../../courses/{$course}/group/{$group['secretDirectory']}";
            export_directory($rootDir, $directories, $items, $uid, $iid, $course, $roles);
        }
    }
    
    $data = array(
        'root' => array('uid' => 0, 'roles' => array(get_resource_base_role())),
        'directories' => $directories,
        'items' => $items
    );
    
    return $data;
}

function export_directory(
    $dir, 
    &$directories, 
    &$items, 
    &$uid, 
    &$iid,
    $course,
    $roles
)
{
    $iterator = new \DirectoryIterator($dir);
    $ds = DIRECTORY_SEPARATOR;
    $parent = $uid - 1;
    
    foreach ($iterator as $item) {
                
        if ($item->isFile()) {
            $finfo = finfo_open();
            $fileinfo = finfo_file($finfo, $item->getPathName(), FILEINFO_MIME);
            $fi = explode(';', $fileinfo);
            $extension = pathinfo($item->getBaseName(), PATHINFO_EXTENSION);
            $uniqid = uniqid() . '.' . $extension;
            copy($item->getPathName(), __DIR__ .  "/{$course}/{$uniqid}");
            
            $items[] = array(
                    'item' => array(
                    'name' => utf8_encode($item->getBaseName()),
                    'creator' => null,
                    'parent' => $parent,
                    'type' => 'file',
                    'roles' => $roles,
                    'uid' => $iid,
                    'data' => array(
                        array(
                            'file' => array(
                                'path' => $uniqid,
                                'mime_type' => $fi[0]
                            )
                        )
                    )
                )
            );
            
            $iid++;
        }
        
        if ($item->isDir() && !$item->isDot()) {
            $directories[] = array(
                'directory' => array(
                    'name' => utf8_encode($item->getBaseName()),
                    'creator' => null,
                    'parent' => $parent,
                    'uid' => $uid,
                    'roles' => $roles
                )
            );
                
            $uid++;
            
            export_directory(
                $dir . $ds . $item->getBaseName(), 
                $directories, 
                $items, 
                $uid, 
                $iid,
                $course,
                $roles
            );
        }
    }
    
    return $directories;
}

function export_forums($course, $items, $exportUser, &$iid)
{
    $categories = claro_export_category_list($course);
    @mkdir(__DIR__ . "/{$course}/forum");
    
    //$item list 
    foreach ($categories as $category) {
        $item = array(
            'name' => $category['cat_title'],
            'creator' => null,
            'parent' => 0,
            'uid' => $iid,
            'type' => 'claroline_forum',
            'is_rich' => true,
            'import' => array(array('path' => 'forum_' . $category['cat_id'] . '.yml'))
        );
        
        $items[] = array('item' => $item);
        $forums = claro_export_forum_list($course, $category['cat_id']);
        $categories = array();
        
        foreach ($forums as $forum) {
            $subjects = array();
            $topics = claro_export_topic_list($course, $forum['forum_id']);
            
            foreach ($topics as $topic) {
                $messages = array();
                $posts = claro_export_forum_post($course, $topic['topic_id']);
                @mkdir(__DIR__ . "/{$course}/forum/{$topic['topic_id']}");
                
                foreach ($posts as $post) {
                    $uniqid = uniqid() . '.txt';
                    file_put_contents(
                        __DIR__ . "/{$course}/forum/{$topic['topic_id']}/{$uniqid}", 
                        utf8_encode($post['post_text'])
                    );


                    if ($exportUser) {
                        $creator = user_get_properties($post['poster_id']);
                        $creatorUsername = $creator['username'];
                    } else {
                        $creatorUsername = null;
                    }
                    
                    $messages[] = array(
                        'message' => array(
                            'path' => "forum/{$topic['topic_id']}/{$uniqid}",
                            'creator' => $creatorUsername,
                            'creation_date' => $post['post_time']
                        )
                    );
                }
                
                $subjects[] = array(
                    'subject' => array(
                        'name' => $topic['topic_title'],
                        'creator' => null,
                        'messages' => $messages
                    )
                );
            }
            
            $categories[] = array(
                'category' => array(
                    'name' => $forum['forum_name'],
                    'subjects' => $subjects
                )
            );
        }
        
        $data['data'] = $categories;
        
        file_put_contents(
            __DIR__ . "/{$course}/forum_{$category['cat_id']}.yml", 
            utf8_encode(Yaml::dump($data, 10))
        );
    }
    
    return $items;
}

function get_resource_base_role()
{
    return array(
        'role' => array(
            'name' => 'ROLE_WS_COLLABORATOR',
            'rights' => array(
                'open' => true,
                'export' => true
            )
        )
    );
}

/*
 * http://www.php.net/manual/en/function.realpath.php
 */
function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
{
    $arFrom = explode($ps, rtrim($from, $ps));
    $arTo = explode($ps, rtrim($to, $ps));
    
    while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
        array_shift($arFrom);
        array_shift($arTo);
    }
    
    return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
}

/********
 * MAIN *
 *******/
 
function export($exportUser, $exportGroups, $course) {
    $coursedata = claro_get_course_data($course);
    $courseName = $coursedata['name'];
    $cid = $coursedata['id'];

    //temporary file directory wich is going to be zipped
    @mkdir(__DIR__ . "/{$course}");

    $data = array();
    $data['properties'] = array(
        'name' => $courseName,
        'code' => $course,
        'visible' => true,
        'self_registration' => true,
        'self_unregistration' => true,
        'owner' => null
    );

    if ($exportUser) {
        $data['members']['users'] = export_users($course, $exportGroups);
    }

    $data['roles'] = export_roles($course, $exportGroups);
    $data['tools'] = export_tools($course, $exportUser, $exportGroups, $cid);

    //add UTF-8 encoding
    file_put_contents(__DIR__ . "/{$course}/manifest.yml", utf8_encode(Yaml::dump($data, 10)));

    $zipArchive = new \ZipArchive();
    unlink(__DIR__ . "/{$course}.zip");
    $zipArchive->open(__DIR__ . "/{$course}.zip", \ZipArchive::CREATE);

    $dir = __DIR__ . "/{$course}";

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $el) {
        if ($el->isFile()) {
            $zipArchive->addFile($el->getPathName(), relativePath($dir, $el->getPathName())); 
        }
    }

    $zipArchive->close();

    foreach ($iterator as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($dir);
}

/********/
/* MISC */
/********/

function findUidByPath($path, $data)
{
    $elements = explode('/', $path);
    $last = array_pop($elements);
    $uids = [];
    $i = 0;

    //get the uid tree path
    foreach ($elements as $element) {
        $parentUid = ($i === 0) ? 0: $uids[$i - 1]['uid'];
        $uids[] = findDir($parentUid, $element, $data);
        $i++;
    }

    $lastParent = count($uids) > 0 ? array_pop($uids)['uid']: 0;

    //find the last leaf
    foreach($data['directories'] as $directory) {
        $dir = $directory['directory'];

        if ($dir['name'] === $last && $dir['parent'] === $lastParent) {
            return array('directory', $dir['uid']);
        }
    }

    foreach($data['items'] as $item) {
        $el = $item['item'];

        if ($el['name'] === $last && $el['parent'] === $lastParent) {
            return array('item', $el['uid']);
        }
    }

    throw new \Exception('Resource ' . $path . ' not found in the configuration file.');
}

function findDir($parentUid, $name, $data)
{
    foreach ($data['directories'] as $directory) {
        $dir = $directory['directory'];
        if ($dir['parent'] === $parentUid && $dir['name'] === $name) {
            return $dir;
        }
    }

    return null;
}


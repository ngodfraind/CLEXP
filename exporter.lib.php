<?php 

use Symfony\Component\Yaml\Yaml;

 /********
  * YAML *
  *******/
function export_roles($course)
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

    return $roles;
}

function export_tools($course)
{
    $resmData = export_resource_manager($course);
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
    $editorialTab = array( 'name' => 'Editorial');
    @mkdir(__DIR__ . "/{$course}/home");
    @mkdir(__DIR__ . "/{$course}/home/editorial");
    @mkdir(__DIR__ . "/{$course}/home/course_description");

    //tools introduction
    foreach ($toolsIntroductions as $toolsIntroduction)
    {
        $uniqid = uniqid() . '.txt';
        $content = $toolsIntroduction->getContent();
        $locator = new ClarolineResourceLocator($course, 'CLINTRO', $toolsIntroduction->getId());
        $links = claro_export_link_list($course, $locator);
        /*
        foreach ($links as $link) {
            $el = ClarolineResourceLocator::parse($link['crl']);

            //pour le moment, je ne supporte que les pièces jointes de type "document".
            //Je laisse tomber les vrais liens pour le moment
            if ($el->getModuleLabel() === 'CLDOC') {
                $ids = findUidByPath($el->getResourceId(), $resmData);
                $content .= '</br>[[internal:resource_manager:data:directories:' . $ids[0] . ':uid:' . $ids[1] . ']]';
            }
        }*/

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

    $courseDescriptionTab = array('name' => 'Description', 'widgets' => array());
    //course descriptions
    $courseDescriptions = claro_export_course_descriptions($course); 
    
    //@todo export locators
    foreach ($courseDescriptions as $courseDescription) {
        $uniqid = uniqid() . '.txt';
        $content = $courseDescription['content'];
        file_put_contents(
            __DIR__ . "/{$course}/home/course_description/{$uniqid}",
            utf8_encode($content)
        );
        
        $courseDescriptionTab['widgets'][] = array(
            'widget' => array(
                'name' => $courseDescription['title'],
                'type' => 'simple_text',
                'data' => array(
                    array(
                        'locale' => 'fr',
                        'content' => "home/course_description/{$uniqid}"
                    )
                )
            )
        );
    }

    $data[] = array('tab' => $courseDescriptionTab);
    $data[] = array('tab' => $editorialTab);

    return $data;
}

function export_resource_manager($course)
{
    $rootDir = __DIR__ . "/../../courses/{$course}/document";
    $uid = 1;
    $iid = 1;
    $directories = array();
    $items = array();
    $roles = array(get_resource_base_role());
    export_directory($rootDir, $directories, $items, $uid, $iid, $course, $roles);
    $items = export_forums($course, $items, $iid);
    $items = export_wikis($course, $items, $iid);
    $items = export_work_assignements($course, $items, $iid);
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

function export_wikis($course, $items, &$iid)
{
    $roles = array(get_resource_base_role());
    @mkdir(__DIR__ . "/{$course}/wiki");
    $con = Claroline::getDatabase();
    $config = array ();
    $tblList = claro_sql_get_course_tbl();
    $config["tbl_wiki_properties"] = $tblList["wiki_properties"];
    $config["tbl_wiki_pages"] = $tblList["wiki_pages"];
    $config["tbl_wiki_pages_content"] = $tblList["wiki_pages_content"];
    $config["tbl_wiki_acls"] = $tblList["wiki_acls"];
    $wikis  = claro_export_wiki_properties($course);
    
    foreach ($wikis as $data) {
        $wiki = new Wiki($con, $config);
        $wiki->load($data['id']);
        $exporter = new WikiToSingleHTMLExporter($wiki);
        $res = $exporter->export();
        $uniqid = 'wiki/' . uniqid() . '.txt';
        file_put_contents(
            __DIR__ . "/{$course}/{$uniqid}", 
            utf8_encode($res)
        );
                    
        //create "text" resource
        $item = array(
            'name' => $data['title'],
            'creator' => null,
            'parent' => 0,
            'type' => 'text',
            'is_rich' => true,
            'roles' => $roles,
            'uid' => $iid,
            'data' => array(
                array(
                    'file' => array(
                        'path' => $uniqid
                    )
                )
            )
        );
        
        $items[] = array('item' => $item);
        $iid++;
    }
    
    return $items;
}

function export_forums($course, $items, &$iid)
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

                    $creator = user_get_properties($post['poster_id']);
                    $creatorUsername = $creator['username'];
                    
                    $messages[] = array(
                        'message' => array(
                            'path' => "forum/{$topic['topic_id']}/{$uniqid}",
                            'creator' => $creatorUsername,
                            'author' => $creatorUsername,
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

        $iid++;
    }
    
    return $items;
}

function export_work_assignements($course, $items, &$iid)
{
    $wrkAssignments = claro_export_work_assignments($course);
    @mkdir(__DIR__ . "/{$course}/work_assignments");
    $roles = array(get_resource_base_role());
    
    foreach ($wrkAssignments as $wrkAssignment) {
        $uniqid = 'work_assignments/' . uniqid() . '.txt';
        file_put_contents(
            __DIR__ . "/{$course}/{$uniqid}", 
            utf8_encode($wrkAssignment['description'])
        );
        $item = array(
                'item' => array(
                'name' => $wrkAssignment['title'],
                'creator' => null,
                'parent' => 0,
                'uid' => $iid,
                'type' => 'text',
                'roles' => $roles,
                'is_rich' => true,
                'data' => array(
                    array(
                        'file' => array(
                            'path' => $uniqid
                        )
                    )
                )
            )
        );
        $items[] = $item;
        $iid++;
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
 
function export($course) {
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

    $data['roles'] = export_roles($course);
    $data['tools'] = export_tools($course, $cid);

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


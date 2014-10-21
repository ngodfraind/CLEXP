<?php 

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/richTextParser.class.php';
require_once __DIR__ . '/utils.lib.php';

class Exporter
{
    private $course;
    private $resourceBaseRoles;
    
    public function __construct($course)
    {
        $this->course = $course;
        $this->resourceBaseRoles = array(
            'role' => array(
                'name' => 'ROLE_WS_COLLABORATOR',
                'rights' => array(
                    'open' => true,
                    'export' => true
                )
            )
        );
    }
    
    public function export()
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $coursedata = claro_get_course_data($course);
        $courseName = $coursedata['name'];
        $cid = $coursedata['id'];

        //temporary file directory wich is going to be zipped
        $courseTmpDir = __DIR__ . "{$ds}{$course}";
        @mkdir($courseTmpDir);

        $data = array();
        $data['properties'] = array(
            'name' => $courseName,
            'code' => $course,
            'visible' => true,
            'self_registration' => true,
            'self_unregistration' => true,
            'owner' => null
        );

        $data['roles'] = $this->exportRoles();
        $data['tools'] = $this->exportTools();

        //now we parse and edit the urls for rich texts
        $richTextParser = new RichTextParser($data, $course, $courseTmpDir);
        $richTextParser->parseAndReplace();

        //add UTF-8 encoding
        file_put_contents(__DIR__ . "{$ds}{$course}{$ds}manifest.yml", utf8_encode(Yaml::dump($data, 10)));
        $fileName = __DIR__ . "{$ds}{$course}.zip";
        unlink($fileName);
        $archive = zipDir($courseTmpDir, true);
        rename($archive, $fileName);
        
        return $fileName;
    }
    
    private function exportRoles()
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
        
        $groups = claro_export_groups($this->course);

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
    
    private function exportTools()
    {
        $course = $this->course;
        $resmData = $this->exportResourceManager($course);
        $home = $this->exportHome($resmData);
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
    
    private function exportResourceManager()
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $rootDir = __DIR__ . "{$ds}..{$ds}..{$ds}courses{$ds}{$course}{$ds}document";
        $uid = 1;
        $iid = 1;
        $directories = array();
        $items = array();
        $roles = array($this->resourceBaseRoles);
        $this->exportDirectory($rootDir, $directories, $items, $uid, $iid, $roles);
        $items = $this->exportForums($items, $iid);
        $items = $this->exportWikis($items, $iid);
        $items = $this->exportWorkAssignments($items, $iid);
        $items = $this->exportScormPackages($items, $iid);
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
            $rootDir = __DIR__ . "{$ds}..{$ds}..{$ds}courses{$ds}{$course}{$ds}group{$ds}{$group['secretDirectory']}";
            $this->exportDirectory($rootDir, $directories, $items, $uid, $iid, $roles);
        }

        
        $data = array(
            'root' => array('uid' => 0, 'roles' => array($this->resourceBaseRoles)),
            'directories' => $directories,
            'items' => $items
        );
        
        return $data;
    }
    
    private function exportDirectory($dir, &$directories, &$items, &$uid, &$iid, $roles)
    {
        $course = $this->course;
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
                copy($item->getPathName(), __DIR__ .  "{$ds}{$course}{$ds}{$uniqid}");
                
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
                
                $this->exportDirectory(
                    $dir . $ds . $item->getBaseName(), 
                    $directories, 
                    $items, 
                    $uid, 
                    $iid,
                    $roles
                );
            }
        }
        
        return $directories;      
    }
    
    private function exportWikis($items, &$iid)
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $roles = array($this->resourceBaseRoles);
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}wiki");
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
            $uniqid = "wiki{$ds}" . uniqid() . '.txt';
            file_put_contents(
                __DIR__ . "{$ds}{$course}{$ds}{$uniqid}", 
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
    
    private function exportForums($items, &$iid)
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $categories = claro_export_category_list($course);
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}forum");
        $roles = array($this->resourceBaseRoles);
        
        //$item list 
        foreach ($categories as $category) {
            $item = array(
                'name' => $category['cat_title'],
                'creator' => null,
                'parent' => 0,
                'uid' => $iid,
                'type' => 'claroline_forum',
                'is_rich' => true,
                'roles' => $roles,
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
                    @mkdir(__DIR__ . "{$ds}{$course}{$ds}forum{$ds}{$topic['topic_id']}");
                    
                    foreach ($posts as $post) {
                        $uniqid = uniqid() . '.txt';
                        file_put_contents(
                            __DIR__ . "{$ds}{$course}{$ds}forum{$ds}{$topic['topic_id']}{$ds}{$uniqid}", 
                            utf8_encode($post['post_text'])
                        );

                        $creator = user_get_properties($post['poster_id']);
                        $creatorUsername = $creator['username'];
                        
                        $messages[] = array(
                            'message' => array(
                                'path' => "forum{$ds}{$topic['topic_id']}{$ds}{$uniqid}",
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
                __DIR__ . "{$ds}{$course}{$ds}forum_{$category['cat_id']}.yml", 
                utf8_encode(Yaml::dump($data, 10))
            );

            $iid++;
        }
        
        return $items;
    }
    
    private function exportWorkAssignments($items, &$iid)
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $wrkAssignments = claro_export_work_assignments($course);
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}work_assignments");
        $roles = array($this->resourceBaseRoles);
        
        foreach ($wrkAssignments as $wrkAssignment) {
            $uniqid = "work_assignments{$ds}" . uniqid() . '.txt';
            file_put_contents(
                __DIR__ . "{$ds}{$course}{$ds}{$uniqid}", 
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
    
    private function exportScormPackages($items, &$iid)
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $rootDir = __DIR__ . "{$ds}..{$ds}..{$ds}courses{$ds}{$course}{$ds}scormPackages";
        $iterator = new \DirectoryIterator($rootDir);
        $roles = array($this->resourceBaseRoles);
        $scormDir = __DIR__ . "{$ds}{$course}{$ds}scorm";
        @mkdir($scormDir);
        
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $archive = zipDir($item->getPathName(), $removeOldFiles = false);
                rename($archive, $scormDir . $ds . $item->getBaseName() . '.zip');
                
                $items[] = array(
                    'item' => array(
                    'name' => utf8_encode($item->getBaseName()),
                    'creator' => null,
                    'parent' => 0,
                    'type' => 'claroline_scorm_12',
                    'roles' => $roles,
                    'uid' => $iid,
                    'data' => array(
                        array(
                            'scorm12' => array(
                                'path' => "scorm{$ds}{$item->getBaseName()}.zip",
                                'version' => 'scorm-2012'
                                )
                            )
                        )
                    )
                );
                $iid++;
            }
        }
        
        return $items;
    }
    
    private function exportHome($resmData)
    {
        $ds = DIRECTORY_SEPARATOR;
        $course = $this->course;
        $toolsIntroductions = new ToolIntroductionIterator($course);
        $editorialTab = array( 'name' => 'Editorial');
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}home");
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}home{$ds}editorial");
        @mkdir(__DIR__ . "{$ds}{$course}{$ds}home{$ds}course_description");

        //tools introduction
        foreach ($toolsIntroductions as $toolsIntroduction)
        {
            $uniqid = uniqid() . '.txt';
            $content = $toolsIntroduction->getContent();
            $locator = new ClarolineResourceLocator($course, 'CLINTRO', $toolsIntroduction->getId());
            $links = claro_export_link_list($course, $locator);
            
            foreach ($links as $link) {
                $el = ClarolineResourceLocator::parse($link['crl']);

                if ($el->getModuleLabel() === 'CLDOC') {
                    $ids = findUidByPath($el->getResourceId(), $resmData);
                    $content .= '</br>[[uid=' . $ids[1] . ']]';
                }
            }

            file_put_contents(
                __DIR__ . "{$ds}{$course}{$ds}home{$ds}editorial{$ds}{$uniqid}",
                utf8_encode($content)
            );

            $editorialTab['widgets'][$toolsIntroduction->getRank()] = array(
                'widget' => array(
                    'name' => $toolsIntroduction->getTitle(),
                    'type' => 'simple_text',
                    'data' => array(
                        array(
                            'locale' => 'fr',
                            'content' => "home{$ds}editorial{$ds}{$uniqid}"
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
                __DIR__ . "{$ds}{$course}{$ds}home{$ds}course_description{$ds}{$uniqid}",
                utf8_encode($content)
            );
            
            $courseDescriptionTab['widgets'][] = array(
                'widget' => array(
                    'name' => $courseDescription['title'],
                    'type' => 'simple_text',
                    'data' => array(
                        array(
                            'locale' => 'fr',
                            'content' => "home{$ds}course_description{$ds}{$uniqid}"
                        )
                    )
                )
            );
        }

        $data[] = array('tab' => $courseDescriptionTab);
        $data[] = array('tab' => $editorialTab);

        return $data;
    }
}


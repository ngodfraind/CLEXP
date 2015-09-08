<?php

//replaced urls have this form.
//.../nomplateforme/courses/codeCours/document/nomdoc.html
//.../nomplateforme/claroline/backends/download.php?url=LzZCJTJDNS5qcGc%3D&cidReset=true&cidReq=codeCours

//url are replaced by a link looking like that: [[uid=10]]
require_once get_path('incRepositorySys') . '/lib/file/downloader.lib.php';

class RichTextParser
{
    private $data;
    private $platformName;
    private $course;
    private $courseTmpDir;
    private $downloader;
    private $resManagerData;
    
    public function __construct($data, $course, $courseTmpDir)
    {
        $this->data = $data;
        foreach ($this->data['tools'] as $tool) {
            if ($tool['tool']['type'] = 'resource_manager') {
                $this->resManagerData = $tool['tool'];
            }
        }
        $this->course = $course;
        $this->courseTmpDir = $courseTmpDir;
        $this->downloader = new Claro_PlatformDocumentsDownloader();
    }
    
    public function parseAndReplace()
    {
        //in this version of the exporter, all textes are located in ".txt" files.
        //so we parse the tmp directory, find .txt files and replace everything we need out here.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->courseTmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $el) {
            if ($el->isFile() && pathinfo($el->getBaseName(), PATHINFO_EXTENSION) === 'txt') {
                $content = file_get_contents($el->getPathName());
                file_put_contents($el->getPathName(), $this->setPlaceholders($content));
            }
        } 
    }
    
    private function setPlaceholders($text)
    {
        $matches = array();
        //download url
        preg_match_all(
            '#/claroline/backends/download.php\?url=([^&]+)#', 
            $text, 
            $matches, 
            PREG_SET_ORDER
        );
        
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $matchReplaced = array();
                $requestUrl = urldecode(strip_tags($match[1]));

                if (is_download_url_encoded($requestUrl)) {
                    $requestUrl = download_url_decode($requestUrl);
                }
                
                $filepath = str_replace('//', '/', $requestUrl);
                $path = realpath(rtrim(str_replace( '\\', '/', get_path('rootSys') ), '/') 
                . '/courses/' . $this->course . '/document' . $requestUrl);
                
                $fileUid = $this->findFileInData($filepath);
                preg_match(
                    '#<img([^>]+)claroline/backends/download.php([^>]+)' . $match[1] . '[^>]+>#', 
                    $text, 
                    $matchReplaced
                );
                
                if (count($matchReplaced) > 0) {
                    $text = str_replace($matchReplaced[0], "[[uid={$fileUid}]]", $text);
                }
            }
        }
        
        //the other url
        $matches = array();
        $regexp = '#<img src="(.*/' . $this->course . '/document)([^"]+)[^>]+>#';
        preg_match_all(
            $regexp, 
            $text, 
            $matches, 
            PREG_SET_ORDER
        );
        
        if (count($matches) > 1) {
            foreach($matches as $match) {
                $matchReplaced = array();
                $fileUid = $this->findFileInData($match[2]);
                preg_match($regexp, $text, $matchReplaced);
                
                if (count($matchReplaced) > 0) {
                    $text = str_replace($matchReplaced[0], "[[uid={$fileUid}]]", $text);
                }
            }
        }
        
        return $text;
    }
    
    private function findFileInData($path) 
    {
        $lastFoundDir = $this->findRecursiveLastDirInData($path);
        $parentUid = $lastFoundDir['uid'];
        preg_match('#^/(.+/)*(.+)$#', $path, $matches);
        $name = array_pop($matches);
        $item = $this->findFileByNameAndParentUid($name, $parentUid);
        
        return $item['uid'];
    }
    
    private function findRecursiveLastDirInData($path, $parentUid = 0)
    {
        preg_match('#/([^/]+)/#', $path, $matches);
        $isLastDir = substr_count($path, '/') === 2;

        if (count($matches) === 0 && $parentUid === 0) {
            return $this->resManagerData['data']['root']; 
        }
        
        if ($isLastDir) {
            $name = $matches[1];
            return $this->findDirectoryByNameAndParentUid($name, $parentUid);
        }

        if (count($matches) === 2) {
            $name = $matches[1];
            $path = str_replace('/' . $name, '', $path);
            $dirData = $this->findDirectoryByNameAndParentUid($name, $parentUid);
            $parentUid = $dirData['uid'];
            $lastFoundDir = $this->findRecursiveLastDirInData($path, $parentUid);
        }
        
        return $lastFoundDir;
    }
    
    private function findDirectoryByNameAndParentUid($name, $parentUid)
    {
        foreach ($this->resManagerData['data']['directories'] as $directory) {
            if ($directory['directory']['parent'] === $parentUid &&
                $directory['directory']['name'] === $name) {
                    
                return $directory['directory'];
            }
        }
    }
    
    private function findFileByNameAndParentUid($name, $parentUid)
    {        
        foreach ($this->resManagerData['data']['items'] as $item) {
            if ($item['item']['parent'] === $parentUid &&
                $item['item']['name'] === $name) {
                    
                return $item['item'];
            }
        }
    }
}


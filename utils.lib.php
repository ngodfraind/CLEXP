<?php

//Utility functions

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

function zipDir($directory, $removeOldFiles = false)
{
    $zipArchive = new \ZipArchive();
    $archive = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '.zip';
    $zipArchive->open($archive, \ZipArchive::CREATE);
    addDirToZip($directory, $zipArchive);
    $zipArchive->close();
    
    if ($removeOldFiles) {
        recursiveRemoveDirectory($directory);
    }

    rmdir($directory);
    
    return $archive;
}

function addDirToZip($directory, \ZipArchive $zipArchive, $includeRoot = false)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $el) {
        if ($el->isFile()) {
            $path = $includeRoot ? 
                pathinfo($directory, PATHINFO_FILENAME) . '/' . relativePath($directory, $el->getPathName()):
                relativePath($directory, $el->getPathName());
            $zipArchive->addFile($el->getPathName(), $path); 
        }
    } 
}

function findUidByPath($path, $data)
{
    $elements = explode('/', $path);
    $last = array_pop($elements);
    $uids = array();
    $i = 0;

    //get the uid tree path
    foreach ($elements as $element) {
        $parentUid = ($i === 0) ? 0: $uids[$i - 1]['uid'];
        $uids[] = findDir($parentUid, $element, $data);
        $i++;
    }

    if (count($uids) > 0) {
        $arr = array_pop($uids);
        $lastParent = $arr['uid'];
    } else {
        $lastParent = 0;
    }

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

function copyDirectory($source, $dest)
{
	if (!is_dir($source)) return;
    foreach (
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $source, 
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        ) as $item
    ) {
        if ($item->isDir()) {
            mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        } else {
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        }
    }
}

/**
 * http://stackoverflow.com/questions/11267086/php-unlink-all-files-within-a-directory-and-then-deleting-that-directory 
 */
function recursiveRemoveDirectory($directory)
{
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file)) { 
            recursiveRemoveDirectory($file);
        } else {
            unlink($file);
        }
    }
    rmdir($directory);
}

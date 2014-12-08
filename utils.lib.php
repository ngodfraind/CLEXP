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
        foreach ($iterator as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

        rmdir($directory);
    }
    
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

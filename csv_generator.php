<?php 
    
require_once __DIR__ . '/../../claroline/inc/claro_init_global.inc.php';


require_once __DIR__ . '/../../claroline/inc/lib/user.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/course_utils.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/core/linker.lib.php';
require_once __DIR__ . '/../../claroline/tool_intro/lib/toolintroductioniterator.class.php';
require_once __DIR__ . '/exporter.lib.php';
require_once __DIR__ . '/vendor/autoload.php';

$users = claro_get_all_users();
$d = ';';
$filename = __DIR__ . '/csv.txt';
$content = '';

foreach($users as $user)
{
    $row = $user['prenom'] . $d . 
        $user['nom'] . $d . 
        $user['username'] . $d . 
        $user['username'] . $d . 
        $user['username'] . '@claro.net' .
        PHP_EOL;
        
    $content .= $row;
}

file_put_contents($filename, utf8_encode($content));

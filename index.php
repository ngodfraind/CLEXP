<?php 
    
require_once __DIR__ . '/../../claroline/inc/claro_init_global.inc.php';


require_once __DIR__ . '/../../claroline/inc/lib/user.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/course_utils.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/core/linker.lib.php';
require_once __DIR__ . '/../../claroline/tool_intro/lib/toolintroductioniterator.class.php';
require_once __DIR__ . '/exporter.lib.php';
require_once __DIR__ . '/vendor/autoload.php';

$exportUser = true;
$exportGroups = true;
$course = 'GE01';

/*
$out = '';
$nameTools = get_lang('Export');
$out .= claro_html_tool_title($nameTools);
$out .= '<form action="#"><input type="text"></input><input type="submit"></input></form>';

$claroline->display->body->appendContent($out);

echo $claroline->display->render();
*/

export($exportUser, $exportGroups, $course);

<?php 
    
require_once __DIR__ . '/../../claroline/inc/claro_init_global.inc.php';

//requests
require_once __DIR__ . '/requests.lib.php';

require_once __DIR__ . '/../../claroline/inc/lib/user.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/course_utils.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/core/linker.lib.php';
require_once __DIR__ . '/../../claroline/tool_intro/lib/toolintroductioniterator.class.php';
require_once __DIR__ . '/../../claroline/course_description/lib/courseDescription.lib.php';
require_once __DIR__ . '/../../claroline/wiki/lib/class.wiki2xhtmlexport.php';
require_once __DIR__ . '/../../claroline/wiki/lib/class.wiki.php';
require_once __DIR__ . '/exporter.class.php';
require_once __DIR__ . '/vendor/autoload.php';

$exportUser = true;
$exportGroups = true;
$course = 'TEST';


$out = '';
$nameTools = get_lang('Export');
$out .= claro_html_tool_title($nameTools);
$out .= '<form action="#"><input type="text"></input><input type="submit"></input></form>';
$claroline->display->body->appendContent($out);
$code = $claroline->display->header->course['sysCode'];

//echo $claroline->display->render();

$exporter = new Exporter($code);
$exporter->export();

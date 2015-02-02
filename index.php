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
$nameTools = get_lang('Export');
$code = $claroline->display->header->course['sysCode'];

//echo $claroline->display->render();

$exporter = new Exporter($code);
$file = $exporter->export();

//http://stackoverflow.com/questions/5595485/php-file-download
if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header("Content-Type: 'application/force-download'");
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Content-Transfer-Encoding: octet-stream'); 
        header('Connection: close'); 
        ob_clean();
        flush();
        readfile($file);
        unlink($file);
}

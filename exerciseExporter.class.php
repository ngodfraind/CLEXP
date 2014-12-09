<?php 

require_once __DIR__ . '/../../claroline/exercise/lib/exercise.class.php';
require_once __DIR__ . '/../../claroline/exercise/lib/exercise.lib.php';
require_once __DIR__ . '/../../claroline/exercise/lib/question.class.php';
require_once __DIR__ . '/../../claroline/exercise/export/qti2/qti2_export.php';
require_once __DIR__ . '/utils.lib.php';
require_once __DIR__ . '/../../claroline/inc/lib/thirdparty/pclzip/pclzip.lib.php';

class ExerciseExporter
{
    private $course;
    
    public function __construct($course) 
    {
        $this->course = $course;
    }
    
    public function exportQti(array $data)
    {
        $exercise = new Exercise();
        $exercise->load($data['id']);
        $questionList = $exercise->getQuestionList();
        $filePathList = array();
        
        //prepare xml file of each question
        foreach ($questionList as $question)
        {
            $quId = $question['id'];
            $questionObj = new Qti2Question();
            $questionObj->load($quId);
            $xml = $questionObj->export();
            
            if (substr($questionObj->questionDirSys, -1) == '/') {
                $questionObj->questionDirSys = substr($questionObj->questionDirSys, 0, -1);
            }

            //save question xml file
            if (!file_exists($questionObj->questionDirSys)) {
                claro_mkdir($questionObj->questionDirSys, CLARO_FILE_PERMISSIONS);
            }

            if ($fp = @fopen($questionObj->questionDirSys . "/question_" . $quRank . ".xml", 'w')) {
                fwrite ($fp, $xml);
                fclose ($fp);
            }

            $filePathList[] = $questionObj->questionDirSys;
        }
        
        return $filePathList;
    }
}

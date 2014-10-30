<?php 

/**
 * Exports a single question and the solutions of their participants.
 * 
 * @author Stefan Born <stefan.born@phzh.ch>
 * @version $Id$
 *
 */
class ilQuestionExport
{
	/**
	 * Question ID
	 * @var integer
	 */
	private $question_id = 0;
	
	/**
	 * Test
	 * @var ilObjTest
	 */
	private $test = null;
	
	/**
	 * Language
	 * @var ilLanguage
	 */
	private $lng = null;
	
	/**
	 * Constructor
	 * 
	 * @param int $question_id
	 * @param int $test
	 */
	function ilQuestionExport($question_id, $test)
	{
		global $lng;
		
		$this->lng =& $lng;
		$this->question_id = $question_id;
		$this->test =& $test;
	}	
	
	/**
	 * Evaluates whether the specified question is supported for exporting.
	 * @param assQuestion $question
	 */
	public static function isQuestionTypeSupported($question)
	{
		if ($question == null)
			return false;
		
		return method_exists($question, "getResultsForExport");
	}
	
	/**
	 * Exports the user results for a single question as Excel.
	 */
	public function exportUserResultsAsExcel()
	{
		// classes we need
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		include_once "./Services/Excel/classes/class.ilExcelWriterAdapter.php";
		include_once "./Services/Excel/classes/class.ilExcelUtils.php";
		
		$row = 0;
		$col = 0;
	
		$testId = $this->test->getTestId();
		$question =& $this->instanciateQuestionForTest();
		if (!isset($question))
		{
			echo "Question (id=" . $this->question_id . ") not found for test (id=" . $testId . ")";
			exit;
		}
		
		
		$title = $question->getTitle();
		
		// initialize excel file
		$excelfile = ilUtil::ilTempnam();
		$adapter = new ilExcelWriterAdapter($excelfile, false);
		$workbook = $adapter->getWorkbook();
		$workbook->setVersion(8); // Use Excel97/2000 Format
	
		// create a worksheet
		$format_h1 =& $workbook->addFormat();
		$format_h1->setBold();
		$format_h1->setSize(14);
		$format_h2 =& $workbook->addFormat();
		$format_h2->setBold();
		$format_h2->setSize(12);
		$format_bold =& $workbook->addFormat();
		$format_bold->setBold();
		$format_title =& $workbook->addFormat();
		$format_title->setBold();
		$format_title->setColor('black');
		$format_title->setPattern(1);
		$format_title->setFgColor('silver');
		$worksheet =& $workbook->addWorksheet(ilExcelUtils::_convert_text($this->lng->txt("tst_results")));
		
		// header information
		$worksheet->writeString($row++, 0, ilExcelUtils::_convert_text($this->test->getTitle()), $format_h1);
		$row++;
		
		$worksheet->writeString($row++, 0, ilExcelUtils::_convert_text($title), $format_h2);
		
		$worksheet->writeString($row, 0, $this->lng->txt("question_type"));
		$worksheet->writeString($row++, 1, ilExcelUtils::_convert_text(assQuestion::_getQuestionTypeName($question->getQuestionType())));
		
		$worksheet->writeString($row, 0, $this->lng->txt("tst_maximum_points"));
		$worksheet->writeString($row++, 1, $question->getMaximumPoints());	
		$row++;

		// write correct answers and their points
		$solutionValues = $this->getSolution($question);
		if (is_array($solutionValues) && count($solutionValues) > 0)
		{
			$worksheet->writeString($row++, 0, ilExcelUtils::_convert_text($this->lng->txt("tst_heading_scoring")), $format_h2);
			
			foreach ($solutionValues as $key => $solution)
			{
				$cellFormat = $key === "title" ? $format_title : null;
				
				if (is_array($solution))
				{
					$col = 0;
					foreach ($solution as $solutionValue)
						$worksheet->write($row, $col++, ilExcelUtils::_convert_text($solutionValue), $cellFormat);
					$row++;
				}
				else
				{
					$worksheet->write($row++, 0, ilExcelUtils::_convert_text($solution), $cellFormat);
				}
			}
			
			$row++;
		}
		
		// title
		$worksheet->writeString($row++, 0, ilExcelUtils::_convert_text($this->lng->txt("tst_results")), $format_h2);
		
		// name
		$col = 0;
		if ($this->test->getAnonymity())
		{
			$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($this->lng->txt("counter")), $format_title);
		}
		else
		{
			$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($this->lng->txt("name")), $format_title);
			$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($this->lng->txt("login")), $format_title);
		}	
		
		$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($this->lng->txt("tst_reached_points")), $format_title);
		
		// headers
		$headers = $this->getColumnHeaders($question);
		foreach ($headers as $header)
			$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($header), $format_title);
		
		// write data
		$headerWritten = false;
		$counter = 1;
		$participants = $this->test->getParticipantsForTestAndQuestion($testId, $this->question_id);
		
		// sort the participants by name
		$userInfos = array();
		foreach ($participants as $active_id => $passes)
		{
			if (!$this->test->getAnonymity())
			{
				$userId = ilObjTest::_getUserIdFromActiveId($active_id);
				$user = new ilObjUser($userId);
				$userName = $this->test->buildName($userId, $user->getFirstname(), $user->getLastname(), $user->getUTitle());
				
				$userInfos[$active_id] = array("name" => $userName, "login" => $user->getLogin(), "passes" => $passes);
			}
			else
			{
				$userInfos[$active_id] = array("name" => $counter, "login" => null, "passes" => $passes);
				$counter++;
			}
		}
		uasort($userInfos, array($this, "sortByUsername"));
		
		// get the results for each participant
		foreach ($userInfos as $active_id => $userInfo)
		{
			$passes = $userInfo["passes"];
			$resultpass = $this->test->_getResultPass($active_id);
			for ($i = 0; $i < count($passes); $i++)
			{
				if (($resultpass != null) && ($resultpass == $passes[$i]["pass"]))
				{
					$col = 0;
					$row++;
			
					// instatiate question of users pass
					$question = ilObjTest::_instanciateQuestion($passes[$i]["qid"]);
					
					$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($userInfo["name"]));
					if (!$this->test->getAnonymity())
						$worksheet->writeString($row, $col++, ilExcelUtils::_convert_text($userInfo["login"]));
					
					$worksheet->write($row, $col++, $question->getReachedPoints($active_id, $resultpass));
					
					// insert test values
					$values = $this->getResultValues($question, $active_id, $resultpass);
					foreach ($values as $value)
						$worksheet->write($row, $col++, ilExcelUtils::_convert_text($value));
	
					$counter++;
				}
			}
		}
		$workbook->close();
		
		// output excel file
		$testname = ilUtil::getASCIIFilename(preg_replace("/\s/", "_", $title)) . ".xls";
		ilUtil::deliverFile($excelfile, $testname, "application/vnd.ms-excel", false, true);
		exit;
	}
	
	/**
	 * Compares two user information arrays by the user name.
	 * @param array $a First user information.
	 * @param array $b Second user information.
	 * @return 
	 */
	private function sortByUsername($a, $b)
	{
		$name1 = $a["name"];
		$name2 = $b["name"];
		
		$name1 = preg_replace("/&(.)(acute|cedil|circ|ring|tilde|uml);/", "$1", htmlentities($name1, ENT_COMPAT | ENT_HTML401, "UTF-8"));
		$name2 = preg_replace("/&(.)(acute|cedil|circ|ring|tilde|uml);/", "$1", htmlentities($name2, ENT_COMPAT | ENT_HTML401, "UTF-8"));
		
		return strcasecmp($name1, $name2);
	}
	
	/**
	 * Instanciates the question for the assigned test.
	 * 
	 * @return object
	 */
	private function instanciateQuestionForTest()
	{
		$participants = $this->test->getParticipantsForTestAndQuestion($this->test->getTestId(), $this->question_id);
		$firstParticipant = array_shift(array_values($participants));
		$questionId = $firstParticipant[0]["qid"];
	
		return ilObjTest::_instanciateQuestion($questionId);
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assQuestion $question
	 * @return multitype:string
	 */
	private function getSolution($question)
	{
		// method defined?
		if (method_exists($question, "getCorrectAnswersForExport"))	
			return $question->getCorrectAnswersForExport($question);
		
		return array();
	}
	
	/**
	 * Gets the column headers for the specified question.
	 * 
	 * @param assQuestion $question
	 * @return multitype:string
	 */
	private function getColumnHeaders($question)
	{
		if (method_exists($question, "getResultHeadersForExport"))
			return $question->getResultHeadersForExport();
		
		return array();
	}
	
	/**
	 * Gets the result values for the specified question, user and pass.
	 *
	 * @param assQuestion $question
	 * @param int $active_id
	 * @param int $pass
	 * @return multitype:string
	 */	
	private function getResultValues($question, $active_id, $pass)
	{
		// method defined?
		if (method_exists($question, "getResultsForExport"))	
			return $question->getResultsForExport($active_id, $pass);
		
		return array();
	}
}

?>
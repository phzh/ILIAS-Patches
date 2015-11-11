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
		
		//return method_exists($question, "getResultsForExport");
        
		$valueFunction = self::getValuesFunctionName($question);
		return method_exists("ilQuestionExport", $valueFunction);
		
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
		//if (method_exists($question, "getCorrectAnswersForExport"))	
		//	return $question->getCorrectAnswersForExport($question);
		
		$solutionFunction = self::getSolutionFunctionName($question);
		
		// method defined?
		if (method_exists($this, $solutionFunction))	
			return $this->$solutionFunction($question);
		
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
		//if (method_exists($question, "getResultHeadersForExport"))
		//	return $question->getResultHeadersForExport();
		
		//return array();

		$headers = array();
		
		switch ($question->getQuestionType())
		{
			case "assTextQuestion":
                $headers[] = $this->lng->txt("result");
                $headers[] = $this->lng->txt("characters");
				break;
                
			case "assNumeric":
				$headers[] = $this->lng->txt("result");
				break;
				
			case "assClozeTest":
				for ($i = 1; $i <= $question->getGapCount(); $i++)
				{
					$headers[] = $this->lng->txt("gap") . " " . $i;
				}
				break;
			
			case "assErrorText":
				$headers[] = $this->lng->txt("selection");
				break;
				
			case "assFileUpload":
				$headers[] = $this->lng->txt("files");
				break;		
				
			case "assImagemapQuestion":
				foreach ($question->getAnswers() as $id => $answer)
				{
					$answerText = $answer->getAnswertext();
					if (strlen($answerText))
						$headers[] = $answerText;
					else
						$headers[] = $answer->getArea() . ": " . $answer->getCoords();
				}
				break;
				
			case "assMultipleChoice":
			case "assSingleChoice":
				foreach ($question->getAnswers() as $id => $answer)
				{
					$answerText = $answer->getAnswertext();
					if (strlen($answerText))
						$headers[] = $this->removeHtmlElements($answerText);
					else
						$headers[] = $this->lng->txt("answer") . " " . ($answer->getOrder() + 1);
				}
				break;

			case "assMatchingQuestion":
				foreach ($question->getDefinitions() as $idx => $definition)
				{
					$text = $definition->text;
					if (!strlen($text))
						$headers[] = $text;
					else
						$headers[] = $this->lng->txt("definition") . " " . ($idx + 1);
				}
				break;
				
			case "assOrderingQuestion":
				for ($i = 1; $i <= $question->getAnswerCount(); $i++)
					$headers[] = $this->lng->txt("value") . " " . $i;
				break;
			
			case "assOrderingHorizontal":
				$headers[] = $this->lng->txt("result");
				break;
				
			case "assTextSubset":
				for ($i = 1; $i <= $question->getCorrectAnswers(); $i++)
					$headers[] = $this->lng->txt("value") . " " . $i;
				break;
				
			case "assFlashQuestion":
			case "assJavaApplet":
				/* NOT SUPPORTED YET */
				break;
		}
		
		return $headers;
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
		// method defined on question?
		//if (method_exists($question, "getResultsForExport"))	
		//	return $question->getResultsForExport($active_id, $pass);
		
		$values = $question->getSolutionValues($active_id, $pass);		
		$valuesFunction = self::getValuesFunctionName($question);
		
		// method defined?
		if (method_exists($this, $valuesFunction))	
			return $this->$valuesFunction($question, $values);

		return array();
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assTextQuestion $question
	 * @return multitype:string
	 */
	private function getTextQuestionSolution($question)
	{
		$result = array();
		
		$result["answers"] = $this->lng->txt("keywords");
		$answers = $question->getAnswers();
		
		if (is_array($answers) && count($answers) > 0)
		{
			$resultText = "";
			foreach ($answers as $answer)
				$resultText .= $answer->answertext . ", ";
			$result[] = substr_replace($resultText, "", -2);
		}
		else
			$result[] = "-";
		
		return $result;
	}
		
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assTextQuestion $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getTextQuestionValues($question, $values)
	{
		if (strlen($values[0]["value1"]))
		{
			$text = $this->removeHtmlElements($values[0]["value1"]);
			return array($text, strlen($text));
		}
        
		return array();
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assTextSubset $question
	 * @return multitype:string
	 */
	private function getTextSubsetSolution($question)
	{
		$result = array();			
		$result["title"] = array($this->lng->txt("answer_text"), $this->lng->txt("points"));		
		foreach ($question->getAnswers() as $answer)
		{
			$result[] = array(
				$answer->getAnswertext(),
				$answer->getPoints()	
			);
		}	
		return $result;
	}
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assTextSubset $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */
	/*private function getTextSubsetValues($question, $values)
	{
		$result = array();
		
		foreach ($values as $value)
			$result[] = $value["value1"];
		
		return $result;
	}	*/
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assOrderingQuestion $question
	 * @return multitype:string
	 */
	private function getOrderingQuestionSolution($question)
	{
		$result = array();
		$result["title"] = $this->lng->txt("solution_order");
		$index = 1;
		foreach ($question->getAnswers() as $answer)
		{
			if ($question->getOrderingType() == OQ_TERMS)
				$result[] = $answer->getAnswertext();
			else
				$result[] = $this->lng->txt("image") . " " . $index;
			$index++;
		}	
		return $result;
	}
		
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assOrderingQuestion $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getOrderingQuestionValues($question, $values)
	{
		$result = array();
		
		// get ordering of answers
		$indexes = array();
		foreach ($values as $value)
			$indexes[$value["value1"]] = $value["value2"];
		asort($indexes);
		$indexes = array_keys($indexes);
		
		// get answers
		$answers = $question->getAnswers();
		foreach ($indexes as $index)
		{
			foreach ($values as $value)
			{
				if ($value["value1"] == $index)
				{
					if ($question->getOrderingType() == OQ_TERMS)
						$result[] = $answers[$index]->getAnswertext();
					else
						$result[] = $this->lng->txt("image") . " " . ($index + 1);
				}
			}					
		}
		
		return $result;
	}	
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assOrderingHorizontal $question
	 * @return multitype:string
	 */
	private function getOrderingHorizontalSolution($question)
	{
		$result = array();
		$result["title"] = $this->lng->txt("solution_order");
		$result[] = $question->getOrderText();
		return $result;
	}
		
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assOrderingHorizontal $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getOrderingHorizontalValues($question, $values)
	{
		$result = array();
		$result[] = str_replace("{::}", " ", $values[0]["value1"]);
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assMatchingQuestion $question
	 * @return multitype:string
	 */
	private function getMatchingQuestionSolution($question)
	{
		$result = array();	
		$result["title"] = array($this->lng->txt("definition"), $this->lng->txt("term"), $this->lng->txt("points"));		
		foreach ($question->getMatchingPairs() as $pair)
		{
			$termText = $pair->term->text;
			if (strlen($termText) === 0)
			{
				$i = 1;
				foreach ($question->getTerms() as $term)
				{
					if ($pair->term->identifier == $term->identifier)
					{
						$termText = $this->lng->txt("term") . " " . $i;
						break;
					}
					$i++;
				}
			}			
			$result[] = array($pair->definition->text, $termText, $pair->points);
		}
		return $result;
	}
		
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assMatchingQuestion $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getMatchingQuestionValues($question, $values)
	{
		$result = array();
		
		// order by definition to match headers
		foreach ($question->getDefinitions() as $definition)
		{
			foreach ($values as $value)
			{
				// answer for current definition?
				if ($value["value2"] != $definition->identifier)
					continue;
				
				$i = 0;
				$termFound = false;
				foreach ($question->getTerms() as $term)
				{
					if ($term->identifier == $value["value1"])
					{
						if (strlen($term->text))
							$result[] = $term->text;
						else
							$result[] = $this->lng->txt("term") . " " . ($i + 1);
						
						$termFound = true;
					}		
					
					$i++;
				}
				
				if (!$termFound)
					$result[] = "";
			}	
		}
	
		return $result;
	}	
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assNumeric $question
	 * @return multitype:string
	 */
	private function getNumericSolution($question)
	{
		$result = array();
		$result["title"] = array($this->lng->txt("range_lower_limit"), $this->lng->txt("range_upper_limit"));
		$result[] = array($question->getLowerLimit(), $question->getUpperLimit());
		return $result;
	}
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assNumeric $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getNumericValues($question, $values)
	{
		if (strlen($values[0]["value1"]))
			return array($values[0]["value1"]);
	
		return array();
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assSingleChoice $question
	 * @return multitype:string
	 */
	private function getSingleChoiceSolution($question)
	{
		$result = array();
		$result["title"] = array($this->lng->txt("answer"), $this->lng->txt("points"));
		
		foreach ($question->getAnswers() as $answer)
		{
			$answerText = $answer->getAnswertext();
			if (strlen($answerText))
				$result[] = array($this->removeHtmlElements($answerText), $answer->getPoints());
			else
				$result[] = array($this->lng->txt("answer") . " " . ($answer->getOrder() + 1), $answer->getPoints());
		}
		
		return $result;
	}
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assSingleChoice $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getSingleChoiceValues($question, $values)
	{
		$result = array();
		foreach ($question->getAnswers() as $id => $answer)
			$result[] = $id == $values[0]["value1"] ? 1 : 0;		
		
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assMultipleChoice $question
	 * @return multitype:string
	 */
	private function getMultipleChoiceSolution($question)
	{
		$result = array();
		$result["title"] = array($this->lng->txt("answer"), $this->lng->txt("points"));
		
		foreach ($question->getAnswers() as $answer)
		{
			$answerText = $answer->getAnswertext();
			if (strlen($answerText))
				$result[] = array($this->removeHtmlElements($answerText), $answer->getPoints());
			else
				$result[] = array($this->lng->txt("answer") . " " . ($answer->getOrder() + 1), $answer->getPoints());
		}
		
		return $result;
	}
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assMultipleChoice $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getMultipleChoiceValues($question, $values)
	{
		$result = array();
		foreach ($question->getAnswers() as $id => $answer)
		{
			$checked = false;
			foreach ($values as $value)
			{
				if ($id == $value["value1"])
				{
					$checked = true;
					break;
				}
			}
			$result[] = $checked ? 1 : 0;
		}
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assImagemapQuestion $question
	 * @return multitype:string
	 */
	private function getImagemapQuestionSolution($question)
	{
		$result = array();
		$result["title"] = array($this->lng->txt("answer"), $this->lng->txt("points"));
		
		foreach ($question->getAnswers() as $answer)
		{
			$answerText = $answer->getAnswertext();
			if (strlen($answerText))
				$result[] = array($answerText, $answer->getPoints());
			else
				$result[] = array($answer->getArea() . ": " . $answer->getCoords(), $answer->getPoints());
		}
		
		return $result;
	}	
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assImagemapQuestion $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getImagemapQuestionValues($question, $values)
	{
		$result = array();		
		foreach ($question->getAnswers() as $id => $answer)
			$result[] = $id == $values[0]["value1"] ? 1 : 0;		
		
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assFileUpload $question
	 * @return multitype:string
	 */
	private function getFileUploadSolution($question)
	{
		/* no solution available */
		return array();
	}	
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assFileUpload $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getFileUploadValues($question, $values)
	{
		$result = array();
		foreach ($values as $value)
		{
			if (strlen($value["value1"]))
			{
				$result[] = $value["value1"];
				$result[] = $value["value2"];
			}
		}
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assErrorText $question
	 * @return multitype:string
	 */
	private function getErrorTextSolution($question)
	{
		$result = array();
		
		$result[] = array($this->lng->txt("assErrorText") . ":", $question->getErrorText());
		$result[] = "";
		
		$result["title"] = array($this->lng->txt("text_wrong"), $this->lng->txt("text_correct"), $this->lng->txt("points"));
		foreach ($question->getErrorData() as $error)
			$result[] = array($error->text_wrong, $error->text_correct, $error->points);
		
		return $result;
	}	
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assErrorText $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getErrorTextValues($question, $values)
	{
		$result = array();
		$selections = array();
		if (is_array($values))
		{
			foreach ($values as $value)
				array_push($selections, $value['value1']);
		}
		
		$counter = 0;
		$textarray = preg_split("/[\n\r]+/", $question->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", $text);
			foreach ($items as $idx => $item)
			{
				if (in_array($counter, $selections))
					$result[] = $item;					
					
				$counter++;
			}
		}
		
		return $result;
	}
	
	/**
	 * Gets the solution for the specified question.
	 * 
	 * @param assClozeTest $question
	 * @return multitype:string
	 */
	private function getClozeTestSolution($question)
	{
		$result = array();
		$result["title"] = array(
			$this->lng->txt("gap"), 
			$this->lng->txt("type"),
			$this->lng->txt("values"), 
			$this->lng->txt("points")
		);
		
		foreach ($question->getGaps() as $gapIndex => $gap)
		{
			$gapName = $this->lng->txt("gap") . " " . ($gapIndex + 1);
			
			
			switch ($gap->getType())
			{
				case CLOZE_SELECT:
				case CLOZE_TEXT:
					foreach ($gap->getItemsRaw() as $itemIndex => $item)
					{
						$values = array("", "");
						if ($itemIndex == 0)
						{
							$values[0] = $gapName;
							$values[1] = $this->lng->txt($gap->getType() == CLOZE_TEXT ? "text_gap" : "select_gap");
						}
						$values[2] = $item->getAnswertext();
						$values[3] = $item->getPoints();
						
						$result[] = $values;
					}
					break;
					
				case CLOZE_NUMERIC:
					$items = $gap->getItemsRaw();
					$itemValue = $items[0]->getAnswertext();
					$lowerBound = $items[0]->getLowerBound();
					$upperBound = $items[0]->getUpperBound();
					$rangeText = $lowerBound == $upperBound ? "" : " (" . $this->lng->txt("range") . ": " . $lowerBound . " - " . $upperBound . ")";
					$result[] = array(
						$gapName,
						$this->lng->txt("numeric_gap"),
						$itemValue . $rangeText,
						$items[0]->getPoints()
					);
					break;
			}
		}
		
		return $result;
	}	
	
	/**
	 * Gets the result values for the specified question and result values.
	 *
	 * @param assClozeTest $question
	 * @param multitype:object $values
	 * @return multitype:string
	 */	
	private function getClozeTestValues($question, $values)
	{
		$result = array();		
		foreach ($question->getGaps() as $gap_index => $gap)
		{
			foreach ($values as $value)
			{
				if ($gap_index == $value["value1"])
				{
					switch ($gap->getType())
					{
						case CLOZE_SELECT:
							$result[] = $gap->getItem($value["value2"])->getAnswertext();
							break;
						case CLOZE_NUMERIC:
						case CLOZE_TEXT:
							$result[] = $value["value2"];
							break;
					}
				}
			}
		}		
		return $result;
	}
	
	/**
	 * Removes the HTML elements from the specified text.
	 * 
	 * @param string $text
	 * @return string
	 */
	private function removeHtmlElements($text)
	{
		$text = preg_replace("/<br.*?>/", "\n", $text);
		return preg_replace("/<.*?>/", "", $text);
	}
	
	/**
	 * Gets the function name to retrieve question values.
	 * 
	 * @param assQuestion $question
	 * @return string
	 */
	private static function getValuesFunctionName($question)
	{
		$type = self::getPlainTypeName($question);
		return "get{$type}Values";
	}
	
	/**
	 * Gets the function name to retrieve question answers.
	 * 
	 * @param assQuestion $question
	 * @return string
	 */
	private static function getSolutionFunctionName($question)
	{
		$type = self::getPlainTypeName($question);
		return "get{$type}Solution";
	}	
	
	/**
	 * Gets the plain type name of the specified question.
	 * That is without the "ass" prefix.
	 * 
	 * @param assQuestion $question
	 * @return string
	 */
	private static function getPlainTypeName($question)
	{
		$type = $question->getQuestionType();
		$pos = strpos($type, "ass");
		if ($pos === 0)
			$type = substr($type, 3);
		return $type;
	}
}

?>
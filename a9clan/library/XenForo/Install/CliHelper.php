<?php

class XenForo_Install_CliHelper
{
	protected $_screenWidth = 80;

	public function triggerFatalError($message)
	{
		echo PHP_EOL . $message . PHP_EOL;
		exit;
	}

	public function printStatus($message, $width = null)
	{
		if ($width === null)
		{
			$width = $this->_screenWidth;
		}

		echo str_pad($message, $width, ' ', STR_PAD_RIGHT) . "\r";
	}

	public function clearStatus($width = null, $message = '')
	{
		$this->printStatus($width);
		echo PHP_EOL;
		if ($message)
		{
			$this->printMessage($message);
		}
	}

	public function printMessage($message, $eols = 1)
	{
		echo $message . ($eols ? str_repeat(PHP_EOL, $eols) : '');
	}

	public function printWarning($message)
	{
		echo $message . PHP_EOL;
	}

	public function askQuestion($question)
	{
		echo $question . ' ';
		return trim(fgets(STDIN));
	}

	public function validateYesNoAnswer($answer, &$final)
	{
		$final = false;

		if ($answer == 'y' || $answer == 'Y')
		{
			$final = true;
			return true;
		}
		else if ($answer == 'n' || $answer == 'N')
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
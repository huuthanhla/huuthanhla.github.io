<?php

class XenForo_Diff3
{
	const UPDATE = 'u';
	const CONFLICT = 'c';
	const EQUAL  = 'e';

	const MINE = 'm';
	const YOURS = 'y';
	const BOTH = 'b';

	/**
	 * @var XenForo_Diff
	 */
	protected $_comparer;

	protected $_currentOrig;
	protected $_currentMine;
	protected $_currentYours;

	protected $_bigConflicts = true;

	public function __construct(XenForo_Diff $comparer = null)
	{
		if (!$comparer)
		{
			$comparer = new XenForo_Diff();
		}

		$this->_comparer = $comparer;
	}

	protected function _initBlock()
	{
		$this->_currentOrig = $this->_currentMine = $this->_currentYours = array();
	}

	protected function _appendOrig(array $blocks)
	{
		array_splice($this->_currentOrig, count($this->_currentOrig), 0, $blocks);
	}

	protected function _appendMine(array $blocks)
	{
		array_splice($this->_currentMine, count($this->_currentMine), 0, $blocks);
	}

	protected function _appendYours(array $blocks)
	{
		array_splice($this->_currentYours, count($this->_currentYours), 0, $blocks);
	}

	protected function _finishBlock(array &$output, $init = true)
	{
		if ($this->_currentMine || $this->_currentYours || $this->_currentOrig)
		{
			if ($this->_currentMine == $this->_currentYours)
			{
				if ($this->_currentMine == $this->_currentOrig)
				{
					$output[] = array(self::EQUAL, $this->_currentMine);
				}
				else
				{
					$output[] = array(self::UPDATE, $this->_currentMine, $this->_currentOrig, self::BOTH);
				}
			}
			else if ($this->_currentMine == $this->_currentOrig)
			{
				$output[] = array(self::UPDATE, $this->_currentYours, $this->_currentOrig, self::YOURS);
			}
			else if ($this->_currentYours == $this->_currentOrig)
			{
				$output[] = array(self::UPDATE, $this->_currentMine, $this->_currentOrig, self::MINE);
			}
			else
			{
				// potential conflict - try to resolve prefixes
				$cM = $this->_currentMine;
				$cY = $this->_currentYours;
				$cO = $this->_currentOrig;

				if ($init)
				{
					$childOutput = array();
				}
				else
				{
					$childOutput =& $output;
				}

				if ($i = $this->_getMatchLength($this->_currentMine, $this->_currentYours))
				{
					$update = array_splice($this->_currentMine, 0, $i);
					array_splice($this->_currentYours, 0, $i);
					$childOutput[] = array(self::UPDATE, $update, array(), self::BOTH);
					$this->_finishBlock($childOutput, false);
				}
				else if ($i = $this->_getMatchLength($this->_currentMine, $this->_currentOrig))
				{
					$update = array_splice($this->_currentMine, 0, $i);
					array_splice($this->_currentOrig, 0, $i);
					$childOutput[] = array(self::UPDATE, array(), $update, self::YOURS);
					$this->_finishBlock($childOutput, false);
				}
				else if ($i = $this->_getMatchLength($this->_currentYours, $this->_currentOrig))
				{
					$update = array_splice($this->_currentYours, 0, $i);
					array_splice($this->_currentOrig, 0, $i);
					$childOutput[] = array(self::UPDATE, array(), $update, self::MINE);
					$this->_finishBlock($childOutput, false);
				}
				else if ($i = $this->_getEndMatchLength($this->_currentMine, $this->_currentYours))
				{
					$update = array_splice($this->_currentMine, -$i);
					array_splice($this->_currentYours, -$i);
					$this->_finishBlock($childOutput, false);
					$childOutput[] = array(self::UPDATE, $update, array(), self::BOTH);
				}
				else if ($i = $this->_getEndMatchLength($this->_currentMine, $this->_currentOrig))
				{
					$update = array_splice($this->_currentMine, -$i);
					array_splice($this->_currentOrig, -$i);
					$this->_finishBlock($childOutput, false);
					$childOutput[] = array(self::UPDATE, array(), $update, self::YOURS);
				}
				else if ($i = $this->_getEndMatchLength($this->_currentYours, $this->_currentOrig))
				{
					$update = array_splice($this->_currentYours, -$i);
					array_splice($this->_currentOrig, -$i);
					$this->_finishBlock($childOutput, false);
					$childOutput[] = array(self::UPDATE, array(), $update, self::MINE);
				}
				else if (!$init)
				{
					$childOutput[] = array(self::CONFLICT, $this->_currentMine, $this->_currentOrig, $this->_currentYours);
				}

				if ($init)
				{
					if ($childOutput)
					{
						$hasConflict = false;
						foreach ($childOutput AS $child)
						{
							if ($child[0] == self::CONFLICT)
							{
								$hasConflict = true;
								break;
							}
						}
						if ($hasConflict && $this->_bigConflicts)
						{
							// still have a conflict, just mark the whole thing as it originally was
							$output[] = array(self::CONFLICT, $cM, $cO, $cY);
						}
						else
						{
							// no longer have a conflict or we want the small conflicts
							array_splice($output, count($output), 0, $childOutput);
						}
					}
					else
					{
						// couldn't find any matching bits
						$output[] = array(self::CONFLICT, $this->_currentMine, $this->_currentOrig, $this->_currentYours);
					}
				}
			}
		}

		if ($init)
		{
			$this->_initBlock();
		}
	}

	protected function _getMatchLength(array $blocks1, array $blocks2)
	{
		$i = 0;
		while (isset($blocks1[$i]) && isset($blocks2[$i]) && $blocks1[$i] === $blocks2[$i])
		{
			$i++;
		}

		return $i;
	}

	protected function _getEndMatchLength(array $blocks1, array $blocks2)
	{
		$match = 0;
		$end1 = count($blocks1) - 1;
		$end2 = count($blocks2) - 1;
		while (isset($blocks1[$end1]) && isset($blocks2[$end2]) && $blocks1[$end1] === $blocks2[$end2])
		{
			$match++;
			$end1--;
			$end2--;
		}

		return $match;
	}

	public function findDifferences($mine, $original, $yours, $type = XenForo_Diff::DIFF_TYPE_LINE)
	{
		$mineDiff = $this->_comparer->findDifferences($original, $mine, $type);
		$yourDiff = $this->_comparer->findDifferences($original, $yours, $type);

		$output = array();
		$this->_initBlock();

		$m = reset($mineDiff);
		$y = reset($yourDiff);
		while ($m || $y)
		{
			if ($m && $y)
			{
				$mType = $m[0];
				$mBlocks =& $m[1];

				$yType = $y[0];
				$yBlocks =& $y[1];

				if ($mType == XenForo_Diff::EQUAL && $yType == XenForo_Diff::EQUAL)
				{
					$this->_finishBlock($output);

					$i = $this->_getMatchLength($mBlocks, $yBlocks);
					if (!$i)
					{
						throw new Exception("Both equal but no leading match?");
					}

					$matches = array_splice($mBlocks, 0, $i);
					$output[] = array(self::EQUAL, $matches);
					array_splice($yBlocks, 0, $i);

					if (!$mBlocks)
					{
						$m = next($mineDiff);
					}
					if (!$yBlocks)
					{
						$y = next($yourDiff);
					}
				}
				else if ($mType == XenForo_Diff::INSERT)
				{
					$this->_appendMine($mBlocks);
					$m = next($mineDiff);
				}
				else if ($yType == XenForo_Diff::INSERT)
				{
					$this->_appendYours($yBlocks);
					$y = next($yourDiff);
				}
				else if ($mType == XenForo_Diff::DELETE && $yType == XenForo_Diff::DELETE)
				{
					$this->_finishBlock($output);

					$i = $this->_getMatchLength($mBlocks, $yBlocks);
					if (!$i)
					{
						throw new Exception("Both deletes but no leading match?");
					}

					$this->_appendOrig(array_splice($mBlocks, 0, $i));
					array_splice($yBlocks, 0, $i);

					if (!$mBlocks)
					{
						$m = next($mineDiff);
					}
					if (!$yBlocks)
					{
						$y = next($yourDiff);
					}
				}
				else if ($mType == XenForo_Diff::DELETE && $yType == XenForo_Diff::EQUAL)
				{
					$min = min(count($mBlocks), count($yBlocks));

					array_splice($mBlocks, 0, $min); // removed
					$block = array_splice($yBlocks, 0, $min);
					$this->_appendOrig($block);
					$this->_appendYours($block);

					if (!$mBlocks)
					{
						$m = next($mineDiff);
					}
					if (!$yBlocks)
					{
						$y = next($yourDiff);
					}
				}
				else if ($yType == XenForo_Diff::DELETE && $mType == XenForo_Diff::EQUAL)
				{
					$min = min(count($mBlocks), count($yBlocks));

					array_splice($yBlocks, 0, $min); // removed
					$block = array_splice($mBlocks, 0, $min);
					$this->_appendOrig($block);
					$this->_appendMine($block);

					if (!$mBlocks)
					{
						$m = next($mineDiff);
					}
					if (!$yBlocks)
					{
						$y = next($yourDiff);
					}
				}
			}
			else if ($m)
			{
				if ($m[0] != XenForo_Diff::INSERT)
				{
					throw new Exception("Had m only but wasn't insert");
				}

				$this->_appendMine($m[1]);
				$m = next($mineDiff);
			}
			else if ($y)
			{
				if ($y[0] != XenForo_Diff::INSERT)
				{
					throw new Exception("Had y only but wasn't insert");
				}

				$this->_appendYours($y[1]);
				$y = next($yourDiff);
			}
		}

		$this->_finishBlock($output);

		return $this->_finalize($output);
	}

	protected function _finalize(array $output)
	{
		$newOutput = array();
		$i = -1;
		$lastType = null;
		foreach ($output AS $hunk)
		{
			if ($hunk[0] == self::CONFLICT && $lastType === self::CONFLICT)
			{
				// back to back conflicts: merge
				$newOutput[$i][1] = array_merge($newOutput[$i][1], $hunk[1]);
				$newOutput[$i][2] = array_merge($newOutput[$i][2], $hunk[2]);
				$newOutput[$i][3] = array_merge($newOutput[$i][3], $hunk[3]);
			}
			else
			{
				$i++;
				$newOutput[$i] = $hunk;
				$lastType = $hunk[0];
			}
		}

		return $newOutput;
	}

	public function mergeToFinal($mine, $original, $yours, $type = XenForo_Diff::DIFF_TYPE_LINE)
	{
		$diffs = $this->findDifferences($mine, $original, $yours, $type);
		$output = array();

		foreach ($diffs AS $diff)
		{
			if ($diff[0] == self::CONFLICT)
			{
				return false;
			}

			array_splice($output, count($output), 0, $diff[1]);
		}

		switch ($type)
		{
			case XenForo_Diff::DIFF_TYPE_CHAR:
				$joiner = '';
				break;

			case XenForo_Diff::DIFF_TYPE_WORD:
				$joiner = ' ';
				break;

			case XenForo_Diff::DIFF_TYPE_LINE:
			default:
				$joiner = "\n";
		}

		return implode($joiner, $output);
	}
}
<?php

class XenForo_ViewPublic_Login_PostRedirect extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$data = $this->_params['postData'];
		unset($data['_xfResponseType'], $data['_xfRequestUri'], $data['_xfNoRedirect']);
		$this->_params['hiddenHtml'] = $this->_arrayToHiddenInput($data);
	}

	protected function _arrayToHiddenInput(array $array, $prefix = '')
	{
		$output = '';
		foreach ($array AS $k => $v)
		{
			$name = strlen($prefix) ? $prefix . '[' . $k . ']' : $k;

			if (is_array($v))
			{
				$output .= $this->_arrayToHiddenInput($v, $name);
			}
			else
			{
				$output .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_COMPAT, 'utf-8')
					. '" value="' . htmlspecialchars($v, ENT_COMPAT, 'utf-8') . '" />' . "\n";
			}
		}

		return $output;
	}
}
<?php

class XenForo_ViewPublic_Member_WarnFill extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$warning = $this->_params['warning'];

		$formValues = array(
			'#WarningEditableInput' => (bool)$warning['is_editable'], // must be first
			'input[name=conversation_title]' => $warning['conversationTitle'],
			'textarea[name=conversation_message]' => $warning['conversationMessage'],
			'input[name=points_enable]' => true,
			'input[name=expiry_enable]' => true,
			'input[name=points]' => $warning['points_default']
		);

		if (!$warning['points_default'])
		{
			$formValues['input[name=points]'] = 0;
			$formValues['input[name=points_enable]'] = false;
		}

		if ($warning['expiry_type'] == 'never')
		{
			$formValues['input[name=expiry_enable]'] = false;
		}

		if (!$formValues['input[name=expiry_enable]'])
		{
			$formValues['select[name=expiry_unit]'] = 'months';
			$formValues['input[name=expiry_value]'] = 1;
		}
		else
		{
			$formValues['select[name=expiry_unit]'] = $warning['expiry_type'];
			$formValues['input[name=expiry_value]'] = $warning['expiry_default'];
		}

		return array('formValues' => $formValues);
	}
}
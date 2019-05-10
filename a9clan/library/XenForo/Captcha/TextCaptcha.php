<?php

class XenForo_Captcha_TextCaptcha extends XenForo_Captcha_Abstract
{
	/**
	 * Determines if CAPTCHA is valid (passed).
	 *
	 * @see XenForo_Captcha_Abstract::isValid()
	 */
	public function isValid(array $input)
	{
		$cleaner = new XenForo_Input($input);

		$answer = $cleaner->filterSingle('captcha_question_answer', XenForo_Input::STRING);
		$hash = $cleaner->filterSingle('captcha_question_hash', XenForo_Input::STRING);

		/** @var XenForo_Model_CaptchaQuestion $model */
		$model = XenForo_Model::create('XenForo_Model_CaptchaQuestion');
		return $model->verifyTextCaptcha($hash, $answer);
	}

	/**
	 * Renders the CAPTCHA template.
	 *
	 * @see XenForo_Captcha_Abstract::renderInternal()
	 */
	public function renderInternal(XenForo_View $view)
	{
		return $view->createTemplateObject('captcha_textcaptcha', array(
			'captchaQuestion' => XenForo_Model::create('XenForo_Model_CaptchaQuestion')->getTextCaptchaEntry()
		));
	}
}
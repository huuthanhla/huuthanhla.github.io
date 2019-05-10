<?php

class XenForo_Importer_vBulletin36x extends XenForo_Importer_vBulletin
{
	public static function getName()
	{
		return 'vBulletin 3.6';
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		unset($steps['visitorMessages'], $steps['threadPrefixes'], $steps['postEditHistory'], $steps['infractions']);

		$steps['threads'] = array(
			'title' => new XenForo_Phrase('import_threads_and_posts'),
			'depends' => array('forums', 'users') // remove thread prefix dependency
		);

		return $steps;
	}
}
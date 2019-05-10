<?php

class XenForo_Install_Controller_Install extends XenForo_Install_Controller_Abstract
{
	protected function _preDispatch($action)
	{
		if ($this->_getInstallModel()->isInstalled())
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('you_have_completed_installation_to_reinstall'))
			);
		}
	}

	public function actionIndex()
	{
		$viewParams = array(
			'errors' => $this->_getInstallModel()->getRequirementErrors(),
			'warnings' => $this->_getInstallModel()->getRequirementWarnings()
		);

		return $this->_getInstallWrapper('index',
			$this->responseView('XenForo_Install_View_Install_Index', 'install_index', $viewParams)
		);
	}

	public function actionStep1()
	{
		$configFile = XenForo_Application::getInstance()->getConfigDir() . '/config.php';
		if (file_exists($configFile))
		{
			$config = array();
			require($configFile);
		}
		else
		{
			return $this->actionConfig();
		}

		$viewParams = array(
			'config' => $config,
		);

		return $this->_getInstallWrapper(1,
			$this->responseView('XenForo_Install_View_Install_Step1', 'install_step1', $viewParams)
		);
	}

	protected function _testConfig(array $config, &$error)
	{
		$outputConfig = new Zend_Config(array(), true);
		$outputConfig
			->merge(XenForo_Application::getInstance()->loadDefaultConfig())
			->merge(new Zend_Config($config));

		try
		{
			$db = Zend_Db::factory($outputConfig->db->adapter,
				array(
					'host' => $outputConfig->db->host,
					'port' => $outputConfig->db->port,
					'username' => $outputConfig->db->username,
					'password' => $outputConfig->db->password,
					'dbname' => $outputConfig->db->dbname,
					'charset' => 'utf8'
				)
			);
			$db->getConnection();
			$db->listTables();

			$error = '';
		}
		catch (Zend_Db_Exception $e)
		{
			$error = new XenForo_Phrase('following_error_occurred_while_connecting_database', array('error' => $e->getMessage()));
		}

		return $db;
	}

	public function actionConfig()
	{
		$config = $this->_input->filterSingle('config', XenForo_Input::JSON_ARRAY);

		if ($this->_request->isPost())
		{
			$db = $this->_testConfig($config, $error);
			if ($error)
			{
				return $this->responseError($error);
			}

			$configFile = XenForo_Application::getInstance()->getConfigDir() . '/config.php';
			if (!file_exists($configFile) && is_writable(dirname($configFile)))
			{
				try
				{
					file_put_contents($configFile, $this->_getInstallModel()->generateConfig($config));
					XenForo_Helper_File::makeWritableByFtpUser($configFile);

					$written = true;
				}
				catch (Exception $e)
				{
					$written = false;
				}
			}
			else
			{
				$written = false;
			}

			$viewParams = array(
				'written' => $written,
				'configFile' => $configFile,
				'config' => $config
			);

			return $this->_getInstallWrapper(1,
				$this->responseView('XenForo_Install_View_Install_ConfigGenerated', 'install_config_generated', $viewParams)
			);
		}
		else
		{
			return $this->_getInstallWrapper(1,
				$this->responseView('XenForo_Install_View_Install_Config', 'install_config')
			);
		}
	}

	public function actionConfigSave()
	{
		$config = $this->_input->filterSingle('config', XenForo_Input::JSON_ARRAY);

		$viewParams = array(
			'generated' => $this->_getInstallModel()->generateConfig($config)
		);

		$this->_routeMatch->setResponseType('raw');
		return $this->responseView('XenForo_Install_View_Install_ConfigSave', '', $viewParams);
	}

	public function actionStep1b()
	{
		$configFile = XenForo_Application::getInstance()->getConfigDir() . '/config.php';

		if (!file_exists($configFile))
		{
			return $this->responseError(new XenForo_Phrase('config_file_x_could_not_be_found', array('file' => $configFile)));
		}

		$config = array();
		require($configFile);

		$db = $this->_testConfig($config, $error);

		if ($error)
		{
			return $this->responseError($error);
		}

		$errors = $this->_getInstallModel()->getRequirementErrors($db);
		if ($errors)
		{
			return $this->responseError($errors);
		}

		$viewParams = array(
			'existingInstall' => $this->_getInstallModel()->hasApplicationTables(),
			'warnings' => $this->_getInstallModel()->getRequirementWarnings($db),
			'config' => $config
		);

		return $this->_getInstallWrapper(1,
			$this->responseView('XenForo_Install_View_Install_Step1b', 'install_step1b', $viewParams)
		);
	}

	public function actionStep2()
	{
		$this->_assertPostOnly();

		$installModel = $this->_getInstallModel();

		$start = $this->_input->filterSingle('start', XenForo_Input::UINT);

		if (!$start)
		{
			if ($this->_input->filterSingle('remove', XenForo_Input::UINT))
			{
				$removed = $installModel->deleteApplicationTables();
			}
			else
			{
				if ($installModel->hasApplicationTables())
				{
					return $this->responseError(new XenForo_Phrase('you_cannot_proceed_unless_tables_removed'));
				}

				$removed = array();
			}
		}
		else
		{
			$removed = array();
		}

		$installModel->createApplicationTables(5, $start, $endOffset);
		if ($endOffset === false)
		{
			$installModel->insertDefaultData();
			$installModel->createDirectories();
		}

		$viewParams = array(
			'removed' => $removed,
			'endOffset' => $endOffset
		);

		return $this->_getInstallWrapper(2,
			$this->responseView('XenForo_Install_View_Install_Step2', 'install_step2', $viewParams)
		);
	}

	public function actionStep2b()
	{
		$this->_assertPostOnly();

		$this->_getInstallModel()->insertDeferredRebuild();

		return $this->responseReroute(__CLASS__, 'runDeferred');
	}

	public function actionRunDeferred()
	{
		$output = $this->_manualDeferredRunner('index.php?install/run-deferred', 'index.php?install/step/3');
		if ($output instanceof XenForo_ControllerResponse_View)
		{
			return $this->_getInstallWrapper(2, $output);
		}
		else
		{
			return $output;
		}
	}

	public function actionStep3()
	{
		return $this->_getInstallWrapper(3,
			$this->responseView('XenForo_Install_View_Install_Step3', 'install_step3')
		);
	}

	public function actionStep3b()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'email' => XenForo_Input::STRING,
			'password' => XenForo_Input::STRING,
			'password_confirm' => XenForo_Input::STRING
		));

		$this->_getInstallModel()->insertAdministrator($input);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			'index.php?install/step/4'
		);
	}

	public function actionStep4()
	{
		$optionModel = XenForo_Model::create('XenForo_Model_Option');

		$optionIds = array('boardTitle', 'boardUrl', 'contactEmailAddress', 'homePageUrl');
		$optionsRaw = $optionModel->prepareOptions($optionModel->getOptionsByIds($optionIds));
		$options = array();
		foreach ($optionIds AS $optionId)
		{
			$options[$optionId] = $optionsRaw[$optionId];
		}

		$paths = XenForo_Application::get('requestPaths');
		$options['boardUrl']['option_value'] = preg_replace('#(/install)?/?$#i', '', $paths['fullBasePath']);
		$options['homePageUrl']['option_value'] = $paths['protocol'] . '://' . $paths['host'];

		$user = XenForo_Model::create('XenForo_Model_User')->getUserById(1);
		if ($user)
		{
			$options['contactEmailAddress']['option_value'] = $user['email'];
		}

		$viewParams = array(
			'options' => $options,
			'canEditOptionDefinition' => false
		);

		return $this->_getInstallWrapper(4,
			$this->responseView('XenForo_Install_View_Install_Step4', 'install_step4', $viewParams)
		);
	}

	public function actionStep4b()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'group_id' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,
			'options_listed' => array(XenForo_Input::STRING, array('array' => true))
		));

		foreach ($input['options_listed'] AS $optionName)
		{
			if (!isset($input['options'][$optionName]))
			{
				$input['options'][$optionName] = '';
			}
		}

		if (!empty($input['options']['contactEmailAddress']))
		{
			$input['options']['defaultEmailAddress'] = $input['options']['contactEmailAddress'];
		}

		if (!empty($input['options']['boardUrl']))
		{
			$input['options']['boardUrl'] = rtrim($input['options']['boardUrl'], '/');
		}

		$xfJsFile = XenForo_Application::getInstance()->getRootDir() . '/js/xenforo/xenforo.js';
		if (file_exists($xfJsFile))
		{
			$xfJs = file_get_contents(XenForo_Application::getInstance()->getRootDir() . '/js/xenforo/xenforo.js');
			if (strpos($xfJs, '// _XF_JS_UNCOMPRESSED_TEST_') !== false)
			{
				$input['options']['uncompressedJs'] = 3;
			}
		}

		XenForo_Model::create('XenForo_Model_Option')->updateOptions($input['options']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			'index.php?install/complete'
		);
	}

	public function actionComplete()
	{
		$this->_getInstallModel()->completeInstallation();

		$this->getModelFromCache('XenForo_Model_Deferred')->run(false);

		return $this->_getInstallWrapper('complete',
			$this->responseView('XenForo_Install_View_Install_Complete', 'install_complete')
		);
	}

	protected function _getInstallWrapper($step, XenForo_ControllerResponse_View $subView)
	{
		$params = array(
			'step' => $step
		);

		$view = $this->responseView('XenForo_Install_View_Install_Wrapper', 'install_wrapper', $params);
		$view->subView = $subView;

		return $view;
	}

	protected function _setupSession($action) {}
	protected function _handlePost($action) {}

	/**
	 * @return XenForo_Install_Model_Install
	 */
	protected function _getInstallModel()
	{
		return $this->getModelFromCache('XenForo_Install_Model_Install');
	}
}
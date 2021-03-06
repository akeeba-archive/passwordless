<?php
/**
 * @package   AkeebaPasswordlessLogin
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Passwordless\PluginTraits;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\Passwordless\Exception\AjaxNonCmsAppException;
use Akeeba\Passwordless\Helper\Joomla;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

/**
 * Allows the plugin to handle AJAX requests in the backend of the site, where com_ajax is not available when we are not
 * logged in.
 */
trait AjaxHandler
{
	/**
	 * We need to log into the backend BUT com_ajax is not accessible unless we are already logged in. Moreover, since
	 * the backend is a separate application from the frontend we cannot share the user session between them. Therefore
	 * I am going to write my own AJAX handler for the backend by abusing the onAfterInitialize event.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAfterInitialiseAjax(): void
	{
		// At this stage we can safely load the language
		$this->loadLanguage();

		// Make sure this is the backend of the site...
		if (!Joomla::isAdminPage())
		{
			return;
		}

		// ...and we are not already logged in...
		if (!Joomla::getUser()->guest)
		{
			return;
		}

		$app   = Joomla::getApplication();
		$input = $app->input;

		// ...and this is a request to com_ajax...
		if ($input->getCmd('option', '') != 'com_ajax')
		{
			return;
		}

		// ...about a system plugin...
		if ($input->getCmd('group', '') != 'system')
		{
			return;
		}

		// ...called 'passwordless'
		if ($input->getCmd('plugin', '') != 'passwordless')
		{
			return;
		}

		/**
		 * Why do we go through onAjaxPasswordless instead of importing the code directly in here?
		 *
		 * AJAX responses are called through com_ajax. In the frontend the com_ajax component itself is handling the
		 * request, without going through our special onAfterInitialize handler. As a result, it calls the
		 * onAjaxPasswordless plugin event directly.
		 *
		 * In the backend, however, com_ajax is not accessible before we log in. This doesn't help us any since we need
		 * it when we are not logged in, to perform the passwordless login. Therefore our special onAfterInitialize
		 * code kicks in and simulates what com_ajax would do, to a degree that it's sufficient for our purposes.
		 *
		 * Only in the second case would it make sense to import the code here. In the interest of keeping it DRY we do
		 * not do that, instead going through the plugin event with a negligible performance impact in the order of a
		 * millisecond or less. This is orders of magnitude less than the roundtrip time of the AJAX request.
		 */
		Joomla::runPlugins('onAjaxPasswordless', []);
	}

	/**
	 * Processes the callbacks from the passwordless login views.
	 *
	 * Note: this method is called from Joomla's com_ajax or, in the case of backend logins, through the special
	 * onAfterInitialize handler we have created to work around com_ajax usage limitations in the backend.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function onAjaxPasswordless(): void
	{
		/** @var CMSApplication $app */
		$app   = Joomla::getApplication();
		$input = $app->input;

		// Get the return URL from the session
		$returnURL = Joomla::getSessionVar('returnUrl', Uri::base(), 'plg_system_passwordless');
		$result    = null;

		try
		{
			Joomla::log('system', "Received AJAX callback.");

			if (!Joomla::isCmsApplication($app))
			{
				throw new AjaxNonCmsAppException();
			}

			$input    = $app->input;
			$akaction = $input->getCmd('akaction');
			$token    = Joomla::getToken();

			if ($input->getInt($token, 0) != 1)
			{
				throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
			}

			// Empty action? No bueno.
			if (empty($akaction))
			{
				throw new RuntimeException(Text::_('PLG_SYSTEM_PASSWORDLESS_ERR_AJAX_INVALIDACTION'));
			}

			// Call the plugin event onAjaxPasswordlessSomething where Something is the akaction param.
			$eventName = 'onAjaxPasswordless' . ucfirst($akaction);

			$results = Joomla::runPlugins($eventName, [], $app);
			$result  = null;

			foreach ($results as $r)
			{
				if (is_null($r))
				{
					continue;
				}

				$result = $r;

				break;
			}
		}
		catch (AjaxNonCmsAppException $e)
		{
			Joomla::log('system', "This is not a CMS application", Log::NOTICE);

			$result = null;
		}
		catch (Exception $e)
		{
			Joomla::log('system', "Callback failure, redirecting to $returnURL.");
			Joomla::setSessionVar('returnUrl', null, 'plg_system_passwordless');
			$app->enqueueMessage($e->getMessage(), 'error');
			$app->redirect($returnURL);

			return;
		}

		if (!is_null($result))
		{
			switch ($input->getCmd('encoding', 'json'))
			{
				default:
				case 'json':
					Joomla::log('system', "Callback complete, returning JSON.");
					echo json_encode($result);

					break;

				case 'jsonhash':
					Joomla::log('system', "Callback complete, returning JSON inside ### markers.");
					echo '###' . json_encode($result) . '###';

					break;

				case 'raw':
					Joomla::log('system', "Callback complete, returning raw response.");
					echo $result;

					break;

				case 'redirect':
					$modifiers = '';

					if (isset($result['message']))
					{
						$type = isset($result['type']) ? $result['type'] : 'info';
						$app->enqueueMessage($result['message'], $type);

						$modifiers = " and setting a system message of type $type";
					}

					if (isset($result['url']))
					{
						Joomla::log('system', "Callback complete, performing redirection to {$result['url']}{$modifiers}.");
						$app->redirect($result['url']);
					}


					Joomla::log('system', "Callback complete, performing redirection to {$result}{$modifiers}.");
					$app->redirect($result);

					return;
					break;
			}

			$app->close(200);
		}

		Joomla::log('system', "Null response from AJAX callback, redirecting to $returnURL");
		Joomla::setSessionVar('returnUrl', null, 'plg_system_passwordless');

		$app->redirect($returnURL);
	}
}
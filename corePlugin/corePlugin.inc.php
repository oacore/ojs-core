<?php

import('lib.pkp.classes.plugins.GenericPlugin');

class corePlugin extends GenericPlugin {

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed')) return false;
		$this->addLocaleData();
		if ($success) {
            // Hook into ojs system
			// Article footer
			HookRegistry::register('Templates::Article::Footer::PageFooter', array($this, 'insertFooter'));
			
            // Article interstitial footer
			HookRegistry::register('Templates::Article::Interstitial::PageFooter', array($this, 'insertFooter'));

			// Article pdf interstitial footer
			HookRegistry::register('Templates::Article::PdfInterstitial::PageFooter', array($this, 'insertFooter'));

            HookRegistry::register('TemplateManager::display', array(&$this, 'insertHeaders'));

		}
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category, and should be suitable for part of a filename
	 * (ie short, no spaces, and no dependencies on cases being unique).
	 * @return String name of plugin
	 */
	function getName() {
		return 'corePlugin';
	}

	function getDisplayName() {
		return __('plugins.generic.corePlugin.displayName');
	}

	function getDescription() {
		return __('plugins.generic.corePlugin.description');
	}

	/**
	 * Extend the {url ...} smarty to support this plugin.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array($this->getCategory(), $this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}

		if (!empty($params['id'])) {
			$params['path'] = array_merge($params['path'], array($params['id']));
			unset($params['id']);
		}
		return $smarty->smartyUrl($params, $smarty);
	}

	/**
	 * Set the page's breadcrumbs, given the plugin's tree of items
	 * to append.
	 * @param $subclass boolean
	 */
	function setBreadcrumbs($isSubclass = false) {
		$templateMgr = &TemplateManager::getManager();
		$pageCrumbs = array(
			array(
				Request::url(null, 'user'),
				'navigation.user'
			),
			array(
				Request::url(null, 'manager'),
				'user.role.manager'
			)
		);
		if ($isSubclass) $pageCrumbs[] = array(
			Request::url(null, 'manager', 'plugins'),
			'manager.plugins'
		);

		$templateMgr->assign('pageHierarchy', $pageCrumbs);
	}

	/**
	 * Display verbs for the management interface.
	 */
	function getManagementVerbs() {
		$verbs = array();
		if ($this->getEnabled()) {
			$verbs[] = array(
				'disable',
				__('manager.plugins.disable')
			);
			$verbs[] = array(
				'settings',
				__('plugins.generic.corePlugin.manager.settings')
			);
		} else {
			$verbs[] = array(
				'enable',
				__('manager.plugins.enable')
			);
		}
		return $verbs;
	}

	/**
	 * Determine whether or not this plugin is enabled.
	 */
	function getEnabled() {
		$journal =& Request::getJournal();
		if (!$journal) return false;
		return $this->getSetting($journal->getId(), 'enabled');
	}

	/**
	 * Set the enabled/disabled state of this plugin
	 */
	function setEnabled($enabled) {
		$journal = &Request::getJournal();
		if ($journal) {
			$this->updateSetting($journal->getJournalId(), 'enabled', $enabled ? true : false);
			return true;
		}
		return false;
	}

    function insertHeaders($hookName, $args) {
        $templateManager =& $args[0];

        $additionalHeadData = $templateManager->get_template_vars('styleSheets');

        $script1 = '<link rel="stylesheet" type="text/css" href="' . Request::getBaseUrl() . '/' . $this->getPluginPath() . '/css/jquery.coreWidget.css" media="screen">';

        $templateManager->assign('additionalHeadData', $additionalHeadData."\n\t".$script1);
    }
    
	/**
	 * Insert Javascript onto page
	 */
	function insertFooter($hookName, $params) {
		if ($this->getEnabled()) {
			$smarty = &$params[1];
			$output = &$params[2];
			$journal = &Request::getJournal();
			$journalId = $journal->getJournalId();
			$journalPath = $journal->getPath();
			$coreApiKey = $this->getSetting($journalId, 'coreApiKey');
            $corePluginTitle = $this->getSetting($journalId, 'corePluginTitle');

			if (!empty($coreApiKey)) {
				$output =   '<!-- CORE Plugin Section -->'.	
                        '<div id="pluginOutput"></div>'.
                        '<script type="text/javascript" src="'. Request::getBaseUrl() . '/' . $this->getPluginPath() . '/js/jquery.coreWidget.js" /></script>'.
                        '<script type="text/javascript">'.
                        '    $(function() {'.
                        '        $("#pluginOutput").coreWidget("' . $corePluginTitle . '" ,{'.
                        '            documentOAI: "OAI:" + $("meta[name=\'DC.Identifier.URI\']").attr("content") + ":" + $("meta[name=\'DC.Identifier\']").attr("content") ,'.
                        '            documentUrl: $("meta[name=\'DC.Identifier.URI\']").attr("content"),'.
                        '            documentTitle: $("meta[name=\'DC.Title\']").attr("content"),'.
                        '            documentAuthors: ["", "", ""],'.
                        '            documentAbstract: $("meta[name=\'DC.Description\']").attr("content"),'.
                        '            apiKey: "V92JJ1LqdzX1hm0Ozp1tlL1FfWaW1bhL"'.
                        '    });'.
                        '});'.
                        '</script>'.
						'<!-- End CORE Plugin Section -->';
                //error_log(print_r($smarty, true), 3, "/data/web/my-errors.log");
     
			}
		}
		return false;
	}

	/**
	 * Perform management functions
	 */
	function manage($verb, $args) {
		$templateMgr = &TemplateManager::getManager();
		$templateMgr->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
		$journal = &Request::getJournal();
		$returner = true;

		switch ($verb) {
			case 'enable':
				$this->setEnabled(true);
				$returner = false;
				break;
			case 'disable':
				$this->setEnabled(false);
				$returner = false;
				break;
			case 'settings':
				if ($this->getEnabled()) {
					$this->import('corePluginSettingsForm');
					$form = &new corePluginSettingsForm($this, $journal->getJournalId());
					if (Request::getUserVar('save')) {
						$form->readInputData();
						if ($form->validate()) {
							$form->execute();
							Request::redirect(null, 'manager', 'plugin');
						} else {
							$this->setBreadCrumbs(true);
							$form->display();
						}
					} else {
						$this->setBreadCrumbs(true);
						$form->initData();
						$form->display();
					}
				} else {
					Request::redirect(null, 'manager');
				}
				break;
			default:
				Request::redirect(null, 'manager');
		}
		return $returner;
	}
}
?>

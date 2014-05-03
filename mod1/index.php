<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007-2014 Michael Gagnon <mgagnon@infoglobe.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

// DEFAULT initialization of a module [BEGIN]

$GLOBALS['LANG']->includeLLFile('EXT:ig_ldap_sso_auth/res/locallang_mod1.xml');

// This checks permissions and exits if the users has no permission for entry.
$GLOBALS['BE_USER']->modAccess($MCONF, 1);

// DEFAULT initialization of a module [END]

/**
 * Module 'LDAP configuration' for the 'ig_ldap_sso_auth' extension.
 *
 * @author	Michael Gagnon <mgagnon@infoglobe.ca>
 * @package	TYPO3
 * @subpackage	tx_igldapssoauth
 */
class tx_igldapssoauth_module1 extends t3lib_SCbase {

	const FUNCTION_VIEW_CONFIGURATION = 1;
	const FUNCTION_WIZARD_SEARCH = 2;
	const FUNCTION_WIZARD_AUTHENTICATION = 3;
	const FUNCTION_IMPORT_GROUPS = 4;

	var $pageinfo;
	var $lang;

	/**
	 * @var string
	 */
	protected $extKey = 'ig_ldap_sso_auth';

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * Default constructor
	 */
	public function __construct() {
		$config = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey];
		$this->config = $config ? unserialize($config) : array();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	public function menuConfig() {
		$this->MOD_MENU = Array(
			'function' => Array(
				static::FUNCTION_VIEW_CONFIGURATION => $GLOBALS['LANG']->getLL('view_configuration'),
				static::FUNCTION_WIZARD_SEARCH => $GLOBALS['LANG']->getLL('wizard_search'),
				static::FUNCTION_IMPORT_GROUPS => $GLOBALS['LANG']->getLL('import_groups'),
			)
		);

		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return void
	 */
	public function main() {
		if (!version_compare(TYPO3_version, '4.5.99', '>')) {
			// See bug http://forge.typo3.org/issues/31697
			$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = 1;
		}
		if (version_compare(TYPO3_version, '6.1.0', '>=')) {
			$this->doc = t3lib_div::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
		} else {
			$this->doc = t3lib_div::makeInstance('template');
		}
		if (version_compare(TYPO3_branch, '6.2', '>=')) {
			$this->doc->setModuleTemplate(t3lib_extMgm::extPath($this->extKey) . 'mod1/mod_template.html');
		} else {
			$this->doc->setModuleTemplate(t3lib_extMgm::extPath($this->extKey) . 'mod1/mod_template_v45-61.html');
		}
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$docHeaderButtons = $this->getButtons();

		if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id)) {
			$this->doc->form = '<form action="" method="post">';

			if (version_compare(TYPO3_branch, '6.0', '<')) {
				// override the default jumpToUrl
				$this->doc->JScodeArray['jumpToUrl'] = '
					function jumpToUrl(URL) {
						document.location = URL;
					}
				';
			}

			// Initialize the LDAP connection:
			tx_igldapssoauth_config::init('', 0);

			// Render content:
			$this->moduleContent();
		} else {
			// If no access or if ID == zero
			$docHeaderButtons['save'] = '';
			$this->content .= $this->doc->spacer(10);
		}

		// Compile document
		$markers['FUNC_MENU'] = $this->doc->funcMenu('', t3lib_BEfunc::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']));
		$markers['CONTENT'] = $this->content;
		$this->content = '';

		// Build the <body> for the module
		$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Generates the module content.
	 *
	 * @return void
	 */
	protected function moduleContent() {
		$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));

		$uidArray = t3lib_div::intExplode(',', $this->conf['uidConfiguration']);

		foreach ($uidArray as $uid) {
			tx_igldapssoauth_config::init(TYPO3_MODE, $uid);
			$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('view_configuration_title') . ' ' . tx_igldapssoauth_config::getName() . ' (' . tx_igldapssoauth_config::getUid() . ')</h2>';
			$this->content .= '<hr />';

			switch ((string)$this->MOD_SETTINGS['function']) {
				case static::FUNCTION_VIEW_CONFIGURATION:
					$this->view_configuration();
					break;
				case static::FUNCTION_WIZARD_SEARCH:
					$this->wizard_search(t3lib_div::_GP('search'));
					break;
				case static::FUNCTION_WIZARD_AUTHENTICATION:
					//$this->wizard_authentication(t3lib_div::_GP('authentication'));
					break;
				case static::FUNCTION_IMPORT_GROUPS:
					$this->import_groups();
					break;
			}
		}
	}

	/**
	 * Creates the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc.
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => '',
			'shortcut' => '',
			'close' => '',
			'save' => '',
			'save_close' => '',
		);

		// CSH
		$buttons['csh'] = t3lib_BEfunc::cshItem('_MOD_web_func', '', $GLOBALS['BACK_PATH']);

		// Shortcut
		if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
			$buttons['shortcut'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
		}

		return $buttons;
	}

	protected function view_configuration() {
		$feConfiguration = tx_igldapssoauth_config::getFeConfiguration();
		$beConfiguration = tx_igldapssoauth_config::getBeConfiguration();

		// LDAP
		$this->content .= '<h3>' . $GLOBALS['LANG']->getLL('view_configuration_ldap') . '</h3>';
		$this->content .= '<hr />';

		$ldapConfiguration = tx_igldapssoauth_config::getLdapConfiguration();
		if ($ldapConfiguration['host']) {

			$ldapConfiguration['server'] = tx_igldapssoauth_config::get_server_name($ldapConfiguration['server']);

			tx_igldapssoauth_ldap::connect($ldapConfiguration);
			$ldapConfiguration['password'] = $ldapConfiguration['password'] ? '********' : NULL;

			$this->content .= t3lib_utility_Debug::viewArray($ldapConfiguration);

			$this->content .= '<h3><strong>' . $GLOBALS['LANG']->getLL('view_configuration_ldap_connexion_status') . '</strong></h3>';
			$this->content .= '<h4>' . t3lib_utility_Debug::viewArray(tx_igldapssoauth_ldap::get_status()) . '</h4>';

		} else {

			$this->content .= '<strong>' . $GLOBALS['LANG']->getLL('view_configuration_ldap_disable') . '</strong>';
			return FALSE;
		}

		// CAS
		$this->content .= '<h3>' . $GLOBALS['LANG']->getLL('view_configuration_cas') . '</h3>';
		$this->content .= '<hr />';

		if ($feConfiguration['LDAPAuthentication'] && $feConfiguration['CASAuthentication']) {
			$this->content .= t3lib_utility_Debug::viewArray(tx_igldapssoauth_config::getCasConfiguration());
		} else {
			$this->content .= '<strong>' . $GLOBALS['LANG']->getLL('view_configuration_cas_disable') . '</strong>';
		}

		// BE
		$this->content .= '<h3>' . $GLOBALS['LANG']->getLL('view_configuration_backend_authentication') . '</h3>';
		$this->content .= '<hr />';

		if ($beConfiguration['LDAPAuthentication']) {
			$this->content .= t3lib_utility_Debug::viewArray($beConfiguration);
		} else {
			$this->content .= '<strong>' . $GLOBALS['LANG']->getLL('view_configuration_backend_authentication_disable') . '</strong>';
		}

		// FE
		$this->content .= '<h3>' . $GLOBALS['LANG']->getLL('view_configuration_frontend_authentication') . '</h3>';
		$this->content .= '<hr />';

		if ($feConfiguration['LDAPAuthentication']) {
			$this->content .= t3lib_utility_Debug::viewArray($feConfiguration);
		} else {
			$this->content .= '<strong>' . $GLOBALS['LANG']->getLL('view_configuration_frontend_authentication_disable') . '</strong>';
		}
	}

	function wizard_search($search = array()) {

		switch ($search['action']) {

			case 'select' :

				list($typo3_mode, $table) = explode('_', $search['table']);
				$config = ($typo3_mode === 'be') ? tx_igldapssoauth_config::getBeConfiguration() : tx_igldapssoauth_config::getFeConfiguration();

				$search['basedn'] = $config[$table]['basedn'];
				$search['filter'] = tx_igldapssoauth_config::replace_filter_markers($config[$table]['filter']);
				$search['attributes'] = $search['first_entry'] ? '' : implode(',', tx_igldapssoauth_config::get_ldap_attributes($config[$table]['mapping']));

				break;

			case 'search' :

				break;

			default :

				$search['table'] = 'be_users';

				list($typo3_mode, $table) = explode('_', $search['table']);
				$config = ($typo3_mode === 'be') ? tx_igldapssoauth_config::getBeConfiguration() : tx_igldapssoauth_config::getFeConfiguration();

				$search['first_entry'] = TRUE;
				$search['see_status'] = FALSE;
				$search['basedn'] = $config[$table]['basedn'];
				$search['filter'] = tx_igldapssoauth_config::replace_filter_markers($config[$table]['filter']);
				$search['attributes'] = $search['first_entry'] ? '' : implode(',', tx_igldapssoauth_config::get_ldap_attributes($config[$table]['mapping']));

				break;
		}

		$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_title') . '</h2>';
		$this->content .= '<hr />';

		if (tx_igldapssoauth_ldap::connect(tx_igldapssoauth_config::getLdapConfiguration())) {
			if (is_array($search['basedn'])) {
				$search['basedn'] = implode('||', $search['basedn']);
			}
			$first_entry = $search['first_entry'] ? 'checked="checked"' : "";
			$see_status = $search['see_status'] ? 'checked="checked"' : "";
			$be_users = ($search['table'] == 'be_users') ? 'checked="checked"' : "";
			$be_groups = ($search['table'] == 'be_groups') ? 'checked="checked"' : "";
			$fe_users = ($search['table'] == 'fe_users') ? 'checked="checked"' : "";
			$fe_groups = ($search['table'] == 'fe_groups') ? 'checked="checked"' : "";

			$this->content .= '<form action="" method="post" name="search">';

			$this->content .= '<fieldset>';

			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-beusers" value="be_users" ' . $be_users . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-beusers"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_radio_be_users') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-begroups" value="be_groups" ' . $be_groups . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-beusers"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_radio_be_groups') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-feusers" value="fe_users" ' . $fe_users . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-feusers"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_radio_fe_users') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-fegroups" value="fe_groups" ' . $fe_groups . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-fegroups"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_radio_fe_groups') . '</strong></label>
				</div>';
			$this->content .= '<br />';

			$this->content .= '
				<div>
					<input type="checkbox" name="search[first_entry]" id="first-entry" value="true" ' . $first_entry . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="first-entry"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_checkbox_first_entry') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="checkbox" name="search[see_status]" id="see-status" value="true" ' . $see_status . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="see-status"><strong>' . $GLOBALS['LANG']->getLL('wizard_search_checkbox_see_status') . '</strong></label>
				</div>';
			$this->content .= '<br />';

			$this->content .= '<div><strong>' . $GLOBALS['LANG']->getLL('wizard_search_input_base_dn') . '</strong>&nbsp;<input type="text" name="search[basedn]" value="' . $search['basedn'] . '" size="50" /></div><br />';
			$this->content .= '<div><strong>' . $GLOBALS['LANG']->getLL('wizard_search_input_filter') . '</strong>&nbsp;<input type="text" name="search[filter]" value="' . $search['filter'] . '" size="50" /></div><br />';
			$this->content .= $search['attributes'] ? '<div><strong>' . $GLOBALS['LANG']->getLL('wizard_search_input_attributes') . '</strong>&nbsp;<input type="text" name="search[attributes]" value="' . $search['attributes'] . '" size="50" /></div><br />' : '';

			$this->content .= '<input type="hidden" name="search[action]" value="' . $search['action'] . '" />';
			$this->content .= '<input type="submit" value="' . $GLOBALS['LANG']->getLL('wizard_search_submit_search') . '" onclick="this.form.elements[\'search[action]\'].value=\'search\';" />';

			$this->content .= '</fieldset>';

			$this->content .= '</form><br />';

			$attributes = array();

			if (!$search['first_entry'] || !empty($search['attributes'])) {

				$attributes = explode(',', $search['attributes']);

			}
			$search['basedn'] = explode('||', $search['basedn']);
			if ($result = tx_igldapssoauth_ldap::search($search['basedn'], $search['filter'], $attributes, $search['first_entry'], 100)) {

				$this->content .= $search['see_status'] ? '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_ldap_status') . '</h2><hr />' . t3lib_utility_Debug::viewArray(tx_igldapssoauth_ldap::get_status()) : null;
				$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_result') . '</h2>';
				$this->content .= '<hr />';
				$this->content .= t3lib_utility_Debug::viewArray($result);

			} else {

				$this->content .= $search['see_status'] ? '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_ldap_status') . '</h2><hr />' . t3lib_utility_Debug::viewArray(tx_igldapssoauth_ldap::get_status()) : null;
				$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_no_result') . '</h2>';
				$this->content .= '<hr />';
				$this->content .= t3lib_utility_Debug::viewArray(array());

			}

			tx_igldapssoauth_ldap::disconnect();

		} else {

			$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('wizard_search_ldap_status') . '</h2><hr />' . t3lib_utility_Debug::viewArray(tx_igldapssoauth_ldap::get_status());

		}

	}

	protected function import_groups() {

		$typo3_modes = array('fe', 'be');
		$import_groups = t3lib_div::_GP('import');

		$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('import_groups_title') . '</h2>';
		$this->content .= '<hr />';

		if (tx_igldapssoauth_ldap::connect(tx_igldapssoauth_config::getLdapConfiguration())) {

			foreach ($typo3_modes as $typo3_mode) {
				$config = ($typo3_mode === 'be') ? tx_igldapssoauth_config::getBeConfiguration() : tx_igldapssoauth_config::getFeConfiguration();
				if ($ldap_groups = tx_igldapssoauth_ldap::search(
					$config['groups']['basedn'],
					tx_igldapssoauth_config::replace_filter_markers(
						$config['groups']['filter']),
						tx_igldapssoauth_config::get_ldap_attributes($config['groups']['mapping'])
					)) {

					$this->content .= '<form action="" method="post" name="import_' . $typo3_mode . '_groups">';

					$this->content .= '<fieldset>';

					$this->content .= '<table border="1">';

					$this->content .= '<tr>' .
						'<th>' . $GLOBALS['LANG']->getLL('import_groups_table_th_title') . '</th>' .
						'<th>' . $GLOBALS['LANG']->getLL('import_groups_table_th_dn') . '</th>' .
						'<th>' . $GLOBALS['LANG']->getLL('import_groups_table_th_pid') . '</th>' .
						'<th>' . $GLOBALS['LANG']->getLL('import_groups_table_th_uid') . '</th>' .
						'<th>' . $GLOBALS['LANG']->getLL('import_groups_table_th_import') . '</th>' .
						'</tr>';

					$this->content .= '<caption><h2>' . $GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode . '_title') . '</h2></caption>';

					$typo3_group_pid = tx_igldapssoauth_config::get_pid($config['groups']['mapping']);
					$typo3_groups = tx_igldapssoauth_auth::get_typo3_groups($ldap_groups, $config['groups']['mapping'], $typo3_mode . '_groups', $typo3_group_pid);

					unset($ldap_groups['count']);

					foreach ($ldap_groups as $index => $ldap_group) {
						$typo3_group = tx_igldapssoauth_auth::merge($ldap_group, $typo3_groups[$index], $config['groups']['mapping']);
						if (isset($import_groups[$typo3_mode]) && in_array($typo3_group['tx_igldapssoauth_dn'], $import_groups[$typo3_mode])) {
							unset($typo3_group['parentGroup']);
							$typo3_group = tx_igldapssoauth_typo3_group::insert($typo3_mode . '_groups', $typo3_group);
							$typo3_group = $typo3_group[0];

							$fieldParent = $config['groups']['mapping']['parentGroup'];
							preg_match("`<([^$]*)>`", $fieldParent, $attribute);
							$fieldParent = $attribute[1];

							if (is_array($ldap_group[$fieldParent])) {
								unset($ldap_group[$fieldParent]['count']);
								if (is_array($ldap_group[$fieldParent])) {
									$this->setParentGroup($ldap_group[$fieldParent], $fieldParent, $typo3_group['uid'], $typo3_group_pid, $typo3_mode);
								}
							}
						}

						$this->content .= '<tr>' .
							'<td>' . ($typo3_group['title'] ? $typo3_group['title'] : '&nbsp;') . '</td>' .
							'<td>' . $typo3_group['tx_igldapssoauth_dn'] . '</td>' .
							'<td>' . ($typo3_group['pid'] ? $typo3_group['pid'] : 0) . '</td>' .
							'<td>' . ($typo3_group['uid'] ? $typo3_group['uid'] : 0) . '</td>' .
							'<td align="center"><input type="checkbox" name="import[' . $typo3_mode . '][]" value="' . $typo3_group['tx_igldapssoauth_dn'] . '" ' . ($typo3_group['uid'] ? 'checked="checked" disabled="disabled"' : null) . ' /></td>' .
							'</tr>';

					}

					$this->content .= '</table><br />';

					$this->content .= '<input type="hidden" name="import[action]" value="update" />';
					$this->content .= '<input type="submit" value="' . $GLOBALS['LANG']->getLL('import_groups_form_submit_value') . '" onclick="this.form.elements[\'import[action]\'].value=\'update\';" />';

					$this->content .= '</fieldset>';

					$this->content .= '</form><br />';

				} else {

					$this->content .= '<h3>' . $GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode . '_no_groups_found') . '</h3>';
					//$this->content .= '<hr />';
					//$this->content .= t3lib_utility_Debug::viewArray(array());

				}

			}

			tx_igldapssoauth_ldap::disconnect();

		}

	}

	function setParentGroup($parentsLDAPGroups, $feildParent, $childUid, $typo3_group_pid, $typo3_mode) {
		foreach ($parentsLDAPGroups as $parentDn) {
			$typo3ParentGroup = tx_igldapssoauth_typo3_group::select($typo3_mode . '_groups', FALSE, $typo3_group_pid, '', $parentDn);

			if (is_array($typo3ParentGroup[0])) {
				if (!empty($typo3ParentGroup[0]['subgroup'])) {
					$subGroupList = t3lib_div::trimExplode(',', $typo3ParentGroup[0]['subgroup']);
				}
				//if(!is_array($subGroupList)||!in_array($childUid,$subGroupList)){
				$subGroupList[] = $childUid;
				$subGroupList = array_unique($subGroupList);
				$typo3ParentGroup[0]['subgroup'] = implode(',', $subGroupList);
				tx_igldapssoauth_typo3_group::update($typo3_mode . '_groups', $typo3ParentGroup[0]);
				//}
			} else {
				$config = ($typo3_mode === 'be') ? tx_igldapssoauth_config::getBeConfiguration() : tx_igldapssoauth_config::getFeConfiguration();
				if ($ldap_groups = tx_igldapssoauth_ldap::search($config['groups']['basedn'], '(&' . tx_igldapssoauth_config::replace_filter_markers($config['groups']['filter']) . '&(distinguishedName=' . $parentDn . '))', tx_igldapssoauth_config::get_ldap_attributes($config['groups']['mapping']))) {
					if (is_array($ldap_groups)) {
						$typo3_group_pid = tx_igldapssoauth_config::get_pid($config['groups']['mapping']);

						$typo3_groups = tx_igldapssoauth_auth::get_typo3_groups($ldap_groups, $config['groups']['mapping'], $typo3_mode . '_groups', $typo3_group_pid);

						unset($ldap_groups['count']);

						foreach ($ldap_groups as $index => $ldap_group) {
							$typo3_group = tx_igldapssoauth_auth::merge($ldap_group, $typo3_groups[$index], $config['groups']['mapping']);
							unset($typo3_group['parentGroup']);
							$typo3_group['subgroup'] = $childUid;
							$typo3_group = tx_igldapssoauth_typo3_group::insert($typo3_mode . '_groups', $typo3_group);
							$typo3_group = $typo3_group[0];

							if (is_array($ldap_group[$feildParent])) {
								unset($ldap_group[$feildParent]['count']);
								if (is_array($ldap_group[$feildParent])) {
									$this->setParentGroup($ldap_group[$feildParent], $feildParent, $typo3_group['uid'], $typo3_group_pid, $typo3_mode);
								}

							}
						}
					}
				}
			}
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return string HTML content
	 */
	function printContent() {
		echo $this->content;
	}

}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ig_ldap_sso_auth/mod1/index.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ig_ldap_sso_auth/mod1/index.php']);
}

// Make instance:
/** @var $SOBE tx_igldapssoauth_module1 */
$SOBE = t3lib_div::makeInstance('tx_igldapssoauth_module1');
$SOBE->init();

// Include files?
foreach ($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

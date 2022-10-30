<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can also view it online at
 * https://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  the authors
 * @author     Froxlor team <team@froxlor.org>
 * @license    https://files.froxlor.org/misc/COPYING.txt GPLv2
 */

use Froxlor\Froxlor;
use Froxlor\FileDir;
use Froxlor\Database\Database;
use Froxlor\Settings;
use Froxlor\Install\Update;

if (!defined('_CRON_UPDATE')) {
	if (!defined('AREA') || (defined('AREA') && AREA != 'admin') || !isset($userinfo['loginname']) || (isset($userinfo['loginname']) && $userinfo['loginname'] == '')) {
		header('Location: ../../../../index.php');
		exit();
	}
}

// last 0.10.x release
if (Froxlor::isFroxlorVersion('0.10.38')) {

	$update_to = '2.0.0-beta1';

	Update::showUpdateStep("Updating from 0.10.38 to ".$update_to, false);

	Update::showUpdateStep("Removing unused table");
	Database::query("DROP TABLE IF EXISTS `panel_sessions`;");
	Database::query("DROP TABLE IF EXISTS `panel_languages`;");
	Update::lastStepStatus(0);

	Update::showUpdateStep("Updating froxlor - theme");
	Database::query("UPDATE `" . TABLE_PANEL_ADMINS . "` SET `theme` = 'Froxlor' WHERE `theme` <> 'Froxlor';");
	Database::query("UPDATE `" . TABLE_PANEL_CUSTOMERS . "` SET `theme` = 'Froxlor' WHERE `theme` <> 'Froxlor';");
	Settings::Set('panel.default_theme', 'Froxlor');
	Update::lastStepStatus(0);

	Update::showUpdateStep("Creating new tables and fields");
	Database::query("DROP TABLE IF EXISTS `panel_usercolumns`;");
	$sql = "CREATE TABLE `panel_usercolumns` (
	`adminid` int(11) NOT NULL default '0',
	`customerid` int(11) NOT NULL default '0',
	`section` varchar(500) NOT NULL default '',
	`columns` text NOT NULL,
	UNIQUE KEY `user_section` (`adminid`, `customerid`, `section`),
	KEY adminid (adminid),
	KEY customerid (customerid)
	) ENGINE=InnoDB CHARSET=utf8 COLLATE=utf8_general_ci;";
	Database::query($sql);
	// new customer allowed_mysqlserver field
	Database::query("ALTER TABLE `" . TABLE_PANEL_CUSTOMERS . "` ADD `allowed_mysqlserver` varchar(500) NOT NULL default '[0]';");
	// ftp_users adjustments
	Database::query("ALTER TABLE `" . TABLE_FTP_USERS . "` CHANGE `password` varchar(255) NOT NULL default '';");
	// mail_users adjustments
	Database::query("ALTER TABLE `" . TABLE_MAIL_USERS . "` CHANGE `password` varchar(255) NOT NULL default '';");
	Database::query("ALTER TABLE `" . TABLE_MAIL_USERS . "` CHANGE `password_enc` varchar(255) NOT NULL default '';");
	Update::lastStepStatus(0);

	Update::showUpdateStep("Checking for multiple mysql-servers to allow acccess to customers for existing databases");
	$dbservers_stmt = Database::query("
		SELECT `customerid`,
		GROUP_CONCAT(DISTINCT `dbserver` SEPARATOR ',') as allowed_mysqlserver
		FROM `" . TABLE_PANEL_DATABASES . "`
		GROUP BY `customerid`;
	");
	$upd_stmt = Database::prepare("UPDATE `" . TABLE_PANEL_CUSTOMERS . "` SET `allowed_mysqlserver` = :allowed_mysqlserver WHERE `customerid` = :customerid");
	while ($dbserver = $dbservers_stmt->fetch(PDO::FETCH_ASSOC)) {
		if (isset($dbserver['allowed_mysqlserver']) && !empty($dbserver['allowed_mysqlserver'])) {
			$allowed_mysqlserver = json_encode(explode(",", $dbserver['allowed_mysqlserver']));
			Database::pexecute($upd_stmt, ['allowed_mysql_server' => $allowed_mysqlserver, 'customerid' => $dbserver['customerid']]);
		}
	}
	Update::lastStepStatus(0);

	Update::showUpdateStep("Cleaning up old files");
	$to_clean = array(
		"install/lib",
		"install/lng",
		"install/updates/froxlor/0.9",
		"install/updates/froxlor/0.10",
		"install/updates/preconfig/0.9",
		"install/updates/preconfig/0.10",
		"install/updates/preconfig.php",
		"templates/Sparkle",
		"lib/version.inc.php",
		"lng/czech.lng.php",
		"lng/dutch.lng.php",
		"lng/english.lng.php",
		"lng/french.lng.php",
		"lng/german.lng.php",
		"lng/italian.lng.php",
		"lng/lng_references.php",
		"lng/portugues.lng.php",
		"lng/swedish.lng.php",
		"scripts",
	);
	$disabled = explode(',', ini_get('disable_functions'));
	$exec_allowed = !in_array('exec', $disabled);
	$del_list = "";
	foreach ($to_clean as $filedir) {
		$complete_filedir = Froxlor::getInstallDir() . $filedir;
		if (file_exists($complete_filedir)) {
			if ($exec_allowed) {
				FileDir::safe_exec("rm -rf " . escapeshellarg($complete_filedir));
			} else {
				$del_list .= "rm -rf " . escapeshellarg($complete_filedir) . PHP_EOL;
			}
		}
	}
	if ($exec_allowed) {
		Update::lastStepStatus(0);
	} else {
		if (empty($del_list)) {
			// none of the files existed
			Update::lastStepStatus(0);
		} else {
			Update::lastStepStatus(1, 'manual commands needed', 'Please run the following commands manually:<br><pre>' . $del_list . '</pre>');
		}
	}

	Update::showUpdateStep("Adding new settings");
	$panel_settings_mode = isset($_POST['panel_settings_mode']) ? (int) $_POST['panel_settings_mode'] : 0;
	Settings::AddNew("panel.settings_mode", $panel_settings_mode);
	$system_distribution = isset($_POST['system_distribution']) ? $_POST['system_distribution'] : '';
	Settings::AddNew("system.distribution", $system_distribution);
	Settings::AddNew("system.update_channel", 'stable');
	Settings::AddNew("system.updatecheck_data", '');
	Settings::AddNew("system.update_notify_last", $update_to);
	Settings::AddNew("panel.phpconfigs_hidesubdomains", '1');
	Update::lastStepStatus(0);

	Update::showUpdateStep("Adjusting existing settings");
	Settings::Set('system.passwordcryptfunc', PASSWORD_DEFAULT);
	// remap default-language
	$lang_map = [
		'Deutsch' => 'de',
		'English' => 'en',
		'Fran&ccedil;ais' => 'fr',
		'Portugu&ecirc;s' => 'pt',
		'Italiano' => 'it',
		'Nederlands' => 'nl',
		'Svenska' => 'se',
		'&#268;esk&aacute; republika' => 'cz'
	];
	// update user default languages
	$upd_adm_stmt = Database::prepare("UPDATE `" . TABLE_PANEL_ADMINS . "` SET `def_language` = :nv WHERE `def_language` = :ov");
	$upd_cus_stmt = Database::prepare("UPDATE `" . TABLE_PANEL_CUSTOMERS . "` SET `def_language` = :nv WHERE `def_language` = :ov");
	foreach ($lang_map as $old_val => $new_val) {
		Database::pexecute($upd_adm_stmt, ['nv' => $new_val, 'ov' => $old_val]);
		Database::pexecute($upd_cus_stmt, ['nv' => $new_val, 'ov' => $old_val]);
	}
	Settings::Set('panel.standardlanguage', $lang_map[Settings::Get('panel_standardlanguage')] ?? 'en');
	Database::query("DELETE FROM `" . TABLE_PANEL_SETTINGS . "` WHERE `settinggroup` = 'system' AND `varname` = 'debug_cron'");
	Database::query("DELETE FROM `" . TABLE_PANEL_SETTINGS . "` WHERE `settinggroup` = 'system' AND `varname` = 'letsencryptcountrycode'");
	Database::query("DELETE FROM `" . TABLE_PANEL_SETTINGS . "` WHERE `settinggroup` = 'system' AND `varname` = 'letsencryptstate'");
	Update::lastStepStatus(0);

	Froxlor::updateToVersion($update_to);
}

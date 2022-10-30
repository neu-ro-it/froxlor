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

const AREA = 'customer';
require __DIR__ . '/lib/init.php';

use Froxlor\Api\Commands\EmailAccounts as EmailAccounts;
use Froxlor\Api\Commands\EmailForwarders as EmailForwarders;
use Froxlor\Api\Commands\Emails as Emails;
use Froxlor\Database\Database;
use Froxlor\FroxlorLogger;
use Froxlor\PhpHelper;
use Froxlor\Settings;
use Froxlor\UI\Collection;
use Froxlor\UI\HTML;
use Froxlor\UI\Listing;
use Froxlor\UI\Panel\UI;
use Froxlor\UI\Request;
use Froxlor\UI\Response;
use Froxlor\Validate\Check;
use Froxlor\CurrentUser;

// redirect if this customer page is hidden via settings
if (Settings::IsInList('panel.customer_hide_options', 'email')) {
	Response::redirectTo('customer_index.php');
}

$id = (int)Request::get('id');

if ($page == 'overview' || $page == 'emails') {
	if ($action == '') {
		$log->logAction(FroxlorLogger::USR_ACTION, LOG_NOTICE, "viewed customer_email::emails");

		try {
			$email_list_data = include_once dirname(__FILE__) . '/lib/tablelisting/customer/tablelisting.emails.php';
			$collection = (new Collection(Emails::class, $userinfo))
				->withPagination($email_list_data['email_list']['columns'], $email_list_data['email_list']['default_sorting']);
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}

		$result_stmt = Database::prepare("
			SELECT COUNT(`id`) as emaildomains
			FROM `" . TABLE_PANEL_DOMAINS . "`
			WHERE `customerid`= :cid AND `isemaildomain` = '1'
		");
		$result2 = Database::pexecute_first($result_stmt, [
			"cid" => $userinfo['customerid']
		]);
		$emaildomains_count = $result2['emaildomains'];

		$actions_links = false;
		if (CurrentUser::canAddResource('emails')) {
			$actions_links = [
				[
					'href' => $linker->getLink(['section' => 'email', 'page' => $page, 'action' => 'add']),
					'label' => lng('emails.emails_add')
				]
			];
		}

		UI::view('user/table.html.twig', [
			'listing' => Listing::format($collection, $email_list_data, 'email_list'),
			'actions_links' => $actions_links,
			'entity_info' => lng('emails.description')
		]);
	} elseif ($action == 'delete' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['email']) && $result['email'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					Emails::getLocal($userinfo, [
						'id' => $id,
						'delete_userfiles' => ($_POST['delete_userfiles'] ?? 0)
					])->delete();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page
				]);
			} else {
				if ($result['popaccountid'] != '0') {
					$show_checkbox = true;
				} else {
					$show_checkbox = false;
				}
				HTML::askYesNoWithCheckbox('email_reallydelete', 'admin_customer_alsoremovemail', $filename, [
					'id' => $id,
					'page' => $page,
					'action' => $action
				], $idna_convert->decode($result['email_full']), $show_checkbox);
			}
		}
	} elseif ($action == 'add') {
		if ($userinfo['emails_used'] < $userinfo['emails'] || $userinfo['emails'] == '-1') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					$json_result = Emails::getLocal($userinfo, $_POST)->add();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				$result = json_decode($json_result, true)['data'];
				Response::redirectTo($filename, [
					'page' => $page,
					'action' => 'edit',
					'id' => $result['id']
				]);
			} else {
				$result_stmt = Database::prepare("SELECT `id`, `domain`, `customerid` FROM `" . TABLE_PANEL_DOMAINS . "`
					WHERE `customerid`= :cid
					AND `isemaildomain`='1'
					ORDER BY `domain_ace` ASC");
				Database::pexecute($result_stmt, [
					"cid" => $userinfo['customerid']
				]);
				$domains = [];
				while ($row = $result_stmt->fetch(PDO::FETCH_ASSOC)) {
					$domains[$row['domain']] = $idna_convert->decode($row['domain']);
				}

				if (count($domains) > 0) {
					$email_add_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_add.php';

					if (Settings::Get('catchall.catchall_enabled') != '1') {
						unset($email_add_data['emails_add']['sections']['section_a']['fields']['iscatchall']);
					}
					UI::view('user/form.html.twig', [
						'formaction' => $linker->getLink(['section' => 'email']),
						'formdata' => $email_add_data['emails_add']
					]);
				} else {
					Response::standardError('emails.noemaildomainaddedyet');
				}
			}
		} else {
			Response::standardError('allresourcesused');
		}
	} elseif ($action == 'edit' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['email']) && $result['email'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				Response::redirectTo($filename, [
					'page' => $page
				]);
			}
			$result['email'] = $idna_convert->decode($result['email']);
			$result['email_full'] = $idna_convert->decode($result['email_full']);
			$result['destination'] = explode(' ', $result['destination']);
			uasort($result['destination'], 'strcasecmp');
			$forwarders = [];
			$forwarders_count = 0;

			foreach ($result['destination'] as $dest_id => $destination) {
				$destination = $idna_convert->decode($destination);
				if ($destination != $result['email_full'] && $destination != '') {
					$forwarders[] = [
						'item' => $destination,
						'href' => $linker->getLink([
							'section' => 'email',
							'page' => 'forwarders',
							'action' => 'delete',
							'id' => $id,
							'forwarderid' => $dest_id
						]),
						'label' => lng('panel.delete'),
						'classes' => 'btn btn-sm btn-danger'
					];
					$forwarders_count++;
				}
				$result['destination'][$dest_id] = $destination;
			}

			$destinations_count = count($result['destination']);
			$result = PhpHelper::htmlentitiesArray($result);

			$email_edit_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_edit.php';

			if (Settings::Get('catchall.catchall_enabled') != '1') {
				unset($email_edit_data['emails_edit']['sections']['section_a']['fields']['mail_catchall']);
			}

			UI::view('user/form.html.twig', [
				'formaction' => $linker->getLink(['section' => 'email']),
				'formdata' => $email_edit_data['emails_edit'],
				'editid' => $id
			]);
		}
	} elseif ($action == 'togglecatchall' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		try {
			Emails::getLocal($userinfo, [
				'id' => $id,
				'iscatchall' => ($result['iscatchall'] == '1' ? 0 : 1)
			])->update();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		Response::redirectTo($filename, [
			'page' => $page,
			'action' => 'edit',
			'id' => $id
		]);
	}
} elseif ($page == 'accounts') {
	if ($action == 'add' && $id != 0) {
		if ($userinfo['email_accounts'] == '-1' || ($userinfo['email_accounts_used'] < $userinfo['email_accounts'])) {
			try {
				$json_result = Emails::getLocal($userinfo, [
					'id' => $id
				])->get();
			} catch (Exception $e) {
				Response::dynamicError($e->getMessage());
			}
			$result = json_decode($json_result, true)['data'];

			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					EmailAccounts::getLocal($userinfo, $_POST)->add();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => 'emails',
					'action' => 'edit',
					'id' => $id
				]);
			} else {
				if (Check::checkMailAccDeletionState($result['email_full'])) {
					Response::standardError([
						'mailaccistobedeleted'
					], $result['email_full']);
				}

				$result['email_full'] = $idna_convert->decode($result['email_full']);
				$result = PhpHelper::htmlentitiesArray($result);
				$quota = Settings::Get('system.mail_quota');

				$account_add_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_addaccount.php';

				UI::view('user/form.html.twig', [
					'formaction' => $linker->getLink(['section' => 'email', 'id' => $id]),
					'formdata' => $account_add_data['emails_addaccount'],
					'actions_links' => [
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink([
								'section' => 'email',
								'page' => 'emails',
								'action' => 'edit',
								'id' => $id
							]),
							'label' => lng('emails.emails_edit'),
							'icon' => 'fa-solid fa-pen'
						],
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink(['section' => 'email', 'page' => 'emails']),
							'label' => lng('menue.email.emails'),
							'icon' => 'fa-solid fa-envelope'
						]
					],
				]);
			}
		} else {
			Response::standardError([
				'allresourcesused',
				'allocatetoomuchquota'
			], $quota);
		}
	} elseif ($action == 'changepw' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['popaccountid']) && $result['popaccountid'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					EmailAccounts::getLocal($userinfo, $_POST)->update();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => 'emails',
					'action' => 'edit',
					'id' => $id
				]);
			} else {
				$result['email_full'] = $idna_convert->decode($result['email_full']);
				$result = PhpHelper::htmlentitiesArray($result);

				$account_changepw_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_accountchangepasswd.php';

				UI::view('user/form.html.twig', [
					'formaction' => $linker->getLink(['section' => 'email', 'id' => $id]),
					'formdata' => $account_changepw_data['emails_accountchangepasswd'],
					'actions_links' => [
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink([
								'section' => 'email',
								'page' => 'emails',
								'action' => 'edit',
								'id' => $id
							]),
							'label' => lng('emails.emails_edit'),
							'icon' => 'fa-solid fa-pen'
						],
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink(['section' => 'email', 'page' => 'emails']),
							'label' => lng('menue.email.emails'),
							'icon' => 'fa-solid fa-envelope'
						]
					],
				]);
			}
		}
	} elseif ($action == 'changequota' && Settings::Get('system.mail_quota_enabled') == '1' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['popaccountid']) && $result['popaccountid'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					EmailAccounts::getLocal($userinfo, $_POST)->update();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => 'emails',
					'action' => 'edit',
					'id' => $id
				]);
			} else {
				$result['email_full'] = $idna_convert->decode($result['email_full']);
				$result = PhpHelper::htmlentitiesArray($result);

				$quota_edit_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_accountchangequota.php';

				UI::view('user/form.html.twig', [
					'formaction' => $linker->getLink(['section' => 'email', 'id' => $id]),
					'formdata' => $quota_edit_data['emails_accountchangequota'],
					'actions_links' => [
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink([
								'section' => 'email',
								'page' => 'emails',
								'action' => 'edit',
								'id' => $id
							]),
							'label' => lng('emails.emails_edit'),
							'icon' => 'fa-solid fa-pen'
						],
						[
							'class' => 'btn-secondary',
							'href' => $linker->getLink(['section' => 'email', 'page' => 'emails']),
							'label' => lng('menue.email.emails'),
							'icon' => 'fa-solid fa-envelope'
						]
					],
				]);
			}
		}
	} elseif ($action == 'delete' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['popaccountid']) && $result['popaccountid'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					EmailAccounts::getLocal($userinfo, $_POST)->delete();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => 'emails',
					'action' => 'edit',
					'id' => $id
				]);
			} else {
				HTML::askYesNoWithCheckbox('email_reallydelete_account', 'admin_customer_alsoremovemail', $filename, [
					'id' => $id,
					'page' => $page,
					'action' => $action
				], $idna_convert->decode($result['email_full']));
			}
		}
	}
} elseif ($page == 'forwarders') {
	if ($action == 'add' && $id != 0) {
		if ($userinfo['email_forwarders_used'] < $userinfo['email_forwarders'] || $userinfo['email_forwarders'] == '-1') {
			try {
				$json_result = Emails::getLocal($userinfo, [
					'id' => $id
				])->get();
			} catch (Exception $e) {
				Response::dynamicError($e->getMessage());
			}
			$result = json_decode($json_result, true)['data'];

			if (isset($result['email']) && $result['email'] != '') {
				if (isset($_POST['send']) && $_POST['send'] == 'send') {
					try {
						EmailForwarders::getLocal($userinfo, $_POST)->add();
					} catch (Exception $e) {
						Response::dynamicError($e->getMessage());
					}
					Response::redirectTo($filename, [
						'page' => 'emails',
						'action' => 'edit',
						'id' => $id
					]);
				} else {
					$result['email_full'] = $idna_convert->decode($result['email_full']);
					$result = PhpHelper::htmlentitiesArray($result);

					$forwarder_add_data = include_once dirname(__FILE__) . '/lib/formfields/customer/email/formfield.emails_addforwarder.php';

					UI::view('user/form.html.twig', [
						'formaction' => $linker->getLink(['section' => 'email', 'id' => $id]),
						'formdata' => $forwarder_add_data['emails_addforwarder'],
						'actions_links' => [
							[
								'class' => 'btn-secondary',
								'href' => $linker->getLink([
									'section' => 'email',
									'page' => 'emails',
									'action' => 'edit',
									'id' => $id
								]),
								'label' => lng('emails.emails_edit'),
								'icon' => 'fa-solid fa-pen'
							],
							[
								'class' => 'btn-secondary',
								'href' => $linker->getLink(['section' => 'email', 'page' => 'emails']),
								'label' => lng('menue.email.emails'),
								'icon' => 'fa-solid fa-envelope'
							]
						],
					]);
				}
			}
		} else {
			Response::standardError('allresourcesused');
		}
	} elseif ($action == 'delete' && $id != 0) {
		try {
			$json_result = Emails::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['destination']) && $result['destination'] != '') {
			if (isset($_POST['forwarderid'])) {
				$forwarderid = intval($_POST['forwarderid']);
			} elseif (isset($_GET['forwarderid'])) {
				$forwarderid = intval($_GET['forwarderid']);
			} else {
				$forwarderid = 0;
			}

			$result['destination'] = explode(' ', $result['destination']);

			if (isset($result['destination'][$forwarderid]) && $result['email'] != $result['destination'][$forwarderid]) {
				$forwarder = $result['destination'][$forwarderid];

				if (isset($_POST['send']) && $_POST['send'] == 'send') {
					try {
						EmailForwarders::getLocal($userinfo, $_POST)->delete();
					} catch (Exception $e) {
						Response::dynamicError($e->getMessage());
					}
					Response::redirectTo($filename, [
						'page' => 'emails',
						'action' => 'edit',
						'id' => $id
					]);
				} else {
					HTML::askYesNo('email_reallydelete_forwarder', $filename, [
						'id' => $id,
						'forwarderid' => $forwarderid,
						'page' => $page,
						'action' => $action
					], $idna_convert->decode($result['email_full']) . ' -> ' . $idna_convert->decode($forwarder));
				}
			}
		}
	}
}

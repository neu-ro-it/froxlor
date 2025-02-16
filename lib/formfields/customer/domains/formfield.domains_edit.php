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

use Froxlor\Domain\Domain;
use Froxlor\Settings;

return [
	'domain_edit' => [
		'title' => lng('domains.subdomain_edit'),
		'image' => 'fa-solid fa-pen',
		'self_overview' => ['section' => 'domains', 'page' => 'domains'],
		'sections' => [
			'section_a' => [
				'title' => lng('domains.subdomain_edit'),
				'image' => 'icons/domain_edit.png',
				'fields' => [
					'domain' => [
						'label' => lng('domains.domainname'),
						'type' => 'label',
						'value' => $result['domain']
					],
					'dns' => [
						'label' => lng('dns.destinationip'),
						'type' => 'itemlist',
						'values' => $domainips
					],
					'alias' => [
						'visible' => $alias_check == '0',
						'label' => lng('domains.aliasdomain'),
						'type' => 'select',
						'select_var' => $domains,
						'selected' => $result['aliasdomain']
					],
					'path' => [
						'label' => lng('panel.path'),
						'desc' => (Settings::Get('panel.pathedit') != 'Dropdown' ? lng('panel.pathDescriptionSubdomain') : null) . (isset($pathSelect['note']) ? '<br />' . $pathSelect['value'] : ''),
						'type' => $pathSelect['type'],
						'select_var' => $pathSelect['value'],
						'selected' => $pathSelect['value']
					],
					'url' => [
						'visible' => Settings::Get('panel.pathedit') == 'Dropdown',
						'label' => lng('panel.urloverridespath'),
						'type' => 'text',
						'value' => $urlvalue
					],
					'redirectcode' => [
						'visible' => Settings::Get('customredirect.enabled') == '1',
						'label' => lng('domains.redirectifpathisurl'),
						'desc' => lng('domains.redirectifpathisurlinfo'),
						'type' => 'select',
						'select_var' => $redirectcode,
						'selected' => $def_code
					],
					'selectserveralias' => [
						'visible' => ($result['parentdomainid'] == '0' && $userinfo['subdomains'] != '0') || $result['parentdomainid'] != '0',
						'label' => lng('admin.selectserveralias'),
						'desc' => lng('admin.selectserveralias_desc'),
						'type' => 'select',
						'select_var' => $serveraliasoptions,
						'selected' => $serveraliasoptions_selected
					],
					'isemaildomain' => [
						'visible' => ($result['subcanemaildomain'] == '1' || $result['subcanemaildomain'] == '2') && $result['parentdomainid'] != '0',
						'label' => 'Emaildomain',
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['isemaildomain']
					],
					'openbasedir_path' => [
						'visible' => $result['openbasedir'] == '1',
						'label' => lng('domain.openbasedirpath'),
						'type' => 'select',
						'select_var' => $openbasedir,
						'selected' => $result['openbasedir_path']
					],
					'phpsettingid' => [
						'visible' => ((int)Settings::Get('system.mod_fcgid') == 1 || (int)Settings::Get('phpfpm.enabled') == 1) && count($phpconfigs) > 0,
						'label' => lng('admin.phpsettings.title'),
						'type' => 'select',
						'select_var' => $phpconfigs,
						'selected' => $result['phpsettingid']
					]
				]
			],
			'section_bssl' => [
				'title' => lng('admin.webserversettings_ssl'),
				'image' => 'icons/domain_edit.png',
				'visible' => Settings::Get('system.use_ssl') == '1' && $ssl_ipsandports && Domain::domainHasSslIpPort($result['id']),
				'fields' => [
					'sslenabled' => [
						'label' => lng('admin.domain_sslenabled'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['ssl_enabled']
					],
					'ssl_redirect' => [
						'label' => lng('domains.ssl_redirect.title'),
						'desc' => lng('domains.ssl_redirect.description') . ($result['temporary_ssl_redirect'] > 1 ? lng('domains.ssl_redirect_temporarilydisabled') : ''),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['ssl_redirect']
					],
					'letsencrypt' => [
						'visible' => Settings::Get('system.leenabled') == '1',
						'label' => lng('customer.letsencrypt.title'),
						'desc' => lng('customer.letsencrypt.description'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['letsencrypt']
					],
					'http2' => [
						'visible' => $ssl_ipsandports && Settings::Get('system.webserver') != 'lighttpd' && Settings::Get('system.http2_support') == '1',
						'label' => lng('admin.domain_http2.title'),
						'desc' => lng('admin.domain_http2.description'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['http2']
					],
					'hsts_maxage' => [
						'label' => lng('admin.domain_hsts_maxage.title'),
						'desc' => lng('admin.domain_hsts_maxage.description'),
						'type' => 'number',
						'min' => 0,
						'max' => 94608000, // 3-years
						'value' => $result['hsts']
					],
					'hsts_sub' => [
						'label' => lng('admin.domain_hsts_incsub.title'),
						'desc' => lng('admin.domain_hsts_incsub.description'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['hsts_sub']
					],
					'hsts_preload' => [
						'label' => lng('admin.domain_hsts_preload.title'),
						'desc' => lng('admin.domain_hsts_preload.description'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => $result['hsts_preload']
					]
				]
			]
		]
	]
];

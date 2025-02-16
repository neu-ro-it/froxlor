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

namespace Froxlor\UI\Callbacks;

use Froxlor\PhpHelper;
use Froxlor\Settings;

class ProgressBar
{
	/**
	 * get progressbar data for used diskspace
	 *
	 * @param array $attributes
	 * @return array
	 */
	public static function diskspace(array $attributes): array
	{
		$infotext = null;
		if (isset($attributes['fields']['webspace_used']) && isset($attributes['fields']['mailspace_used']) && isset($attributes['fields']['dbspace_used'])) {
			$infotext = lng('panel.used') . ':' . PHP_EOL;
			$infotext .= 'web: ' . PhpHelper::sizeReadable($attributes['fields']['webspace_used'] * 1024, null, 'bi') . PHP_EOL;
			$infotext .= 'mail: ' . PhpHelper::sizeReadable($attributes['fields']['mailspace_used'] * 1024, null, 'bi') . PHP_EOL;
			$infotext .= 'mysql: ' . PhpHelper::sizeReadable($attributes['fields']['dbspace_used'] * 1024, null, 'bi');
		}

		return self::pbData('diskspace', $attributes['fields'], 1024, (int)Settings::Get('system.report_webmax'), $infotext);
	}

	/**
	 * do needed calculations
	 */
	private static function pbData(string $field, array $attributes, int $size_factor = 1024, int $report_max = 90, $infotext = null): array
	{
		$percent = 0;
		$style = 'bg-primary';
		$text = PhpHelper::sizeReadable($attributes[$field . '_used'] * $size_factor, null, 'bi') . ' / ' . lng('panel.unlimited');
		if ((int)$attributes[$field] >= 0) {
			if (($attributes[$field] / 100) * $report_max < $attributes[$field . '_used']) {
				$style = 'bg-danger';
			} elseif (($attributes[$field] / 100) * ($report_max - 15) < $attributes[$field . '_used']) {
				$style = 'bg-warning';
			}
			$percent = round(($attributes[$field . '_used'] * 100) / ($attributes[$field] == 0 ? 1 : $attributes[$field]), 0);
			if ($percent > 100) {
				$percent = 100;
			}
			$text = PhpHelper::sizeReadable($attributes[$field . '_used'] * $size_factor, null, 'bi') . ' / ' . PhpHelper::sizeReadable($attributes[$field] * $size_factor, null, 'bi');
		}

		return [
			'macro' => 'progressbar',
			'data' => [
				'percent' => $percent,
				'style' => $style,
				'text' => $text,
				'infotext' => $infotext
			]
		];
	}

	/**
	 * get progressbar data for traffic
	 *
	 * @param array $attributes ['fields']
	 * @return array
	 */
	public static function traffic(array $attributes): array
	{
		return self::pbData('traffic', $attributes['fields'], 1024, (int)Settings::Get('system.report_trafficmax'));
	}
}

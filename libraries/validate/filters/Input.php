<?php

namespace projectorangebox\orange\library\validate\filters;

/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

use projectorangebox\orange\library\validate\Input_filter;

/**
 * Validation Filter
 *
 * @help filter input for human visible characters with optional length
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */
class Input extends Input_filter
{
	public function filter(&$field, string $options = '') : void
	{
		$this->field($field)->human()->length($options);
	}
}

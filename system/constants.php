<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2013
 * @author     Leo Feyer <https://contao.org>
 * @package    System
 * @license    LGPL
 * @filesource
 */


/**
 * -------------------------------------------------------------------------
 * GENERAL CONSTANTS
 * -------------------------------------------------------------------------
 *
 * The URL_SUFFIX will be added to the URI string to simulate the use of
 * static documents (search engine optimization).
 * 
 * If you are using the Environment library to get environment parameters,
 * you can choose whether to ENCODE_AMPERSANDS or not.
 * 
 * If you are rendering widgets with error message, you can choose whether
 * to change the order (default order: error message above input field).
 */
define('URL_SUFFIX', '.html');
define('ENCODE_AMPERSANDS', true);
define('SWITCH_ORDER', true);


/**
 * -------------------------------------------------------------------------
 * CORE AND PLUGIN VERSIONS
 * -------------------------------------------------------------------------
 * 
 * Version numbers are added to style sheets and JavaScript files to make
 * the web browser reload those resources after a Contao update.
 */
define('VERSION', '2.11');
define('BUILD', '12');


/**
 * -------------------------------------------------------------------------
 * INPUT CONSTANTS
 * -------------------------------------------------------------------------
 *
 * These constants can be used with methods of the input library. You can
 * choose whether to ALLOW_HTML and whether to DECODE_ENTITIES.
 * 
 * Usage example:
 *   echo $this->Input->post('input_field', ALLOW_HTML, DECODE_ENTITIES);
 */
define('ALLOW_HTML', true);
define('DECODE_ENTITIES', true);
define('STRICT_MODE', true);


/**
 * -------------------------------------------------------------------------
 * LINK CONSTANTS
 * -------------------------------------------------------------------------
 *
 * These constants can be used with any HTML link. Their primary purpose is
 * to provide an accessible alternative for common operations.
 * 
 * Usage example:
 *   <a href="index.html"<?php echo LINK_NEW_WINDOW_BLUR; ?>>Home</a>
 */
define('LINK_BLUR', ' onclick="this.blur()"');
define('LINK_NEW_WINDOW', ' onclick="return !window.open(this.href)"');
define('LINK_NEW_WINDOW_BLUR', ' onclick="this.blur();return !window.open(this.href)"');

?>
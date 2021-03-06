<?php
/**
 * Fontis eWAY NZ payment gateway
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so you can be sent a copy immediately.
 *
 * @category   Fontis
 * @package    Fontis_EwayNz
 * @copyright  Copyright (c) 2010 Fontis (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_EwayNz_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('fontis/ewaynz/form.phtml');
        parent::_construct();
    }
}

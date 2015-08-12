<?php
/**
 * @category    VMalanka
 * @package     VMalanka_CleanCheckout
 * @author      Vasyl Malanka <vasyl.malanka@yahoo.com>
 */
$installer = $this;
$installer->startSetup();

// Enable 'Check / Money Order' payment method
$config = new Mage_Core_Model_Config();
$config->saveConfig('payment/checkmo/active', '1');

$installer->endSetup();

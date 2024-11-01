<?php
/**
 * Plugin Name: Multi-Carrier ShipEngine Shipping Rates & Address Validation for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/wc-shipengine-shipping/
 * Description: Displays live ShipEgine shipping rates at cart and checkout pages and validates address before allowing to place an order
 * Version: 1.3.14
 * Tested up to: 6.6
 * Requires PHP: 7.3
 * Author: OneTeamSoftware
 * Author URI: http://oneteamsoftware.com/
 * Developer: OneTeamSoftware
 * Developer URI: http://oneteamsoftware.com/
 * Text Domain: wc-shipengine-shipping
 * Domain Path: /languages
 *
 * Copyright: © 2024 FlexRC, Canada.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace OneTeamSoftware\WooCommerce\Shipping;

defined('ABSPATH') || exit;

require_once(__DIR__ . '/includes/autoloader.php');
	
(new Plugin(
		__FILE__, 
		'ShipEngine', 
		sprintf('<div class="notice notice-info inline"><p>%s<br/><li><a href="%s" target="_blank">%s</a><br/><li><a href="%s" target="_blank">%s</a></p></div>', 
			__('Real-time ShipEngine live shipping rates', 'wc-shipengine-shipping'),
			'https://1teamsoftware.com/contact-us/',
			__('Do you have any questions or requests?', 'wc-shipengine-shipping'),
			'https://wordpress.org/plugins/wc-shipengine-shipping/', 
			__('Do you like our plugin and can recommend to others?', 'wc-shipengine-shipping')),
		'1.3.14'
	)
)->register();

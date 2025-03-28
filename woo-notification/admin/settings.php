<?php

/*
Class Name: VI_WNOTIFICATION_F_Admin_Settings
Author: Andy Ha (support@villatheme.com)
Author URI: http://villatheme.com
Copyright 2016-2018 villatheme.com. All rights reserved.
*/
if (!defined('ABSPATH')) {
	exit;
}

class VI_WNOTIFICATION_F_Admin_Settings {

	public static $setting;

	public function __construct() {
		self::$setting = VI_WNOTIFICATION_F_Data::get_instance();
		add_action('admin_init', array($this, 'save_meta_boxes'));
		add_action('wp_ajax_wcn_search_product', array($this, 'search_product'));
		add_action('wp_ajax_wcn_search_cate', array($this, 'search_cate'));
	}

	/**
	 * Search product category ajax
	 */
	public function search_cate() {
		if (!current_user_can('manage_options')) {
			return;
		}

		ob_start();

		$keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';

		if (empty($keyword)) {
			die();
		}
		$categories = get_terms(array(
			'taxonomy' => 'product_cat',
			'orderby'  => 'name',
			'order'    => 'ASC',
			'search'   => $keyword,
			'number'   => 100,
		));
		$items      = array();
		if (count($categories)) {
			foreach ($categories as $category) {
				$item    = array(
					'id'   => $category->term_id,
					'text' => $category->name,
				);
				$items[] = $item;
			}
		}
		wp_send_json($items);
		die;
	}

	/*Ajax Product Search*/
	public function search_product( $x = '', $post_types = array('product') ) {
		if (!current_user_can('manage_options')) {
			return;
		}

		ob_start();

		$keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';

		if (empty($keyword)) {
			die();
		}
		$arg            = array(
			'post_status'    => 'publish',
			'post_type'      => $post_types,
			'posts_per_page' => 50,
			's'              => $keyword,

		);
		$the_query      = new WP_Query($arg);
		$found_products = array();
		if ($the_query->have_posts()) {
			while ($the_query->have_posts()) {
				$the_query->the_post();
				$prd = wc_get_product(get_the_ID());

				if ($prd->has_child() && $prd->is_type('variable')) {
					$product_children = $prd->get_children();
					if (count($product_children)) {
						foreach ($product_children as $product_child) {
							if (woocommerce_version_check()) {
								$product = array(
									'id'   => $product_child,
									'text' => get_the_title($product_child),
								);
							} else {
								$child_wc  = wc_get_product($product_child);
								$get_atts  = $child_wc->get_variation_attributes();
								$attr_name = array_values($get_atts)[0];
								$product   = array(
									'id'   => $product_child,
									'text' => get_the_title() . ' - ' . $attr_name,
								);
							}
							$found_products[] = $product;
						}
					}
				} else {
					$product_id    = get_the_ID();
					$product_title = get_the_title();
					$the_product   = new WC_Product($product_id);
					if (!$the_product->is_in_stock()) {
						$product_title .= ' (out-of-stock)';
					}
					$product          = array(
						'id'   => $product_id,
						'text' => $product_title,
					);
					$found_products[] = $product;
				}
			}
		}
		wp_send_json($found_products);
		die;
	}

	/**
	 * Get files in directory
	 *
	 * @param $dir
	 *
	 * @return array|bool
	 */
	static private function scan_dir( $dir ) {
		$ignored = array('.', '..', '.svn', '.htaccess', 'test-log.log');

		$files = array();
		foreach (scandir($dir) as $file) {
			if (in_array($file, $ignored)) {
				continue;
			}
			$files[$file] = filemtime($dir . '/' . $file);
		}
		arsort($files);
		$files = array_keys($files);

		return ($files) ? $files : false;
	}

	private function stripslashes_deep( $value ) {
		$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);

		return $value;
	}

	/**
	 * Save post meta
	 *
	 * @param $post
	 *
	 * @return bool
	 */
	public function save_meta_boxes() {
		global $woocommerce_notification_settings;
		if (!isset($_POST['_wnotification_nonce']) || !isset($_POST['wnotification_params'])) {
			return false;
		}
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wnotification_nonce'])), 'wnotification_save_email_settings')) {
			return false;
		}
		if (!current_user_can('manage_options')) {
			return false;
		}

		update_option('_woocommerce_notification_prefix', substr(md5(gmdate("YmdHis")), 0, 10));

		$data                    = wc_clean($_POST['wnotification_params']);
		$data['enable']          = $data['enable'] ?? 0;
		$data['enable_mobile']   = $data['enable_mobile'] ?? 0;
		$data['non_ajax']        = $data['non_ajax'] ?? 0;
		$data['show_close_icon'] = $data['show_close_icon'] ?? 0;
		/* Because name contain slashes, need to handle separately*/
		$args = array(
			'virtual_name',
			'conditional_tags',
			'virtual_city',
			'custom_css',
			'virtual_country',
		);
		if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
			$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

			if (count($languages)) {
				foreach ($languages as $key => $language) {
					if ($language['active']) {
						continue;
					}
					$args[] = 'virtual_name_' . $key;
					$args[] = 'virtual_city_' . $key;
					$args[] = 'virtual_country_' . $key;
				}
			}
		} /*Polylang*/ elseif (class_exists('Polylang')) {
			$languages = pll_languages_list();

			foreach ($languages as $language) {
				$default_lang = pll_default_language('slug');

				if ($language == $default_lang) {
					continue;
				}
				$args[] = 'virtual_name_' . $language;
				$args[] = 'virtual_city_' . $language;
				$args[] = 'virtual_country_' . $language;
			}
		}

		foreach ($args as $field) {
			$data[$field] = isset($_POST['wnotification_params'][$field]) ? $this->stripslashes_deep($_POST['wnotification_params'][$field]) : "";
		}

		update_option('wnotification_params', $data);
		if (is_plugin_active('wp-fastest-cache/wpFastestCache.php')) {
			$cache = new WpFastestCache();
			$cache->deleteCache(true);
		}
		$woocommerce_notification_settings = $data;
	}

	/**
	 * Set Nonce
	 *
	 * @return string
	 */
	protected static function set_nonce() {
		return wp_nonce_field('wnotification_save_email_settings', '_wnotification_nonce');
	}

	/**
	 * Set field in meta box
	 *
	 * @param      $field
	 * @param bool $multi
	 *
	 * @return string
	 */
	protected static function set_field( $field, $multi = false ) {
		if ($field) {
			if ($multi) {
				return 'wnotification_params[' . $field . '][]';
			} else {
				return 'wnotification_params[' . $field . ']';
			}
		} else {
			return '';
		}
	}

	/**
	 * Get Post Meta
	 *
	 * @param $field
	 *
	 * @return bool
	 */
	public static function get_field( $field, $default = '' ) {
		return self::$setting->get_params($field, $default);
	}

	/**
	 * Get list shortcode
	 *
	 * @return array
	 */
	public static function page_callback() {
		self::$setting = VI_WNOTIFICATION_F_Data::get_instance(true);
		?>
        <div class="wrap woo-notification">
            <h2><?php esc_attr_e('Notification for WooCommerce Settings', 'woo-notification') ?></h2>
            <form method="post" action="" class="vi-ui form" id="wn-notification-form">
				<?php echo wp_kses_post(ent2ncr(self::set_nonce())) ?>
                <div class="vi-ui attached tabular menu">
                    <div class="item active" data-tab="general"><a href="#general"><?php esc_html_e('General', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="design"><a href="#design"><?php esc_html_e('Design', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="messages"><a href="#messages"><?php esc_html_e('Messages', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="products"><a href="#products"><?php esc_html_e('Products', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="product-detail"><a href="#product-detail"><?php esc_html_e('Product Detail', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="time"><a href="#time"><?php esc_html_e('Time', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="sound"><a href="#sound"><?php esc_html_e('Sound', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="assign"><a href="#assign"><?php esc_html_e('Assign', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="logs"><a href="#logs"><?php esc_html_e('Report', 'woo-notification') ?></a></div>
                    <div class="item" data-tab="ai-engine"><a href="#ai-engine"><?php esc_html_e('AI Engine', 'woo-notification') ?></a></div>
                </div>
                <!--General-->
                <div class="vi-ui bottom attached tab segment active" data-tab="general">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('enable')) ?>">
									<?php esc_html_e('Enable', 'woo-notification') ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('enable')) ?>"
                                           type="checkbox" <?php checked(self::get_field('enable'), 1) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('enable')) ?>"/>
                                    <label></label>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('enable_mobile')) ?>">
									<?php esc_html_e('Mobile', 'woo-notification') ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('enable_mobile')) ?>"
                                           type="checkbox" <?php checked(self::get_field('enable_mobile'), 1) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('enable_mobile')) ?>"/>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e('Notification will show on mobile and responsive.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!--Products-->
                <div class="vi-ui bottom attached tab segment" data-tab="products">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>

                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Show Products', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <select name="<?php echo esc_attr(self::set_field('archive_page')) ?>"
                                        class="vi-ui fluid dropdown">
                                    <option <?php selected(self::get_field('archive_page'), 0) ?>
                                            value="0"><?php esc_attr_e('Get from Billing', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('archive_page'), 1) ?>
                                            value="1"><?php esc_attr_e('Select Products', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('archive_page'), 2) ?>
                                            value="2"><?php esc_attr_e('Latest Products', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('archive_page'), 3) ?>
                                            value="3"><?php esc_attr_e('Select Categories', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('archive_page'), 4) ?>
                                            value="4"><?php esc_attr_e('Recent Viewed Products', 'woo-notification') ?></option>
                                </select>

                                <p class="description"><?php esc_html_e('You can arrange product order or special product which you want to up-sell.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="get_from_billing vi_hidden">
                            <th scope="row">
                                <label><?php esc_html_e('From order', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php
								$order_statuses = self::get_field('order_statuses', ['wc-completed']);
								$statuses       = wc_get_order_statuses();

								?>
                                <div class="vi-ui field">
                                    <select multiple="multiple"
                                            name="<?php echo esc_attr(self::set_field('order_statuses', true)) ?>"
                                            class="vi-ui fluid dropdown">
										<?php foreach ($statuses as $k => $status) {
											$selected = '';
											if (in_array($k, $order_statuses)) {
												$selected = 'selected="selected"';
											}
											?>
                                            <option <?php echo esc_attr($selected); ?>
                                                    value="<?php echo esc_attr($k) ?>"><?php echo esc_html($status) ?></option>
										<?php } ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Order status', 'woo-notification') ?></p>
                                </div>
                                <div class="field vi-ui">
                                    <div class="vi-ui right labeled input">
                                        <input type="number" min="0"
                                               value="<?php echo esc_attr(self::get_field('order_threshold_num', 30)) ?>"
                                               name="<?php echo esc_attr(self::set_field('order_threshold_num')) ?>"
                                               class="vi-ui fluid right twelve wide"
                                        />
                                        <select name="<?php echo esc_attr(self::set_field('order_threshold_time')) ?>"
                                                class="vi-ui dropdown label">
                                            <option <?php selected(self::get_field('order_threshold_time'), 0) ?>
                                                    value="0"><?php esc_attr_e('Hours', 'woo-notification') ?></option>
                                            <option <?php selected(self::get_field('order_threshold_time', 1), 1) ?>
                                                    value="1"><?php esc_attr_e('Days', 'woo-notification') ?></option>
                                            <option <?php selected(self::get_field('order_threshold_time'), 2) ?>
                                                    value="2"><?php esc_attr_e('Minutes', 'woo-notification') ?></option>
                                        </select>
                                    </div>
                                    <p class="description"><?php esc_html_e('Actual products displayed on the notification will get from the orders placed within this period of time.  ', 'woo-notification') ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top" class="select_only_product hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Select Products', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php
								$products_ach = self::get_field('archive_products', array()); ?>
                                <select multiple="multiple"
                                        name="<?php echo esc_attr(self::set_field('archive_products', true)) ?>"
                                        class="product-search"
                                        placeholder="<?php esc_attr_e('Please select products', 'woo-notification') ?>">
									<?php if (count($products_ach)) {
										$args_p      = array(
											'post_type'      => array('product', 'product_variation'),
											'post_status'    => 'publish',
											'post__in'       => $products_ach,
											'posts_per_page' => 2,
										);
										$the_query_p = new WP_Query($args_p);
										if ($the_query_p->have_posts()) {
											$products_ach = $the_query_p->posts;
											foreach ($products_ach as $product_ach) {
												$data_ach = wc_get_product($product_ach);
												if (woocommerce_version_check()) {
													if ($data_ach->get_type() == 'variation') {
														$name_prd = $data_ach->get_name();
													} else {
														$name_prd = $data_ach->get_title();
													}
													if (!$data_ach->is_in_stock()) {
														$name_prd .= ' (out-of-stock)';
													}
												} else {
													$prd_var_title = $data_ach->post->post_title;
													if ($data_ach->get_type() == 'variation') {
														$prd_var_attr = $data_ach->get_variation_attributes();
														$attr_name1   = array_values($prd_var_attr)[0];
														$name_prd     = $prd_var_title . ' - ' . $attr_name1;
													} else {
														$name_prd = $prd_var_title;
													}
												}
												if ($data_ach) { ?>
                                                    <option selected="selected"
                                                            value="<?php echo esc_attr($data_ach->get_id()); ?>"><?php echo esc_html($name_prd); ?></option>
												<?php }
											}
										}
										// Reset Post Data
										wp_reset_postdata();
									} ?>
                                </select>
                                <p class="description"><?php esc_html_e('You only select 2 products. Please upgrade to unlock unlimited.', 'woo-notification') ?></p>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Product image size', 'woo-notification') ?></label>
                            </th>

                            <td>
								<?php
								$additional_image_sizes = wp_get_additional_image_sizes();
								$default_image_sizes    = array('thumbnail', 'medium', 'medium_large', 'large');
								$image_sizes            = array();

								foreach ($default_image_sizes as $size) {
									$image_sizes[$size] = array(
										'width'  => (int)get_option($size . '_size_w'),
										'height' => (int)get_option($size . '_size_h'),
										'crop'   => (bool)get_option($size . '_crop'),
									);
								}

								if ($additional_image_sizes) {
									$image_sizes = array_merge($image_sizes, $additional_image_sizes);
								}

								$image_sizes['full'] = array();
								?>
                                <select name="<?php echo esc_attr(self::set_field('product_sizes')) ?>"
                                        class="vi-ui fluid dropdown">
									<?php
									$selected_product_sizes = self::get_field('product_sizes');
									$mapping                = [
										'shop_thumbnail' => 'woocommerce_thumbnail',
										'shop_catalog'   => 'woocommerce_gallery_thumbnail',
										'shop_single'    => 'woocommerce_single',
									];
									if (isset($mapping[$selected_product_sizes])) {
										$selected_product_sizes = $mapping[$selected_product_sizes];
									}

									foreach ($image_sizes as $slug => $info) {
										$display_size = !empty($info['width']) ? " - {$info['width']}" : '';
										$display_size .= !empty($info['height']) ? "x{$info['height']}" : '';

										printf("<option value='%s' %s>%s</option>", esc_attr($slug), selected($selected_product_sizes, $slug, false), esc_html($slug . $display_size));
									}
									?>
                                </select>

                                <p class="description"><?php esc_html_e('Image size will get form your WordPress site.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="select-categories hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Select Categories', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php
								$cates = self::get_field('select_categories', array()); ?>
                                <select multiple="multiple"
                                        name="<?php echo esc_attr(self::set_field('select_categories', true)) ?>"
                                        class="category-search"
                                        placeholder="<?php esc_attr_e('Please select category', 'woo-notification') ?>">
									<?php
									if (count($cates)) {
										$categories = get_terms(array(
											'taxonomy' => 'product_cat',
											'include'  => $cates,
										));
										if (count($categories)) {
											foreach ($categories as $category) { ?>
                                                <option selected="selected"
                                                        value="<?php echo esc_attr($category->term_id) ?>"><?php echo esc_html($category->name) ?></option>
												<?php
											}
										}
									} ?>
                                </select>
                                <p class="description"><?php esc_html_e('You only select 2 categories. Please upgrade to unlock unlimited.', 'woo-notification') ?></p>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr valign="top" class="hidden select-categories">
                            <th scope="row">
                                <label><?php esc_html_e('Exclude Products', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php $products = self::get_field('cate_exclude_products', array()); ?>
                                <select multiple="multiple"
                                        name="<?php echo esc_attr(self::set_field('cate_exclude_products', true)) ?>"
                                        class="product-search"
                                        placeholder="<?php esc_attr_e('Please select products', 'woo-notification') ?>">
									<?php if (count($products)) {
										$args_p      = array(
											'post_type'      => array('product'),
											'post_status'    => 'publish',
											'post__in'       => $products,
											'posts_per_page' => -1,
										);
										$the_query_p = new WP_Query($args_p);
										if ($the_query_p->have_posts()) {
											$products = $the_query_p->posts;
											foreach ($products as $product) {
												$data = wc_get_product($product);
												if (woocommerce_version_check()) {
													if ($data->get_type() == 'variation') {
														continue;
													} else {
														$name_prd = $data->get_title();
													}
													if (!$data->is_in_stock()) {
														$name_prd .= ' (out-of-stock)';
													}
												} else {
													$prd_var_title = $data->post->post_title;
													if ($data->get_type() == 'variation') {
														continue;
													} else {
														$name_prd = $prd_var_title;
													}
												}
												if ($data) {
													?>
                                                    <option selected="selected"
                                                            value="<?php echo esc_attr($data->get_id()) ?>"><?php echo esc_html($name_prd) ?></option>
													<?php
												}
											}
										}
										// Reset Post Data
										wp_reset_postdata();
									} ?>
                                </select>

                                <p class="description"><?php esc_html_e('These products will not display on notification.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label
                                        for="<?php echo esc_attr(self::set_field('enable_out_of_stock_product')) ?>"><?php esc_html_e('Show Products', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="field">
                                    <a class="vi-ui button" target="_blank"
                                       href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                    <p class="description"><?php esc_html_e('Products which have this product visibility status will appear on Notification.', 'woo-notification') ?></p>
                                </div>
                                <div class="vi-ui three fields">
                                    <div class="field">
                                        <label
                                                for="<?php echo esc_attr(self::set_field('enable_current_category')) ?>"><?php esc_html_e('Current category', 'woo-notification') ?></label>
                                        <a class="vi-ui button" target="_blank"
                                           href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description"><?php esc_html_e('Display products in the same category with the being displayed product.', 'woo-notification') ?></p>
                                    </div>
                                    <div class="field">
                                        <label
                                                for="<?php echo esc_attr(self::set_field('enable_out_of_stock_product')) ?>"><?php esc_html_e('Out-of-stock products', 'woo-notification') ?></label>
                                        <div class="vi-ui toggle checkbox">
                                            <input id="<?php echo esc_attr(self::set_field('enable_out_of_stock_product')) ?>"
                                                   type="checkbox" <?php checked(self::get_field('enable_out_of_stock_product'), 1) ?>
                                                   tabindex="0" class="vi_hidden" value="1"
                                                   name="<?php echo esc_attr(self::set_field('enable_out_of_stock_product')) ?>"/>
                                            <label></label>
                                        </div>
                                        <p class="description"><?php esc_html_e('Turn on to show out-of-stock products on notifications.', 'woo-notification') ?></p>
                                    </div>
                                    <div class="field">
                                        <label
                                                for="<?php echo esc_attr(self::set_field('product_link')) ?>"><?php esc_html_e('External link', 'woo-notification') ?></label>
                                        <div class="vi-ui toggle checkbox">
                                            <input id="<?php echo esc_attr(self::set_field('product_link')) ?>"
                                                   type="checkbox" <?php checked(self::get_field('product_link'), 1) ?>
                                                   tabindex="0" class="vi_hidden" value="1"
                                                   name="<?php echo esc_attr(self::set_field('product_link')) ?>"/>
                                            <label></label>
                                        </div>
                                        <p class="description"><?php esc_html_e('Working with  External/Affiliate product. Product link is product URL.', 'woo-notification') ?></p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top" class="select_product vi_hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Virtual Time', 'woo-notification') ?></label></th>
                            <td>
                                <div class="vi-ui form">
                                    <div class="two fields">
                                        <div class="field">
                                            <div class="vi-ui fluid right labeled input">
                                                <input type="number"
                                                       name="<?php echo esc_attr(self::set_field('virtual_time')) ?>"
                                                       min="0"
                                                       value="<?php echo esc_attr(self::get_field('virtual_time', '10')) ?>"/>
                                                <label class="vi-ui label"><?php esc_html_e('hours', 'woo-notification') ?></label>
                                            </div>
                                            <p class="description"><?php esc_html_e('Virtual time will randomly get in this threshold.', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field">
                                            <div class="vi-ui toggle checkbox">
                                                <input id="<?php echo esc_attr(self::set_field('change_virtual_time_enable')) ?>"
                                                       type="checkbox" <?php checked(self::get_field('change_virtual_time_enable'), 1) ?>
                                                       tabindex="0" class="vi_hidden" value="1"
                                                       name="<?php echo esc_attr(self::set_field('change_virtual_time_enable')) ?>"/>
                                                <label></label>
                                            </div>
                                            <p class="description"><?php esc_html_e('Enable to change the virtual time matched your site’s timezone.', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!--	Select Categories-->

                        <tr valign="top" class="hidden latest-product-select-categories">
                            <th scope="row">
                                <label><?php esc_html_e('Product limit', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>

                                <p class="description"><?php esc_html_e('Product quantity will be got in list latest products.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="hidden exclude_products">
                            <th scope="row">
                                <label><?php esc_html_e('Exclude Products', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php $products = self::get_field('exclude_products', array()); ?>
                                <select multiple="multiple"
                                        name="<?php echo esc_attr(self::set_field('exclude_products', true)) ?>"
                                        class="product-search"
                                        placeholder="<?php esc_attr_e('Please select products', 'woo-notification') ?>">
									<?php if (count($products)) {
										$args_p      = array(
											'post_type'      => array('product', 'product_variation'),
											'post_status'    => 'publish',
											'post__in'       => $products,
											'posts_per_page' => -1,
										);
										$the_query_p = new WP_Query($args_p);
										if ($the_query_p->have_posts()) {
											$products = $the_query_p->posts;
											foreach ($products as $product) {
												$data = wc_get_product($product);
												if (woocommerce_version_check()) {
													if ($data->get_type() == 'variation') {
														$name_prd = $data->get_name();
													} else {
														$name_prd = $data->get_title();
													}
													if (!$data->is_in_stock()) {
														$name_prd .= ' (out-of-stock)';
													}
												} else {
													$prd_var_title = $data->post->post_title;
													if ($data->get_type() == 'variation') {
														$prd_var_attr = $data->get_variation_attributes();
														$attr_name1   = array_values($prd_var_attr)[0];
														$name_prd     = $prd_var_title . ' - ' . $attr_name1;
													} else {
														$name_prd = $prd_var_title;
													}
												}
												if ($data) {
													?>
                                                    <option selected="selected"
                                                            value="<?php echo esc_attr($data->get_id()) ?>"><?php echo esc_html($name_prd) ?></option>
													<?php
												}
											}
										}
										// Reset Post Data
										wp_reset_postdata();
									} ?>
                                </select>

                                <p class="description"><?php esc_html_e('These products will not show on notification.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="select_product hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Virtual First Name', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php
								$first_names = self::get_field('virtual_name')
								?>
                                <textarea
                                        name="<?php echo esc_attr(self::set_field('virtual_name')) ?>"><?php echo esc_html($first_names) ?></textarea>

                                <p class="description"><?php esc_html_e('Virtual first name what will show on notification. Each first name on a line.', 'woo-notification') ?></p>
								<?php
								/*WPML.org*/
								if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
									$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

									if (count($languages)) {
										foreach ($languages as $key => $language) {
											if ($language['active']) {
												continue;
											}
											$wpml_name = self::get_field('virtual_name_' . $key);
											if (!$wpml_name) {
												$wpml_name = $first_names;
											}
											?>
                                            <h4><?php echo esc_html($language['native_name']) ?></h4>
                                            <textarea
                                                    name="<?php echo esc_attr(self::set_field('virtual_name_' . $key)) ?>"><?php echo esc_html($wpml_name) ?></textarea>
										<?php }
									}
								} /*Polylang*/ elseif (class_exists('Polylang')) {
									$languages = pll_languages_list();

									foreach ($languages as $language) {
										$default_lang = pll_default_language('slug');

										if ($language == $default_lang) {
											continue;
										}
										$wpml_name = self::get_field('virtual_name_' . $language);
										if (!$wpml_name) {
											$wpml_name = $first_names;
										}
										?>
                                        <h4><?php echo esc_html($language) ?></h4>
                                        <textarea
                                                name="<?php echo esc_attr(self::set_field('virtual_name_' . $language)) ?>"><?php echo esc_html($wpml_name) ?></textarea>
										<?php
									}
								}
								?>
                            </td>
                        </tr>
                        <tr valign="top" class="select_product hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Address', 'woo-notification') ?></label></th>
                            <td>
                                <select name="<?php echo esc_attr(self::set_field('country')) ?>"
                                        class="vi-ui fluid dropdown">
                                    <option <?php selected(self::get_field('country'), 0) ?>
                                            value="0"><?php esc_attr_e('Auto Detect', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('country'), 1) ?>
                                            value="1"><?php esc_attr_e('Virtual', 'woo-notification') ?></option>
                                </select>

                                <p class="description"><?php esc_html_e('You can use auto detect address or make virtual address of customer.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="virtual_address hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Virtual City', 'woo-notification') ?></label></th>
                            <td>
								<?php
								$virtual_city = self::get_field('virtual_city');
								?>
                                <textarea
                                        name="<?php echo esc_attr(self::set_field('virtual_city')) ?>"><?php echo esc_attr($virtual_city) ?></textarea>

                                <p class="description"><?php esc_html_e('Virtual city name what will show on notification. Each city name on a line.', 'woo-notification') ?></p>
								<?php
								/*WPML.org*/
								if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
									$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

									if (count($languages)) {
										foreach ($languages as $key => $language) {
											if ($language['active']) {
												continue;
											}
											$wpml_city = self::get_field('virtual_city_' . $key);
											if (!$wpml_city) {
												$wpml_city = $virtual_city;
											}
											?>
                                            <h4><?php echo esc_html($language['native_name']) ?></h4>
                                            <textarea
                                                    name="<?php echo esc_attr(self::set_field('virtual_city_' . $key)) ?>"><?php echo esc_html($wpml_city) ?></textarea>
										<?php }
									}
								} /*Polylang*/ elseif (class_exists('Polylang')) {
									$languages = pll_languages_list();

									foreach ($languages as $language) {
										$default_lang = pll_default_language('slug');

										if ($language == $default_lang) {
											continue;
										}

										$wpml_city = self::get_field('virtual_city_' . $language);
										if (!$wpml_city) {
											$wpml_city = $virtual_city;
										}
										?>
                                        <h4><?php echo esc_html($language) ?></h4>
                                        <textarea
                                                name="<?php echo esc_attr(self::set_field('virtual_city_' . $language)) ?>"><?php echo esc_html($wpml_city) ?></textarea>
										<?php
									}
								} ?>
                            </td>
                        </tr>
                        <tr valign="top" class="virtual_address hidden">
                            <th scope="row">
                                <label><?php esc_html_e('Virtual Country', 'woo-notification') ?></label></th>
                            <td>
								<?php $virtual_country = self::get_field('virtual_country') ?>
                                <input type="text" name="<?php echo esc_attr(self::set_field('virtual_country')) ?>"
                                       value="<?php echo esc_attr($virtual_country) ?>"/>

                                <p class="description"><?php esc_html_e('Virtual country name what will show on notification.', 'woo-notification') ?></p>
								<?php /*WPML.org*/
								if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
									$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

									if (count($languages)) {
										foreach ($languages as $key => $language) {
											if ($language['active']) {
												continue;
											}
											$wpml_country = self::get_field('virtual_country_' . $key);
											if (!$wpml_country) {
												$wpml_country = $virtual_country;
											}
											?>
                                            <label><?php echo esc_html($language['native_name']) ?></label>
                                            <input type="text"
                                                   name="<?php echo esc_attr(self::set_field('virtual_country_' . $key)) ?>"
                                                   value="<?php echo esc_attr($wpml_country) ?>"/>
										<?php }
									}
								} elseif (class_exists('Polylang')) {
									$languages = pll_languages_list();

									foreach ($languages as $language) {
										$default_lang = pll_default_language('slug');

										if ($language == $default_lang) {
											continue;
										}
										$wpml_country = self::get_field('virtual_country_' . $language);
										if (!$wpml_country) {
											$wpml_country = $virtual_country;
										}
										?>
                                        <h4><?php echo esc_html($language) ?></h4>
                                        <input type="text"
                                               name="<?php echo esc_attr(self::set_field('virtual_country_' . $language)) ?>"
                                               value="<?php echo esc_attr($wpml_country) ?>"/>
										<?php
									}
								} ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('non_ajax')) ?>"><?php esc_html_e('Non Ajax', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('non_ajax')) ?>"
                                           type="checkbox" <?php checked(self::get_field('non_ajax'), 1) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('non_ajax')) ?>"/>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e('Load popup will not use ajax. Your site will be load faster. It creates cache. It is not working with Get product from Billing feature and options of Product detail tab.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Product detail !-->
                <div class="vi-ui bottom attached tab segment" data-tab="product-detail">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('enable_single_product')) ?>">
									<?php esc_html_e('Run single product', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('enable_single_product')) ?>"
                                           type="checkbox" <?php checked(self::get_field('enable_single_product'), 1) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('enable_single_product')) ?>"/>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e('Notification will only display current product in product detail page that they are viewing.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label
                                        for="<?php echo esc_attr(self::set_field('notification_product_show_type')) ?>"><?php esc_html_e('Notification show', 'woo-notification') ?></label>
                            </th>
                            <td>

                                <select name="<?php echo esc_attr(self::set_field('notification_product_show_type')) ?>"
                                        class="vi-ui fluid dropdown">
                                    <option <?php selected(self::get_field('notification_product_show_type', 0), '0') ?>
                                            value="0"><?php echo esc_html__('Current product', 'woo-notification') ?></option>
                                    <option <?php selected(self::get_field('notification_product_show_type')) ?>
                                            value="1"><?php echo esc_html__('Products in the same category', 'woo-notification') ?></option>
                                </select>

                                <p class="description"><?php esc_html_e('In product single page, Notification can only display current product or other products in the same category.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top" class="only_current_product hidden">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('show_variation')) ?>"><?php esc_html_e('Show variation', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('show_variation')) ?>"
                                           type="checkbox" <?php checked(self::get_field('show_variation'), 1) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('show_variation')) ?>"/>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e('Show variation instead of product variable.', 'woo-notification') ?></p>
                            </td>
                        </tr>

                        </tbody>
                    </table>
                </div>
                <!-- Design !-->
                <div class="vi-ui bottom attached tab segment" data-tab="design">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Templates', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="wn-slider-wrapper">
                                    <div class="wn-slider-slides">
										<?php
										$b_images = woocommerce_notification_background_images();
										foreach ($b_images as $k => $b_image) {
											$value = $k;
											if ('none' === $k) {
												$value = 0;
											}
											?>
                                            <div class="wn-slider-item">
                                                <input id="<?php echo esc_attr('background_image_' . $k) ?>"
                                                       type="radio" <?php echo checked(self::get_field('background_image', 0), $value) ?>
                                                       class="vi_hidden input"
                                                       value="<?php echo esc_attr($value) ?>"
                                                       name="<?php echo esc_attr(self::set_field('background_image')) ?>"/>
                                                <label class="vi-ui center aligned wn-slider-item__info" for="<?php echo esc_attr('background_image_' . $k) ?>">
                                                    <div class="wn-slider-item__info-bg">
                                                        <img src="<?php echo esc_url($b_image) ?>"/>
                                                    </div>
                                                    <div class="wn-slider-item__info-name">
                                                        <span>
                                                            <?php echo esc_html(ucwords(str_replace('_', ' ', $k))) ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
											<?php
										}
										?>
                                    </div>
                                </div>
                                <div class="wn-all-templates">
                                    <div class="wn-view-all-tmpl">
										<?php esc_html_e('See all', 'woo-notification') ?>
                                    </div>
                                    <div class="vi-ui modal wn-all-tmpl-modal">
                                        <span class="close wn-close-all-tmpl-modal"></span>
                                        <div class="header"><?php esc_html_e('All Templates', 'woo-notification') ?></div>
                                        <div class="content">
                                            <div class="vi-ui three column grid">
                                                <div class="row">
													<?php
													$b_images = woocommerce_notification_background_images();
													foreach ($b_images as $k => $b_image) {
														$value = $k;
														if ('none' === $k) {
															$value = '0';
														}
														?>
                                                        <div class="wn-slider-item column <?php echo self::get_field('background_image') === $value ? 'active' : '' ?>">
                                                            <label class="vi-ui center aligned wn-slider-item__info" for="<?php echo esc_attr('background_image_' . $k) ?>">
                                                                <div class="wn-slider-item__info-bg">
                                                                    <img src="<?php echo esc_url($b_image) ?>"/>
                                                                </div>
                                                                <div class="wn-slider-item__info-name">
                                                                    <span>
                                                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $k))) ?>
                                                                    </span>
                                                                </div>
                                                            </label>
                                                        </div>
														<?php
													}
													?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Position & effect', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="vi-ui form">
                                    <div class="three fields">
                                        <div class="fluid field">
                                            <select name="<?php echo esc_attr(self::set_field('position')) ?>"
                                                    class="vi-ui fluid dropdown">
                                                <option <?php selected(self::get_field('position', 0), 0) ?>
                                                        value="0"><?php esc_attr_e('Bottom left', 'woo-notification') ?></option>
                                                <option <?php selected(self::get_field('position'), 1) ?>
                                                        value="1"><?php esc_attr_e('Bottom right', 'woo-notification') ?></option>
                                                <option <?php selected(self::get_field('position'), 2) ?>
                                                        value="2"><?php esc_attr_e('Top left', 'woo-notification') ?></option>
                                                <option <?php selected(self::get_field('position'), 3) ?>
                                                        value="3"><?php esc_attr_e('Top right', 'woo-notification') ?></option>
                                            </select>
                                        </div>
                                        <div class="fluid field">
                                            <select name="<?php echo esc_attr(self::set_field('message_display_effect')) ?>"
                                                    class="vi-ui fluid dropdown"
                                                    id="<?php echo esc_attr(self::set_field('message_display_effect')) ?>">
                                                <optgroup label="Bouncing Entrances">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'bounceIn') ?>
                                                            value="bounceIn"><?php esc_attr_e('bounceIn', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'bounceInDown') ?>
                                                            value="bounceInDown"><?php esc_attr_e('bounceInDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'bounceInLeft') ?>
                                                            value="bounceInLeft"><?php esc_attr_e('bounceInLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'bounceInRight') ?>
                                                            value="bounceInRight"><?php esc_attr_e('bounceInRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'bounceInUp') ?>
                                                            value="bounceInUp"><?php esc_attr_e('bounceInUp', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Fading Entrances">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fade-in') ?>
                                                            value="fade-in"><?php esc_attr_e('fadeIn', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInDown') ?>
                                                            value="fadeInDown"><?php esc_attr_e('fadeInDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInDownBig') ?>
                                                            value="fadeInDownBig"><?php esc_attr_e('fadeInDownBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInLeft') ?>
                                                            value="fadeInLeft"><?php esc_attr_e('fadeInLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInLeftBig') ?>
                                                            value="fadeInLeftBig"><?php esc_attr_e('fadeInLeftBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInRight') ?>
                                                            value="fadeInRight"><?php esc_attr_e('fadeInRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInRightBig') ?>
                                                            value="fadeInRightBig"><?php esc_attr_e('fadeInRightBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInUp') ?>
                                                            value="fadeInUp"><?php esc_attr_e('fadeInUp', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'fadeInUpBig') ?>
                                                            value="fadeInUpBig"><?php esc_attr_e('fadeInUpBig', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Flippers">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'flipInX') ?>
                                                            value="flipInX"><?php esc_attr_e('flipInX', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'flipInY') ?>
                                                            value="flipInY"><?php esc_attr_e('flipInY', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Lightspeed">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'lightSpeedIn') ?>
                                                            value="lightSpeedIn"><?php esc_attr_e('lightSpeedIn', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Rotating Entrances">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rotateIn') ?>
                                                            value="rotateIn"><?php esc_attr_e('rotateIn', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rotateInDownLeft') ?>
                                                            value="rotateInDownLeft"><?php esc_attr_e('rotateInDownLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rotateInDownRight') ?>
                                                            value="rotateInDownRight"><?php esc_attr_e('rotateInDownRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rotateInUpLeft') ?>
                                                            value="rotateInUpLeft"><?php esc_attr_e('rotateInUpLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rotateInUpRight') ?>
                                                            value="rotateInUpRight"><?php esc_attr_e('rotateInUpRight', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Sliding Entrances">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'slideInUp') ?>
                                                            value="slideInUp"><?php esc_attr_e('slideInUp', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'slideInDown') ?>
                                                            value="slideInDown"><?php esc_attr_e('slideInDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'slideInLeft') ?>
                                                            value="slideInLeft"><?php esc_attr_e('slideInLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'slideInRight') ?>
                                                            value="slideInRight"><?php esc_attr_e('slideInRight', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Zoom Entrances">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'zoomIn') ?>
                                                            value="zoomIn"><?php esc_attr_e('zoomIn', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'zoomInDown') ?>
                                                            value="zoomInDown"><?php esc_attr_e('zoomInDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'zoomInLeft') ?>
                                                            value="zoomInLeft"><?php esc_attr_e('zoomInLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'zoomInRight') ?>
                                                            value="zoomInRight"><?php esc_attr_e('zoomInRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_display_effect'), 'zoomInUp') ?>
                                                            value="zoomInUp"><?php esc_attr_e('zoomInUp', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Special">
                                                    <option <?php selected(self::get_field('message_display_effect'), 'rollIn') ?>
                                                            value="rollIn"><?php esc_attr_e('rollIn', 'woo-notification') ?></option>
                                                </optgroup>
                                            </select>
                                            <p class="description"><?php esc_html_e('Show notification effect', 'woo-notification') ?></p>
                                        </div>
                                        <div class="fluid field">
                                            <select name="<?php echo esc_attr(self::set_field('message_hidden_effect')) ?>"
                                                    class="vi-ui fluid dropdown"
                                                    id="<?php echo esc_attr(self::set_field('message_hidden_effect')) ?>">
                                                <optgroup label="Bouncing Exits">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'bounceOut') ?>
                                                            value="bounceOut"><?php esc_attr_e('bounceOut', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'bounceOutDown') ?>
                                                            value="bounceOutDown"><?php esc_attr_e('bounceOutDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'bounceOutLeft') ?>
                                                            value="bounceOutLeft"><?php esc_attr_e('bounceOutLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'bounceOutRight') ?>
                                                            value="bounceOutRight"><?php esc_attr_e('bounceOutRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'bounceOutUp') ?>
                                                            value="bounceOutUp"><?php esc_attr_e('bounceOutUp', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Fading Exits">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fade-out') ?>
                                                            value="fade-out"><?php esc_attr_e('fadeOut', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutDown') ?>
                                                            value="fadeOutDown"><?php esc_attr_e('fadeOutDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutDownBig') ?>
                                                            value="fadeOutDownBig"><?php esc_attr_e('fadeOutDownBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutLeft') ?>
                                                            value="fadeOutLeft"><?php esc_attr_e('fadeOutLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutLeftBig') ?>
                                                            value="fadeOutLeftBig"><?php esc_attr_e('fadeOutLeftBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutRight') ?>
                                                            value="fadeOutRight"><?php esc_attr_e('fadeOutRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutRightBig') ?>
                                                            value="fadeOutRightBig"><?php esc_attr_e('fadeOutRightBig', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutUp') ?>
                                                            value="fadeOutUp"><?php esc_attr_e('fadeOutUp', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'fadeOutUpBig') ?>
                                                            value="fadeOutUpBig"><?php esc_attr_e('fadeOutUpBig', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Flippers">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'flipOutX') ?>
                                                            value="flipOutX"><?php esc_attr_e('flipOutX', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'flipOutY') ?>
                                                            value="flipOutY"><?php esc_attr_e('flipOutY', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Lightspeed">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'lightSpeedOut') ?>
                                                            value="lightSpeedOut"><?php esc_attr_e('lightSpeedOut', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Rotating Exits">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rotateOut') ?>
                                                            value="rotateOut"><?php esc_attr_e('rotateOut', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rotateOutDownLeft') ?>
                                                            value="rotateOutDownLeft"><?php esc_attr_e('rotateOutDownLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rotateOutDownRight') ?>
                                                            value="rotateOutDownRight"><?php esc_attr_e('rotateOutDownRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rotateOutUpLeft') ?>
                                                            value="rotateOutUpLeft"><?php esc_attr_e('rotateOutUpLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rotateOutUpRight') ?>
                                                            value="rotateOutUpRight"><?php esc_attr_e('rotateOutUpRight', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Sliding Exits">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'slideOutUp') ?>
                                                            value="slideOutUp"><?php esc_attr_e('slideOutUp', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'slideOutDown') ?>
                                                            value="slideOutDown"><?php esc_attr_e('slideOutDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'slideOutLeft') ?>
                                                            value="slideOutLeft"><?php esc_attr_e('slideOutLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'slideOutRight') ?>
                                                            value="slideOutRight"><?php esc_attr_e('slideOutRight', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Zoom Exits">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'zoomOut') ?>
                                                            value="zoomOut"><?php esc_attr_e('zoomOut', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'zoomOutDown') ?>
                                                            value="zoomOutDown"><?php esc_attr_e('zoomOutDown', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'zoomOutLeft') ?>
                                                            value="zoomOutLeft"><?php esc_attr_e('zoomOutLeft', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'zoomOutRight') ?>
                                                            value="zoomOutRight"><?php esc_attr_e('zoomOutRight', 'woo-notification') ?></option>
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'zoomOutUp') ?>
                                                            value="zoomOutUp"><?php esc_attr_e('zoomOutUp', 'woo-notification') ?></option>
                                                </optgroup>
                                                <optgroup label="Special">
                                                    <option <?php selected(self::get_field('message_hidden_effect'), 'rollOut') ?>
                                                            value="rollOut"><?php esc_attr_e('rollOut', 'woo-notification') ?></option>
                                                </optgroup>
                                            </select>
                                            <p class="description"><?php esc_html_e('Hide notification effect', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('rounded_corner')) ?>">
									<?php esc_html_e('Style', 'woo-notification') ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui form">
                                    <div class="two fields">
                                        <div class="field">
                                            <div class="vi-ui toggle checkbox">
                                                <input id="<?php echo esc_attr(self::set_field('rounded_corner')) ?>"
                                                       type="checkbox" <?php checked(self::get_field('rounded_corner'), 1) ?>
                                                       tabindex="0" class="vi_hidden" value="1"
                                                       name="<?php echo esc_attr(self::set_field('rounded_corner')) ?>"/>
                                                <label></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('Message will be rounded and product image is round instead of square', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field wn-rounded-conner-depending">
                                            <div class="vi-ui fluid right labeled input">
                                                <input type="number"
                                                       name="<?php echo esc_attr(self::set_field('border_radius')) ?>" min="0"
                                                       value="<?php echo esc_attr(self::get_field('border_radius', '9')) ?>"/>
                                                <label class="vi-ui label"><?php esc_html_e('px', 'woo-notification') ?></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('Custom rounded corner', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                    <div class="three fields">
                                        <div class="field">
                                            <div class="vi-ui labeled input">
                                                <div class="vi-ui label">
													<?php esc_html_e('Background', 'woo-notification') ?>
                                                </div>
                                                <input style="background-color: <?php echo esc_attr(self::get_field('background_color', '#ffffff')) ?>"
                                                       data-ele="backgroundcolor" type="text" class="color-picker"
                                                       name="<?php echo esc_attr(self::set_field('background_color')) ?>"
                                                       value="<?php echo esc_attr(self::get_field('background_color', '#ffffff')) ?>"/>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <div class="vi-ui labeled input">
                                                <div class="vi-ui label">
													<?php esc_html_e('Text message', 'woo-notification') ?>
                                                </div>
                                                <input data-ele="textcolor"
                                                       style="background-color: <?php echo esc_attr(self::get_field('text_color', '#000000')) ?>"
                                                       type="text" class="color-picker"
                                                       name="<?php echo esc_attr(self::set_field('text_color')) ?>"
                                                       value="<?php echo esc_attr(self::get_field('text_color', '#000000')) ?>"/>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <div class="vi-ui labeled input">
                                                <div class="vi-ui label">
													<?php esc_html_e('Product name', 'woo-notification') ?>
                                                </div>
                                                <input data-ele="highlight" type="text" class="color-picker"
                                                       name="<?php echo esc_attr(self::set_field('highlight_color')) ?>"
                                                       value="<?php echo esc_attr(self::get_field('highlight_color', '#000000')) ?>"
                                                       style="background-color: <?php echo esc_attr(self::get_field('highlight_color', '#000000')) ?>"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Image', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <div class="vi-ui form">
                                    <div class="three fields">
                                        <div class="field">
                                            <select name="<?php echo esc_attr(self::set_field('image_position')) ?>"
                                                    class="vi-ui fluid dropdown">
                                                <option <?php selected(self::get_field('image_position'), 0) ?>
                                                        value="0"><?php esc_attr_e('Left', 'woo-notification') ?></option>
                                                <option <?php selected(self::get_field('image_position'), 1) ?>
                                                        value="1"><?php esc_attr_e('Right', 'woo-notification') ?></option>
                                            </select>
                                            <p class="description"><?php esc_html_e('Image Position', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field image_border_radius">
                                            <div class="vi-ui fluid right labeled input">
                                                <input type="number"
                                                       name="<?php echo esc_attr(self::set_field('image_border_radius')) ?>" min="0"
                                                       value="<?php echo esc_attr(self::get_field('image_border_radius', '9')) ?>"/>
                                                <label class="vi-ui label"><?php esc_html_e('px', 'woo-notification') ?></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('The custom-rounded corner of the image', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field">
                                            <div class="vi-ui fluid right labeled input">
                                                <input type="number" min="0" max="15"
                                                       name="<?php echo esc_attr(self::set_field('image_padding')) ?>"
                                                       value="<?php echo esc_attr(self::get_field('image_padding', '0')) ?>"/>
                                                <label class="vi-ui label"><?php esc_html_e('px', 'woo-notification') ?></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('Gap between product image and notification\'s border', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                    <div class="two fields">
                                        <div class="field">
                                            <div class="vi-ui toggle checkbox">
                                                <input id="<?php echo esc_attr(self::set_field('image_redirect')) ?>"
                                                       type="checkbox" <?php checked(self::get_field('image_redirect'), 1) ?>
                                                       tabindex="0" class="vi_hidden" value="1"
                                                       name="<?php echo esc_attr(self::set_field('image_redirect')) ?>"/>
                                                <label></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('When click image, you will redirect to product single page.', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field">
                                            <div class="vi-ui toggle checkbox">
                                                <input id="<?php echo esc_attr(self::set_field('image_redirect_target')) ?>"
                                                       type="checkbox" <?php checked(self::get_field('image_redirect_target'), 1) ?>
                                                       tabindex="0" class="vi_hidden" value="1"
                                                       name="<?php echo esc_attr(self::set_field('image_redirect_target')) ?>"/>
                                                <label></label>
                                            </div>
                                            <p class="description"><?php echo esc_html__('When click image, you will be redirected to the product\'s single page on a new tab.', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('show_close_icon')) ?>">
									<?php esc_html_e('Show Close Icon', 'woo-notification') ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php echo esc_attr(self::set_field('show_close_icon')) ?>"
                                           type="checkbox" <?php checked(self::get_field('show_close_icon'), 1) ?>
                                           tabindex="0" class="vi_hidden" value="1"
                                           name="<?php echo esc_attr(self::set_field('show_close_icon')) ?>"/>
                                    <label></label>
                                </div>
                                <div class="vi-ui form show-close-icon">
                                    <div class="two fields">
                                        <div class="field">
                                            <div class="vi-ui fluid right labeled input">
                                                <input type="number"
                                                       name="<?php echo esc_attr(self::set_field('time_close')) ?>" min="0"
                                                       value="<?php echo esc_attr(self::get_field('time_close', '24')) ?>"/>
                                                <label class="vi-ui label"><?php esc_html_e('hour', 'woo-notification') ?></label>
                                            </div>
                                            <p class="description"><?php esc_html_e('Time close', 'woo-notification') ?></p>
                                        </div>
                                        <div class="field">
                                            <input data-ele="close_icon_color"
                                                   style="background-color: <?php echo esc_attr(self::get_field('close_icon_color', '#000000')) ?>"
                                                   type="text" class="color-picker"
                                                   name="<?php echo esc_attr(self::set_field('close_icon_color')) ?>"
                                                   value="<?php echo esc_attr(self::get_field('close_icon_color', '#000000')) ?>"/>
                                            <p class="description"><?php esc_html_e('Close icon color', 'woo-notification') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('custom_css')) ?>">
									<?php esc_html_e('Custom CSS', 'woo-notification') ?>
                                </label>
                            </th>
                            <td>
                                <textarea class=""
                                          name="<?php echo esc_attr(self::set_field('custom_css')) ?>"><?php echo wp_kses_post(self::get_field('custom_css')) ?></textarea>
                            </td>
                        </tr>
                        </tbody>
                    </table>
					<?php
					$class = array();
					switch (self::get_field('position')) {
						case 1:
							$class[] = 'bottom_right';
							break;
						case 2:
							$class[] = 'top_left';
							break;
						case 3:
							$class[] = 'top_right';
							break;
						default:
							$class[] = '';
					}
					$background_image = self::get_field('background_image');
					if ($background_image) {
						$class[] = 'wn-extended';
						$class[] = 'wn-' . $background_image;
					}
					$class[] = 'vi-wn-show';

					if (self::get_field('rounded_corner')) {
						$class[] = 'wn-rounded-corner';
					}
					$class[] = 'wn-product-with-image';
					$class[] = self::get_field('image_position') ? 'img-right' : '';
					?>
                    <div class="<?php echo esc_attr(implode(' ', $class)) ?>"
                         id="message-purchased"
                         data-effect_display="<?php echo esc_attr(self::get_field('message_display_effect')); ?>"
                         data-effect_hidden="<?php echo esc_attr(self::get_field('message_hidden_effect')); ?>">
                        <div class="message-purchase-main">
                            <span class="wn-notification-image-wrapper"><img class="wn-notification-image"
                                                                             src="<?php echo esc_url(VI_WNOTIFICATION_F_IMAGES . 'demo-image.jpg') ?>"></span>
                            <p class="wn-notification-message-container">Joe Doe in London, England purchased a
                                <a href="#">Ninja Silhouette</a>
                                <small>About 9 hours ago</small>
                            </p>
                        </div>
                        <div id="notify-close"></div>
                    </div>
                </div>
                <!-- Time !-->
                <div class="vi-ui bottom attached tab segment" data-tab="time">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('loop')) ?>"><?php esc_html_e('Loop', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Next time display', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Time to show next notification ', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Notification per page', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>

                                <p class="description"><?php esc_html_e('Number of notifications on a page.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label
                                        for="<?php echo esc_attr(self::set_field('initial_delay_random')) ?>"><?php esc_html_e('Initial time random', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Initial time will be random from 0 to current value.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Minimum initial delay time', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Time will be random from Initial delay time min to Initial time.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Initial delay', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('When your site loads, notifications will show after this amount of time', 'woo-notification') ?></p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Display time', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Time your notification display.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Sound !-->
                <div class="vi-ui bottom attached tab segment" data-tab="sound">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('sound_enable')) ?>"><?php esc_html_e('Enable', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Sound', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>

                                <p class="description"><?php echo esc_html__('Please select sound. Notification rings when show.', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Messages !-->
                <div class="vi-ui bottom attached tab segment" data-tab="messages">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('Message purchased', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <table class="vi-ui message-purchased optiontable form-table">
									<?php $messages = self::get_field('message_purchased');
									if (!$messages) {
										$messages = array('Someone in {city}, {country} purchased a {product_with_link} {time_ago}');
									} elseif (!is_array($messages) && $messages) {
										$messages = array($messages);
									}

									if (count($messages)) {
										foreach ($messages as $k => $message) {
											?>
                                            <tr>
                                                <td width="90%">

                                                    <textarea
                                                            name="<?php echo esc_attr(self::set_field('message_purchased', 1)) ?>"><?php echo wp_kses_post(wp_strip_all_tags($message)) ?></textarea>

													<?php
													/*WPML.org*/
													if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
														$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

														if (count($languages)) {
															foreach ($languages as $key => $language) {
																if ($language['active']) {
																	continue;
																}
																$wpml_messages = self::get_field('message_purchased_' . $key);
																if (!$wpml_messages) {
																	$wpml_messages = array('Someone in {city}, {country} purchased a {product_with_link} {time_ago}');
																} elseif (!is_array($wpml_messages) && $wpml_messages) {
																	$wpml_messages = array($wpml_messages);
																}
																?>
                                                                <h4><?php echo esc_html($language['native_name']) ?></h4>
                                                                <textarea
                                                                        name="<?php echo esc_attr(self::set_field('message_purchased_' . $key, 1)) ?>"><?php echo wp_kses_post(isset($wpml_messages[$k]) ? wp_strip_all_tags($wpml_messages[$k]) : $message) ?></textarea>
															<?php }
														}
													} /*Polylang*/ elseif (class_exists('Polylang')) {
														$languages = pll_languages_list();

														foreach ($languages as $language) {
															$default_lang = pll_default_language('slug');

															if ($language == $default_lang) {
																continue;
															}
															$wpml_messages = self::get_field('message_purchased_' . $language);
															if (!$wpml_messages) {
																$wpml_messages = array('Someone in {city}, {country} purchased a {product_with_link} {time_ago}');
															} elseif (!is_array($wpml_messages) && $wpml_messages) {
																$wpml_messages = array($wpml_messages);
															}
															?>
                                                            <h4><?php echo esc_html($language) ?></h4>
                                                            <textarea
                                                                    name="<?php echo esc_attr(self::set_field('message_purchased_' . $language, 1)) ?>"><?php echo wp_kses_post(isset($wpml_messages[$k]) ? wp_strip_all_tags($wpml_messages[$k]) : $message) ?></textarea>
															<?php
														}
													}
													?>

                                                </td>
                                                <td>
                                                    <span class="vi-ui button remove-message red"><?php esc_html_e('Remove', 'woo-notification') ?></span>
                                                </td>
                                            </tr>
										<?php }
									} ?>
                                </table>
                                <p>
                                    <span class="vi-ui button add-message green"><?php esc_html_e('Add New', 'woo-notification') ?></span>
                                </p>
                                <ul class="description" style="list-style: none">
                                    <li>
                                        <span>{first_name}</span>
                                        - <?php esc_html_e('Customer\'s first name', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{city}</span>
                                        - <?php esc_html_e('Customer\'s city', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{state}</span>
                                        - <?php esc_html_e('Customer\'s state', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{country}</span>
                                        - <?php esc_html_e('Customer\'s country', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{product}</span>
                                        - <?php esc_html_e('Product title', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{product_with_link}</span>
                                        - <?php esc_html_e('Product title with link', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{time_ago}</span>
                                        - <?php esc_html_e('Time after purchase', 'woo-notification') ?>
                                    </li>
                                    <li>
                                        <span>{custom}</span>
                                        - <?php esc_html_e('Use custom shortcode', 'woo-notification') ?>
                                    </li>
                                </ul>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('custom_shortcode')) ?>"><?php esc_html_e('Custom', 'woo-notification') ?></label>
                            </th>
                            <td>
								<?php $custom_shortcode = self::get_field('custom_shortcode', esc_attr('{number} people seeing this product right now')); ?>
                                <input id="<?php echo esc_attr(self::set_field('custom_shortcode')) ?>" type="text"
                                       tabindex="0"
                                       value="<?php echo esc_attr($custom_shortcode) ?>"
                                       name="<?php echo esc_attr(self::set_field('custom_shortcode')) ?>"/>

                                <p class="description"><?php esc_html_e('This is {custom} shortcode content.', 'woo-notification') ?></p>
								<?php
								/*WPML.org*/
								if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
									$languages = $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

									if (count($languages)) {
										foreach ($languages as $key => $language) {
											if ($language['active']) {
												continue;
											}
											$wpml_custom_shortcode = self::get_field('custom_shortcode_' . $key);
											if (!$wpml_custom_shortcode) {
												$wpml_custom_shortcode = $custom_shortcode;
											}
											?>
                                            <h4><?php echo esc_html($language['native_name']) ?></h4>
                                            <input id="<?php echo esc_attr(self::set_field('custom_shortcode_' . $key)) ?>"
                                                   type="text"
                                                   tabindex="0"
                                                   value="<?php echo esc_attr($wpml_custom_shortcode) ?>"
                                                   name="<?php echo esc_attr(self::set_field('custom_shortcode_' . $key)) ?>"/>
										<?php }
									}
								} /*Polylang*/ elseif (class_exists('Polylang')) {
									$languages = pll_languages_list();

									foreach ($languages as $language) {
										$default_lang = pll_default_language('slug');

										if ($language == $default_lang) {
											continue;
										}
										$wpml_custom_shortcode = self::get_field('custom_shortcode_' . $language);
										if (!$wpml_custom_shortcode) {
											$wpml_custom_shortcode = $custom_shortcode;
										}
										?>
                                        <h4><?php echo esc_html($language) ?></h4>
                                        <input id="<?php echo esc_attr(self::set_field('custom_shortcode_' . $language)) ?>"
                                               type="text"
                                               tabindex="0"
                                               value="<?php echo esc_attr($wpml_custom_shortcode) ?>"
                                               name="<?php echo esc_attr(self::set_field('custom_shortcode_' . $language)) ?>"/>
										<?php
									}
								}
								?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('min_number')) ?>"><?php esc_html_e('Min Number', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <input id="<?php echo esc_attr(self::set_field('min_number')) ?>" type="number"
                                       tabindex="0"
                                       value="<?php echo esc_attr(self::get_field('min_number', 100)) ?>"
                                       name="<?php echo esc_attr(self::set_field('min_number')) ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('max_number')) ?>"><?php esc_html_e('Max number', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <input id="<?php echo esc_attr(self::set_field('max_number')) ?>" type="number"
                                       tabindex="0"
                                       value="<?php echo esc_attr(self::get_field('max_number', 200)) ?>"
                                       name="<?php echo esc_attr(self::set_field('max_number')) ?>"/>

                                <p class="description"><?php esc_html_e('Number will random from Min number to Max number', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Assign !-->
                <div class="vi-ui bottom attached tab segment" data-tab="assign">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('is_home')) ?>"><?php esc_html_e('Home page', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Turn on is hidden notification on Home page', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('is_checkout')) ?>"><?php esc_html_e('Checkout page', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Turn on is hidden notification on Checkout page', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('is_cart')) ?>"><?php esc_html_e('Cart page', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                <p class="description"><?php esc_html_e('Turn on is hidden notification on Cart page', 'woo-notification') ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
								<?php esc_html_e('Conditional Tags', 'woo-notification') ?>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>

                                <p class="description"><?php esc_html_e('Let you control on which pages Notification will appear using ', 'woo-notification') ?>
                                    <a class="wn-notification-link" href="http://codex.wordpress.org/Conditional_Tags" target="_blank"><?php esc_html_e('WP\'s conditional tags,', 'woo-notification') ?></a>
                                    <a class="wn-notification-link" href="http://developer.woocommerce.com/docs/conditional-tags-in-woocommerce/" target="_blank"><?php esc_html_e('Woo\'s conditional tags', 'woo-notification') ?></a>
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Logs !-->
                <div class="vi-ui bottom attached tab segment" data-tab="logs">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('save_logs')) ?>"><?php esc_html_e('Save Logs', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e('History time', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- AI Engine !-->
                <div class="vi-ui bottom attached tab segment" data-tab="ai-engine">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr(self::set_field('ai_type')) ?>"><?php esc_html_e('AI Type', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Min characters', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Max characters', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Writing style', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Writing tone', 'woo-notification') ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="vi-ui styled fluid accordion ">
                        <div class="title active <?php echo esc_attr('ai_type_gemini'); ?>">
                            <i class="dropdown icon"></i><?php esc_html_e('Gemini', 'woo-notification'); ?>
                        </div>
                        <div class="content active <?php echo esc_attr('ai_type_gemini'); ?>">
                            <table class="form-table vi-ui form">
                                <tbody>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('API key', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('<strong>Follow these steps to get API:</strong>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Go to <a href="https://ai.google.dev/gemini-api">https://ai.google.dev/gemini-api</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Select "Get an API key from Google AI Studio"', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Click "Create API key"', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Then, configure your key. And copy the key and paste it to this field', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('API version', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <select name="<?php echo esc_attr(self::set_field('ai_gemini_version')) ?>"
                                                class="vi-ui fluid dropdown">
                                            <option value="v1"><?php esc_attr_e('V1', 'woo-notification') ?></option>
                                            <option value="v1beta"><?php esc_attr_e('V1 Beta', 'woo-notification') ?></option>
                                        </select>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('API version supported by Gemini. Read more details of each version in <a href="https://ai.google.dev/gemini-api/docs/api-versions">this article</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                    </td>
                                </tr>

                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('API model list', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank"
                                           href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Choose a model. Gemini models are designed for multimodal applications, processing prompts with both text and images to generate text responses. Read more details in <a href="https://ai.google.dev/gemini-api/docs/models/gemini">this article.</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                    </td>
                                </tr>

                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Message purchased" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate messages in the \'Message Purchased\' input field under Messages. Ex:', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo esc_html__('Generate notification sentences that simulate recent purchases, product views, or inquiries, using numbers and locations, without urgency or exclamation marks', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Generate urgency-driven notification sentences, mentioning recent purchases, product views, inquiries, or popular demand, using numbers, locations, and a sense of immediacy', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Virtual First Name" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate customers\' names in the \'Virtual First Name\' input field under Products', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Virtual City" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate cities in the \'Virtual City\' input field under Products', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="title active <?php echo esc_attr('ai_type_openai'); ?>">
                            <i class="dropdown icon"></i><?php esc_html_e('OpenAI', 'woo-notification'); ?>
                        </div>
                        <div class="content active <?php echo esc_attr('ai_type_openai'); ?>">
                            <table class="form-table vi-ui form">
                                <tbody>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('API key', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('<strong>Follow these steps to get API:</strong>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Sign up/Login at <a href="https://platform.openai.com/">OpenAI</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Get an API Key from the <a href="https://platform.openai.com/account/api-keys">API Keys page.</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                            <li>
												<?php
												echo wp_kses_post(sprintf(__('Then, configure your key. And copy the key and paste it to this field', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
												?>
                                            </li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('API model list', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Choose a model. OpenAI models are designed for multimodal applications, processing prompts with both text and images to generate text responses. Read more details in <a href="https://platform.openai.com/docs/models#models-overview">this article.</a>', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                    </td>
                                </tr>

                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Message purchased" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate messages in the \'Message Purchased\' input field under Messages. Ex:', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo wp_kses_post(sprintf(__('Generate notification sentences that simulate recent purchases, product views, or inquiries, using numbers and locations, without urgency or exclamation marks', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo wp_kses_post(sprintf(__('Generate urgency-driven notification sentences, mentioning recent purchases, product views, inquiries, or popular demand, using numbers, locations, and a sense of immediacy', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Virtual First Name" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate customers\' names in the \'Virtual First Name\' input field under Products', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                <tr class="">
                                    <th scope="row">
                                        <label><?php esc_html_e('"Virtual City" prompt', 'woo-notification') ?></label>
                                    </th>
                                    <td>
                                        <a class="vi-ui button" target="_blank" href="https://1.envato.market/djEZj"><?php esc_html_e('Update This Feature', 'woo-notification') ?></a>
                                        <p class="description">
											<?php
											echo wp_kses_post(sprintf(__('Provide your prompt or specific instructions for the AI to generate cities in the \'Virtual City\' input field under Products', 'woo-notification')));// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
											?>
                                        </p>
                                        <ol class="description">
                                            <li><?php echo esc_html__('Shortcode {MIN_CHARACTERS} : is min characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {MAX_CHARACTERS} : is max characters', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_STYLE} : is writing style', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                            <li><?php echo esc_html__('Shortcode {WRITING_TONE} : is writing tone', 'woo-notification');// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment?></li>
                                        </ol>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <p style="position: relative;margin-bottom: 70px; display: inline-block;">
                    <button class="vi-ui button labeled icon primary wn-submit">
                        <i class="send icon"></i> <?php esc_html_e('Save', 'woo-notification') ?>
                    </button>
                </p>
            </form>
			<?php do_action('villatheme_support_woo-notification') ?>
        </div>
	<?php }

} ?>
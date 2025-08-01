<?php

/**
 * Class VI_WNOTIFICATION_F_Frontend_Notify
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WNOTIFICATION_F_Frontend_Notify {

	protected $settings;
	protected $lang;
	protected $background_type;

	public function __construct() {
		$this->background_type = 0;
		$this->settings        = new VI_WNOTIFICATION_F_Data();
		if ( isset( $_COOKIE['woo_notification_close'] ) && sanitize_text_field( wp_unslash( $_COOKIE['woo_notification_close'] ) ) && $this->settings->show_close_icon() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'init_scripts' ) );

		add_action( 'wp_ajax_nopriv_woonotification_get_product', array( $this, 'product_html' ) );
		add_action( 'wp_ajax_woonotification_get_product', array( $this, 'product_html' ) );

		add_action( 'woocommerce_new_order', array( $this, 'update_prefix' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'update_prefix' ) );
		add_action( 'woocommerce_order_status_pending', array( $this, 'update_prefix' ) );

		/*Update Recent visited products*/
		if ( $this->settings->archive_page() == 4 ) {
			add_action( 'template_redirect', array( $this, 'track_product_view' ), 21 );
		}
	}

	/**
	 * Track product views.
	 */
	public function track_product_view() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		if ( is_active_widget( false, false, 'woocommerce_recently_viewed_products', true ) ) {
			return;
		}

		global $post;

		if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ) { // @codingStandardsIgnoreLine.
			$viewed_products = array();
		} else {
			$viewed_products = wp_parse_id_list( (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ); // @codingStandardsIgnoreLine.
		}

		// Unset if already in viewed products list.
		$keys = array_flip( $viewed_products );

		if ( isset( $keys[ $post->ID ] ) ) {
			unset( $viewed_products[ $keys[ $post->ID ] ] );
		}

		$viewed_products[] = $post->ID;

		if ( count( $viewed_products ) > 15 ) {
			array_shift( $viewed_products );
		}
		// Store for session only.
		wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed_products ) );
	}

	public function update_prefix() {
		$archive_page = $this->settings->archive_page();
		if ( ! $archive_page ) {
			update_option( '_woocommerce_notification_prefix', substr( md5( gmdate( "YmdHis" ) ), 0, 10 ) );
		}
	}

	/**
	 * Show HTML on front end
	 */
	public function product_html() {
		$enable = $this->settings->enable();
		if ( $enable ) {
			$products = apply_filters( 'woonotification_get_products', $this->get_product() );
			if ( is_array( $products ) && count( $products ) ) {
				echo wp_json_encode( $products );
				die;
			}
		}

		echo wp_json_encode( array() );
		die;
	}

	/**
	 * @return false|string
	 */
	protected function show_product() {
		$image_position = $this->settings->get_image_position();
		$position       = $this->settings->get_position();

		$class            = array( 'wn-background-template-type-' . $this->background_type );
		$class[]          = $image_position ? 'img-right' : '';
		$background_image = $this->settings->get_background_image();

		switch ( $position ) {
			case  1:
				$class[] = 'bottom_right';
				break;
			case  2:
				$class[] = 'top_left';
				break;
			case  3:
				$class[] = 'top_right';
				break;
		}
		if ( $background_image ) {
			$class[] = 'wn-extended';
			$class[] = 'wn-' . $background_image;
		}

		if ( $this->settings->enable_rtl() ) {
			$class[] = 'wn-rtl';
		}
		if ( $this->settings->rounded_corner() ) {
			$class[] = 'wn-rounded-corner';
		}
		ob_start();

		?>
        <div id="message-purchased" class=" <?php echo esc_attr( implode( ' ', $class ) ) ?>" style="display: none;">

        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get virtual names
	 *
	 * @param int $limit
	 *
	 * @return array|mixed|void
	 */
	public function get_names( $limit = 0 ) {
		$virtual_name = $this->settings->get_virtual_name();

		if ( $virtual_name && is_string( $virtual_name ) ) {
			$virtual_name = explode( "\n", $virtual_name );
			$virtual_name = array_filter( $virtual_name );
			if ( $limit ) {
				if ( count( $virtual_name ) > $limit ) {
					shuffle( $virtual_name );

					return array_slice( $virtual_name, 0, $limit );
				}
			}
		}

		return $virtual_name;
	}

	/**
	 * Get virtual cities
	 *
	 * @param int $limit
	 *
	 * @return array|mixed|void
	 */
	public function get_cities( $limit = 0 ) {
		$city = $this->settings->get_virtual_city();
		if ( $city && is_string( $city ) ) {
			$city = explode( "\n", $city );
			$city = array_filter( $city );
			if ( $limit ) {
				if ( count( $city ) > $limit ) {
					shuffle( $city );

					return array_slice( $city, 0, $limit );
				}
			}
		}

		return $city;
	}

	/**
	 * Get all orders given a Product ID.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return array An array of WC_Order objects.
	 * @global        $wpdb
	 *
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	protected function get_orders_by_product( $product_id ) {
		if ( is_array( $product_id ) ) {
			$product_id = implode( ',', $product_id );
		}
		$order_threshold_num  = $this->settings->get_order_threshold_num();
		$order_threshold_time = $this->settings->get_order_threshold_time();
		$order_statuses       = $this->settings->get_order_statuses();
		if ( $order_threshold_num ) {
			switch ( $order_threshold_time ) {
				case 1:
					$time_type = 'days';
					break;
				case 2:
					$time_type = 'minutes';
					break;
				default:
					$time_type = 'hours';
			}
			$current_time = strtotime( "-" . $order_threshold_num . " " . $time_type );
			$timestamp    = gmdate( 'Y-m-d G:i:s', $current_time );
		}

		global $wpdb;

		$raw = "
        SELECT items.order_id,
          MAX(CASE
              WHEN itemmeta.meta_key = '_product_id' THEN itemmeta.meta_value
           END) AS product_id
          
        FROM {$wpdb->prefix}woocommerce_order_items AS items
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON items.order_item_id = itemmeta.order_item_id
        INNER JOIN {$wpdb->prefix}posts AS post ON post.ID = items.order_id
        
        WHERE items.order_item_type IN('line_item') AND itemmeta.meta_key IN('_product_id','_variation_id') AND post.post_date >= '%s'
        
        GROUP BY items.order_item_id
        
        HAVING product_id IN (%s)";

		$sql     = $wpdb->prepare( $raw, $timestamp, $product_id );
		$results = $wpdb->get_results( $sql, ARRAY_A );
		$return  = array();
		if ( count( $results ) ) {
			foreach ( $results as $result ) {
				$order_id = $result['order_id'];
				$order    = wc_get_order( $order_id );
				if ( $order ) {
					$return[] = $order;
				}
			}
		}

		return $return;
	}
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
	/**
	 * @return array|bool|mixed
	 */
	// phpcs:disable WordPress.DB.SlowDBQuery
	protected function get_product() {
		$enable_single_product          = $this->settings->enable_single_product();
		$notification_product_show_type = $this->settings->get_notification_product_show_type();
		$products                       = array();
		$product_link                   = $this->settings->product_link();
		$product_thumb                  = $this->settings->get_product_sizes();
		$archive_page                   = $this->settings->archive_page();
		$prefix                         = woocommerce_notification_prefix();
		$current_lang                   = '';
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$current_lang = wpml_get_current_language();
			$prefix       .= $current_lang;
		}
		/*Check Single Product page*/
		if ( $enable_single_product && is_product() ) {
			$product_id = get_the_ID();
			if ( ! $product_id ) {
				return;
			}
			$products = get_transient( $prefix . 'wn_product_child' . $product_id );
			if ( is_array( $products ) && count( $products ) ) {
				return $products;
			}
			$products = [];
			$product  = wc_get_product( $product_id );

			/* Only show current product*/
			if ( ! $notification_product_show_type ) {
				/*Show variation products*/
				$enable_variable = $this->settings->show_variation();
				if ( $product->get_type() == 'variable' && $enable_variable ) {
					$temp_p = delete_transient( 'wn_product_child' . $product_id );

					if ( is_array( $temp_p ) && count( $temp_p ) ) {
						return $temp_p;
					} else {
						$temp_p = $product->get_children();

						if ( count( $temp_p ) ) {
							foreach ( $temp_p as $key => $the_product ) {
								$product_variation = wc_get_product( $the_product );

								if ( ! $product_variation || ( ! $product_variation->is_in_stock() && ! $this->settings->enable_out_of_stock_product() ) ) {
									unset( $temp_p[ $key ] );
								} else {
									if ( $product_variation->get_catalog_visibility() == 'hidden' ) {
										continue;
									}

									// do stuff for everything else
									$link      = $product_variation->get_permalink();
									$thumb_alt = '';
									if ( has_post_thumbnail( $product_id ) ) {
										$thumb_alt = get_post_meta( get_post_thumbnail_id( $product_id ), '_wp_attachment_image_alt', true );
										if ( empty( $thumb_alt ) ) {
											$thumb_alt = get_the_title();
										}
									}
									$product_tmp = array(
										'title'     => $product_variation->get_name(),
										'url'       => $link,
										'thumb'     => has_post_thumbnail( $product_id ) ? get_the_post_thumbnail_url( $product_id, $product_thumb ) : '',
										'thumb_alt' => $thumb_alt,

									);

									if ( ! $archive_page ) {
										$orders = $this->get_orders_by_product( $product_id );
										if ( is_array( $orders ) && count( $orders ) ) {
											foreach ( $orders as $order ) {
												if ( is_a( $order, 'WC_Order_Refund' ) ) {
													$order = wc_get_order( $order->get_parent_id() );
												}
												$order_date_created = $order->get_date_created();
												$order_infor        = [
													'time'       => $this->time_substract( $order_date_created->date_i18n( "Y-m-d H:i:s" ) ),
													'time_org'   => $order_date_created->date_i18n( "Y-m-d H:i:s" ),
													'first_name' => base64_encode( ucfirst( $order->get_billing_first_name() ) ),
													'last_name'  => base64_encode( ucfirst( $order->get_billing_last_name() ) ),
													'city'       => base64_encode( ucfirst( $order->get_billing_city() ) ),
													'state'      => base64_encode( ucfirst( $order->get_billing_state() ) ),
													'country'    => base64_encode( ucfirst( WC()->countries->countries[ $order->get_billing_country() ] ?? '' ) ),
												];
												$products[]         = array_merge( $product_tmp, $order_infor );
											}
										}
									} else {
										$products[] = $product_tmp;
									}
								}
							}
						}
					}
				} else {
					if ( $product->is_in_stock() || $this->settings->enable_out_of_stock_product() ) {
						if ( $product->get_catalog_visibility() == 'hidden' ) {
							return false;
						}

						// do stuff for everything else
						$link      = $product->get_permalink();
						$thumb_alt = '';
						if ( has_post_thumbnail( $product_id ) ) {
							$thumb_alt = get_post_meta( get_post_thumbnail_id( $product_id ), '_wp_attachment_image_alt', true );
							if ( empty( $thumb_alt ) ) {
								$thumb_alt = get_the_title();
							}
						}
						$product_tmp = array(
							'title'     => get_the_title(),
							'url'       => $link,
							'thumb'     => has_post_thumbnail( $product_id ) ? get_the_post_thumbnail_url( $product_id, $product_thumb ) : '',
							'thumb_alt' => $thumb_alt,

						);

						if ( ! $archive_page ) {
							$orders = $this->get_orders_by_product( $product_id );
							if ( is_array( $orders ) && count( $orders ) ) {
								foreach ( $orders as $order ) {
									if ( is_a( $order, 'WC_Order_Refund' ) ) {
										$order = wc_get_order( $order->get_parent_id() );
									}
									$order_date_created = $order->get_date_created();
									$order_infor        = [
										'time'       => $this->time_substract( $order_date_created->date_i18n( "Y-m-d H:i:s" ) ),
										'time_org'   => $order_date_created->date_i18n( "Y-m-d H:i:s" ),
										'first_name' => base64_encode( ucfirst( $order->get_billing_first_name() ) ),
										'last_name'  => base64_encode( ucfirst( $order->get_billing_last_name() ) ),
										'city'       => base64_encode( ucfirst( $order->get_billing_city() ) ),
										'state'      => base64_encode( ucfirst( $order->get_billing_state() ) ),
										'country'    => base64_encode( ucfirst( WC()->countries->countries[ $order->get_billing_country() ] ?? '' ) ),
									];
									$products[]         = array_merge( $product_tmp, $order_infor );
								}
							}
						} else {
							$products[] = $product_tmp;
						}
					} else {
						return false;
					}
				}
			} else {
				/* Show products in the same category*/
				$cates = $product->get_category_ids();
				$args  = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'rand',
					'post__not_in'   => array( $product_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
					'tax_query'      => array(
						array(
							'taxonomy'         => 'product_cat',
							'field'            => 'id',
							'terms'            => $cates,
							'include_children' => false,
							'operator'         => 'IN',
						),
					),
					'meta_query'     => array(
						array(
							'key'     => '_stock_status',
							'value'   => 'instock',
							'compare' => '=',
						),
					),
				);
				if ( $this->settings->enable_out_of_stock_product() ) {
					unset( $args['meta_query'] );
				}
				$the_query = new WP_Query( $args );

				if ( $the_query->have_posts() ) {
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						$same_cate_product_id = get_the_ID();
						$same_cate_product    = wc_get_product( $same_cate_product_id );
						if ( $same_cate_product->get_catalog_visibility() == 'hidden' ) {
							continue;
						}
						if ( $same_cate_product->is_type( 'external' ) && $product_link ) {
							// do stuff for simple products
							$link = get_post_meta( $same_cate_product_id, '_product_url', '#' );
							if ( ! $link ) {
								$link = get_the_permalink();
							}
						} else {
							// do stuff for everything else
							$link = get_the_permalink();
						}
						$thumb_alt = '';
						if ( has_post_thumbnail( $same_cate_product_id ) ) {
							$thumb_alt = get_post_meta( get_post_thumbnail_id( $same_cate_product_id ), '_wp_attachment_image_alt', true );
							if ( empty( $thumb_alt ) ) {
								$thumb_alt = get_the_title();
							}
						}
						$product_tmp = array(
							'title'     => get_the_title(),
							'url'       => $link,
							'thumb'     => has_post_thumbnail( $same_cate_product_id ) ? get_the_post_thumbnail_url( $same_cate_product_id, $product_thumb ) : '',
							'thumb_alt' => $thumb_alt,
						);

						$products[] = $product_tmp;
					}
				}

				// Reset Post Data
				wp_reset_postdata();
			}
			if ( is_array( $products ) && count( $products ) ) {
				set_transient( $prefix . 'wn_product_child' . $product_id, $products, 3600 );

				return $products;
			} else {
				return false;
			}
			// Reset Post Data
		}

		/*Get All page*/
		/*Check with Product get from Billing*/
		$limit_product = 2;
		if ( $archive_page > 0 ) {
			$products = get_transient( $prefix );
			if ( is_array( $products ) && count( $products ) ) {
				return $products;
			} else {
				$products = array();
			}
			switch ( $archive_page ) {
				case 1:
					/*Select Products*/
					/*Params from Settings*/
					$archive_products = $this->settings->get_products();
					$archive_products = is_array( $archive_products ) ? $archive_products : array();
					if ( count( array_filter( $archive_products ) ) < 1 ) {
						$args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => '50',
							'orderby'        => 'rand',
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),
						);
					} else {
						$args = array(
							'post_type'      => array( 'product', 'product_variation' ),
							'post_status'    => 'publish',
							'posts_per_page' => '50',
							'orderby'        => 'rand',
							'post__in'       => $archive_products,
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),

						);
					}
					break;
				case 2:
					/*Latest Products*/ /*Params from Settings*/
					$args = array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => $limit_product,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'meta_query'     => array(
							array(
								'key'     => '_stock_status',
								'value'   => 'instock',
								'compare' => '=',
							),
						),
					);

					break;
				case 4:
					$viewed_products = ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ? (array) explode( '|', wc_clean( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) : array(); // @codingStandardsIgnoreLine
					$viewed_products = array_reverse( array_filter( array_map( 'absint', $viewed_products ) ) );

					if ( empty( $viewed_products ) ) {
						$args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => '50',
							'orderby'        => 'rand',
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),

						);
					} else {
						$args = array(
							'posts_per_page' => $limit_product,
							'no_found_rows'  => 1,
							'post_status'    => 'publish',
							'post_type'      => 'product',
							'post__in'       => $viewed_products,
							'orderby'        => 'post__in',
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),
						);
					}

					break;
				default:
					/*Select Categories*/
					$cates = $this->settings->get_categories();
					if ( count( $cates ) ) {
						$categories = get_terms( array(
							'taxonomy' => 'product_cat',
							'include'  => $cates,
						) );

						$categories_checked = array();
						if ( count( $categories ) ) {
							foreach ( $categories as $category ) {
								$categories_checked[] = $category->term_id;
							}
						} else {
							return false;
						}

						/*Params from Settings*/
						$cate_exclude_products = $this->settings->get_cate_exclude_products();

						if ( ! is_array( $cate_exclude_products ) ) {
							$cate_exclude_products = array();
						}

						$args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => $limit_product,
							'orderby'        => 'rand',
							'post__not_in'   => $cate_exclude_products, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
							'tax_query'      => array(
								array(
									'taxonomy'         => 'product_cat',
									'field'            => 'id',
									'terms'            => $categories_checked,
									'include_children' => false,
									'operator'         => 'IN',
								),
							),
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),
						);
					} else {
						$args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => '50',
							'orderby'        => 'rand',
							'meta_query'     => array(
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),
							),

						);
					}
			}
			/*Enable in stock*/
			if ( $this->settings->enable_out_of_stock_product() ) {
				unset( $args['meta_query'] );
			}
			$the_query = new WP_Query( $args );

			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$product_id = get_the_ID();
					$product    = wc_get_product( $product_id );

					if ( ! is_object( $product ) ) {
						continue;
					}

					if ( $product->get_catalog_visibility() == 'hidden' ) {
						continue;
					}
					if ( $product->is_type( 'external' ) && $product_link ) {
						// do stuff for simple products
						$link = get_post_meta( $product_id, '_product_url', '#' );
						if ( ! $link ) {
							$link = get_the_permalink();
						}
					} else {
						// do stuff for everything else
						$link = get_the_permalink();
					}
					$thumb_alt = '';
					if ( has_post_thumbnail( $product_id ) ) {
						$thumb_alt = get_post_meta( get_post_thumbnail_id( $product_id ), '_wp_attachment_image_alt', true );
						if ( empty( $thumb_alt ) ) {
							$thumb_alt = get_the_title();
						}
					}
					$product_tmp = array(
						'title'     => get_the_title(),
						'url'       => $link,
						'thumb'     => has_post_thumbnail() ? get_the_post_thumbnail_url( '', $product_thumb ) : '',
						'thumb_alt' => $thumb_alt,
					);
					if ( ! $product_tmp['thumb'] && $product->is_type( 'variation' ) ) {
						$parent_id = $product->get_parent_id();

						if ( has_post_thumbnail( $parent_id ) ) {
							$thumb_alt = get_post_meta( get_post_thumbnail_id( $parent_id ), '_wp_attachment_image_alt', true );
							if ( empty( $thumb_alt ) ) {
								$thumb_alt = get_the_title();
							}
						}
						if ( $parent_id ) {
							$product_tmp['thumb']     = has_post_thumbnail( $parent_id ) ? get_the_post_thumbnail_url( $parent_id, $product_thumb ) : '';
							$product_tmp['thumb_alt'] = $thumb_alt;
						}
					}
					$products[] = $product_tmp;
				}
			}
			// Reset Post Data
			wp_reset_postdata();
			if ( count( $products ) ) {
				set_transient( $prefix, $products, 3600 );

				return $products;
			} else {
				return false;
			}
		} else {
			/*Get from billing*/
			/*Param*/
			$order_threshold_num  = $this->settings->get_order_threshold_num();
			$order_threshold_time = $this->settings->get_order_threshold_time();
			$exclude_products     = $this->settings->get_exclude_products();
			$order_statuses       = $this->settings->get_order_statuses();
			if ( ! is_array( $exclude_products ) ) {
				$exclude_products = array();
			}
			$current_time = '';
			if ( $order_threshold_num ) {
				switch ( $order_threshold_time ) {
					case 1:
						$time_type = 'days';
						break;
					case 2:
						$time_type = 'minutes';
						break;
					default:
						$time_type = 'hours';
				}
				$current_time = strtotime( "-" . $order_threshold_num . " " . $time_type );
			}
			$args = array(
				'post_type'   => 'shop_order',
				'post_status' => $order_statuses,
				'limit'       => '100',
				'orderby'     => 'date',
				'order'       => 'DESC',
			);
			if ( $current_time ) {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$args['date_created'] = ">$current_time";
				} else {
					$args['date_query'] = [
						[
							'after'     => [
								'year'   => gmdate( 'Y', $current_time ),
								'month'  => gmdate( 'm', $current_time ),
								'day'    => gmdate( 'd', $current_time ),
								'hour'   => gmdate( 'H', $current_time ),
								'minute' => gmdate( 'i', $current_time ),
								'second' => gmdate( 's', $current_time ),
							],
							'inclusive' => true,//(boolean) - For after/before, whether exact value should be matched or not'.
							//	'compare'   => '<=',//(string) - Possible values are '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS' (only in WP >= 3.5), and 'NOT EXISTS' (also only in WP >= 3.5). Default value is '='
							'column'    => 'post_date', //(string) - Column to query against. Default: 'post_date'.
							'relation'  => 'AND', //(string) - OR or AND, how the sub-arrays should be compared. Default: AND.
						],
					];
				}
			}

			/*$orders   = wc_get_orders( $args );
			$my_query = new WP_Query( $args );*/

			$orders = wc_get_orders( $args );

			$products = [];

			if ( ! empty( $orders ) ) {
				foreach ( $orders as $order ) {
					$items = $order->get_items();
					foreach ( $items as $item ) {
						$line_product_id = $item['product_id'];
						if ( in_array( $line_product_id, $exclude_products ) || in_array( $item['variation_id'], $exclude_products ) ) {
							continue;
						}
						if ( $line_product_id ) {
							$line_product_id = apply_filters( 'wpml_object_id', $line_product_id, 'product', false, $current_lang );
							$p_data          = wc_get_product( $line_product_id );

							if ( ! is_object( $p_data ) ) {
								continue;
							}
							if ( ! $p_data->is_in_stock() && ! $this->settings->enable_out_of_stock_product() ) {
								continue;
							}
							if ( $p_data->get_status() != 'publish' ) {
								continue;
							}
							if ( ! empty( $product_visibility ) && ! in_array( $p_data->get_catalog_visibility(), $product_visibility ) ) {
								continue;
							}
							// do stuff for everything else
							$link               = $p_data->get_permalink();
							$line_product_title = get_the_title( $line_product_id );
							if ( ! empty( $item['variation_id'] ) ) {
								$line_variation_id  = apply_filters( 'wpml_object_id', $item['variation_id'], 'product', false, $current_lang );
								$line_product_title = get_the_title( $line_variation_id );
							}
							$product_tmp = [
								'title'      => apply_filters( 'wcn_product_title', $line_product_title, $line_product_id ),
								'url'        => $link,
								'thumb'      => has_post_thumbnail( $line_product_id ) ? get_the_post_thumbnail_url( $line_product_id, $product_thumb ) : '',
								'time'       => $this->time_substract( $order->get_date_created()->date_i18n( "Y-m-d H:i:s" ) ),
								'time_org'   => $order->get_date_created()->date_i18n( "Y-m-d H:i:s" ),
								'first_name' => base64_encode( ucfirst( $order->get_billing_first_name() ) ),
								'last_name'  => base64_encode( ucfirst( $order->get_billing_last_name() ) ),
								'city'       => base64_encode( ucfirst( $order->get_billing_city() ) ),
								'state'      => base64_encode( ucfirst( $order->get_billing_state() ) ),
								'country'    => base64_encode( ucfirst( WC()->countries->countries[ $order->get_billing_country() ] ) ),
							];
							if ( ! $product_tmp['thumb'] && $p_data->is_type( 'variation' ) ) {
								$parent_id = $p_data->get_parent_id();
								if ( $parent_id ) {
									$product_tmp['thumb'] = has_post_thumbnail( $parent_id ) ? get_the_post_thumbnail_url( $parent_id, $product_thumb ) : '';
								}
							}

							$products[] = $product_tmp;
						}
					}
					$products = array_map( "unserialize", array_unique( array_map( "serialize", $products ) ) );
					$products = array_values( $products );
					if ( count( $products ) >= 100 ) {
						break;
					}
				}
			}
			// Reset Post Data
			wp_reset_postdata();
			if ( count( $products ) ) {
				set_transient( $prefix, $products, 3600 );

				return $products;
			} else {
				return false;
			}
		}
	}
	// phpcs:enable WordPress.DB.SlowDBQuery.

	/**
	 * Get time
	 *
	 * @param      $time
	 * @param bool $number
	 * @param bool $calculate
	 *
	 * @return bool|string
	 */
	protected function time_substract( $time, $number = false, $calculate = false ) {
		if ( ! $number ) {
			if ( $time ) {
				$time = strtotime( $time );
			} else {
				return false;
			}
		}

		if ( ! $calculate ) {
			$current_time = current_time( 'timestamp' );
			//			echo "$current_time - $time";
			$time_substract = $current_time - $time;
		} else {
			$time_substract = $time;
		}
		if ( $time_substract > 0 ) {
			/*Check day*/
			$day = $time_substract / ( 24 * 3600 );
			$day = intval( $day );
			if ( $day > 1 ) {
				return $day . ' ' . esc_html__( 'days', 'woo-notification' );
			} elseif ( $day > 0 ) {
				return $day . ' ' . esc_html__( 'day', 'woo-notification' );
			}

			/*Check hour*/
			$hour = $time_substract / ( 3600 );
			$hour = intval( $hour );
			if ( $hour > 1 ) {
				return $hour . ' ' . esc_html__( 'hours', 'woo-notification' );
			} elseif ( $hour > 0 ) {
				return $hour . ' ' . esc_html__( 'hour', 'woo-notification' );
			}

			/*Check min*/
			$min = $time_substract / ( 60 );
			$min = intval( $min );
			if ( $min > 1 ) {
				return $min . ' ' . esc_html__( 'minutes', 'woo-notification' );
			} elseif ( $min > 0 ) {
				return $min . ' ' . esc_html__( 'minute', 'woo-notification' );
			}

			return intval( $time_substract ) . ' ' . esc_html__( 'seconds', 'woo-notification' );
		} else {
			return esc_html__( 'a few seconds', 'woo-notification' );
		}
	}

	/**
	 *
	 * @return mixed
	 */
	protected function get_custom_shortcode() {
		$message_shortcode = $this->settings->get_custom_shortcode();
		$min_number        = $this->settings->get_min_number();
		$max_number        = $this->settings->get_max_number();

		$number  = wp_rand( $min_number, $max_number );
		$message = preg_replace( '/\{number\}/i', $number, $message_shortcode );

		return $message;
	}

	/**Deprecated
	 * check woo-notification.js
	 *
	 * @return string
	 */
	protected function message_purchased() {
		$message_purchased     = $this->settings->get_message_purchased();
		$show_close_icon       = $this->settings->show_close_icon();
		$archive_page          = $this->settings->archive_page();
		$product_link          = $this->settings->product_link();
		$image_redirect        = $this->settings->image_redirect();
		$image_redirect_target = $this->settings->image_redirect_target();
		if ( is_array( $message_purchased ) ) {
			$index             = wp_rand( 0, count( $message_purchased ) - 1 );
			$message_purchased = $message_purchased[ $index ];
		}
		$messsage = '';
		$keys     = array(
			'{first_name}',
			'{last_name}',
			'{city}',
			'{state}',
			'{country}',
			'{product}',
			'{product_with_link}',
			'{time_ago}',
			'{custom}',
		);

		$product = $this->get_product();

		if ( $product ) {
			$product_id = $product['id'];
		} else {
			return false;
		}

		$first_name = trim( $product['first_name'] );
		$last_name  = trim( $product['last_name'] );

		$city    = trim( $product['city'] );
		$state   = trim( $product['state'] );
		$country = trim( $product['country'] );
		$time    = trim( $product['time'] );
		if ( ! $archive_page ) {
			$time = $this->time_substract( $time );
		}

		$_product      = wc_get_product( $product_id );
		$prd_var_title = $_product->post->post_title;
		if ( $_product->get_type() == 'variation' ) {
			$prd_var_attr = $_product->get_variation_attributes();
			$attr_name1   = array_values( $prd_var_attr )[0];
			$product      = $prd_var_title . ' - ' . $attr_name1;
		} else {
			$product = $prd_var_title;
		}

		$product = wp_strip_all_tags( $product );

		if ( $_product->is_type( 'external' ) && $product_link ) {
			// do stuff for simple products
			$link = get_post_meta( $product_id, '_product_url', '#' );
			if ( ! $link ) {
				$link = get_permalink( $product_id );
				$link = wp_nonce_url( $link, 'wocommerce_notification_click', 'link' );
			}
		} else {
			// do stuff for everything else
			$link = get_permalink( $product_id );
			$link = wp_nonce_url( $link, 'wocommerce_notification_click', 'link' );
		}
		ob_start();
		?>
        <a <?php if ( $image_redirect_target ) {
			echo 'target="_blank"';
		} ?> href="<?php echo esc_url( $link ) ?>"><?php echo esc_html( $product ) ?></a>
		<?php
		$product_with_link = ob_get_clean();

		ob_start();
		?>
        <small><?php echo esc_html__( 'About', 'woo-notification' ) . ' ' . esc_html( $time ) . ' ' . esc_html__( 'ago', 'woo-notification' ) ?></small>
		<?php
		$time_ago      = ob_get_clean();
		$product_thumb = $this->settings->get_product_sizes();
		if ( has_post_thumbnail( $product_id ) ) {
			if ( $image_redirect ) {
				$messsage .= '<a ' . ( $image_redirect_target ? 'target="_blank"' : '' ) . ' href="' . esc_url( $link ) . '">';
				$messsage .= get_the_post_thumbnail( $product_id, $product_thumb, [ 'class' => 'wcn-product-image' ] );
				$messsage .= '</a>';
			} else {
				$messsage .= get_the_post_thumbnail( $product_id, $product_thumb, [ 'class' => 'wcn-product-image' ] );
			}
		} elseif ( $_product->get_type() == 'variation' ) {
			$parent_id = $_product->get_parent_id();
			if ( $image_redirect && $parent_id ) {
				$messsage .= '<a ' . ( $image_redirect_target ? 'target="_blank"' : '' ) . ' href="' . esc_url( $link ) . '">';
				$messsage .= get_the_post_thumbnail( $parent_id, $product_thumb, [ 'class' => 'wcn-product-image' ] );
				$messsage .= '</a>';
			} else {
				$messsage .= get_the_post_thumbnail( $parent_id, $product_thumb, [ 'class' => 'wcn-product-image' ] );
			}
		}

		//Get custom shortcode
		$custom_shortcode = $this->get_custom_shortcode();
		$replaced         = array(
			$first_name,
			$last_name,
			$city,
			$state,
			$country,
			$product,
			$product_with_link,
			$time_ago,
			$custom_shortcode,
		);
		$messsage         .= str_replace( $keys, $replaced, '<p>' . wp_strip_all_tags( $message_purchased ) . '</p>' );
		ob_start();
		if ( $show_close_icon ) {
			?>
            <span id="notify-close"></span>
			<?php
		}
		$messsage .= ob_get_clean();

		return $messsage;
	}

	/**
	 * Show HTML code
	 */
	public function wp_footer() {
		echo $this->show_product(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Add Script and Style
	 */
	function init_scripts() {
		$enable = $this->settings->enable();
		/*Hidden in preview widget*/
		if ( is_admin() ) {
			return;
		}
		if ( ! $enable ) {
			return;
		}
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
            if(function_exists('wpml_get_current_language')){
	            $this->lang = wpml_get_current_language();
            }
		} elseif ( class_exists( 'Polylang' ) ) {
			if(function_exists('pll_current_language')){
				$this->lang = pll_current_language( 'slug' );
			}
		} else {
			$this->lang = '';
		}

		$prefix        = woocommerce_notification_prefix();
		$mobile_detect = new VillaTheme_Mobile_Detect();
		$enable_mobile = $this->settings->enable_mobile();
		// Any mobile device (phones or tablets).
		if ( ! $enable_mobile && $mobile_detect->isMobile() ) {
			return;
		}
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
		wp_enqueue_style( 'woo-notification-icons-close', VI_WNOTIFICATION_F_CSS . 'icons-close.css', array(), VI_WNOTIFICATION_F_VERSION );
		if ( WP_DEBUG ) {
			wp_enqueue_style( 'woo-notification', VI_WNOTIFICATION_F_CSS . 'woo-notification.css', array(), VI_WNOTIFICATION_F_VERSION );
			wp_enqueue_script( 'woo-notification', VI_WNOTIFICATION_F_JS . 'woo-notification.js', array( 'jquery' ), VI_WNOTIFICATION_F_VERSION, true );
		} else {
			wp_enqueue_style( 'woo-notification', VI_WNOTIFICATION_F_CSS . 'woo-notification.min.css', array(), VI_WNOTIFICATION_F_VERSION );
			wp_enqueue_script( 'woo-notification', VI_WNOTIFICATION_F_JS . 'woo-notification.min.js', array( 'jquery' ), VI_WNOTIFICATION_F_VERSION, true );
		}
		if ( $this->settings->get_background_image() ) {
			wp_enqueue_style( 'woo-notification-templates', VI_WNOTIFICATION_F_CSS . 'woo-notification-templates.css', array(), VI_WNOTIFICATION_F_VERSION );
		}

		$options_array                  = get_transient( $prefix . '_head' . $this->lang );
		$non_ajax                       = $this->settings->non_ajax();
		$archive                        = $this->settings->archive_page();
		$enable_single_product          = $this->settings->enable_single_product();
		$notification_product_show_type = $this->settings->get_notification_product_show_type();
		if ( ! is_array( $options_array ) || count( $options_array ) < 1 ) {
			$options_array                   = array(
				'str_about'   => esc_html__( 'About', 'woo-notification' ),
				'str_ago'     => esc_html__( 'ago', 'woo-notification' ),
				'str_day'     => esc_html__( 'day', 'woo-notification' ),
				'str_days'    => esc_html__( 'days', 'woo-notification' ),
				'str_hour'    => esc_html__( 'hour', 'woo-notification' ),
				'str_hours'   => esc_html__( 'hours', 'woo-notification' ),
				'str_min'     => esc_html__( 'minute', 'woo-notification' ),
				'str_mins'    => esc_html__( 'minutes', 'woo-notification' ),
				'str_secs'    => esc_html__( 'secs', 'woo-notification' ),
				'str_few_sec' => esc_html__( 'a few seconds', 'woo-notification' ),
				'time_close'  => $this->settings->get_time_close(),
				'show_close'  => $this->settings->show_close_icon(),
			);
			$message_display_effect          = $this->settings->get_display_effect();
			$options_array['display_effect'] = $message_display_effect;

			$message_hidden_effect          = $this->settings->get_hidden_effect();
			$options_array['hidden_effect'] = $message_hidden_effect;

			$target                           = $this->settings->image_redirect_target();
			$options_array['redirect_target'] = $target;

			$image_redirect         = $this->settings->image_redirect();
			$options_array['image'] = $image_redirect;

			$message_purchased = $this->settings->get_message_purchased();
			if ( ! is_array( $message_purchased ) ) {
				$message_purchased = array( $message_purchased );
			}
			$options_array['messages']           = $message_purchased;
			$options_array['message_custom']     = $this->settings->get_custom_shortcode();
			$options_array['message_number_min'] = $this->settings->get_min_number();
			$options_array['message_number_max'] = $this->settings->get_max_number();

			/*Autodetect*/
			$detect                  = $this->settings->country();
			$options_array['detect'] = $detect;
			/*Check get from billing*/
			/*Current products*/

			if ( $archive || ( $notification_product_show_type && is_product() && $enable_single_product ) ) {
				$virtual_time          = $this->settings->get_virtual_time();
				$options_array['time'] = $virtual_time;

				$names = $this->get_names( 50 );
				if ( is_array( $names ) && count( $names ) ) {
					$names                  = array_map( 'base64_encode', $names );
					$options_array['names'] = $names;
				}
				if ( $detect ) {
					$cities = $this->get_cities( 50 );
					if ( is_array( $cities ) && count( $cities ) ) {
						$options_array['cities'] = array_map( 'base64_encode', $cities );
					}
					$options_array['country'] = $this->settings->get_virtual_country();
				}
			} else {
				if ( ! $non_ajax && ! is_product() ) {
					$options_array['ajax_url'] = admin_url( 'admin-ajax.php' );
				}
			}
			set_transient( $prefix . '_head' . $this->lang, $options_array, 86400 );
		}
		if ( $notification_product_show_type && is_product() && $enable_single_product ) {
			$options_array['billing'] = 0;
		} else {
			$options_array['billing'] = 1;
		}
		if ( $archive ) {
			$options_array['billing'] = 0;
		} else {
			$options_array['billing'] = 1;
		}

		/*Process products, address, time */
		/*Load products*/
		if ( $archive || $non_ajax || is_product() ) {
			$products = apply_filters( 'woonotification_get_products', $this->get_product() );
		} else {
			$options_array['ajax_url'] = admin_url( 'admin-ajax.php' );
			$products                  = array();
		}
		if ( is_array( $products ) && count( $products ) ) {
			$options_array['products'] = $products;
		}
		wp_localize_script( 'woo-notification', '_woocommerce_notification_params', $options_array );
		/*Custom*/

		$highlight_color     = $this->settings->get_highlight_color();
		$text_color          = $this->settings->get_text_color();
		$background_color    = $this->settings->get_background_color();
		$image_border_radius = $this->settings->get_image_border_radius();
		$custom_css_setting  = $this->settings->get_custom_css();
		$background_image    = $this->settings->get_background_image();
		$image_padding       = $this->settings->image_padding();
		$close_icon_color    = $this->settings->close_icon_color();
		$custom_css          = '#message-purchased #notify-close:before{color:' . $close_icon_color . ';}';
		$is_rtl              = is_rtl();
		if ( $background_image ) {
			$border_radius         = $this->settings->get_border_radius() . 'px';
			$this->background_type = 2;
			$background_image_url  = woocommerce_notification_background_images( $background_image );

			$custom_css .= "#message-purchased .message-purchase-main::before{
				background-image: url('{$background_image_url}');  
				 border-radius:{$border_radius};
			}";
		} else {
			$custom_css    .= '#message-purchased .message-purchase-main{overflow:hidden}';
			$border_radius = $this->settings->get_border_radius() . 'px';
		}
		if ( $image_padding ) {
			$padding_right = 15 - $image_padding;
			$custom_css    .= "#message-purchased .wn-notification-image-wrapper{padding:{$image_padding}px;}";
			if ( $is_rtl ) {
				$custom_css .= "#message-purchased .wn-notification-message-container{padding-right:{$padding_right}px;}";
			} else {
				$custom_css .= "#message-purchased .wn-notification-message-container{padding-left:{$padding_right}px;}";
			}
			$custom_css .= "#message-purchased .wn-notification-image{border-radius:{$image_border_radius}px;}";
		} else {
			$custom_css .= "#message-purchased .wn-notification-image-wrapper{padding:0;}";
			if ( $is_rtl ) {
				$custom_css .= "#message-purchased .wn-notification-message-container{padding-right:15px;}";
			} else {
				$custom_css .= "#message-purchased .wn-notification-message-container{padding-left:15px;}";
			}
			$custom_css .= "#message-purchased .wn-notification-image{border-radius:{$image_border_radius}px;}";
		}
		$custom_css .= "
                #message-purchased .message-purchase-main{
                        background-color: {$background_color};                       
                        color:{$text_color} !important;
                        border-radius:{$border_radius} ;
                }
                 #message-purchased a, #message-purchased p span{
                        color:{$highlight_color} !important;
                }" . $custom_css_setting;

		wp_add_inline_style( 'woo-notification', $custom_css );
	}

}
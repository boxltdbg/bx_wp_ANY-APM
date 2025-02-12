<?php
/*
Plugin Name: BOX NOW Доставка
Description: Wordpress плъгин от BOX NOW за интеграция на метод на доставка до BOX NOW автомат.
Author: boxnowbulgaria
Text Domain: boxnowbulgaria
Version: 2.1.4
*/

// Cancel order API call file
require_once(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-cancel-order.php');

// Include the box-now-delivery-print-order.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-print-order.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

  // Include custom shipping method file
  include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-shipping-method.php');

  // Include admin page functions
  include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-admin-page.php');

  /**
   * Enqueue scripts and styles for Box Now plugin.
   */
  function box_now_delivery_enqueue_scripts()
  {
    if (is_checkout()) {
      $button_color = esc_attr(get_option('boxnow_button_color', '#84C33F'));
      $button_text = esc_attr(get_option('boxnow_button_text', 'Избери BOX NOW автомат'));
      $checkout_type = get_option('boxnow_checkout_type', 'classic'); // added setting

      wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery.js', array('jquery'), '1.0.0', true);
      wp_enqueue_style('box-now-delivery-css', plugins_url('/css/box-now-delivery.css', __FILE__));

      wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', array(
        'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
        'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
        'buttonColor' => $button_color,
        'buttonText' => $button_text,
        'lockerNotSelectedMessage' => esc_js(get_option("boxnow_locker_not_selected_message", "Моля изберете автомат за да продължите!")),
        'gps_option' => get_option('boxnow_gps_tracking', 'on'),
        'ajax_url' => admin_url('admin-ajax.php'),
        'checkoutType' => $checkout_type // pass checkout type to JS
      ));
    }
  }
  add_action('wp_enqueue_scripts', 'box_now_delivery_enqueue_scripts');

  // Get the selected checkout type from settings
  $checkout_type = get_option('boxnow_checkout_type', 'classic');

  /*
   * CLASSIC CHECKOUT CODE BLOCK (Only executes if checkout_type = 'classic')
   */
  if ($checkout_type === 'classic') {
    // Add a custom field to retrieve the Locker ID from the checkout page
    add_filter('woocommerce_checkout_fields', 'bndp_box_now_delivery_custom_override_checkout_fields');

    /**
     * Add custom field for Locker ID on checkout.
     *
     * @param array $fields Fields on the checkout.
     * @return array $fields Modified fields.
     */
    function bndp_box_now_delivery_custom_override_checkout_fields($fields)
    {
      $fields['billing']['_boxnow_locker_id'] = array(
        'label' => __('Номер на BOX NOW автомат', 'woocommerce'),
        'placeholder' => _x('Номер на BOX NOW автомат', 'placeholder', 'woocommerce'),
        'required' => false,
        'class' => array('boxnow-form-row-hidden', 'boxnow-locker-id-field'),
        'clear' => true
      );
      return $fields;
    }

    /**
     * Hide the locker ID field on the checkout page.
     */
    function bndp_hide_box_now_delivery_locker_id_field()
    {
      if (is_checkout()) {
?>
        <script>
          jQuery(document).ready(function($) {
            $('.boxnow-locker-id-field').hide();
          });
        </script>
    <?php
      }
    }
    add_action('wp_footer', 'bndp_hide_box_now_delivery_locker_id_field');

    /**
     * Update the order meta with field value for classic checkout.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta($order)
    {
      if (!empty($_POST['_boxnow_locker_id'])) {
        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($_POST['_boxnow_locker_id']));
      }
      if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
        $order->add_meta_data('_selected_warehouse', explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')))[0]);
      }
      $order->save();
    }
    add_action('woocommerce_checkout_create_order', 'bndp_box_now_delivery_checkout_field_update_order_meta');
  }

  /*
   * BLOCK-BASED CHECKOUT CODE BLOCK (Only executes if checkout_type = 'block')
   */
  if ($checkout_type === 'block') {
    add_action('wp_ajax_set_boxnow_locker_id', 'set_boxnow_locker_id');
    add_action('wp_ajax_nopriv_set_boxnow_locker_id', 'set_boxnow_locker_id');

    function set_boxnow_locker_id()
    {
      if (!isset($_POST['locker_id'])) {
        wp_send_json_error('No locker ID provided.');
      }

      $locker_id = sanitize_text_field($_POST['locker_id']);

      if (function_exists('WC')) {
        WC()->session->set('_boxnow_locker_id', $locker_id);
        wp_send_json_success('Locker ID stored in session.');
      } else {
        wp_send_json_error('WooCommerce session not available.');
      }
    }

    //Validate locker before order complete
    add_action('wp_ajax_validate_boxnow_locker_id', 'validate_boxnow_locker_id');
    add_action('wp_ajax_nopriv_validate_boxnow_locker_id', 'validate_boxnow_locker_id');

    function validate_boxnow_locker_id()
    {
      $locker_id = WC()->session->get('boxnow_locker_id');
      if (!empty($locker_id)) {
        wp_send_json_success(['locker_id' => $locker_id]);
      } else {
        wp_send_json_error(['locker_id' => null]);
      }
    }

    add_action('woocommerce_cart_emptied', 'clear_boxnow_session_data');
    add_action('woocommerce_before_cart', 'clear_boxnow_session_data');
    add_action('woocommerce_checkout_init', 'clear_boxnow_session_data');

    function clear_boxnow_session_data()
    {
      if (!is_admin() && function_exists('WC') && WC()->session) {
        WC()->session->set('boxnow_locker_id', null);
      }
    }

    /**
     * Update the order meta for block-based checkout after the order is created.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta_block($order)
    {
      if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($_POST['_boxnow_locker_id']));
      }

      if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
        $order->add_meta_data('_selected_warehouse', explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')))[0]);
      }

      $order->save();
    }
    add_action('woocommerce_store_api_checkout_update_order_meta', 'bndp_box_now_delivery_checkout_field_update_order_meta_block');

    // Save the locker_id from session into the order meta once the order is updated from request
    function save_boxnow_locker_id_from_session($order)
    {
      $locker_id = WC()->session->get('_boxnow_locker_id');
      if (!empty($locker_id)) {
        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($locker_id));
      }
      if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
        $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
        $first_warehouse = $warehouse_ids[0] ?? '';
        $order->update_meta_data('_selected_warehouse', $first_warehouse);
      }
      $order->save();
    }
    add_action('woocommerce_store_api_checkout_update_order_from_request', 'save_boxnow_locker_id_from_session', 10, 1);
  }

  /* Display field value on the order edit page for both checkout types */
  add_action('woocommerce_admin_order_data_after_billing_address', 'bndp_box_now_delivery_checkout_field_display_admin_order_meta', 10, 1);

  /**
   * Display custom checkout field in the order edit page.
   *
   * @param WC_Order $order WooCommerce Order.
   */
  function bndp_box_now_delivery_checkout_field_display_admin_order_meta($order)
  {
    // 1) Check if the order used Box Now shipping
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;
    foreach ($shipping_methods as $shipping_method) {
      if ($shipping_method->get_method_id() === 'box_now_delivery') {
        $box_now_used = true;
        break;
      }
    }
    if (!$box_now_used) {
      return;
    }

    // Existing metadata
    $locker_id    = $order->get_meta('_boxnow_locker_id');
    $warehouse_id = $order->get_meta('_selected_warehouse');

    // If there's no locker or warehouse, do nothing
    if (empty($locker_id) && empty($warehouse_id)) {
      return;
    }

    // === NEW WAREHOUSE CHECK LOGIC ===
    $warehouse_ids_str = get_option('boxnow_warehouse_id', '');
    $warehouse_ids     = explode(',', str_replace(' ', '', $warehouse_ids_str));
    if (!empty($warehouse_ids)) {
      $current_wh = $order->get_meta('_selected_warehouse');
      // If empty or not in the plugin's list => overwrite
      if (empty($current_wh) || !in_array($current_wh, $warehouse_ids)) {
        $order->update_meta_data('_selected_warehouse', $warehouse_ids[0]);
        $order->save();
      }
    }

    // Now retrieve token & warehouse data (unchanged logic)
    $api_url   = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
    $auth_args = array(
      'method'  => 'POST',
      'headers' => array('Content-Type' => 'application/json'),
      'body'    => json_encode(array(
        'grant_type'    => 'client_credentials',
        'client_id'     => get_option('boxnow_client_id', ''),
        'client_secret' => get_option('boxnow_client_secret', '')
      ))
    );
    $response = wp_remote_post($api_url, $auth_args);
    $json     = json_decode(wp_remote_retrieve_body($response), true);

    $origins_url   = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/origins';
    $origins_args  = array(
      'method'  => 'GET',
      'headers' => array(
        'Authorization' => 'Bearer ' . $json['access_token'],
        'Content-Type'  => 'application/json'
      )
    );
    $warehouses_json = wp_remote_get($origins_url, $origins_args);
    $warehouses_list = json_decode(wp_remote_retrieve_body($warehouses_json), true);

    // Build an array of warehouse_id => warehouse_name
    $warehouse_names = array();
    if (!empty($warehouses_list['data']) && is_array($warehouses_list['data'])) {
      foreach ($warehouses_list['data'] as $wh) {
        $warehouse_names[$wh['id']] = $wh['name'];
      }
    }
    ?>
    <div class="boxnow_data_column">
      <h4>
        <?php echo esc_html__('BOX NOW доставка', 'woocommerce'); ?>
        <a href="#" class="edit_address"><?php echo esc_html__('Редакция', 'woocommerce'); ?></a>
      </h4>

      <!-- "View" mode (before clicking "Edit") -->
      <div class="address">
        <?php
        // Номер на автомат
        echo '<p><strong>' . esc_html__('Номер на автомат: ', 'woocommerce') . '</strong>' . esc_html($locker_id) . '</p>';

        // Адрес на автомат (from _boxnow_real_address)
        $real_boxnow_address = $order->get_meta('_boxnow_real_address');
        if (!empty($real_boxnow_address)) {
          echo '<p><strong>' . esc_html__('Адрес на автомат: ', 'woocommerce') . '</strong>'
            . esc_html($real_boxnow_address)
            . '</p>';
        }

        // Номер на склад
        echo '<p><strong>' . esc_html__('Номер на склад: ', 'woocommerce') . '</strong>' . esc_html($warehouse_id);
        if (!empty($warehouse_names[$warehouse_id])) {
          echo ' - ' . esc_html($warehouse_names[$warehouse_id]);
        }
        echo '</p>';
        ?>
      </div>

      <!-- "Edit" mode (after clicking "Edit") -->
      <div class="edit_address">
        <p class="form-field form-field-wide _boxnow_locker_id" style="margin: 0 !important;">
          <label for="_boxnow_locker_id" style="display: block; margin-bottom: 5px; margin-top: 5px">
            <?php esc_html_e('Номер на автомат:', 'woocommerce'); ?>
          </label>
          <span style="display: flex; align-items: center; gap: 10px;">
            <input type="text"
              class="short"
              name="_boxnow_locker_id"
              id="_boxnow_locker_id"
              value="<?php echo esc_attr($locker_id); ?>" />
            <button type="button" class="button" id="admin-boxnow-open-popup">
              Промяна на адреса
            </button>
          </span>
        </p>

        <?php $real_boxnow_address = $order->get_meta('_boxnow_real_address'); ?>
        <p class="form-field form-field-wide _boxnow_real_address" style="margin: 0 !important;">
          <label for="_boxnow_real_address" style="display: block; margin-bottom: 5px; margin-top: 5px">
            <?php esc_html_e('Адрес на автомат:', 'woocommerce'); ?>
          </label>
          <input type="text"
            class="short"
            name="_boxnow_real_address"
            id="_boxnow_real_address"
            value="<?php echo esc_attr($real_boxnow_address); ?>"
            readonly="readonly" />
        </p>

        <?php
        $warehouse_ids   = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
        $warehouses_show = array();
        foreach ($warehouse_ids as $id) {
          if (!empty($warehouse_names[$id])) {
            $warehouses_show[$id] = $id . ' - ' . esc_html($warehouse_names[$id]);
          } else {
            $warehouses_show[$id] = $id; // fallback if not found
          }
        }
        $warehouse_options = array_combine($warehouse_ids, $warehouses_show);
        woocommerce_wp_select(array(
          'id'            => '_selected_warehouse',
          'label'         => esc_html__('Номер на склад: ', 'woocommerce'),
          'wrapper_class' => '_selected_warehouse',
          'options'       => $warehouse_options
        ));
        ?>

        <style>
          #boxnow-admin-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0) !important;
            z-index: 999999;
            display: none;
          }

          #boxnow-admin-popup-iframe {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 80%;
            border: 0;
            border-radius: 20px;
            background: #fff;
            z-index: 1000000;
            display: none;
          }
        </style>

        <script type="text/javascript">
          (function($) {

            $('#admin-boxnow-open-popup').on('click', function(e) {
              e.preventDefault();
              openBoxNowAdminPopup();
            });

            function openBoxNowAdminPopup() {
              if ($('#boxnow-admin-overlay').length === 0) {
                $('body').append('<div id="boxnow-admin-overlay"></div>');
              }
              if ($('#boxnow-admin-popup-iframe').length === 0) {
                $('body').append('<iframe id="boxnow-admin-popup-iframe"></iframe>');
              }
              var src = 'https://widget-v5.boxnow.bg/popup.html?gps=yes&autoselect=no';
              $('#boxnow-admin-popup-iframe').attr('src', src).show();
              $('#boxnow-admin-overlay').show();
            }

            function closeBoxNowAdminPopup() {
              $('#boxnow-admin-popup-iframe').attr('src', '').hide();
              $('#boxnow-admin-overlay').hide();
            }
            $(document).on('click', '#boxnow-admin-overlay', function() {
              closeBoxNowAdminPopup();
            });
            window.addEventListener('message', function(event) {
              var dataObj = event.data;
              if (typeof dataObj === 'string') {
                try {
                  dataObj = JSON.parse(dataObj);
                } catch (e) {}
              }
              if (dataObj === 'closeIframe' || dataObj.boxnowClose !== undefined) {
                closeBoxNowAdminPopup();
                return;
              }
              if (dataObj && dataObj.boxnowLockerId) {
                $('#_boxnow_locker_id').val(dataObj.boxnowLockerId);
                closeBoxNowAdminPopup();
                // Re-fetch address for new ID
                fetchAndShowBoxNowAddress(dataObj.boxnowLockerId);
              }
            }, false);

            // (2) LIVE-UPDATE when admin changes locker ID manually
            $('#_boxnow_locker_id').on('change keyup', function() {
              var typedLockerId = $(this).val().trim();
              fetchAndShowBoxNowAddress(typedLockerId);
            });

            // (3) A function that fetches location data from the BoxNow URL,
            //     finds the matching ID, and updates _boxnow_real_address.
            function fetchAndShowBoxNowAddress(lockerId) {
              if (!lockerId) {
                $('#_boxnow_real_address').val('');
                return;
              }
              $.get('https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json')
                .done(function(response) {
                  if (response && response.data && Array.isArray(response.data)) {
                    var foundAddress = '';
                    response.data.forEach(function(locker) {
                      if (locker.id == lockerId) {
                        foundAddress = locker.addressLine1 || '';
                      }
                    });
                    $('#_boxnow_real_address').val(foundAddress);
                  }
                })
                .fail(function() {
                  // if error, we can clear or do nothing
                  $('#_boxnow_real_address').val('');
                });
            }
          })(jQuery);
        </script>
      </div>
    </div>
    <?php
  }


  /**
   * Fetch the locker addressLine1 from the BoxNow public location API.
   *
   * @param string $locker_id The ID of the locker (e.g. "BG10001").
   * @return string The "addressLine1", or an empty string if not found or error.
   */
  function boxnow_get_locker_address_line1($locker_id)
  {
    $url = 'https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json';

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
      return '';
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($json['data']) || !is_array($json['data'])) {
      return '';
    }

    foreach ($json['data'] as $locker) {
      // Compare as strings to avoid type issues
      if (!empty($locker['id']) && (string)$locker['id'] === (string)$locker_id) {
        if (!empty($locker['addressLine1'])) {
          return $locker['addressLine1'];
        }
        break;
      }
    }
    return '';
  }

  /**
   * Save custom checkout fields in the admin + store real locker address in _boxnow_real_address.
   */
  function bndp_box_now_delivery_save_checkout_field_admin_order_meta($post_id)
  {
    $order = wc_get_order($post_id);

    // Ensure we have an order and the required POST data
    if (!$order || !isset($_POST['_boxnow_locker_id']) || !isset($_POST['_selected_warehouse'])) {
      return;
    }

    // Save locker ID + warehouse
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
    $order->update_meta_data('_boxnow_locker_id', $locker_id);

    $selected_warehouse = sanitize_text_field($_POST['_selected_warehouse']);
    $order->update_meta_data('_selected_warehouse', $selected_warehouse);

    // Fetch the real address
    $real_address_line1 = '';
    if (!empty($locker_id)) {
      $real_address_line1 = boxnow_get_locker_address_line1($locker_id);
    }

    // Store it in a new meta key
    $order->update_meta_data('_boxnow_real_address', $real_address_line1);
    $order->save();
  }
  add_action('woocommerce_process_shop_order_meta', 'bndp_box_now_delivery_save_checkout_field_admin_order_meta');

  /**
   * Save extra details when processing the shop order.
   */
  add_action('woocommerce_order_status_changed', 'boxnow_save_extra_details', 10, 4);

  function boxnow_save_extra_details($order_id, $old_status, $new_status, $order)
  {
    // Log old status, new status, locker ID, and warehouse ID before status change
    error_log('Номер на поръчката: ' . $order_id . ', Предишен статус: ' . $old_status . ', Нов Статус: ' . $new_status);
    error_log('Преди промяна на статуса, Номер на автомат: ' . $order->get_meta('_boxnow_locker_id'));
    error_log('Преди промяна на статуса, Номер на склад: ' . $order->get_meta('_selected_warehouse'));

    // Check if locker id and Номер на склад are present
    $locker_id = $order->get_meta('_boxnow_locker_id');
    if (isset($locker_id) && $locker_id !== '') {
      error_log("Номер на автомат: " . $locker_id);
    } else {
      error_log("Грешка: Поле номер на автомат е празно.");
    }

    $warehouse_id = $order->get_meta('_selected_warehouse');
    if (isset($warehouse_id) && $warehouse_id !== '') {
      error_log("Номер на склад: " . $warehouse_id);
    } else {
      error_log("Номер на склад is empty when trying to save.");
    }

    // Refresh the order data after changes
    $order = wc_get_order($order_id);

    // Log locker ID and warehouse ID after status change
    error_log('After status change, Номер на автомат: ' . $order->get_meta('_boxnow_locker_id'));
    error_log('After status change, Номер на склад: ' . $order->get_meta('_selected_warehouse'));
  }

  /**
   * Change Cash on delivery title to custom
   */
  add_filter('woocommerce_gateway_title', 'bndp_change_cod_title_for_box_now_delivery', 20, 2);

  function bndp_change_cod_title_for_box_now_delivery($title, $payment_id)
  {
    if (!is_admin() && $payment_id === 'cod') {
      if (function_exists('WC') && WC()->session) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $box_now_delivery_method = 'box_now_delivery';

        if (is_array($chosen_shipping_methods) && in_array($box_now_delivery_method, $chosen_shipping_methods)) {
          $title = __('Плащане при доставка (Наложен платеж)', 'woocommerce');
        }
      }
    }

    return $title;
  }

  /*
  * Send information to BOX NOW api and for sending an email to the customer with the voucher
  */
  add_action('woocommerce_order_status_completed', 'boxnow_order_completed');

  /**
   * Verify that a given locker ID is present in the BoxNow location API.
   *
   * @param string $locker_id The locker ID to check.
   * @return bool True if found in the JSON, false if not found (or if error).
   */
  function boxnow_verify_locker_availability($locker_id)
  {
    $url = 'https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json';

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
      return false;
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($json['data']) || !is_array($json['data'])) {
      return false;
    }

    foreach ($json['data'] as $locker) {
      // Compare strings to avoid type mismatch
      if (isset($locker['id']) && (string)$locker['id'] === (string)$locker_id) {
        return true; // found a match
      }
    }

    return false;
  }

  /**
   * Fires when an order is marked "completed" in WooCommerce,
   * then attempts to create a BoxNow shipping voucher via API.
   *
   * @param int      $order_id   The WooCommerce order ID.
   * @param string   $old_status Previous order status (e.g. 'processing').
   * @param string   $new_status New order status (e.g. 'completed').
   * @param WC_Order $order      The Order object.
   */
  function boxnow_order_completed($order_id)
  {
    error_log("DEBUG: boxnow_order_completed() called for order_id = $order_id");

    if (get_transient('_manual_status_change')) {
      delete_transient('_manual_status_change');
      error_log("DEBUG: _manual_status_change was set. Bailing out.");
      return;
    }

    if (get_option('boxnow_voucher_option') !== 'email') {
      error_log("DEBUG: boxnow_voucher_option is not 'email'. Bailing out.");
      return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
      error_log("DEBUG: No order found for #$order_id. Stopping.");
      return;
    }

    if (!$order->has_shipping_method('box_now_delivery')) {
      error_log("DEBUG: Order #$order_id doesn't have box_now_delivery shipping method. Stopping.");
      return;
    }

    // If voucher is already created, skip
    if (get_post_meta($order_id, '_voucher_created', true)) {
      error_log("DEBUG: _voucher_created is already set for #$order_id. Stopping.");
      return;
    }

    // Prepare data
    $prep_data = boxnow_prepare_data($order);
    $locker_id = isset($prep_data['locker_id']) ? $prep_data['locker_id'] : '';

    error_log("DEBUG: locker_id from prep_data = $locker_id");

    // If no locker ID
    if (!$locker_id) {
      error_log("DEBUG: No locker ID, calling wp_die() now.");
      wp_die(
        __('No BOX NOW locker ID was found in this order. Cannot proceed.', 'boxnowbulgaria'),
        __('Missing Locker ID', 'boxnowbulgaria'),
        ['back_link' => true]
      );
    }

    // Validate locker in location JSON
    $result = boxnow_verify_locker_availability($locker_id);
    error_log("DEBUG: boxnow_verify_locker_availability($locker_id) => " . ($result ? 'TRUE' : 'FALSE'));
    if (!$result) {
      error_log("DEBUG: Locker not in location API. Calling wp_die() now.");
      $message = sprintf(
        __('The selected locker (ID: %s) is not currently available in BoxNow’s network. Please choose another location.', 'boxnowbulgaria'),
        esc_html($locker_id)
      );
      wp_die($message, __('Invalid BoxNow Locker', 'boxnowbulgaria'), ['back_link' => true]);
    }

    // If locker is valid, proceed
    error_log("DEBUG: Locker valid, proceeding with voucher creation...");

    $response = boxnow_order_completed_delivery_request($prep_data, $order_id, 1);
    $response_data = json_decode($response, true);

    if (isset($response_data['parcels'][0]['id'])) {
      $order->update_meta_data('_boxnow_parcel_id', $response_data['parcels'][0]['id']);
      update_post_meta($order_id, '_voucher_created', 'yes');
      $order->save();
      error_log("DEBUG: Voucher created successfully for order #$order_id");
    } else {
      error_log("DEBUG: Request to create voucher failed. Response: " . print_r($response_data, true));
    }
  }

  // This is the delivery request only for the boxnow_order_completed function
  function boxnow_order_completed_delivery_request($prep_data, $order_id, $num_vouchers)
  {
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = strval(mt_rand());
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';

    $items = [];
    for ($i = 0; $i < $num_vouchers; $i++) {
      $item_data = [
        "value" => $prep_data['product_price'],
        "weight" => $prep_data['weight']
      ];

      if (isset($prep_data['compartment_sizes'])) {
        $item_data["compartmentSize"] = $prep_data['compartment_sizes'][0];
      }

      $items[] = $item_data;
    }

    $data = [
      "notifyOnAccepted" => $send_voucher_via_button ? get_option('boxnow_voucher_button', '') : '',
      "orderNumber" => $randStr,
      "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
      "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "allowReturn" => true,
      "origin" => [
        "contactName" => get_option('boxnow_sender_name', ''),
        "contactNumber" => get_option('boxnow_sender_phone', ''),
        "contactEmail" => get_option('boxnow_sender_email', ''),
        "locationId" => $prep_data['selected_warehouse'],
      ],
      "destination" => [
        "contactNumber" => $prep_data['phone'],
        "contactEmail" => $prep_data['email'],
        "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
        "locationId" => $prep_data['locker_id'],
      ],
      "items" => $items
    ];
  

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    $order = wc_get_order($order_id);

    if (is_wp_error($response)) {
      return $response->get_error_message();
    } else {
      $response_body = json_decode(wp_remote_retrieve_body($response), true);
      if (isset($response_body['id'])) {
        $parcel_ids = [];
        foreach ($response_body['parcels'] as $parcel) {
          $parcel_ids[] = $parcel['id'];
        }
        $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        $order->save();
      } else {
        throw new Exception('Грешка: Неуспещно създаване на товарителница.');
      }
      return wp_remote_retrieve_body($response);
    }
  }

  // Function to determine the compartment size based on dimensions
  function boxnow_get_compartment_size($dimensions)
  {
    $small = ['length' => 60, 'width' => 45, 'height' => 8];
    $medium = ['length' => 60, 'width' => 45, 'height' => 17];
    $large = ['length' => 60, 'width' => 45, 'height' => 36];

    if ((!isset($dimensions['length']) || $dimensions['length'] == 0) &&
      (!isset($dimensions['width']) || $dimensions['width'] == 0) &&
      (!isset($dimensions['height']) || $dimensions['height'] == 0)
    ) {
      return 2;
    }

    if (
      $dimensions['length'] <= $small['length'] &&
      $dimensions['width'] <= $small['width'] &&
      $dimensions['height'] <= $small['height']
    ) {
      return 1;
    }

    if (
      $dimensions['length'] <= $medium['length'] &&
      $dimensions['width'] <= $medium['width'] &&
      $dimensions['height'] <= $medium['height']
    ) {
      return 2;
    }

    if (
      $dimensions['length'] <= $large['length'] &&
      $dimensions['width'] <= $large['width'] &&
      $dimensions['height'] <= $large['height']
    ) {
      return 3;
    }

    throw new Exception('Невалидни размери на продукта(продуктите) - моля уверете се че продукта(продуктите) се събират в автомат на BOX NOW!');
  }

  function boxnow_prepare_data($order)
  {
    // Update possibly edited fields
    if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
      $order->update_meta_data('_boxnow_locker_id', wc_clean($_POST['_boxnow_locker_id']));
    }
    if (isset($_POST['_selected_warehouse']) && !empty($_POST['_selected_warehouse'])) {
      $order->update_meta_data('_selected_warehouse', wc_clean($_POST['_selected_warehouse']));
    }
    $order->save();

    $prep_data = $order->get_address('billing');
    foreach ($order->get_meta_data() as $data) {
      $meta_key = $data->key;
      $meta_value = $data->value;

      switch ($meta_key) {
        case get_option('boxnow-save-data-addressline1', ''):
          $prep_data['locker_addressline1'] = $meta_value;
          break;
        case get_option('boxnow-save-data-postalcode', ''):
          $prep_data['locker_postalcode'] = (int)$meta_value;
          break;
        case get_option('boxnow-save-data-addressline2', ''):
          $prep_data['locker_addressline2'] = $meta_value;
          break;
        case '_boxnow_locker_id':
          $prep_data['locker_id'] = $meta_value;
          break;
        case '_selected_warehouse':
          $prep_data['selected_warehouse'] = $meta_value;
          break;
      }
    }

    $prep_data['payment_method'] = $order->get_payment_method();
    $prep_data['order_total'] = $order->get_total();
    $prep_data['product_price'] = number_format(strval($order->get_subtotal()), 2, '.', '');

    $compartment_sizes = [];
    foreach ($order->get_items() as $item) {
      $product = $item->get_product();

      $dimensions = [
        'length' => is_numeric($product->get_length()) ? floatval($product->get_length()) : 0,
        'width' => is_numeric($product->get_width()) ? floatval($product->get_width()) : 0,
        'height' => is_numeric($product->get_height()) ? floatval($product->get_height()) : 0
      ];

      $compartment_size = boxnow_get_compartment_size($dimensions);
      $quantity = $item->get_quantity();
      for ($i = 0; $i < $quantity; $i++) {
        $compartment_sizes[] = $compartment_size;
      }
    }
    $prep_data['compartment_sizes'] = $compartment_sizes;

    $tel = $prep_data['phone'];
    if (substr($tel, 0, 1) != '+' && substr($tel, 0, 2) != '00') {
      $tel = '+359' . $tel;
    }
    $prep_data['phone'] = $tel;

    $weight = 1.00;
    foreach ($order->get_items() as $item) {
      $product = $item->get_product();
      $quantity = $item->get_quantity();
      $product_weight = $product->get_weight();
      if (!is_null($product_weight) && is_numeric($product_weight)) {
        $weight += floatval($product_weight) * $quantity;
      }
    }
    $prep_data['weight'] = $weight;

    return $prep_data;
  }

  function boxnow_send_delivery_request($prep_data, $order_id, $num_vouchers, $compartment_sizes)
  {
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = strval(mt_rand());
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';

    $items = [];
    for ($i = 0; $i < $num_vouchers; $i++) {
      $items[] = [
        "value" => $prep_data['product_price'],
        "weight" => $prep_data['weight'],
        "compartmentSize" => $compartment_sizes
      ];
    }

    $data = [
      "notifyOnAccepted" => $send_voucher_via_button ? get_option('boxnow_voucher_button', '') : '',
      "orderNumber" => $randStr,
      "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
      "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "allowReturn" => true,
      "origin" => [
        "contactName" => get_option('boxnow_sender_name', ''),
        "contactNumber" => get_option('boxnow_sender_phone', ''),
        "contactEmail" => get_option('boxnow_sender_email', ''),
        "locationId" => $prep_data['selected_warehouse'],
      ],
      "destination" => [
        "contactNumber" => $prep_data['phone'],
        "contactEmail" => $prep_data['email'],
        "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
        "locationId" => $prep_data['locker_id'],
      ],
      "items" => $items
    ];

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    $order = wc_get_order($order_id);

    if (is_wp_error($response)) {
      return $response->get_error_message();
    } else {
      $response_body = json_decode(wp_remote_retrieve_body($response), true);
      if (isset($response_body['id'])) {
        $parcel_ids = [];
        foreach ($response_body['parcels'] as $parcel) {
          $parcel_ids[] = $parcel['id'];
        }
        $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        $order->save();
      } else {
        error_log('API Response: ' . print_r($response_body, true));
        throw new Exception('Error: Unable to create vouchers.');
      }
      return wp_remote_retrieve_body($response);
    }
  }

  function boxnow_get_access_token()
  {
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
    $client_id = get_option('boxnow_client_id', '');
    $client_secret = get_option('boxnow_client_secret', '');

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
      ]),
    ]);

    if (is_wp_error($response)) {
      return $response->get_error_message();
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($json['access_token'])) {
      return $json['access_token'];
    } else {
      error_log('API Response: ' . print_r($json, true));
      return null;
    }
  }

  // Refresh the checkout page when the payment method changes
  add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');

  // Print Vouchers section
  function box_now_delivery_vouchers_input($order)
  {
    // Get the order shipping method
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;

    foreach ($shipping_methods as $shipping_method) {
      if ($shipping_method->get_method_id() == 'box_now_delivery') {
        $box_now_used = true;
        break;
      }
    }

    // Only proceed if Box Now was used
    if ($box_now_used) {
      if (get_option('boxnow_voucher_option', 'email') === 'button') {
        $max_vouchers = 0;
        foreach ($order->get_items() as $item) {
          $max_vouchers += $item->get_quantity();
        }

        $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
        $vouchers_created = $order->get_meta('_boxnow_vouchers_created');
        $button_disabled = $vouchers_created ? 'disabled' : '';

        if (!empty($parcel_ids)) {
          echo '<input type="hidden" id="box_now_parcel_ids" value="' . esc_attr(json_encode($parcel_ids ?: [])) . '">';
        }

        echo '<input type="hidden" id="create_vouchers_enabled" value="true" />';
        echo '<input type="hidden" id="max_vouchers" value="' . esc_attr($max_vouchers) . '">';

        if ($parcel_ids) {
          $links_html = '';
          foreach ($parcel_ids as $parcel_id) {
            $links_html .= '<a href="#" data-parcel-id="' . $parcel_id . '" class="parcel-id-link box-now-link">&#128196; ' . $parcel_id . '</a> ';
            $links_html .= '<button class="cancel-voucher-btn" data-order-id="' . $order->get_id() . '" style="color: white; background-color: red; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Откажи генерираната товарителница/и</button><br>';
          }
        } else {
          $links_html = '';
        }
    ?>
        <div class="box-now-vouchers">
          <h4>Генериране на товарителница за BOX NOW</h4>
          <p>Товарителница/и за тази поръчка (Максимален възможен брой товарителници: <span style="font-weight: bold; color: red;"><?php echo esc_html($max_vouchers); ?></span>)</p>
          <input type="hidden" id="box_now_order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
          <input type="number" id="box_now_voucher_code" name="box_now_voucher_code" min="1" max="<?php echo esc_attr($max_vouchers); ?>" placeholder="Въведете брой товарителници" style="width: 100%;" />

          <div class="box-now-compartment-size-buttons" style="margin-top: 10px;">
            <button type="button" id="box_now_create_voucher_small" class="button button-primary" data-compartment-size="small" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Създай товарителница (Малко)</button>
            <button type="button" id="box_now_create_voucher_medium" class="button button-primary" data-compartment-size="medium" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Създай товарителница (Средно)</button>
            <button type="button" id="box_now_create_voucher_large" class="button button-primary" data-compartment-size="large" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Създай товарителница (Голямо)</button>
          </div>
          <div id="box_now_voucher_link"><?php echo wp_kses_post($links_html); ?></div>
        </div>
      <?php
      }
    }
  }
  add_action('woocommerce_admin_order_data_after_shipping_address', 'box_now_delivery_vouchers_input', 10, 1);

  function box_now_delivery_vouchers_js()
  {
    wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-create-voucher.js', array('jquery'), '1.0', true);

    wp_localize_script('box-now-delivery-js', 'myAjax', array(
      'nonce' => wp_create_nonce('box-now-delivery-nonce'),
      'ajaxurl' => admin_url('admin-ajax.php'),
    ));
  }
  add_action('admin_enqueue_scripts', 'box_now_delivery_vouchers_js');

  function boxnow_cancel_voucher_ajax_handler()
  {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'box-now-delivery-nonce')) {
      wp_die('Invalid nonce');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $parcel_id = isset($_POST['parcel_id']) ? sanitize_text_field($_POST['parcel_id']) : '';

    if ($order_id > 0 && $parcel_id) {
      $order = wc_get_order($order_id);
      if (!$order) {
        wp_send_json_error('Невалиден номер на поръчка');
        return;
      }

      $api_cancellation_result = boxnow_send_cancellation_request($parcel_id);
      if ($api_cancellation_result === 'success') {
        boxnow_order_canceled($order_id, '', 'wc-boxnow-canceled', $order);

        $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
        if (($key = array_search($parcel_id, $parcel_ids)) !== false) {
          unset($parcel_ids[$key]);
          $parcel_ids = array_values($parcel_ids);

          $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
          $order->update_status('cancelled', __('Order cancelled after voucher cancellation.', 'boxnowbulgaria'));
          $order->save();
        }

        wp_send_json_success();
      } else {
        wp_send_json_error("BOX NOW API                : " . $api_cancellation_result);
      }
    } else {
      wp_send_json_error('                                     ');
    }
  }
  add_action('wp_ajax_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');
  add_action('wp_ajax_nopriv_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');

  function boxnow_create_box_now_vouchers_callback()
  {
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!isset($_POST['order_id']) || !isset($_POST['voucher_quantity']) || !isset($_POST['compartment_size'])) {
      wp_send_json_error('Error: Missing required data.');
    }

    $order_id = intval($_POST['order_id']);
    $voucher_quantity = intval($_POST['voucher_quantity']);
    $compartment_size = intval(sanitize_text_field($_POST['compartment_size']));

    $order = wc_get_order($order_id);
    if (!$order) {
      wp_send_json_error('Error: Order not found.');
    }

    // 1) Validate the locker ID BEFORE sending the delivery request.
    $locker_id = $order->get_meta('_boxnow_locker_id');
    if (!boxnow_verify_locker_availability($locker_id)) {
      wp_send_json_error(sprintf(
        'The selected locker (ID: %s) is not available in BoxNow’s network. Please choose another location.',
        esc_html($locker_id)
      ));
      return;
    }

    // 2) Prepare data as before
    $prep_data = boxnow_prepare_data($order);

    // 3) Attempt to create vouchers
    try {
      $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
      $response_body = json_decode($delivery_request_response, true);

      if (isset($response_body['id'])) {
        // Vouchers created successfully; store parcel IDs
        $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
        if (!$parcel_ids) {
          $parcel_ids = [];
        }

        foreach ($response_body['parcels'] as $parcel) {
          $parcel_ids[] = $parcel['id'];
          update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id);
        }

        $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        $order->update_meta_data('_boxnow_vouchers_created', 1);
        $order->update_status('completed', __('Order completed after voucher creation.', 'boxnowbulgaria'));
        $order->save();

        $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
        if (!$parcel_ids || count($parcel_ids) === 0) {
          throw new Exception('Error: No parcel IDs available. API response: ' . json_encode($response_body));
        }
      } else {
        throw new Exception('Error: Unable to create vouchers. API response: ' . json_encode($response_body));
      }
    } catch (Exception $e) {
      wp_send_json_error('Error: ' . $e->getMessage());
    }

    // 4) If we have parcel IDs, return the newest ones in the AJAX response
    if (!empty($parcel_ids)) {
      $new_parcel_ids = array_slice($parcel_ids, -$voucher_quantity);
      wp_send_json_success(array('new_parcel_ids' => $new_parcel_ids));
    } else {
      throw new Exception('Error: Unable to create vouchers. API response: ' . json_encode($response_body));
    }
  }

  add_action('wp_ajax_create_box_now_vouchers', 'boxnow_create_box_now_vouchers_callback');

  function boxnow_print_box_now_voucher_callback()
  {
    if (!isset($_GET['parcel_id'])) {
      wp_die('Error: Missing required data.');
    }

    $parcel_id = sanitize_text_field($_GET['parcel_id']);

    $order_id = get_option('_boxnow_parcel_order_id_' . $parcel_id);

    if (!$order_id) {
      wp_die('Error: Order not found.');
    }

    $order = wc_get_order($order_id);

    if (!$order) {
      wp_die('Error: Order not found.');
    }

    try {
      boxnow_print_voucher_pdf($parcel_id);
    } catch (Exception $e) {
      wp_die('Error: ' . $e->getMessage());
    }

    exit();
  }
  add_action('wp_ajax_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');
  add_action('wp_ajax_nopriv_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');


  /**
   * Add voucher email validation script to the admin footer.
   */
  function boxnow_voucher_email_validation()
  {
    if (is_admin()) {
      ?>
      <script>
        function isValidEmail(email) {
          const re = /^(([^<>()[\]\\.,;:\s@"]+(.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(([[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}]|(([a-zA-Z\-0-9]+.)+[a-zA-Z]{2,}))$/;
          return re.test(email.toLowerCase());
        }

        function displayEmailValidationMessage(message) {
          const messageContainer = document.getElementById('email_validation_message');
          messageContainer.textContent = message;
        }

        document.addEventListener('DOMContentLoaded', function() {
          const emailInput = document.querySelector('input[name="boxnow_voucher_email"]');

          if (emailInput) {
            emailInput.addEventListener('input', function() {
              if (!isValidEmail(emailInput.value)) {
                displayEmailValidationMessage('Моля въведете валиден e-mail адрес!');
              } else {
                displayEmailValidationMessage('');
              }
            });
          } else {
            console.warn("Email input element not found.");
          }
        });
      </script>
    <?php
    }
  }
  add_action('admin_footer', 'boxnow_voucher_email_validation');

  add_action('admin_enqueue_scripts', 'boxnow_load_jquery_in_admin');
  function boxnow_load_jquery_in_admin()
  {
    wp_enqueue_script('jquery');
  }
} else {
  /**
   * Display admin notice if WooCommerce is not active.
   */
  function bndp_box_now_delivery_admin_notice()
  {
    ?>
    <div class="notice notice-error is-dismissible">
      <p><?php _e('BOX NOW requires WooCommerce to be installed and active.', 'box-now-delivery'); ?></p>
    </div>
<?php
  }

  add_action('admin_notices', 'bndp_box_now_delivery_admin_notice');
}

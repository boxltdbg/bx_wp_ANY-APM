<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', 'box_now_delivery_shipping_method');

/**
 * Initialize the Box Now Delivery shipping method.
 */
function box_now_delivery_shipping_method()
{
    if (!class_exists('Box_Now_Delivery_Shipping_Method')) {
        /**
         * Class Box_Now_Delivery_Shipping_Method
         *
         * @property array $instance_form_fields
         */
        class Box_Now_Delivery_Shipping_Method extends WC_Shipping_Method
        {
            /**
             * Constructor for the shipping class.
             */
            public function __construct($instance_id = 0)
            {
                $this->id                 = 'box_now_delivery';
                $this->instance_id        = absint($instance_id);
                $this->method_title       = __('BOX NOW Bulgaria', 'box-now-delivery');
                $this->method_description = __('Настройки за BOX NOW Bulgaria', 'box-now-delivery');

                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );

                $this->init();

                // Define user set variables.
                $this->enabled                 = $this->get_option('enabled');
                $this->title                   = $this->get_option('title');
                $this->free_delivery_threshold = $this->get_option('free_delivery_threshold');
                $this->taxable                 = $this->get_option('taxable');
                $this->costbr1                 = $this->get_option('costbr1');
                $this->costbr2                 = $this->get_option('costbr2');
                $this->costbr3                 = $this->get_option('costbr3');
                $this->costbr4                 = $this->get_option('costbr4');
            }

            /**
             * Initialize settings and form fields.
             */
            public function init()
            {
                $this->instance_form_fields = $this->get_form_fields();
                $this->init_instance_settings();

                // Save settings in admin if you have any defined
                add_action('woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id, array($this, 'process_admin_options'));
            }

            /**
             * Define settings fields for the shipping method.
             */
            public function get_form_fields()
            {
                $fields = array(
                    'enabled' => array(
                        'title'       => __('Включено / Изключено', 'box-now-delivery'),
                        'type'        => 'checkbox',
                        'description' => 'Включване и изключване на метода за доставка BOX NOW',
                        'default'     => 'yes',
                    ),
                    'title' => array(
                        'title'       => __('Име на метода за доставка', 'box-now-delivery'),
                        'type'        => 'text',
                        'description' => __('Име на метода за доставка, което вижда клиента при приключване на поръчката.', 'box-now-delivery'),
                        'default'     => __('Доставка до BOX NOW автомат - достъпни 24/7', 'box-now-delivery'),
                        'desc_tip'    => true,
                    ),
                    'free_delivery_threshold' => array(
                        'title'       => __('Стойност лимит за безплатна доставка.', 'box-now-delivery'),
                        'type'        => 'number',
                        'description' => __('Ако стойността на поръчката е над тази сума, няма да се начисли такса за доставка', 'box-now-delivery'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'taxable' => array(
                        'title'       => __('Начисляване на ДДС', 'box-now-delivery'),
                        'type'        => 'select',
                        'description' => __('Начисляване на ДДС върху стойността на поръчката?', 'box-now-delivery'),
                        'default'     => 'yes',
                        'options'     => array(
                            'yes' => __('Да', 'box-now-delivery'),
                            'no'  => __('Не', 'box-now-delivery'),
                        ),
                    ),
                    'custom_weight' => array(
                        'title'             => __('Максимално допустимо тегло (kg)', 'box-now-delivery'),
                        'type'              => 'number',
                        'description'       => __('Максимално допустимо тегло (kg)', 'box-now-delivery'),
                        'placeholder'       => __('20kg', 'box-now-delivery'),
                        'default'           => 20,
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'step' => '0.1',
                            'min'  => '0.1',
                        ),
                    ),
                    'dimensions' => array(
                        'title'       => __('Максимални размери на пратката', 'box-now-delivery'),
                        'type'        => 'title',
                        'description' => __('Максимални размери на пратката при доставка с BOX NOW', 'box-now-delivery'),
                    ),
                    'max_length' => array(
                        'title'             => __('Максимална дължина (cm)', 'box-now-delivery'),
                        'type'              => 'number',
                        'description'       => __('Максимална дължина на пратката позволена за този метод за доставка (в см.)', 'box-now-delivery'),
                        'placeholder'       => __('60 cm', 'box-now-delivery'),
                        'default'           => 60,
                        'custom_attributes' => array(),
                    ),
                    'max_width' => array(
                        'title'             => __('Максимална широчина (cm)', 'box-now-delivery'),
                        'type'              => 'number',
                        'description'       => __('Максимална широчина на пратката позволена за този метод за доставка (в см.)', 'box-now-delivery'),
                        'placeholder'       => __('45 cm', 'box-now-delivery'),
                        'default'           => 45,
                        'custom_attributes' => array(),
                    ),
                    'max_height' => array(
                        'title'             => __('Максимална височина (cm)', 'box-now-delivery'),
                        'type'              => 'number',
                        'description'       => __('Максимална височина на пратката позволена за този метод за доставка (в см.)', 'box-now-delivery'),
                        'placeholder'       => __('36 cm', 'box-now-delivery'),
                        'default'           => 36,
                        'custom_attributes' => array(),
                    ),
                    'weight_based_costs' => array(
                        'title'       => __('Стойност на доставката по тегло', 'box-now-delivery'),
                        'type'        => 'title',
                        'description' => __('Определете стойностите на доставката за различни теглови диапазони.', 'box-now-delivery'),
                    ),
                    'costbr1' => array(
                        'title'       => __('Стойност на доставката 0 кг. - 3 кг.', 'box-now-delivery'),
                        'type'        => 'text',
                        'description' => __('Стойност на доставката 0 кг. - 3 кг.', 'box-now-delivery'),
                        'default'     => '0',
                        'desc_tip'    => true,
                    ),
                    'costbr2' => array(
                        'title'       => __('Стойност на доставката 3 кг. - 6 кг.', 'box-now-delivery'),
                        'type'        => 'text',
                        'description' => __('Стойност на доставката 3 кг. - 6 кг.', 'box-now-delivery'),
                        'default'     => '0',
                        'desc_tip'    => true,
                    ),
                    'costbr3' => array(
                        'title'       => __('Стойност на доставката 6 кг. - 10 кг.', 'box-now-delivery'),
                        'type'        => 'text',
                        'description' => __('Стойност на доставката 6 кг. - 10 кг.', 'box-now-delivery'),
                        'default'     => '0',
                        'desc_tip'    => true,
                    ),
                    'costbr4' => array(
                        'title'       => __('Стойност на доставката 10 кг. - 20 кг.', 'box-now-delivery'),
                        'type'        => 'text',
                        'description' => __('Стойност на доставката 10 кг. - 20 кг.', 'box-now-delivery'),
                        'default'     => '0',
                        'desc_tip'    => true,
                    ),
                    'class_costs_title' => array(
                        'title'       => __('Стойност на доставката по клас на доставка', 'box-now-delivery'),
                        'type'        => 'title',
                        'description' => __('Определете стойностите на доставката за всеки клас на доставка.', 'box-now-delivery'),
                    ),
                );

                // Get shipping classes
                $shipping_classes = WC()->shipping()->get_shipping_classes();

                foreach ($shipping_classes as $shipping_class) {
                    $fields['class_cost_' . $shipping_class->slug] = array(
                        'title'       => sprintf(__('Стойност за %s', 'box-now-delivery'), esc_html($shipping_class->name)),
                        'type'        => 'text',
                        'description' => sprintf(__('Стойност на доставката за продукти с клас на доставка "%s".', 'box-now-delivery'), esc_html($shipping_class->name)),
                        'default'     => '0', // Set default to '0'
                        'desc_tip'    => true,
                    );
                }

                // Add field for products without a shipping class
                $fields['no_class_cost'] = array(
                    'title'       => __('Стойност за продукти без клас на доставка', 'box-now-delivery'),
                    'type'        => 'text',
                    'description' => __('Стойност на доставката за продукти без клас на доставка.', 'box-now-delivery'),
                    'default'     => '0', // Set default to '0'
                    'desc_tip'    => true,
                );

                // COD Description Settings
                $fields['cod_description'] = array(
                    'title'       => __('Настройване на описанието на "Наложен платеж"', 'box-now-delivery'),
                    'type'        => 'title',
                    'description' => __('Промяна на текста в описанието на метод за плащане "Наложен платеж"', 'box-now-delivery'),
                );
                $fields['enable_custom_cod_description'] = array(
                    'title'       => __('Модифициране на текста за "Наложен платеж"', 'box-now-delivery'),
                    'type'        => 'checkbox',
                    'description' => __('Включване / изключване на модифицирането на текста за метод за плащане "Наложен платеж".', 'box-now-delivery'),
                    'default'     => 'yes',
                    'class'       => 'enable_custom_cod_description',
                );
                $fields['custom_cod_description'] = array(
                    'title'       => __('Описание на метода Наложен Платеж', 'box-now-delivery'),
                    'type'        => 'text',
                    'description' => __('Въведете текст по желание при избран метод за плащане Наложен платеж', 'box-now-delivery'),
                    'default'     => 'ВНИМАНИЕ! При доставка до автомат на BOX NOW с "Наложен платеж" няма опция за плащане в брой. Плащането е с банкова карта през линк, който ще получите по SMS/Viber/имейл заедно с потвърждението за изпратената пратка.',
                    'desc_tip'    => true,
                    'class'       => 'custom_cod_description_field',
                );

                return $fields;
            }

            /**
             * Processes and saves options.
             */
            public function process_admin_options()
            {
                parent::process_admin_options();
            }

            /**
             * Calculate the shipping cost.
             *
             * @param array $package Shipping package.
             */
            public function calculate_shipping($package = array())
            {
                // Check if any item in the cart is oversized
                if ($this->has_oversized_products()) {
                    return; // Do not display the Box Now Delivery shipping method if an item is oversized
                }
            
                // Taxable yes or no
                $taxable = ($this->taxable == 'yes') ? true : false;
            
                // Get the order total
                $order_total = WC()->cart->get_displayed_subtotal();
            
                // Adjust total for any coupons
                if (!empty(WC()->cart->get_coupons())) {
                    foreach (WC()->cart->get_coupons() as $code => $coupon) {
                        if ($coupon->is_type('fixed_cart')) {
                            $order_total -= $coupon->get_amount();
                        } elseif ($coupon->is_type('percent')) {
                            $order_total -= ($coupon->get_amount() / 100) * $order_total;
                        }
                    }
                }
            
                // Get the user-defined threshold for free delivery
                $free_delivery_threshold = $this->get_option('free_delivery_threshold');
            
                // Check if the order total is above the threshold for free delivery
                if (!empty($free_delivery_threshold) && $order_total >= $free_delivery_threshold) {
                    $this->cost = 0.00;
                } else {
                    // Initialize cost
                    $this->cost = 0.00;
            
                    // Calculate weight-based cost
                    $parcel_weight = $this->get_cart_weight();
            
                    // Convert option values to numeric types
                    $costbr1 = (float) $this->get_option('costbr1', '0');
                    $costbr2 = (float) $this->get_option('costbr2', '0');
                    $costbr3 = (float) $this->get_option('costbr3', '0');
                    $costbr4 = (float) $this->get_option('costbr4', '0');
            
                    // Determine the shipping cost based on weight ranges
                    if ($parcel_weight <= 3) {
                        $weight_based_cost = $costbr1;
                    } elseif ($parcel_weight <= 6) {
                        $weight_based_cost = $costbr2;
                    } elseif ($parcel_weight <= 10) {
                        $weight_based_cost = $costbr3;
                    } elseif ($parcel_weight <= 20) {
                        $weight_based_cost = $costbr4;
                    } else {
                        $weight_based_cost = 0; // Default to 0 if weight exceeds defined ranges
                    }
            
                    // Calculate per-shipping-class cost
                    $class_costs = array();
                    $has_shipping_class = false;
            
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        $product           = $cart_item['data'];
                        $shipping_class_id = $product->get_shipping_class_id();
            
                        if ($shipping_class_id) {
                            $has_shipping_class = true;
                            $shipping_class = get_term($shipping_class_id, 'product_shipping_class');
                            $class_slug     = $shipping_class->slug;
                            $class_cost     = $this->get_option('class_cost_' . $class_slug, '0');
            
                            // Convert class_cost to float, default to 0 if empty
                            $class_cost = wc_format_decimal($class_cost);
                            $class_cost = $class_cost !== '' ? floatval($class_cost) : 0;
            
                            // Collect the cost
                            $class_costs[] = $class_cost;
                        }
                    }
            
                    // Determine the shipping cost based on presence of shipping classes
                    if ($has_shipping_class && !empty($class_costs)) {
                        // Use the highest shipping class cost
                        $this->cost = max($class_costs);
                    } else {
                        // Use the weight-based cost
                        $this->cost = $weight_based_cost;
                    }
                }
            
                $rate = array(
                    'id'       => $this->get_rate_id(),
                    'label'    => $this->title,
                    'cost'     => $this->cost,
                    'taxes'    => '',
                    'calc_tax' => 'per_order',
                );
            
                // Register the rate.
                $this->add_rate($rate);
            }

            /**
             * Get rate ID.
             *
             * @param string $suffix Optional. Rate ID suffix.
             * @return string
             */
            public function get_rate_id($suffix = '')
            {
                return $this->id . ':' . $this->instance_id . $suffix;
            }

            /**
             * Get the total weight of items in the cart.
             *
             * @return float
             */
            private function get_cart_weight()
            {
                $cart_weight = 0;

                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $product    = $cart_item['data'];
                    $weight     = $product->get_weight();
                    $quantity   = $cart_item['quantity'];
                    $cart_weight += is_numeric($weight) ? floatval($weight) * $quantity : 0;
                }

                return $cart_weight;
            }

            /**
             * Checks if the cart contains any oversized products or if the total weight exceeds the custom weight limit.
             *
             * @return bool Returns true if the cart contains oversized products or if the total weight exceeds the custom weight limit, otherwise returns false.
             */
            private function has_oversized_products()
            {
                $custom_weight_limit      = floatval($this->get_option('custom_weight'));
                $total_volume             = 0;
                $any_side_exceeds_diagonal = false;
                $weight_exceeds_limit     = false;

                // Maximum dimensions
                $max_length = 58; // cm
                $max_width  = 44; // cm
                $max_height = 35; // cm
                $max_diagonal = 80.78; // cm
                $max_volume = $max_length * $max_width * $max_height; // 58 * 44 * 35 = 89,320 cm³

                // Loop through each item in the cart
                foreach (WC()->cart->get_cart_contents() as $cart_item) {
                    $product  = $cart_item['data'];
                    $quantity = $cart_item['quantity'];
                    $length   = $product->get_length();
                    $width    = $product->get_width();
                    $height   = $product->get_height();

                    // Convert dimensions to floats, default to 0 if empty
                    $length = is_numeric($length) ? floatval($length) : 0;
                    $width  = is_numeric($width) ? floatval($width) : 0;
                    $height = is_numeric($height) ? floatval($height) : 0;

                    // Calculate volume for this item (in cubic centimeters)
                    $item_volume = $length * $width * $height * $quantity;

                    // Sum up total volume
                    $total_volume += $item_volume;

                    // Calculate the largest side of the item
                    $max_side = max($length, $width, $height);

                    // Check if any side exceeds the maximum diagonal
                    if ($max_side > $max_diagonal) {
                        $any_side_exceeds_diagonal = true;
                    }

                    // Handle the weight calculation
                    $weight = $product->get_weight();
                    // If no weight default to 1 kg.
                    $weight = is_numeric($weight) ? floatval($weight) * $quantity : 1 * $quantity; // Default weight to 1 kg per item if not set

                    // Check if total weight exceeds the custom weight limit
                    if ($weight > $custom_weight_limit) {
                        $weight_exceeds_limit = true;
                    }
                }

                // Check if total volume exceeds 89,320 cm³ or any side exceeds 80.78 cm
                if ($total_volume > $max_volume || $any_side_exceeds_diagonal) {
                    return true;
                }

                // Also, return true if total weight exceeds the custom weight limit
                if ($weight_exceeds_limit) {
                    return true;
                }

                // Otherwise, return false (no oversized products)
                return false;
            }
        }
    }
}

// Add the custom shipping method to WooCommerce
add_filter('woocommerce_shipping_methods', 'boxnow_add_box_now_delivery_shipping_method');

/**
 * Add the custom shipping method to WooCommerce.
 *
 * @param array $methods Existing shipping methods.
 * @return array Updated shipping methods.
 */
function boxnow_add_box_now_delivery_shipping_method($methods)
{
    $methods['box_now_delivery'] = 'Box_Now_Delivery_Shipping_Method';
    return $methods;
}

// Modify the Cash on Delivery payment method's description based on the shipping zone
add_filter('woocommerce_gateway_description', 'boxnow_change_cod_description', 10, 2);
function boxnow_change_cod_description($description, $payment_id)
{
    if ('cod' !== $payment_id) {
        return $description;
    }
    // Get the chosen shipping methods from the current customer's session
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

    // Only modify the description if the chosen shipping method is 'box_now_delivery'
    if (is_array($chosen_shipping_methods) && in_array('box_now_delivery', $chosen_shipping_methods)) {
        // Get the current customer's package
        $package = array(
            'destination' => array(
                'country'  => WC()->customer->get_shipping_country(),
                'state'    => WC()->customer->get_shipping_state(),
                'postcode' => WC()->customer->get_shipping_postcode(),
            ),
        );

        // Get the shipping zone matching the customer's package
        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package($package);

        // Now you can access the shipping methods of the shipping zone
        $shipping_methods = $shipping_zone->get_shipping_methods();

        foreach ($shipping_methods as $instance_id => $shipping_method) {
            if ('box_now_delivery' === $shipping_method->id) {
                $enable_custom_cod_description = $shipping_method->get_option('enable_custom_cod_description');
                $custom_cod_description        = $shipping_method->get_option('custom_cod_description');

                if ('yes' === $enable_custom_cod_description && !empty($custom_cod_description)) {
                    return $custom_cod_description;
                }
            }
        }
    }

    return $description;
}

// Refresh the checkout page when the payment method changes
add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');
function boxnow_add_cod_payment_refresh_script()
{
?>
    <script>
        jQuery(document).ready(function($) {
            $(document.body).on('change', 'input[name="payment_method"]', function(event) {
                event.preventDefault();
                event.stopPropagation();
            });
        });
    </script>
<?php
}

// Exclude Box Now delivery based on shipping class
add_filter('woocommerce_package_rates', 'exclude_shipping_methods_for_class', 10, 2);
function exclude_shipping_methods_for_class($rates, $package)
{
    // Get the dynamic exclusion class from the settings
    $exclude_classes = get_option('boxnow_exclude_class', '');

    if (!empty($exclude_classes)) {
        $exclude_classes_array = array_map('trim', explode(';', $exclude_classes));

        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $product_shipping_class = $product->get_shipping_class();

            if ($product_shipping_class && in_array($product_shipping_class, $exclude_classes_array)) {
                foreach ($rates as $rate_id => $rate) {
                    if (strpos($rate_id, 'box_now') !== false) {
                        unset($rates[$rate_id]);
                    }
                }
                break; // Exit loop once a matching product is found
            }
        }
    }
    return $rates;
}
?>

<?php

define('WCWPGW_VERSION', '2.5.15');

class WCWPGW_Gateway extends WC_Payment_Gateway
{

    /**
     * Version
     */
    public $version;

    /**
     * HTTP Payload
     */
    protected $payload = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version            = WCWPGW_VERSION;
        $this->id                 = 'wappoint';
        $this->method_title       = __('WapPoint', 'wcagw-payment-gateway');

        $this->method_description = sprintf(__('WapPoint works by sending the user to %1$sWapPoint%2$s to enter their payment information.', 'wcagw-payment-gateway'), '<a href="http://wappoint.co.za/">', '</a>');
        $this->icon               = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__))) . '/assets/images/logo.png';
        $this->debug_email        = get_option('admin_email');

        $this->supports = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->client_id            = $this->get_option('client_id');
        $this->client_secret        = $this->get_option('client_secret');
        $this->environment          = $this->get_option('environment');
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->payment_url          = $this->get_option('payment_url');
        $this->enabled              = 'yes';


        if ($this->environment == 'yes') {
            $this->url              = 'https://secure.addpay.co.za/v2/transactions';
        } else {
            $this->url              = 'https://secure-test.addpay.co.za/v2/transactions';
        }
        
        add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_wappoint', [$this, 'process_admin_options']);

        // $this->wcwpgw_check_result();

        add_action('woocommerce_api_wcwpgw_gateway', [$this, 'wcwpgw_check_result']);
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wcagw-payment-gateway'),
                'label'       => __('Enable WapPoint', 'wcagw-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('This controls whether or not this gateway is enabled within WooCommerce.', 'wcagw-payment-gateway'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'title' => array(
                'title'       => __('Title', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wcagw-payment-gateway'),
                'default'     => __('WapPoint', 'wcagw-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'wcagw-payment-gateway'),
                'default'     => __('Proceed via WapPoint suite of payment methods.', 'wcagw-payment-gateway'),
                'desc_tip'    => true,
            ),
            'client_id' => array(
                'title'       => __('Client ID', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This is the Client ID generated on the WapPoint merchant console.', 'wcagw-payment-gateway'),
                'default'     => 'CHANGE ME',
            ),
            'client_secret' => array(
                'title'       => __('Client Secret', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This is the Client Secret generated on the WapPoint merchant console.', 'wcagw-payment-gateway'),
                'default'     => 'CHANGE ME',
            ),
            'environment' => array(
                'title'       => __('Environment', 'wcagw-payment-gateway'),
                'label'       => __('Live WapPoint API Credentials', 'wcagw-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('This controls whether or not this gateway is using sandbox or live credentials.', 'wcagw-payment-gateway'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'payment_url' => array(
                'title'       => __('Payment URL', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('The URL of your custom payment page. WapPoint Plus Customers only.  ', 'wcagw-payment-gateway'),
                'default'     => __('', 'wcagw-payment-gateway'),
            )

        );
    }

    /**
     * Process the payment and return the result.
     *
     * @since 1.0.0
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WCWPGW_Gateway', home_url('/')));
        $return_url = str_replace('https:', 'http:', home_url('/') . 'wc-api/wcwpgw_gateway/');

        $this->payload = json_encode(array(
            'reference'   => $order->get_order_number(),
            'description' => get_bloginfo('name'),
            'customer' => array(
                'firstname' => self::wcwpgw_get_order_prop($order, 'billing_first_name'),
                'lastname'  => self::wcwpgw_get_order_prop($order, 'billing_last_name'),
                'email'     => self::wcwpgw_get_order_prop($order, 'billing_email'),
                'mobile'    => self::wcwpgw_get_order_prop($order, 'billing_phone'),
            ),
            'amount'  => array(
              'value'         => $order->get_total(),
              'currency_code' => get_woocommerce_currency(),
            ),
            'service' => array(
              'key'     => 'DIRECTPAY',
              'intent'  => 'SALE'
            ),
            'return_url'    => $return_url, //$this->get_return_url($order),
            'notify_url'    => $return_url,
            'cancel_url'    => $return_url
        ));

        $this->result = wp_remote_post($this->url, array(
              'method'       => 'POST',
              'timeout'      => 45,
              'redirection'  => 5,
              'httpversion'  => '1.0',
              'blocking'     => true,
              'headers'      => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'Authorization' => 'Token ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
              ],
              'body'         => $this->payload,
              'cookies'      => array()
        ));

        if ($this->result['response']['code'] == 201 || $this->result['response']['code'] == 200) {
            $result = json_decode($this->result['body'])->data;

            if (strlen($this->payment_url) == 0){
                return array(
                    'result'     => 'success',
                    'redirect'   => $result->direct,
                    );
                }
            else {
                $redirect = strpos($this->payment_url, "?") !== false ? $this->payment_url .'&transaction_id=' . $result->id : $this->payment_url .'?transaction_id=' . $result->id;
                return array(
                    'result'     => 'success',
                    'redirect'   => $redirect
                );
            }

        } else {
            try {
                $result = json_decode($this->result['body']);

                wc_add_notice(__('<strong>Payment Error</strong><br/>', 'woothemes') . $result->meta->message . '', 'error');
                return;
            } catch (\Exception $e) {
                wc_add_notice(__('<strong>Payment Error:</strong><br/>', 'woothemes') . 'System error', 'error');
                return;
            }
        }
    }

    /**
     * Check payment result.
     *
     * Check the result of the transaction and mark the order appropriately
     *
     * @since 1.0.0
     */
    public function wcwpgw_check_result($order_id = '')
    {
        global $woocommerce;
        $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : false;
        $cancelled = isset($_GET['cancel']) && $_GET['cancel'] == 1;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : false;
        $checkout_url = $woocommerce->cart->get_checkout_url();

        if ($transaction_id) {
            $this->result = wp_remote_get("{$this->url}/{$transaction_id}", array(
              'method'       => 'GET',
              'timeout'      => 45,
              'redirection'  => 5,
              'httpversion'  => '1.0',
              'blocking'     => true,
              'headers'      => [
                'Content-Type'  => 'application/json',
                'accept'        => 'application/json',
                'Authorization' => 'Token ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
              ],
              'cookies'      => array()
            ));

            $transaction = json_decode($this->result['body'])->data;
            $order          = new WC_Order($transaction->reference);
            $redirect = $this->get_return_url($order);
            $status         = !$status ? $transaction->status : $status;

            if ($status == 'COMPLETE') {
                if (!isset($_POST['transaction_id'])) {
                    wc_add_notice(__('<strong>Payment successfully processed via WapPoint</strong> ', 'woothemes'), 'success');
                }
                $order->update_status('processing');
                $order->payment_complete();
                wp_redirect($redirect);
            } elseif ($status == 'FAILED') {
                if (!isset($_POST['transaction_id'])) {
                    $state = ucFirst(strtolower($status));

                    wc_add_notice(__("<strong>Payment {$state}</strong><br/>{$transaction->status_reason}", 'woothemes'), 'error');
                }

                $order->update_status('failed');
                wp_redirect($redirect);
            } else if ($status == 'CANCELLED' || $cancelled) {
                wp_redirect($checkout_url);
            } else {
                wp_redirect($redirect);
            }
        } else {
            if (!isset($_POST['transaction_id'])) {
                wc_add_notice(__('<strong>WapPoint response does not include the transaction details.</strong> ', 'woothemes'), 'error');
            }
            wp_redirect($checkout_url);
        }
    }

    /**
     * Get order property with compatibility check on order getter introduced
     * in WC 3.0.
     *
     * @since 1.0.0
     *
     * @param WC_Order $order Order object.
     * @param string   $prop  Property name.
     *
     * @return mixed Property value
     */
    public static function wcwpgw_get_order_prop($order, $prop)
    {
        switch ($prop) {
            case 'order_total':
                $getter = array( $order, 'get_total' );
                break;
            default:
                $getter = array( $order, 'get_' . $prop );
                break;
        }

        return is_callable($getter) ? call_user_func($getter) : $order->{ $prop };
    }
}

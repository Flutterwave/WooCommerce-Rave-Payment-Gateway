<?php

  if( ! defined( 'ABSPATH' ) ) { exit; }
  
  define("BASEPATH", 1);
  
  require_once( FLW_WC_DIR_PATH . 'Flutterwave-Rave-PHP-SDK/lib/rave.php' );
  require_once( FLW_WC_DIR_PATH . 'includes/eventHandler.php' );
      
  use Flutterwave\Rave;

  /**
   * Main Rave Gateway Class
   */
  class FLW_WC_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {

      $this->base_url = 'https://rave-api-v2.herokuapp.com';
      $this->id = 'rave';
      $this->icon = plugins_url('assets/img/rave.png', FLW_WC_PLUGIN_FILE);
      $this->has_fields         = false;
      $this->method_title       = __( 'Rave', 'flw-payments' );
      $this->method_description = __( 'Rave allows you to accept payment from cards and bank accounts in multiple currencies. You can also accept payment offline via USSD and POS.', 'flw-payments' );
      $this->supports = array(
        'products',
      );

      $this->init_form_fields();
      $this->init_settings();

      $this->title        = $this->get_option( 'title' );
      $this->description  = $this->get_option( 'description' );
      $this->enabled      = $this->get_option( 'enabled' );
      $this->public_key   = $this->get_option( 'public_key' );
      $this->secret_key   = $this->get_option( 'secret_key' );
      $this->go_live      = $this->get_option( 'go_live' );
      $this->payment_method = $this->get_option( 'payment_method' );
      $this->country = $this->get_option( 'country' );
      $this->modal_logo = $this->get_option( 'modal_logo' );
      $this->payment_plan = $this->get_option( 'payment_plan' );

      add_action( 'admin_notices', array( $this, 'admin_notices' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
      add_action( 'woocommerce_api_flw_wc_payment_gateway', array( $this, 'flw_verify_payment' ) );

      if ( is_admin() ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      }

      if ( 'yes' === $this->go_live ) {
        $this->base_url = 'https://api.ravepay.co';
      }

      $this->load_scripts();

    }

    /**
     * Initial gateway settings form fields
     *
     * @return void
     */
    public function init_form_fields() {

      $this->form_fields = array(

        'enabled' => array(
          'title'       => __( 'Enable/Disable', 'flw-payments' ),
          'label'       => __( 'Enable Rave Payment Gateway', 'flw-payments' ),
          'type'        => 'checkbox',
          'description' => __( 'Enable Rave Payment Gateway as a payment option on the checkout page', 'flw-payments' ),
          'default'     => 'no',
          'desc_tip'    => true
        ),
        'go_live' => array(
          'title'       => __( 'Go Live', 'flw-payments' ),
          'label'       => __( 'Switch to live account', 'flw-payments' ),
          'type'        => 'checkbox',
          'description' => __( 'Ensure that you are using a public key and secret key generated from the live account.', 'flw-payments' ),
          'default'     => 'no',
          'desc_tip'    => true
        ),
        'title' => array(
          'title'       => __( 'Payment method title', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Optional', 'flw-payments' ),
          'default'     => 'Rave'
        ),
        'description' => array(
          'title'       => __( 'Payment method description', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Optional', 'flw-payments' ),
          'default'     => 'Powered by Flutterwave: Accepts Mastercard, Visa, Verve, Discover, AMEX, Diners Club and Union Pay.'
        ),
        'public_key' => array(
          'title'       => __( 'Rave Checkout Public Key', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Required! Enter your Rave Checkout public key here', 'flw-payments' ),
          'default'     => ''
        ),
        'secret_key' => array(
          'title'       => __( 'Rave Checkout Secret Key', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Required! Enter your Rave Checkout secret key here', 'flw-payments' ),
          'default'     => ''
        ),
        'payment_method' => array(
          'title'       => __( 'Payment Method', 'flw-payments' ),
          'type'        => 'select',
          'description' => __( 'Optional - Choice of payment method to use. Card, Account or Both. (Default: both)', 'flw-payments' ),
          'options'     => array(
            'both' => esc_html_x( 'Card and Account', 'payment_method', 'flw-payments' ),
            'card'  => esc_html_x( 'Card Only',  'payment_method', 'flw-payments' ),
            'account'  => esc_html_x( 'Account Only',  'payment_method', 'flw-payments' ),
          ),
          'default'     => 'both'
        ),
        'country' => array(
          'title'       => __( 'Charge Country', 'flw-payments' ),
          'type'        => 'select',
          'description' => __( 'Optional - Charge country. (Default: NG)', 'flw-payments' ),
          'options'     => array(
            'NG' => esc_html_x( 'NG', 'country', 'flw-payments' ),
            'GH' => esc_html_x( 'GH', 'country', 'flw-payments' ),
            'KE' => esc_html_x( 'KE', 'country', 'flw-payments' ),
            'ZA' => esc_html_x( 'ZA', 'country', 'flw-payments' ),
          ),
          'default'     => 'NG'
        ),
        'payment_plan' => array(
           'title'       => __( 'Payment Plan ID', 'flw-payments' ),
           'type'        => 'text',
           'description' => __( 'Optional - For recurring payment. Create/Get Payment Plan ID from your dashboard', 'flw-payments' ),
           'default'     => ''
        ),
        'modal_logo' => array(
          'title'       => __( 'Modal Custom Logo', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Optional - URL to your store\'s logo. Preferably a square image', 'flw-payments' ),
          'default'     => ''
       )

      );

    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment( $order_id ) {

      $order = wc_get_order( $order_id );

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
      );

    }

    /**
     * Handles admin notices
     *
     * @return void
     */
    public function admin_notices() {

      if ( 'no' == $this->enabled ) {
        return;
      }

      /**
       * Check if public key is provided
       */
      if ( ! $this->public_key || ! $this->secret_key ) {

        echo '<div class="error"><p>';
        echo sprintf(
          'Provide your public key and secret key <a href="%s">here</a> to be able to use the Rave Payment Gateway plugin. If you don\'t have one, kindly sign up at <a href="https://rave.flutterwave.com" target="_blank>https://rave.flutterwave.com</a>, navigate to the settings page and click on API.',
           admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rave' )
         );
        echo '</p></div>';
        return;
      }

    }

    /**
     * Checkout receipt page
     *
     * @return void
     */
    public function receipt_page( $order ) {

      $order = wc_get_order( $order );
      
      echo '<p>'.__( 'Thank you for your order, please click the <b>Make Payment</b> button below to make payment. You will be redirected to a secure page where you can enter you card details or bank account details. <b>Please, do not close your browser at any point in this process.</b>', 'flw-payments' ).'</p>';
      echo '<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">';
      echo __( 'Cancel order &amp; restore cart', 'flw-payments' ) . '</a> ';
      echo '<button class="button alt  wc-forward" id="flw-pay-now-button">Make Payment</button> ';
      

    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function load_scripts() {

      if ( ! is_checkout_pay_page() ) return;
      wp_enqueue_script( 'flw_js', plugins_url( 'assets/js/flw.js', FLW_WC_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', true );

      if ( get_query_var( 'order-pay' ) ) {
        
        $order_key = urldecode( $_REQUEST['key'] );
        $order_id  = absint( get_query_var( 'order-pay' ) );
        
        $order     = wc_get_order( $order_id );
        
        if ( $order->order_key == $order_key ) {
          $cb_url = WC()->api_request_url( 'FLW_WC_Payment_Gateway' ).'?rave_id='.$order_id;
          $payment_args = compact( 'cb_url');
        }

      }

      wp_localize_script( 'flw_js', 'flw_payment_args', $payment_args );

    }

    /**
     * Verify payment made on the checkout page
     *
     * @return void
     */
    public function flw_verify_payment() {
      
      $order_id = urldecode( $_GET['rave_id'] );
      
      if(!$order_id){
        $order_id = urldecode( $_GET['order_id'] );
      }
      $order = wc_get_order( $order_id );
      
      $redirectURL = WC()->api_request_url( 'FLW_WC_Payment_Gateway' ).'?order_id='.$order_id;
      $publicKey = $this->public_key; // Remember to change this to your live public keys when going live
      $secretKey = $this->secret_key; // Remember to change this to your live secret keys when going live
      if($this->go_live === 'yes'){
        $env = 'live';
      }else{
        $env = 'staging';
      }
      $ref = uniqid("WOOC_".get_bloginfo('name')."_" . $order_id."_".time()."_");
      $overrideRef = true;
      
      $payment = new Rave($publicKey, $secretKey, $ref, $env, $overrideRef);
        
        if(urldecode( $_GET['rave_id'] )){
          
          if($this->modal_logo){
            $rave_m_logo = $this->modal_logo;
          }

          //set the currency to route to their countries
          switch ($order->get_order_currency()) {
            case 'KES':
              $this->country = 'KE';
              break;
            case 'GHS':
              $this->country = 'GH';
              break;
            case 'ZAR':
              $this->country = 'ZA';
              break;
            
            default:
              $this->country = 'NG';
              break;
          }
          
          // Make payment
          $payment
          ->eventHandler(new myEventHandler($order))
          ->setAmount($order->order_total)
          ->setPaymentMethod($this->payment_method) // value can be card, account or both
          ->setDescription("Payment for Order ID: $order_id on ". get_bloginfo('name'))
          ->setLogo($rave_m_logo)
          ->setTitle(get_bloginfo('name'))
          ->setCountry($this->country)
          ->setCurrency($order->get_order_currency())
          ->setEmail($order->billing_email)
          ->setFirstname($order->billing_first_name)
          ->setLastname($order->billing_last_name)
          ->setPhoneNumber($order->billing_phone)
          ->setPaymentPlan($this->payment_plan)
          ->setRedirectUrl($redirectURL)
          // ->setMetaData(array('metaname' => 'SomeDataName', 'metavalue' => 'SomeValue')) // can be called multiple times. Uncomment this to add meta datas
          // ->setMetaData(array('metaname' => 'SomeOtherDataName', 'metavalue' => 'SomeOtherValue')) // can be called multiple times. Uncomment this to add meta datas
          ->initialize(); 
          die();
        }else{
          if(urldecode($_GET['cancelled']) && urldecode($_GET['txref'])){
              // Handle canceled payments
              $payment
              ->eventHandler(new myEventHandler($order))
              ->requeryTransaction(urldecode($_GET['txref']))
              ->paymentCanceled(urldecode($_GET['txref']));
              
              $redirect_url = $this->get_return_url( $order );
              header("Location: ".$redirect_url);
              die(); 
          }elseif(urldecode($_GET['txref'])){
              // Handle completed payments
              $payment->logger->notice('Payment completed. Now requerying payment.');
              
              $payment
              ->eventHandler(new myEventHandler($order))
              ->requeryTransaction(urldecode($_GET['txref']));
              
              $redirect_url = $this->get_return_url( $order );
              header("Location: ".$redirect_url);
              die(); 
          }else{
              die();
          }
      }
    }
  }
  
?>

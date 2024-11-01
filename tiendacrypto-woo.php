<?php
/**
 * Plugin Name: TiendaCrypto para WooCommerce
 * Plugin URI: https://tiendacrypto.com/
 * Description: Con el Plugin para WooCommerce de la API Commerce de TiendaCrypto, vas a  poder cobrar con criptomonedas de la forma más sencilla. 
 * Author: TiendaCrypto
 * Author URI: https://tiendacrypto.com/
 * Version: 1.2.1
 * WC tested up to: 7.1.0
 * Text Domain: wc-gateway-tiendacrypto
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2010-2022 Wanderlust Web Design
 *
 *
 * @package   WC-Gateway-TiendaCrypto
 * @author    Wanderlust Web Design
 * @copyright Copyright (c) 2010-2022, Wanderlust Web Design
 *
 */

  if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    add_action( 'plugins_loaded', 'tiendacrypto_init_gateway_class' );
    add_action( 'updated_option', 'add_webhookcrypto_function', 10, 3);
    add_filter( 'woocommerce_payment_gateways', 'tiendacrypto_add_gateway_class' );

    function tiendacrypto_add_gateway_class( $gateways ) {
        $gateways[] = 'WC_TiendaCrypto_Gateway'; 
        return $gateways;
    }

    function add_webhookcrypto_function(){
      if(isset($_POST['woocommerce_tiendacrypto_gateway_enabled']) && isset($_POST['woocommerce_tiendacrypto_gateway_client_id']) ) {
        $webhooks = get_option('tiendacrypto_webhook');
        if(empty($webhooks)){
          $bodywebhook = [
            'event_url' => site_url() .'/?wc-api=tiendacrypto'
          ];
          $bodywebhook = wp_json_encode( $bodywebhook );              
          $optionswebhook = [
            'body'        => $bodywebhook,
            'headers'     => [
              'tc-api-key' => $_POST['woocommerce_tiendacrypto_gateway_client_id'],
              'Content-Type' => 'application/json',
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
          ];
          $respuestawebhook =  wp_remote_post( 'https://api-commerce.tiendacrypto.com/v1/webhook', $optionswebhook );    
          update_option( 'tiendacrypto_webhook', $respuestawebhook );      
        }
      }
    }

    function tiendacrypto_init_gateway_class() {

        class WC_TiendaCrypto_Gateway extends WC_Payment_Gateway {

            public function __construct() {

                $this->id = 'tiendacrypto_gateway';
                $this->icon = apply_filters( 'woocommerce_tiendacrypto_icon', plugins_url( 'tienda-crypto-woo/img/logo.png', plugin_dir_path( __FILE__ ) ) );            
                $this->has_fields = false; 
                $this->method_title = 'TiendaCrypto';
                $this->method_description = 'Con el Plugin para WooCommerce de la API Commerce de TiendaCrypto, vas a  poder cobrar con criptomonedas de la forma más sencilla.';
                $this->supports = array(
                    'products'
                );

                $this->init_form_fields();
                $this->init_settings();
                $this->title = 'Pagá con criptomonedas ';
                $this->description = 'La acreditación es inmediata. Te llevaremos a una página donde podrás elegir la red y moneda que quieras.';
                $this->enabled = $this->get_option( 'enabled' );
                $this->user_name = $this->get_option( 'user_name' );
                $this->client_id =  $this->get_option( 'client_id' ) ;
                $this->client_secret_id =  $this->get_option( 'client_secret_id' ) ;

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_tiendacrypto', array( $this, 'webhook' ) );
                add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
             }

            public function init_form_fields(){
                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => 'Enable/Disable',
                        'label'       => 'Enable TiendaCrypto',
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no'
                    ),
                    'client_id' => array(
                        'title'       => 'API keys',
                        'type'        => 'text'
                    ),
                    'secret_id' => array(
                        'title'       => 'Webhook secret',
                        'type'        => 'text'
                    ) 

                );
            }

            public function payment_fields() {
                if ( $this->description ) {
                    echo wpautop( wp_kses_post( $this->description ) );
                }
            }

            public function process_payment( $order_id ) {
                global $woocommerce;
                $order = wc_get_order( $order_id );
                $data  = $order->get_data();
                $currency = get_option('woocommerce_currency') ;
                $shipping_data = $order->get_items( 'shipping' );
                $nombre = '';
                $productos = array();
                $items = $order->get_items();
                foreach( $items as $item ) {         
                   
                    if($item['variation_id'] > 0){
                      $product = wc_get_product( $item['variation_id'] );
                      $nombre = $product->get_name();
                      $precio_ok += $product->get_price() * $item['quantity'];
                      $precios = $product->get_price() * $item['quantity'];
                      $image = wp_get_attachment_image_src( get_post_thumbnail_id( $item['variation_id'] ), 'single-post-thumbnail' ); 
                      $productos[] = array(
                        "id" => 1,
                        "name" => $nombre,
                        "price" => $precios,
                        "quantity" => $item['quantity'],
                        "image_url" => $image[0],
                        "desc" => "",
                      );                    
                    } else { 
                      $product = wc_get_product( $item['product_id'] );
                      $nombre = $product->get_name();
                      $precio_ok += $product->get_price() * $item['quantity'];
                      $precios = $product->get_price() * $item['quantity'];
                      $image = wp_get_attachment_image_src( get_post_thumbnail_id( $item['product_id'] ), 'single-post-thumbnail' ); 
                      $productos[] = array(
                        "id" => 1,
                        "name" => $nombre,
                        "price" => $precios,
                        "quantity" => $item['quantity'],
                        "image_url" => $image[0],
                        "desc" => "",
                      );
                    }
                }


              if(is_array($shipping_data)){
                foreach($shipping_data as $k=>$sm){
                  if($sm['total'] > 1){
                    $precio_ok +=  $sm['total'];
                    $productos[] = array(
                        "id" => 1,
                        "name" => strtoupper($sm['method_title']),
                        "price" => $sm['total'],
                        "quantity" => 1,
                    );
                  }
                }
              }       

              $url = 'https://api-commerce.tiendacrypto.com/v1/charge';
              $body = [
                  'price' =>  array(
                    'amount' => $precio_ok,
                    'currency' => $currency,
                  ),
                  'redirect_url' => $order->get_checkout_order_received_url(),
                  'cancel_url' => $order->get_cancel_order_url(),
                  'metadata' => array(
                    'plugin' => 'woocommerce',
                    'items' => $productos,
                    'order_id' => $order_id,
                    'client' => $data['billing'],                  
                  )
              ];
              $body = wp_json_encode( $body );
    
              $options = [
                  'body'        => $body,
                  'headers'     => [
                    'tc-api-key' => $this->client_id,
                    'Content-Type' => 'application/json',
                  ],
                  'timeout'     => 60,
                  'redirection' => 5,
                  'blocking'    => true,
                  'httpversion' => '1.0',
                  'sslverify'   => false,
                  'data_format' => 'body',
              ];

              $respuesta =  wp_remote_post( $url, $options );  
              update_post_meta($order_id, 'checkout', $response);

             if ( !is_wp_error( $respuesta ) ) {
                $response = json_decode($respuesta['body']);
                if($response->id){
                  $order->reduce_order_stock();
                  WC()->cart->empty_cart();
                  return array(
                    'result' => 'success',
                    'redirect' => 'https://checkout.tiendacrypto.com/' . $response->id
                  );            
                }
              } 
            }

            public function thankyou_page($order_id) {
              $order = wc_get_order( $order_id );
              if($_GET['key']){
                $order->add_order_note(
                  'Tienda Crypto: ' .
                  __( 'Payment pending.', 'wc-gateway-tiendacrypto' )
                );
              }          
            }

            public function webhook() { 
              header( 'HTTP/1.1 200 OK' );
              $postBody = file_get_contents('php://input');
              $responseipn = json_decode($postBody);
              if($responseipn->event_type == 'charge:completed'){
                $order_id = $responseipn->charge->order_id;
                if($order_id){
                  $order = wc_get_order( $order_id );
                  $headers = getallheaders();
                  $datas = file_get_contents('php://input');
                  $hash = hash_hmac('sha256', $postBody, $this->secret_id);
                  if($headers['x-tc-webhook-signature'] == $hash){
                    update_post_meta($order_id, 'tiendacrypto_response', 'VALIDDO');    
                    $order->add_order_note(
                      'Tienda Crypto: ' .
                      __( 'Payment approved.', 'wc-gateway-tiendacrypto' )
                    );
                    $order->payment_complete();

                  } else {
                    update_post_meta($order_id, 'tiendacrypto_response', 'NO VALIDO');    
                  }
                  update_post_meta($order_id, 'tiendacrypto_response_ipn', $postBody);              
                }
              } 
              if($responseipn->event_type == 'charge:resolved'){
                $order_id = $responseipn->charge->order_id;
                if($order_id){
                  $order = wc_get_order( $order_id );
                  $headers = getallheaders();
                  $datas = file_get_contents('php://input');
                  $hash = hash_hmac('sha256', $postBody, $this->secret_id);
                  if($headers['x-tc-webhook-signature'] == $hash){
                    update_post_meta($order_id, 'tiendacrypto_response', 'VALIDDO');    
                    $order->add_order_note(
                      'Tienda Crypto: ' .
                      __( 'Payment approved.', 'wc-gateway-tiendacrypto' )
                    );
                    $order->payment_complete();

                  } else {
                    update_post_meta($order_id, 'tiendacrypto_response', 'NO VALIDO');    
                  }
                  update_post_meta($order_id, 'tiendacrypto_response_ipn', $postBody);              
                }
              }                         
            }
        }
    }
  }
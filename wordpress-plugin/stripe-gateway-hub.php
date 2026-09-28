<?php
/**
 * Plugin Name: Stripe Gateway Hub
 * Plugin URI:  https://payments.example.com
 * Description: Connect your WordPress site to Stripe Gateway Hub for centralized payments.
 * Version:     1.0.0
 * Author:      Stripe Gateway Hub
 * License:     GPL-2.0+
 * Text Domain: stripe-gateway-hub
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SGH_VERSION', '1.0.0' );
define( 'SGH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SGH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ──────────────────────────────────────────────
// Admin Settings Page
// ──────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_options_page(
        'Stripe Gateway Hub',
        'Stripe Gateway Hub',
        'manage_options',
        'stripe-gateway-hub',
        'sgh_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'sgh_settings_group', 'sgh_api_key',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'sgh_settings_group', 'sgh_hub_url',  [ 'sanitize_callback' => 'esc_url_raw' ] );
    register_setting( 'sgh_settings_group', 'sgh_success_page', [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'sgh_settings_group', 'sgh_cancel_page',  [ 'sanitize_callback' => 'absint' ] );
} );

function sgh_settings_page() {
    $hub_url = get_option( 'sgh_hub_url', 'https://payments.example.com' );
    $api_key = get_option( 'sgh_api_key', '' );
    $success = get_option( 'sgh_success_page', 0 );
    $cancel  = get_option( 'sgh_cancel_page', 0 );

    // Test connection
    $connection_status = '';
    if ( ! empty( $api_key ) && ! empty( $hub_url ) ) {
        $resp = wp_remote_post( trailingslashit( $hub_url ) . 'api/plugin/connect', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ],
            'timeout' => 10,
        ] );
        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            $connection_status = '<div class="notice notice-success inline"><p>✓ Connected to <strong>' . esc_html( $body['tenant']['name'] ?? 'Hub' ) . '</strong></p></div>';
        } else {
            $connection_status = '<div class="notice notice-error inline"><p>✗ Connection failed. Check your API key and Hub URL.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Stripe Gateway Hub Settings</h1>
        <?php echo $connection_status; ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'sgh_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="sgh_hub_url">Hub URL</label></th>
                    <td>
                        <input type="url" id="sgh_hub_url" name="sgh_hub_url"
                               value="<?php echo esc_attr( $hub_url ); ?>"
                               class="regular-text" placeholder="https://payments.example.com" />
                        <p class="description">URL of your Stripe Gateway Hub installation.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sgh_api_key">API Key</label></th>
                    <td>
                        <input type="password" id="sgh_api_key" name="sgh_api_key"
                               value="<?php echo esc_attr( $api_key ); ?>"
                               class="regular-text" placeholder="sgw_..." />
                        <p class="description">Your tenant API key from the Gateway Hub dashboard.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sgh_success_page">Success Page</label></th>
                    <td>
                        <?php wp_dropdown_pages( [
                            'name'             => 'sgh_success_page',
                            'selected'         => $success,
                            'show_option_none' => '— Select Page —',
                        ] ); ?>
                        <p class="description">Page shown after successful payment. Add <code>[sgh_payment_success]</code> shortcode here.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sgh_cancel_page">Cancel Page</label></th>
                    <td>
                        <?php wp_dropdown_pages( [
                            'name'             => 'sgh_cancel_page',
                            'selected'         => $cancel,
                            'show_option_none' => '— Select Page —',
                        ] ); ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save & Test Connection' ); ?>
        </form>

        <hr />
        <h2>Shortcode Usage</h2>
        <table class="widefat">
            <thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td><code>[sgh_checkout price_id="price_123"]</code></td><td>Checkout button for a specific price</td></tr>
                <tr><td><code>[sgh_checkout price_id="price_123" label="Buy Now" description="Premium Plan"]</code></td><td>Custom button label and payment description</td></tr>
                <tr><td><code>[sgh_payment_success]</code></td><td>Show order confirmation on success page</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}

// ──────────────────────────────────────────────
// Shortcode: [sgh_checkout price_id="..." label="..." description="..."]
// ──────────────────────────────────────────────

add_shortcode( 'sgh_checkout', 'sgh_checkout_shortcode' );

function sgh_checkout_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'price_id'    => '',
        'label'       => 'Pay Now',
        'description' => '',
        'class'       => 'sgh-btn',
    ], $atts, 'sgh_checkout' );

    if ( empty( $atts['price_id'] ) ) {
        return '<p style="color:red;">Error: price_id is required in [sgh_checkout] shortcode.</p>';
    }

    $nonce = wp_create_nonce( 'sgh_checkout_nonce' );
    ob_start();
    ?>
    <div class="sgh-checkout-wrapper" data-price="<?php echo esc_attr( $atts['price_id'] ); ?>"
         data-description="<?php echo esc_attr( $atts['description'] ); ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <form class="sgh-checkout-form" onsubmit="sghHandleCheckout(event, this)">
            <div class="sgh-field">
                <label for="sgh_email_<?php echo esc_attr( $nonce ); ?>">Email address</label>
                <input type="email" id="sgh_email_<?php echo esc_attr( $nonce ); ?>"
                       name="email" required placeholder="your@email.com" />
            </div>
            <div class="sgh-field">
                <label for="sgh_name_<?php echo esc_attr( $nonce ); ?>">Full name (optional)</label>
                <input type="text" id="sgh_name_<?php echo esc_attr( $nonce ); ?>"
                       name="name" placeholder="John Smith" />
            </div>
            <button type="submit" class="<?php echo esc_attr( $atts['class'] ); ?>">
                <?php echo esc_html( $atts['label'] ); ?>
            </button>
            <div class="sgh-error" style="display:none;color:red;margin-top:8px;"></div>
        </form>
    </div>

    <style>
        .sgh-checkout-wrapper { max-width: 400px; }
        .sgh-field { margin-bottom: 12px; }
        .sgh-field label { display: block; font-size: 14px; margin-bottom: 4px; font-weight: 600; }
        .sgh-field input { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        .sgh-field input:focus { outline: none; border-color: #635bff; box-shadow: 0 0 0 3px rgba(99,91,255,0.15); }
        .sgh-btn { background: #635bff; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .sgh-btn:hover { background: #5147e5; }
        .sgh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
    <?php
    return ob_get_clean();
}

// ──────────────────────────────────────────────
// Shortcode: [sgh_payment_success]
// ──────────────────────────────────────────────

add_shortcode( 'sgh_payment_success', 'sgh_payment_success_shortcode' );

function sgh_payment_success_shortcode() {
    $order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( $_GET['order_id'] ) : '';

    if ( empty( $order_id ) ) {
        return '<p>Thank you for your payment!</p>';
    }

    $api_key = get_option( 'sgh_api_key', '' );
    $hub_url = get_option( 'sgh_hub_url', '' );

    if ( empty( $api_key ) || empty( $hub_url ) ) {
        return '<p>Thank you for your payment!</p>';
    }

    $resp = wp_remote_get(
        trailingslashit( $hub_url ) . 'api/plugin/order-status?order_id=' . urlencode( $order_id ),
        [
            'headers' => [
                'X-API-Key' => $api_key,
            ],
            'timeout' => 10,
        ]
    );

    if ( is_wp_error( $resp ) ) {
        return '<p>Thank you for your payment!</p>';
    }

    $order = json_decode( wp_remote_retrieve_body( $resp ), true );

    ob_start();
    ?>
    <div class="sgh-success-box" style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:8px;padding:24px;max-width:480px;">
        <h3 style="margin-top:0;color:#276749;">✓ Payment Successful</h3>
        <?php if ( ! empty( $order['product']['name'] ) ) : ?>
            <p><strong>Product:</strong> <?php echo esc_html( $order['product']['name'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $order['amount'] ) ) : ?>
            <p><strong>Amount:</strong>
                <?php echo esc_html( strtoupper( $order['currency'] ?? 'usd' ) ); ?>
                <?php echo esc_html( number_format( $order['amount'] / 100, 2 ) ); ?>
            </p>
        <?php endif; ?>
        <?php if ( ! empty( $order['customerEmail'] ) ) : ?>
            <p><strong>Email:</strong> <?php echo esc_html( $order['customerEmail'] ); ?></p>
        <?php endif; ?>
        <p style="font-size:13px;color:#555;">Order ID: <code><?php echo esc_html( $order_id ); ?></code></p>
    </div>
    <?php
    return ob_get_clean();
}

// ──────────────────────────────────────────────
// AJAX handler: create checkout session
// ──────────────────────────────────────────────

add_action( 'wp_ajax_sgh_create_checkout',        'sgh_ajax_create_checkout' );
add_action( 'wp_ajax_nopriv_sgh_create_checkout', 'sgh_ajax_create_checkout' );

function sgh_ajax_create_checkout() {
    check_ajax_referer( 'sgh_checkout_nonce', 'nonce' );

    $email       = sanitize_email( $_POST['email'] ?? '' );
    $name        = sanitize_text_field( $_POST['name'] ?? '' );
    $price_id    = sanitize_text_field( $_POST['price_id'] ?? '' );
    $description = sanitize_text_field( $_POST['description'] ?? '' );

    if ( ! is_email( $email ) ) {
        wp_send_json_error( 'Invalid email address' );
    }

    if ( empty( $price_id ) ) {
        wp_send_json_error( 'Missing price_id' );
    }

    $api_key    = get_option( 'sgh_api_key', '' );
    $hub_url    = get_option( 'sgh_hub_url', '' );
    $success_id = get_option( 'sgh_success_page', 0 );
    $cancel_id  = get_option( 'sgh_cancel_page', 0 );

    if ( empty( $api_key ) || empty( $hub_url ) ) {
        wp_send_json_error( 'Stripe Gateway Hub is not configured. Please contact the site administrator.' );
    }

    $success_url = $success_id ? get_permalink( $success_id ) : home_url( '/payment-success' );
    $cancel_url  = $cancel_id  ? get_permalink( $cancel_id )  : wp_get_referer() ?: home_url();

    $payload = [
        'priceId'       => $price_id,
        'customerEmail' => $email,
        'customerName'  => $name,
        'successUrl'    => $success_url,
        'cancelUrl'     => $cancel_url,
        'description'   => $description ?: sprintf( 'WordPress purchase on %s', get_bloginfo( 'name' ) ),
        'metadata'      => [
            'source'  => 'wordpress',
            'site'    => get_bloginfo( 'name' ),
            'siteUrl' => home_url(),
        ],
    ];

    $resp = wp_remote_post(
        trailingslashit( $hub_url ) . 'api/plugin/create-checkout',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ]
    );

    if ( is_wp_error( $resp ) ) {
        wp_send_json_error( 'Failed to connect to payment hub: ' . $resp->get_error_message() );
    }

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        wp_send_json_error( $body['error'] ?? 'Payment session creation failed' );
    }

    wp_send_json_success( [
        'checkoutUrl' => $body['checkoutUrl'],
        'orderId'     => $body['orderId'],
    ] );
}

// ──────────────────────────────────────────────
// Enqueue frontend JavaScript
// ──────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'sgh-checkout',
        SGH_PLUGIN_URL . 'assets/checkout.js',
        [],
        SGH_VERSION,
        true
    );
    wp_localize_script( 'sgh-checkout', 'sghAjax', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ] );
} );

// ──────────────────────────────────────────────
// Plugin activation: create assets dir
// ──────────────────────────────────────────────

register_activation_hook( __FILE__, function () {
    $assets_dir = SGH_PLUGIN_DIR . 'assets';
    if ( ! file_exists( $assets_dir ) ) {
        wp_mkdir_p( $assets_dir );
    }
    // Write the checkout JS inline
    $js = <<<'JS'
async function sghHandleCheckout(event, form) {
    event.preventDefault();
    const wrapper = form.closest('[data-price]');
    const btn = form.querySelector('button[type="submit"]');
    const errDiv = form.querySelector('.sgh-error');
    const originalLabel = btn.textContent;

    btn.disabled = true;
    btn.textContent = 'Processing...';
    errDiv.style.display = 'none';

    const email = form.querySelector('input[name="email"]').value;
    const name = form.querySelector('input[name="name"]')?.value || '';
    const priceId = wrapper.dataset.price;
    const description = wrapper.dataset.description || '';
    const nonce = wrapper.dataset.nonce;

    const formData = new FormData();
    formData.append('action', 'sgh_create_checkout');
    formData.append('nonce', nonce);
    formData.append('email', email);
    formData.append('name', name);
    formData.append('price_id', priceId);
    formData.append('description', description);

    try {
        const resp = await fetch(sghAjax.ajaxUrl, { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.success && data.data.checkoutUrl) {
            window.location.href = data.data.checkoutUrl;
        } else {
            throw new Error(data.data || 'Unexpected error');
        }
    } catch (err) {
        errDiv.textContent = err.message || 'Something went wrong. Please try again.';
        errDiv.style.display = 'block';
        btn.disabled = false;
        btn.textContent = originalLabel;
    }
}
JS;
    file_put_contents( $assets_dir . '/checkout.js', $js );
} );

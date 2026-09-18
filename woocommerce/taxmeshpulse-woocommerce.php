<?php
/**
 * Plugin Name: TaxMeshPulse for WooCommerce
 * Plugin URI: https://taxmeshpulse.ctar.tech
 * Description: Integrasi Otomatis Pajak PPN 12% Coretax DJP Indonesia & Global Tax Engine untuk WooCommerce. Dilengkapi penerbitan e-Faktur resmi & QR Code DJP.
 * Version: 1.0.0
 * Author: CTARTech Ecosystem
 * Author URI: https://ctar.tech
 * Text Domain: taxmeshpulse-wc
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class TaxMeshPulse_WooCommerce
{
    private static $instance = null;
    private $api_key;
    private $environment;
    private $base_url = 'https://taxmeshpulse.ctar.tech';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->api_key = get_option('tmp_api_key', '');
        $this->environment = get_option('tmp_environment', 'sandbox');

        // Admin Settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Checkout Custom Fields (NPWP / NIK 16 Digit)
        add_action('woocommerce_after_order_notes', [$this, 'add_npwp_checkout_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_npwp_order_meta']);

        // Tax Calculation in Cart
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_taxmeshpulse_tax'], 20, 1);

        // Auto-File e-Faktur on Order Complete
        add_action('woocommerce_order_status_completed', [$this, 'file_efaktur_on_completed'], 10, 1);

        // Display e-Faktur Info on Thank You Page
        add_action('woocommerce_thankyou', [$this, 'display_efaktur_thankyou'], 10, 1);
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            'TaxMeshPulse Coretax DJP',
            'TaxMeshPulse (PPN 12%)',
            'manage_options',
            'taxmeshpulse-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('tmp_settings_group', 'tmp_api_key');
        register_setting('tmp_settings_group', 'tmp_environment');
        register_setting('tmp_settings_group', 'tmp_auto_efaktur');
        register_setting('tmp_settings_group', 'tmp_rate_percent');
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>⚡ TaxMeshPulse - Pengaturan Kepatuhan Pajak & Coretax DJP</h1>
            <p>Hubungkan toko WooCommerce Anda ke infrastruktur otomatis <strong>TaxMeshPulse</strong> untuk penghitungan PPN 12% UU HPP dan e-Faktur Coretax.</p>
            <form method="post" action="options.php">
                <?php settings_fields('tmp_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Lingkungan (Environment)</th>
                        <td>
                            <select name="tmp_environment">
                                <option value="sandbox" <?php selected(get_option('tmp_environment'), 'sandbox'); ?>>🧪 Sandbox (Testing)</option>
                                <option value="live" <?php selected(get_option('tmp_environment'), 'live'); ?>>⚡ Live (Production)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key TaxMeshPulse</th>
                        <td>
                            <input type="password" name="tmp_api_key" value="<?php echo esc_attr(get_option('tmp_api_key')); ?>" class="regular-text" placeholder="tax_live_... atau tax_test_...">
                            <p class="description">Dapatkan API Key di <a href="https://taxmeshpulse.ctar.tech" target="_blank">Dashboard Portal Tenant TaxMeshPulse</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tarif PPN Standar</th>
                        <td>
                            <input type="number" step="0.1" name="tmp_rate_percent" value="<?php echo esc_attr(get_option('tmp_rate_percent', '12')); ?>" style="width:80px;"> %
                            <p class="description">Standar tarif PPN Indonesia UU HPP: <strong>12%</strong>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Otomatisasi e-Faktur DJP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tmp_auto_efaktur" value="1" <?php checked(get_option('tmp_auto_efaktur', '1'), '1'); ?>>
                                Terbitkan e-Faktur & QR Code DJP secara otomatis saat status order menjadi "Completed".
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Simpan Pengaturan'); ?>
            </form>
        </div>
        <?php
    }

    public function add_npwp_checkout_field($checkout)
    {
        echo '<div id="tmp_npwp_checkout_field"><h3>📋 Faktur Pajak DJP (Opsional)</h3>';
        woocommerce_form_field('billing_npwp_nik', [
            'type'        => 'text',
            'class'       => ['form-row-wide'],
            'label'       => 'NPWP / NIK 16 Digit (Untuk e-Faktur Resmi)',
            'placeholder' => 'Contoh: 0123456789012345',
            'required'    => false,
        ], $checkout->get_value('billing_npwp_nik'));
        echo '</div>';
    }

    public function save_npwp_order_meta($order_id)
    {
        if (!empty($_POST['billing_npwp_nik'])) {
            update_post_meta($order_id, '_billing_npwp_nik', sanitize_text_field($_POST['billing_npwp_nik']));
        }
    }

    public function apply_taxmeshpulse_tax($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $rate = floatval(get_option('tmp_rate_percent', 12));
        $subtotal = $cart->get_subtotal();
        if ($subtotal > 0 && $rate > 0) {
            $tax_amount = round(($subtotal * $rate) / 100);
            $cart->add_fee("PPN {$rate}% (Coretax DJP)", $tax_amount, false);
        }
    }

    public function file_efaktur_on_completed($order_id)
    {
        $api_key = get_option('tmp_api_key');
        if (empty($api_key) || !get_option('tmp_auto_efaktur', '1')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        // Prevent duplicate filing
        if (get_post_meta($order_id, '_tmp_efaktur_nsfp', true)) return;

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'sku' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'unit_price' => floatval($item->get_total() / max(1, $item->get_quantity())),
            ];
        }

        $npwp = get_post_meta($order_id, '_billing_npwp_nik', true);
        $payload = [
            'reference_id' => 'WC-' . $order->get_id(),
            'currency' => $order->get_currency(),
            'customer' => [
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'country' => $order->get_billing_country() ?: 'ID',
                'type' => !empty($npwp) ? 'business' : 'individual',
                'npwp' => $npwp ?: '0000000000000000',
                'address' => $order->get_billing_address_1(),
            ],
            'items' => $items,
        ];

        // Call TaxMeshPulse API
        $response = wp_remote_post("{$this->base_url}/v1/tax/invoices", [
            'headers' => [
                'Authorization' => "Bearer {$api_key}",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['data']['compliance']['nsfp'])) {
                update_post_meta($order_id, '_tmp_efaktur_nsfp', sanitize_text_field($body['data']['compliance']['nsfp']));
                update_post_meta($order_id, '_tmp_efaktur_ntte', sanitize_text_field($body['data']['compliance']['ntte']));
                update_post_meta($order_id, '_tmp_efaktur_qr', esc_url_raw($body['data']['compliance']['qr_code_url']));
                $order->add_order_note("⚡ e-Faktur berhasil diterbitkan via TaxMeshPulse. NSFP: {$body['data']['compliance']['nsfp']}");
            }
        }
    }

    public function display_efaktur_thankyou($order_id)
    {
        $nsfp = get_post_meta($order_id, '_tmp_efaktur_nsfp', true);
        $qr = get_post_meta($order_id, '_tmp_efaktur_qr', true);
        if (!empty($nsfp)) {
            echo '<div style="background:#0f172a; border:1px solid #10b981; border-radius:8px; padding:16px; margin:20px 0; color:#f8fafc;">';
            echo '<h4 style="color:#10b981; margin-top:0;">🇮🇩 Bukti e-Faktur Pajak Resmi (DJP Coretax)</h4>';
            echo "<p><strong>Nomor Seri Faktur Pajak (NSFP):</strong> <code>{$nsfp}</code></p>";
            if (!empty($qr)) {
                echo "<p><a href='{$qr}' target='_blank' style='display:inline-block; background:#10b981; color:#0f172a; padding:8px 14px; border-radius:6px; font-weight:bold; text-decoration:none;'>📱 Buka QR Code Validasi DJP ↗</a></p>";
            }
            echo '</div>';
        }
    }
}

add_action('plugins_loaded', ['TaxMeshPulse_WooCommerce', 'get_instance']);

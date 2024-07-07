<?php
/*
Plugin Name: Tedarikçi Yönetimi Eklentisi
Description: WooCommerce için özelleştirilmiş tedarikçi yönetimi eklentisi.
Version: 1.0
Author: Mahmut Yüksel MERT
Text Domain: supplier-managment-for-woocommerce
*/

function create_supplier_post_type() {
    $labels = array(
        'name'               => __('Tedarikçiler', 'supplier-management-for-woocommerce'),
        'singular_name'      => __('Tedarikçi', 'supplier-management-for-woocommerce'),
        'add_new'            => __('Yeni Ekle', 'supplier-management-for-woocommerce'),
        'add_new_item'       => __('Yeni Tedarikçi Ekle', 'supplier-management-for-woocommerce'),
        'edit_item'          => __('Tedarikçiyi Düzenle', 'supplier-management-for-woocommerce'),
        'new_item'           => __('Yeni Tedarikçi', 'supplier-management-for-woocommerce'),
        'all_items'          => __('Tüm Tedarikçiler', 'supplier-management-for-woocommerce'),
        'view_item'          => __('Tedarikçiyi Gör', 'supplier-management-for-woocommerce'),
        'search_items'       => __('Tedarikçi Ara', 'supplier-management-for-woocommerce'),
        'not_found'          => __('Tedarikçi bulunamadı', 'supplier-management-for-woocommerce'),
        'not_found_in_trash' => __('Çöp Kutusu\'nda tedarikçi bulunamadı', 'supplier-management-for-woocommerce'),
        'menu_name'          => __('Tedarikçiler', 'supplier-management-for-woocommerce')
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'supplier'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title'),
    );

    register_post_type('supplier', $args);
    add_action('add_meta_boxes', 'add_supplier_email_meta_box');
}

add_action('init', 'create_supplier_post_type');

function add_supplier_email_meta_box() {
    add_meta_box('supplier_email_meta_box', __('Tedarikçi E-Posta', 'supplier-management-for-woocommerce'), 'render_supplier_email_meta_box', 'supplier', 'normal', 'default');
}

// Tedarikçi meta kutusuna e-posta alanını ekleyelim
function render_supplier_email_meta_box($post) {
    $supplier_email = get_post_meta($post->ID, '_supplier_email', true);
    ?>
    <p>
        <label for="supplier_email"><?php _e('Tedarikçi E-Posta:', 'supplier-management-for-woocommerce'); ?></label>
        <input type="email" id="supplier_email" name="supplier_email" placeholder="test@localhost.com" value="<?php echo esc_attr($supplier_email); ?>">
    </p>
    <?php
}

// Tedarikçi e-posta alanını kaydedelim ve benzersiz olmasını sağlayalım
function save_supplier_meta_data($post_id) {
    if (array_key_exists('supplier_email', $_POST)) {
        $supplier_email = sanitize_email($_POST['supplier_email']);

        if ( !empty($_POST['supplier_email']) ) {
            update_post_meta($post_id, '_supplier_email', $supplier_email);
        }
    }
}
add_action('save_post_supplier', 'save_supplier_meta_data');

add_action('admin_notices', function () {
    if (isset($_GET['supplier_email_error'])) {
        ?>
        <div class="error">
            <p><?php echo urldecode($_GET['supplier_email_error']); ?></p>
        </div>
        <?php
    }
});


function add_supplier_meta_box() {
    add_meta_box(
        'supplier_meta_box',
        __('Tedarikçi', 'supplier-management-for-woocommerce'),
        'render_supplier_meta_box',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_supplier_meta_box');

function render_supplier_meta_box($post) {
    $args = array(
        'post_type' => 'supplier',
        'posts_per_page' => -1,
    );
    $selected_supplier_id = get_post_meta($post->ID, '_product_supplier', true);
    $suppliers = get_posts($args);
    if ($suppliers) {
        echo '<select id="product_supplier" class="form-control" name="product_supplier">';
        echo '<option value="0">' . __('Bir tedarikçi seçin', 'supplier-management-for-woocommerce') . '</option>';
        foreach ($suppliers as $supplier) {
            $selected = ($selected_supplier_id == $supplier->ID) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($supplier->ID) . '" ' . $selected . '>' . esc_html($supplier->post_title) . '</option>';
        }
        echo '</select>';
    }
}

function save_supplier_product_meta_data($post_id) {
    if (array_key_exists('product_supplier', $_POST)) {
        update_post_meta(
            $post_id,
            '_product_supplier',
            sanitize_text_field($_POST['product_supplier'])
        );
    }
}
add_action('save_post_product', 'save_supplier_product_meta_data');

add_action( 'woocommerce_email_order_details', 'remove_order_details', 1, 4);
add_action( 'woocommerce_email_order_details','action_woocommerce_email_order_details', 10, 4); 

function action_woocommerce_email_order_details($order, $sent_to_admin, $plain_text, $email)
{
	
	if (!$sent_to_admin) {
        // Müşteriye giden e-postalarda herhangi bir değişiklik yapmak istemiyoruz.
        return;
    }
	
    $text_align = is_rtl() ? 'right' : 'left';

    $products = $order->get_items();
    $grouped_products = array();

    // Ürünleri tedarikçilerine göre gruplandıralım
    foreach ( $products as $item_id => $item ) {
        $supplier_id = get_post_meta( $item->get_product_id(), '_product_supplier', true );
        if ( ! isset( $grouped_products[ $supplier_id ] ) ) {
            $grouped_products[ $supplier_id ] = array();
        }
        $grouped_products[ $supplier_id ][] = $item;
    }

    ?>
    <h2>
        <?php
        if ($sent_to_admin) {
            $before = '<a class="link" href="' . esc_url($order->get_edit_order_url()) . '">';
            $after = '</a>';
        } else {
            $before = '';
            $after = '';
        }
        /* translators: %s: Order ID. */
        echo wp_kses_post($before . sprintf(__('[Order #%s]', 'woocommerce') . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format('c'), wc_format_datetime($order->get_date_created())));
        ?>
    </h2>

    <div style="margin-bottom: 40px;">
        <?php 
        foreach ( $grouped_products as $supplier_id => $supplier_products ) {

        $supplier = get_post($supplier_id);
        $supplier_name = $supplier ? $supplier->post_title : __('Bilinmeyen Tedarikçi', 'supplier-management-for-woocommerce');
        $supplier_email = get_post_meta($supplier_id, '_supplier_email', true);

        echo '<h3>Tedarikçi: ' . $supplier_name . '</h3>';
        echo '<p>E-Posta: '.$supplier_email.'<p>';
        ?>
        <table class="td" cellspacing="0" cellpadding="6"
               style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
            <thead>
            <tr>
                <th class="td" scope="col"
                    style="text-align:<?php echo esc_attr($text_align); ?>;"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                <th class="td" scope="col"
                    style="text-align:<?php echo esc_attr($text_align); ?>;"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                <th class="td" scope="col"
                    style="text-align:<?php echo esc_attr($text_align); ?>;"><?php esc_html_e('Price', 'woocommerce'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($order->get_items() as $item_id => $item) { 
                $product_supplier_id = get_post_meta( $item->get_product_id(), '_product_supplier', true );
                if ($supplier_id == $product_supplier_id) {
                ?>
                <tr class="<?php echo esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)); ?>">
                    <td class="td"
                        style="text-align:<?php echo esc_attr($text_align); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                        <?php
                        echo wp_kses_post(apply_filters('woocommerce_order_item_name', $item->get_name(), $item, false));

                        do_action('woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text);

                        do_action('woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text);
                        ?>
                    </td>
                    <td class="td"
                        style="text-align:<?php echo esc_attr($text_align); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
                        <?php echo wp_kses_post(apply_filters('woocommerce_email_order_item_quantity', $item->get_quantity(), $item)); ?>
                    </td>
                    <td class="td" style="text-align:left; vertical-align:middle;"><?php echo wc_price( $item->get_total() ); ?></td>
                </tr>
            <?php
                }
            } 
            ?>
            </tbody>
            <tfoot>
            <?php
            if ( $order->get_customer_note() ) {
                ?>
                <tr>
                    <th class="td" scope="row" colspan="2"
                        style="text-align:<?php echo esc_attr($text_align); ?>;">
                        <?php esc_html_e('Note:', 'woocommerce'); ?>
                    </th>
                    <td class="td"
                        style="text-align:<?php echo esc_attr($text_align); ?>;">
                        <?php echo wp_kses_post(wptexturize($order->get_customer_note())); ?>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tfoot>
        </table>
        <?php 
        } //
        ?>
    </div>
    <?php
}

function remove_order_details()
{
    $mailer = WC()->mailer(); // get the instance of the WC_Emails class
    remove_action('woocommerce_email_order_details', array($mailer, 'order_details'));
}

?>

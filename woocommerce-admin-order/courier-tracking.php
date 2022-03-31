<?php

// Adding Meta container admin shop_order pages
add_action( 'add_meta_boxes', 'd2d_wc_deliveries_wc_admin_add_meta_box' );

if ( ! function_exists( 'd2d_wc_deliveries_wc_admin_add_meta_box' ) )
{
    function d2d_wc_deliveries_wc_admin_add_meta_box()
    {
        add_meta_box(
            'd2d_wc_deliveries_wc_admin_meta_box_courier_tracking',
            __('D2D Courier Tracking', 'woocommerce'),
            'd2d_wc_deliveries_wc_admin_order_meta_box_courier_tracking',
            'shop_order',
            'side',
            'core'
        );
    }
}

if ( ! function_exists( 'd2d_wc_deliveries_wc_admin_order_meta_box_courier_tracking' ) )
{
    function d2d_wc_deliveries_wc_admin_order_meta_box_courier_tracking( $post ) {

        $order_need_shipping = get_post_meta( $post->ID, 'd2d_order_needs_shipping', true );
        $order_tracking_link = get_post_meta( $post->ID, 'd2d_tracking_link', true );

        if ( $order_need_shipping ) { ?>
    
        <p>This order requires delivery</p>
        <p><a href="<?= $order_tracking_link ?>" target="_blank">Click here to open the courier tracking</a></p>
        
        <?php } else { ?>

        <p>This order doesn't require delivery</p>

        <?php }

    }
}

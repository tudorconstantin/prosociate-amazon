<?php
class ProssociateDisplay {

    function __construct() {
        add_shortcode('prosociate', array($this, 'display'));
        add_action('wp_enqueue_scripts', array($this, 'front_styles'));

        $hook_name = get_option('prossociate_settings-iframe-position');
        if (!$hook_name) {
            $hook_name = 'comment_form';
        }

        add_action($hook_name, array($this, 'iframe_reviews'));
        // http://wpengineer.com/2205/comment-form-hooks-visualized/

        add_action('wp_footer', array($this, 'frontJs'));
    }

    public function frontJs() {
        $string = 'Product prices and availability are accurate as of the date/time indicated ' .
        'and are subject to change. Any price and availability information displayed on ' . 'AMAZON.' . AWS_COUNTRY .
        ' at the time of purchase will apply to the purchase ' .
        'of this product.';
        ?>
        <script>
            jQuery(document).ready(function(){
                jQuery('.prosamazondis').click(function(){
                    alert('<?php echo $string; ?>');
                });
            });
        </script>
    <?php }

    function display($atts) {

        global $post;

        extract(
                shortcode_atts(
                        array(
            'asin' => null
                        ), $atts
                )
        );

        if ($post->post_type == 'product' && ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) )) {
            ob_start();
            include PROSSOCIATE_ROOT_DIR . "/views/display/single-wooco.php";
            return ob_get_clean();
        }
    }

    function iframe_reviews() {

        global $post;

        if ($post->post_type == 'product' && ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) )) {

            // with wooco, output this, but then we have to move it with jquery later

            $ASIN = get_post_meta($post->ID, '_pros_ASIN', true);

            if ($ASIN) {
                include PROSSOCIATE_ROOT_DIR . "/views/display/wooco-reviews.php";
            }
        }
    }

    function front_styles() {

        wp_register_style('pros_front', PROSSOCIATE_ROOT_URL . '/css/front_style.css');
		wp_enqueue_style('jquery-themes-smooth', 'http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css');
        wp_enqueue_style('pros_front');
    }

}


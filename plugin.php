<?php
/*
  Plugin Name: Prosociate Free Edition
  Description: The best free WordPress plugin for Amazon Associates.
  Version: 0.9.3
  Author: Soflyy
  Plugin URI: http://www.prosociate.com/
 */

// Prevent direct access
if (!function_exists('add_action')) {
    die('Im just a plugin and can\'t do anything alone');
}

define('PROSSOCIATE_ROOT_DIR', str_replace('\\', '/', dirname(__FILE__)));
define('PROSSOCIATE_ROOT_URL', rtrim(plugin_dir_url(__FILE__), '/'));
define('PROSSOCIATE_PREFIX', 'pros_');
define('AWS_API_KEY', get_option('prossociate_settings-aws-public-key'));
define('AWS_API_SECRET_KEY', get_option('prossociate_settings-aws-secret-key'));
define('AWS_ASSOCIATE_TAG', get_option('prossociate_settings-associate-id'));
define('AWS_COUNTRY', get_option('prossociate_settings-associate-program-country'));

if (!defined('PROSOCIATE_INSTALLED')) {
    define('PROSOCIATE_INSTALLED', '1.0.1');
}

// ------- amazon sort order translations
$proso_sort_order['relevancerank'] = 'Relevance';
$proso_sort_order['salesrank'] = 'Best Selling';
$proso_sort_order['pricerank'] = 'Price: low to high';
$proso_sort_order['inverseprice'] = 'Price: high to low';
$proso_sort_order['-launch-date'] = 'Newest arrivals';
$proso_sort_order['sale-flag'] = 'On Sale';

$proso_sort_order['price'] = 'Price: low to high';
$proso_sort_order['-price'] = 'Price: high to low';

$proso_sort_order['reviewrank'] = 'Average customer review: high to low';

$proso_sort_order['pmrank'] = 'Featured Items';
$proso_sort_order['psrank'] = 'Projected Sales';

$proso_sort_order['inverse-pricerank'] = 'Price: high to low';

$proso_sort_order['titlerank'] = 'Alphabetical: A to Z';
$proso_sort_order['-titlerank'] = 'Alphabetical: Z to A';

$proso_sort_order['daterank'] = 'Newest published';

require_once( ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'meta-boxes.php' );
require "libraries/AmazonECS.class.php";
require "libraries/aws_signed_request.php";
include "framework/framework-load.php";
include "classes/ProssociateSearch.php";
include "classes/ProssociateCampaign.php";
include "classes/ProssociateCampaignController.php";
include "classes/ProssociateItem.php";
include "classes/ProssociatePoster.php";
include "classes/ProssociateDisplay.php";
include "classes/ProssociateCheckoutHooker.php";
include "classes/ProssociateCron.php";

// utility
if (!function_exists('pre_print_r')) {

    function pre_print_r($x) {
        echo "<pre>";
        print_r($x);
        echo "</pre>";
    }

}

class Prossociate {
    // Initilize the properties

    /**
     * The Campaign Controller. Only load if we are on a campaign-related admin page. And needs to be setup before upon initialization
     * 
     * Source File: /classes/ProssociateCampaignController.php
     * @var object  ProssociateCampaignController
     */
    public $PCC;

    /**
     * Only load the display at the frontend. Also contains the shortcode [prossociate]
     * 
     * Source File: /classes/ProssociateDisplay.php
     * @var object ProssociateDisplay 
     */
    public $Display;

    /**
     * The poster. It does ajax iterative requests to bypass the php max execution time
     * 
     * Source File: /classes/ProssociateDisplay.php
     * @var object ProssociateDisplay 
     */
    public $Poster;

    /**
     * Responsible for making the purchase to AmazonECS
     * 
     * Source File: /classes/ProssociateCheckoutHooker.php
     * @var object ProssociateCheckoutHooker
     */
    public $CheckoutHooker;

    /**
     * Check if cron is still running to prevent duplicates.
     * 
     * Source File: /classes/ProssociateCron.php
     * TODO: Will check what this file actually does.
     * @var object ProssociateCron
     */
    public $Cron;

    /**
     * Instance container
     * @var object  Prossociate
     */
    protected static $instance;

    /**
     * Construct
     */
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activation'));

        // Instantiate the objects (TODO delete the comments)
        $this->PCC = new ProssociateCampaignController; // we only need to create the campaign controller if we are on a campaign related admin page. and we need to do it when the plugin starts up, before anything happens.
        $this->Display = new ProssociateDisplay; // we only need to create the Display on the frontend. And we need to do it right on startup, because the Display registers the shortcodes
        $this->Poster = new ProssociatePoster; // we only need to create the Poster in the admin panel. And we only need to do it so it can handle AJAX iterative post requests.
        $this->CheckoutHooker = new ProssociateCheckoutHooker; // we only need to create the checkout hooker on the frontend
        $this->Cron = new ProssociateCron(); // we only need to create the cron on the frontend

        add_action('admin_init', array($this, 'addSettings'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_notices', array($this, 'notifications'));
        add_action('admin_notices', array($this, 'first_time_notice'));

        // force user to add amazon access key first
        add_action('admin_init', array($this, 'settings_redirect'));
        add_action('admin_notices', array($this, 'amazon_keys_required'));
        add_action('admin_notices', array($this, 'adminNotificationAds'));
		
		add_action('init', array($this, 'woocommerceTabs'));
		add_action('init', array($this, 'woocommercePrice'));

        // Style for amazon disclaimer
        add_action('wp_head', array($this, 'addAmazonDisclaimerStyle'));

        // JS script for settings page
        add_action('admin_print_scripts', array($this, 'addJsSettingsPage'));

        // Custom metabox
        add_action('add_meta_boxes', array($this, 'prosociate_wc_meta_box'));
        add_action('save_post', array($this, 'prosociate_wc_meta_box_save'));

        // Capture the tell us
        add_action('admin_init', array($this, 'adminSendTellUsAds'));
    }

    /**
     * Display "Tell Us" form for tracking ads
     */
    public function adminNotificationAds() {
        // Check if this is the first time sending the info
        if(isset($_POST['freepos-text'])) { ?>
            <div class="updated">
                <p>Thank you for your cooperation.</p>
            </div>
        <?php } else {
            // Get option if we need to display this
            if(get_option('freepros-ads', false) === 'finished')
                return;
            ?>
            <div class="updated" style="padding-top: 10px; padding-bottom: 10px;">
                <form method="post">
                    <label for="freepos-tellus-text">How did you hear about Prosociate?</label>
                    <input style="margin: 0 10px;" type="text" name="freepos-text" id="freepos-tellus-text"/>
                    <input type="submit" value="Tell Us"/>
                </form>
            </div>
    <?php }
    }

    /**
     * Send the info to prosociate.com
     */
    public function adminSendTellUsAds() {
        if(isset($_POST['freepos-text'])) {
            // Only send if the field is not empty
            if(!empty($_POST['freepos-text'])) {
                $url = 'http://www.prosociate.com/';
                wp_remote_post($url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body' => array('message' => $_POST['freepos-text']),
                    'cookies' => array()
                ));
            }

            // Update option to hide this
            update_option('freepros-ads', 'finished');
        }

    }

    /**
     * Add the meta box on products
     */
    public function prosociate_wc_meta_box() {
        add_meta_box('prosociate_meta_box',
            'Prosociate',
            array($this, 'pros_product_section_inner_custom_box'),
            'product', 'normal', 'high'
        );
    }

    /**
     * Callback for the custom meta box
     * @param $post
     */
    public function pros_product_section_inner_custom_box( $post ) {

        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'pros_product_section_inner_custom_box', 'pros_product_section_inner_custom_box_nonce' );

        /*
         * Use get_post_meta() to retrieve an existing value
         * from the database and use the value for the form.
         */
        $value = get_post_meta( $post->ID, '_pros_alt_prod_desc', true );

        $placeHolder = '';
        // If no value
        if(!$value || empty($value)) {
            $placeHolder = 'placeholder="Enter your description here... HTML is allowed" ';
            $value = '';
        }

        echo '<p><label for="myplugin_new_field">';
        echo 'Override the Product Description (useful for SEO)';
        echo '</label><br />';
        echo '<textarea '. $placeHolder .'style="width: 100%" rows="6" id="myplugin_new_field" name="pros_alt_prod_desc">' . esc_attr( $value ) . '</textarea></p>';
        echo '<p>Product ASIN: <strong>' . get_post_meta($post->ID, '_pros_ASIN', true) . '</strong></p>';
    }

    /**
     * Save the custom meta box
     * @param $post_id
     * @return mixed
     */
    public function prosociate_wc_meta_box_save( $post_id ) {
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if ( ! isset( $_POST['pros_product_section_inner_custom_box_nonce'] ) )
            return $post_id;

        $nonce = $_POST['pros_product_section_inner_custom_box_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'pros_product_section_inner_custom_box' ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check the user's permissions.
        if ( 'page' == $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;

        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) )
                return $post_id;
        }

        /* OK, its safe for us to save the data now. */

        // Sanitize user input.
        $mydata = htmlentities($_POST['pros_alt_prod_desc'], ENT_QUOTES);

        if($mydata === 'Enter your description here... HTML is allowed')
            $mydata = '';

        // Update the meta field in the database.
        update_post_meta( $post_id, '_pros_alt_prod_desc', $mydata );
    }
	
	public function woocommercePrice() {
		add_filter('woocommerce_get_price_html', array($this, 'filterPrice'));
	}
	
	public function filterPrice($price) {
		if(is_admin())
			return $price;
        // Get post object
        global $post;

        // Check if it's a prosociate product
        if(get_post_meta($post->ID, '_pros_ASIN', true) == '')
            return $price;

        // Get the settings
        $displayByTime = get_option('prossociate_settings-pros-dis-display-time', 'true');
        $displayByLocation = get_option('prossociate_settings-pros-dis-display-individual', 'true');
        $lastUpdateTime = get_post_meta($post->ID, '_pros_last_update_time', true);

        if($displayByLocation == 'true') {
            // Only do the filter if we're on the individual product page
            if(is_single()) {
                $price = $this->filterPriceByTime($price, $displayByTime, $lastUpdateTime);
            }
        } else {
            // Only do the filtration by time
            $price = $this->filterPriceByTime($price, $displayByTime, $lastUpdateTime);
        }

        // If we need to display the disclaimer regarding of the refreshed time
        return $price;
	}

    /**
     * Do filtration of price if product is not refreshed within 24 hours.
     * @param $price
     * @param $displayByTime
     * @param $lastUpdateTime
     * @return string
     */
    private function filterPriceByTime($price, $displayByTime, $lastUpdateTime) {
        if($displayByTime == 'false') {
            if((int)$lastUpdateTime <= (time() - 86400)) {
                // Product was not updated within 24 hours
                $newPrice = $this->alterPrice($price, $lastUpdateTime);
            }
            else {
                // if product was updated within the last 24 hours. Still display the price.
                $newPrice = $price;
            }
        } else {
            // Display regardless
            $newPrice = $this->alterPrice($price, $lastUpdateTime);
        }

        return $newPrice;
    }

    /**
     * Changes to be done on the price
     * @param $price
     * @param $lastUpdateTime
     * @return string
     */
    private function alterPrice($price, $lastUpdateTime) {
        global $post;
        // Get date format
        $dateDisplay = get_option('prossociate_settings-pros-date-format', 'true');
        // Set default
        if($dateDisplay == false || empty($dateDisplay))
            $dateDisplay = '(as of %%DATE%% at %%TIME%%)';

        // Get date
        $date = date('m/d/Y', $lastUpdateTime);
        // Get time
        $time = date('H:i', $lastUpdateTime) . ' ' . date_default_timezone_get();
        // Convert the %%DATE%%
        $str = str_replace('%%DATE%%', $date, $dateDisplay);
        // Convert the %%TIME%%
        $str2 = str_replace('%%TIME%%', $time, $str);

        //$lastUpdate = date('m/d/Y H:i', $lastUpdateTime) . ' ' . date_default_timezone_get();

        $tooLowToDisplay = get_post_meta($post->ID, '_filterTooLowPrice', 'true');
        if($tooLowToDisplay === 'true')
            $price = 'Too low to display';

        $price .= "<div class='prosamazondis'>{$str2}</div>";

        return $price;
    }
	
	public function woocommerceTabs() {
		add_filter('woocommerce_product_tabs', array($this, 'reviewTabs'));
	}
	
	public function reviewTabs($tabs) {
		$tabs['reviews']['title'] = "Reviews";
		return $tabs;
	}

    /**
     * Check if the instance is already created. If not, create an instance
     */
    static public function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function initCron() {
        new ProssociateCron();
    }

    public function check_amazon_notice() {
        if(isset($_GET['page'])) {
            $page = $_GET['page'];
        } else {
            $page ='';
        }
        
        if(isset($_GET['settings-updated'])) {
            $settingsUpdated = $_GET['settings-updated'];
        } else {
            $settingsUpdated = '';
        }
        
        if ($page == 'prossociate_settings' && $settingsUpdated == 'true') {
            $error = $this->check_amazon();
            if ($error) {
                $this->check_amazon_fail();
                // Update option
                update_option('pros_valid_amazon_keys', 'invalid');
            } else {
                $this->check_amazon_success();
                update_option('pros_valid_amazon_keys', 'valid');
            }
        }
    }

    public function check_amazon_fail() {
        ?>
        <div class="error">
            <p>Prosociate was not able to connect to Amazon with the specified AWS Key Pair and Associate ID. Please triple-check your AWS Keys and Associate ID.</p>
        </div>
    <?php }

    public function check_amazon_success() {
        ?>
        <div id="connected" class="updated">
            <p>Prosociate was able to connect to Amazon with the specified AWS Key Pair and Associate ID.</p>
        </div>
    <?php
    }

    private function check_amazon() {
        $error = false;

        // Get the keys
        $awsApiKey = get_option('prossociate_settings-aws-public-key');
        $awsApiSecret = get_option('prossociate_settings-aws-secret-key');
        $awsCountry = 'com';
        $awsAssociateTag = get_option('prossociate_settings-associate-id');

        // Try 
        try {
            // Do a test connection
            $tryConnect = new AmazonECS($awsApiKey, $awsApiSecret, $awsCountry, $awsAssociateTag);
            $tryConnect->responseGroup('Small');
            $tryConnect->category('Apparel');
            $tryResponse = $tryConnect->search('*', '1036592');
        } catch (Exception $e) {
            // Check 
            //if (isset($e->faultcode)) {
                $error = true;
            //}
        }

        return $error;
    }

    /**
     * Redirect user to the settings page if the amazon access isn't populated
     */
    public function settings_redirect() {
        $redirect = TRUE;

        // Check if all amazon access is given
        if (!( AWS_API_KEY == '' || AWS_API_KEY == NULL || AWS_API_KEY == FALSE )) {
            if (!( AWS_API_SECRET_KEY == '' || AWS_API_SECRET_KEY == NULL || AWS_API_SECRET_KEY == FALSE )) {
                if (!( AWS_ASSOCIATE_TAG == '' || AWS_ASSOCIATE_TAG == NULL || AWS_ASSOCIATE_TAG == FALSE )) {
                    $redirect = FALSE;
                }
            }
        }

        if (get_option('pros_valid_amazon_keys', 'invalid') == 'invalid') {
            $redirect = TRUE;
        }

        // Check if we need to redirect
        if ($redirect) {
            if(isset($_GET['page']))
                $page = $_GET['page'];
            else
                $page = '';

            if ($page == 'prossociate_addedit_campaign' || $page == 'prossociate_manage_campaigns') {
                // Admin url
                $admin_url = admin_url('admin.php');

                // The settings url
                $url = add_query_arg(array(
                    'page' => 'prossociate_settings',
                    'message' => 1
                        ), $admin_url);

                wp_redirect($url);
                exit();
            }
        }
    }

    public function amazon_keys_required() {
        if(isset($_GET['page'])) {
            $page = $_GET['page'];
        } else {
            $page = '';
        }
        
        if(isset($_GET['message'])) {
            $message = $_GET['message'];
        } else {
            $message = '';
        }
        
        if(isset($_GET['settings-updated'])) {
            $settingsUpdated = $_GET['settings-updated'];
        } else {
            $settingsUpdated = '';
        }
        
        if ($page == 'prossociate_settings' && $message == 1 && $settingsUpdated != 'true') {
            ?>
            <div class="error">
                <p>Please enter in your Associate ID, AWS Access Key ID, and AWS Secret Access Key</p>
            </div>
        <?php
        }
    }

    /**
     * Display an admin notice upon plugin activation
     */
    public function first_time_notice() {
        // Check if the plugin was installed before
        if (!get_option('prosociate_installed')) {
            add_option('prosociate_installed', 'PROSOCIATE_INSTALLED');
            // Set the default
            add_option('prossociate_settings-iframe-width', 600);
            add_option('prossociate_settings-iframe-height', 600);
            add_option('prossociate_settings-iframe-position', 'comment_form');
			add_option('prossociate_settings-title-word-length', 9999);
            ?>
            <div class="updated">
                <p>Thanks for installing Prosociate.</p>
            </div>
        <?php
        }
    }

    // Moved to top: var $PCC, $Display, $Poster, $CheckoutHooker, $Cron; // To be deleted

    /**
     * Notify the user if woocommerce is an active plugin
     */
    public function notifications() {

        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

            echo '<div id="message" class="error prosociate_admin_msg"><p><strong>Prosociate</strong> - you must <a href="' . admin_url("plugin-install.php?tab=plugin-information&amp;plugin=woocommerce&amp;TB_iframe=true&amp;width=600&amp;height=550") . '" class="thickbox">install WooCommerce</a> to use Prosociate.</p><p>If this message appears after installing WooCommerce, you must Activate it from the Plugins screen.</div>';
        }
    }

    /**
     * the homepage
     */
    public function home() {
        include PROSSOCIATE_ROOT_DIR . "/views/home/home.php";
    }

    /**
     * Build the admin menu
     */
    public function admin_menu() {
        // Admin Parent Menu
        add_menu_page('Prosociate', 'Prosociate', 'manage_options', __FILE__, array($this, 'home'), PROSSOCIATE_ROOT_URL . "/images/favicon.png");

        // Subpages
        add_submenu_page(__FILE__, 'Add Products', 'Home', 'manage_options', __FILE__, array($this, 'home'));
        add_submenu_page(__FILE__, 'New Campaign', 'New Campaign', 'manage_options', 'prossociate_addedit_campaign', array($this->PCC, 'addedit'));
        add_submenu_page(__FILE__, 'Manage Campaigns', 'Manage Campaigns', 'manage_options', 'prossociate_manage_campaigns', array($this->PCC, 'manage_campaigns'));

        // Set up options page
        $settings = new SoflyyOptionsPage('Settings', 'prossociate_settings', __FILE__, 'Prosociate: Settings');
        $settings->add_field('Associate ID', 'associate-id', 'text', 'Register for an Associate ID <a target="_blank" href="https://affiliate-program.amazon.com/">here</a>.');
        $settings->add_field('Associate Program Country', 'associate-program-country', 'select', 'Choose a country.', array(
            'com' => 'United States',
            'co.uk' => 'United Kingdom',
            'co.jp' => 'Japan',
            'de' => 'Germany',
            'fr' => 'France',
            'ca' => 'Canada',
            'es' => 'Spain',
            'it' => 'Italy',
            'cn' => 'China',
            'in' => 'India'
                )
        );
        $settings->add_field('AWS Access Key ID', 'aws-public-key');
        $settings->add_field('AWS Secret Access Key', 'aws-secret-key', 'text', 'Get your AWS Access Key ID and AWS Secret Access Key <a target="_blank" href="https://affiliate-program.amazon.com/gp/advertising/api/detail/main.html">here</a>.');
        $settings->add_field('Customer Reviews IFrame Width', 'iframe-width');
        $settings->add_field('Customer Reviews IFrame Height', 'iframe-height');
		$settings->add_field('Max Length for Product Titles', 'title-word-length', 'text', 'Limit the number of characters in product titles. Does not apply retroactively.');
//        $settings->add_field('Customer Reviews Position', 'iframe-position', 'select', 'Customer Reviews IFrame Position', array('comment_form' => 'Standard', 'comment_form_before' => 'Before The Comment Form', 'comment_form_after' => 'After The Comment Form'));
        //TODO should delete this?
        /*
          Canada
          China
          France
          Germany
          Italy
          Japan
          Spain
          United Kingdom
         */

        // check if amazon keys are correct
        add_action('admin_notices', array($this, 'check_amazon_notice'));

        // Add the category meta-box
        add_meta_box('categorydiv', __('Product Categories'), array( $this, 'product_categories_meta_box'), 'prosociate_page_prossociate_addedit_campaign', 'side', 'core');
    }

    public function addSettings() {
        // Add new settigns for compliance
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-css', array($this, 'sanitize_styles'));
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-display-individual');
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-display-time');
        register_setting('prossociate_settings', 'prossociate_settings-pros-date-format');
        register_setting('prossociate_settings', 'prossociate_settings-pros-too-low-display-text');
        // TODO new setting for "Too low to display" filter
        add_settings_section('prosdisstyle', '', array($this, 'complianceSettings'), 'dm-pros-sections');
    }

    public function sanitize_styles($input) {
        $input = esc_html($input);

        return $input;
    }

    public function complianceSettings() {
        $css = get_option('prossociate_settings-pros-dis-css');
        $displayByLocation = get_option('prossociate_settings-pros-dis-display-individual', 'true');
        $displayByTime = get_option('prossociate_settings-pros-dis-display-time', 'true');
        $dateDisplay = get_option('prossociate_settings-pros-date-format', 'true');
        if(!$css)
            $css = '';

        // Set defaults
        if($dateDisplay == false || empty($dateDisplay))
            $dateDisplay = '(as of %%DATE%% at %%TIME%%)';
        ?>
        <div id="tabs-compliance-settings" style="display: none;">
            <h3>Compliance</h3>
            <div style='padding-left: 10px'>
                <p style="font-weight: bold; font-size: 14px;">Amazon’s TOS requires a disclaimer to be placed next to all prices that haven't been refreshed in the last 24 hours.</p>

                Prosociate periodically refreshes the data on your site. How often the data is refreshed depends on the number of visitors to your site (the more, the more often the data is refreshed), and the number of products (the more, the less often the data is refreshed).

            </div>

            <p style="padding-left: 10px"><label for='prossociate_settings-pros-date-format'>Translate (as of 10/20/2015 at 09:23 UTC) <a id="dm-pros-default-link" href="#">reset to default</a></label><br />
                <input type="text" id="prossociate_settings-pros-date-format" name="prossociate_settings-pros-date-format"
                    style="width: 300px;" value="<?php echo $dateDisplay; ?>"/>
                <script type="text/javascript">
                    function dmRestoreDefault() {
                        document.getElementById('prossociate_settings-pros-date-format').value = '(as of %%DATE%% at %%TIME%%)';
                    }
                    document.getElementById('dm-pros-default-link').addEventListener("click", dmRestoreDefault, false);
                </script>
            </p>

            <table class='form-table'>
                <tr valign="top">
                    <th scope="row">
                        <label>CSS for Price Disclaimer</label>
                    </th>
                    <td>
                        <textarea name="prossociate_settings-pros-dis-css" cols="55" rows="6"><?php echo $css; ?></textarea>
                        <p class="description">Will be applied to <code>(as of 10/20/2015 at 09:23 UTC)</code></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label>Where to show the disclaimer</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-pros-dis-display-individual" >
                            <?php
                                $selected = '';
                                if($displayByLocation == 'false')
                                    $selected = ' selected=selected';
                            ?>
                            <option value='true'>Only show on individual product pages.</option>
                            <option value='false'<?php echo $selected; ?>>Show everywhere prices are displayed.</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label>When to show the disclaimer</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-pros-dis-display-time" >
                            <?php
                            $selected = '';
                            if($displayByTime == 'false')
                                $selected = ' selected=selected';
                            ?>
                            <option value='true'>Display disclaimer for all products.</option>
                            <option value='false'<?php echo $selected; ?>>Only display disclaimer if the pricing data is more than 24 hours old.</option>
                        </select>
                    </td>
                </tr>
            </table>
            <div style='padding-left: 10px'>
                <p style="font-weight: bold; font-size: 14px;">Amazon’s TOS requires you to place the following text somewhere on your site in a way that is clearly visible to users. <br />
                    We recommend placing it in your footer:</p>

                <p style="font-style: italic">“CERTAIN CONTENT THAT APPEARS ON THIS SITE COMES FROM AMAZON SERVICES LLC. <br />
                    THIS CONTENT IS PROVIDED 'AS IS' AND IS SUBJECT TO CHANGE OR REMOVAL AT ANY TIME.”</p>
            </div>
        </div>
    <?php }

    public function addAmazonDisclaimerStyle() {
        // Get the style
        $css = get_option('prossociate_settings-pros-dis-css');
        // if there are no custom style don't show it in the front end
        if(!$css)
            return; ?>
        <style type="text/css">
            .prosamazondis {<?php echo $css; ?>}
        </style>
    <?php }

    /**
     * Create the new table on the database for the plugin
     */
    public function activation() {
        // Create the tables
        // Check if cron checker was set
        if (!get_option('pros_active_cron')) {
            update_option('pros_active_cron', "not_active_cron");
        }
        
        // Check if variation checker is set
        if(!get_option('pros_active_cron_variation')) {
            update_option('pros_active_cron_variation', 'no_variation');
        }
        
        // Check if variation step checker is set
        if(!get_option('pros_active_cron_variation_step')) {
            update_option('pros_active_cron_variation_step', 'no_variation');
        }
        
        if( get_option('pros_active_cron_variation_offset') === FALSE ) {
            update_option('pros_active_cron_variation_offset', 0);
        }
        
        // Check if cron time checker
        if(!get_option('pros_last_cron_time')) {
            update_option('pros_last_cron_time', time());
        }

        // Check if cron time checker
        if(!get_option('prossociate_settings-title-word-length')) {
            update_option('prossociate_settings-title-word-length', 9999);
        }

        // Check for the date format settings
        if(!get_option('prossociate_settings-pros-date-format')) {
            update_option('prossociate_settings-pros-date-format', '(as of %%DATE%% at %%TIME%%)');
        }

        // create/update required database tables
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require PROSSOCIATE_ROOT_DIR . '/schema.php';

        // dbDelta usage:
        // You must put each field on its own line in your SQL statement.
        // You must have two spaces between the words PRIMARY KEY and the definition of your primary key.
        // You must use the key word KEY rather than its synonym INDEX and you must include at least one KEY.
        // You must not use any apostrophes or backticks around field names.

        dbDelta($plugin_queries);
    }

    /**
     * Display post categories form fields.
     *
     * @since 2.6.0
     *
     * @param object $post
     */
    public function product_categories_meta_box($post, $box) {
        $campaign_id = $_REQUEST['campaign_id'];
        if( $campaign_id != null ) {
            global $wpdb;
            
            $dmSql = "Select options FROM wp_pros_campaigns WHERE id = '{$campaign_id}'";
            
            $dmResult = $wpdb->get_col( $dmSql );
            
            $dmUnserialized = unserialize($dmResult[0]);
            
            $dmSelectedCats = $dmUnserialized['dmcategories'];
            
            $removeZeroTermId = array_shift($dmSelectedCats);
            
        }
        
        $defaults = array('taxonomy' => 'product_cat');
        if (!isset($box['args']) || !is_array($box['args']))
            $args = array();
        else
            $args = $box['args'];
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        $tax = get_taxonomy($taxonomy);
        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
                <li class="tabs"><a href="#<?php echo $taxonomy; ?>-all"><?php echo $tax->labels->all_items; ?></a></li>
                <li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop"><?php _e('Most Used'); ?></a></li>
            </ul>

            <div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
                <ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
        <?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
                </ul>
            </div>

            <div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
        <?php
        $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
        echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
        ?>
                <ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy ?>" class="categorychecklist form-no-clear">
                    <?php wp_terms_checklist($post->ID, array('taxonomy' => $taxonomy, 'popular_cats' => $popular_ids)) ?>
                </ul>
                <?php if($campaign_id != null){ ?>
                    <script type=''>
                        <?php if(count($dmSelectedCats) > 0) { 
                            foreach($dmSelectedCats as $dmSelectedCat) { ?>
                            jQuery("#in-product_cat-<?php echo $dmSelectedCat; ?>").attr("checked", "checked");
                            
                            <?php } 
                        } ?>
                    </script>
                <?php } ?>
            </div>
        <?php if (current_user_can($tax->cap->edit_terms)) : ?>
                <div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
                    <h4>
                        <a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js">
                    <?php
                    /* translators: %s: add new taxonomy label */
                    printf(__('+ %s'), $tax->labels->add_new_item);
                    ?>
                        </a>
                    </h4>
                    <p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
                        <input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr($tax->labels->new_item_name); ?>" aria-required="true"/>
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
            <?php echo $tax->labels->parent_item_colon; ?>
                        </label>
                            <?php wp_dropdown_categories(array('taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new' . $taxonomy . '_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;')); ?>
                        <input type="button" id="<?php echo $taxonomy; ?>-add-submit" data-wp-lists="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add" class="button category-add-submit" value="<?php echo esc_attr($tax->labels->add_new_item); ?>" />
                            <?php wp_nonce_field('add-' . $taxonomy, '_ajax_nonce-add-' . $taxonomy, false); ?>
                        <span id="<?php echo $taxonomy; ?>-ajax-response"></span>
                    </p>
                </div>
        <?php endif; ?>
        </div>
        <?php
    }

    public function addJsSettingsPage() { ?>
        <script type="text/javascript">
            window.onload = dmInit;

            function dmInit() {
                var compliance = document.getElementById('tabs-compliance-settings-link');
                compliance.onclick = function(e) {
                    e.preventDefault;
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-compliance-settings').style.display = 'inline';
                    this.className = this.className + " nav-tab-active";
                }

                var generalSettings = document.getElementById('tabs-general-settings-link');
                generalSettings.onclick = function(e) {
                    e.preventDefault;
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-general-settings').style.display = 'inline';
                    this.className = this.className + " nav-tab-active";
                }
            }

        </script>
    <?php }

}

// Get / create an instance
Prossociate::getInstance();
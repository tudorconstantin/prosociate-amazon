<?php
/**
 * The campaign controller. Only load on campaign-related pages
 */
class ProssociateCampaignController {
    // TODO
    // Initialize $url_to_here
    // Give more details on each methods
    // Complete the massive delete feature
       
    /**
     * Construct
     */
    public function __construct() {
        // Get the current url (Not sure yet while we need to do a str_replace
        $url_to_here = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
        // Convert the current url to an array
        $url_to_here = explode("&", $url_to_here);
        // Get the first element of the array and store it
        $url_to_here = $url_to_here[0];
        // TODO find out what is the data type of the $url_to_here
        $this->url_to_here = $url_to_here;

        if(isset($_GET['page'])) {
            if($_GET['page'] == 'prossociate_addedit_campaign')
                add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        add_action('wp_ajax_prossociate_search', array($this, 'ajax_print_serps'));
        
        $search = new ProssociateSearch('','');
        
        add_action( 'init', array( $this, 'pagination' ) );
        
        // Check for mass delete
        if( isset( $_GET['dm-mass-delete'] ) )
        {
            if( $_GET['dm-mass-delete'] == 1 )
            {
                add_action( 'admin_notices', array( $this, 'mass_delete_success' ) );
            }
            else
            {
                add_action( 'admin_notices', array( $this, 'mass_delete_fail' ) );
            }
        }
    }
    
    /**
     * Save / Edit Campaign
     */
    public function addedit() {
        global $wpdb;
        
        $campaign = new ProssociateCampaign();
        
        // Check if wer're editing a campaign
        // TODO I think it's better if we use $_GET[] explicit for security and readability 
        // Also it's good to be sure that we are passing an int
        if( isset($_REQUEST['campaign_id']) ) 
        {
            if(!(isset($_REQUEST['pros_submit_type']) || isset($_REQUEST['pros_submit_type']))) {
                // Load the campaign
                $campaign->load( $_REQUEST['campaign_id'] );
            }
        } 
        else 
        {
            // Load the defaults. Meaning create a new one
        	$campaign->defaults();
        }
        
        // yuri - check submit button
        if( isset($_REQUEST['pros_submit_type']) || isset($_REQUEST['pros_submit_type']) ) {
            if( $_REQUEST['pros_submit_type'] == 'Save Campaign' || $_REQUEST['pros_submit_type'] == 'Save Campaign & Post Products' ) 
            {
                $campaign_parameters['keywords'] = $_REQUEST['keyword'];
                $campaign_parameters['searchindex'] = $_REQUEST['searchindex'];
                // yuri - add sortby, browsenode, category, selected ASINs parameter
                $campaign_parameters['category'] = $_REQUEST['category'];
                $campaign_parameters['browsenode'] = $_REQUEST['browsenode'];
                $campaign_parameters['nodepath'] = $_REQUEST['nodepath'];
                $campaign_parameters['sortby'] = $_REQUEST['sortby'];
                $campaign_parameters['ASINs'] = $_REQUEST['ASINs'];
                // yuri - add advanced search options
                $campaign_parameters['dmAdditionalAttributes'] = $_REQUEST['dmAdditionalAttributes'];

                // Categories
                if(isset($_REQUEST['tax_input']['product_cat'])) {
                    $campaign_parameters['dmcategories'] = $_REQUEST['tax_input']['product_cat'];
                } else {
                    $campaign_parameters['dmcategories'] = '';
                }

                $campaign->options = $campaign_parameters;

                // Set post options from array form field
                $post_options = $_REQUEST['post_options'];
                $campaign->post_options = $post_options;

                // search parameters (doesnt work yet)
                if(isset($_REQUEST['search_parameters'])) {
                    $search_parameters = $_REQUEST['search_parameters'];
                } else {
                    $search_parameters = '';
                }
                
                $campaign->search_parameters = $search_parameters;

                // campaign settings
                $campaign_settings = $_REQUEST['campaign_settings'];
                $campaign->campaign_settings = $campaign_settings;

                $campaign->name = $_REQUEST['campaign_name'];

                // if campaign name is leave blank. Use the keywords as the name
                if( $_REQUEST['campaign_name'] == '' || empty($_REQUEST['campaign_name']) )
                {
                    if($_REQUEST['keyword'] == '')
                        $campaign->name = $_REQUEST['category'];
                    else
                        $campaign->name = $_REQUEST['keyword'];
                }

                $campaign->search();

                if( $_REQUEST['campaign_id'] ) 
                {
                    $campaign->id = $_REQUEST['campaign_id'];
                }

                $campaign->save();

                if( $_REQUEST['pros_submit_type'] == 'Save Campaign & Post Products' ) 
                {
                        $campaign->post();
                }

            }
        }
        
        if( !isset($_REQUEST['pros_submit_type']) ) {
            include PROSSOCIATE_ROOT_DIR."/views/campaign/addedit.php";
        }
    
    }
    
    /**
     * Ajax for the display results
     */
    public function ajax_print_serps() {
        // yuri - ignore browse node parameter if it's the same with searchindex
        /*
        if( $_POST['searchindex'] == $_POST['category'] )
        {
            $browsenode = null;
        }
        else
        {
            $browsenode = $_POST['browsenode'];
         * 
         */
        $browsenode = $_POST['browsenode'];

        // yuri - add sortby, browsenode parameter
        $search = new ProssociateSearch($_POST['keyword'], $_POST['searchindex'], $browsenode, $_POST['sortby'], $_POST['page'], $_POST['category']);

        $search->execute('Small,OfferSummary,Images,Variations,VariationOffers,Offers,OfferFull', false);
        // yuri - set advanced search options
        //$search->set_advanced_options($_POST['minprice'], $_POST['maxprice'], $_POST['availability'], $_POST['condition'], $_POST['manufacturer'], $_POST['brand'], $_POST['merchantid'], $_POST['minpercentageoff']);
        //if(get_option('pros_valid_amazon_keys') == 'valid') {
        //    $search->execute('');
        //}
        
        include PROSSOCIATE_ROOT_DIR."/views/campaign/ajax_print_serps.php";
        
        die(); 
    }
    
    /**
     * Manage campaigns logic
     * For the page - wp-admin/admin.php?page=prossociate_manage_campaigns
     */
    public function manage_campaigns() {
        global $wpdb;
        
        // Get the current action
        if(isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        } else {
            $action = '';
        }
        
        // Check if mass delete was confirmed
        if( isset( $_POST['mass_ids'] ) )
        {
            $deleteAssociated = FALSE;
            // Check if associated posts will also be deleted
            if( isset( $_POST['is_delete_posts'] ) && ( $_POST['is_delete_posts'] == "on" ) )
            {
                $deleteAssociated = TRUE;
            }
            
            // Delete the campaigns
            $deleteCheck = $this->delete_campaigns( explode( '-', $_POST['mass_ids'] ), $deleteAssociated );
            
            // Remove the pagination arg
            $currUrl = remove_query_arg( 'pagi' );
            
            // Add the dm-mass-delete get param
            $redirectMassDel = add_query_arg( array( 'dm-mass-delete' => $deleteCheck ), $currUrl );
            
            // Redirect
            wp_redirect( $redirectMassDel );
            exit;
            
        }
        
        // Confirm delete associated posts on mass campaign delete
        if( isset( $_POST['campaign'] ) )
        {
            $dmMassDelete = true;
            include PROSSOCIATE_ROOT_DIR."/views/campaign/delete.php";
        }
        
        // When deleting a campaign
        if( $action == 'delete' ) 
        {
            $campaign = new ProssociateCampaign();
            if( $_REQUEST['is_confirmed'] )
            {
                // Check if we will also delete all the posts associated with the campaign
                if( $_REQUEST['is_delete_posts'] == 'on' ) 
                {
                    // Delete the associated posts
                $campaign->delete_associated_posts($_REQUEST['campaign_id']);
                }
                
                // Delete the campaign
                // TODO i think it's better to check if the campaign is an integer for better security
                $campaign->dbdelete($_REQUEST['campaign_id']);
                
                $msg = 'Campaign deleted successfully.';
                
                // Unset the action
                $action = null;
            } 
            else 
            {
            	include PROSSOCIATE_ROOT_DIR."/views/campaign/delete.php";
            }
        }
        
        // Check if there's no action
        if( ! ($action || isset( $_POST['campaign'] ) )) 
        {
            // If there's no action, display existing campaigns
            
            // Check if the page number is given and if it's a number
            if( isset( $_GET['pagi'] ) && is_numeric($_GET['pagi'] ) )
            {
                $currentPage = $_GET['pagi'];
            }
            else
            {
                // Default to the first page
                $currentPage = 1;
            }
            
            // Number of campaigns to show per page
            $campaignsPerPage = 10;
            
            // Total number of existing campaigns
            $numberOfCampaigns = $this->count_campaigns();
            
            // Compute for the number of pages
            $numberOfPages = ceil( $numberOfCampaigns / $campaignsPerPage );
            
            // Set the order
            $order = '';
            $orderBy = '';
            
            // Check for order
            if( isset( $_GET['order'] ) )
            {
                $orderBy = $_GET['order_by'];
            }
            
            // The pagination
            $campaigns = $this->pagination( $campaignsPerPage, $currentPage. $order, $orderBy );

            //  The display
            include PROSSOCIATE_ROOT_DIR."/views/campaign/manage.php";
        
        }
        
    }
    
    /**
     * Delete multiple campaigns
     * @global type $wpdb
     * @param array $campaignIds Campaign ids to be deleted
     * @param boolean True if associated posts will also be deleted
     * @return boolean
     */
    private function delete_campaigns( $campaignIds, $deleteAssociatedPosts = FALSE ) {
        global $wpdb;
        
        // Check if $campaignids are not empty
        if( !empty( $campaignIds ) )
        {
            // Fail until tested
            $success = 0;
            
            // Check if associated posts will be deleted
            if( $deleteAssociatedPosts )
            {
                foreach( $campaignIds as $campaignId )
                {
                    // Delete the associated posts of each campaign
                    // TODO this is not the best way to do this
                    $campaign = new ProssociateCampaign();
                    
                    $campaign->delete_associated_posts( $campaignId );
                }
            }

            // Clean the ids
            $sanitizeIds = $this->sanitize_ids( $campaignIds );

            // Check if the $campaignIds are sanitized
            if( $sanitizeIds !== FALSE )
            {
                // Number of campaigns to be deleted
                $numberOfCampaigns = count($sanitizeIds);

                // Set a counter
                $counter = 1;

                // The SQL
                $sql = "DELETE FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . "campaigns WHERE id IN (";

                // Loop through all the $campaignIds to be deleted
                foreach( $sanitizeIds as $sanitizeId )
                {
                    // If we are on the last element on the array don't add ","
                    // Also add the closing parenthesis
                    if( $counter === $numberOfCampaigns )
                    {
                        $sql .= "{$sanitizeId})";
                    }
                    else
                    {
                        $sql .= "{$sanitizeId},";
                    }

                    $counter++;
                }

                // Do the operation and check if it's successful
                if( $wpdb->query($sql) !== FALSE )
                {
                    // massive delete is a success
                    $success = 1;
                }
            }
            
            
        }
        
        return $success;
    }
    
    /**
     * Sanitize an array of ids to make sure each of the element is an integer
     * 
     * @param array $ids the array to be sanitized
     */
    private function sanitize_ids( $ids ) {
        // Fail until tested
        $success = FALSE;
        
        // The container for the new array
        $sanitizeArray = array();
        
        // Check if the $ids are not empty
        if( !empty( $ids ) )
        {
            // Assuming the all the $ids passed are integer
            $sanitizeSuccess = TRUE;
            
            // Loop each of the $ids
            foreach( $ids as $id )
            {
                // Check if there's a non integer element on the ids
                if( !is_numeric( $id ) )
                {
                    // Failed
                    $sanitizeSuccess = FALSE;
                    // End the loop
                    break;
                }
                else
                {
                    // Convert the type of $id to integer for safety measures
                    // Store it to the new array
                    $sanitizeArray[] = (int)$id;
                }
            }
            // Check if the sanitizing process is completed
            if( $sanitizeSuccess )
            {
                // Return the new array
                $success = $sanitizeArray;
            }
        }
        
        return $success;
    }
        
    /**
     * Provide pagination feature in managing campaigns
     * @param int $currentPage the current page
     * @param int $campaignsPerPage the number of campaigns to be displayed per page
     * @param string $orderBy Order of campaigns
     * @param string $order ASC or DESC
     * @return object The campaigns to be displayed
     */
    public function pagination( $campaignsPerPage = 10, $currentPage = 1, $orderBy = 'id', $order = 'ASC' ) {
        global $wpdb;        
        
        // Accepted $orderBy
        // TODO Keywords
        $acceptedOrderBy = array( 'id', 'name', 'last', 'last_run_time' );
        
        // Check if the $order and $orderBy parameter is safe.
        if( !in_array( $orderBy, $acceptedOrderBy ) )
        {
            // Default the $orderBy to id
            $orderBy = 'id';
        }
    
        // Get the number of campaigns
        // TODO try if this is really needed
        $numberOfCampaigns = $this->count_campaigns();
    
        // Compute where to start retrieving data
        $offset = ($currentPage - 1) * $campaignsPerPage;
    
        // Get only the campaigns that needs to be displayed
        if( (int)$offset == 0 ) {
            $query = "SELECT * FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . "campaigns ORDER BY {$orderBy} {$order} LIMIT 0 , 10";
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . "campaigns ORDER BY {$orderBy} {$order} LIMIT {$offset}, {$campaignsPerPage}";
        }
        
        
        // Get the campaigns
        $campaigns = $wpdb->get_results($query);
     
        return $campaigns;
    }
                
    /**
     * Count the number of campaigns in the database
     * @return int
     */
    private function count_campaigns() {
        global $wpdb;
        
        // Count the campaign
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX ."campaigns";
        
        $results = $wpdb->get_col( $query );
            
        return $results[0];
    }
    
    /**
     * Admin notice if mass delete was a success
     */
    public function mass_delete_success() { ?>
        <div class="updated">
            <p><?php _e( 'Campaigns were successfully deleted', 'my-text-domain' ); ?></p>
        </div>
    <?php }
    
    /**
     * Admin notice if mass delete failed
     */
    public function mass_delete_fail() { ?>
        <div class="error">
            <p><?php _e( 'A problem occurred while deleting campaigns', 'my-text-domain' ); ?></p>
        </div>
    <?php }
    
    /**
     * The scripts to be loaded on all campaign-related pages on the admin panel
     */
    public function admin_enqueue_scripts() {

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_script('post');
        wp_enqueue_script('jquery');
        
        wp_register_script('jquery-ui-gapi', '//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js');
        wp_enqueue_script('jquery-ui-gapi');
        
        wp_register_style('jquery-ui-gapi', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/themes/base/jquery-ui.css');
        wp_enqueue_style('jquery-ui-gapi');
        
        // Check if woocommerce is activated
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            global $woocommerce;
            wp_enqueue_script('jquery-dm-tiptip', $woocommerce->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js');
        }
        
        wp_register_script('prossociate_campaign_controller', PROSSOCIATE_ROOT_URL.'/js/ProssociateCampaignController.js' );
        wp_enqueue_script('prossociate_campaign_controller');
        
        wp_register_script('jquery-jstree', PROSSOCIATE_ROOT_URL.'/libraries/jstree/jquery.jstree.js');
        wp_enqueue_script('jquery-jstree');
        
        wp_register_style('jquery-jstree', PROSSOCIATE_ROOT_URL.'/libraries/jstree/themes/classic/style.css');
        wp_enqueue_style('jquery-jstree');
        
        wp_register_style('pros_admin_style', PROSSOCIATE_ROOT_URL.'/css/admin_style.css');
        wp_enqueue_style('pros_admin_style');
    }

}



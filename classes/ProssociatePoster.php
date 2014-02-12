<?php
/**
 * Responsible for the product posting
 */
class ProssociatePoster {
    /**
     * Campaign to get the products
     * @var type 
     */
    var $campaign;
    
    /**
     * For variation posting 
     */
    private $variation = FALSE; // By default we not posting variations
    private $var_data;
    private $var_post_id;
    private $var_post;
    private $var_update_operation;
    private $var_post_options;
    private $var_offset = 0;
    private $var_mode;

    private $external = false;
   
    /**
     * Construct
     * @param type $campaign
     */
    public function __construct($campaign = null) {
        
        if ($campaign) {
            $this->campaign = $campaign;
        }

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_prossociate_iterate', array($this, 'ajax_iterate'));
    }
    
    /**
     * The actual posting function
     * TODO Should be trimmed down to parts for more organize / extensible code
     * @global type $wpdb
     */
    public function ajax_iterate() {
        global $wpdb;
        
        // Try to post
        try {

            // we want to both update and post new
            // get all post IDs associated with the campaign.
            // if there are any, update them accordingly. iterate through the update. after posting 10, cancel the process, send the JSON. this will retrigger.
            // it now checks for all post IDs again, and see when there last update date was. because we saved the campaign, this has changed. and now we don't update those.
            // once we update all the posts, we begin doing the normal search iteration operation
            // but, before we post, we check for duplicates. we do this by looking at the array of existing posts on the site. the array will contain ASIN information. if the ASIN matches on already, we handle the duplicate accordingly.

            @set_time_limit(0);

            ob_start(); // start the output buffer for logging
            
            // Check if we need to use cron
            if( isset( $_REQUEST['proso-cron-key'] ) ) {

                $campaign_id = $_REQUEST['campaign_id'];

                $this->campaign = new ProssociateCampaign();
                $this->campaign->load($campaign_id);

                if ($this->campaign->campaign_settings['refresh'] == 'refresh') {

                    $check_time = (time() - $this->campaign->campaign_settings["refresh_time"] * 60 * 60);

                    if ($this->campaign->cron_last_run_time < $check_time) {

                        $page = $this->campaign->cron_page;
                        $mode = $this->campaign->cron_mode;
                        $update_offset = 0;

                        if (!$page) {
                            $page = 1;
                        }

                        if (!$mode || $mode == 'error') {
                            $mode = "update";
                        }

                        echo $mode;
                        echo "an update is necessary";
                    } else {
                        die("no update necessary");
                    }
                } else {
                    echo "box is not checked in campaign settings to refresh data";
                }
            } 
            else 
            {
                // prevent cron from running when posting new products
                update_option('pros_active_cron', 'active_cron');
                $total_products_from_js = $_REQUEST['total_products'];
                $campaign_id = $_REQUEST['campaign_id'];
                $page = $_REQUEST['page'];
                $mode = $_REQUEST['mode'];
                $var_offset = $_REQUEST['var_offset'];
                $poster_offset = $_REQUEST['poster_offset'];
                $update_offset = $_REQUEST['update_offset'];
                $global_counter = $_REQUEST['global_counter'];
                
                $this->campaign = new ProssociateCampaign();
                $this->campaign->load($campaign_id);
                
            } // End cron           

            if ($mode == "update") { // we are updating existing posts
                $set_mode = "update"; // keep us in update mode for the JSON. if we are leaving update mode, we'll change this
                // get all the posts associated with the campaign, if any
                $assoc_posts = $this->campaign->associated_posts;
                
                // check if there are associated posts in the campaign
                if( $assoc_posts )
                {
                    $newAssoc_posts = array();
                    // Re create the array 
                    // This is to fix the indexing
                    foreach( $assoc_posts as $post )
                    {
                        $newAssoc_posts[] = array(
                            'id' => $post['id'],
                            'asin' => $post['asin'],
                            'updated' => $post['updated']
                        );
                    }
                    
                    // Total number of associated posts
                    $assocPostsCount = count( $assoc_posts );
                    
                    // For loop iteration counter
                    $loopCounter = 0;
                    
                    $global_counter = (int)$global_counter;
                    $poster_offset = (int)$poster_offset;
                    
                    for( $counter = $update_offset; $counter < $assocPostsCount; $counter++ )
                    {
                        $global_counter++;
                        $poster_offset++;
                        // Increment the tracker
                        $loopCounter++;
                       
                        // Update the update_offset
                        $update_offset = (int)$update_offset+ 1;
                        
                        // Check if the post still exists
                        if( !get_post( $newAssoc_posts[$counter]['id'] ) )
                        {
                            // dissociate the post in the campaign
                            echo "Dissocating ";
                            echo $assoc_posts[$counter]['id'];
                            echo "...<br />";
                            
                            // dissociate the post
                            $this->campaign->dissociate_post( $assoc_posts[$counter]['id'] );
                            $this->campaign->save();
                            break;
                        }
                        
                        // Get the time 30 mins ago
                        $checktime = time() - (60 * 1);
                        
                        // If the post was last updated later than 30 mins ago
                        if( $newAssoc_posts[$counter]['updated'] < $checktime )
                        {
                            $post_id = $newAssoc_posts[$counter]['id'];
                            
                            // get ASIN from post
                            $ASIN = get_post_meta($post_id, '_pros_ASIN', true); // kind of unnecessary, because we have the ASIN in the $post array.
                            // get item from ASIN, like this:
                            $item = new ProssociateItem($ASIN);
                            
                            // update the post
                            // shoudn't we have variations here? - dm
                            $post_id = $this->post($item, $post_id, true);
                            
                            $this->campaign->associated_posts[$post_id] = array( "id" => $post_id, "asin" => $ASIN, "updated" => time() );
                            $this->campaign->save();
                            
                            // If the post was successful. Check if variation exists
                            if ( isset( $item->data->VariationSummary ) ) 
                            {
                                // Delete old variations
                                echo "Deleting previous variations. <br />";
                                $this->delete_old_variations($post_id);
                                // Add variation data in the database
                                update_option( 'dm_pros_var_data', $this->var_data );
                                update_option( 'dm_pros_var_post_id', $this->var_post_id );
                                update_option( 'dm_pros_var_post', $this->var_post );
                                update_option( 'dm_pros_var_update_operation', $this->var_update_operation );
                                update_option( 'dm_pros_var_post_options', $this->var_post_options );

                                $set_mode = 'variation';
                                $var_offset = 0;
                                $poster_offset++;
                                $global_counter++;
                                //$data['poster_offset'] = $poster_handler;
                                //$complete = false;
                                break;
                            }

                            echo "Updated ";
                            echo $post_id;
                            echo " - ";
                            echo $ASIN;
                            echo "<br />";
                            // Just to separate logs
                            echo "------------------------ <br />";
                        }
                        else
                        {
                            $post_id = $newAssoc_posts[$counter]['id'];
                            $ASIN = get_post_meta($post_id, '_pros_ASIN', true);

                            echo "Updated ";
                            echo $post_id;
                            echo " - ";
                            echo $ASIN;
                            echo " less than 30 minutes ago, no update necessary...<br />";
                        } // end if last update time < 30 mins
                        
                        // If all the associated posts was checked for update
                        $ctr = (int)$counter + 1; // to bypass the 0 index of array
                        if( $ctr == $assocPostsCount )
                        {
                            echo "<b><i>Update operation complete -  posts for any new products...</i></b><br /><br />";
                            $set_mode = "create";

                            break;
                        }
                        
                        // here's where idea of posting 5 products per ajax came from
                        // Check if the loop executed 5 times
                        if( $loopCounter >= 5) 
                        {
                            // we updated more than 5 posts
                            echo "<i>Updated five... iterating...</i><br /><br />";
                            break;
                        }
                        
                    } // End for
                    
                }
                else
                {
                    echo "<b><i>No posts need an update,  posts for any new products...</i></b><br /><br />";
                    $set_mode = "create";
                } // end if there are associated posts
                
                $log = ob_get_clean();

                $data['log'] = $log;
                $data['total_products'] = $total_products_from_js;
                $data['campaign_id'] = $campaign_id;
                $data['page'] = 1;
                $data['mode'] = $set_mode;
                $data['complete'] = false;
                $data['poster_offset'] = $poster_offset;
                $data['var_offset'] = $var_offset;
                $data['update_offset'] = $update_offset;
                $data['global_counter'] = $global_counter;

                $data = json_encode($data);

                echo $data;

                die();
            } 
            else if ($mode == "create") // CREATE NEW POSTS
            {
                //var_dump($this->campaign->options);
                $log_messages = '';
                // poster offset
                $poster_handler = 0;
                
                // Result container
                $dmResults = array();
                // yuri - get selected product list
                $ASINs_string = $this->campaign->options["ASINs"];
                $ASINs = explode(',', $ASINs_string);

                // If we are using 'All' limit it to 5 page
                if($this->campaign->options['searchindex'] == 'All')
                    $maxPage = 6;
                else
                    $maxPage = 10;

                //var_dump($page);
                
                if((int)$page <= $maxPage) {
                    $search = new ProssociateSearch($this->campaign->options['keywords'], $this->campaign->options['searchindex'], $this->campaign->options['browsenode'], $this->campaign->options['sortby']);
                    $search->page = $page;

                    $search->execute('Small', false);

                    // Go through the results
                    foreach( $search->results as $result )
                    {
                        // Check if there are selected products
                        if( $ASINs_string != '' && count( $ASINs ) > 0 )
                        {
                            // Check if the result isn't selected
                            if( !in_array( $result['ASIN'], $ASINs ) )
                            {
                                continue;
                            }
                        }

                        // the result product was selected
                        $dmResults[] = $result;
                    }

                    $addPage = true;

                    // Iteration counter
                    $iterationCounter = 0;
                    // Loop through the results
                    for( $counter = (int)$poster_offset; $counter < count($dmResults); $counter++ )
                    {
                        // Reload the page 
                        if( $iterationCounter == 10 )
                        {
                            $log_messages = 'Processing next batch of products' . $log_messages;
                            break;
                        }

                        // Make sure that we are tracking the products
                        $poster_handler = $counter + 1;

                        // the product doesn't exist until proven
                        $does_exist = false;

                        // Check if there are associated posts on the campaign
                        if( $this->campaign->associated_posts )
                        {
                            // Loop through each of the associated posts
                            foreach( $this->campaign->associated_posts as $post )
                            {
                                // Check if the current selected product already exists
                                if( $post['asin'] == $dmResults[$counter]['ASIN'] )
                                {
                                    // prove that product exist
                                    $does_exist = true;
                                    break;
                                }
                            }
                        } // end if

                        // Check if the product already exist
                        if( $does_exist )
                        {
                            $log_messages = "Skipping " . $dmResults[$counter]['ASIN'] . ", already exists, continuing...<br />" . $log_messages;
                        }
                        else
                        {
                            echo "Attempting to post " . $dmResults[$counter]['ASIN'] . "...<br />";

                            // Load the current selected product data
                            // Amazon only
                            $item = new ProssociateItem( $dmResults[$counter]['ASIN'], 'Amazon' );
							
							// Check for duplicates
							$checkIfDuplicate = $this->checkIfAsinExists($dmResults[$counter]['ASIN']);
							if($checkIfDuplicate) {
								$global_counter++;
								echo 'Detected as duplicate.<br />';
								echo '------------------------<br />';
								continue;
							}


                            if($item->isValid) {
                                // Post the product
                                $post_id = $this->post($item);
                            } else {
                                $post_id = false;
                            }

                            // Check if the product was posted
                            if( $post_id )
                            {
                                // Associate the product with the campaign
                                $this->campaign->associate_post( $post_id, $dmResults[$counter]['ASIN'] );
                                $this->campaign->save();

                                echo  "Posted " . $dmResults[$counter]['ASIN'] . ", continuing...<br />";
                                echo "------------------------ <br />";

                                $global_counter++;
                                $setVariation = true;
                            }
                            else
                            {
                                // Not posted
                                $global_counter++;
                                if($item->code == 100) {
                                    echo "Product ". $dmResults[$counter]['ASIN'] . " can't be posted because it has too many variations. <br />";
                                    // Try to die here. This might solve the issue of having unexpected token on the js.
                                    break;
                                }
                                else
                                {
                                    echo "Did not post " . $dmResults[$counter]['ASIN'] . " because it is unavailable, continuing...<br />";
                                }

                                // If the variation is not posted. Then we don't need to set it as mode variation
                                $setVariation = false;
                            } // end if $post_id



                        } // end if $does_exit

                        $iterationCounter++;
                    } // end  for loop

                    if ($this->campaign->options['searchindex'] != 'All' && $this->campaign->options['searchindex'] != 'Blended') {
                        if ($search->totalpages >= 10) {
                            $search->totalpages = 10;
                        }
                    } else {
                        if ($search->totalpages >= 5) {
                            $search->totalpages = 5;
                        }
                    }


                    if( $addPage )
                    {
                        $page++;
                    }

                    // For safe keeping
                    $testCounter = $global_counter;

                    // Check if we had posted all products
                    if($testCounter >= $total_products_from_js) {
                        $testCounter = 96;
                    }

                    $complete = false;

                    $tempComplete = false;
                    if($testCounter == $total_products_from_js) {
                        $tempComplete = true;
                    }

                    if($tempComplete) {
                        $complete = true;
                    } else {
                        $complete = false;
                    }
            }


                if ($page > $search->totalpages && $testCounter >= 70 ) {
                    $this->delete_products();
                    $complete = true;

                    // yuri - refresh attribute cache
                    $transient_name = 'wc_attribute_taxonomies';
                    $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                    set_transient($transient_name, $attribute_taxonomies);
                }


            // If we are on maxPage = 5
            if($maxPage = 5 && $page >= $maxPage) {
                $this->delete_products();
                $complete = true;

                // yuri - refresh attribute cache
                $transient_name = 'wc_attribute_taxonomies';
                $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                set_transient($transient_name, $attribute_taxonomies);
            } else {
                $complete = false;
            }

                $log = ob_get_clean(); // get the contents of the output buffer. this way, if there are any error messages, hopefully they show up on the frontend. - later: this isn't happening. maybe we need try / catch blocks.

                if (isset($_REQUEST['proso-cron-key'])) {
                    if ($complete == true) {
                        $this->campaign->cron_mode = '';
                        $this->campaign->cron_page = '';
                        $this->campaign->cron_last_run_time = time();
                        $this->campaign->save();
                    } else {
                        $this->campaign->cron_mode = 'complete';
                        $this->campaign->cron_page = $page;
                        $this->campaign->save();
                    }
                }

                $data['log'] = $log . $log_messages;
                $data['total_products'] = $total_products_from_js;
                $data['campaign_id'] = $campaign_id;
                $data['page'] = $page;
                $data['mode'] = 'create';
                $data['update_offset'] = $update_offset;
                $data['global_counter'] = $global_counter;

                
                $data['complete'] = $complete;

                $data = json_encode($data);

                echo $data;

                die();
            } //END CREATE NEW POSTS
            else if ($mode == 'complete') {
                // Delete error products
                $this->delete_products();
                // If the posting products is complete reactivate cron
                update_option('pros_active_cron', 'not_active_cron');
                if (isset($_REQUEST['proso-cron-key'])) {
                    $this->campaign->cron_mode = '';
                    $this->campaign->cron_page = '';
                    $this->campaign->cron_last_run_time = time();
                    $this->campaign->save();
                }
            } else {
               // If the posting products is complete reactivate cron
                update_option('pros_active_cron', 'not_active_cron');
                $data['log'] = 'Error';
                $data['campaign_id'] = $campaign_id;
                $data['page'] = $page;
                $data['complete'] = true;
                $data['mode'] = 'error';

                $data = json_encode($data);

                echo $data;

                die();
            }
        } catch (Exception $e) {
            // If the posting products is complete reactivate cron
            update_option('pros_active_cron', 'not_active_cron');
            var_dump($e);
            $log = ob_get_clean();

            $data['log'] = $log;
            $data['campaign_id'] = 'error';
            $data['page'] = 'error';
            $data['complete'] = true;
            $data['mode'] = 'error';

            $data = json_encode($data);

            echo $data;

            die();
        }
?>
        <?php

        die();
    }

    function start_process() {

        include PROSSOCIATE_ROOT_DIR . "/views/campaign/process.php";
    }
	
	/**
	 * Check if product already posted
	 * @param $asin
	 * @return bool
	 */
	private function checkIfAsinExists($asin) {
		$args = array(
			'post_type' => 'product',
			'meta_key' => '_pros_ASIN',
			'meta_value' => $asin
		);
		
		$query = new WP_Query($args);
		
		if(empty($query->posts)) {
			return false;
		}
		
		return true;
	}

    function post($item, $post_id = null, $fromUpdate = false) {
        $dmCheckifUpdate = false;
        // If updating products remove the prices meta fields to remove conflict
        if($post_id != null) {
            $dmCheckifUpdate = true;
            delete_post_meta($post_id, '_regular_price');
            delete_post_meta($post_id, '_sale_price');
            delete_post_meta($post_id, '_price');
        }
        // Get the data
        $data = $item->data;

        // Check if we have amazon offers
        if($data->Offers->TotalOffers === 0) {
            $asin = $data->ASIN;
            unset($item); // Free some memory
            unset($data);
            $item = new ProssociateItem($asin);
            $data = $item->data;
        }

        // Check if we have offerlistings
        if($data->Offers->TotalOffers > 0) {
            // Check if array
            if(is_array($data->Offers->Offer)) {
                foreach($data->Offers->Offer as $offer) {
                    // Check if there's no offer listing
                    if(!isset($offer->OfferListing->OfferListingId)) {
                        continue;
                    } else {
                        $finalOffer = $offer->OfferListing->OfferListingId;
                        $finalPrice = $offer->OfferListing->Price->FormattedPrice;
                        $finalAmount = $offer->OfferListing->Price->Amount;
                        break;
                    }
                }
            } else {
                // For non-array
                // Check if offer listing exists
                if(isset($data->Offers->Offer->OfferListing->OfferListingId)) {
                    $finalOffer = $data->Offers->Offer->OfferListing->OfferListingId;
                    $finalPrice = $data->Offers->Offer->OfferListing->Price->FormattedPrice;
                    $finalAmount = $data->Offers->Offer->OfferListing->Price->Amount;
                }
            }
        }

        if ($post_id) {
            $update_operation = true;
            // If on update use the previously defined title. This is to avoid overridding of user-defined title
            $finalTitle = get_the_title($post_id);

            // Get existing excerpt
            $dmPost = get_post($post_id);
            $finalExcerpt = $dmPost->post_excerpt;
        } else {
            $update_operation = false;

            // If not on update generate a custom title
            // Limit title length
            $titleLength = get_option('prossociate_settings-title-word-length', 9999);
            if(!is_numeric($titleLength))
                $titleLength = 9999;

            $trimmedTitle = wordwrap($item->Title, $titleLength, "dmpros123", false);
            $explodedTitle = explode("dmpros123", $trimmedTitle);
            $finalTitle = $explodedTitle[0];

            $finalExcerpt = '';
        }

        $post_options = $this->campaign->post_options;
        $search_parameters = $this->campaign->search_parameters;
        $campaign_settings = $this->campaign->campaign_settings;
        
        // ----------------------------------
        // SET UP THE POST ARRAY
        $post = array(
            'post_author' => $post_options['author'],
            'post_content' => '[prosociate]',
            'post_status' => 'publish',
            'post_title' => $finalTitle,
            'post_type' => $post_options['post_type'],
            'post_excerpt' => $finalExcerpt
        );
        
         if(isset($post_options['comment_status'])) {
             if($post_options['comment_status'] == 'open') {
                 $post['comment_status'] = 'open';
             } else {
                 $post['comment_status'] = 'closed';
             }
         }
         
         if(isset($post_options['ping_status'])) {
             if($post_options['ping_status'] == 'open') {
                 $post['ping_status'] = 'open';
             } else {
                 $post['ping_status'] = 'closed';
             }
         }


        // INSERT THE POST
        if ($post_id) { // we're updating an existing post
            $post['ID'] = $post_id;
        }

        // check campaign settings - if it is not available, don't post it.
        if (($data->OfferSummary->TotalNew + $data->OfferSummary->TotalUsed + $data->OfferSummary->TotalCollectible + $data->OfferSummary->TotalRefurbished) > 0) {
            $Availability = true;
        } else {
            if (!isset($data->VariationSummary)) {    // unless there are variations, it really is unavailable
                $Availability = false;
            } else {
                $Availability = true;
            }
        }
        
        if ($Availability == true) {
            $post_id = wp_insert_post($post);
            update_post_meta($post_id, '_pros_Available', "yes");
        } else {
            if ($post_id) {
                if ($campaign_settings['availability'] == 'remove') {
                    wp_delete_post($post_id, true);
                    return false;
                } else {

                    update_post_meta($post_id, '_pros_Available', "no");
                }
            }

            return false;
        }
        
        // Save last update time
        update_post_meta( $post_id, '_pros_last_update_time', time() );
        
        // Check if there are variations
        $dmIsVariation = false;
        if (isset($data->VariationSummary) ) {
            $dmIsVariation = true;
        }
        
        if($update_operation) {
            $dmIsVariation = true;
        }
        
        if($fromUpdate) {
            $dmIsVariation = true;
        }
        
        $this->standard_custom_fields($data, $post_id, $dmIsVariation);
        
        // INSERT FEATURED IMAGES
        if ($post_options['download_images'] == 'on') {
            $this->set_post_images($data, $post_id, $dmIsVariation);
        }



        // WooCommerce support
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

            // Check if we will add attributes
            if(isset($this->campaign->options['dmAdditionalAttributes']) && $this->campaign->options['dmAdditionalAttributes'] === 'true') {

            } else {
                $this->set_woocommerce_attributes($data, $post_id, $post, $update_operation, $post_options);
            }

            // Set product attributes
            $this->set_woocommerce_fields($data, $post_id, $dmIsVariation);
            wp_set_post_terms($post_id, 'external', 'product_type', false);
            update_post_meta($post_id, '_dmaffiliate', 'affiliate');
        }

        // If users selected categories then put the campaigns on those categories
        if(isset($post_options['dm_select_category'])) {
            if ($post_options['dm_select_category'] == 'yes') {
                $forcedAssignedCats = $this->campaign->options['dmcategories'];

                // Remove the 0 term id
                $removeZeroTermId = array_shift($forcedAssignedCats);

                // Assign the post on the categories created
                wp_set_post_terms( $post_id,  $forcedAssignedCats, 'product_cat', true );
            }
        }

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Try to get the listing price
        $finalListingPrice = 0;
        if(isset($data->ItemAttributes->ListPrice->Amount)) {
            $finalListingPrice = $data->ItemAttributes->ListPrice->Amount; // Get the listing int amount
            $finalProcessedListingPrices = $this->reformat_prices($this->remove_currency_symbols($data->ItemAttributes->ListPrice->FormattedPrice));
        }

        // Add the offer id and price
        update_post_meta($post_id, '_dmpros_offerid', $finalOffer);
        update_post_meta($post_id, '_price', $finalProcessedPrice);


        // Handle prices with Too low to display
        if($finalPrice === 'Too low to display') {
            update_post_meta($post_id, '_price', '0');
            update_post_meta($post_id, '_filterTooLowPrice', 'true');
        } elseif($finalAmount < $finalListingPrice) {  // Handle the regular / sale price
            update_post_meta($post_id, '_regular_price',$finalProcessedListingPrices);
            update_post_meta($post_id, '_sale_price', $finalProcessedPrice);
        }

        if(empty($finalProcessedPrice))
            $this->set_woocommerce_fields_prices($data, $post_id);

        // Check if we have valid post id
        if(is_int($post_id))
            $this->wordpressSeobyYoastIntegration($post_id, $finalTitle);

        // Insert ASIN as SKU
        update_post_meta($post_id, '_sku', $data->ASIN);
        
        // return the post ID
        return $post_id;
    }

    /**
     * Add wordpress seo by yoast integration
     * @param int $post_id
     * @param string $finalTitle
     */
    private function wordpressSeobyYoastIntegration($post_id, $finalTitle) {
        // Trim the title with 70 chars
        $title = substr($finalTitle, 0, 70);

        // Get the description
        $description = '';
        $EditorialReviews = unserialize(get_post_meta($post_id, '_pros_EditorialReviews', true));

        if ($EditorialReviews->EditorialReview) {
            if (count($EditorialReviews->EditorialReview) == 1) {
                $description .= $EditorialReviews->EditorialReview->Content;
            } else {
                foreach ($EditorialReviews->EditorialReview as $er) {
                    $description .= $er->Content;
                }
            }
        }

        // Trim the description with 156 chars
        $desc = substr($description, 0, 156);

        // Update the meta for wordpress seo by yoast
        update_post_meta($post_id, '_yoast_wpseo_title', $title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
    }

    function set_woocommerce_attributes($data, $post_id, $post, $update_operation, $post_options) {
        if(!$update_operation) {
            echo "Creating attributes for product {$data->ASIN}. <br />";
        }
        global $wpdb;
        global $woocommerce;

        // yuri - convert Amazon attributes into woocommerce attributes
        $_product_attributes = array();
        $position = 0;
        
        foreach( $data->ItemAttributes as $key => $value )
        {
            if (!is_object($value)) 
            {
                // For clothing size hack
                if($key === 'ClothingSize') {
                    $key = 'Size';
                }
                //var_dump($key);
                // yuri - change dimension name as woocommerce attribute name
                $attribute_name = $woocommerce->attribute_taxonomy_name(strtolower($key));
                $_product_attributes[$attribute_name] = array(
                    'name' => $attribute_name,
                    'value' => '',
                    'position' => $position++,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
                $this->add_attribute_value($post_id, $key, $value);
            }
        }
        
        // yuri - update product attribute
        update_post_meta($post_id, '_product_attributes', serialize($_product_attributes));
    }

    // yuri - add attribute values
    function add_attribute_value($post_id, $key, $value) {
        global $wpdb;
        global $woocommerce;

        // get attribute name, label
        $attribute_label = $key;
        $attribute_name = woocommerce_sanitize_taxonomy_name($key);

        // set attribute type
        $attribute_type = 'select';
        
        // check for duplicates
        $attribute_taxonomies = $wpdb->get_var("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = '$attribute_name'");
        
        if ($attribute_taxonomies) {
            // update existing attribute
            $wpdb->update(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
                'attribute_label' => $attribute_label,
                'attribute_name' => $attribute_name,
                'attribute_type' => $attribute_type,
                'attribute_orderby' => 'name'
                    ), array('attribute_name' => $attribute_name)
            );
        } else {
            // add new attribute
            $wpdb->insert(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
                'attribute_label' => $attribute_label,
                'attribute_name' => $attribute_name,
                'attribute_type' => $attribute_type,
                'attribute_orderby' => 'name'
                    )
            );
        }

        // avoid object to be inserted in terms
        if (is_object($value))
            return;

        // add attribute values if not exist
        $taxonomy = $woocommerce->attribute_taxonomy_name($attribute_name);
        
        if( is_array( $value ) )
        {
            $values = $value;
        }
        else
        {
            $values = array($value);
        }
        
        // check taxonomy
        if( !taxonomy_exists( $taxonomy ) ) 
        {
            // add attribute value
            foreach ($values as $attribute_value) {
                if(is_string($attribute_value)) {
                    // add term
                    $name = stripslashes($attribute_value);
                    $slug = sanitize_title($name);
                    if( !term_exists($name) ) {
                        if( $slug != '' && $name != '' ) {
                            $wpdb->insert(
                                    $wpdb->terms, array(
                                'name' => $name,
                                'slug' => $slug
                                    )
                            );

                            // add term taxonomy
                            $term_id = $wpdb->insert_id;
                            $wpdb->insert(
                                    $wpdb->term_taxonomy, array(
                                'term_id' => $term_id,
                                'taxonomy' => $taxonomy
                                    )
                            );
                        }
                    }
                } // End if 
            } //  End foreach
        } 
        else 
        {
            // get already existing attribute values
            $attribute_values = array();
            $terms = get_terms($taxonomy);
            foreach ($terms as $term) {
                $attribute_values[] = $term->name;
            }
            
            // DM
            // Check if $attribute_value is not empty
            if( !empty( $attribute_values ) )
            {
                foreach( $values as $attribute_value ) 
                {
                    if( !in_array( $attribute_value, $attribute_values ) ) 
                    {
                        // add new attribute value
                        wp_insert_term($attribute_value, $taxonomy);
                    }
                }
            }
        }

        // Add terms
        if( is_array( $value ) )
        {
            foreach( $value as $dm_v )
            {
                if(is_string($dm_v)) {
                    wp_insert_term( $dm_v, $taxonomy );
                }
            }
        }
        else
        {
            if(is_string($value)) {
                wp_insert_term( $value, $taxonomy );
            }
        }

        // link to woocommerce attribute values
        if( !empty( $values ) )
        {
            //pre_print_r( 'Values not empty ');
            foreach( $values as $term )
            {
                
                if( !is_object( $term ) )
                {
                    $term = sanitize_title($term);
                    
                    $term_taxonomy_id = $wpdb->get_var( "SELECT tt.term_taxonomy_id FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} as tt ON tt.term_id = t.term_id WHERE t.slug = '{$term}' AND tt.taxonomy = '{$taxonomy}'");
                    
                    if( $term_taxonomy_id ) 
                    {
                        $checkSql = "SELECT * FROM {$wpdb->term_relationships} WHERE object_id = {$post_id} AND term_taxonomy_id = {$term_taxonomy_id}";
                        if( !$wpdb->get_var($checkSql) ) {
                            $wpdb->insert(
                                    $wpdb->term_relationships, array(
                                'object_id' => $post_id,
                                'term_taxonomy_id' => $term_taxonomy_id
                                    )
                            );
                        }
                    }

                }
                 
            }
            
        }
    }
    
    private function delete_products() {
        $today = getdate();
        $lastPosts = new WP_Query( array(
                    'year' => $today["year"],
                'monthnum' => $today["mon"],
                'day' => $today['mday'],
                'post_type' => 'product',
                'meta_query' => array(
                    array(
                        'key' => '_price',
                        'value' => '',
                        'compare' => 'NOT EXISTS'
                    )
                )
               ));
        
        foreach($lastPosts->posts as $post ) {
            wp_delete_post($post->ID, true);
        }
    }
    
    private function delete_old_variations($post_id) {
        if(is_int($post_id)) {
            $args = array(
                'post_parent' => $post_id,
                'post_type' => 'product_variation'
            );

            $remove_posts = get_posts($args);

            if (is_array($remove_posts) && count($remove_posts) > 0) {

                foreach ($remove_posts as $remove_post) {
                    echo "Removing Variation Post " . $remove_post->ID;
                    echo "...<br />";
                    wp_delete_post($remove_post->ID, true);
                }
            }
        }
    }

    function standard_custom_fields($data, $post_id, $isVariation = false ) {
        if(!$isVariation) {
            echo "Inserting meta fields for {$data->ASIN}. <br />";
        }
        
        if(isset($data->ItemAttributes)) {
            update_post_meta($post_id, '_pros_ItemAttributes', serialize($data->ItemAttributes));
        }
        if(isset($data->Offers)) {
            update_post_meta($post_id, '_pros_Offers', serialize($data->Offers));
        }
        if(isset($data->OfferSummary)) {
            update_post_meta($post_id, '_pros_OfferSummary', serialize($data->OfferSummary));
        }
        if(isset($data->SimilarProducts)) {
            update_post_meta($post_id, '_pros_SimilarProducts', serialize($data->SimilarProducts));
        }
        if(isset($data->Accessories)) {
            update_post_meta($post_id, '_pros_Accessories', serialize($data->Accessories));
        }
        if(isset($data->ASIN)) {
            update_post_meta($post_id, '_pros_ASIN', $data->ASIN);
        }
        if(isset($data->ParentASIN)) {
            update_post_meta($post_id, '_pros_ParentASIN', $data->ParentASIN);
        }
        if(isset($data->DetailPageURL)) {
            update_post_meta($post_id, '_pros_DetailPageURL', $data->DetailPageURL);
        }
        if(isset($data->CustomerReviews)) {
            update_post_meta($post_id, '_pros_CustomerReviews', serialize($data->CustomerReviews));
        }
        if(isset($data->EditorialReviews)) {
            update_post_meta($post_id, '_pros_EditorialReviews', serialize($data->EditorialReviews));
        }
        if(isset($data->VariationSummary)) {
            update_post_meta($post_id, '_pros_VariationSummary', serialize($data->VariationSummary));
        }
        if(isset($data->Variations->VariationDimensions)) {
            update_post_meta($post_id, '_pros_VariationDimensions', serialize($data->Variations->VariationDimensions));
        }
        
        if(isset($data->Variations->TotalVariations)) {
            if ($data->Variations->TotalVariations > 0) {
                if (count($data->Variations->Item) == 1) {
                    update_post_meta($post_id, '_pros_FirstVariation', serialize($data->Variations->Item));
                } else {
                    update_post_meta($post_id, '_pros_FirstVariation', serialize($data->Variations->Item[0]));
                }
            }
        }
    }
    
    private function set_post_featured_thumb($image_url, $title, $post_id) {
        $upload_dir = wp_upload_dir();
        //$image_data = file_get_contents($image_url);
        $dmImage = wp_remote_get($image_url);
        $image_data = wp_remote_retrieve_body($dmImage);
        
        $filename = substr(md5($image_url), 0, 12) . "." . pathinfo($image_url, PATHINFO_EXTENSION);
        
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
            
        file_put_contents($file, $image_data);
        
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit'
         );
        
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }

    function set_post_images($data, $post_id, $variation = false ) {
        if(!$variation) {
            echo "Downloading product images for {$data->ASIN}. <br />";
        }
        if(isset($data->ImageSets->ImageSet)) {
            if (count($data->ImageSets->ImageSet) == 1) {

                $i = $data->ImageSets->ImageSet;

                $image_url = $i->LargeImage->URL;

                $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                set_post_thumbnail($post_id, $attach_id);
            } else {
                if (isset($data->ImageSets->ImageSet)) {
                    // Count the number of images
                    $imageSetCount = count($data->ImageSets->ImageSet);
                    // Gallery ids container
                    $dmGalleryIds = array();
                    foreach ($data->ImageSets->ImageSet as $k => $i) {
                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        // Check if on the first image
                        if ($k == 0) {
                            // Make the first image the featured image
                            set_post_thumbnail($post_id, $attach_id);
                        }

                        // Check if we're on variation
                        if( $variation ) {
                            // Process only 1 image for variations
                            break;
                        }          

                        // Store the post_id of the images to be attached as gallery
                        if ($k > 0) {
                            $dmGalleryIds[] = $attach_id;
                        }

                        // If we're on the last image
                        if ($k == ($imageSetCount - 1)) {
                            // Set the gallery
                            update_post_meta($post_id, '_product_image_gallery', implode(',', $dmGalleryIds));
                        }
                    }
                } else {
                    // Not it will go here when there are no available image
                    // If that's the case, we will get the image from the first variation

                    // Check if we have variation
                    if(is_array($data->Variations->Item)) {
                        if($data->Variations->Item[0]) {
                            $i = $data->Variations->Item[0];

                            // same code
                            $image_url = $i->LargeImage->URL;

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                            set_post_thumbnail($post_id, $attach_id);
                        } else {
                            echo "How do we end up here? Images bug: " . $post_id;
                        }
                    } else {
                        // If Variations->Item is not a n array
                        if($data->Variations->Item) {
                            $i = $data->Variations->Item;

                            // same code
                            $image_url = $i->LargeImage->URL;

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                            set_post_thumbnail($post_id, $attach_id);
                        } else {
                            echo "How do we end up here? Images bug: " . $post_id;
                        }
                    }
                }
            }
        } else {
            // If we don't have images from the product itself. We get it from the variations
            if(isset($data->Variations->Item)) {
                // Check if we have variation
                if(is_array($data->Variations->Item)) {
                    if($data->Variations->Item[0]) {
                        $i = $data->Variations->Item[0];

                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        set_post_thumbnail($post_id, $attach_id);
                    } else {
                        echo "How do we end up here? Images bug: " . $post_id;
                    }
                } else {
                    // If Variations->Item is not a n array
                    if($data->Variations->Item) {
                        $i = $data->Variations->Item;

                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        set_post_thumbnail($post_id, $attach_id);
                    } else {
                        echo "How do we end up here? Images bug: " . $post_id;
                    }
                }
            }
        }
    }
    
    function set_woocommerce_fields($data, $post_id, $isVariation = false) {
        if(!$isVariation) {
            echo "Populating cart info for {$data->ASIN}. <br />";
        }
        
        if(isset($data->DetailPageURL)) {
            update_post_meta($post_id, '_product_url', $data->DetailPageURL);
        }

        // SET CUSTOM FIELDS
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');
        update_post_meta($post_id, '_stock_status', 'instock');


        // SET AVAILABILITY

        if (get_post_meta($post_id, "_pros_Available", true) == "no") {
            update_post_meta($post_id, '_stock_status', "outofstock");
        }
    }

    function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
    }

    function set_woocommerce_fields_prices($data, $post_id) {
        // in case there is no price
        $backup_price = '';
        if (isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $backup_price = $data->ItemAttributes->ListPrice->FormattedPrice;
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $backup_price = $data->Offers->Offer->OfferListing->Price->FormattedPrice;
        }
        
        // If there's no other prices available (like ASIN: B00BTCWOQG, Disney Pixar Cars 2013 Diecast Flo Wheel Well Motel 7/11)
        if( isset($data->OfferSummary->LowestNewPrice->FormattedPrice) ) {
            $backup_price = $data->OfferSummary->LowestNewPrice->FormattedPrice;
        }

        // remove dollar signs from price
        $backup_price = $this->remove_currency_symbols($backup_price);
        // format the price
        $backup_price = $this->reformat_prices($backup_price);
        
        if (isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $data->ItemAttributes->ListPrice->FormattedPrice = $this->remove_currency_symbols($data->ItemAttributes->ListPrice->FormattedPrice);
            // format the price
            $data->ItemAttributes->ListPrice->FormattedPrice = $this->reformat_prices($data->ItemAttributes->ListPrice->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $data->Offers->Offer->OfferListing->Price->FormattedPrice = $this->remove_currency_symbols($data->Offers->Offer->OfferListing->Price->FormattedPrice);
            // Replace comma with period
            $data->Offers->Offer->OfferListing->Price->FormattedPrice = $this->reformat_prices($data->Offers->Offer->OfferListing->Price->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice)) {
            $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice = $this->remove_currency_symbols($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
            // Replace comma with period
            $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice = $this->reformat_prices($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice) && isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            
            if ($data->Offers->Offer->OfferListing->Price->FormattedPrice == $data->ItemAttributes->ListPrice->FormattedPrice) {
                // only set the regular price
                update_post_meta($post_id, '_regular_price', $data->ItemAttributes->ListPrice->FormattedPrice);
                update_post_meta($post_id, '_price', $data->ItemAttributes->ListPrice->FormattedPrice);
            }

            if ($data->Offers->Offer->OfferListing->Price->FormattedPrice < $data->ItemAttributes->ListPrice->FormattedPrice) {
                //  set the regular price and sale price
                update_post_meta($post_id, '_regular_price', $data->ItemAttributes->ListPrice->FormattedPrice);
                update_post_meta($post_id, '_price', $data->Offers->Offer->OfferListing->Price->FormattedPrice);
                update_post_meta($post_id, '_sale_price', $data->Offers->Offer->OfferListing->Price->FormattedPrice);
            }
            
            // Check if we problems with the price
            if(!get_post_meta($post_id, '_regular_price', true)) {
                // Convert the price to proper integer
                $regPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
                $salePrice = $data->Offers->Offer->OfferListing->Price->FormattedPrice;

                $regPrice = (int)str_replace(',', '', $regPrice);
                $salePrice = (int)str_replace(',', '', $salePrice);
                
                if($salePrice == $regPrice || $salePrice > $regPrice ) {
                    update_post_meta($post_id, '_regular_price', $regPrice);
                    update_post_meta($post_id, '_price', $regPrice);
                }
                
                if($salePrice < $regPrice) {
                    update_post_meta($post_id, '_regular_price', $regPrice);
                    update_post_meta($post_id, '_price', $salePrice);
                    update_post_meta($post_id, '_sale_price', $salePrice);
                }
            }
     
        }
        else {
            // only one price is available - it doesnt matter if it is the sale or regular price. we have to show it as regular, because we cant show a higher price as the regular.
            if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
                $insertRegPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            } else {
                $insertRegPrice = $backup_price;
            }
            
             if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
                $insertPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            } else {
                $insertPrice = $backup_price;
            }
            update_post_meta($post_id, '_regular_price', $insertRegPrice);
            update_post_meta($post_id, '_price', $insertPrice);
        }

        // Add the saleprice
        if(isset($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice)) {
            update_post_meta($post_id, '_price', $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
            update_post_meta($post_id, '_sale_price', $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
        }

    }

    function reformat_prices($price) {
        switch( AWS_COUNTRY ) {
            // Germany
            case 'de':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // France
            case 'fr':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // Spain
            case 'es':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // Italy
            case 'it':
                $formatPrice = $this->reformat_price_de($price);
                break;
            default:
                $formatPrice = str_replace(',', '', $price);
                break;
        }
        
        return $formatPrice;
    }
    
    function reformat_price_de($price) {
        // Convert the string to array
        $priceArray = str_split($price);
        foreach ($priceArray as $k => $v) {
            // Check if a period
            if ($v == '.') {
                // Convert the period to comma
                $priceArray[$k] = '';
            } elseif ($v == ',') {
                // Convert comma to period
                $priceArray[$k] = '.';
            }
        }
        // Convert the array to a string
        $formatPrice = implode('', $priceArray);
        
        return $formatPrice;
    }
    
    function admin_enqueue_scripts() {
        wp_register_script('prossociate_poster', PROSSOCIATE_ROOT_URL . '/js/ProssociatePoster.js');
        wp_enqueue_script('prossociate_poster');
    }

}

function my_shutdown_function() {

    $log = ob_get_clean();

    $data['log'] = $log;
    $data['campaign_id'] = 'error';
    $data['page'] = 'error';
    $data['complete'] = true;
    $data['mode'] = 'error';

    $data = json_encode($data);

    echo $data;
}

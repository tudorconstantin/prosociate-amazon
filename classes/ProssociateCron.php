<?php
class ProssociateCron {

    function __construct() {
        // Create the 2 min sched
        add_filter('cron_schedules', array($this, 'addCronSchedule'));

        add_action('dm_pros_check_cron', array($this, 'dm_pros_do_cron'));
        add_action('wp', array($this, 'dm_pros_activate_cron'));
    }

    /**
     * Add a new cron schedule
     * @param mixed $schedules
     * @return mixed
     */
    public function addCronSchedule($schedules) {
        $schedules['dmBiMinute'] = array(
            'interval' => 120,
            'display' => __('Once every 2 minutes.')
        );

        return $schedules;
    }

    public function dm_pros_do_cron() {  
        // Get the cron checker
        $isActiveCron = get_option('pros_active_cron');
        
        // Variation flag
        $isVariation = FALSE;
        
        // Bypass for max execution time
        if( $isActiveCron === 'active_cron' ) {
            // Get the last cron time
            $lastCronTime = get_option('pros_last_cron_time');
            
            // Check if the last cron time was greater than 5 mins ago
            if( (int)$lastCronTime < time() - 300) {
                // Assume that the last cron was halted by max execution time
                update_option('pros_active_cron', "not_active_cron");
                $isActiveCron = "not_active_cron";
            }
        }

        // Check if we dont have an active cron job
        if ($isActiveCron === "not_active_cron") {
            // Make the cron active
            update_option('pros_active_cron', "active_cron");
            // Time of the last cron
            update_option('pros_last_cron_time', time() );
            
            // Get the variation id if variation
            $variationId = get_option('pros_active_cron_variation');
            
            // Get the step
            $variationStep = get_option('pros_active_cron_variation_step');
            
            // Check if we have a variation step
            if($variationStep !== 'no_variation') {
               $this->variation_steps($variationStep, $variationId);
               return;
            }
            
            // Check if we are on variation
            // Check if a valid variation id
            if(is_numeric($variationId)){
                // If we're on variation
                // Set the variation id as the product id
                $productId = (int)$variationId;
                $isVariation = TRUE;
            }
            else {
                // If we're not on variation
                // Get the product
                $query = new WP_Query(array(
                    'post_type' => 'product',
                    'meta_query' => array(
                        array(
                            'key' => '_pros_last_update_time',
                            'value' => time() - 86400, // Should be 24 hours (86400)
                            'compare' => '<='
                        )
                    ),
                    'orderby' => 'meta_value_num',
                    'order' => 'DESC',
                    'posts_per_page' => 1
                ));
                
                // Check if there are products
                if ($query->post_count >= 1) {
                    $productId = $query->posts[0]->ID;
                }
                else {
                    $productId = null;
                }
                
                wp_reset_postdata();
            }
            
            // Check if we have a valid product ID
            if(is_int($productId)) {
                // See if product was featured
                $productIsFeatured = false;
                // See if product was affiliate
                $productIsAffiliate = false;

                $featured = get_post_meta($productId, '_featured', true);
                $affiliate = get_post_meta($productId, '_dmaffiliate', true);

                if($featured) {
                    if($featured === 'yes')
                        $productIsFeatured = true;
                }

                if($affiliate) {
                    if($affiliate === 'affiliate')
                        $productIsAffiliate = true;
                }

                // Product ASIN
                $productAsin = get_post_meta($productId, '_pros_ASIN', true);
                
                // Get product data from amazon
                $productData = $this->get_product_data($productAsin);
                
                // Update a single product
                $updateProduct = $this->update_product($productId, $productData);
                
                // Check if product has variations
                if( isset($productData->Variations) && $isVariation === FALSE ) {
                    // Set the next cron to be variation
                    update_option('pros_active_cron_variation', $productId);
                    // Set the next cron step
                    update_option('pros_active_cron_variation_step', 'delete_variations');
                }

                // Re-featured the product if its featured before
                if($productIsFeatured)
                    update_post_meta($productId, '_featured', 'yes');

                // Re-external the product
                if($productIsAffiliate)
                    wp_set_post_terms($productId, 'external', 'product_type', false);
                
                // Assuming the product was update
                update_post_meta($productId, '_pros_last_update_time', time());
            }

            // When the update is done make it as not active
            update_option('pros_active_cron', "not_active_cron");

        }

    }
    
    private function variation_steps( $step, $productParentId ) {
        global $wpdb;
        switch ($step) {
            case 'delete_variations':
                $this->delete_variations($productParentId);
                //Proceed to the next step
                update_option('pros_active_cron_variation_step', 'create_variations');
                // When the update is done make it as not active
                update_option('pros_active_cron', "not_active_cron");
                break;
            case 'create_variations':
                $this->create_variations($productParentId);
                update_option('pros_active_cron_variation_step', 'no_variation');
                update_option('pros_active_cron_variation', 'no_variation');
                // Refresh attributes
                $transient_name = 'wc_attribute_taxonomies';
                $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                set_transient($transient_name, $attribute_taxonomies);
                // Clean post cache
                clean_post_cache($productParentId);
                // When the update is done make it as not active
                update_option('pros_active_cron_variation_step', 'no_variation');
                update_option('pros_active_cron', "not_active_cron");
                break;
            default:
                break;
        }
    }
    
    private function create_variations( $productParentId ) {
        global $woocommerce;
        // Get the parent asin
        $productAsin = get_post_meta($productParentId, '_pros_ASIN', true);
        // Get the data
        $productData = $this->get_product_data($productAsin);
        
        // Check if there are variations
        if($productData->Variations->TotalVariations > 0) {
            // its not a simple product, it is a variable product
            wp_set_post_terms($productParentId, 'variable', 'product_type', false);
            
            // initialize the variation dimensions array
            if (count($productData->Variations->VariationDimensions->VariationDimension) == 1) {
                $VariationDimensions[$productData->Variations->VariationDimensions->VariationDimension] = array();
            } else {
                foreach ($productData->Variations->VariationDimensions->VariationDimension as $dim) {
                    $VariationDimensions[$dim] = array();
                }
            }
                 
            // loop through the variations, make a variation post for each of them
            if (count($productData->Variations->Item) == 1) {
                $variation_item = $productData->Variations->Item;
                $VariationDimensions = $this->post_variations($variation_item, $productParentId, $VariationDimensions);
            } else {
                // Get the variation offset
                $variationOffset = (int)get_option('pros_active_cron_variation_offset');
                // Loop through the remaining variations
                for($counter = $variationOffset; $counter<count($productData->Variations->Item); $counter++) {
                    // Create the variation
                    $VariationDimensions = $this->post_variations($productData->Variations->Item[$counter], $productParentId, $VariationDimensions);
                    // Update the counter
                    $newVariationOffset = $counter + 1;
                    update_option('pros_active_cron_variation_offset', $newVariationOffset);
                }
                // Reset the counter if all variations are posted
                if($newVariationOffset >= count($productData->Variations->Item)) {
                    update_option('pros_active_cron_variation_offset', 0);
                    // Delete the cache
                    delete_transient('wc_product_children_ids_' . $productParentId);
                }
                /*
                foreach ($productData->Variations->Item as $variation_item) {
                    $VariationDimensions = $this->post_variations($variation_item, $productParentId, $VariationDimensions);
                    // Get the current variation we're in
                    $variationOffset = (int)get_option('pros_active_cron_variation_offset');
                    // Increment the variation offset
                    $variationOffset++;
                    // Update the option
                    update_option('pros_active_cron_variation_offset', $variationOffset);
                }
                */
            }
        }
        
        $tempProdAttr = unserialize(get_post_meta( $productParentId, '_product_attributes', true ));
        
        foreach( $VariationDimensions as $name => $values )
        {
            $this->add_attribute_value($productParentId, $name, $values);
            $dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($name));
            $tempProdAttr[$dimension_name] = array(
                'name' => $dimension_name,
                'value' => '',
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            );
        }
        
        update_post_meta($productParentId, '_product_attributes', serialize($tempProdAttr));
    }
    
    private function post_variations($variation_item, $productParentId, $VariationDimensions) {
        global $woocommerce;
        
        $post['post_title'] = $variation_item->ItemAttributes->Title;
        $post['post_type'] = 'product_variation';
        $post['post_parent'] = $productParentId;
        $post['post_status'] = 'publish';
        $post['ID'] = null;
        
        // Post the variation
        $variation_post_id = wp_insert_post($post);
        // Update the custom fields
        $this->update_custom_fields($variation_post_id, $variation_item);
        
        // TODO Insert new image
        $this->set_post_images($variation_item, $variation_post_id);
        
        // Set woocommerce fields
        $this->set_woocommerce_fields($variation_item, $variation_post_id);

        if(is_array($variation_item->VariationAttributes->VariationAttribute)) {
            // Compile all the possible variation dimensions
            foreach ($variation_item->VariationAttributes->VariationAttribute as $va) {

                $curarr = $VariationDimensions[$va->Name];
                $curarr[$va->Value] = $va->Value;

                $VariationDimensions[$va->Name] = $curarr;

                // SET WOOCO VARIATION ATTRIBUTE FIELDS / yuri - change dimension name as woocommerce attribute name
                $dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($va->Name));
                update_post_meta($variation_post_id, 'attribute_' . $dimension_name, sanitize_title($va->Value));
            }
        } else {
            $va = $variation_item->VariationAttributes->VariationAttribute;
            $curarr = $VariationDimensions[$va->Name];
            $curarr[$va->Value] = $va->Value;

            $VariationDimensions[$va->Name] = $curarr;

            // SET WOOCO VARIATION ATTRIBUTE FIELDS / yuri - change dimension name as woocommerce attribute name
            $dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($va->Name));
            update_post_meta($variation_post_id, 'attribute_' . $dimension_name, sanitize_title($va->Value));
        }

        // Check if we have offerlistings
        if(isset($variation_item->Offers)) {
            // Check if array
            if(is_array($variation_item->Offers->Offer)) {
                foreach($variation_item->Offers->Offer as $offer) {
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
                if(isset($variation_item->Offers->Offer->OfferListing->OfferListingId)) {
                    $finalOffer = $variation_item->Offers->Offer->OfferListing->OfferListingId;
                    $finalPrice = $variation_item->Offers->Offer->OfferListing->Price->FormattedPrice;
                    $finalAmount = $variation_item->Offers->Offer->OfferListing->Price->Amount;
                }
            }
        } else {
            // if no offers
            return false;
        }

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Try to get the listing price
        $finalListingPrice = 0;
        if(isset($variation_item->ItemAttributes->ListPrice->Amount)) {
            $finalListingPrice = $variation_item->ItemAttributes->ListPrice->Amount; // Get the listing int amount
            $finalProcessedListingPrices = $this->reformat_prices($this->remove_currency_symbols($variation_item->ItemAttributes->ListPrice->FormattedPrice));
        }

        // Add the offer id and price
        update_post_meta($variation_post_id, 'dmpros_offerid', $finalOffer);
        update_post_meta($variation_post_id, '_price', $finalProcessedPrice);

        // Handle the regular / sale price
        if($finalAmount < $finalListingPrice) {
            update_post_meta($variation_post_id, '_regular_price',$finalProcessedListingPrices);
            update_post_meta($variation_post_id, '_sale_price', $finalProcessedPrice);
        }

        return $VariationDimensions;
    }
    
    private function set_post_images($data, $post_id, $variation = true) {
        if (count($data->ImageSets->ImageSet) == 1) {

            $i = $data->ImageSets->ImageSet;

            // same code
            $image_url = $i->LargeImage->URL;

            $upload_dir = wp_upload_dir();
            $image_data = file_get_contents($image_url);

            $filename = substr(md5($image_url), 0, 12) . "." . pathinfo($image_url, PATHINFO_EXTENSION);

            if (wp_mkdir_p($upload_dir['path']))
                $file = $upload_dir['path'] . '/' . $filename;
            else
                $file = $upload_dir['basedir'] . '/' . $filename;
            file_put_contents($file, $image_data);

            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => $data->ItemAttributes->Title,
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            set_post_thumbnail($post_id, $attach_id);
            // ---------------
        } else {

            if ($data->ImageSets->ImageSet) {
                // Count the number of images
                $imageSetCount = count($data->ImageSets->ImageSet);
                // Gallery ids container
                $dmGalleryIds = array();
                foreach ($data->ImageSets->ImageSet as $k => $i) {
                    // TODO: set alternatives if LargeImage isn't provided
                    // same code
                    $image_url = $i->LargeImage->URL;

                    $upload_dir = wp_upload_dir();
                    $image_data = file_get_contents($image_url);

                    $filename = substr(md5($image_url), 0, 12) . "." . pathinfo($image_url, PATHINFO_EXTENSION);

                    if (wp_mkdir_p($upload_dir['path']))
                        $file = $upload_dir['path'] . '/' . $filename;
                    else
                        $file = $upload_dir['basedir'] . '/' . $filename;
                    file_put_contents($file, $image_data);

                    $wp_filetype = wp_check_filetype($filename, null);
                    $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title' => $data->ItemAttributes->Title,
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );

                    $attach_id = wp_insert_attachment($attachment, $file, $post_id);
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                    wp_update_attachment_metadata($attach_id, $attach_data);

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


                    // ---------------
                }
            }
        }
    }
    
    private function set_woocommerce_fields($item, $post_id) {
        // TODO - availability update
        update_post_meta($post_id, '_product_url', $item->DetailPageURL);

        // SET PRICES
        $this->set_woocommerce_fields_prices($item, $post_id);

        // SET CUSTOM FIELDS
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');
        update_post_meta($post_id, '_stock_status', 'instock');
    }
    
    private function set_woocommerce_fields_prices($data, $post_id) {
        // in case there is no price
        $backup_price = '';
        if ($data->ItemAttributes->ListPrice->FormattedPrice) {
            $backup_price = $data->ItemAttributes->ListPrice->FormattedPrice;
        }

        if ($data->Offers->Offer->OfferListing->Price->FormattedPrice) {
            $backup_price = $data->Offers->Offer->OfferListing->Price->FormattedPrice;
        }

        // remove dollar signs from price
        $backup_price = $this->remove_currency_symbols($backup_price);
        if ($data->ItemAttributes->ListPrice->FormattedPrice) {
            $data->ItemAttributes->ListPrice->FormattedPrice = $this->remove_currency_symbols($data->ItemAttributes->ListPrice->FormattedPrice);
        }

        if ($data->Offers->Offer->OfferListing->Price->FormattedPrice) {
            $data->Offers->Offer->OfferListing->Price->FormattedPrice = $this->remove_currency_symbols($data->Offers->Offer->OfferListing->Price->FormattedPrice);
        }

        if ($data->Offers->Offer->OfferListing->Price->FormattedPrice && $data->ItemAttributes->ListPrice->FormattedPrice) {
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
        } else {
            // only one price is available - it doesnt matter if it is the sale or regular price. we have to show it as regular, because we cant show a higher price as the regular.
            update_post_meta($post_id, '_regular_price', ($data->ItemAttributes->ListPrice->FormattedPrice ? $data->ItemAttributes->ListPrice->FormattedPrice : $backup_price));
            update_post_meta($post_id, '_price', ($data->ItemAttributes->ListPrice->FormattedPrice ? $data->ItemAttributes->ListPrice->FormattedPrice : $backup_price));
        }
    }

    function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
    }
    
    private function delete_variations( $productParentId ) {
        global $wpdb;
        // Get the featured thumbnail of the main product
        $featuredImageIdArgs = array(
                'post_parent' => (int)$productParentId,
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'any'
            );
        $featuredImageId = get_children( $featuredImageIdArgs );
        
        // Get the featured image src
        foreach( $featuredImageId as $k ) {
            $featuredImage = get_post_meta($k->ID, '_wp_attached_file', true);
        }
        
        // Check if we got a featured image
        if( $featuredImage ) {
            // Container of the post ids that will not be deleted
            $saveArray = array();
            $query = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = '{$featuredImage}'";
            $results = $wpdb->get_results($query);
            
            // Store the ids
            foreach($results as $result) {
                $saveArray[] = $result->post_id;
            }
        }
        
        // Get variations
        $args = array(
            'post_parent' => (int)$productParentId,
            'post_type' => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'any'
        );
        $variationPosts = get_children( $args );
        
        // Delete 10 variation posts per requests
        foreach($variationPosts as $variationPost) {
            // Delete the attached images
            $this->delete_attached_images($variationPost->ID, $saveArray);
            // Delete the post
            wp_delete_post($variationPost->ID, true);
        }
    }
    
    private function delete_attached_images( $variationPostId, $saveArray ) {
        // Get the attached images
        $args = array(
            'post_parent' => (int)$variationPostId,
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'any'
        );
        $attachedimages = get_children( $args );
        
        // Delete the attached image
        foreach($attachedimages as $attachedimage) {
            // Check if the we will need to preserve the attachment
            if(!in_array($attachedimage->ID, $saveArray)) {
                wp_delete_attachment( $attachedimage->ID, true );
            }
        }
    }

    private function update_product($productId, $productData) {
        // Update the custom fields
        $this->update_custom_fields($productId, $productData);

        // Set the attributes for woocommerce
        $this->update_woocommerce_attributes($productId, $productData->ItemAttributes);
        
        // Set woocommerce fields
        $this->set_woocommerce_fields($productData, $productId);
        wp_set_post_terms($productId, 'simple', 'product_type', false);

        // Check if we have offerlistings
        if(isset($productData->Offers)) {
            // Check if array
            if(is_array($productData->Offers->Offer)) {
                foreach($productData->Offers->Offer as $offer) {
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
                if(isset($productData->Offers->Offer->OfferListing->OfferListingId)) {
                    $finalOffer = $productData->Offers->Offer->OfferListing->OfferListingId;
                    $finalPrice = $productData->Offers->Offer->OfferListing->Price->FormattedPrice;
                    $finalAmount = $productData->Offers->Offer->OfferListing->Price->Amount;
                }
            }
        } else {
            // if no offers
            return false;
        }

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Try to get the listing price
        $finalListingPrice = 0;
        if(isset($productData->ItemAttributes->ListPrice->Amount)) {
            $finalListingPrice = $productData->ItemAttributes->ListPrice->Amount; // Get the listing int amount
            $finalProcessedListingPrices = $this->reformat_prices($this->remove_currency_symbols($productData->ItemAttributes->ListPrice->FormattedPrice));
        }

        // Add the offer id and price
        update_post_meta($productId, 'dmpros_offerid', $finalOffer);
        update_post_meta($productId, '_price', $finalProcessedPrice);

        // Handle the regular / sale price
        if($finalAmount < $finalListingPrice) {
            update_post_meta($productId, '_regular_price',$finalProcessedListingPrices);
            update_post_meta($productId, '_sale_price', $finalProcessedPrice);
        }
    }

    private function get_product_data($productAsin) {
        // Create an instance of the amazon class
        $cronAmazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);
        $cronAmazonEcs->optionalParameters(array('MerchantId' => 'All', 'Condition' => 'All'));
        // Retrieve the data of an asin
        $response = $cronAmazonEcs->responseGroup('Large,Variations,OfferFull')->lookup($productAsin);

        return $response->Items->Item;
    }

    private function update_custom_fields($productId, $data) {
        // Availability
        if (($data->OfferSummary->TotalNew + $data->OfferSummary->TotalUsed + $data->OfferSummary->TotalCollectible + $data->OfferSummary->TotalRefurbished) > 0) {
            $Availability = true;
        } else {
            if (!isset($data->VariationSummary)) {    // unless there are variations, it really is unavailable
                $Availability = false;
            } else {
                $Availability = true;
            }
        }
        
        if($Availability) {
            // meta key '_stock_status' is used by woocommerce
            // If 'outofstock' we can't add products to woocommerce cart
            update_post_meta($productId, '_stock_status', 'instock');
        } else {
            update_post_meta($productId, '_stock_status', 'outofstock');
        }
        
        update_post_meta($productId, '_pros_ItemAttributes', serialize($data->ItemAttributes));
        update_post_meta($productId, '_pros_Offers', serialize($data->Offers));
        update_post_meta($productId, '_pros_OfferSummary', serialize($data->OfferSummary));
        update_post_meta($productId, '_pros_SimilarProducts', serialize($data->SimilarProducts));
        update_post_meta($productId, '_pros_Accessories', serialize($data->Accessories));

        update_post_meta($productId, '_pros_ASIN', $data->ASIN);
        update_post_meta($productId, '_pros_ParentASIN', $data->ParentASIN);
        update_post_meta($productId, '_pros_DetailPageURL', $data->DetailPageURL);

        update_post_meta($productId, '_pros_CustomerReviews', serialize($data->CustomerReviews));
        update_post_meta($productId, '_pros_EditorialReviews', serialize($data->EditorialReviews));

        update_post_meta($productId, '_pros_VariationSummary', serialize($data->VariationSummary));
        update_post_meta($productId, '_pros_VariationDimensions', serialize($data->Variations->VariationDimensions));
    }

    private function update_woocommerce_attributes($productId, $productData) {
        global $woocommerce;

        $_product_attributes = array();
        $position = 0;
        // Go through each of the attributes
        foreach ($productData as $k => $v) {
            // Dont process if value is an object
            if (!is_object($v)) {
                // TODO: what if array?
                $attribute_name = $woocommerce->attribute_taxonomy_name(strtolower($k));
                $_product_attributes[$attribute_name] = array(
                    'name' => $attribute_name,
                    'value' => '',
                    'position' => $position++,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
            }
            $this->add_attribute_value($productId, $k, $v);
        }
        // Update the product attributes meta
        update_post_meta($productId, '_product_attributes', serialize($_product_attributes));
    }

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

    /*
    private function add_attribute_value($post_id, $key, $value) {
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

        if (is_array($value)) {
            $values = $value;
        } else {
            $values = array($value);
        }

        // check taxonomy
        if (!taxonomy_exists($taxonomy)) {
            // add attribute value
            foreach ($values as $attribute_value) {
                // add term
                $name = stripslashes($attribute_value);
                $slug = sanitize_title($name);
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
        } else {
            // get already existing attribute values
            $attribute_values = array();
            $terms = get_terms($taxonomy);
            foreach ($terms as $term) {
                $attribute_values[] = $term->name;
            }

            // Check if $attribute_value is not empty
            if (!empty($attribute_value)) {
                foreach ($values as $attribute_value) {
                    if (!in_array($attribute_value, $attribute_values)) {
                        // add new attribute value
                        wp_insert_term($attribute_value, $taxonomy);
                    }
                }
            }
        }

        // Add terms
        if (is_array($value)) {
            foreach ($value as $dm_v) {
                wp_insert_term($dm_v, $taxonomy);
            }
        } else {
            wp_insert_term($value, $taxonomy);
        }
    }
    */


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

    public function dm_pros_activate_cron() {
        if (!wp_next_scheduled('dm_pros_check_cron')) {
            wp_schedule_event(time(), 'dmBiMinute', 'dm_pros_check_cron');
        }
    }

}
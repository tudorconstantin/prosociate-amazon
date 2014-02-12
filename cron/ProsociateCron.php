<?php
class Prosociate_Cron {
    /**
     * API key to prevent spam
     * @var int
     */
    private static $apiKey;

    /**
     * Number of seconds before update. Commonly 1 day (86400)
     */
    const UPDATE_SECONDS = 86400;

    function __construct() {
        add_action('wp_loaded', array($this, 'captureGet'));
    }

    /**
     * Set the api key
     */
    public function getApi() {
        // Check if we dont have the api key yet
        if(self::$apiKey === null || self::$apiKey === '')
            self::$apiKey = get_option('prossociate_settings-dm-cron-api-key', '');
    }

    /**
     * Capture the cron starter
     */
    public function captureGet() {
        // Get api
        $this->getApi();
        // Check if we have the proper initializers and api key
        if(isset($_GET['proscron']) && $_GET['proscron'] == self::$apiKey) {
            $this->startCron();
        }
    }

    /**
     * Start the cron process
     */
    private function startCron() {
        $products = $this->getProducts();

        // Prevent further actions if no products need to be updated
        if(empty($products))
            return;

        // Get if we need to delete
        $deleteUnavailable = get_option('prossociate_settings-dm-pros-prod-avail', false);

        // Process each product
        foreach($products as $product) {
            // Get asin of product
            $asin = get_post_meta($product->ID, '_pros_ASIN', true);

            // Get data of the asin
            $data = $this->getData($asin);

            // Check if we got the data
            if($data === false)
                break;

            // Check for the availability of the product
            $available = $this->checkAvailability($data);

            // If available update the product
            if($available) {
                // Update product meta
                $this->updateProduct($product->ID, $data);
                // Make it have "stock"
                update_post_meta($product->ID, '_stock_status', 'instock');
            } else {
                // Check if we need to delete
                if($deleteUnavailable == 'remove') {
                    // Delete the main product
                    // Delete the images
                    $this->deleteImages($product->ID);
                    // Delete the product
                    $this->deleteProduct($product->ID);
                } else {
                    // Update product meta
                    $this->updateProduct($product->ID, $data);
                    // Make out of stock
                    update_post_meta($product->ID, '_stock_status', 'outofstock');
                }

            }

            // Update the update time
            update_post_meta($product->ID, '_pros_last_update_time', time());
        }
    }

    /**
     * Get products that needs to be updated
     * @return array
     */
    private function getProducts() {
        // Get products that are not updated within 24 hours
        $products = new WP_Query(
            array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 100, // Assuming that we can only process less than 100 products per request
                'meta_query' => array(
                    array(
                        'key' => '_pros_last_update_time',
                        'value' => time() - self::UPDATE_SECONDS,
                        'compare' => '<='
                    )
                )
            )
        );

        // Make sure we reset
        wp_reset_postdata();

        return $products->posts;
    }

    /**
     * Get product data from amazon
     * @param $asin
     * @return object|false
     */
    private function getData($asin) {
        // Instantiate the lib
        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        // Include the merchant ID
        $amazonEcs->optionalParameters(array('MerchantId' => 'All'));

        // Try to get the data
        try {
            $response = $amazonEcs->responseGroup('Large,Variations,OfferFull,VariationOffers')->lookup($asin);
            $data = $response->Items->Item;
        } catch(Exception $exception) {
            $data = false;
        }

        return $data;
    }

    /**
     * Update the prices of the product
     * @param int $productId
     * @param object $data
     */
    private function updatePrices($productId, $data) {
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
                    // Check if sale price is given
                    if(isset($offer->OfferListing->SalePrice)) {
                        $finalSalePrice = $this->reformat_prices($this->remove_currency_symbols($offer->OfferListing->SalePrice->FormattedPrice));
                        $finalSaleAmount = $offer->OfferListing->SalePrice->Amount;
                    } else {
                        $finalSaleAmount = 0;
                    }
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
                // Check if sale price is given
                if(isset($data->Offers->Offer->OfferListing->SalePrice)) {
                    $finalSalePrice = $this->reformat_prices($this->remove_currency_symbols($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice));
                    $finalSaleAmount = $data->Offers->Offer->OfferListing->SalePrice->Amount;
                } else {
                    $finalSaleAmount = 0;
                }
            }
        }

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Add the offer id and price
        update_post_meta($productId, '_dmpros_offerid', $finalOffer);
        update_post_meta($productId, '_price', $finalProcessedPrice);
        update_post_meta($productId, '_regular_price', $finalProcessedPrice);

        // Handle prices with Too low to display
        if($finalPrice === 'Too low to display') {
            update_post_meta($productId, '_price', '0');
            update_post_meta($productId, '_regular_price', '0');
            update_post_meta($productId, '_filterTooLowPrice', 'true');
        } elseif($finalSaleAmount > 0) {  // Handle the regular / sale price
            update_post_meta($productId, '_regular_price',$finalProcessedPrice);
            update_post_meta($productId, '_sale_price', $finalSalePrice);
            update_post_meta($productId, '_price', $finalSalePrice);
        }
    }

    /**
     * Delete all the price metas
     * @param int $productId
     */
    private function deletePrices($productId) {
        delete_post_meta($productId, '_regular_price');
        delete_post_meta($productId, '_sale_price');
        delete_post_meta($productId, '_price');
    }

    /**
     * Reformat the price
     * @param $price
     * @return mixed
     */
    private function reformat_prices($price) {
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

    /**
     * @param $price
     * @return string
     */
    private function reformat_price_de($price) {
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

    /**
     * Remove the currency symbol
     * @param $x
     * @return mixed
     */
    private function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
    }

    /**
     * Update the fields of the products
     * @param int $productId
     * @param object $data
     */
    private function updateCustomFields($productId, $data) {
        if(isset($data->ItemAttributes)) {
            update_post_meta($productId, '_pros_ItemAttributes', serialize($data->ItemAttributes));
        }
        if(isset($data->Offers)) {
            update_post_meta($productId, '_pros_Offers', serialize($data->Offers));
        }
        if(isset($data->OfferSummary)) {
            update_post_meta($productId, '_pros_OfferSummary', serialize($data->OfferSummary));
        }
        if(isset($data->SimilarProducts)) {
            update_post_meta($productId, '_pros_SimilarProducts', serialize($data->SimilarProducts));
        }
        if(isset($data->Accessories)) {
            update_post_meta($productId, '_pros_Accessories', serialize($data->Accessories));
        }
        if(isset($data->ASIN)) {
            update_post_meta($productId, '_pros_ASIN', $data->ASIN);
            // Add sku
            update_post_meta($productId, '_sku', $data->ASIN);
        }

        if(isset($data->ParentASIN)) {
            update_post_meta($productId, '_pros_ParentASIN', $data->ParentASIN);
        }
        if(isset($data->DetailPageURL)) {
            update_post_meta($productId, '_pros_DetailPageURL', $data->DetailPageURL);
        }
        if(isset($data->CustomerReviews)) {
            update_post_meta($productId, '_pros_CustomerReviews', serialize($data->CustomerReviews));
        }
        if(isset($data->EditorialReviews)) {
            update_post_meta($productId, '_pros_EditorialReviews', serialize($data->EditorialReviews));
        }
        if(isset($data->VariationSummary)) {
            update_post_meta($productId, '_pros_VariationSummary', serialize($data->VariationSummary));
        }
        if(isset($data->Variations->VariationDimensions)) {
            update_post_meta($productId, '_pros_VariationDimensions', serialize($data->Variations->VariationDimensions));
        }

        if(isset($data->Variations->TotalVariations)) {
            if ($data->Variations->TotalVariations > 0) {
                if (count($data->Variations->Item) == 1) {
                    update_post_meta($productId, '_pros_FirstVariation', serialize($data->Variations->Item));
                } else {
                    update_post_meta($productId, '_pros_FirstVariation', serialize($data->Variations->Item[0]));
                }
            }
        }
    }

    /**
     * Process all the meta fields of product
     * @param int $productId
     * @param object $data
     */
    private function updateProduct($productId, $data) {
        // Delete current prices
        $this->deletePrices($productId);
        // Update the price
        $this->updatePrices($productId, $data);
        // Update the custom fields
        $this->updateCustomFields($productId, $data);
    }

    /**
     * Delete associated images for a product
     * @param int $productId
     */
    private function deleteImages($productId) {
        // Get main images
        $args = array(
            'post_parent' => (int)$productId,
            'post_type' => 'attachment',
            'post_status' => 'any'
        );
        $images = get_children($args);

        // Delete images
        foreach($images as $image) {
            wp_delete_attachment($image->ID, true);
        }
    }

    /**
     * Delete a product
     * @param int $productId
     */
    private function deleteProduct($productId) {
        // Delete
        wp_delete_post($productId, true);
    }

    /**
     * Check if the product is available
     * @param object $data
     * @return bool
     */
    private function checkAvailability($data) {
        $available = false;

        // Check for availability
        if(isset($data->Offers->TotalOffers) && $data->Offers->TotalOffers > 0) {
            $available = true;
        }

        return $available;
    }
}
new Prosociate_Cron();
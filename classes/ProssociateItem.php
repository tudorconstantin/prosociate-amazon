<?php
/*

error_reporting(-1);
set_error_handler(function($code, $string, $file, $line) {
            throw new ErrorException($string, null, $code, $file, $line);
        });

register_shutdown_function(function() {
            $error = error_get_last();
            if (null !== $error) {
                echo 'Caught at shutdown';
            }
        });
 * 
 */

class ProssociateItem {

    var $ASIN, $Title, $DetailPageURL, $CustomerReviews;
    var $Images;
    var $data;
    var $isValid = true;
    var $code;

    public function __construct($asin, $merchant = 'All') {

        $this->ASIN = $asin;

        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        $amazonEcs->optionalParameters(array('MerchantId' => $merchant, 'Condition' => 'All'));
        
        if($asin) {
            try {
                $response = $amazonEcs->responseGroup('Large,Variations,OfferFull')->lookup($asin);
            } catch(Exception $exception) {
                //echo 'Caught in try/catch';
                $this->isValid = false;
                $this->code = 100; // Means we have memory issue
            }
        }



        /*
          Variations
          VariationImages
          VariationMatrix
          VariationOffers
          VariationSummary
         */

/*
        if ($response->Items->Request->IsValid != 'True') {
            pre_print_r($response);
            throw new Exception("Invalid Request");
        }
 * 
 */




        if($this->isValid) {

        $this->data = $response->Items->Item;

        $this->Title = $response->Items->Item->ItemAttributes->Title;
        }

        // set attributes
//		$this->DetailPageURL = $response->Items->Item->DetailPageURL;
//		$this->Title = $response->Items->Item->ItemAttributes->Title;
//		die();
    }

    public function dump() {
        pre_print_r($this->data);
    }

}

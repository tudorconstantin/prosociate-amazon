<?php

include('aws_signed_request.php');

// cause a parse error i think this file isnt used

define('AWS_API_KEY', 'AKIAIZFHYWSMERWXFQLA');
define('AWS_API_SECRET_KEY', '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');
define('AWS_ASSOCIATE_TAG', 'soflyy-20');


$public_key = AWS_API_KEY;
$private_key = AWS_API_SECRET_KEY;
$associate_tag = AWS_ASSOCIATE_TAG;

// generate signed URL
$request = aws_signed_request('com', array(
        'Operation' => 'CartCreate',
        'Item.1.OfferListingId' => 'U7Ocb2lnUs0GRRe%2FC%2FP776NdXQES8z5t9GXTmelL1Oegrg6a66YYk4f12VqbOKdQ21oKlKQLnN2JBhnu5oIyujBRUDQdgzeN02AtyWSkYMpXbvwSi95oH3eV1UORBRMYuCjgY14c9qkdMWhBxMXgbQ%3D%3D',
        'Item.1.Quantity' => '2'
        ), $public_key, $private_key, $associate_tag);

// do request (you could also use curl etc.)
$response = file_get_contents($request);


if ($response === FALSE) {
    echo "Request failed.\n";
} else {
    // parse XML
    $pxml = simplexml_load_string($response);
    if ($pxml === FALSE) {
        echo "Response could not be parsed.\n";
    } else {
		echo "<pre>";
        print_r($pxml);
		echo "</pre>";
    }
}

?>
<?php
/**
 * Amazon ECS Class
 * http://www.amazon.com
 * =====================
 *
 * This class fetchs productinformation via the Product Advertising API by Amazon (formerly ECS).
 * It supports three basic operations: ItemSearch, ItemLookup and BrowseNodeLookup.
 * These operations could be expanded with extra prarmeters to specialize the query.
 *
 * Requirement is the PHP extension SOAP.
 *
 * @package      AmazonECS
 * @license      http://www.gnu.org/licenses/gpl.txt GPL
 * @version      1.3.4-DEV
 * @author       Exeu <exeu65@googlemail.com>
 * @contributor  Julien Chaumond <chaumond@gmail.com>
 * @link         http://github.com/Exeu/Amazon-ECS-PHP-Library/wiki Wiki
 * @link         http://github.com/Exeu/Amazon-ECS-PHP-Library Source
 */
class AmazonECS
{
  const RETURN_TYPE_ARRAY  = 1;
  const RETURN_TYPE_OBJECT = 2;

  /**
   * Baseconfigurationstorage
   *
   * @var array
   */
  private $requestConfig = array(
    'requestDelay' => false
  );

  /**
   * Responseconfigurationstorage
   *
   * @var array
   */
  private $responseConfig = array(
    'returnType'          => self::RETURN_TYPE_OBJECT,
    'responseGroup'       => 'Small',
    'optionalParameters'  => array()
  );

  /**
   * All possible locations
   *
   * @var array
   */
  private $possibleLocations = array('de', 'com', 'co.uk', 'ca', 'fr', 'co.jp', 'it', 'cn', 'es', 'in');

  /**
   * The WSDL File
   *
   * @var string
   */
  protected $webserviceWsdl = 'http://webservices.amazon.com/AWSECommerceService/AWSECommerceService.wsdl';

  /**
   * The SOAP Endpoint
   *
   * @var string
   */
  protected $webserviceEndpoint = 'https://webservices.amazon.%%COUNTRY%%/onca/soap?Service=AWSECommerceService';

  /**
   * @param string $accessKey
   * @param string $secretKey
   * @param string $country
   * @param string $associateTag
   */
  public function __construct($accessKey, $secretKey, $country, $associateTag)
  {
    if (empty($accessKey) || empty($secretKey))
    {
      throw new Exception('No Access Key or Secret Key has been set');
    }

    $this->requestConfig['accessKey']     = $accessKey;
    $this->requestConfig['secretKey']     = $secretKey;
    $this->associateTag($associateTag);
    $this->country($country);
  }

  /**
   * execute search
   *
   * @param string $pattern
   *
   * @return array|object return type depends on setting
   *
   * @see returnType()
   */
  public function search($pattern = '*', $nodeId = null)
  {
    if (false === isset($this->requestConfig['category']))
    {
      throw new Exception('No Category given: Please set it up before');
    }

    $browseNode = array();
    if (null !== $nodeId && true === $this->validateNodeId($nodeId))
    {
      $browseNode = array('BrowseNode' => $nodeId);
    }
    
    // yuri - add advanced search options
    $advanced_options = array();
    if (!empty($this->requestConfig['minprice'])) $advanced_options['MinimumPrice'] = $this->requestConfig['minprice'];
    if (!empty($this->requestConfig['maxprice'])) $advanced_options['MaximumPrice'] = $this->requestConfig['maxprice'];
    if (!empty($this->requestConfig['availability'])) $advanced_options['Availability'] = $this->requestConfig['availability'];
    if (!empty($this->requestConfig['merchantid'])) $advanced_options['MerchantId'] = $this->requestConfig['merchantid'];
    if (!empty($this->requestConfig['condition'])) $advanced_options['Condition'] = $this->requestConfig['condition'];
    if (!empty($this->requestConfig['manufacturer'])) $advanced_options['Manufacturer'] = $this->requestConfig['manufacturer'];
    if (!empty($this->requestConfig['brand'])) $advanced_options['Brand'] = $this->requestConfig['brand'];
    if (!empty($this->requestConfig['minpercentageoff'])) $advanced_options['MinPercentageOff'] = $this->requestConfig['minpercentageoff'];
    
    // DM
    $dm = array(
        'Keywords' => $pattern,
        'SearchIndex' => $this->requestConfig['category']
    );
    
    if(isset($this->requestConfig['sortby'])) {
        if(!is_null($this->requestConfig['sortby'])) {
            $dm['Sort'] = $this->requestConfig['sortby'];
        }
    }
    
    $params = $this->buildRequestParams('ItemSearch', array_merge($dm,
      $browseNode,
      $advanced_options
    ));
    
    // yuri - add sortby parameter
    /*
    $params = $this->buildRequestParams('ItemSearch', array_merge(
      array(
        'Keywords' => $pattern,
        'SearchIndex' => $this->requestConfig['category'],
        'Sort' => $this->requestConfig['sortby']
      ),
      $browseNode,
      $advanced_options
    ));
     * 
     */

    return $this->returnData(
      $this->performSoapRequest("ItemSearch", $params)
    );
  }

  /**
   * execute ItemLookup request
   *
   * @param string $asin
   *
   * @return array|object return type depends on setting
   *
   * @see returnType()
   */
  public function lookup($asin)
  {
    $params = $this->buildRequestParams('ItemLookup', array(
      'ItemId' => $asin,
    ));

    return $this->returnData(
      $this->performSoapRequest("ItemLookup", $params)
    );
  }

  /**
   * Implementation of BrowseNodeLookup
   * This allows to fetch information about nodes (children anchestors, etc.)
   *
   * @param integer $nodeId
   */
  public function browseNodeLookup($nodeId)
  {
    $this->validateNodeId($nodeId);

    $params = $this->buildRequestParams('BrowseNodeLookup', array(
      'BrowseNodeId' => $nodeId
    ));

    return $this->returnData(
      $this->performSoapRequest("BrowseNodeLookup", $params)
    );
  }

  /**
   * Implementation of SimilarityLookup
   * This allows to fetch information about product related to the parameter product
   *
   * @param string $asin
   */
  public function similarityLookup($asin)
  {
    $params = $this->buildRequestParams('SimilarityLookup', array(
      'ItemId' => $asin
    ));

    return $this->returnData(
      $this->performSoapRequest("SimilarityLookup", $params)
    );
  }

  /**
   * Builds the request parameters
   *
   * @param string $function
   * @param array  $params
   *
   * @return array
   */
  protected function buildRequestParams($function, array $params)
  {
    $associateTag = array();

    if(false === empty($this->requestConfig['associateTag']))
    {
      $associateTag = array('AssociateTag' => $this->requestConfig['associateTag']);
    }

    return array_merge(
      $associateTag,
      array(
        'AWSAccessKeyId' => $this->requestConfig['accessKey'],
        'Request' => array_merge(
          array('Operation' => $function),
          $params,
          $this->responseConfig['optionalParameters'],
          array('ResponseGroup' => $this->prepareResponseGroup())
    )));
  }

  /**
   * Prepares the responsegroups and returns them as array
   *
   * @return array|prepared responsegroups
   */
  protected function prepareResponseGroup()
  {
    if (false === strstr($this->responseConfig['responseGroup'], ','))
      return $this->responseConfig['responseGroup'];

    return explode(',', $this->responseConfig['responseGroup']);
  }

  /**
   * @param string $function Name of the function which should be called
   * @param array $params Requestparameters 'ParameterName' => 'ParameterValue'
   *
   * @return array The response as an array with stdClass objects
   */
  protected function performSoapRequest($function, $params)
  {
    if (true ===  $this->requestConfig['requestDelay']) {
      sleep(1);
    }
    
    $soapClient = new SoapClient(
      $this->webserviceWsdl,
      array('exceptions' => 1)
    );

    // yuri - caching wsdl
    /*
    $soapClient = new SoapClient(
      $this->webserviceWsdl,
      //array('exceptions' => 1, 'cache_wsdl' => WSDL_CACHE_MEMORY)
      array('exceptions' => 1, 'cache_wsdl' => WSDL_CACHE_NONE)
    );
     * 
     */

    $soapClient->__setLocation(str_replace(
      '%%COUNTRY%%',
      $this->responseConfig['country'],
      $this->webserviceEndpoint
    ));

    $soapClient->__setSoapHeaders($this->buildSoapHeader($function));

    return $soapClient->__soapCall($function, array($params));
  }

  /**
   * Provides some necessary soap headers
   *
   * @param string $function
   *
   * @return array Each element is a concrete SoapHeader object
   */
  protected function buildSoapHeader($function)
  {
    $timeStamp = $this->getTimestamp();
    $signature = $this->buildSignature($function . $timeStamp);

    return array(
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'AWSAccessKeyId',
        $this->requestConfig['accessKey']
      ),
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'Timestamp',
        $timeStamp
      ),
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'Signature',
        $signature
      )
    );
  }

  /**
   * provides current gm date
   *
   * primary needed for the signature
   *
   * @return string
   */
  final protected function getTimestamp()
  {
    return gmdate("Y-m-d\TH:i:s\Z");
  }

  /**
   * provides the signature
   *
   * @return string
   */
  final protected function buildSignature($request)
  {
    return base64_encode(hash_hmac("sha256", $request, $this->requestConfig['secretKey'], true));
  }

  /**
   * Basic validation of the nodeId
   *
   * @param integer $nodeId
   *
   * @return boolean
   */
  final protected function validateNodeId($nodeId)
  {
    if (false === is_numeric($nodeId) || $nodeId <= 0)
    {
      throw new InvalidArgumentException(sprintf('Node has to be a positive Integer.'));
    }

    return true;
  }

  /**
   * Returns the response either as Array or Array/Object
   *
   * @param object $object
   *
   * @return mixed
   */
  protected function returnData($object)
  {
    switch ($this->responseConfig['returnType'])
    {
      case self::RETURN_TYPE_OBJECT:
        return $object;
      break;

      case self::RETURN_TYPE_ARRAY:
        return $this->objectToArray($object);
      break;

      default:
        throw new InvalidArgumentException(sprintf(
          "Unknwon return type %s", $this->responseConfig['returnType']
        ));
      break;
    }
  }

  /**
   * Transforms the responseobject to an array
   *
   * @param object $object
   *
   * @return array An arrayrepresentation of the given object
   */
  protected function objectToArray($object)
  {
    $out = array();
    foreach ($object as $key => $value)
    {
      switch (true)
      {
        case is_object($value):
          $out[$key] = $this->objectToArray($value);
        break;

        case is_array($value):
          $out[$key] = $this->objectToArray($value);
        break;

        default:
          $out[$key] = $value;
        break;
      }
    }

    return $out;
  }

  /**
   * set or get optional parameters
   *
   * if the argument params is null it will reutrn the current parameters,
   * otherwise it will set the params and return itself.
   *
   * @param array $params the optional parameters
   *
   * @return array|AmazonECS depends on params argument
   */
  public function optionalParameters($params = null)
  {
    if (null === $params)
    {
      return $this->responseConfig['optionalParameters'];
    }

    if (false === is_array($params))
    {
      throw new InvalidArgumentException(sprintf(
        "%s is no valid parameter: Use an array with Key => Value Pairs", $params
      ));
    }

    $this->responseConfig['optionalParameters'] = $params;

    return $this;
  }

  /**
   * Set or get the country
   *
   * if the country argument is null it will return the current
   * country, otherwise it will set the country and return itself.
   *
   * @param string|null $country
   *
   * @return string|AmazonECS depends on country argument
   */
  public function country($country = null)
  {
    if (null === $country)
    {
      return $this->responseConfig['country'];
    }

    if (false === in_array(strtolower($country), $this->possibleLocations))
    {
      throw new InvalidArgumentException(sprintf(
        "Invalid Country-Code: %s! Possible Country-Codes: %s",
        $country,
        implode(', ', $this->possibleLocations)
      ));
    }

    $this->responseConfig['country'] = strtolower($country);

    return $this;
  }

  /**
   * Setting/Getting the amazon category
   *
   * @param string $category
   *
   * @return string|AmazonECS depends on category argument
   */
  public function category($category = null)
  {
    if (null === $category)
    {
      return isset($this->requestConfig['category']) ? $this->requestConfig['category'] : null;
    }

    $this->requestConfig['category'] = $category;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon sortby - yuri
   *
   * @param string $sortby
   *
   * @return string|AmazonECS depends on sortby argument
   */
  public function sortby($sortby = null)
  {
    if (null === $sortby)
    {
      return isset($this->requestConfig['sortby']) ? $this->requestConfig['sortby'] : null;
    }

    $this->requestConfig['sortby'] = $sortby;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Minimum Price - yuri
   *
   * @param string $minprice
   *
   * @return string|AmazonECS depends on minprice argument
   */
  public function minprice($minprice = null)
  {
    if (null === $minprice)
    {
      return isset($this->requestConfig['minprice']) ? $this->requestConfig['minprice'] : null;
    }

    $this->requestConfig['minprice'] = $minprice;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Maximum Price - yuri
   *
   * @param string $maxprice
   *
   * @return string|AmazonECS depends on maxprice argument
   */
  public function maxprice($maxprice = null)
  {
    if (null === $maxprice)
    {
      return isset($this->requestConfig['maxprice']) ? $this->requestConfig['maxprice'] : null;
    }

    $this->requestConfig['maxprice'] = $maxprice;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Availability - yuri
   *
   * @param string $availability
   *
   * @return string|AmazonECS depends on availability argument
   */
  public function availability($availability = null)
  {
    if (null === $availability)
    {
      return isset($this->requestConfig['availability']) ? $this->requestConfig['availability'] : null;
    }

    $this->requestConfig['availability'] = $availability;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Condition - yuri
   *
   * @param string $condition
   *
   * @return string|AmazonECS depends on condition argument
   */
  public function condition($condition = null)
  {
    if (null === $condition)
    {
      return isset($this->requestConfig['condition']) ? $this->requestConfig['condition'] : null;
    }

    $this->requestConfig['condition'] = $condition;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Manufacturer - yuri
   *
   * @param string $manufacturer
   *
   * @return string|AmazonECS depends on manufacturer argument
   */
  public function manufacturer($manufacturer = null)
  {
    if (null === $manufacturer)
    {
      return isset($this->requestConfig['manufacturer']) ? $this->requestConfig['manufacturer'] : null;
    }

    $this->requestConfig['manufacturer'] = $manufacturer;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon Brand - yuri
   *
   * @param string $brand
   *
   * @return string|AmazonECS depends on brand argument
   */
  public function brand($brand = null)
  {
    if (null === $brand)
    {
      return isset($this->requestConfig['brand']) ? $this->requestConfig['brand'] : null;
    }

    $this->requestConfig['brand'] = $brand;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon MerchantId - yuri
   *
   * @param string $merchantid
   *
   * @return string|AmazonECS depends on merchantid argument
   */
  public function merchantid($merchantid = null)
  {
    if (null === $merchantid)
    {
      return isset($this->requestConfig['merchantid']) ? $this->requestConfig['merchantid'] : null;
    }

    $this->requestConfig['merchantid'] = $merchantid;

    return $this;
  }
  
  /**
   * Setting/Getting the amazon MinPercentageOff - yuri
   *
   * @param string $minpercentageoff
   *
   * @return string|AmazonECS depends on minpercentageoff argument
   */
  public function minpercentageoff($minpercentageoff = null)
  {
    if (null === $minpercentageoff)
    {
      return isset($this->requestConfig['minpercentageoff']) ? $this->requestConfig['minpercentageoff'] : null;
    }

    $this->requestConfig['minpercentageoff'] = $minpercentageoff;

    return $this;
  }

  /**
   * Setting/Getting the responsegroup
   *
   * @param string $responseGroup Comma separated groups
   *
   * @return string|AmazonECS depends on responseGroup argument
   */
  public function responseGroup($responseGroup = null)
  {
    if (null === $responseGroup)
    {
      return $this->responseConfig['responseGroup'];
    }

    $this->responseConfig['responseGroup'] = $responseGroup;

    return $this;
  }

  /**
   * Setting/Getting the returntype
   * It can be an object or an array
   *
   * @param integer $type Use the constants RETURN_TYPE_ARRAY or RETURN_TYPE_OBJECT
   *
   * @return integer|AmazonECS depends on type argument
   */
  public function returnType($type = null)
  {
    if (null === $type)
    {
      return $this->responseConfig['returnType'];
    }

    $this->responseConfig['returnType'] = $type;

    return $this;
  }

  /**
   * Setter/Getter of the AssociateTag.
   * This could be used for late bindings of this attribute
   *
   * @param string $associateTag
   *
   * @return string|AmazonECS depends on associateTag argument
   */
  public function associateTag($associateTag = null)
  {
    if (null === $associateTag)
    {
      return $this->requestConfig['associateTag'];
    }

    $this->requestConfig['associateTag'] = $associateTag;

    return $this;
  }

  /**
   * @deprecated use returnType() instead
   */
  public function setReturnType($type)
  {
    return $this->returnType($type);
  }

  /**
   * Setting the resultpage to a specified value.
   * Allows to browse resultsets which have more than one page.
   *
   * @param integer $page
   *
   * @return AmazonECS
   */
  public function page($page)
  {
    if (false === is_numeric($page) || $page <= 0)
    {
      throw new InvalidArgumentException(sprintf(
        '%s is an invalid page value. It has to be numeric and positive',
        $page
      ));
    }

    $this->responseConfig['optionalParameters'] = array_merge(
      $this->responseConfig['optionalParameters'],
      array("ItemPage" => $page)
    );

    return $this;
  }

  /**
   * Enables or disables the request delay.
   * If it is enabled (true) every request is delayed one second to get rid of the api request limit.
   *
   * Reasons for this you can read on this site:
   * https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html
   *
   * By default the requestdelay is disabled
   *
   * @param boolean $enable true = enabled, false = disabled
   *
   * @return boolean|AmazonECS depends on enable argument
   */
  public function requestDelay($enable = null)
  {
    if (false === is_null($enable) && true === is_bool($enable))
    {
      $this->requestConfig['requestDelay'] = $enable;

      return $this;
    }

    return $this->requestConfig['requestDelay'];
  }










  /** modifications for cartcreate

  */

    public function cartCreate($asin)
    {
      $params = $this->buildRequestParams2('CartCreate', array(
        'Item%2E1%2EOfferListingId' => 'U7Ocb2lnUs0GRRe%2FC%2FP776NdXQES8z5t9GXTmelL1Oegrg6a66YYk4f12VqbOKdQ21oKlKQLnN2JBhnu5oIyujBRUDQdgzeN02AtyWSkYMpXbvwSi95oH3eV1UORBRMYuCjgY14c9qkdMWhBxMXgbQ%3D%3D',
        'Item%2E1%2EQuantity' => '2',
        'Item' => "WHAT THE FUCK"
      ));


      $soapresult = $this->performSoapRequest2("CartCreate", $params);

      echo "soap result: <br />";
      pre_print_r($soapresult);


      return $this->returnData(
        $soapresult
      );
    }



  /**
   * Builds the request parameters, modified to not have responseGroup
   *
   * @param string $function
   * @param array  $params
   *
   * @return array
   */
  protected function buildRequestParams2($function, array $params)
  {
    $associateTag = array();

    if(false === empty($this->requestConfig['associateTag']))
    {
      $associateTag = array('AssociateTag' => $this->requestConfig['associateTag']);
    }

    $merged_array = array_merge(
      $associateTag,
      array(
        'AWSAccessKeyId' => $this->requestConfig['accessKey'],
        'Request' => array_merge(
          array('Operation' => $function),
          $params
    )));

    return $merged_array;

  }


  /**
   * @param string $function Name of the function which should be called
   * @param array $params Requestparameters 'ParameterName' => 'ParameterValue'
   *
   * @return array The response as an array with stdClass objects
   */
  protected function performSoapRequest2($function, $params)
  {
    if (true ===  $this->requestConfig['requestDelay']) {
      sleep(1);
    }

    $soapClient = new SoapClient(
      $this->webserviceWsdl,
      array('exceptions' => 1)
    );

    $soapClient->__setLocation(str_replace(
      '%%COUNTRY%%',
      $this->responseConfig['country'],
      $this->webserviceEndpoint
    ));

    $soapClient->__setSoapHeaders($this->buildSoapHeader($function));


    echo "SOAP CLIENT: <br />";
    pre_print_r($soapClient);
    echo "<br />------------------";

    echo "Function: <br />";
    echo $function;
    echo "<br /><br />";


    $returned_result = $soapClient->__soapCall($function, array($params));

    echo "RESULT OF THE SOAP CALL<br />";
    pre_print_r($returned_result);
    echo "-----------";

    return $returned_result;

  }



}
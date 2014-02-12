<?php
// returns ASINs, Titles, and some other product data for a search query

class ProssociateSearch {

    // yuri - add sortby, browsenode parameter
    var $keywords, $searchindex, $browsenode, $sortby;

    var $pure_results, $results, $totalpages, $totalresults, $page;
    
    var $response;

    // yuri - add sortby, browsenode parameter
    public function __construct($keywords, $searchindex, $browsenode = null, $sortby = null, $page = 1, $dmCategory = null) {
        if($browsenode == 1) {
            $browsenode = null;
        }
        $this->keywords = $keywords;
        $this->searchindex = $searchindex;
        $this->browsenode = $browsenode;
        $this->sortby = $sortby;
        $this->page = $page;

        //if(!is_null($dmCategory)) {
            //$this->searchindex = $dmCategory;
        //}

        add_action('wp_ajax_prossociate_search_node', array($this, 'ajax_search_node'));
        // yuri - return sort values
        add_action('wp_ajax_prossociate_sort_values', array($this, 'ajax_sort_values'));

        add_action('wp_ajax_prossociate_manual_browsenode', array($this, 'ajax_manual_browsenode'));
    }

    /**
     * Manually specify browsenodes
     */
    public function ajax_manual_browsenode() {
        // Make sure we only have valid node id
        if(!is_numeric($_REQUEST['node'])) {
            echo 'Please enter valid node';
            die();
        }

        if(AWS_COUNTRY == 'com') {
            $this->manualBrowseNodeByMain($_REQUEST['node']);
        } else {
            $this->manualBrowseNodeByAmazon($_REQUEST['node']);
        }

        die();
    }

    /**
     * Get searchindex of specific browsenode id from amazon
     * @param $nodeId
     */
    private function manualBrowseNodeByAmazon($nodeId) {
        // As of now amazon's browsenodelookup doesn't return the needed search index
        /*
        $amazon = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);
        // Set the response group to browsenodeinfo
        $amazon->responseGroup('BrowseNodeInfo');
        */

        // We can only use 'All' for now
        // Todo amazon.in doesn't support All search index
        $searchIndex = 'All';

        echo $searchIndex;
    }

    /**
     * Get searchindex of specific browsenode id from prosociate.com
     * @param $nodeId
     */
    private function manualBrowseNodeByMain($nodeId) {
        $requestUrl = 'http://prosociate.com/?dmpros=1';
        $data = array(
            'dmpros' => '1',
            'node' => $nodeId
        );
        $url = add_query_arg($data, $requestUrl);
        $request = wp_remote_get($url);
        // Check if error
        if(!is_wp_error($request)) {
            $response = wp_remote_retrieve_body($request);
            $unserializedRespons = unserialize($response);

            // if no searchindex retrieved
            if(empty($unserializedRespons))
                $this->manualBrowseNodeByAmazon($nodeId);
            else
                echo $unserializedRespons;

        } else {
            // If error use the manualBrowsenode by amazon
            $this->manualBrowseNodeByAmazon($nodeId);
        }
    }

    public function execute($responsegroup = 'Small,OfferSummary,Images,Variations,VariationOffers,Offers,OfferFull', $isAsinLookUp = false) {

        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        $amazonEcs->category($this->searchindex);
        $amazonEcs->responseGroup($responsegroup);
        //$amazonEcs->optionalParameters(array('MerchantId' => 'All', 'Condition' => 'All'));
        // yuri - add sortby parameter
        if (!empty($this->sortby)) {
            $amazonEcs->sortby($this->sortby);
        }
        if ($this->page) {
            $amazonEcs->page($this->page);
        }

        //$response = $amazonEcs->search($this->keywords, $this->browsenode);
        // DM - check if this is a better approach
        //$response = $amazonEcs->category( $this->searchindex )->responseGroup( $responsegroup )->search( $this->keywords );
        $amazonEcs->category($this->searchindex);
        $amazonEcs->responseGroup($responsegroup);

        $amazonEcs->merchantid('Amazon');


            if (!empty($this->keywords)) {
                $response = $amazonEcs->search($this->keywords, $this->browsenode);
            } else {
                $response = $amazonEcs->search('*', $this->browsenode);
            }

        if($this->searchindex !== '') { // To prevent unnecessary exception
            if ($response != '') {
                if ($response->Items->Request->IsValid != 'True') {
                    print_r($response);
                    throw new Exception('Invalid Request');
                }
            }
        }
        
        $this->response = $response;

        // yuri - null exception
        $results = array();

        if(isset($response->Items->Item)) {
            if (count($response->Items->Item) == 1) {

                $item = $response->Items->Item;

                $ASIN = $item->ASIN;
                $DetailPageURL = $item->DetailPageURL;
                $Title = $item->ItemAttributes->Title;

                $results[] = array("ASIN" => $ASIN, "Title" => $Title);
                $items[] = $item;
            } else {

                if (isset($response->Items->Item)) {

                    foreach ($response->Items->Item as $item) {
                        /*
                        // For DVD
                        // Check if there are multiple offers
                        if(is_array($item->Offers->Offer)) {

                        } else {
                            // Check if Merchant exist
                            if(isset($item->Offers->Offer->Merchant)) {
                                // Check if string
                                if(is_string($item->Offers->Offer->Merchant->Name)) {
                                    // Now check if Name of Merchant is "Amazon Video On Demand"
                                    // I've noticed that all dvd products that can't be added on cart has Merchant name of Amazon Video On Demand
                                    // So we should not show those products to prevent users to add them
                                    //if($item->Offers->Offer->Merchant->Name === 'Amazon Video On Demand')
                                    //    continue;
                                }
                            } // End if Merchant
                        } // End if array
                        */

                        $ASIN = $item->ASIN;
                        $DetailPageURL = $item->DetailPageURL;
                        $Title = $item->ItemAttributes->Title;


                        $results[] = array("ASIN" => $ASIN, "Title" => $Title);
                        $items[] = $item;
                    }
                }
            }
            $this->results = $results;
            $this->results_pure = $items;
        }



        // Total pages and results logic for asin look up
        if($isAsinLookUp) {
            $this->totalpages = 1; // Because we have 10 asin lookup limit
            $this->totalresults = count($this->results_pure); // Because no total results are given
        } else {
            $this->totalpages = isset($response->Items->TotalPages) ? $response->Items->TotalPages : 0;
            $this->totalresults = isset($response->Items->TotalResults) ? $response->Items->TotalResults: 0;
        }

        // TODO
        //pre_print_r( $response );

        return $results;
    }

    // yuri - load sort values for selected search index
    public function get_sortvalue_array($searchindex_sel) {

        $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/sortvalues-' . AWS_COUNTRY . '.csv', 'r');

        if (!$handle) {
            throw new Exception('SortValue data unreadable or inaccessible.');
        }

        $sortvalues = array();

        $searchindex_cur = 'All';
        while (($data = fgetcsv($handle, 0, "\t")) !== false) {
            $count = count($data);
            if ($count == 1) {
                if (strpos($data[0], 'SearchIndex:') !== false) {
                    $searchindex_line = explode(':', $data[0]);
                    $searchindex = trim($searchindex_line[1]);
                    if (!empty($searchindex) && $searchindex != $searchindex_cur) {
                        $searchindex_cur = $searchindex;
                    }
                } else {
                    continue;
                }
            } else if ($count > 1) {
                if ($data[0] == 'Value') {
                    continue;
                } else {
                    $sortvalues[$searchindex_cur][] = array('val' => $data[0], 'txt' => $data[1]);
                }
            } else {
                continue;
            }
        }

        fclose($handle);

        return $sortvalues[$searchindex_sel];
    }

    public function get_browsenode_array() {
        // Make the process different
        if(AWS_COUNTRY === 'com') {
            $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/browsenodes-com.csv', 'r');
        } else {
            $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/browsenodes.csv', 'r');
        }

        if (!$handle) {
            throw new Exception('BrowseNode data unreadable or inaccessible.');
        }

        $firstrow = fgetcsv($handle);

        // Make the process different
        if(AWS_COUNTRY === 'com') {
            $browsenodes = array();
            while (($data = fgetcsv($handle)) !== false) {
                $browsenodes[] = array(
                    'name' => $data[0],
                    'nodeId' => $data[1],
                    'searchIndex' => $data[2]
                );
            }
        } else {
            $key = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $count = count($data);

                for ($i = 0; $i < $count; $i++) {
                    // yuri - convert county initial to the AES code
                    $nn = ProssociateSearch::get_country_code_from_initial($firstrow[$i]);
                    $browsenodes[$data[0]][$nn] = $data[$i];
                }

                $key++;
            }
        }

        fclose($handle);

        return $browsenodes;
    }

    public function get_country_code_from_initial($initial) {

        switch ($initial) {
            case 'IN':
                return 'in';
            case 'CA':
                return 'ca';
            case 'CN':
                return 'cn';
            case 'DE':
                return 'de';
            case 'ES':
                return 'es';
            case 'FR':
                return 'fr';
            case 'IT':
                return 'it';
            case 'JP':
                return 'co.jp';
            case 'UK':
                return 'co.uk';
            case 'US':
                return 'com';
            default:
                return 'com';
        }
    }

    public function makePrice($price) {
        if(AWS_COUNTRY == 'de' || AWS_COUNTRY == 'fr' || AWS_COUNTRY == 'es' || AWS_COUNTRY == 'it') {
            $cleanPrice = str_replace(',','', $price);
        } else {
            $cleanPrice = str_replace('.','', $price);
        }
        // Clean price for eng

        if(!is_numeric($cleanPrice))
            return '';

        return $cleanPrice;
    }

    /**
     * Get browsnode data on amazon
     * @param $nodeid
     * @param $nodes
     * @param $root
     * @throws Exception
     */
    private function browseNodeByAmazon($nodeid, $nodes, $root) {
        // Check if on top level nodes
        if($nodeid == '-2000') {
            $nodeid = '';
        }

        if (!$nodeid) { ?>
            <ul>
            <?php
                $browsenodes = ProssociateSearch::get_browsenode_array();
                if(AWS_COUNTRY === 'com') {
                    // For united states
                    foreach($browsenodes as $node) {
                        echo '
                                <li class="jstree-closed" id="' . $node['nodeId'] . '" nodes="" root="' . $node['searchIndex'] . '">
                                    <a href="javascript:prossociate_select_browsenodes(\'' . $node['nodeId'] . '\', \'' . $node['name'] . '\', \'' . $node['searchIndex'] . '\');">' . $node['name'] . '</a>
                                </li>';
                    }
                } else {
                    foreach ($browsenodes as $nodename => $nodevalues) {
                        // yuri - load selected country's data
                        if ($nodevalues[AWS_COUNTRY]) {
                            // yuri - set browse node value into serach index box
                            echo '
                                <li class="jstree-closed" id="' . $nodevalues[AWS_COUNTRY] . '" nodes="" root="' . $nodename . '">
                                    <a href="javascript:prossociate_select_browsenodes(\'' . $nodevalues[AWS_COUNTRY] . '\', \'' . $nodename . '\', \'' . $nodename . '\');">' . $nodename . '</a>
                                </li>';
                        }
                    }
                }
            ?>
            </ul>
        <?php } else {
            $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

            // yuri - incorrect conversion for big numbers
            //$nodeid = intval($nodeid);

            $amazonEcs->responseGroup('BrowseNodeInfo');
            $response = $amazonEcs->browseNodeLookup($nodeid);

            if ($response->BrowseNodes->Request->IsValid != 'True') {
                print_r($response);
                throw new Exception("Invalid Request");
            }

            if($nodeid == 1 ) {
                die();
            }

            // yuri - check for invalid id exception
            if (!isset($response->BrowseNodes->Request->Errors)) {

                // Check if there are child node
                if (isset($response->BrowseNodes->BrowseNode->Children)) {

                    foreach ($response->BrowseNodes->BrowseNode->Children->BrowseNode as $browsenode) {

                        if ($browsenode->BrowseNodeId) {

                            //$amazonEcs->responseGroup('BrowseNodeInfo');
                            //$response2 = $amazonEcs->browseNodeLookup($browsenode->BrowseNodeId);
                            // yuri - track node tree path
                            if (empty($nodes)) {
                                $node_ids = array();
                            } else {
                                $node_ids = split(',', $nodes);
                            }
                            $node_ids[] = $nodeid;
                            $node_path = implode(',', $node_ids);

                            /* if (!$response2->BrowseNodes->BrowseNode->Children) {
                              // yuri - set browse node value into serach index box
                              echo '
                              <li class="jstree-leaf" id="' . $browsenode->BrowseNodeId . '" nodes="' . $node_path . '" root="' . $root . '">
                              <a href="javascript:prossociate_select_browsenodes(' . $browsenode->BrowseNodeId . ', \'' . $browsenode->Name . '\', \'' . $root . '\');">' . $browsenode->Name . '</a>
                              </li>';
                              } else { */
                            // yuri - set browse node value into serach index box
                            echo '
                                                        <li class="jstree-closed" id="' . $browsenode->BrowseNodeId . '" nodes="' . $node_path . '" root="' . $root . '">
                                    <a href="javascript:prossociate_select_browsenodes(' . $browsenode->BrowseNodeId . ', \'' . addslashes($browsenode->Name) . '\', \'' . $root . '\');">' . $browsenode->Name . '</a>
                                                        </li>';
                            //}
                        }
                    }
                } else {
                    echo '<li class="jstree-leaf dm-no-child">No child categories</li>';
                }
            } else {
                var_dump($response);
            }
        }
    }

    private function manualSearchNode($nodeId, $rootNodes, $root) {
        // Checker
        $useMainSite = false;

        // Only when on com
        if(AWS_COUNTRY == 'com') {
            // Try to get the nodes data from main site
            $response = wp_remote_get('http://prosociate.com/?dmprosi=1&node=' . $nodeId); // TODO replace url
            $responseBody = wp_remote_retrieve_body($response);

            // If fail to connect to the main
            if(is_wp_error($response) || $response == '' || $responseBody == '') {
                $useMainSite = false;
            } else {
                // if connected to prosociate.com
                $useMainSite = true;
            }
        }

        $this->browseNodeByAmazon($nodeId, $rootNodes, $root);

        die();
    }

    public function ajax_search_node() {

        $nodeid = $_REQUEST['id'];
        $nodes = $_REQUEST['nodes']; // yuri - get node tree path
        $root = $_REQUEST['root']; // yuri - get node tree path


        //if(AWS_COUNTRY == 'com') {
            $this->manualSearchNode($nodeid, $nodes, $root);
            die();
        //}



    }

        // yuri - return  sort values for selected search index
        public function ajax_sort_values() {
            global $proso_sort_order;

            $searchindex_sel = $_REQUEST['searchindex'];

            if (empty($searchindex_sel)) {
                echo '<option value="" selected="selected">Default</option>';
                die();
            }

            $sortvalues = ProssociateSearch::get_sortvalue_array($searchindex_sel);

            echo '<option value="" selected="selected">Default</option>';
            if (count($sortvalues)) {
                foreach ($sortvalues as $sortvalue) {
                    $value = $sortvalue['val'];
                    // Check if value is in the clean sort values array
                    if (array_key_exists($value, $proso_sort_order)) {
                        // Use the clean values
                        $optionLabel = $proso_sort_order[$value];
                    } else {
                        $optionLabel = $value;
                    }
                    $text = substr($sortvalue['txt'], 0, 20);
                    echo '<option value="' . $value . '">' . $optionLabel . '</option>';
                }
            }

            die();
        }

    }
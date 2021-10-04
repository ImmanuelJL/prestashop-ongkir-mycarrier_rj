<?php
/**
 * mycarrier_rj.php
 * File module utama untuk install, uninstall, dan proses penghitungan ongkir via API Raja Ongkir
 *
 * Cek https://soft-gain.com/2019/02/07/module-cek-ongkir-jne-tiki-pos-prestashop/ untuk informasi lebih lanjut.
 * Dukung dengan like dan share :)
 *
 * @author      Immanuel Julianto Lasmana <immanueljl44@gmail.com>
 * @link        https://soft-gain.com/2019/02/07/module-cek-ongkir-jne-tiki-pos-prestashop/
 * @copyright   2017 Immanuel Julianto Lasmana et al.
 * @license     GPL-3.0 (https://opensource.org/licenses/GPL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class mycarrier_rj extends CarrierModule
{
    const PREFIX = 'mycarrier_rj_mcj_';

    public $id_carrier;

    protected $_hooks = array(        
        'actionCarrierUpdate',
    );

    protected $_carriers = array( 
        'OKE (Ongkos Kirim Ekonomis)' => 'mcj',//PUT CARRIER NAME
        'REG (Reguler)' => 'mcj2',//PUT CARRIER NAME
        'YES (Yakin Esok Sampai)' => 'mcj3',//PUT CARRIER NAME
        'CTC (JNE City Courier)' => 'mcj31',//PUT CARRIER NAME
        'CTCYES (JNE City Courier)' => 'mcj32',//PUT CARRIER NAME
        //'HDS (Holiday Service)' => 'mcj4',//PUT CARRIER NAME
        'REG (Reguler Service)' => 'mcj5',//PUT CARRIER NAME
        'ECO (Economy Service)' => 'mcj6',//PUT CARRIER NAME
        'ONS (Over Night Service)' => 'mcj7',//PUT CARRIER NAME
        //'Surat Kilat Khusus' => 'mcj8',//PUT CARRIER NAME
        'Paket Kilat Khusus' => 'mcj9',//PUT CARRIER NAME
        //'Express Next Day Dokumen' => 'mcj10',//PUT CARRIER NAME
        //'Paket Jumbo Ekonomi' => 'mcj11',//PUT CARRIER NAME
        //'Paketpos Dangerous Goods' => 'mcj12',//PUT CARRIER NAME
        'Express Next Day Barang' => 'mcj13',//PUT CARRIER NAME
        //'Paketpos Valuable Goods' => 'mcj14',//PUT CARRIER NAME
    );

    public function __construct()
    {
        $this->name = 'mycarrier_rj';//MOLDULE NAME
        $this->tab = 'shipping_logistics';//TAB MODULE
        $this->version = '1.1';//MODULE VERSION
        $this->author = 'Immanuel Julianto Lasmana';//CREATOR
        $this->bootstrap = TRUE;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('My Carrier RJ');
        $this->description = $this->l('My Carrier RJ, hitung ongkir jasa pengiriman barang atau ekspedisi via Jalur Nugraha Ekakurir (JNE), TIKI (Citra Van Titipan Kilat), POS (POS Indonesia). Menggunakan API dari https://rajaongkir.com. Module untuk prestashop GRATIS buatan Immanuel Julianto Lasmana, untuk dokumentasi cek https://soft-gain.com/2019/02/07/module-cek-ongkir-jne-tiki-pos-prestashop/');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
        if (!Configuration::get('myc_api'))      
          $this->warning = $this->l('No API Provided');

        if (!Configuration::get('myc_city'))      
          $this->warning = $this->l('No City From Provided');
    }

    public function install()
    {
        if (parent::install()) {//INSTALL HOOK
            foreach ($this->_hooks as $hook) {
                if (!$this->registerHook($hook)) {
                    return FALSE;
                }
            }

            if (!$this->installDB()) {//INSTALL DATABASE
                return FALSE;
            }

            if (!$this->createCarriers()) {//INSTAL CARRIER
                return FALSE;
            }

            if( _PS_VERSION_ < '1.7' ){
                copy(dirname(__FILE__).'/controllers/front/AddressController.php', _PS_OVERRIDE_DIR_.'/controllers/front/AddressController.php');
                rename(_PS_ROOT_DIR_.'/themes/default-bootstrap/address.tpl', _PS_ROOT_DIR_.'/themes/default-bootstrap/addressBAKMYCARRIERRJ.tpl');
                copy(dirname(__FILE__).'/views/theme/address.tpl', _PS_ROOT_DIR_.'/themes/default-bootstrap/address.tpl');
                unlink(_PS_ROOT_DIR_.'/cache/class_index.php');
            }else{
                // COPY CustomerAddressFormatter INTO OVERRIDE CLASS
                copy(dirname(__FILE__).'/controllers/front/CustomerAddressFormatter.php', _PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');
            }

            return TRUE;
        }

        return FALSE;
    }

    protected function uninstallDB()
    {
        $sql = array();

        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mycarrier_rj_ijl`';

        foreach ($sql as $_sql) {
            if (!Db::getInstance()->Execute($_sql)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    protected function installDB()
    {
        $sql = array();

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mycarrier_rj_ijl` (
            `id_mycarrier_rj_ijl` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_key` TEXT,
            `from_city` TEXT,
            `date_upd` DATETIME NULL,
            PRIMARY KEY (`id_mycarrier_rj_ijl`)
        ) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        foreach ($sql as $_sql) {
            if (!Db::getInstance()->Execute($_sql)) {
                return FALSE;
            }
        }        

        return TRUE;
    }

    protected function createCarriers()
    {
        //INSERT DATABASE 
        $query = "INSERT INTO "._DB_PREFIX_."mycarrier_rj_ijl (api_key, from_city) VALUES ('xxxxxxxxxxxxxxxxxxxx', 'Jakarta Utara')";
        Db::getInstance()->Execute($query);
        
            foreach ($this->_carriers as $key => $value) {
                //Create own carrier
                $carrier = new Carrier();                
                if($value=='mcj' OR $value=='mcj2' OR $value=='mcj3' OR $value=='mcj31' OR $value=='mcj32'){
                    $carrier->name = 'JNE';
                }
                if(/*$value=='mcj4' OR */$value=='mcj5' OR $value=='mcj6' OR $value=='mcj7'){
                    $carrier->name = 'TIKI';
                }
                if(/*$value=='mcj8' OR */$value=='mcj9' OR /*$value=='mcj10' OR $value=='mcj11'
                     OR $value=='mcj12' OR */$value=='mcj13'/* OR $value=='mcj14'*/){
                    $carrier->name = 'POS';
                } 
                $carrier->active = TRUE;
                $carrier->deleted = 0;
                $carrier->shipping_handling = FALSE;
                $carrier->range_behavior = 0;
                $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = $key;
                $carrier->shipping_external = TRUE;
                $carrier->is_module = TRUE;
                $carrier->external_module_name = $this->name;
                $carrier->need_range = TRUE;

                if ($carrier->add()) {
                    $groups = Group::getGroups(true);
                    foreach ($groups as $group) {
                        $query = "INSERT INTO "._DB_PREFIX_."carrier_group (id_carrier, id_group) VALUES ('".$carrier->id."', '".$group['id_group']."')";
                        Db::getInstance()->Execute($query);
                    }

                    $rangePrice = new RangePrice();
                    $rangePrice->id_carrier = $carrier->id;
                    $rangePrice->delimiter1 = '0';
                    $rangePrice->delimiter2 = '1000000';
                    $rangePrice->add();

                    $rangeWeight = new RangeWeight();
                    $rangeWeight->id_carrier = $carrier->id;
                    $rangeWeight->delimiter1 = '0';
                    $rangeWeight->delimiter2 = '1000000';
                    $rangeWeight->add();

                    $zones = Zone::getZones(true);
                    foreach ($zones as $z) {
                        $queryZone1 = "INSERT INTO "._DB_PREFIX_."carrier_zone (id_carrier, id_zone) VALUES ('".$carrier->id."', '".$z['id_zone']."')";
                        Db::getInstance()->Execute($queryZone1);
                        $queryZone2 = "INSERT INTO "._DB_PREFIX_."delivery (id_carrier, id_range_price, id_range_weight, id_zone, price) VALUES ('".$carrier->id."', '".$rangePrice->id."', '".NULL."', '".$z['id_zone']."', '25')";
                        Db::getInstance()->Execute($queryZone2);
                        $queryZone3 = "INSERT INTO "._DB_PREFIX_."delivery (id_carrier, id_range_price, id_range_weight, id_zone, price) VALUES ('".$carrier->id."', '".NULL."', '".$rangeWeight->id."', '".$z['id_zone']."', '25')";
                        Db::getInstance()->Execute($queryZone3);
                    }

                    if($value=='mcj' OR $value=='mcj2' OR $value=='mcj3' OR $value=='mcj31' OR $value=='mcj32'){
                        copy(dirname(__FILE__) . '/views/img/carrier.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }
                    if(/*$value=='mcj4' OR */$value=='mcj5' OR $value=='mcj6' OR $value=='mcj7'){
                        copy(dirname(__FILE__) . '/views/img/carrier.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }
                    if(/*$value=='mcj8' OR */$value=='mcj9' OR /*$value=='mcj10' OR $value=='mcj11'
                         OR $value=='mcj12' OR */$value=='mcj13'/* OR $value=='mcj14'*/){
                        copy(dirname(__FILE__) . '/views/img/carrier.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }                    

                    Configuration::updateValue(self::PREFIX . $value, $carrier->id);
                    Configuration::updateValue(self::PREFIX . $value . '_reference', $carrier->id);
                }
            }//end of foreach ($this->_carriers as $key => $value) {
                        
        return TRUE;
    }

    protected function deleteCarriers()
    {
        foreach ($this->_carriers as $value) {
            $tmp_carrier_id = Configuration::get(self::PREFIX . $value);
            $carrier = new Carrier($tmp_carrier_id);
            $carrier->delete();
        }

        return TRUE;
    }

    public function uninstall()
    {
        if (parent::uninstall()) {
            foreach ($this->_hooks as $hook) {
                if (!$this->unregisterHook($hook)) {
                    return FALSE;
                }
            }

            if (!$this->uninstallDB()) {
                return FALSE;
            }

            if (!$this->deleteCarriers()) {
                return FALSE;
            }

            if( _PS_VERSION_ < '1.7' ){
                unlink(_PS_OVERRIDE_DIR_.'/classes/controller/AddressController.php');
                unlink(_PS_ROOT_DIR_.'/themes/default-bootstrap/address.tpl');
                rename(_PS_ROOT_DIR_.'/themes/default-bootstrap/addressBAKMYCARRIERRJ.tpl', _PS_ROOT_DIR_.'/themes/default-bootstrap/address.tpl');
                unlink(_PS_ROOT_DIR_.'/cache/class_index.php');
            }else{
                // DELETE CustomerAddressFormatter FROM OVERRIDE CLASS            
                unlink(_PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');
            }

            return TRUE;
        }

        return FALSE;
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit'.$this->name))
        {
            $my_carrier_api = strval(Tools::getValue('myc_api'));
            $my_carrier_city = strval(Tools::getValue('myc_city'));
            if (!$my_carrier_api
              || empty($my_carrier_api)
              || !Validate::isGenericName($my_carrier_api)
              || !$my_carrier_city
              || empty($my_carrier_city)
              || !Validate::isGenericName($my_carrier_city))
                $output .= $this->displayError($this->l('Invalid Configuration Value'));
            else
            {
                Configuration::updateValue('myc_api', $my_carrier_api);
                Configuration::updateValue('myc_city', $my_carrier_city);
                //UPDATE DATABASE
                $query = "UPDATE "._DB_PREFIX_."mycarrier_rj_ijl SET api_key='".$my_carrier_api."', from_city='".$my_carrier_city."' WHERE id_mycarrier_rj_ijl = 1";
                Db::getInstance()->Execute($query);
                $output .= $this->displayConfirmation($this->l('Settings updated'));

            }
        }
        return $output.$this->displayForm();
    }

    public function displayForm()
        {

        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $options = array();
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);/*GET LIST OF CITY FROM LOCAL JSON FILE*/
        $responseCity = json_decode($responseCity);/*DECODE THE JSON, BECAUSE IT WAS A STRING*/      
          
        foreach ($responseCity->rajaongkir->results as $key) {
            $options[] = array(
                "id" => $key->city_name,
                "name" => $key->city_name
            );
        }
         
        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('My Carrier Setting'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'myc_api',
                    'size' => 50,
                    'required' => true
                ),
                array(
                    'type' => 'select',                              // This is a <select> tag.
                    'label' => $this->l('City From:'),         // The <label> for this <select> tag.
                    'desc' => $this->l('Choose a City From'),  // A help text, displayed right next to the <select> tag.
                    'name' => 'myc_city',                     // The content of the 'id' attribute of the <select> tag.
                    'required' => true,                              // If set to true, this option must be set.
                    'options' => array(
                        'query' => $options,                           // $options contains the data itself.
                            'id' => 'id',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
                            'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
                        )
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );
         
        $helper = new HelperForm();
         
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
         
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
         
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );
         
        // Load current value
        $helper->fields_value['myc_api'] = Configuration::get('myc_api');
        $helper->fields_value['myc_city'] = Configuration::get('myc_city');
         
        return $helper->generateForm($fields_form);
    }

    public function getOrderShippingCost($params, $shipping_cost)//CALCULATE SHIPPING COST HERE
    {

        // RajaOngkir API returns in grams, while front-end input is in kilograms
        // so we're now processing in grams.
        $weight = (float) $this->context->cart->getTotalWeight($this->context->cart->getProducts()) * 1000.0;
        $weight = max($weight, 1000.00); // minimum weight is 1kg

        //GET API KEY & CITY FROM DATABASE
        $sqlMyCarrier = 'SELECT * FROM '._DB_PREFIX_.'mycarrier_rj_ijl WHERE id_mycarrier_rj_ijl = 1';
        if ($rowMyCarrier = Db::getInstance()->getRow($sqlMyCarrier))        

        $address = new Address($this->context->cart->id_address_delivery);//GET CURRENT CUSTOMER ADDRESS DELIVERY

        $from = $rowMyCarrier['from_city'];
        $to = $address->city;
 
        /* GET CITY FROM AND TO FROM LOCAL JSON FILE (NOT UPDATED BUT FASTER) */
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);/*GET LIST OF CITY FROM LOCAL JSON FILE*/
        $responseCity = json_decode($responseCity);/*DECODE THE JSON, BECAUSE IT WAS A STRING*/      
        
        $fromCity = NULL;
        $toCity = NULL;

        foreach ($responseCity->rajaongkir->results as $key) {
            if ($key->city_name == $from) { $fromCity = $key->city_id; }

            if (preg_match_all("/\(([^\]]*)\)/", $to, $matches)) {
                if ($matches[1][0] == "Kota") {
                    $temp = explode("(", $to);
                    if (trim($temp[0]) == $key->city_name AND $key->type == "Kota") { $toCity = $key->city_id; }
                }
            } elseif ($key->city_name == $to) { $toCity = $key->city_id; }
            else { continue; }

            if (! is_null($fromCity) AND ! is_null($toCity)) { break; }
        }
        
        if( isset($fromCity) && isset($toCity) ){
            $cache_jne_id = 'ShoppingCost::jne::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_tiki_id = 'ShoppingCost::tiki::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_pos_id = 'ShoppingCost::pos::'.$fromCity.'_'.$toCity.'_'.$weight;

            /* JNE */
            if (!Cache::isStored($cache_jne_id)) {
                $roa = $this->checkRajaOngkirApi($rowMyCarrier["api_key"], $fromCity, $toCity, $weight, 'jne');
                $response = $roa["response"];
                $error_response = $roa["error"];

                if ($error_response) {
                    // bad things happened, and we have no shipping cost
                    // better we throw something here...
                    // TODO: shipping price is free if RajaOngkir API is dead.
                    return FALSE;
                } else {
                    // TODO: cache parsed results rather than cURL responses, reducing json_decode calls.
                    Cache::store($cache_jne_id, $response);
                    $responseCostJne = $response;
                }
            } else {
                $responseCostJne = Cache::retrieve($cache_jne_id);
                // dump($responseCostJne, "cachejne");
            }

            /* TIKI */
            if (!Cache::isStored($cache_tiki_id)) {
                $roa = $this->checkRajaOngkirApi($rowMyCarrier["api_key"], $fromCity, $toCity, $weight, 'tiki');
                $response = $roa["response"];
                $error_response = $roa["error"];

                if ($error_response) {
                    // bad things happened, and we have no shipping cost
                    // better we throw something here...
                    // TODO: shipping price is free if RajaOngkir API is dead.
                    return FALSE;
                } else {
                    // TODO: cache parsed results rather than cURL responses, reducing json_decode calls.
                    Cache::store($cache_tiki_id, $response);
                    $responseCostTiki = $response;
                }
            } else {
                $responseCostTiki = Cache::retrieve($cache_tiki_id);
                // dump($responseCostTiki, "cachetiki");
            }

            /* POS */
            if (!Cache::isStored($cache_pos_id)) {
                $roa = $this->checkRajaOngkirApi($rowMyCarrier["api_key"], $fromCity, $toCity, $weight, 'pos');
                $response = $roa["response"];
                $error_response = $roa["error"];

                if ($error_response) {
                    // bad things happened, and we have no shipping cost
                    // better we throw something here...
                    // TODO: shipping price is free if RajaOngkir API is dead.
                    return FALSE;
                } else {
                    // TODO: cache parsed results rather than cURL responses, reducing json_decode calls.
                    Cache::store($cache_pos_id, $response);
                    $responseCostPos = $response;
                }
            } else {
                $responseCostPos = Cache::retrieve($cache_pos_id);
                // dump($responseCostPos, "cachepos");
            } 

            $responseCostJne = json_decode($responseCostJne);        
            $ongkirOkeJne = false;
            $ongkirRegJne = false;
            $ongkirYesJne = false;
            $ongkirCtcJne = false;
            $ongkirCtcYesJne = false;
            if(isset($responseCostJne->rajaongkir->results[0]->costs[0]->cost[0]->value)){
                foreach ($responseCostJne->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'OKE' ){
                        $ongkirOkeJne=$value->cost[0]->value;
                    }
                    if( $value->service == 'REG' ){
                        $ongkirRegJne=$value->cost[0]->value;
                    }
                    if( $value->service == 'YES' ){
                        $ongkirYesJne=$value->cost[0]->value;
                    }
                    if( $value->service == 'CTC' ){
                        $ongkirCtcJne=$value->cost[0]->value;
                    }
                    if( $value->service == 'CTCYES' ){
                        $ongkirCtcYesJne=$value->cost[0]->value;
                    }
                }
            }           

            $responseCostTiki = json_decode($responseCostTiki);
            $ongkirTiki2=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[1]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'REG' ){
                        $ongkirTiki2=$value->cost[0]->value;
                        break;
                    }
                }
            }
            $ongkirTiki3=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[2]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'ECO' ){
                        $ongkirTiki3=$value->cost[0]->value;
                        break;
                    }
                }
            }
            $ongkirTiki4=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[3]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'ONS' ){
                        $ongkirTiki4=$value->cost[0]->value;
                        break;
                    }
                }
            }    

            $responseCostPos = json_decode($responseCostPos);
            $ongkirPos2=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[1]->cost[0]->value)){
                foreach ($responseCostPos->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'Paket Kilat Khusus' ){
                        $ongkirPos2=$value->cost[0]->value;
                        break;
                    }
                }
            }
            $ongkirPos6=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[5]->cost[0]->value)){
                foreach ($responseCostPos->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'Express Next Day Barang' ){
                        $ongkirPos6=$value->cost[0]->value;
                        break;
                    }
                }
            }

            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj_reference')))
                return $ongkirOkeJne;//ONGKIR KATEGORI OKE
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj2_reference')))
                return $ongkirRegJne;//ONGKIR KATEGORI REG
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj3_reference')))
                return $ongkirYesJne;//ONGKIR KATEGORI YES
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj31_reference')))
                return $ongkirCtcJne;//ONGKIR KATEGORI YES
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj32_reference')))
                return $ongkirCtcYesJne;//ONGKIR KATEGORI YES
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj5_reference')))
                return $ongkirTiki2;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj6_reference')))
                return $ongkirTiki3;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj7_reference')))
                return $ongkirTiki4;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj9_reference')))
                return $ongkirPos2;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj13_reference')))
                return $ongkirPos6;

            return false; // HILANGKAN CARRIER BILA ONGKIR TIDAK TERSEDIA
        }

        return false; // HILANGKAN CARRIER BILA ONGKIR TIDAK TERSEDIA

    }

    public function getOrderShippingCostExternal($params)//USE THIS IF CARRIER NEED RANGE = 0
    {
        return $this->getOrderShippingCost($params, 0);
    }

    /**
     * Hook method to call on Carrier update.
     * @see mycarrier_rj::$_hooks
     */
    public function hookActionCarrierUpdate($params) {
        // loops make things easier.
        foreach ($this->_carriers as $carrier_name => $carrier_properties) {
            foreach ($carrier_properties["services"] as $service_name => $service_properties) {
                $service_prefix = $service_properties["prefix"];

                if ($params["carrier"]->id_reference == Configuration::get(self::PREFIX . "{$service_prefix}_reference")) {
                    Configuration::updateValue(self::PREFIX . "{$service_prefix}", $params["carrier"]->id);
                }
            }
        }
    }

    /**
     * Log messages to a file.
     * @param string $message Message to log.
     * @return mixed
     */
    protected function log($message) {
        $file_ext = ".log";
        $ymd = strftime("%Y%m%d");
        $file_path = "{$this->_LOG_PATH}{$this->_LOG_PREFIX}_{$ymd}{$file_ext}";

        if (! $fp = @fopen($file_path, "ab")) { return FALSE; }

        flock($fp, LOCK_EX);
        $ctime = strftime("%Y-%m-%d %H:%M:%S");
        return fwrite($fp, "[{$ctime}] {$message}\n");

        fclose($fp);
    }

    /**
     * Send a request to RajaOngkir API
     * @param string $api_key RajaOngkir API key.
     * @param int $origin Origin city ID.
     * @param int $destination Destination city ID.
     * @param float $weight Package weight, in grams.
     * @param string $courier Courier flag.
     * @return array
     */
    protected function checkRajaOngkirApi($api_key, $origin, $destination, $weight, $courier) {
        $curl_obj = curl_init();

        // TODO: handle RajaOngkir non-starter accounts.
        curl_setopt_array($curl_obj, array(
            CURLOPT_URL            => "https://api.rajaongkir.com/starter/cost",
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => "origin={$origin}&destination={$destination}&weight={$weight}&courier={$courier}",
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => array("key: {$api_key}"),
        ));

        $response = curl_exec($curl_obj);
        $error_response = curl_error($curl_obj);
        curl_close($curl_obj);

        return array(
            "response" => $response, 
            "error" => $error_response
        );
    }

}

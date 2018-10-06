<?php
/*                  NOTE                  *
*******************************************/
/* Cek http://immanueljl.blogspot.co.id untuk informasi lebih lanjut.
* Dukung dengan like dan share :)
* ****************************************************
* @author  Immanuel Julianto Lasmana <immanueljl44@gmail.com>
* @site    http://immanueljl.blogspot.co.id
* @copyright  Copyright (c)2017 
* @license    FREE LICENSE SOFTWARE (BOLEH DIPAKAI UNTUK KEPERLUAN APAPUN TANPA MERUBAH COPYRIGHT NOTICE)
*/
if (!defined('_PS_VERSION_')) {
    exit;
}
/*THIS CLASS NAME SHOULD SAME WITH FOLDER/MODULE NAME*/
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
        $this->version = '1.0';//MODULE VERSION
        $this->author = 'Immanuel Julianto Lasmana';//CREATOR
        $this->bootstrap = TRUE;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('My Carrier RJ');
        $this->description = $this->l('My Carrier RJ, hitung ongkir jasa pengiriman barang atau ekspedisi via Jalur Nugraha Ekakurir (JNE), TIKI (Citra Van Titipan Kilat), POS (POS Indonesia). Menggunakan API dari https://rajaongkir.com. Module untuk prestashop GRATIS buatan Immanuel Julianto Lasmana, untuk dokumentasi cek http://immanueljl.blogspot.co.id');

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

            // COPY CustomerAddressFormatter INTO OVERRIDE CLASS
            copy(dirname(__FILE__).'/controllers/front/CustomerAddressFormatter.php', _PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');

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
                        copy(dirname(__FILE__) . '/views/img/carrier2.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }
                    if(/*$value=='mcj8' OR */$value=='mcj9' OR /*$value=='mcj10' OR $value=='mcj11'
                         OR $value=='mcj12' OR */$value=='mcj13'/* OR $value=='mcj14'*/){
                        copy(dirname(__FILE__) . '/views/img/carrier3.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
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

            // DELETE CustomerAddressFormatter FROM OVERRIDE CLASS            
            unlink(_PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');

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

        $weight = $this->context->cart->getTotalWeight($this->context->cart->getProducts());//GET TOTAL WEIGHT PN CART

        //GET API KEY & CITY FROM DATABASE
        $sqlMyCarrier = 'SELECT * FROM '._DB_PREFIX_.'mycarrier_rj_ijl WHERE id_mycarrier_rj_ijl = 1';
        if ($rowMyCarrier = Db::getInstance()->getRow($sqlMyCarrier))        

        $address = new Address($this->context->cart->id_address_delivery);//GET CURRENT CUSTOMER ADDRESS DELIVERY

        $from = $rowMyCarrier['from_city'];
        $to = $address->city;
        if($weight<1){//SET THE DEFFAULT IF WEIGHT IF BELOW 1 KG
        $weight=1;
        }
 
        /* GET CITY FROM AND TO FROM LOCAL JSON FILE (NOT UPDATED BUT FASTER) */
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);/*GET LIST OF CITY FROM LOCAL JSON FILE*/
        $responseCity = json_decode($responseCity);/*DECODE THE JSON, BECAUSE IT WAS A STRING*/      
          
           foreach ($responseCity->rajaongkir->results as $key) {
               if ($key->city_name == $from) {
                   $fromCity = $key->city_id;               
               }
           } 

           foreach ($responseCity->rajaongkir->results as $key) {
               if ($key->city_name == $to) {
                   $toCity = $key->city_id;               
               }
           }   
        /* GET CITY FROM AND TO FROM LOCAL JSON FILE (NOT UPDATED BUT FASTER) */

        /* JNE */

        if( isset($fromCity) && isset($toCity) ){

            $cache_jne_id = 'ShoppingCost::jne::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_tiki_id = 'ShoppingCost::tiki::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_pos_id = 'ShoppingCost::pos::'.$fromCity.'_'.$toCity.'_'.$weight;


             if (!Cache::isStored($cache_jne_id)) {

                $curlCostJne = curl_init();

                curl_setopt_array($curlCostJne, array(
                  CURLOPT_URL => "https://api.rajaongkir.com/starter/cost",
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 30,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "POST",
                  CURLOPT_POSTFIELDS => "origin=".$fromCity."&destination=".$toCity."&weight=".$weight."&courier=jne",
                  CURLOPT_IPRESOLVE => true,
                  CURL_IPRESOLVE_V4 => true,
                  CURLOPT_ENCODING => true,
                  CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded",
                    "key: ".$rowMyCarrier['api_key']
                  ),
                ));

                $responseCostJne = curl_exec($curlCostJne);

                 $errCostJne = curl_error($curlCostJne);

                curl_close($curlCostJne);
                if ($errCostJne) {
                  //echo "cURL Error #:" . $errCostJne;
                } else {
                    Cache::store($cache_jne_id, $responseCostJne);
                }
                
             }        
             else {
                $responseCostJne = Cache::retrieve($cache_jne_id);
                // dump($responseCostJne, "cachejne");
             } 

            
           
            /* JNE */


             if (!Cache::isStored($cache_tiki_id)) {
                 $curlCostTiki = curl_init();

                curl_setopt_array($curlCostTiki, array(
                  CURLOPT_URL => "https://api.rajaongkir.com/starter/cost",
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 30,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "POST",
                  CURLOPT_POSTFIELDS => "origin=".$fromCity."&destination=".$toCity."&weight=".$weight."&courier=tiki",
                  CURLOPT_IPRESOLVE => true,
                  CURL_IPRESOLVE_V4 => true,
                  CURLOPT_ENCODING => true,
                  CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded",
                    "key: ".$rowMyCarrier['api_key']
                  ),
                ));

                $responseCostTiki = curl_exec($curlCostTiki);
                $errCostTiki = curl_error($curlCostTiki);

                curl_close($curlCostTiki);
                if ($errCostTiki) {
                  //echo "cURL Error #:" . $errCostTiki;
                } else {
                   Cache::store($cache_tiki_id, $responseCostTiki);
                }

             }        
             else {
                $responseCostTiki = Cache::retrieve($cache_tiki_id);
                // dump($responseCostTiki, "cachetiki");
             }  
            /* TIKI */
           
            /* TIKI */

            /* POS */
            if (!Cache::isStored($cache_pos_id)) {

                $curlCostPos = curl_init();

                curl_setopt_array($curlCostPos, array(
                  CURLOPT_URL => "https://api.rajaongkir.com/starter/cost",
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 30,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "POST",
                  CURLOPT_POSTFIELDS => "origin=".$fromCity."&destination=".$toCity."&weight=".$weight."&courier=pos",
                  CURLOPT_IPRESOLVE => true,
                  CURL_IPRESOLVE_V4 => true,
                  CURLOPT_ENCODING => true,
                  CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded",
                    "key: ".$rowMyCarrier['api_key']
                  ),
                ));

                $responseCostPos = curl_exec($curlCostPos);
                $errCostPos = curl_error($curlCostPos);
                
                curl_close($curlCostPos);

                if ($errCostPos) {
                  //echo "cURL Error #:" . $errCostPos;
                }    else {
                   Cache::store($cache_pos_id, $responseCostPos);
                }


             }        
             else {
                $responseCostPos = Cache::retrieve($cache_pos_id);
                // dump($responseCostPos, "cachepos");
             } 
            /* POS */  
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
            /*$ongkirTiki1=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[0]->cost[0]->value)){
                $ongkirTiki1=$responseCostTiki->rajaongkir->results[0]->costs[0]->cost[0]->value;
            }*/
            $ongkirTiki2=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[1]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'REG' ){
                        $ongkirTiki2=$value->cost[0]->value;
                    }
                }
            }
            $ongkirTiki3=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[2]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'ECO' ){
                        $ongkirTiki3=$value->cost[0]->value;
                    }
                }
            }
            $ongkirTiki4=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostTiki->rajaongkir->results[0]->costs[3]->cost[0]->value)){
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'ONS' ){
                        $ongkirTiki4=$value->cost[0]->value;
                    }
                }
            }    

            $responseCostPos = json_decode($responseCostPos);     
            /*$ongkirPos1=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[0]->cost[0]->value)){
                $ongkirPos1=$responseCostPos->rajaongkir->results[0]->costs[0]->cost[0]->value;
            }*/
            $ongkirPos2=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[1]->cost[0]->value)){
                foreach ($responseCostPos->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'Paket Kilat Khusus' ){
                        $ongkirPos2=$value->cost[0]->value;
                    }
                }
            }
            /*$ongkirPos3=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[2]->cost[0]->value)){
                $ongkirPos3=$responseCostPos->rajaongkir->results[0]->costs[2]->cost[0]->value;
            }
            $ongkirPos4=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[3]->cost[0]->value)){
                $ongkirPos4=$responseCostPos->rajaongkir->results[0]->costs[3]->cost[0]->value;
            }
            $ongkirPos5=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[4]->cost[0]->value)){
                $ongkirPos5=$responseCostPos->rajaongkir->results[0]->costs[4]->cost[0]->value;
            }*/
            $ongkirPos6=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[5]->cost[0]->value)){
                foreach ($responseCostPos->rajaongkir->results[0]->costs as $value) {
                    if( $value->service == 'Express Next Day Barang' ){
                        $ongkirPos6=$value->cost[0]->value;
                    }
                }
            }
            /*$ongkirPos7=false;//PREPARE ONGKIR VALUE
            if(isset($responseCostPos->rajaongkir->results[0]->costs[6]->cost[0]->value)){
                $ongkirPos7=$responseCostPos->rajaongkir->results[0]->costs[6]->cost[0]->value;
            }*/

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
            /*if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj4_reference')))
                return $ongkirTiki1;*/
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj5_reference')))
                return $ongkirTiki2;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj6_reference')))
                return $ongkirTiki3;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj7_reference')))
                return $ongkirTiki4;

            /*if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj8_reference')))
                return $ongkirPos1;*/
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj9_reference')))
                return $ongkirPos2;
            /*if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj10_reference')))
                return $ongkirPos3;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj11_reference')))
                return $ongkirPos4;
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj12_reference')))
                return $ongkirPos5;*/
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj13_reference')))
                return $ongkirPos6;
            /*if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'mcj14_reference')))
                return $ongkirPos7;*/
            return false;//HILANGKAN CARRIER BILA ONGKIR TIDAK TERSEDIA
        }
    }

    public function getOrderShippingCostExternal($params)//USE THIS IF CARRIER NEED RANGE = 0
    {
        return $this->getOrderShippingCost($params, 0);
    }

    public function hookActionCarrierUpdate($params)//This hook is required to ensure that the module will not lose connection with the carrier, created by the module
    {
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj2_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj2', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj3_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj3', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj31_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj31', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj32_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj32', $params['carrier']->id);
        }
        /*if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj4_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj4', $params['carrier']->id);
        }*/
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj5_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj5', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj6_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj6', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj7_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj7', $params['carrier']->id);
        }
        /*if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj8_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj8', $params['carrier']->id);
        }*/
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj9_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj9', $params['carrier']->id);
        }
        /*if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj10_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj10', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj11_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj11', $params['carrier']->id);
        }
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj12_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj12', $params['carrier']->id);
        }*/
        if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj13_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj13', $params['carrier']->id);
        }
        /*if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . 'mcj14_reference')) {
            Configuration::updateValue(self::PREFIX . 'mcj14', $params['carrier']->id);
        }*/
    }

}

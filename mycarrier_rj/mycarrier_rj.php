<?php
/* mycarrier_rj.php
 * File module utama untuk install, uninstall, dan proses penghitungan ongkir via API Raja Ongkir
 *
 * Cek http://immanueljl.blogspot.co.id untuk informasi lebih lanjut.
 * Dukung dengan like dan share :)
 *
 * @author  Immanuel Julianto Lasmana <immanueljl44@gmail.com>
 * @site    http://immanueljl.blogspot.co.id
 * @copyright  Copyright (c)2017
 * @license    FREE LICENSE SOFTWARE (BOLEH DIPAKAI UNTUK KEPERLUAN APAPUN TANPA MERUBAH COPYRIGHT NOTICE)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class mycarrier_rj extends CarrierModule
{
    const PREFIX = 'mycarrier_rj_mcj_';
    const FRONT_TABLE_NAME = _DB_PREFIX_ . 'mycarrier_rj_ijl';

    public $id_carrier;

    protected $_hooks = array(
        'actionCarrierUpdate',
    );

    protected $_carriers = array(
        // JNE
        'OKE (Ongkos Kirim Ekonomis)' => 'mcj',
        'REG (Reguler)'               => 'mcj2'
        'YES (Yakin Esok Sampai)'     => 'mcj3',
        'CTC (JNE City Courier)'      => 'mcj31',
        'CTCYES (JNE City Courier)'   => 'mcj32',

        // TIKI
        'REG (Reguler Service)'    => 'mcj5',
        'ECO (Economy Service)'    => 'mcj6',
        'ONS (Over Night Service)' => 'mcj7',
        // 'HDS (Holiday Service)' => 'mcj4',

        // POS Indonesia
        'Paket Kilat Khusus'         => 'mcj9',
        'Express Next Day Barang'    => 'mcj13',
        //'Surat Kilat Khusus'       => 'mcj8',
        //'Express Next Day Dokumen' => 'mcj10',
        //'Paket Jumbo Ekonomi'      => 'mcj11',
        //'Paketpos Dangerous Goods' => 'mcj12',
        //'Paketpos Valuable Goods'  => 'mcj14',
    );

    public function __construct() {
        $this->name       = 'mycarrier_rj';
        $this->tab        = 'shipping_logistics';
        $this->version    = '1.0';
        $this->author     = 'Immanuel Julianto Lasmana';
        $this->bootstrap  = TRUE;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('My Carrier RJ');
        $this->description = $this->l('My Carrier RJ, hitung ongkir jasa pengiriman barang'
                                      . ' atau ekspedisi via Jalur Nugraha Ekakurir (JNE), '
                                      .'TIKI (Citra Van Titipan Kilat), POS (POS Indonesia).'
                                      . ' Menggunakan API dari https://rajaongkir.com. Module'
                                      . ' untuk prestashop GRATIS buatan Immanuel Julianto '
                                      . 'Lasmana, untuk dokumentasi cek http://immanueljl.blogspot.co.id');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (! Configuration::get('myc_api')) {
            $this->warning = $this->l('No API Provided');
        }

        if (! Configuration::get('myc_city')) {
            $this->warning = $this->l('No City From Provided');
        }
    }

    public function install() {
        if (parent::install()) {
            foreach ($this->_hooks as $hook) {
                if (! $this->registerHook($hook)) { return FALSE; }
            }
            if (! $this->installDB())      { return FALSE; }
            if (! $this->createCarriers()) { return FALSE; }

            // Set our custom CustomerAddressFormatter
            copy(dirname(__FILE__).'/controllers/front/CustomerAddressFormatter.php',
                 _PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');

            return TRUE;
        }

        return FALSE;
    }

    protected function uninstallDB() {
        $queries = array(
            'DROP TABLE IF EXISTS `' . self::FRONT_TABLE_NAME . '`',
        );

        foreach ($queries as $query) {
            if (! Db::getInstance()->Execute($query)) { return FALSE; }
        }

        return TRUE;
    }

    protected function installDB() {
        $queries = array(
            'CREATE TABLE IF NOT EXISTS `' . self::FRONT_TABLE_NAME . '` (
                `id_mycarrier_rj_ijl` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT,
                `api_key` TEXT,
                `from_city` TEXT,
                `date_upd` DATETIME NULL,
                PRIMARY KEY (`id_mycarrier_rj_ijl`)
            ) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET = utf8',
        );

        foreach ($queries as $query) {
            if (! Db::getInstance()->Execute($query)) { return FALSE; }
        }

        return TRUE;
    }

    protected function createCarriers() {
        $query = "INSERT INTO `" . self::FRONT_TABLE_NAME . "` (api_key, from_city) VALUES ('xxxxxxxxxxxxxxxxxxxx', 'Jakarta Utara')";
        Db::getInstance()->Execute($query);

        foreach ($this->_carriers as $key => $value) {
            $carrier = new Carrier();

            if ($value == 'mcj'
                OR $value == 'mcj2'
                OR $value == 'mcj3'
                OR $value == 'mcj31'
                OR $value == 'mcj32') { $carrier->name = 'JNE'; }

            if ($value=='mcj5'
                OR $value=='mcj6'
                OR $value=='mcj7'
                /* OR $value=='mcj4' */) { $carrier->name = 'TIKI'; }

            if ($value=='mcj9'
                /* OR $value=='mcj10'
                OR $value=='mcj11'
                OR $value=='mcj12' */
                OR $value=='mcj13'
                /* OR $value=='mcj14'
                OR $value=='mcj8 */) { $carrier->name = 'POS'; }

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
                    $query = "INSERT INTO " . _DB_PREFIX_ . "carrier_group (id_carrier, id_group)
                              VALUES ('" . $carrier->id . "', '" . $group['id_group'] . "')";
                    Db::getInstance()->Execute($query);
                }

                $rangePrice = new RangePrice();
                $rangePrice->id_carrier = $carrier->id;
                $rangePrice->delimiter1 = 0.0;
                $rangePrice->delimiter2 = 100000000.0;
                $rangePrice->add();

                $rangeWeight = new RangeWeight();
                $rangeWeight->id_carrier = $carrier->id;
                $rangeWeight->delimiter1 = 0.0;
                $rangeWeight->delimiter2 = 1000000.0;
                $rangeWeight->add();

                $zones = Zone::getZones(true);
                foreach ($zones as $zone) {
                    $zone_id = $zone["id_zone"];

                    $queryZone1 = "INSERT INTO " . _DB_PREFIX_ . "carrier_zone (id_carrier, id_zone)
                                   VALUES ('" . $carrier->id . "', '" . $zone_id . "')";
                    Db::getInstance()->Execute($queryZone1);

                    $queryZone2 = "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                                   VALUES ('" . $carrier->id . "', '" . $rangePrice->id . "', '" . NULL . "', '" . $zone_id . "', '25')";
                    Db::getInstance()->Execute($queryZone2);

                    $queryZone3 = "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                                   VALUES ('" . $carrier->id . "', '" . NULL . "', '" . $rangeWeight->id . "', '" . $zone_id . "', '25')";
                    Db::getInstance()->Execute($queryZone3);
                }

                if ($value == 'mcj'
                    OR $value == 'mcj2'
                    OR $value == 'mcj3'
                    OR $value == 'mcj31'
                    OR $value == 'mcj32') {
                    copy(dirname(__FILE__) . '/views/img/carrier.jpg',
                         _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                }

                if ($value=='mcj5'
                    OR $value=='mcj6'
                    OR $value=='mcj7'
                    /* OR $value=='mcj4' */) {
                    copy(dirname(__FILE__) . '/views/img/carrier2.jpg',
                         _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                }

                if ($value=='mcj9'
                    /* OR $value=='mcj10'
                    OR $value=='mcj11'
                    OR $value=='mcj12' */
                    OR $value=='mcj13'
                    /* OR $value=='mcj14'
                    OR $value=='mcj8 */) {
                    copy(dirname(__FILE__) . '/views/img/carrier3.jpg',
                         _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                }

                Configuration::updateValue(self::PREFIX . $value, $carrier->id);
                Configuration::updateValue(self::PREFIX . $value . '_reference', $carrier->id);
            }
        }

        return TRUE;
    }

    protected function deleteCarriers() {
        foreach ($this->_carriers as $current_carrier) {
            $tmp_carrier_id = Configuration::get(self::PREFIX . $current_carrier);
            $carrier = new Carrier($tmp_carrier_id);
            $carrier->delete();
        }

        return TRUE;
    }

    public function uninstall() {
        if (parent::uninstall()) {
            foreach ($this->_hooks as $hook) {
                if (! $this->unregisterHook($hook)) { return FALSE; }
            }

            if (!$this->uninstallDB()) { return FALSE; }
            if (!$this->deleteCarriers()) { return FALSE; }

            unlink(_PS_OVERRIDE_DIR_.'/classes/form/CustomerAddressFormatter.php');

            return TRUE;
        }

        return FALSE;
    }

    public function getContent() {
        $output = NULL;

        if (Tools::isSubmit('submit'.$this->name)) {
            $api_key = strval(Tools::getValue('myc_api'));
            $origin_city = strval(Tools::getValue('myc_city'));

            if (! $api_key
                OR empty($api_key)
                OR ! Validate::isGenericName($api_key)

                OR ! $origin_city
                OR empty($origin_city)
                OR ! Validate::isGenericName($origin_city)) {
                $output .= $this->displayError($this->l('Invalid configuration value!'));
            } else {
                Configuration::updateValue('myc_api', $api_key);
                Configuration::updateValue('myc_city', $origin_city);

                $query = "UPDATE `" . self::FRONT_TABLE_NAME . "`
                          SET api_key='" . $api_key . "', from_city='" . $origin_city . "'
                          WHERE id_mycarrier_rj_ijl = 1";
                Db::getInstance()->Execute($query);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    public function displayForm() {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $options = array();
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);
        $responseCity = json_decode($responseCity);

        foreach ($responseCity->rajaongkir->results as $key) {
            $options[] = array(
                "id" => $key->city_name,
                "name" => $key->city_name
            );
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('My Carrier Setting'),
            ),
            'input' => array(
                array(
                    'type'     => 'text',
                    'label'    => $this->l('API Key:'),
                    'desc'     => $this->l("Raja Ongkir API Key (see https://rajaongkir.com/dokumentasi#aturan-penggunaan).")
                    'name'     => 'myc_api',
                    'size'     => 50,
                    'required' => true
                ),
                array(
                    'type'     => 'select',
                    'label'    => $this->l('Origin City:'),
                    'desc'     => $this->l('Choose origin city.'),
                    'name'     => 'myc_city',
                    'required' => true,
                    'options'  => array(
                        'query' => $options,
                        'id'    => 'id',
                        'name'  => 'name'
                    )
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'desc' => $this->l('Back to list'),
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules')
            )
        );

        $helper->fields_value['myc_api'] = Configuration::get('myc_api');
        $helper->fields_value['myc_city'] = Configuration::get('myc_city');

        return $helper->generateForm($fields_form);
    }

    protected function checkRajaOngkirApi($origin, $destination, $weight, $courier) {
        $curl_obj = curl_init();

        curl_setopt_array($curl_obj, array(
            CURLOPT_URL            => "https://api.rajaongkir.com/starter/cost",
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => "origin={$origin}&destination={$destination}&weight={$weight}&courier={$courier}",
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => array( "key: " . $rowMyCarrier["api_key"] ),
        ));

        $response = curl_exec($curl_obj);
        $error_response = curl_error($curl_obj);
        curl_close($curl_obj);

        return array($response, $error_response);
    }

    public function getOrderShippingCost($params, $shipping_cost) {
        // RajaOngkir API returns in gram, while front-end input is in kilogram
        $weight = (float) $this->context->cart->getTotalWeight($this->context->cart->getProducts()) * 1000.0;

        $sqlMyCarrier = 'SELECT * FROM `' . self::FRONT_TABLE_NAME . '` WHERE id_mycarrier_rj_ijl = 1';

        // this two line should be detached, but that's what I got from code.
        if ($rowMyCarrier = Db::getInstance()->getRow($sqlMyCarrier)) {
            $address = new Address($this->context->cart->id_address_delivery);
        }

        $from = $rowMyCarrier['from_city'];
        $to = $address->city;

        $weight = max(($weight * 1.0), 1000.00); // weight in RajaOngkir API is in grams, and minimum weight is 1kg.

        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);
        $responseCity = json_decode($responseCity);

        $fromCity = NULL;
        $toCity = NULL;

        foreach ($responseCity->rajaongkir->results as $key) {
            if ($key->city_name == $from) { $fromCity = $key->city_id; }
            if ($key->city_name == $to)   { $toCity = $key->city_id; }

            if (! is_null($fromCity) AND ! is_null($toCity)) { break; }
        }

        if (! is_null($fromCity) && ! is_null($toCity)) {
            $cache_jne_id = 'ShoppingCost::jne::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_tiki_id = 'ShoppingCost::tiki::'.$fromCity.'_'.$toCity.'_'.$weight;
            $cache_pos_id = 'ShoppingCost::pos::'.$fromCity.'_'.$toCity.'_'.$weight;

            /* JNE */
            if (! Cache::isStored($cache_jne_id)) {
                $jne_roa = $this->checkRajaOngkirApi($fromCity, $toCity, $weight, "jne");

                $responseCostJne = $jne_roa[0];
                $error_response = $jne_roa[1];

                if ($error_response) {
                    // do nothing, as in current module error.
                } else {
                    Cache::store($cache_jne_id, $responseCostJne);
                }
            } else {
                $responseCostJne = Cache::retrieve($cache_jne_id);
            }

            /* TIKI */
            if (! Cache::isStored($cache_tiki_id)) {
                $tiki_roa = $this->checkRajaOngkirApi($fromCity, $toCity, $weight, "tiki");

                $responseCostTiki = $tiki_roa[0];
                $error_response = $tiki_roa[1];

                if ($error_response) {
                    // do nothing, as in current module error.
                } else {
                    Cache::store($cache_tiki_id, $responseCostTiki);
                }
            } else {
                $responseCostTiki = Cache::retrieve($cache_tiki_id);
            }

            /* POS */
            if (! Cache::isStored($cache_pos_id)) {
                $pos_roa = $this->checkRajaOngkirApi($fromCity, $toCity, $weight, "pos");

                $responseCostPos = $pos_roa[0];
                $error_response = $pos_roa[1];

                if ($error_response) {
                    // do nothing, as in current module error.
                } else {
                    Cache::store($cache_pos_id, $responseCostPos);
                }
            } else {
                $responseCostPos = Cache::retrieve($cache_pos_id);
            }

            /* Parse API result values */
            $ongkirOkeJne = FALSE;
            $ongkirRegJne = FALSE;
            $ongkirYesJne = FALSE;
            $ongkirCtcJne = FALSE;
            $ongkirCtcYesJne = FALSE;

            $responseCostJne = json_decode($responseCostJne);
            if (isset($responseCostJne->rajaongkir->results[0]->costs[0]->cost[0]->value)) {
                foreach ($responseCostJne->rajaongkir->results[0]->costs as $value) {
                    if ($value->service == 'OKE')    { $ongkirOkeJne    = $value->cost[0]->value; }
                    if ($value->service == 'REG')    { $ongkirRegJne    = $value->cost[0]->value; }
                    if ($value->service == 'YES')    { $ongkirYesJne    = $value->cost[0]->value; }
                    if ($value->service == 'CTC')    { $ongkirCtcJne    = $value->cost[0]->value; }
                    if ($value->service == 'CTCYES') { $ongkirCtcYesJne = $value->cost[0]->value; }
                }
            }

            $ongkirRegTiki = FALSE;
            $ongkirEcoTiki = FALSE;
            $ongkirOnsTiki = FALSE;

            $responseCostTiki = json_decode($responseCostTiki);
            if (isset($responseCostTiki->rajaongkir->results[0]->costs[0]->cost[0]->value)) {
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if ($value->service == 'REG') { $ongkirRegTiki = $value->cost[0]->value; }
                    if ($value->service == 'ECO') { $ongkirEcoTiki = $value->cost[0]->value; }
                    if ($value->service == 'ONS') { $ongkirOnsTiki = $value->cost[0]->value; }
                }
            }

            $ongkirPkkPos = FALSE;
            $ongkirEndPos = FALSE;

            $responseCostPos = json_decode($responseCostPos);
            if (isset($responseCostPos->rajaongkir->results[0]->costs[0]->cost[0]->value)) {
                foreach ($responseCostTiki->rajaongkir->results[0]->costs as $value) {
                    if ($value->service == 'Paket Kilat Khusus')      { $ongkirPkkPos = $value->cost[0]->value; }
                    if ($value->service == 'Express Next Day Barang') { $ongkirEndPos = $value->cost[0]->value; }
                }
            }

            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj_reference')))   { return $ongkirOkeJne; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj2_reference')))  { return $ongkirRegJne; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj3_reference')))  { return $ongkirYesJne; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj31_reference'))) { return $ongkirCtcJne; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj32_reference'))) { return $ongkirCtcYesJne; }

            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj5_reference'))) { return $ongkirRegTiki; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj6_reference'))) { return $ongkirEcoTiki; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj7_reference'))) { return $ongkirOnsTiki; }

            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj9_reference')))  { return $ongkirPkkPos; }
            if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . 'mcj13_reference'))) { return $ongkirEndPos; }

            return FALSE;
        }
    }

    public function getOrderShippingCostExternal($params) {
        return $this->getOrderShippingCost($params, 0);
    }

    public function hookActionCarrierUpdate($params) {
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

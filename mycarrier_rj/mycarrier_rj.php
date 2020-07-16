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

    protected static $_IMAGE_JNE  = dirname(__FILE__) . '/views/img/carrier.jpg';
    protected static $_IMAGE_TIKI = dirname(__FILE__) . '/views/img/carrier2.jpg';
    protected static $_IMAGE_POS  = dirname(__FILE__) . '/views/img/carrier3.jpg';

    public $id_carrier;

    protected $_hooks = array(
        'actionCarrierUpdate',
    );

    protected $_carriers = array(
        /* JNE */
        "JNE"  => array(
            "rj_api_courier" => "jne",
            "services"       => array(
                'OKE (Ongkos Kirim Ekonomis)' => array("prefix" => 'mcj',   "rj_service" => "OKE"),
                'REG (Reguler)'               => array("prefix" => 'mcj2',  "rj_service" => "REG"),
                'YES (Yakin Esok Sampai)'     => array("prefix" => 'mcj3',  "rj_service" => "YES"),
                'CTC (JNE City Courier)'      => array("prefix" => 'mcj31', "rj_service" => "CTC"),
                'CTCYES (JNE City Courier)'   => array("prefix" => 'mcj32', "rj_service" => "CTCYES"),
            ),
        ),

        /* TIKI */
        "TIKI" => array(
            "rj_api_courier" => "tiki",
            "services"       => array(
                'REG (Reguler Service)'    => array("prefix" => 'mcj5', "rj_service" => "REG"),
                'ECO (Economy Service)'    => array("prefix" => 'mcj6', "rj_service" => "ECO"),
                'ONS (Over Night Service)' => array("prefix" => 'mcj7', "rj_service" => "ONS"),
                // 'HDS (Holiday Service)' => 'mcj4',
            ),
        ),

        /* POS Indonesia */
        "POS"  => array(
            "rj_api_courier" => "pos",
            "services"       => array(
                'Paket Kilat Khusus'         => array("prefix" => 'mcj9',  "rj_service" => "Paket Kilat Khusus"),
                'Express Next Day Barang'    => array("prefix" => 'mcj13', "rj_service" => "Express Next Day Barang"),
                //'Surat Kilat Khusus'       => 'mcj8',
                //'Express Next Day Dokumen' => 'mcj10',
                //'Paket Jumbo Ekonomi'      => 'mcj11',
                //'Paketpos Dangerous Goods' => 'mcj12',
                //'Paketpos Valuable Goods'  => 'mcj14',
            ),
        ),
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

        foreach ($this->_carriers as $carrier_name => $carrier_properties) {
            foreach ($carrier_properties["services"] as $service_name => $service_properties) {
                $service_prefix = $service_properties["prefix"];

                $carrier = new Carrier();

                $carrier->name                  = $carrier_name;
                $carrier->active                = TRUE;
                $carrier->deleted               = 0;
                $carrier->shipping_handling     = FALSE;
                $carrier->range_behavior        = 0;
                $carrier->shipping_external     = TRUE;
                $carrier->is_module             = TRUE;
                $carrier->external_module_name  = $this->name;
                $carrier->need_range            = TRUE;

                $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = $service_name;

                if ($carrier->add()) {
                    $groups = Group::getGroups(true);
                    foreach ($groups as $group) {
                        $query = "INSERT INTO " . _DB_PREFIX_ . "carrier_group (id_carrier, id_group)
                                  VALUES ('" . $carrier->id . "', '" . $group['id_group'] . "')";
                        Db::getInstance()->Execute($query);
                    }

                    $rangePrice             = new RangePrice();
                    $rangePrice->id_carrier = $carrier->id;
                    $rangePrice->delimiter1 = 0.0;
                    $rangePrice->delimiter2 = 100000000.0;
                    $rangePrice->add();

                    $rangeWeight             = new RangeWeight();
                    $rangeWeight->id_carrier = $carrier->id;
                    $rangeWeight->delimiter1 = 0.0;
                    $rangeWeight->delimiter2 = 1000000.0;
                    $rangeWeight->add();

                    $zones = Zone::getZones(true);
                    foreach ($zones as $zone) {
                        $zone_id = $zone["id_zone"];

                        $zone_queries = array(
                            "INSERT INTO " . _DB_PREFIX_ . "carrier_zone (id_carrier, id_zone)
                             VALUES ('" . $carrier->id . "', '" . $zone_id . "')",
                            "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                             VALUES ('" . $carrier->id . "', '" . $rangePrice->id . "', '" . NULL . "', '" . $zone_id . "', '25')",
                            "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                             VALUES ('" . $carrier->id . "', '" . NULL . "', '" . $rangeWeight->id . "', '" . $zone_id . "', '25')",
                        );

                        foreach ($zone_queries as $zone_query) { Db::getInstance()->Execute($zone_query); }
                    }

                    if ($carrier_name == "JNE") {
                        copy(self::$_IMAGE_JNE,
                             _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }

                    if ($carrier_name == "TIKI") {
                        copy(self::$_IMAGE_TIKI,
                             _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }

                    if ($carrier_name == "POS") {
                        copy(self::$_IMAGE_POS,
                             _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                    }

                    Configuration::updateValue(self::PREFIX . $value, $carrier->id);
                    Configuration::updateValue(self::PREFIX . $value . '_reference', $carrier->id);
                }
            }
        }

        return TRUE;
    }

    protected function deleteCarriers() {
        foreach ($this->_carriers as $carrier_name => $carrier_properties) {
            foreach ($carrier_properties["services"] as $service_name => $service_properties) {
                $service_prefix = $service_properties["prefix"];

                $tmp_carrier_id = Configuration::get(self::PREFIX . $service_prefix);
                $carrier = new Carrier($tmp_carrier_id);
                $carrier->delete();
            }
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
                    'desc'     => $this->l("Raja Ongkir API Key (see https://rajaongkir.com/dokumentasi#aturan-penggunaan)."),
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
            CURLOPT_MAXREDIRS      => 10,
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
        $carrier_name = NULL;
        $carrier_flag = NULL;
        $service_name = NULL;
        $service_flag = NULL;
        $originCity = NULL;
        $destinationCity = NULL;

        foreach ($this->_carriers as $l_carrier_name => $l_carrier_properties) {
            foreach ($l_carrier_properties["services"] as $l_service_name => $l_service_properties) {
                $service_prefix = $l_service_properties["prefix"];

                if ($this->id_carrier == (int) (Configuration::get(self::PREFIX . "{$service_prefix}_reference"))) {
                    $carrier_flag = $l_service_properties["rj_api_courier"];
                    $carrier_name = $l_carrier_name;
                    $service_name = $l_service_name;
                    $service_flag = $l_service_properties["rj_service"];

                    // we found our data, just break.
                    break;
                }
            }
            if (! is_null($service_flag)) { break; /* domino effect from inner foreach */ }
        }

        if (is_null($service_flag)) { return FALSE; } // we don't have service and carrier data here.

        // question is, do we need to check other flags, i.e. $carrier_flag, etc

        // RajaOngkir API returns in grams, while front-end input is in kilograms
        // so we're now processing in grams.
        // I do sound like a drug dealer here, grams grams grams...
        $weight = (float) $this->context->cart->getTotalWeight($this->context->cart->getProducts()) * 1000.0;
        $weight = max(($weight * 1.0), 1000.00); // minimum weight is 1kg

        $sqlMyCarrier = 'SELECT * FROM `' . self::FRONT_TABLE_NAME . '` WHERE id_mycarrier_rj_ijl = 1';
        $rowMyCarrier = Db::getInstance()->getRow($sqlMyCarrier);

        $address = new Address($this->context->cart->id_address_delivery);

        $from = $rowMyCarrier['from_city'];
        $to = $address->city;

        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);
        $responseCity = json_decode($responseCity);

        $originCity = NULL;
        $destinationCity = NULL;

        foreach ($responseCity->rajaongkir->results as $key) {
            if ($key->city_name == $from) { $originCity = $key->city_id; }
            if ($key->city_name == $to)   { $destinationCity = $key->city_id; }

            if (! is_null($originCity) AND ! is_null($destinationCity)) { break; }
        }

        if (is_null($originCity) || is_null($destinationCity)) { return FALSE; } // cannot calculate if there's no origin/destination.

        // we got origin and destination.
        $cache_id = "ShoppingCost::{$carrier_flag}::{$originCity}_{$destinationCity}_{$weight}";
        if (! Cache::is_stored($cache_id)) {
            $roa = $this->checkRajaOngkirApi($originCity, $destinationCity, $weight, $carrier_flag);
            $response = $roa[0];
            $error_response = $roa[1];

            if ($error_response) {
                // bad things happened, and we have no shipping cost
                // better we throw something here...
                return FALSE;
            } else {
                Cache::store($cache_id, $response);
            }
        } else {
            $response = Cache::retrieve($cache_id);
        }

        // we got API response in $response, now we need to compare to our $service_flag to get shipping cost.
        if (isset($response->rajaongkir->results[0]->costs[0]->cost[0]->value)) { // just to validate our response
            foreach ($response->rajaongkir->results[0]->costs as $value) {
                if ($value->service == $service_flag) { return $value->cost[0]->value; }
            }
        }

        return FALSE;
    }

    public function getOrderShippingCostExternal($params) {
        return $this->getOrderShippingCost($params, 0);
    }

    public function hookActionCarrierUpdate($params) {
        # loops make things easier.
        foreach ($this->_carriers as $carrier_name => $carrier_properties) {
            foreach ($carrier_properties["services"] as $service_name => $service_properties) {
                $service_prefix = $service_properties["prefix"];

                if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . "{$service_prefix}_reference")) {
                    Configuration::updateValue(self::PREFIX . "{$service_prefix}", $params['carrier']->id);
                }
            }
        }
    }
}

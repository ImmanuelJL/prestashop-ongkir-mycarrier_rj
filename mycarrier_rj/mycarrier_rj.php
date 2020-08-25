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

if (!defined("_PS_VERSION_")) { exit; }

class mycarrier_rj extends CarrierModule {
    /**
     * PrestaShop Configuration prefix
     */
    const PREFIX = "mycarrier_rj_mcj_";

    /**
     * Configuration reference to RajaOngkir API key.
     */
    const CFGN_API_KEY = "myc_api";

    /**
     * Configuration reference to sender origin city.
     */
    const CFGN_ORIGIN_CITY = "myc_city";

    /**
     * Module's custom CustomerAddressFormat file.
     * @var string
     */
    protected $_CAF_FILE;

    /**
     * PrestaShop's custom CustomerAddressFormat file.
     * @var string
     */
    protected $_CAF_TARGET;

    /**
     * Module configuration table name.
     * @var string
     */
    protected $_FRONT_TABLE_NAME;

    /**
     * Path to carrier images.
     * @var string
     */
    protected $_IMAGE_JNE;
    protected $_IMAGE_TIKI;
    protected $_IMAGE_POS;

    /**
     * Module log file path.
     * @var string
     */
    protected $_LOG_PATH;

    /**
     * Module log file prefix.
     * @var string
     */
    protected $_LOG_PREFIX;

    /**
     * CarrierModule ID.
     * @var int
     */
    public $id_carrier;

    /**
     * PrestaShop available module hooks.
     * @var array
     */
    protected $_hooks = array(
        "actionCarrierUpdate",
    );

    /**
     * Available carriers and each carrier services and properties.
     * @var array
     */
    protected $_carriers = array(
        /* JNE */
        "JNE"  => array(
            "rj_api_courier" => "jne",
            "services"       => array(
                "OKE (Ongkos Kirim Ekonomis)" => array("prefix" => "mcj",   "rj_service" => "OKE"),
                "REG (Reguler)"               => array("prefix" => "mcj2",  "rj_service" => "REG"),
                "YES (Yakin Esok Sampai)"     => array("prefix" => "mcj3",  "rj_service" => "YES"),
                "CTC (JNE City Courier)"      => array("prefix" => "mcj31", "rj_service" => "CTC"),
                "CTCYES (JNE City Courier)"   => array("prefix" => "mcj32", "rj_service" => "CTCYES"),
            ),
        ),

        /* TIKI */
        "TIKI" => array(
            "rj_api_courier" => "tiki",
            "services"       => array(
                "REG (Reguler Service)"    => array("prefix" => "mcj5", "rj_service" => "REG"),
                "ECO (Economy Service)"    => array("prefix" => "mcj6", "rj_service" => "ECO"),
                "ONS (Over Night Service)" => array("prefix" => "mcj7", "rj_service" => "ONS"),
                // "HDS (Holiday Service)" => "mcj4",
            ),
        ),

        /* POS Indonesia */
        "POS"  => array(
            "rj_api_courier" => "pos",
            "services"       => array(
                "Paket Kilat Khusus"         => array("prefix" => "mcj9",  "rj_service" => "Paket Kilat Khusus"),
                "Express Next Day Barang"    => array("prefix" => "mcj13", "rj_service" => "Express Next Day Barang"),
                //"Surat Kilat Khusus"       => "mcj8",
                //"Express Next Day Dokumen" => "mcj10",
                //"Paket Jumbo Ekonomi"      => "mcj11",
                //"Paketpos Dangerous Goods" => "mcj12",
                //"Paketpos Valuable Goods"  => "mcj14",
            ),
        ),
    );

    public function __construct() {
        $this->name       = "mycarrier_rj";
        $this->tab        = "shipping_logistics";
        $this->version    = "1.1";
        $this->author     = "Immanuel Julianto Lasmana";
        $this->bootstrap  = TRUE;
        $this->module_key = "";

        parent::__construct();

        $this->_FRONT_TABLE_NAME = _DB_PREFIX_ . "mycarrier_rj_ijl";

        $this->_IMAGE_JNE  = dirname(__FILE__) . "/views/img/carrier.jpg";
        $this->_IMAGE_TIKI = dirname(__FILE__) . "/views/img/carrier2.jpg";
        $this->_IMAGE_POS  = dirname(__FILE__) . "/views/img/carrier3.jpg";

        $this->_LOG_PATH   = _PS_ROOT_DIR_ . "/app/logs/";
        $this->_LOG_PREFIX = "mycarrier-rj";

        $this->_CAF_FILE = dirname(__FILE__) . "/controllers/front/CustomerAddressFormatter.php";
        $this->_CAF_TARGET = _PS_OVERRIDE_DIR_."/classes/form/CustomerAddressFormatter.php";

        $this->displayName = $this->l("My Carrier RJ");
        $this->description = $this->l("My Carrier RJ, hitung ongkir jasa pengiriman barang "
                                      . "atau ekspedisi via Jalur Nugraha Ekakurir (JNE), "
                                      . "TIKI (Citra Van Titipan Kilat), POS (POS Indonesia). "
                                      . "Menggunakan API dari https://rajaongkir.com. Module "
                                      . "untuk prestashop GRATIS buatan Immanuel Julianto "
                                      . "Lasmana, untuk dokumentasi cek "
                                      . "https://soft-gain.com/2019/02/07/module-cek-ongkir-jne-tiki-pos-prestashop/");

        $this->confirmUninstall = $this->l("Are you sure you want to uninstall {$this->displayName} module?");

        if (! Configuration::get(self::CFGN_API_KEY)) {
            $this->warning = $this->l("No RajaOngkir API key provided.");
        }

        if (! Configuration::get(self::CFGN_ORIGIN_CITY)) {
            $this->warning = $this->l("No origin city provided.");
        }
    }

    /**
     * Install this module, extends Module::install().
     * @see Module::install()
     * @return bool
     */
    public function install() {
        if (parent::install()) {
            foreach ($this->_hooks as $hook) {
                if (! $this->registerHook($hook)) { return FALSE; }
            }

            if (! $this->installDB())      { return FALSE; }
            if (! $this->createCarriers()) { return FALSE; }

            // Set our custom CustomerAddressFormatter
            copy($this->_CAF_FILE, $this->_CAF_TARGET);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Installs module database.
     * @see mycarrier_rj::install()
     * @return bool
     */
    protected function installDB() {
        $mysql_engine = _MYSQL_ENGINE_;
        $queries = array(
            // TODO: This module stores its configurations in two ways: 
            //       PrestaShop Configuration and MySQL database.
            //       Probably we can let PrestaShop stores module 
            //       configuration so we don't need to manage them.
            "CREATE TABLE IF NOT EXISTS `{$this->_FRONT_TABLE_NAME}` (
                `id_mycarrier_rj_ijl` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT,
                `api_key` TEXT,
                `from_city` TEXT,
                `date_upd` DATETIME NULL,
                PRIMARY KEY (`id_mycarrier_rj_ijl`)
            ) ENGINE = {$mysql_engine} DEFAULT CHARSET = utf8",
        );

        foreach ($queries as $query) {
            if (! Db::getInstance()->Execute($query)) { return FALSE; }
        }

        return TRUE;
    }

    /**
     * Imports module supported carriers and carrier services to 
     * PrestaShop's configuration.
     * @see mycarrier_rj::install()
     * @return bool
     */
    protected function createCarriers() {
        $query = "INSERT INTO `{$this->_FRONT_TABLE_NAME}` (api_key, from_city)
                  VALUES ('xxxxxxxxxxxxxxxxxxxx', 'Jakarta Utara')";
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

                $carrier->delay[Configuration::get("PS_LANG_DEFAULT")] = $service_name;

                if ($carrier->add()) {
                    $groups = Group::getGroups(true);
                    foreach ($groups as $group) {
                        $group_id = $group["id_group"];

                        $query = "INSERT INTO " . _DB_PREFIX_ . "carrier_group (id_carrier, id_group)
                                  VALUES ('{$carrier->id}', '{$group_id}')";
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
                             VALUES ('{$carrier->id}', '{$zone_id}')",
                            "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                             VALUES ('{$carrier->id}', '{$rangePrice->id}', '" . NULL . "', '{$zone_id}', '25')",
                            "INSERT INTO " . _DB_PREFIX_ . "delivery (id_carrier, id_range_price, id_range_weight, id_zone, price)
                             VALUES ('{$carrier->id}', '" . NULL . "', '{$rangeWeight->id}', '{$zone_id}', '25')",
                        );

                        foreach ($zone_queries as $zone_query) { Db::getInstance()->Execute($zone_query); }
                    }

                    $carrier_id = (int) $carrier->id;
                    
                    if ($carrier_name == "JNE") {
                        copy($this->_IMAGE_JNE, _PS_SHIP_IMG_DIR_ . "/{$carrier_id}.jpg");
                    }

                    if ($carrier_name == "TIKI") {
                        copy($this->$_IMAGE_TIKI, _PS_SHIP_IMG_DIR_ . "/{$carrier_id}.jpg");
                    }

                    if ($carrier_name == "POS") {
                        copy($this->$_IMAGE_POS, _PS_SHIP_IMG_DIR_ . "/{$carrier_id}.jpg");
                    }

                    Configuration::updateValue(self::PREFIX . $value, $carrier->id);
                    Configuration::updateValue(self::PREFIX . "{$value}_reference", $carrier->id);
                }
            }
        }

        return TRUE;
    }

    /**
     * Uninstalls this module, extends Module::uninstall().
     * @see Module::uninstall()
     * @return bool
     */
    public function uninstall() {
        if (parent::uninstall()) {
            foreach ($this->_hooks as $hook) {
                if (! $this->unregisterHook($hook)) { return FALSE; }
            }

            if (!$this->uninstallDB()) { return FALSE; }
            if (!$this->deleteCarriers()) { return FALSE; }

            unlink($this->_CAF_TARGET);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Uninstalls module database.
     * @see mycarrier_rj::uninstall()
     * @return bool
     */
    protected function uninstallDB() {
        $queries = array(
            "DROP TABLE IF EXISTS `{$this->_FRONT_TABLE_NAME}`",
        );

        foreach ($queries as $query) {
            if (! Db::getInstance()->Execute($query)) { return FALSE; }
        }

        return TRUE;
    }

    /**
     * Removes module supported carriers and carrier services from 
     * PrestaShop's configuration.
     * @see mycarrier_rj::uninstall()
     * @return bool
     */
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

    /**
     * Loads and processes module settings page.
     * @see mycarrier_rj::displayForm()
     * @return string
     */
    public function getContent() {
        $output = NULL;

        if (Tools::isSubmit("submit".$this->name)) {
            $api_key = strval(Tools::getValue(self::CFGN_API_KEY));
            $origin_city = strval(Tools::getValue(self::CFGN_ORIGIN_CITY));

            if (! $api_key
                OR empty($api_key)
                OR ! Validate::isGenericName($api_key)

                OR ! $origin_city
                OR empty($origin_city)
                OR ! Validate::isGenericName($origin_city)) {
                $output .= $this->displayError($this->l("Invalid configuration value!"));
            } else {
                Configuration::updateValue(self::CFGN_API_KEY, $api_key);
                Configuration::updateValue(self::CFGN_ORIGIN_CITY, $origin_city);

                $query = "UPDATE `{$this->_FRONT_TABLE_NAME}`
                          SET api_key='{$api_key}', from_city='{$origin_city}'
                          WHERE id_mycarrier_rj_ijl = 1";
                Db::getInstance()->Execute($query);

                $output .= $this->displayConfirmation($this->l("Settings updated."));
            }
        }
        return $output . $this->displayForm();
    }

    /**
     * Displays form on module settings.
     * @return string
     */
    public function displayForm() {
        $default_lang = (int) Configuration::get("PS_LANG_DEFAULT");

        $options = array();
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);
        $responseCity = json_decode($responseCity);

        foreach ($responseCity->rajaongkir->results as $key) {
            $options[] = array(
                "id"   => $key->city_name,
                "name" => $key->city_name
            );
        }

        $fields_form[0]["form"] = array(
            "legend" => array(
                "title" => $this->l("My Carrier Settings"),
            ),
            "input" => array(
                array(
                    "type"     => "text",
                    "label"    => $this->l("API Key"),
                    "desc"     => $this->l("RajaOngkir API Key (see https://rajaongkir.com/dokumentasi#aturan-penggunaan)."),
                    "name"     => self::CFGN_API_KEY,
                    "size"     => 50,
                    "required" => true
                ),
                array(
                    "type"     => "select",
                    "label"    => $this->l("Origin City"),
                    "desc"     => $this->l("Choose origin city."),
                    "name"     => self::CFGN_ORIGIN_CITY,
                    "required" => true,
                    "options"  => array(
                        "query" => $options,
                        "id"    => "id",
                        "name"  => "name"
                    )
                ),
            ),
            "submit" => array(
                "title" => $this->l("Save"),
                "class" => "btn btn-default pull-right"
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite("AdminModules");
        $helper->currentIndex = AdminController::$currentIndex . "&configure={$this->name}";

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = "submit" . $this->name;
        $helper->toolbar_btn = array(
            "save" => array(
                "desc" => $this->l("Save"),
                "href" => AdminController::$currentIndex . "&configure={$this->name}&save{$this->name}&token=" . Tools::getAdminTokenLite("AdminModules"),
            ),
            "back" => array(
                "desc" => $this->l("Back to list"),
                "href" => AdminController::$currentIndex . "&token=" . Tools::getAdminTokenLite("AdminModules")
            )
        );

        $helper->fields_value[self::CFGN_API_KEY] = Configuration::get(self::CFGN_API_KEY);
        $helper->fields_value[self::CFGN_ORIGIN_CITY] = Configuration::get(self::CFGN_ORIGIN_CITY);

        return $helper->generateForm($fields_form);
    }

    /**
     * Calculate order shipping cost depending on the ranges set.
     * @see CarrierModule::getOrderShippingCost()
     * @return mixed
     */
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
                    $carrier_flag = $l_carrier_properties["rj_api_courier"];
                    $carrier_name = $l_carrier_name;
                    $service_name = $l_service_name;
                    $service_flag = $l_service_properties["rj_service"];

                    // we found our data, just break.
                    break;
                }
            }
            if (! is_null($service_flag)) { break; /* domino effect from inner foreach */ }
        }

        if (is_null($service_flag)) { return FALSE; } // we don"t have service and carrier data here.

        // question is, do we need to check other flags, i.e. $carrier_flag, etc

        // RajaOngkir API returns in grams, while front-end input is in kilograms
        // so we're now processing in grams.
        $weight = (float) $this->context->cart->getTotalWeight($this->context->cart->getProducts()) * 1000.0;
        $weight = max($weight, 1000.00); // minimum weight is 1kg

        $sqlMyCarrier = "SELECT * FROM `{$this->_FRONT_TABLE_NAME}` WHERE id_mycarrier_rj_ijl = 1";
        $rowMyCarrier = Db::getInstance()->getRow($sqlMyCarrier);

        $address = new Address($this->context->cart->id_address_delivery);
        $state = new State();

        $from = $rowMyCarrier["from_city"];
        $to = $state::getNameById($address->id_state);

        // TODO: City database should be pre-fetched from RajaOngkir API and probably cached
        // in our database.
        $responseCity = file_get_contents("controllers/front/city-ojb.json", FILE_USE_INCLUDE_PATH);
        $responseCity = json_decode($responseCity);

        $originCity = NULL;
        $destinationCity = NULL;

        foreach ($responseCity->rajaongkir->results as $key) {
            if ($key->city_name == $from) { $originCity = $key->city_id; }

            if (preg_match_all("/\(([^\]]*)\)/", $to, $matches)) {
                if ($matches[1][0] == "Kota") {
                    $temp = explode("(", $to);
                    if (trim($temp[0]) == $key->city_name AND $key->type == "Kota") { $destinationCity = $key->city_id; }
                }
            } elseif ($key->city_name == $to) { $destinationCity = $key->city_id; }
            else { continue; }

            if (! is_null($originCity) AND ! is_null($destinationCity)) { break; }
        }

        // cannot calculate if there's no origin/destination.
        if (is_null($originCity) OR is_null($destinationCity)) { return FALSE; }

        // we got origin and destination.

        // TODO: think of a better way on caching RajaOngkir API results.
        $cache_id = "ShoppingCost::{$carrier_flag}::{$originCity}_{$destinationCity}_{$weight}";
        // TODO: rather than caching via PrestaShop, we prefer caching to MySQL table, because not all
        //       installations, sane or not, have enabled cache.
        //       But, some installations would have limited MySQL storage size, so we should manage it carefully.
        if (! Cache::isStored($cache_id)) {
            $roa = $this->checkRajaOngkirApi($rowMyCarrier["api_key"], $originCity, $destinationCity, $weight, $carrier_flag);
            $response = $roa["response"];
            $error_response = $roa["error"];

            if ($error_response) {
                // bad things happened, and we have no shipping cost
                // better we throw something here...
                // TODO: shipping price is free if RajaOngkir API is dead.
                return FALSE;
            } else {
                // TODO: cache parsed results rather than cURL responses, reducing json_decode calls.
                Cache::store($cache_id, $response);
            }
        } else {
            $response = Cache::retrieve($cache_id);
        }

        // we got API response in $response, now we need to compare to our $service_flag to get shipping cost.
        $response_obj = json_decode($response);
        if (isset($response_obj->rajaongkir->results[0]->costs[0]->cost[0]->value)) { // just to validate our response
            foreach ($response_obj->rajaongkir->results[0]->costs as $value) {
                if ($value->service == $service_flag) { return $value->cost[0]->value; }
            }
        }

        return FALSE;
    }

    /**
     * Calculate order shipping cost ignoring ranges set. Should be the
     * same as mycarrier_rj::getOrderShippingCost because this module 
     * doesn't use the ranges either.
     * 
     * @see mycarrier_rj::getOrderShippingCost()
     * @see CarrierModule::getOrderShippingCost()
     * @return mixed
     */
    public function getOrderShippingCostExternal($params) {
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

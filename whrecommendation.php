<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WHRecommendation extends Module
{
    private $configuration;

    public function __construct()
    {
        $this->name = 'whrecommendation';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'AUTHIE Louis';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Recommendation Module');
        $this->description = $this->l('This module generates data for product recommandation');

        $this->confirmUninstall = $this->l('Do you confirm uninstall ?');
    }

    public function install(){
        $this->configuration = $this->get("prestashop.adapter.legacy.configuration");
        return parent::install() && $this->configuration->set('WHRECOMMENDATION_CRON_TOKEN', "tokenabc");
    }
}

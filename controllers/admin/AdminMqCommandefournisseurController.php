<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMqCommandefournisseurController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('@modules/mqcommandefournisseur/views/templates/admin/page.tpl');
    }
} 
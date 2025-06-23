<?php

class AdminCommandesFournisseurController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function postProcess()
    {
        if ($this->ajax) {
            $this->processAjax();
            return;
        }

        return parent::postProcess();
    }

    private function processAjax()
    {
        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'mailcontents':
                    $this->handleMailContents();
                    break;
                case 'envoimail':
                    $this->handleSendMail();
                    break;
                default:
                    $this->ajaxResponse(['erreur' => 'Action non reconnue']);
            }
        } catch (Exception $e) {
            $this->ajaxResponse(['erreur' => $e->getMessage()]);
        }
    }

    private function handleMailContents()
    {
        $products = json_decode(Tools::getValue('products'), true);
        $order_ref = Tools::getValue('order_ref');

        if (!$order_ref) {
            $this->ajaxResponse(['erreur' => 'Pas de reference de commande.']);
            return;
        }

        if (empty($products)) {
            $this->ajaxResponse(['erreur' => 'Pas de produit selectionnés']);
            return;
        }

        $content = $this->module->getTemplateContents($products, $order_ref);
        $this->ajaxResponse(['content' => $content, 'erreur' => '']);
    }

    private function handleSendMail()
    {
        $email_destinataire = Tools::getValue('destinataire');
        $contenu_mail = Tools::getValue('contenu_mail');
        $id_mail = Tools::getValue('id_mail');
        $products = json_decode(Tools::getValue('products'), true);
        $id_supplier = (int) Tools::getValue('id_supplier');
        $order_ref = Tools::getValue('order_ref');

        if (is_null($products)) {
            throw new Exception('envoimail : Pas de produits dans le decodage json');
        }

        if (!$order_ref) {
            throw new Exception('envoimail : Pas de reference de commande.');
        }

        // Appeler les méthodes du module
        $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref);
        $this->module->recordSentMail($id_supplier, $products);

        $this->ajaxResponse(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']);
    }

    private function sendMailToSupplier(string $email_destinataire, string $contenu_mail, string $order_ref): bool
    {
        $logo = Configuration::get('PS_LOGO_MAIL') ? _PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL') : '';
        $shop_url = Context::getContext()->link->getPageLink('index', true);

        return (bool) Mail::send(
            Context::getContext()->language->id,
            'commande',
            "[$order_ref] " . $this->module->l('Demande de Délai'),
            [
                '{content}' => $contenu_mail,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_logo}' => $logo,
                '{shop_url}' => $shop_url,
            ],
            $email_destinataire,
            null,
            $this->module->getMailSenderAddress(),
            $this->module->getMailSenderName(),
            null,
            null,
            _PS_ROOT_DIR_ . _MODULE_DIR_ . 'commandesfournisseuradmin/mails/fr'
        );
    }

    private function ajaxResponse($data)
    {
        $this->ajaxDie(json_encode($data));
    }
}
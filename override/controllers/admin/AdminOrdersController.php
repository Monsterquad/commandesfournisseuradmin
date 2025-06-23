<?php

class AdminOrdersController extends AdminOrdersControllerCore
{
    public function postProcess()
    {
        // Gérer les appels AJAX pour le module commandesfournisseuradmin
        if ($this->ajax && Tools::getValue('action')) {
            $action = Tools::getValue('action');
            if (in_array($action, ['mailcontents', 'envoimail'])) {
                $this->processCommandesFournisseurAjax();
                return;
            }
        }

        return parent::postProcess();
    }

    private function processCommandesFournisseurAjax()
    {
        $module = Module::getInstanceByName('commandesfournisseuradmin');
        if (!$module || !$module->active) {
            $this->ajaxDie(json_encode(['erreur' => 'Module non disponible']));
        }

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'mailcontents':
                    $this->handleMailContents($module);
                    break;
                case 'envoimail':
                    $this->handleSendMail($module);
                    break;
            }
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['erreur' => $e->getMessage()]));
        }
    }

    private function handleMailContents($module)
    {
        $products = json_decode(Tools::getValue('products'), true);
        $order_ref = Tools::getValue('order_ref');

        if (!$order_ref) {
            $this->ajaxDie(json_encode(['erreur' => 'Pas de reference de commande.']));
        }

        if (empty($products)) {
            $this->ajaxDie(json_encode(['erreur' => 'Pas de produit selectionnés']));
        }

        $content = $module->getTemplateContents($products, $order_ref);
        $this->ajaxDie(json_encode(['content' => $content, 'erreur' => '']));
    }

    private function handleSendMail($module)
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
        $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref, $module);
        $module->recordSentMail($id_supplier, $products);

        $this->ajaxDie(json_encode(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']));
    }

    private function sendMailToSupplier(string $email_destinataire, string $contenu_mail, string $order_ref, $module): bool
    {
        $logo = Configuration::get('PS_LOGO_MAIL') ? _PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL') : '';
        $shop_url = Context::getContext()->link->getPageLink('index', true);

        return (bool) Mail::send(
            Context::getContext()->language->id,
            'commande',
            "[$order_ref] " . $module->l('Demande de Délai'),
            [
                '{content}' => $contenu_mail,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_logo}' => $logo,
                '{shop_url}' => $shop_url,
            ],
            $email_destinataire,
            null,
            $module->getMailSenderAddress(),
            $module->getMailSenderName(),
            null,
            null,
            _PS_ROOT_DIR_ . _MODULE_DIR_ . 'commandesfournisseuradmin/mails/fr'
        );
    }
}
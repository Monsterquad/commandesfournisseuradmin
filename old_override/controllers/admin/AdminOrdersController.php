<?php

class AdminOrdersController extends AdminOrdersControllerCore
{
    public function postProcess()
    {
        // Vérifier si c'est un appel AJAX pour notre module
        if (Tools::getValue('ajax') && Tools::getValue('action')) {
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
        // Forcer le type de contenu JSON
        header('Content-Type: application/json');

        try {
            $module = Module::getInstanceByName('commandesfournisseuradmin');
            if (!$module || !$module->active) {
                $this->ajaxDie(json_encode(['erreur' => 'Module non disponible']));
                return;
            }

            $action = Tools::getValue('action');

            switch ($action) {
                case 'mailcontents':
                    $this->handleMailContents($module);
                    break;
                case 'envoimail':
                    $this->handleSendMail($module);
                    break;
                default:
                    $this->ajaxDie(json_encode(['erreur' => 'Action non reconnue: ' . $action]));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('CommandesFournisseur Error: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['erreur' => $e->getMessage()]));
        }
    }

    private function handleMailContents($module)
    {
        $products_json = Tools::getValue('products');
        $order_ref = Tools::getValue('order_ref');

        // Log pour debug
        PrestaShopLogger::addLog('CommandesFournisseur mailcontents - Products JSON: ' . $products_json, 1);
        PrestaShopLogger::addLog('CommandesFournisseur mailcontents - Order ref: ' . $order_ref, 1);

        if (!$order_ref) {
            $this->ajaxDie(json_encode(['erreur' => 'Pas de reference de commande.']));
            return;
        }

        $products = json_decode($products_json, true);
        if (empty($products)) {
            $this->ajaxDie(json_encode(['erreur' => 'Pas de produit selectionnés']));
            return;
        }

        try {
            $content = $module->getTemplateContents($products, $order_ref);
            $this->ajaxDie(json_encode(['content' => $content, 'erreur' => '']));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('CommandesFournisseur getTemplateContents Error: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['erreur' => 'Erreur lors de la génération du contenu: ' . $e->getMessage()]));
        }
    }

    private function handleSendMail($module)
    {
        $email_destinataire = Tools::getValue('destinataire');
        $contenu_mail = Tools::getValue('contenu_mail');
        $id_mail = Tools::getValue('id_mail');
        $products_json = Tools::getValue('products');
        $id_supplier = (int) Tools::getValue('id_supplier');
        $order_ref = Tools::getValue('order_ref');

        // Log pour debug
        PrestaShopLogger::addLog('CommandesFournisseur envoimail - Email: ' . $email_destinataire, 1);
        PrestaShopLogger::addLog('CommandesFournisseur envoimail - Products JSON: ' . $products_json, 1);

        $products = json_decode($products_json, true);
        if (is_null($products)) {
            throw new Exception('envoimail : Pas de produits dans le decodage json');
        }

        if (!$order_ref) {
            throw new Exception('envoimail : Pas de reference de commande.');
        }

        if (!$email_destinataire) {
            throw new Exception('envoimail : Pas d\'email destinataire.');
        }

        try {
            // Envoi du mail
            $result = $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref, $module);

            if (!$result) {
                throw new Exception('Erreur lors de l\'envoi du mail');
            }

            // Enregistrement en base
            $module->recordSentMail($id_supplier, $products);

            $this->ajaxDie(json_encode(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('CommandesFournisseur sendMail Error: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['erreur' => 'Erreur lors de l\'envoi: ' . $e->getMessage()]));
        }
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
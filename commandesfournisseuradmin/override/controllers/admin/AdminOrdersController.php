<?php
/**
 * AdminOrdersController.php monsterquad_17
 *
 * @author Sébastien Monterisi <contact@seb7.fr>
 */
class AdminOrdersController extends AdminOrdersControllerCore
{
    /**
     * @var Commandesfournisseuradmin
     */
    private $module;

    public function postProcess()
    {
        if (!$this->ajax) {
            return parent::postProcess();
        }

        parent::postProcess();

        $erreur = '';
        $content = '';
        try {
            /*
             * @var commandesfournisseuradmin
             */
            /* @phpstan-ignore-next-line */
            $this->module = Module::getInstanceByName('commandesfournisseuradmin');
            /* @phpstan-ignore-next-line */
            if (false === $this->module) {
                throw new Exception('Module commandesfournisseuradmin non installé.');
            }

            $action = Tools::getValue('action');
            switch ($action) {
                case 'mailcontents':
                    $products = Tools::getValue('products', []);
                    $order_ref = Tools::getValue('order_ref');

                    // --- erreurs
                    if (!$order_ref) {
                        $this->ajaxRender(json_encode(['content' => 'erreur.', 'erreur' => 'Pas de reference de commande.']), $this->controller_name);
                        Logger::AddLog('commandesfournisseuradmin AdminOrdersController pas reference de commande.');
                        break;
                    }

                    if (empty($products)) {
                        $this->ajaxRender(json_encode(['content' => 'erreur.', 'erreur' => 'Pas de produit selectionnés']), $this->controller_name);
                        Logger::AddLog('commandesfournisseuradmin AdminOrdersController pas de produits selectionnés');
                        break;
                    }

                    // --- envoi
                    /* @phpstan-ignore-next-line */
                    $this->ajaxRender(json_encode(['content' => $this->module->getTemplateContents($products, $order_ref), 'erreur' => $erreur]), $this->className);
                    break;

                case 'envoimail':
                    $email_destinataire = Tools::getValue('destinataire');
                    $contenu_mail = Tools::getValue('contenu_mail');
                    $id_mail = Tools::getValue('id_mail');
                    $products = array_map('urldecode', explode(',', Tools::getValue('products')));
                    $products = json_decode(urldecode(Tools::getValue('products')), true);
                    $id_supplier = (int) Tools::getValue('id_supplier');
                    $order_ref = Tools::getValue('order_ref');

                    try {
                        if (is_null($products)) {
                            throw new Exception('envoimail : Pas de produits dans le decodage json');
                        }

                        if (!$order_ref) {
                            throw new \Exception('envoimail : Panque reference de commande. ');
                        }

                        $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref);
                        /* @phpstan-ignore-next-line */
                        $this->module->recordSentMail($id_supplier, $products);
                        $this->ajaxRender(json_encode(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']), $this->controller_name);
                    } catch (Exception $exception) {
                        Logger::AddLog('commandesfournisseuradmin AdminOrdersController Erreur insertion sql  :  ' . $exception->getMessage());
                        $this->ajaxRender(json_encode(['content' => 'erreur.' . $id_mail, 'erreur' => 'erreur envoi mail : ' . $exception->getMessage()]), $this->controller_name);
                    }
                    exit();
//                    break;
                    // ne pas déclencher d'erreur en prod : on passe par là pour d'autres action natives.
//                default:
//                    $this->ajaxRender(json_encode(['content' => '', 'erreur' => 'Erreur logique : $action inatendue.']), $this->controller_name);
            }
        } catch (Exception $exception) {
            if (_PS_MODE_DEV_) {
                throw $exception;
            }

            Logger::AddLog('commandesfournisseuradmin AdminOrdersController :  ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * {@inheritdoc}
     * @deprecated  Remplacé par le hook
     * Overridé pour ajouter le champs 'date_commande_fournisseur' aux produits.
     */
    protected function getProducts_disabled($order): array
    {
        $products = parent::getProducts($order);

    }

    /**
     * Envoi du mail au fournisseur.
     * + copie a monsterquad.
     *
     * @param string $email_destinataire
     * @param string $contenu_mail
     * @param string $order_ref
     *
     * @return bool
     */
    private function sendMailToSupplier(string $email_destinataire, string $contenu_mail, string $order_ref): bool
    {
        $logo = Configuration::get('PS_LOGO_MAIL') ? _PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL') : '';
        $message = Swift_Message::newInstance();
        $shop_url = Context::getContext()->link->getPageLink('index', true);

        return (bool) Mail::send(
            Context::getContext()->language->id,
            'commande',
            "[$order_ref] " . $this->module->l('Demande de Délai'),
            ['{content}' => $contenu_mail,
             '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
             '{shop_logo}' => $logo ? $message->embed(Swift_Image::fromPath($logo)) : '',
             '{shop_url}' => $shop_url,
            ],
            $email_destinataire,
            null,
            $this->module->getMailSenderAddress(),
            $this->module->getMailSenderName(),
            null,
            null,
            _PS_ROOT_DIR_ . _MODULE_DIR_ . 'commandesfournisseuradmin/mails/fr',
            false,
            null,
            'service-client@monsterquad.fr', // en dur, ça fait l'affaire ici.
             $this->module->getMailSenderAddress()
        );
    }
}

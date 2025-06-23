<?php
/*
 * Module principal corrigé pour PS 8.2 avec interface fonctionnelle
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Commandesfournisseuradmin extends Module
{
    public function __construct()
    {
        $this->name = 'commandesfournisseuradmin';
        $this->tab = 'shipping_logistics';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => '8.2.99'];

        parent::__construct();

        $this->displayName = $this->l('Demandes de délai fournisseur en admin');
        $this->description = $this->l('Permet de faire des mails de demande de délai au fournisseur a partir des commandes d admin.');

        $this->version = '2.0.0';
        $this->author = 'sebastien monterisi';
        $this->author_uri = 'https://prestashop.seb7.fr';
    }

    public function install(): bool
    {
        try {
            return parent::install()
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('displayAdminOrder')
                && $this->installDb();
        } catch (\Exception $exception) {
            $message = "Impossible d'installer le module {$this->name} : " . $exception->getMessage();
            PrestaShopLogger::AddLog($message);
            $this->_errors[] = $message;
            return false;
        }
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallDb();
    }

    /**
     * Configuration du module - gère les requêtes AJAX
     */
    public function getContent()
    {
        // Vérifier si c'est une requête AJAX
        if (Tools::getValue('ajax')) {
            $this->processAjaxRequest();
            return;
        }

        // Interface de configuration normale
        return $this->displayConfirmation($this->l('Module configuré'));
    }

    public function hookDisplayBackOfficeHeader(): string
    {
        if (Context::getContext()->controller instanceof AdminOrdersController) {
            Context::getContext()->controller->addCSS($this->_path . 'views/css/admin.css');
            Context::getContext()->controller->addJS($this->_path . 'js/commandes-fournisseurs.js');

            // URL d'action pour les requêtes AJAX
            $module_url = Context::getContext()->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->name,
                'module_name' => $this->name
            ]);

            return "<script type=\"text/javascript\">var commandesfournisseur_action_url='{$module_url}&ajax=1';</script>";
        }

        return '';
    }

    public function hookDisplayAdminOrder(array $params): string
    {
        $id_order = (int) $params['id_order'];
        $order = new Order($id_order);
        $order_details = $order->getOrderDetailList();

        $dates_commandes = $this->getSupplierOrderDates($order_details);
        $productData = $this->prepareProductData($order_details, $dates_commandes);

        // Retourner l'HTML directement injecté
        $html = '
        <div id="supplier-interface-container" style="margin: 20px 0;">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-envelope-o"></i>
                    Demandes de délai fournisseur
                </div>
                <div class="panel-body">
                    <p>Sélectionnez les produits pour lesquels vous souhaitez demander un délai :</p>
                    <div id="supplier-products-list">';

        // Afficher les produits avec checkboxes
        foreach ($productData as $index => $product) {
            $checked = '';
            $checkboxName = 'mail_fournisseur[' . $product['id_product'] .
                           ($product['id_product_attribute'] ? '_' . $product['id_product_attribute'] : '') . ']';

            $html .= '
            <div class="checkbox">
                <label>
                    <input type="checkbox" 
                           name="' . $checkboxName . '"
                           data-id_product="' . $product['id_product'] . '"
                           data-id_product_attribute="' . ($product['id_product_attribute'] ?: '0') . '"
                           data-reference="' . htmlspecialchars($product['reference']) . '"
                           data-order_ref="' . htmlspecialchars($order->reference) . '"
                           ' . $checked . '>
                    ' . htmlspecialchars($product['reference']) . ' - ' . htmlspecialchars($product['name'] ?? 'Produit') . '
                    ' . ($product['date_commande_fournisseur'] ?
                        '<small class="text-muted">(Délai demandé le ' . date('d/m/Y', strtotime($product['date_commande_fournisseur'])) . ')</small>' :
                        '<small class="text-success">(Aucune demande envoyée)</small>') . '
                </label>
            </div>';
        }

        $html .= '
                    </div>
                    <div style="margin-top: 15px;">
                        <button id="passer_commande_fournisseur" class="btn btn-default" type="button">
                            <i class="icon-envelope-o"></i>
                            Faire une demande de délai
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="passer_commande_fournisseur_modal" tabindex="-1" role="dialog" style="display:none;">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" onclick="closeSupplierModal()" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">Mail fournisseur</h4>
                    </div>
                    <div class="modal-body">
                        <p>Patienter...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" onclick="closeSupplierModal()">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            console.log("Supplier interface loaded for order ' . $order->reference . '");
            
            // Initialiser les gestionnaires d\'événements
            initSupplierInterface();
        });

        function initSupplierInterface() {
            var button = document.getElementById("passer_commande_fournisseur");
            if (button) {
                button.onclick = function(e) {
                    e.preventDefault();
                    openSupplierModal();
                };
                console.log("Button event handler attached");
            } else {
                console.log("Button not found");
            }
        }

        function openSupplierModal() {
            console.log("Opening supplier modal");
            var modal = document.getElementById("passer_commande_fournisseur_modal");
            if (modal) {
                modal.style.display = "block";
                modal.style.position = "fixed";
                modal.style.zIndex = "9999";
                modal.style.left = "0";
                modal.style.top = "0";
                modal.style.width = "100%";
                modal.style.height = "100%";
                modal.style.backgroundColor = "rgba(0,0,0,0.4)";
                
                // Utiliser l\'objet commandes_fournisseurs du JS externe
                if (typeof commandes_fournisseurs !== "undefined") {
                    commandes_fournisseurs.modal = modal;
                    commandes_fournisseurs.contenu = modal.querySelector(".modal-body");
                    commandes_fournisseurs.onModalOpen();
                } else {
                    console.error("commandes_fournisseurs object not found");
                    modal.querySelector(".modal-body").innerHTML = "Erreur: Script JS non chargé";
                }
            } else {
                console.error("Modal not found");
            }
        }

        function closeSupplierModal() {
            var modal = document.getElementById("passer_commande_fournisseur_modal");
            if (modal) {
                modal.style.display = "none";
            }
        }
        </script>';

        return $html;
    }

    /**
     * Traite les requêtes AJAX directement dans le module
     */
    private function processAjaxRequest()
    {
        // Force le header JSON
        header('Content-Type: application/json');

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
                    $this->ajaxResponse(['erreur' => 'Action non reconnue: ' . $action]);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('CommandesFournisseur Error: ' . $e->getMessage(), 3);
            $this->ajaxResponse(['erreur' => $e->getMessage()]);
        }
    }

    private function handleMailContents()
    {
        $products_json = Tools::getValue('products');
        $order_ref = Tools::getValue('order_ref');

        PrestaShopLogger::addLog('CommandesFournisseur mailcontents - Products: ' . $products_json, 1);
        PrestaShopLogger::addLog('CommandesFournisseur mailcontents - Order ref: ' . $order_ref, 1);

        if (!$order_ref) {
            $this->ajaxResponse(['erreur' => 'Pas de reference de commande.']);
            return;
        }

        $products = json_decode($products_json, true);
        if (empty($products)) {
            $this->ajaxResponse(['erreur' => 'Pas de produit selectionnés']);
            return;
        }

        try {
            $content = $this->getTemplateContents($products, $order_ref);
            $this->ajaxResponse(['content' => $content, 'erreur' => '']);
        } catch (Exception $e) {
            $this->ajaxResponse(['erreur' => 'Erreur lors de la génération du contenu: ' . $e->getMessage()]);
        }
    }

    private function handleSendMail()
    {
        $email_destinataire = Tools::getValue('destinataire');
        $contenu_mail = Tools::getValue('contenu_mail');
        $id_mail = Tools::getValue('id_mail');
        $products_json = Tools::getValue('products');
        $id_supplier = (int) Tools::getValue('id_supplier');
        $order_ref = Tools::getValue('order_ref');

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
            $result = $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref);

            if (!$result) {
                throw new Exception('Erreur lors de l\'envoi du mail');
            }

            $this->recordSentMail($id_supplier, $products);

            $this->ajaxResponse(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']);
        } catch (Exception $e) {
            $this->ajaxResponse(['erreur' => 'Erreur lors de l\'envoi: ' . $e->getMessage()]);
        }
    }

    private function ajaxResponse($data)
    {
        echo json_encode($data);
        exit;
    }

    private function prepareProductData($order_details, $dates_commandes): array
    {
        $data = [];
        foreach ($order_details as $detail) {
            // Récupérer le nom du produit
            $product = new Product($detail['product_id'], false, Context::getContext()->language->id);

            $data[] = [
                'id_product' => $detail['product_id'],
                'id_product_attribute' => $detail['product_attribute_id'] ?? 0,
                'reference' => $detail['product_reference'] ?? '',
                'name' => $product->name ?? 'Produit inconnu',
                'date_commande_fournisseur' => $dates_commandes[$detail['product_reference']] ?? ''
            ];
        }
        return $data;
    }

    private function getSupplierOrderDates($order_details): array
    {
        $references = array_filter(array_map(function ($detail) {
            return $detail['product_reference'] ? "'" . pSQL($detail['product_reference']) . "'" : null;
        }, $order_details));

        if (empty($references)) {
            return [];
        }

        $query = new DbQuery();
        $query->from('commandes_fournisseurs')
            ->select('MAX(date) as date, reference')
            ->where(sprintf('reference IN(%s)', implode(',', $references)))
            ->groupBy('reference');

        $dates_commandes = Db::getInstance()->executeS($query);

        if (false === $dates_commandes) {
            return [];
        }

        return array_column($dates_commandes, 'date', 'reference');
    }

    public function getTemplateContents(array $products, string $order_ref): array
    {
        $grouped_by_supplier = [];
        foreach ($products as $product_array) {
            $product_infos = $this->getProductInfos(
                (int) $product_array['id_product'],
                (int) $product_array['id_product_attribute'],
                $product_array['reference']
            );

            $grouped_by_supplier[$product_infos['id_supplier']]['products'][] = $product_infos;
            if (!isset($grouped_by_supplier[$product_infos['id_supplier']]['supplier'])) {
                $grouped_by_supplier[$product_infos['id_supplier']]['supplier'] = $this->getSupplierInfos((int) ($product_infos['id_supplier']));
            }
        }

        array_walk($grouped_by_supplier, function (&$supplier_products) use ($order_ref) {
            $supplier_products['mail'] = $this->getMailContent($supplier_products['products']);
            $supplier_products['order']['ref'] = $order_ref;
        });

        return array_values($grouped_by_supplier);
    }

    private function getProductInfos(int $id_product, int $id_product_attribute, $reference = null)
    {
        $query = new DbQuery();
        if ($id_product_attribute) {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, pa.id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product=pl.id_product AND pl.id_lang=' . (int)Context::getContext()->language->id)
                ->leftJoin('product_attribute', 'pa', sprintf('p.id_product=pa.id_product AND pa.id_product_attribute=%s', $id_product_attribute))
                ->where(sprintf('p.id_product=%d', $id_product))
                ->where(sprintf('pa.id_product_attribute=%d', $id_product_attribute))
                ->where(sprintf("p.reference='%s'", pSQL($reference)));
        } else {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, 0 as id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product=pl.id_product AND pl.id_lang=' . (int)Context::getContext()->language->id)
                ->where(sprintf("p.reference='%s'", pSQL($reference)));
        }

        if ($r = Db::getInstance()->executeS($query)) {
            return array_shift($r);
        }

        throw new Exception('Erreurs sql : ' . $query->build() . ' : ' . Db::getInstance()->getMsgError());
    }

    private function getSupplierInfos(int $id_supplier): array
    {
        if (0 === $id_supplier) {
            return [
                'name' => 'Aucun fournisseur',
                'mail' => '',
                'id_supplier' => 0
            ];
        }

        $query = new DbQuery();
        $query->from('supplier', 's')
            ->select('s.name, s.mail, s.id_supplier')
            ->where(sprintf('id_supplier=%d', $id_supplier));

        if (!$r = Db::getInstance()->executeS($query)) {
            throw new Exception('Erreurs sql : ' . $query->build() . ' : ' . Db::getInstance()->getMsgError());
        }

        return array_shift($r);
    }

    private function getMailContent(array $products)
    {
        Context::getContext()->smarty->assign(compact('products'));
        return Context::getContext()->smarty->fetch(__DIR__ . '/views/mail.tpl');
    }

    public function getMailSenderAddress(): string
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    public function getMailSenderName(): string
    {
        return '';
    }

    private function sendMailToSupplier(string $email_destinataire, string $contenu_mail, string $order_ref): bool
    {
        $logo = Configuration::get('PS_LOGO_MAIL') ? _PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL') : '';
        $shop_url = Context::getContext()->link->getPageLink('index', true);

        return (bool) Mail::send(
            Context::getContext()->language->id,
            'commande',
            "[$order_ref] " . $this->l('Demande de Délai'),
            [
                '{content}' => $contenu_mail,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_logo}' => $logo,
                '{shop_url}' => $shop_url,
            ],
            $email_destinataire,
            null,
            $this->getMailSenderAddress(),
            $this->getMailSenderName(),
            null,
            null,
            _PS_ROOT_DIR_ . _MODULE_DIR_ . 'commandesfournisseuradmin/mails/fr'
        );
    }

    public function recordSentMail(int $id_supplier, array $products): void
    {
        $values = array_map(function ($product) {
            return "('" . pSQL($product['reference']) . "' , {$product['id_product']} , {$product['id_product_attribute']}, NOW() ) ";
        }, $products);

        $sql = sprintf(
            'INSERT INTO %scommandes_fournisseurs (`reference`, `id_product`, `id_product_attribute`, `date`) VALUES %s',
            _DB_PREFIX_,
            implode(',', $values)
        );

        if (!Db::getInstance()->execute($sql)) {
            throw new Exception("Module {$this->name} : erreur requete sql : $sql");
        }
    }

    private function installDb(): bool
    {
        try {
            $this->processSqlFile(__DIR__ . '/sql/install.sql');
        } catch (\Exception $exception) {
            $error_message = 'commandesfournisseuradmin ' . $exception->getMessage();
            PrestaShopLogger::AddLog($error_message);
            $this->_errors[] = $error_message;
            return false;
        }

        return true;
    }

    private function uninstallDb(): bool
    {
        return $this->processSqlFile(__DIR__ . '/sql/uninstall.sql');
    }

    private function processSqlFile(string $path): bool
    {
        $queries = file_get_contents($path);
        if (!$queries) {
            throw new Exception("Impossible de charger le fichier sql $path");
        }

        $queries = str_replace('{prefix}', _DB_PREFIX_, $queries);

        if (!Db::getInstance()->execute($queries)) {
            throw new Exception('Erreur execution sql : ' . Db::getInstance()->getMsgError());
        }

        return true;
    }
}
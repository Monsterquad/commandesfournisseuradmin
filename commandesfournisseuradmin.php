<?php
/*
 * Commandes fournisseur en admin
 * Permet de faire des mails de commande fournisseur a partir des commandes d'admin.
 * Version adaptée pour PrestaShop 8.2
 *
 * @author  contact@seb7.fr
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

        $this->version = '2.0.0'; // Version PS 8.2
        $this->author = 'sebastien monterisi';
        $this->author_uri = 'https://prestashop.seb7.fr';

        // Suppression de la dépendance car plus compatible PS 8.2
        // $this->dependencies = ['champmailfournisseurs'];
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

    public function hookDisplayBackOfficeHeader(): string
    {
        if (Context::getContext()->controller instanceof AdminOrdersController) {
            Context::getContext()->controller->addCSS($this->_path . 'views/css/admin.css');
            Context::getContext()->controller->addJS($this->_path . 'js/commandes-fournisseurs.js');

            $url = Context::getContext()->link->getAdminLink('AdminOrders');

            return "<script type=\"text/javascript\">var commandesfournisseur_action_url='$url';</script>";
        }

        return '';
    }

    /**
     * Hook pour l'affichage dans les commandes - Version PS 8.2
     */
    public function hookDisplayAdminOrder(array $params): string
    {
        $id_order = (int) $params['id_order'];
        $order = new Order($id_order);
        $order_details = $order->getOrderDetailList();

        // Récupérer les dates de commandes fournisseurs
        $dates_commandes = $this->getSupplierOrderDates($order_details);

        // Injecter le HTML et JavaScript pour ajouter les cases à cocher et le bouton
        return $this->generateOrderInterface($order, $order_details, $dates_commandes);
    }

    /**
     * Traite les appels AJAX
     */
    public function getAjaxResponse()
    {
        if (!Tools::getValue('ajax') || !Context::getContext()->employee) {
            return false;
        }

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'mailcontents':
                    $this->handleMailContents();
                    break;
                case 'envoimail':
                    $this->handleSendMail();
                    break;
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

        $content = $this->getTemplateContents($products, $order_ref);
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

        $this->sendMailToSupplier($email_destinataire, nl2br($contenu_mail), $order_ref);
        $this->recordSentMail($id_supplier, $products);

        $this->ajaxResponse(['content' => 'ok', 'id_mail' => $id_mail, 'erreur' => '']);
    }

    private function ajaxResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
 * Génère l'interface pour les commandes (cases à cocher + bouton + modal)
 */
private function generateOrderInterface($order, $order_details, $dates_commandes): string
{
    // Get the admin URL
    $admin_url = Context::getContext()->link->getAdminLink('AdminOrders');

    $html = '<script type="text/javascript">
    // Définir l\'URL d\'action si elle n\'existe pas
    if (typeof window.commandesfournisseur_action_url === "undefined") {
        window.commandesfournisseur_action_url = "' . $admin_url . '";
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Script loaded - adding checkboxes and button");
        
        // Ajouter les cases à cocher dans le tableau des produits
        var productTable = document.querySelector("#orderProducts, table");
        if (!productTable) {
            console.log("No product table found");
            return;
        }
        console.log("Product table found:", productTable);
        
        var headerRow = productTable.querySelector("thead tr");
        if (headerRow && !headerRow.querySelector(".mq-supplier-header")) {
            var newHeader = document.createElement("th");
            newHeader.className = "mq-supplier-header";
            newHeader.textContent = "Demande délai";
            headerRow.insertBefore(newHeader, headerRow.firstChild);
            console.log("Header added");
        }
        
        var productRows = productTable.querySelectorAll("tbody tr");
        var productData = ' . json_encode($this->prepareProductData($order_details, $dates_commandes)) . ';
        console.log("Product data:", productData);
        
        productRows.forEach(function(row, index) {
            if (productData[index] && !row.querySelector(".mq-supplier-checkbox")) {
                var newCell = document.createElement("td");
                newCell.className = "mq-supplier-checkbox";
                
                var product = productData[index];
                var checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.name = "mail_fournisseur[" + product.id_product + (product.id_product_attribute ? "_" + product.id_product_attribute : "") + "]";
                checkbox.dataset.id_product = product.id_product;
                checkbox.dataset.id_product_attribute = product.id_product_attribute || "0";
                checkbox.dataset.reference = product.reference || "";
                checkbox.dataset.order_ref = "' . $order->reference . '";
                checkbox.title = "Cocher pour inclure dans la demande de délai";
                
                newCell.appendChild(checkbox);
                row.insertBefore(newCell, row.firstChild);
                console.log("Checkbox added for row", index);
            }
        });
        
        // Forcer l\'ajout du bouton après le tableau
        setTimeout(function() {
            addSupplierOrderButtonForced();
        }, 1000);
    });
    
    function addSupplierOrderButtonForced() {
        console.log("Forcing button addition");
        
        if (document.getElementById("passer_commande_fournisseur")) {
            console.log("Button already exists");
            return;
        }
        
        // Trouver le tableau des produits et ajouter après
        var productTable = document.querySelector("#orderProducts, table");
        if (!productTable) {
            console.log("No table found for button placement");
            return;
        }
        
        // Créer le conteneur du bouton
        var buttonContainer = document.createElement("div");
        buttonContainer.className = "row-margin-bottom row-margin-top";
        buttonContainer.style.marginTop = "20px";
        buttonContainer.style.marginBottom = "20px";
        
        var buttonHtml = `
            <button id="passer_commande_fournisseur" class="btn btn-default" type="button"
                    onclick="openSupplierModal()">
                <i class="icon-envelope-o"></i>
                Faire une demande de délai
            </button>
            
            <div class="modal fade" id="passer_commande_fournisseur_modal" tabindex="-1" role="dialog" style="display:none;">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Mail fournisseur</h5>
                            <button type="button" class="close" onclick="closeSupplierModal()">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Patienter ...</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="closeSupplierModal()">Terminé</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        buttonContainer.innerHTML = buttonHtml;
        
        // Insérer après le tableau ou sa div parente
        var insertAfter = productTable.closest(".panel") || productTable.parentNode;
        insertAfter.parentNode.insertBefore(buttonContainer, insertAfter.nextSibling);
        
        console.log("Button added successfully");
    }
    
    // Définir l\'objet commandes_fournisseurs si il n\'existe pas
    if (typeof window.commandes_fournisseurs === "undefined") {
        window.commandes_fournisseurs = {
            modal: null,
            contenu: null,
            
            drawMails: function(response) {
                this.contenu.innerHTML = "";

                if (response.erreur && response.erreur.length > 0) {
                    this.contenu.innerHTML = "Erreur : " + response.erreur;
                    return;
                }

                const draw_a_mail = function(supplier_products) {
                    // Création des éléments
                    const titre = document.createElement("h4");
                    const mailText = supplier_products.supplier.mail || "MAIL NON RENSEIGNÉ";
                    titre.textContent = supplier_products.supplier.name + " (" + mailText + ")";

                    const contenu_mail = document.createElement("textarea");
                    contenu_mail.className = "form-control";
                    contenu_mail.rows = 10;
                    contenu_mail.value = supplier_products.mail;

                    const bouton_envoi = document.createElement("button");
                    bouton_envoi.textContent = "Envoyer le mail (" + supplier_products.supplier.mail + ")";
                    bouton_envoi.className = "passer_commande_fournisseur_submit btn btn-primary mt-2";
                    bouton_envoi.dataset.destinataire = supplier_products.supplier.mail;
                    bouton_envoi.dataset.id_supplier = supplier_products.supplier.id_supplier;
                    bouton_envoi.dataset.products = JSON.stringify(supplier_products.products);
                    bouton_envoi.dataset.order_ref = supplier_products.order.ref;
                    bouton_envoi.onclick = window.commandes_fournisseurs.envoimail;

                    const contenaire = document.createElement("div");
                    contenaire.className = "mb-4";
                    contenaire.dataset.uniqid = "id_" + Date.now();
                    contenaire.appendChild(titre);
                    contenaire.appendChild(contenu_mail);
                    contenaire.appendChild(bouton_envoi);

                    window.commandes_fournisseurs.contenu.appendChild(contenaire);
                };

                // Supprimer les paragraphes existants
                const paragraphs = this.contenu.querySelectorAll("p");
                paragraphs.forEach(p => p.remove());

                // Dessiner chaque mail
                response.content.forEach(draw_a_mail);
            },
            
            envoimail: function(e) {
                e.preventDefault();

                const button = e.target;
                const originalText = button.textContent;
                button.textContent = "Patienter...";
                button.disabled = true;

                const parent_div = button.parentElement;
                const textarea = parent_div.querySelector("textarea");

                fetch(window.commandesfournisseur_action_url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({
                        "destinataire": button.dataset.destinataire,
                        "contenu_mail": textarea.value,
                        "id_mail": parent_div.dataset.uniqid,
                        "id_supplier": button.dataset.id_supplier,
                        "products": button.dataset.products,
                        "order_ref": button.dataset.order_ref,
                        "action": "envoimail"
                    })
                })
                .then(response => response.json())
                .then(data => window.commandes_fournisseurs.resultat_envoi_mails(data))
                .catch(error => {
                    window.commandes_fournisseurs.contenu.innerHTML = "Erreur AJAX: " + error.message;
                    console.error("Erreur:", error);
                    button.textContent = originalText;
                    button.disabled = false;
                });

                return false;
            },
            
            resultat_envoi_mails: function(response) {
                if (!response.erreur || response.erreur.length === 0) {
                    const id_div_a_effacer = response.id_mail;
                    const div = document.querySelector(`div[data-uniqid="${id_div_a_effacer}"]`);
                    const button = div.querySelector("button");

                    div.style.opacity = "0.6";
                    button.className = "btn btn-secondary mt-2";
                    button.textContent = "Envoyé";
                    button.disabled = true;
                    return;
                }

                alert("Erreur: " + response.erreur);
            }
        };
    }
    
    // Fonctions pour la modal (sans Bootstrap)
    window.openSupplierModal = function() {
        console.log("Opening modal");
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
            
            // Déclencher l\'événement comme si Bootstrap l\'avait fait
            window.commandes_fournisseurs.modal = modal;
            window.commandes_fournisseurs.contenu = modal.querySelector(".modal-body");
            
            // Collecte des produits sélectionnés
            const checkedInputs = document.querySelectorAll("input[name^=\\"mail_fournisseur\\"]:checked");
            
            const products = Array.from(checkedInputs).map(function(input) {
                return {
                    "id_product": input.dataset.id_product,
                    "id_product_attribute": input.dataset.id_product_attribute,
                    "reference": input.dataset.reference
                };
            });
            
            if (products.length === 0) {
                window.commandes_fournisseurs.contenu.innerHTML = "Aucun produit sélectionné";
                return;
            }
            
            const order_ref = checkedInputs[0].dataset.order_ref;
            
            // Appel AJAX
            fetch(window.commandesfournisseur_action_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    "products": JSON.stringify(products),
                    "action": "mailcontents",
                    "order_ref": order_ref
                })
            })
            .then(response => response.json())
            .then(data => window.commandes_fournisseurs.drawMails(data))
            .catch(error => {
                window.commandes_fournisseurs.contenu.innerHTML = "Erreur AJAX: " + error.message;
                console.error("Erreur:", error);
            });
        }
    };
    
    window.closeSupplierModal = function() {
        var modal = document.getElementById("passer_commande_fournisseur_modal");
        if (modal) {
            modal.style.display = "none";
        }
    };
    </script>';

    return $html;
}
    /**
     * Prépare les données des produits pour JavaScript
     */
    private function prepareProductData($order_details, $dates_commandes): array
    {
        $data = [];
        foreach ($order_details as $detail) {
            $data[] = [
                'id_product' => $detail['product_id'],
                'id_product_attribute' => $detail['product_attribute_id'] ?? 0,
                'reference' => $detail['product_reference'] ?? '',
                'date_commande_fournisseur' => $dates_commandes[$detail['product_reference']] ?? ''
            ];
        }
        return $data;
    }

    /**
     * Récupère les dates de commandes fournisseurs
     */
    private function getSupplierOrderDates($order_details): array
    {
        $references = array_map(function ($detail) {
            return "'" . pSQL($detail['product_reference']) . "'";
        }, $order_details);

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

    /**
     * Contenu des mails à envoyer aux fournisseurs.
     */
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

        // construction des messages et ajout de la ref de commande
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
                ->leftJoin('product_lang', 'pl', 'p.id_product=pl.id_product')
                ->leftJoin('product_attribute', 'pa', sprintf('p.id_product=pa.id_product AND pa.id_product_attribute=%s', $id_product_attribute))
                ->where(sprintf('p.id_product=%d', $id_product))
                ->where(sprintf('pa.id_product_attribute=%d', $id_product_attribute))
                ->where(sprintf("p.reference='%s'", pSQL($reference)));
        } else {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, 0 as id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product=pl.id_product')
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
            return [];
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
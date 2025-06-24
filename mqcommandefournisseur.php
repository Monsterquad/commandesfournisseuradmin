<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MqCommandeFournisseur extends Module
{
    public function __construct()
    {
        $this->name = 'mqcommandefournisseur';
        $this->tab = 'shipping_logistics';
        $this->need_instance = 1;

        $this->displayName = $this->l('Demandes de délai fournisseur');
        $this->description = $this->l('Permet d\'envoyer des demandes de délai aux fournisseurs depuis les commandes (compatible PrestaShop 8.2).');

        $this->version = '1.0.0';
        $this->author = 'MonsterQuad';
        $this->author_uri = 'https://monsterquad.fr';
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '8.2.99'];

        parent::__construct();
    }

    public function install(): bool
    {
        try {
            return parent::install()
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('actionMailAlterMessageBeforeSend')
                && $this->registerHook('displayAdminOrderMainBottom')
                && $this->registerHook('displayAdminOrder')
                && $this->registerHook('displayAdminOrderSide')
                && $this->registerHook('displayAdminOrderTabOrder')
                && $this->installDb()
                && $this->installTab();
        } catch (\Exception $exception) {
            $message = "Impossible d'installer le module {$this->name} : " . $exception->getMessage();
            PrestaShopLogger::addLog($message, 3);
            $this->_errors[] = $message;
            return false;
        }
    }

    public function uninstall(): bool
    {
        return parent::uninstall() 
            && $this->uninstallDb() 
            && $this->uninstallTab();
    }

    /**
     * Hook pour ajouter CSS et JavaScript dans le back-office
     */
    public function hookDisplayBackOfficeHeader(): string
    {
        if (Context::getContext()->controller instanceof AdminController) {
            // Ajout du CDN jQuery si non déjà présent
            $jqueryCdn = '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
            // Ajout du JS de la modale
            $jsModule = '<script src="' . $this->_path . 'js/commandes-fournisseurs.js"></script>';
            // Ajout du CSS
            $cssModule = '<link rel="stylesheet" href="' . $this->_path . 'views/css/admin.css">';
            return $jqueryCdn . $jsModule . $cssModule;
        }
        return '';
    }

    /**
     * Hook pour afficher l'interface dans les commandes
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        if (!isset($params['id_order'])) {
            return '<script>console.log("MQ Supplier: Pas d\'ID de commande dans les params");</script>';
        }

        $order = new Order((int)$params['id_order']);
        if (!Validate::isLoadedObject($order)) {
            return '<script>console.log("MQ Supplier: Commande non trouvée ID=' . (int)$params['id_order'] . '");</script>';
        }

        // Assignment des variables pour le template
        $this->context->smarty->assign([
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'module_path' => $this->_path
        ]);

        $content = $this->display(__FILE__, 'views/templates/admin/supplier_request_interface.tpl');
        
        // Si le template est vide, créer un fallback
        if (empty(trim(strip_tags($content)))) {
            $content = $this->createFallbackInterface($order);
        }
        
        return $content;
    }

    /**
     * Hook pour l'onglet Order - autre tentative d'affichage
     */
    public function hookDisplayAdminOrderTabOrder($params)
    {
        return $this->hookDisplayAdminOrderMainBottom($params);
    }

    /**
     * Hook pour la sidebar - autre tentative d'affichage  
     */
    public function hookDisplayAdminOrderSide($params)
    {
        return $this->hookDisplayAdminOrderMainBottom($params);
    }

    /**
     * Ajoute les dates de dernière demande aux produits
     */
    private function addSupplierRequestDates(&$products)
    {
        $references = array_map(function ($product) {
            $ref = $product['product_reference'] ?? $product['reference'] ?? '';
            return "'" . pSQL($ref) . "'";
        }, $products);

        if (empty($references)) {
            return;
        }

        $query = new DbQuery();
        $query->from('mq_supplier_requests')
            ->select('MAX(date_request) as last_request, reference')
            ->where(sprintf('reference IN(%s)', implode(',', $references)))
            ->groupBy('reference');
        
        $lastRequests = Db::getInstance()->executeS($query);
        
        if ($lastRequests) {
            $lastRequests = array_column($lastRequests, 'last_request', 'reference');
            
            foreach ($products as &$product) {
                $ref = $product['product_reference'] ?? $product['reference'] ?? '';
                $product['last_supplier_request'] = $lastRequests[$ref] ?? '';
            }
        }
    }

    /**
     * Génère le contenu des emails par fournisseur
     */
    public function getEmailContent(array $products, string $orderReference): array
    {
        $groupedBySupplier = [];
        
        foreach ($products as $productData) {
            $productInfo = $this->getProductInfo(
                (int)$productData['id_product'],
                (int)($productData['id_product_attribute'] ?? 0),
                $productData['reference']
            );

            if ($productInfo && $productInfo['id_supplier'] > 0) {
                $supplierId = $productInfo['id_supplier'];
                $groupedBySupplier[$supplierId]['products'][] = $productInfo;
                
                if (!isset($groupedBySupplier[$supplierId]['supplier'])) {
                    $groupedBySupplier[$supplierId]['supplier'] = $this->getSupplierInfo($supplierId);
                }
            }
        }

        // Génération du contenu des emails
        foreach ($groupedBySupplier as &$supplierData) {
            $supplierData['email_content'] = $this->generateEmailContent($supplierData['products'], $orderReference);
            $supplierData['order_reference'] = $orderReference;
        }

        return array_values($groupedBySupplier);
    }

    /**
     * Récupère les informations d'un produit
     */
    private function getProductInfo(int $idProduct, int $idProductAttribute, string $reference): ?array
    {
        $query = new DbQuery();
        
        if ($idProductAttribute > 0) {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, pa.id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id)
                ->leftJoin('product_attribute', 'pa', 'p.id_product = pa.id_product AND pa.id_product_attribute = ' . $idProductAttribute)
                ->where('p.id_product = ' . $idProduct)
                ->where('pa.id_product_attribute = ' . $idProductAttribute);
        } else {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, 0 as id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id)
                ->where('p.id_product = ' . $idProduct);
        }

        $result = Db::getInstance()->executeS($query);
        return $result ? array_shift($result) : null;
    }

    /**
     * Récupère les informations d'un fournisseur
     */
    private function getSupplierInfo(int $idSupplier): array
    {
        $query = new DbQuery();
        $query->from('supplier', 's')
            ->select('s.name, s.mail as email, s.id_supplier')
            ->where('s.id_supplier = ' . $idSupplier);

        $result = Db::getInstance()->executeS($query);
        return $result ? array_shift($result) : [];
    }

    /**
     * Génère le contenu HTML de l'email
     */
    private function generateEmailContent(array $products, string $orderReference): string
    {
        $this->context->smarty->assign([
            'products' => $products,
            'order_reference' => $orderReference,
            'shop_name' => Configuration::get('PS_SHOP_NAME')
        ]);

        return $this->context->smarty->fetch(__DIR__ . '/views/templates/mail/supplier_request.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        // Récupérer l'ID de la commande et la référence
        $order_id = (int)$params['id_order'];
        $order = new Order($order_id);
        $order_reference = $order->reference;
        
        // Récupérer les produits avec plus de détails
        $products = $order->getOrderDetailList();
        
        // Ajouter les références produit si elles manquent
        foreach ($products as &$product) {
            if (empty($product['product_reference'])) {
                $productObj = new Product($product['product_id']);
                $product['product_reference'] = $productObj->reference;
            }
        }
        
        $this->addSupplierRequestDates($products);
        
        // Convertir en JSON pour JavaScript
        $products_json = json_encode($products);
        
        // Debug : afficher les produits dans la console
        error_log('MQ Supplier: Produits trouvés: ' . print_r($products, true));
        
                 // Créer l'interface complète directement en JavaScript
                 $actionUrl = '/admin704njnfsy/modules/mqcommandefournisseur/supplier-request/ajax';         
                 $js = '
                 <script type="text/javascript">
         window.mqOrderReference = "' . $order_reference . '";
         window.mqOrderProducts = ' . $products_json . ';
         window.mqsupplier_action_url = "' . $actionUrl . '";
        
                 console.log("MQ Supplier: hookDisplayAdminOrder - Données chargées:", {
             reference: window.mqOrderReference,
             products: window.mqOrderProducts,
             productsCount: window.mqOrderProducts ? window.mqOrderProducts.length : 0,
             ajaxUrl: window.mqsupplier_action_url
         });
         
         // Debug détaillé des produits
         if (window.mqOrderProducts && window.mqOrderProducts.length > 0) {
             console.log("MQ Supplier: Premier produit:", window.mqOrderProducts[0]);
             console.log("MQ Supplier: Clés disponibles:", Object.keys(window.mqOrderProducts[0]));
         } else {
             console.log("MQ Supplier: Aucun produit ou tableau vide");
         }
        
                 document.addEventListener("DOMContentLoaded", function() {
             console.log("MQ Supplier: Initializing admin order interface via hookDisplayAdminOrder...");
             
             // Attendre que le DOM soit chargé
             setTimeout(function() {
                 initializeMQSupplierColumns();
             }, 1500);
         });
        
        
        
        function updateMQUI() {
            var selectedCheckboxes = document.querySelectorAll(".mq-product-checkbox:checked");
            var sendButton = document.getElementById("mq-send-supplier-requests");
            var selectedCountSpan = document.getElementById("mq-selected-count");
            
            var count = selectedCheckboxes.length;
            
            if (sendButton) {
                sendButton.disabled = count === 0;
            }
            
            if (selectedCountSpan) {
                if (count === 0) {
                    selectedCountSpan.textContent = "Aucun produit sélectionné";
                    selectedCountSpan.className = "help-block";
                } else {
                    selectedCountSpan.textContent = count + " produit(s) sélectionné(s)";
                    selectedCountSpan.className = "help-block text-success";
                }
            }
        }
        
        function initializeMQSupplierColumns() {
            console.log("MQ Supplier: Adding checkboxes to product table...");
            
            // Sélectionner le tableau des produits
            var productTable = document.querySelector("#orderProductsTable");
            if (!productTable) {
                console.log("MQ Supplier: Table #orderProductsTable non trouvée");
                return;
            }
            
            console.log("MQ Supplier: Tableau trouvé, ajout des colonnes...");
            
            // Ajouter l\'en-tête de colonne en première position
            var headerRow = document.querySelector("#orderProductsTable thead tr");
            if (headerRow && !document.querySelector(".mq-supplier-header")) {
                var newHeader = document.createElement("th");
                newHeader.className = "mq-supplier-header";
                newHeader.style.width = "120px";
                newHeader.style.textAlign = "center";
                newHeader.innerHTML = `
                    <div style="text-align: center;">
                        <input type="checkbox" id="mq-select-all-orders" style="margin-bottom: 5px;" />
                        <br><small>Fournisseur</small>
                    </div>
                `;
                // Insérer en première position
                headerRow.insertBefore(newHeader, headerRow.firstChild);
                
                console.log("MQ Supplier: En-tête ajouté");
            }
            
            // Ajouter les cellules dans chaque ligne de produit
            var productRows = document.querySelectorAll("#orderProductsTable tbody tr");
            productRows.forEach(function(row, index) {
                if (!row.querySelector(".mq-supplier-cell")) {
                                         // Récupérer la référence du produit depuis la ligne
                     var reference = "";
                     var lastRequest = "Jamais";
                     
                     // Chercher la vraie référence dans .productReference
                     var referenceElement = row.querySelector(".productReference");
                     if (referenceElement) {
                         var referenceText = referenceElement.textContent.trim();
                         // Extraire ce qui vient après "Référence :"
                         var match = referenceText.match(/Référence\s*:\s*(.+)/);
                         if (match) {
                             reference = match[1].trim();
                             console.log("MQ Supplier: Référence trouvée:", reference);
                         }
                     }
                     
                     // Fallback : chercher dans les cellules comme avant
                     if (!reference) {
                         var cells = row.querySelectorAll("td");
                         for (var i = 0; i < cells.length; i++) {
                             var cellText = cells[i].textContent.trim();
                             // Pattern pour détecter une référence produit (plus strict maintenant)
                             if (cellText.length > 3 && cellText.match(/^[A-Z0-9\\-_.\/]+$/i) && cellText.indexOf(" ") === -1) {
                                 reference = cellText;
                                 console.log("MQ Supplier: Référence fallback trouvée:", reference);
                                 break;
                             }
                         }
                     }
                      
                     if (!reference) {
                         console.log("MQ Supplier: Aucune référence trouvée pour la ligne", index);
                     }
                    
                                         // Chercher dans les données produits pour la dernière demande
                     if (window.mqOrderProducts && reference) {
                         var product = null;
                         // Utiliser une boucle for compatible avec tous les navigateurs
                         for (var j = 0; j < window.mqOrderProducts.length; j++) {
                             if (window.mqOrderProducts[j].reference === reference) {
                                 product = window.mqOrderProducts[j];
                                 break;
                             }
                         }
                         if (product && product.last_supplier_request) {
                             var date = new Date(product.last_supplier_request);
                             lastRequest = date.toLocaleDateString("fr-FR") + " " + date.toLocaleTimeString("fr-FR", {hour: "2-digit", minute: "2-digit"});
                         }
                     }
                    
                    var newCell = document.createElement("td");
                    newCell.className = "mq-supplier-cell";
                    newCell.style.textAlign = "center";
                    newCell.style.verticalAlign = "middle";
                    newCell.innerHTML = `
                        <div style="padding: 5px;">
                            <input type="checkbox" 
                                   class="mq-product-checkbox" 
                                   data-reference="` + reference + `"
                                   data-row-index="` + index + `"
                                   style="margin-bottom: 5px;" />
                            <br>
                            <small class="text-muted" style="font-size: 10px;">
                                Dernière:<br>` + lastRequest + `
                            </small>
                        </div>
                    `;
                    
                    // Insérer en première position
                    row.insertBefore(newCell, row.firstChild);
                }
            });
            
            console.log("MQ Supplier: Cellules ajoutées pour", productRows.length, "produits");
            
            // Ajouter le panneau de contrôle s\'il n\'existe pas
            addControlPanelIfMissing();
            
            // Initialiser les événements
            initializeMQEvents();
        }
        
        function addControlPanelIfMissing() {
            // Vérifier si le panneau existe déjà
            if (document.getElementById("mq-supplier-request-section")) {
                console.log("MQ Supplier: Panneau de contrôle déjà présent");
                return;
            }
            
            console.log("MQ Supplier: Ajout du panneau de contrôle");
            
            // Créer le panneau de contrôle
            var panelHTML = `
                <div id="mq-supplier-request-section" class="panel panel-default" style="margin-top: 20px; background: #f8f9fa; border: 2px solid #007cba;">
                    <div class="panel-heading" style="background: #007cba; color: white;">
                        <i class="icon-envelope"></i> <strong>Demandes de délai fournisseur</strong>
                                                 <div style="float: right;">
                             <button type="button" 
                                     id="mq-send-supplier-requests" 
                                     class="btn btn-warning btn-sm" 
                                     disabled
                                     style="font-weight: bold;">
                                 <i class="icon-envelope"></i> Envoyer les demandes fournisseur
                             </button>
                         </div>
                        <div style="clear: both;"></div>
                    </div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="row">
                            <div class="col-md-6">
                                <label style="font-weight: bold; color: #007cba;">
                                    <input type="checkbox" id="mq-select-all" style="margin-right: 8px; transform: scale(1.2);" />
                                    Tout sélectionner dans le tableau ci-dessus
                                </label>
                            </div>
                            <div class="col-md-6 text-right">
                                <span id="mq-selected-count" class="help-block" style="margin: 0; font-weight: bold; color: #666;">Aucun produit sélectionné</span>
                            </div>
                        </div>
                        
                        <div class="row" style="margin-top: 10px;">
                            <div class="col-md-12">
                                <p class="help-block" style="margin: 0; font-style: italic;">
                                    ℹ️ Sélectionnez les produits dans le tableau ci-dessus en cochant les cases de la colonne "Fournisseur", puis cliquez sur le bouton pour envoyer les demandes de délai.
                                </p>
                            </div>
                        </div>
                        
                        <div id="mq-messages" class="alert" style="display: none; margin-top: 15px;"></div>
                    </div>
                </div>
            `;
            
            // Trouver où insérer le panneau
            var insertPoint = document.querySelector("#orderProductsTable");
            if (insertPoint) {
                // Insérer après le tableau des produits
                insertPoint.insertAdjacentHTML("afterend", panelHTML);
                console.log("MQ Supplier: Panneau inséré après le tableau des produits");
            } else {
                // Fallback : insérer à la fin du contenu principal
                var mainContent = document.querySelector("#content");
                if (mainContent) {
                    mainContent.insertAdjacentHTML("beforeend", panelHTML);
                    console.log("MQ Supplier: Panneau inséré à la fin du contenu");
                }
            }
        }
        
        function initializeMQEvents() {
            console.log("MQ Supplier: Initializing events...");
            
            // Gestion du "Tout sélectionner" dans le tableau
            var selectAllCheckbox = document.getElementById("mq-select-all-orders");
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener("change", function() {
                    console.log("MQ Supplier: Select all clicked:", this.checked);
                    var productCheckboxes = document.querySelectorAll(".mq-product-checkbox");
                    console.log("MQ Supplier: Found", productCheckboxes.length, "product checkboxes");
                    productCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updateMQUI();
                });
            }
            
            // Gestion du "Tout sélectionner" dans le panneau
            var selectAllPanelCheckbox = document.getElementById("mq-select-all");
            if (selectAllPanelCheckbox) {
                selectAllPanelCheckbox.addEventListener("change", function() {
                    console.log("MQ Supplier: Select all panel clicked:", this.checked);
                    var productCheckboxes = document.querySelectorAll(".mq-product-checkbox");
                    productCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllPanelCheckbox.checked;
                    });
                    // Sync avec le checkbox du tableau
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = selectAllPanelCheckbox.checked;
                    }
                    updateMQUI();
                });
                console.log("MQ Supplier: Select all du panneau connecté");
            }
            
            // Gestion des cases individuelles avec plus de debug
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("mq-product-checkbox")) {
                    console.log("MQ Supplier: Product checkbox changed:", e.target.getAttribute("data-reference"), "checked:", e.target.checked);
                    updateMQUI();
                }
            });
            
            // Aussi ajouter des listeners directs au cas où
            setTimeout(function() {
                var productCheckboxes = document.querySelectorAll(".mq-product-checkbox");
                console.log("MQ Supplier: Adding direct listeners to", productCheckboxes.length, "checkboxes");
                productCheckboxes.forEach(function(checkbox) {
                    checkbox.addEventListener("click", function() {
                        console.log("MQ Supplier: Direct click on checkbox:", this.getAttribute("data-reference"), "checked:", this.checked);
                        updateMQUI();
                    });
                });
            }, 500);
            
            var sendButton = document.getElementById("mq-send-supplier-requests");
            if (sendButton) {
                sendButton.addEventListener("click", function() {
                    console.log("MQ Supplier: Send button clicked");
                    sendSupplierRequests();
                });
                console.log("MQ Supplier: Send button connected");
            }
            
            updateMQUI();
            
            console.log("MQ Supplier: All events initialized");
        }
        
                 function updateMQUI() {
             console.log("MQ Supplier: updateMQUI called");
             var productCheckboxes = document.querySelectorAll(".mq-product-checkbox");
             var selectedCheckboxes = document.querySelectorAll(".mq-product-checkbox:checked");
             var selectAllCheckbox = document.getElementById("mq-select-all-orders");
             var selectAllPanelCheckbox = document.getElementById("mq-select-all");
             
             var count = selectedCheckboxes.length;
             var total = productCheckboxes.length;
             
             console.log("MQ Supplier: Found " + count + " selected out of " + total + " total checkboxes");
             
             // Mise à jour du checkbox global dans le tableau
             if (selectAllCheckbox && total > 0) {
                 selectAllCheckbox.indeterminate = count > 0 && count < total;
                 selectAllCheckbox.checked = count === total;
             }
             
             // Mise à jour du checkbox dans le panneau
             if (selectAllPanelCheckbox && total > 0) {
                 selectAllPanelCheckbox.indeterminate = count > 0 && count < total;
                 selectAllPanelCheckbox.checked = count === total;
             }
             
             // Mettre à jour le bouton d\'envoi
             var sendButton = document.getElementById("mq-send-supplier-requests");
             if (sendButton) {
                 sendButton.disabled = count === 0;
                 console.log("MQ Supplier: Send button disabled =", sendButton.disabled);
             }
             
             // Mettre à jour le compteur
             var countSpan = document.getElementById("mq-selected-count");
             if (countSpan) {
                 if (count === 0) {
                     countSpan.textContent = "Aucun produit sélectionné";
                     countSpan.className = "help-block";
                 } else {
                     countSpan.textContent = count + " produit(s) sélectionné(s)";
                     countSpan.className = "help-block text-success";
                 }
                 console.log("MQ Supplier: Updated count span to:", countSpan.textContent);
             } else {
                 console.log("MQ Supplier: Count span not found!");
             }
         }
        
        // Fonction d\'envoi des demandes fournisseur
        function sendSupplierRequests() {
            var selectedCheckboxes = document.querySelectorAll(".mq-product-checkbox:checked");
            var sendButton = document.getElementById("mq-send-supplier-requests");
            var messagesDiv = document.getElementById("mq-messages");
            
            if (selectedCheckboxes.length === 0) {
                showMessage("Veuillez sélectionner au moins un produit.", "warning");
                return;
            }
            
            // Vérifier que l\'URL AJAX est définie
            if (typeof window.mqsupplier_action_url === "undefined") {
                showMessage("Erreur de configuration : URL AJAX non définie", "danger");
                return;
            }
            
            // Préparation des données
            var selectedProducts = [];
            for (var i = 0; i < selectedCheckboxes.length; i++) {
                var checkbox = selectedCheckboxes[i];
                var reference = checkbox.getAttribute("data-reference");
                
                 var productData = { id_product: 0, id_product_attribute: 0 };
                 if (window.mqOrderProducts && reference) {
                    for (let j = 0; j < window.mqOrderProducts.length; j++) {
                         const product = window.mqOrderProducts[j];
                         const productRef = product.product_reference || product.reference || "";
                         
                         
                         if (productRef === reference) {
                             productData.id_product = parseInt(product.product_id || product.id_product) || 0;
                             productData.id_product_attribute = parseInt(product.product_attribute_id || product.id_product_attribute) || 0;
                             break;
                         }
                     }
                     
                     if (productData.id_product === 0) {
                         console.log("MQ Supplier: Aucune donnée produit trouvée pour référence:", reference);
                         console.log("MQ Supplier: Produits disponibles:", window.mqOrderProducts);
                     }
                 }

                 // console.log("MQ Supplier: Données produit trouvées:", productData);
                
                selectedProducts.push({
                    reference: reference,
                    id_product: productData.id_product,
                    id_product_attribute: productData.id_product_attribute
                });
            }
            
            // console.log("Envoi des demandes pour:", selectedProducts);
            
            // Désactivation du bouton
            sendButton.disabled = true;
            sendButton.innerHTML = "<i class=\\"icon-spinner icon-spin\\"></i> Envoi en cours...";
            
            // Préparation de la requête
            var formData = new FormData();
            formData.append("ajax", "1");
            formData.append("action", "sendSupplierRequests");
            formData.append("order_reference", window.mqOrderReference);
            formData.append("selected_products", JSON.stringify(selectedProducts));

            console.log(window.mqsupplier_action_url, "coucou");
            console.log(selectedProducts);

            
                         // Envoi de la requête vers l\'override AdminOrdersController
             fetch(window.mqsupplier_action_url, {
                 method: "POST",
                 body: formData
             })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showMessage(data.message, "success");
                    // Décocher tous les produits
                    for (var i = 0; i < selectedCheckboxes.length; i++) {
                        selectedCheckboxes[i].checked = false;
                    }
                    updateMQUI();
                } else {
                    showMessage(data.message || "Erreur lors de l\'envoi", "danger");
                }
            })
            .catch(function(error) {
                // console.log("MQ Supplier: Erreur de communication avec le serveur:", error);
                console.error("Erreur:", error);
                showMessage("Erreur de communication avec le serveur", "danger");
            })
            .finally(function() {
                // Réactivation du bouton
                sendButton.disabled = false;
                sendButton.innerHTML = "<i class=\\"icon-envelope\\"></i> Envoyer les demandes fournisseur";
            });
        }
        
        function showMessage(message, type) {
            var messagesDiv = document.getElementById("mq-messages");
            if (messagesDiv) {
                messagesDiv.className = "alert alert-" + type;
                messagesDiv.textContent = message;
                messagesDiv.style.display = "block";
                
                // Masquer après 5 secondes
                setTimeout(function() {
                    messagesDiv.style.display = "none";
                }, 5000);
            }
        }
        </script>';
        
        return $js;
    }

    /**
     * Enregistre l'historique des demandes
     */
    public function recordSupplierRequest(int $idSupplier, array $products): void
    {
        $values = array_map(function ($product) {
            return sprintf(
                "('%s', %d, %d, NOW())",
                pSQL($product['reference']),
                (int)$product['id_product'],
                (int)($product['id_product_attribute'] ?? 0)
            );
        }, $products);

        $sql = sprintf(
            'INSERT INTO %smq_supplier_requests (reference, id_product, id_product_attribute, date_request) VALUES %s',
            _DB_PREFIX_,
            implode(',', $values)
        );

        if (!Db::getInstance()->execute($sql)) {
            throw new Exception("Erreur lors de l'enregistrement de l'historique : " . Db::getInstance()->getMsgError());
        }
    }

    /**
     * Personnalise les emails (supprime le nom de la boutique du sujet)
     */
    public function hookActionMailAlterMessageBeforeSend(array &$params)
    {
        $message = $params['message'];
        if (method_exists($message, 'getSubject')) {
            $subject = $message->getSubject();
            $shopName = Configuration::get('PS_SHOP_NAME');
            $subject = str_replace("[$shopName] ", '', $subject);
            $message->setSubject($subject);
            $params['message'] = $message;
        }
    }

    /**
     * Retourne l'adresse email de l'expéditeur
     */
    public function getMailSenderAddress(): string
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    /**
     * Retourne le nom de l'expéditeur
     */
    public function getMailSenderName(): string
    {
        return Configuration::get('PS_SHOP_NAME');
    }

    /**
     * Installation de la base de données
     */
    private function installDb(): bool
    {
        try {
            $this->processSqlFile(__DIR__ . '/sql/install.sql');
            return true;
        } catch (\Exception $exception) {
            PrestaShopLogger::addLog('mqcommandefournisseur: ' . $exception->getMessage(), 3);
            $this->_errors[] = $exception->getMessage();
            return false;
        }
    }

    /**
     * Désinstallation de la base de données
     */
    private function uninstallDb(): bool
    {
        try {
            $this->processSqlFile(__DIR__ . '/sql/uninstall.sql');
            return true;
        } catch (\Exception $exception) {
            PrestaShopLogger::addLog('mqcommandefournisseur: ' . $exception->getMessage(), 3);
            return false;
        }
    }

    /**
     * Exécute un fichier SQL
     */
    private function processSqlFile(string $path): bool
    {
        $queries = file_get_contents($path);
        if (!$queries) {
            throw new Exception("Impossible de charger le fichier SQL $path");
        }

        $queries = str_replace('{prefix}', _DB_PREFIX_, $queries);

        if (!Db::getInstance()->execute($queries)) {
            throw new Exception('Erreur execution SQL : ' . Db::getInstance()->getMsgError());
        }

        return true;
    }

    /**
     * Installation de l'onglet admin (caché)
     */
    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminMqSupplierRequest';
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'MQ Supplier Request';
        }
        $tab->id_parent = -1; // Tab caché
        $tab->module = $this->name;
        
        return $tab->add();
    }

    /**
     * Désinstallation de l'onglet admin
     */
    private function uninstallTab(): bool
    {
        $idTab = (int)Tab::getIdFromClassName('AdminMqSupplierRequest');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return true;
    }









    /**
     * Récupère les informations d'un produit pour la modal
     */
    private function getProductInfoForModal($idProduct, $idProductAttribute = 0, $reference = '')
    {
        $sql = 'SELECT p.id_product, p.reference, pl.name, ps.id_supplier
                FROM ' . _DB_PREFIX_ . 'product p
                LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id . ')
                LEFT JOIN ' . _DB_PREFIX_ . 'product_supplier ps ON (p.id_product = ps.id_product)
                WHERE p.id_product = ' . (int)$idProduct;
        
        $result = Db::getInstance()->getRow($sql);
        
        if ($result) {
            return [
                'id_product' => $result['id_product'],
                'reference' => $result['reference'] ?: $reference,
                'name' => $result['name'],
                'id_supplier' => (int)$result['id_supplier'],
                'id_product_attribute' => (int)$idProductAttribute
            ];
        }
        
        return null;
    }

    /**
     * Interface de fallback si le template ne fonctionne pas
     */
    private function createFallbackInterface($order)
    {
        return '
        <div class="panel panel-default" style="margin-top: 20px; background: #f8f9fa; border: 2px solid #007cba;">
            <div class="panel-heading" style="background: #007cba; color: white;">
                <i class="icon-envelope"></i> <strong>Demandes de délai fournisseur (Fallback)</strong>
                <button type="button" 
                        id="mq-send-supplier-requests" 
                        class="btn btn-warning btn-sm pull-right" 
                        disabled
                        style="font-weight: bold; margin-top: -5px;">
                    <i class="icon-envelope"></i> Envoyer les demandes fournisseur
                </button>
            </div>
            <div class="panel-body">
                <p>Interface de fallback - Le template principal n\'a pas pu se charger.</p>
                <div id="mq-messages" class="alert" style="display: none;"></div>
            </div>
        </div>

        <!-- Modal de fallback -->
        <div class="modal fade" id="mq_passer_commande_fournisseur_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Demandes de délai fournisseur</h4>
                    </div>
                    <div class="modal-body">
                        <p>Chargement...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // console.log("MQ Supplier: Fallback interface initializing...");
            
            var sendButton = document.getElementById("mq-send-supplier-requests");
            if (sendButton) {
                // console.log("MQ Supplier: Fallback button found, adding click handler");
                sendButton.addEventListener("click", function() {
                    // console.log("MQ Supplier: Fallback button clicked");
                    
                    // Ouvrir la modal
                    var modal = document.getElementById("mq_passer_commande_fournisseur_modal");
                    if (modal) {
                        console.log("MQ Supplier: Opening fallback modal");
                        if (typeof $ !== "undefined" && $.fn.modal) {
                            $("#mq_passer_commande_fournisseur_modal").modal("show");
                        } else {
                            modal.style.display = "block";
                            modal.classList.add("in");
                        }
                    } else {
                        alert("Modal non trouvée");
                    }
                });
                
                // Activer le bouton pour test
                sendButton.disabled = false;
            } else {
                console.error("MQ Supplier: Fallback button not found");
            }
        });
        </script>';
    }
} 
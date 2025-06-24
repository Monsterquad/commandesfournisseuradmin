<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminOrdersController extends AdminOrdersControllerCore
{
    /**
     * @var MqCommandeFournisseur
     */
    private $mqModule;

    public function postProcess()
    {
        if (!$this->ajax) {
            return parent::postProcess();
        }

        parent::postProcess();

        try {
            // Vérifier si c'est une requête pour notre module
            $action = Tools::getValue('action');
            if (!in_array($action, ['mqmailcontents', 'mqenvoimail'])) {
                return; // Laisser les autres actions au contrôleur parent
            }

            $this->mqModule = Module::getInstanceByName('mqcommandefournisseur');
            if (false === $this->mqModule) {
                throw new Exception('Module mqcommandefournisseur non installé.');
            }

            switch ($action) {
                case 'mqmailcontents':
                    $this->processMqMailContents();
                    break;
                    
                case 'mqenvoimail':
                    $this->processMqEnvoiMail();
                    break;
            }
        } catch (Exception $exception) {
            if (_PS_MODE_DEV_) {
                throw $exception;
            }

            PrestaShopLogger::addLog('mqcommandefournisseur AdminOrdersController: ' . $exception->getMessage());
            $this->ajaxRender(json_encode([
                'content' => '',
                'erreur' => 'Erreur: ' . $exception->getMessage()
            ]));
        }
    }

    /**
     * Traite la demande de contenu des emails (génération des templates)
     */
    private function processMqMailContents()
    {
        $products = Tools::getValue('products', []);
        $orderRef = Tools::getValue('order_ref');

        if (!$orderRef) {
            $this->ajaxRender(json_encode([
                'content' => '',
                'erreur' => 'Pas de référence de commande.'
            ]));
            return;
        }

        if (empty($products)) {
            $this->ajaxRender(json_encode([
                'content' => '',
                'erreur' => 'Pas de produits sélectionnés.'
            ]));
            return;
        }

        // Utiliser la méthode du module pour générer les templates
        $content = $this->mqModule->getEmailContent($products, $orderRef);
        
        $this->ajaxRender(json_encode([
            'content' => $content,
            'erreur' => ''
        ]));
    }

    /**
     * Traite l'envoi des emails aux fournisseurs
     */
    private function processMqEnvoiMail()
    {
        $emailDestinataire = Tools::getValue('destinataire');
        $contenuMail = Tools::getValue('contenu_mail');
        $idMail = Tools::getValue('id_mail');
        $products = json_decode(Tools::getValue('products'), true);
        $idSupplier = (int) Tools::getValue('id_supplier');
        $orderRef = Tools::getValue('order_ref');

        if (is_null($products)) {
            throw new Exception('Erreur décodage JSON des produits');
        }

        if (!$orderRef) {
            throw new Exception('Pas de référence de commande');
        }

        if (!$emailDestinataire) {
            throw new Exception('Pas d\'email destinataire');
        }

        try {
            // Envoi de l'email
            $success = $this->sendMailToSupplier($emailDestinataire, nl2br($contenuMail), $orderRef);
            
            if ($success) {
                // Enregistrement en base de données
                if (method_exists($this->mqModule, 'recordSupplierRequest')) {
                    $this->mqModule->recordSupplierRequest($idSupplier, $products);
                }
                
                $this->ajaxRender(json_encode([
                    'content' => 'ok',
                    'id_mail' => $idMail,
                    'erreur' => ''
                ]));
            } else {
                throw new Exception('Échec envoi email');
            }
        } catch (Exception $e) {
            $this->ajaxRender(json_encode([
                'content' => 'erreur',
                'id_mail' => $idMail,
                'erreur' => 'Erreur envoi mail: ' . $e->getMessage()
            ]));
        }
    }

    /**
     * Envoi du mail au fournisseur
     */
    private function sendMailToSupplier(string $emailDestinataire, string $contenuMail, string $orderRef): bool
    {
        $subject = "[$orderRef] Demande de délai";
        
        $templateVars = [
            '{content}' => $contenuMail,
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => $this->context->link->getPageLink('index', true),
            '{order_reference}' => $orderRef
        ];

        return Mail::Send(
            (int)Context::getContext()->language->id,
            'supplier_request',
            $subject,
            $templateVars,
            $emailDestinataire,
            null,
            $this->mqModule->getMailSenderAddress(),
            $this->mqModule->getMailSenderName(),
            null,
            null,
            _PS_MODULE_DIR_ . 'mqcommandefournisseur/mails/',
            false,
            (int)Context::getContext()->shop->id
        );
    }

    public function ajaxProcessSendSupplierRequests()
    {
        $response = ['success' => false, 'message' => ''];
        
        try {
            if (!$this->tabAccess['edit']) {
                throw new Exception('Accès refusé');
            }
            
            $orderReference = Tools::getValue('order_reference');
            $selectedProductsJson = Tools::getValue('selected_products');
            
            if (empty($orderReference) || empty($selectedProductsJson)) {
                throw new Exception('Données manquantes');
            }
            
            $selectedProducts = json_decode($selectedProductsJson, true);
            if (!is_array($selectedProducts) || empty($selectedProducts)) {
                throw new Exception('Aucun produit sélectionné');
            }
            
            // Récupérer le module
            $module = Module::getInstanceByName('mqcommandefournisseur');
            if (!$module || !$module->active) {
                throw new Exception('Module non disponible');
            }
            
            // Générer le contenu des emails par fournisseur
            $emailsData = $module->getEmailContent($selectedProducts, $orderReference);
            
            if (empty($emailsData)) {
                throw new Exception('Aucun fournisseur trouvé pour les produits sélectionnés');
            }
            
            $sentCount = 0;
            $errors = [];
            
            foreach ($emailsData as $supplierData) {
                try {
                    $supplier = $supplierData['supplier'];
                    $products = $supplierData['products'];
                    
                    if (empty($supplier['email'])) {
                        $errors[] = "Pas d'email pour le fournisseur " . ($supplier['name'] ?? 'Inconnu');
                        continue;
                    }
                    
                    $subject = "Demande de délai - Commande $orderReference";
                    $emailContent = $this->generateEmailContent($products, $orderReference, $supplier);
                    
                    // Envoi de l'email
                    $sent = Mail::Send(
                        (int)Context::getContext()->language->id,
                        'supplier_request',
                        $subject,
                        [
                            'supplier_name' => $supplier['name'],
                            'order_reference' => $orderReference,
                            'products' => $products,
                            'shop_name' => Configuration::get('PS_SHOP_NAME')
                        ],
                        $supplier['email'],
                        $supplier['name'],
                        $module->getMailSenderAddress(),
                        $module->getMailSenderName(),
                        null,
                        null,
                        dirname(__FILE__) . '/../../mails/',
                        false
                    );
                    
                    if ($sent) {
                        $sentCount++;
                        // Enregistrer l'historique
                        $module->recordSupplierRequest($supplier['id_supplier'], $products);
                    } else {
                        $errors[] = "Échec d'envoi pour " . $supplier['name'];
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Erreur pour fournisseur: " . $e->getMessage();
                }
            }
            
            if ($sentCount > 0) {
                $message = "$sentCount demande(s) envoyée(s) avec succès";
                if (!empty($errors)) {
                    $message .= ". Erreurs: " . implode(', ', $errors);
                }
                $response = ['success' => true, 'message' => $message];
            } else {
                $response = ['success' => false, 'message' => 'Aucun email envoyé. Erreurs: ' . implode(', ', $errors)];
            }
            
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    private function generateEmailContent($products, $orderReference, $supplier)
    {
        $content = "Bonjour " . ($supplier['name'] ?? '') . ",\n\n";
        $content .= "Nous vous demandons de nous confirmer le délai de livraison pour les produits suivants de la commande $orderReference :\n\n";
        
        foreach ($products as $product) {
            $content .= "- " . ($product['name'] ?? 'Produit') . " (Réf: " . ($product['reference'] ?? '') . ")\n";
        }
        
        $content .= "\nMerci de nous faire un retour rapide.\n\n";
        $content .= "Cordialement,\n";
        $content .= Configuration::get('PS_SHOP_NAME');
        
        return $content;
    }
} 
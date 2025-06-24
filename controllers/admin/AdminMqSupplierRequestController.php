<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'mqcommandefournisseur/mqcommandefournisseur.php';

class AdminMqSupplierRequestController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->module = new MqCommandeFournisseur();
        
        parent::__construct();
    }

    public function postProcess()
    {
        // Traitement des requêtes AJAX uniquement
        if (Tools::isSubmit('ajax') && Tools::isSubmit('action')) {
            $this->processAjax();
            exit;
        }
        
        parent::postProcess();
    }

    protected function processAjax()
    {
        header('Content-Type: application/json');
        
        try {
            $action = Tools::getValue('action');
            
            switch ($action) {
                case 'sendSupplierRequests':
                    $this->processSendSupplierRequests();
                    break;
                    
                default:
                    $this->ajaxRender(json_encode([
                        'success' => false,
                        'message' => 'Action non reconnue'
                    ]));
                    break;
            }
        } catch (Exception $e) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }

    protected function processSendSupplierRequests()
    {
        // Validation des données
        $orderReference = Tools::getValue('order_reference');
        $selectedProducts = json_decode(Tools::getValue('selected_products'), true);
        
        if (empty($orderReference) || empty($selectedProducts) || !is_array($selectedProducts)) {
            throw new Exception('Données invalides reçues');
        }

        // Génération du contenu des emails par fournisseur
        $supplierEmails = $this->module->getEmailContent($selectedProducts, $orderReference);
        
        if (empty($supplierEmails)) {
            throw new Exception('Aucun produit valide trouvé pour l\'envoi');
        }

        $sentEmails = [];
        $errors = [];

        // Envoi des emails
        foreach ($supplierEmails as $supplierData) {
            try {
                $success = $this->sendEmailToSupplier($supplierData);
                
                if ($success) {
                    $sentEmails[] = $supplierData['supplier']['name'];
                    
                    // Enregistrement en base de données
                    $this->module->recordSupplierRequest(
                        (int)$supplierData['supplier']['id_supplier'],
                        $supplierData['products']
                    );
                } else {
                    $errors[] = 'Échec envoi email pour ' . $supplierData['supplier']['name'];
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur pour ' . $supplierData['supplier']['name'] . ': ' . $e->getMessage();
            }
        }

        // Préparation de la réponse
        $response = [
            'success' => !empty($sentEmails),
            'sent_count' => count($sentEmails),
            'sent_suppliers' => $sentEmails,
            'errors' => $errors
        ];

        if ($response['success']) {
            $response['message'] = sprintf(
                '%d email(s) envoyé(s) avec succès aux fournisseurs : %s',
                $response['sent_count'],
                implode(', ', $sentEmails)
            );
        } else {
            $response['message'] = 'Aucun email envoyé. Erreurs : ' . implode(', ', $errors);
        }

        $this->ajaxRender(json_encode($response));
    }

    protected function sendEmailToSupplier(array $supplierData): bool
    {
        $supplier = $supplierData['supplier'];
        $emailContent = $supplierData['email_content'];
        $orderReference = $supplierData['order_reference'];

        if (empty($supplier['email'])) {
            throw new Exception("Aucun email configuré pour le fournisseur {$supplier['name']}");
        }

        // Génération du sujet
        $subject = sprintf(
            'Demande de délai pour commande %s - %s',
            $orderReference,
            Configuration::get('PS_SHOP_NAME')
        );

        // Configuration de l'email
        $templateVars = [
            '{supplier_name}' => $supplier['name'],
            '{order_reference}' => $orderReference,
            '{products_list}' => $this->generateProductsList($supplierData['products']),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_email}' => $this->module->getMailSenderAddress()
        ];

        // Envoi de l'email
        return Mail::Send(
            (int)Context::getContext()->language->id,
            'supplier_request',
            $subject,
            $templateVars,
            $supplier['email'],
            $supplier['name'],
            $this->module->getMailSenderAddress(),
            $this->module->getMailSenderName(),
            null,
            null,
            _PS_MODULE_DIR_ . 'mqcommandefournisseur/mails/',
            false,
            (int)Context::getContext()->shop->id
        );
    }

    protected function generateProductsList(array $products): string
    {
        $list = '<ul>';
        foreach ($products as $product) {
            $list .= '<li>' . htmlspecialchars($product['name']) . ' (Réf: ' . htmlspecialchars($product['reference']) . ')</li>';
        }
        $list .= '</ul>';
        
        return $list;
    }

    protected function ajaxRender($content)
    {
        echo $content;
    }
} 
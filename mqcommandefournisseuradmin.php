<?php
if (!defined('_PS_VERSION_')) {  // Correction 1: _PS_VERSION_ au lieu de PS_VERSION
    exit;
}

require_once(dirname(__FILE__) . '/src/entity/StockPermanent.php');

class Mqcommandefournisseuradmin extends Module
{
    private bool $debug = false;

    public function __construct()
    {
        $this->name = 'mqcommandefournisseuradmin';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'faivre thomas';
        $this->need_instance = 0;
        
        // Correction 2: Ajouter ps_versions_compliancy
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_
        ];
        
        parent::__construct();
        
        $this->displayName = $this->l('MQ Commande Fournisseur Admin');
        $this->description = $this->l('demande de delai + copy reference + stockPermanent + edition de quantité en admin');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayAdminOrderTop')
            && $this->registerHook('displayAdminProductsMainStepRightColumnBeforeQuantity')
            && $this->registerHook('actionAdminProductsControllerSaveAfter')
            && $this->registerHook('adminOrdersControllerGetProducts')
            && $this->runSqlFile('install.sql');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->runSqlFile('uninstall.sql');
    }

    /**
     * Hook pour l'en-tête du back-office - MEILLEUR pour charger JS/CSS
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        // Vérifier qu'on est sur la page des commandes
        $controller = $this->context->controller;

        // dump($controller);

        if ($controller instanceof AdminOrdersController ||
            strpos($controller->php_self, 'AdminOrders') !== false ||
            strpos($_SERVER['REQUEST_URI'], '/sell/orders/') !== false) {

            $this->context->controller->addJS($this->_path . 'views/js/admin-order.js');
            $this->context->controller->addJS($this->_path . 'views/js/copyButton.js');
            $this->context->controller->addJS($this->_path . 'views/js/stockPermanent.js');
            $this->context->controller->addJS($this->_path . 'views/js/editQuantity.js');

            $this->context->controller->addCSS($this->_path . 'views/css/admin-order.css');
            $this->context->controller->addCSS($this->_path . 'views/css/editQuantity.css');
        }else if ($controller instanceof AdminProductsController ||
                $controller->php_self === "AdminProducts" ||
                strpos($_SERVER['REQUEST_URI'], '/sell/catalog/products') !== false
        ) {
            $token = Tools::getAdminTokenLite('AdminModules');

            $script = '
            <script type="text/javascript">
                window.moduleConfig = {
                    moduleToken: "' . $token . '",
                };
            </script>';

            $this->context->controller->addJS($this->_path . 'views/js/editProductQuantity.js');
            $this->context->controller->addCSS($this->_path . 'views/css/editProductQuantity.css');

            echo $script;
        }
    }

    /**
     * Hook qui s'affiche en haut de la page de commande
     */
    public function hookDisplayAdminOrderTop($params)
    {
        $order_id = (int)$params['id_order'];
        $token = Tools::getAdminTokenLite('AdminModules');

        $orderProducts = $this->getOrderProductsWithAvailabilityDate($order_id);

        $productJson = json_encode($orderProducts);

        $script = '
        <script type="text/javascript">
            window.moduleConfig = {
                orderId: ' . (int)$order_id . ',
                moduleToken: "' . $token . '",
                orderProducts: ' . $productJson . ',
                moduleName: "' . $this->name . '"
            };
        </script>';

        return $script;
    }

    public function ajaxProcessSendEmail()
    {
        try {
            $text = Tools::getValue('text');
            $subject = Tools::getValue('subject');
            $receiver = Tools::getValue('receiver');

            if ($text && $subject && $receiver) {
                PrestaShopLogger::addLog('SendEmail AJAX called with: ' . json_encode([
                    'text' => $text,
                    'subject' => $subject,
                    'receiver' => $receiver,
                ]));

                $this->sendEmail($text, $subject, $receiver);
                $this->sendEmail($text, $subject, "MonsterQuad");

                die(json_encode([
                    'success' => true,
                    'message' =>'Email envoyé avec succès',
                ]));
            } else {
                die(json_encode([
                    'success' => false,
                    'message' => 'Erreur: Vérifier les champs obligatoires (text, subject, receiver)'
                ]));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('SendEmail error: ' . $e->getMessage());

            die(json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]));
        }
    }

    private function sendEmail($text, $subject, $receiver): void
    {
        try {
            $result = Mail::Send(
                1,
                "demande_de_delai",
                $subject,
                [
                    '{text}' => $text,
                ],
                $this->getSupplierName($receiver),
                $receiver,
                'service-client@monsterquad.fr',
                'MonsterQuad.fr'
            );

            PrestaShopLogger::addLog('Mail::Send result: ' . ($result ? 'TRUE' : 'FALSE'));
            return;

        } catch (Exception $e) {
            PrestaShopLogger::addLog('SendEmail error: ' . $e->getMessage());
            return;
        }
    }

    private function getSupplierName($email)
    {
        // TODO: mettre les vrai email des fournisseur
        $suppliers = [
            'MonsterQuad' => 'service-clieeddijnfierbfnt@monsterquad.fr',
            'Deltamics' => 'contact@deltamics.fr',
            'GDFrance' => 'commande@gdfrance.com',
        ];

        return $suppliers[$email] ?? 'Fournisseur';
    }

    public function getOrderProductsWithAvailabilityDate($order_id)
    {
        $order = new Order($order_id);
        $products = $order->getProducts();

        $newTab = [];

        foreach ($products as &$product) {

            $sql = 'SELECT available_date 
                    FROM ' . _DB_PREFIX_ . 'product 
                    WHERE id_product = ' . (int)$product['product_id'];

            $availableDate = Db::getInstance()->getValue($sql);

            if ($availableDate && $availableDate != '0000-00-00') {
                $product['available_date'] = $availableDate;
                $product['available_date_formatted'] = date('d/m/Y', strtotime($availableDate));
            } else {
                $product['available_date'] = null;
                $product['available_date_formatted'] = 'Non définie';
            }
            $product['product_reference'] = $product['product_reference'] ?: 'Aucune référence';

            $newProducts = [
                "product_id" => $product['product_id'],
                "reference" => $product['product_reference'],
                "date_available" => $product ? $product['available_date_formatted'] : null,
                "name" => $product['product_name'],
                "stock_permanent" => StockPermanent::getStateForProduct($product['product_id']),
            ];
            if ($newProducts){
                $newTab[]  = $newProducts;
            }
        }

        return $newTab;
    }

    /**
     * @param array{products: array<string, mixed>} $params
     *
     * @return array{products: array<string, mixed>}
     * @noinspection PhpUnused
     */
    public function hookAdminOrdersControllerGetProducts(array &$params): array
    {
        $this->addDebugLegacyLog(__FUNCTION__);

        $stocksPermanents = StockPermanent::getStateForProducts(array_column($params['products'], 'id_product', 'id_order_detail'));
        // ajout du champ 'stock_permanent', défini à 0 par défaut
        $params['products'] = array_map(
            static function (array $product) use ($stocksPermanents) {
                $product['stock_permanent'] = $stocksPermanents[$product['id_product']] ?? 0;

                return $product;
            }, $params['products']);

        return $params;
    }

    /**
     * Hook pour l'enregistrement
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function hookActionAdminProductsControllerSaveAfter(array $params): void
    {
        $this->addDebugLegacyLog('id_product : ' . Tools::getValue('id_product') . 'state : ' . Tools::getValue('mq_stockpermanent_state'));
        try {
            StockPermanent::setStateForProduct(
                (int) (Tools::getValue('id_product')),
                (int) (Tools::getValue('mq_stockpermanent_state')));
        } catch (\Exception $exception) {
            $this->addCriticalLegacyLog('Echec de l\'enregistrement du StockPermanent : ' . $exception->getMessage());

            return;
        }
    }

    /**
     * Hook pour l'affichage et la modification dans le form
     *
     * @param array{id_product: string} $params
     *
     * @return string
     */
    public function hookDisplayAdminProductsMainStepRightColumnBeforeQuantity(array $params): string
    {
        if (!$this->isSymfonyContext()) {
            throw new \Exception('Ce hook est dispo uniquement dans un contexte Symfony.');
        }

        /** @var \Twig\Environment|false $twigEnvironment */
        $twigEnvironment = $this->get('twig');
        if (false === $twigEnvironment) {
            throw new \Exception('Service Twig non dispo !');
        }

        $id_product = (int) $params['id_product'];

        return $twigEnvironment->render('@Modules/' . $this->name . '/views/templates/hook/AdminProductsMainStepRightColumnBeforeQuantity.twig', [
            'id_product' => $id_product,
            'state' => StockPermanent::getStateForProduct($id_product),
        ]);
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    private function runSqlFile(string $file): bool
    {
        if (false !== strpos($file, '/')) {
            throw new InvalidArgumentException('passer juste le nom du fichier, pas le chemin complet.');
        }

        $sql = file_get_contents(sprintf('%s/sql/%s', __DIR__, $file));
        if (!$sql) {
            throw new RuntimeException('Impossible de lire le fichier sql.');
        }

        $sql = str_replace('{prefix}', _DB_PREFIX_, $sql);

        // Séparer les requêtes par point-virgule
        $queries = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($queries as $query) {
            if (empty($query)) {
                continue;
            }

            $execute = Db::getInstance()->execute($query);
            if (!$execute || Db::getInstance()->getMsgError()) {
                throw new RuntimeException('Echec execution sql : ' . Db::getInstance()->getMsgError() . ' - Requête: ' . $query);
            }
        }

        return true;
    }

    private function isDebugMode(): bool
    {
        return $this->debug;
    }

    private function addDebugLegacyLog(string $message): void
    {
        $this->isDebugMode() && PrestaShopLogger::AddLog(sprintf('%s : %s', $this->name, $message), 1, 0, null, null, true);
    }

    public function addCriticalLegacyLog(string $message): void
    {
        PrestaShopLogger::AddLog(sprintf('%s : %s', $this->name, $message), 4, 0, null, null, true);
    }

    /**
     * pour faire l'update de quantity
     */
    public function ajaxProcessUpdateProductQuantity()
    {
        try {
            $productId = (int) Tools::getValue('id_product');
            $quantity = (int) Tools::getValue('quantity');

            // Debug : Log des valeurs reçues
            PrestaShopLogger::addLog('AJAX UpdateQuantity - Product ID: ' . $productId . ', Quantity: ' . $quantity, 1);

            if (!$productId) {
                throw new Exception('ID produit manquant');
            }

            if ($quantity < 0) {
                throw new Exception('Quantité invalide');
            }

            // Vérifier si le produit existe
            $product = new Product($productId);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Produit introuvable');
            }

            $id_shop = (int) Context::getContext()->shop->id;

            // ✅ MÉTHODE CORRIGÉE - Utiliser la méthode qui existe vraiment

            // Méthode 1 : Simple avec setQuantity
            $result1 = StockAvailable::setQuantity($productId, 0, $quantity, $id_shop);

            // Méthode 2 : Mise à jour directe avec la bonne méthode
            $stockAvailableId = StockAvailable::getStockAvailableIdByProductId($productId, 0, $id_shop);
            $result2 = false;

            if ($stockAvailableId) {
                $stock = new StockAvailable($stockAvailableId);
                if (Validate::isLoadedObject($stock)) {
                    $stock->quantity = $quantity;
                    $result2 = $stock->update();
                }
            }

            // Méthode 3 : Alternative avec getQuantityAvailableByProduct pour vérification
            $currentQuantity = StockAvailable::getQuantityAvailableByProduct($productId, 0, $id_shop);

            PrestaShopLogger::addLog('Results - setQuantity: ' . ($result1 ? 'OK' : 'FAIL') .
                                    ', directUpdate: ' . ($result2 ? 'OK' : 'FAIL') .
                                    ', currentQuantity: ' . $currentQuantity, 1);

            die(json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour',
                'debug' => [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'current_quantity' => $currentQuantity,
                    'results' => [$result1, $result2]
                ]
            ]));

        } catch (Exception $e) {
            PrestaShopLogger::addLog('AJAX UpdateQuantity ERROR: ' . $e->getMessage(), 3);

            die(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
}

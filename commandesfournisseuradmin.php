<?php

//declare(strict_types=1);
/*
 * Commandes fournisseur en admin
 * Permet de faire des mails de commande fournisseur a partir des commandes d'admin.
 *
 * Note : les templates d'admin ne sont plus mis en override.
 * Car utilisés par plusieurs modules.
 * Overrride à faire à la main.
 *
 * @author  contact@seb7.fr
 */

namespace commandesfournisseuradmin;

use commandesfournisseuradmin\override\controllers\admin\AdminOrdersController;
use Configuration;
use Context;
use Db;
use DbQuery;
use Logger;
use Module;
use PrestaShopLogger;
use Swift_Message;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Commandesfournisseuradmin extends Module
{
    const ADMIN_FILES = [
        'controllers/admin/templates/orders/helpers/view/view.tpl',
        'controllers/admin/templates/orders/_product_line.tpl',
    ];

    /** @phpstan-ignore-next-line */
    public function __construct()
    {
        $this->name = 'commandesfournisseuradmin';
        $this->tab = 'shipping_logistics';
        $this->need_instance = 1;

        $this->displayName = $this->l('Demandes de délai fournisseur en admin');

        $this->description = $this->l('Permet de faire des mails de demande de délai au fournisseur a partir des commandes d admin.');

        $this->version = '1.2.0';
        $this->author = 'sebastien monterisi';
        $this->author_uri = 'https://prestashop.seb7.fr';

        $this->dependencies = ['champmailfournisseurs'];
        parent::__construct();
    }

    public function install(): bool
    {
        try {
            return parent::install()
//                && $this->installAdminTemplates() // juste pendant le dev, plus pratique.
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('actionMailAlterMessageBeforeSend')
                && $this->registerHook('adminOrdersControllerGetProducts')
                && $this->installDb();
        } catch (\Exception $exception) {
            $message = "Impossible d'installer le module {$this->name} : " . $exception->getMessage();
            Logger::AddLog($message);
            $this->_errors[] = $message;

            return false;
        }
    }

    /**
     * @param array{products: array<string, mixed>} $params
     * @return array{products: array<string, mixed>}
     */
    public function hookAdminOrdersControllerGetProducts(array &$params): array
    {
        try {
            $references = array_map(function ($product) {
                $ref = pSQL($product['reference']);

                return "'$ref'";
            }, $params['products']);

            if (empty($references)) {
                return $params;
            }

            $query = new DbQuery();
            $query->from('commandes_fournisseurs')
                ->select('MAX(date) as date, reference')
                ->where(sprintf('reference IN(%s)', implode(',', $references)))
                ->groupBy('reference');
            $dates_commandes = Db::getInstance()->executeS($query);

            if (false === $dates_commandes) {
                throw new Exception(__FILE__ . ' : Erreur requete sql : ' . $query->build());
            }
            // references ajoutés en clés
            $dates_commandes = array_column($dates_commandes, 'date', 'reference');

            // date de commandes ajoutées au 'product'
            $params['products'] = array_map(function ($product) use ($dates_commandes) {
                $product['date_commande_fournisseur'] = $dates_commandes[$product['reference']] ?? '';

                return $product;
            }, $params['products']);
        } catch (Exception $exception) {
            PrestaShopLogger::AddLog($exception->getMessage());
        }

        return $params;
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallDb();
    }

    public function hookDisplayBackOfficeHeader(): string
    {
        if (Context::getContext()->controller instanceof AdminOrdersController) {
            Context::getContext()->controller->addCSS($this->_path . 'views/css/admin.css');

            $url = Context::getContext()->link->getAdminLink('AdminOrders');

            return "<script type=\"text/javascript\">var commandesfournisseur_action_url='$url';</script>";
        }

        return '';
    }

    /**
     * Lance la copie des vues modifiés pour l'admin
     *
     * @return bool
     *
     * @throws Exception
     */
    private function installAdminTemplates()
    {
        return array_reduce(self::ADMIN_FILES, function ($r, $file) {
            return $r && $this->installAdminTemplate($file);
        }, true);
    }

    /**
     * Copie d'une vue d'admin
     *
     * @param string $fichier
     *
     * @return bool
     */
    private function installAdminTemplate($fichier)
    {
        $file = $this->getFileInfos($fichier);

        // --- verifications ---
        // fichier source n'existe pas
        if (!file_exists($file->sourceFile)) {
            throw new Exception('Mauvaise définition interne. Fichier source inexistant. ' . $file->sourceFile);
        }

        // fichier final existe
//        if (file_exists($file->destinationFile))
//        {
//            throw new Exception('Template admin déjà overidé. ' . $file->destinationFile);
//        }

        // création dossier destination si necessaire
        if (!is_dir($file->destinationDir)) {
            if (!@mkdir($file->destinationDir, 0755, true)) {
                throw new Exception('Impossible de créer le dossier ' . $file->destinationDir);
            }
        }

        // verification ecriture dossier destination
        if (!is_writable($file->destinationDir)) {
            throw new Exception('Le dossier ' . $file->destinationDir . ' n est pas accessible en écriture.');
        }

        // --- action ---

        // verif qui n'a pas sens ici
//        if(!strpos('view', $file->sourceFile))
//        {
//            throw new \Exception("accepte uniquement la copie des viewxxx {$file->sourceFile} -> {$file->destinationFile} ");
//        }

        if (!copy($file->sourceFile, $file->destinationFile)) {
            throw new \Exception("Echec copie {$file->sourceFile} -> {$file->destinationFile}");
        }

        return true;
    }

    /**
     * Objet fichier a copier avec caractéritiques du fichiers
     *
     * @param string $file
     *
     * @return stdClass (sourceFile, sourceDir, destinationFile, destinationDir)
     */
    private function getFileInfos($file)
    {
        $path_parts = pathinfo($file);

        $_dir = $path_parts['dirname'] . '/';
        if (strpos($_dir, '/') === 0) { // sup premier slash si existe
            $_dir = substr($_dir, 1);
        }
        $_file = $path_parts['basename'];

        // destination
        $_destination_dir = _PS_OVERRIDE_DIR_ . $_dir;
        $_destination_file = $_destination_dir . $_file;

        // source
        $_source_dir = $this->getLocalPath() . 'override' . DIRECTORY_SEPARATOR . $_dir;
        $_source_file = $_source_dir . $_file;

        // assemblage dans un classe
        $return = new stdClass();
        $return->sourceFile = $_source_file;
        $return->sourceDir = $_source_dir;
        $return->destinationFile = $_destination_file;
        $return->destinationDir = $_destination_dir;

        return $return;
    }

    /**
     * Contenu des mails à envoyer aux fournisseurs.
     *
     * @param array $products
     * @param string $order_ref
     *
     * @return array<int, array<string, array>>
     *
     * @throws Exception
     * @todo completer le return type
     *
     *               [ <id_supplier> => [
     *               "products" => [
     *               0 => [
     *               "name" => "ROTULE TRIANGLE INFERIEUR"
     *               "reference" => "SC-42150-MAX-00"
     *               "id_supplier" => "4"
     *               ]
     *               1 => [...] ],
     *               "supplier" => [
     *               "name" => "DELTAMICS",
     *               "mail" => "me@deltamics.fr",
     *               "id_supplier" => 4,
     *               ],
     *               "mail" => "  Bonjour, ..."
     *               ],
     *               "order" => ["ref"],
     *
     */
    public function getTemplateContents(array $products, string $order_ref): array
    {
        $grouped_by_supplier = [];
        foreach ($products as $product_array) {
            $product_infos = $this->getProductInfos((int)$product_array['id_product'], (int)$product_array['id_product_attribute'], $product_array['reference']);

            $grouped_by_supplier[$product_infos['id_supplier']]['products'][] = $product_infos;
            if (!isset($grouped_by_supplier[$product_infos['id_supplier']]['supplier'])) {
                $grouped_by_supplier[$product_infos['id_supplier']]['supplier'] = $this->getSupplierInfos((int)($product_infos['id_supplier']));
            }
        }

        // construction des messages et ajout de la ref de commande
        array_walk($grouped_by_supplier, function (&$supplier_products) use ($order_ref) {
            $supplier_products['mail'] = $this->getMailContent($supplier_products['products']);
            $supplier_products['order']['ref'] = $order_ref;
        });

        // array_values pour simplifier la structure du json
        return array_values($grouped_by_supplier);
    }

    /**
     * Infos sur le produit
     *
     * On peut fonctionner avec une requete par produit, il y a peu de produits concernés à chaque fois.
     *
     * @param int $id_product
     * @param int $id_product_attribute
     * @param string|null $reference
     *
     * @return mixed
     *
     * @throws Exception
     * @todo le parser du ModuleDataProvider doit verifier si c'est du php70 et donc le ?$reference n'est pas toléré.
     * en enlevant le typage ça passe.
     * Le reste de la fonction est tout de même à revoir, on fait une requete sql avec $reference qui est null.
     *
     */
    private function getProductInfos(int $id_product, int $id_product_attribute, $reference = null)
    {
        $query = new DbQuery();
        if ($id_product_attribute) {
            $query->from('product', 'p')
                ->select('pl.name, p.reference, p.id_product, pa.id_product_attribute, p.id_supplier')
                ->leftJoin('product_lang', 'pl', 'p.id_product=pl.id_product')
                ->leftJoin('product_attribute', 'pa', sprintf('p.id_product=pa.id_product AND pa.id_product_attribute=%s', $id_product_attribute))->where(sprintf('p.id_product=%d', $id_product))
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

    /**
     * Infos sur un fournisseurs
     *
     * @return array ['name', 'mail', 'id_supplier']
     *
     * @throws Exception
     */
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

    /**
     * Adresse de l'expéditeur.
     * Correspond au mail indiqué dans le cahier des charges. <service-client@monsterquad.fr>
     */
    public function getMailSenderAddress(): string
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    /**
     * Supprime le nom du site du sujet.
     *
     * @param array $params
     */
    public function hookActionMailAlterMessageBeforeSend(array &$params)
    {
//        Logger::AddLog(__FUNCTION__ . $params['message'] . ' ' . uniqid());
        /**
         * @var Swift_Message $message
         */
        $message = $params['message'];
        $subject = $message->getSubject();
        $idShop = Context::getContext()->shop->id ?? 1;
        $to_replace = '%' . preg_quote('[' . Configuration::get('PS_SHOP_NAME', null, null, $idShop) . '] ') . '%';
        $subject = trim(preg_replace($to_replace, '', $subject));

        $message->setSubject($subject);

//        Logger::AddLog(__FUNCTION__ . $message . ' ' . uniqid());

        $params['message'] = $message;
    }

    /**
     * Même remarque que la fonction précédente.
     * Pas de nom d'expéditeur finalement.
     */
    public function getMailSenderName(): string
    {
        return '';
//        return Configuration::get('PS_SHOP_NAME');
    }

    private function installDb(): bool
    {
        try {
            $this->processSqlFile(__DIR__ . '/sql/install.sql');
        } catch (\Exception $exception) {
            // log erreur et stockage dans erreurs du module
            $error_message = 'commandesfournisseuradmin ' . $exception->getMessage();
            Logger::AddLog($error_message);
            $_errors[] = $error_message;

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

    /**
     * Enregistre en base de données que le mail a été envoyée pour les produits.
     *
     * @param int $id_supplier
     * @param array $products
     *
     * @throws Exception
     */
    public function recordSentMail(int $id_supplier, array $products): void
    {
//        throw new \Exception("Pas encore implementé, on va traiter id_product, id_product_attribute et reference (memêe si redondance)."); // @todo Pas encore implementé, on va traiter id_product, id_product_attribute et reference (memêe si redondance).

        $values = array_map(function ($product) {
            // (`reference`, `id_product`, `id_product_attribute`, `date`)
            return "('" . pSQL($product['reference']) . "' , {$product['id_product']} , {$product['id_product_attribute']}, NOW() ) ";
        }, $products);
        $sql = sprintf('INSERT INTO %scommandes_fournisseurs (`reference`, `id_product`, `id_product_attribute`, `date`) VALUES %s', _DB_PREFIX_, implode(',', $values));
        if (!Db::getInstance()->execute($sql)) {
            throw new Exception("Module {$this->name} : erreur requete sql : $sql");
        }
    }
}

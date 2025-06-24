<?php
namespace MqCommandefournisseur\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MailContentController extends FrameworkBundleAdminController
{
    public function mailContentAction(Request $request)
    {
        $action = $request->get('action');
        if ($action === 'mailcontents') {
            // TODO: Générer le contenu des mails pour la modale (mock pour l'instant)
            return new JsonResponse([
                'content' => [
                    [
                        'supplier' => [
                            'name' => 'Fournisseur Test',
                            'mail' => 'test@fournisseur.fr',
                            'id_supplier' => 1
                        ],
                        'mail' => 'Bonjour,\nMerci de nous indiquer le délai.',
                        'products' => [],
                        'order' => ['ref' => 'TEST123']
                    ]
                ],
                'erreur' => ''
            ]);
        } elseif ($action === 'envoimail') {
            // TODO: Envoyer le mail (mock pour l'instant)
            return new JsonResponse([
                'erreur' => '',
                'id_mail' => $request->get('id_mail')
            ]);
        }
        return new JsonResponse(['erreur' => 'Action inconnue']);
    }
} 
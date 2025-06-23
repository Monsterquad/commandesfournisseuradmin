<?php
namespace CommandesFournisseurAdmin\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

class MailContentController extends FrameworkBundleAdminController
{
    public function demoAction()
    {
        return $this->json(['content' => 'hello contr',]);
//        return new JsonResponse(['content' => 'hello contr',]);
//        return $this->render('@Modules/your-module/templates/admin/demo.html.twig');
    }
}


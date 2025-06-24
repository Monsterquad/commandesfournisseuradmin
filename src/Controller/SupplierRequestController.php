<?php
namespace Mqcommandefournisseur\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SupplierRequestController extends AbstractController
{
    public function ajaxAction(Request $request)
    {
        // Test simple
        return new JsonResponse(['success' => true, 'message' => 'Réponse AJAX OK']);
    }
}
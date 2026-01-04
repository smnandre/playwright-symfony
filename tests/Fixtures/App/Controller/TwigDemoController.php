<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class TwigDemoController extends AbstractController
{
    public function demo(): Response
    {
        return $this->render('assetmapper/demo.html.twig', [
            'title' => 'Twig Template Demo',
            'message' => 'This page is rendered using Twig templates!',
        ]);
    }
}

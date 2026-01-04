<?php

declare(strict_types=1);

namespace Playwright\Symfony\Asset;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class AssetMapperPlaywrightBridge
{
    private KernelBrowser $kernelBrowser;

    private RouterInterface $router;

    private string $assetPublicDir;

    public function __construct(
        KernelBrowser $kernelBrowser,
        RouterInterface $router,
        string $projectDir
    ) {
        $this->kernelBrowser = $kernelBrowser;
        $this->router = $router;
        $this->assetPublicDir = rtrim($projectDir, '/').'/public/';
    }

    public function handleAssetRequest(string $url): ?Response
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!$this->isAssetPath($path)) {
            return null;
        }

        $assetPath = $this->resolveAssetPath((string) $path);

        if ($assetPath && file_exists($this->assetPublicDir.$assetPath)) {
            return $this->createAssetResponse($this->assetPublicDir.$assetPath);
        }

        return $this->handleViaKernel($url);
    }

    public function handlePageRequest(string $url): Response
    {
        $request = Request::create($url);
        $this->kernelBrowser->request(
            $request->getMethod(),
            $request->getRequestUri(),
            $request->request->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );

        return $this->kernelBrowser->getResponse();
    }

    public function isAssetPath(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        $assetExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($extension, $assetExtensions, true)
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/build/');
    }

    private function resolveAssetPath(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'assets/')) {
            return $path;
        }

        if (str_starts_with($path, 'build/')) {
            return 'assets/'.substr($path, 6);
        }

        return $path;
    }

    private function createAssetResponse(string $filePath): Response
    {
        $content = @file_get_contents($filePath) ?: '';
        $response = new Response($content);

        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (isset($mimeTypes[$extension])) {
            $response->headers->set('Content-Type', $mimeTypes[$extension]);
        }

        return $response;
    }

    private function handleViaKernel(string $url): Response
    {
        $request = Request::create($url);
        $this->kernelBrowser->request(
            $request->getMethod(),
            $request->getRequestUri()
        );

        return $this->kernelBrowser->getResponse();
    }
}

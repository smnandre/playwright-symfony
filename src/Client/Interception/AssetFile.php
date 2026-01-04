<?php

declare(strict_types=1);

namespace Playwright\Symfony\Client\Interception;

/**
 * Value object representing a resolved asset file or inline asset content.
 */
final class AssetFile
{
    private ?string $path;
    private ?string $content;
    private ?int $lastModified;
    private ?int $size;
    private string $contentType;

    public function __construct(
        ?string $path,
        ?int $lastModified,
        ?int $size,
        string $contentType,
        ?string $content = null,
    ) {
        $this->path = $path;
        $this->lastModified = $lastModified;
        $this->size = $size;
        $this->contentType = $contentType;
        $this->content = $content;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function hasInlineContent(): bool
    {
        return null !== $this->content;
    }

    public function getContent(): string
    {
        if (null !== $this->content) {
            return $this->content;
        }

        if (null === $this->path) {
            return '';
        }

        return @file_get_contents($this->path) ?: '';
    }
}

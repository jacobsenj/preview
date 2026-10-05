<?php

declare(strict_types=1);

namespace F7\Preview\Controller;

/*
 * This file is part of TYPO3 CMS extension preview by F7.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use F7\Preview\Preview\PreviewUriBuilder;
use F7\Preview\Utility\PreviewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * Class PreviewController
 */
class PreviewController
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly UriBuilder $uriBuilder,
        private readonly PreviewUriBuilder $previewUriBuilder,
    ) {}

    public function addLinkAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getQueryParams();
        $pageId = (int)($body['addLink']['page'] ?? 0);
        $languageId = (int)($body['addLink']['language'] ?? 0);
        // check if link already exist
        $linkInformation = PreviewUtility::getPreviewLink($pageId, $languageId);

        if ($linkInformation === []) {
            $configuration = $this->extensionConfiguration->get('preview');
            $lifetime = (int)$configuration['lifetime'];
            $this->previewUriBuilder->generatePreviewUrl($pageId, $languageId, $lifetime);
        }

        return $this->redirectToPage($pageId);
    }

    public function removeLinkAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getQueryParams();
        $pageId = (int)($body['removeLink']['page'] ?? 0);
        $languageId = (int)($body['removeLink']['language'] ?? 0);

        PreviewUtility::removeLink($pageId, $languageId);

        return $this->redirectToPage($pageId);
    }

    private function redirectToPage(int $pageId): ResponseInterface
    {
        $uri = $this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $pageId]);
        return new RedirectResponse((string)$uri);
    }
}

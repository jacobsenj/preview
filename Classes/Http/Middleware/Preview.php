<?php

declare(strict_types=1);

namespace F7\Preview\Http\Middleware;

/*
 * This file is part of TYPO3 CMS extension review by F7.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use F7\Preview\Authentication\PreviewUserAuthentication;
use F7\Preview\Preview\PreviewUriBuilder;
use F7\Preview\Utility\PreviewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to detect "preview mode" so that a hidden language is shown in the frontend
 */
class Preview implements MiddlewareInterface
{
    public function __construct(
        protected readonly Context $context,
        protected readonly ConnectionPool $connectionPool,
    ) {}

    public const REQUEST_ATTRIBUTE = 'tx_preview';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->context->getPropertyFromAspect('backend.user', 'isLoggedIn')) {
            return $handler->handle($request);
        }

        $hash = $this->findHashInRequest($request);
        if (empty($hash)) {
            return $handler->handle($request);
        }

        $language = $request->getAttribute('language', null);
        if (!$language instanceof SiteLanguage) {
            return $handler->handle($request);
        }

        PreviewUtility::removeOutdatedLinks();

        if (!$this->verifyHash($hash, $language)) {
            return $handler->handle($request);
        }

        $this->initializePreviewUser($language, $this->findTargetPid($hash));
        $response = $handler->handle($request);

        // If the GET parameter PreviewUriBuilder::PARAMETER_NAME is set, then a cookie is set for the next request
        if ($request->getQueryParams()[PreviewUriBuilder::PARAMETER_NAME] ?? false) {
            /** @var NormalizedParams $normalizedParams */
            $normalizedParams = $request->getAttribute('normalizedParams');
            $cookie = Cookie::create(PreviewUriBuilder::PARAMETER_NAME)
                ->withValue($hash)
                ->withPath($normalizedParams->getSitePath())
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX);

            return $response->withAddedHeader('Set-Cookie', (string)$cookie);
        }
        return $response;
    }

    /**
     * Looks for the PreviewUriBuilder::PARAMETER_NAME in the QueryParams and Cookies
     *
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function findHashInRequest(ServerRequestInterface $request): string
    {
        return $request->getQueryParams()[PreviewUriBuilder::PARAMETER_NAME] ?? $request->getCookieParams()[PreviewUriBuilder::PARAMETER_NAME] ?? '';
    }

    protected function setCookie(string $inputCode, NormalizedParams $normalizedParams): void
    {
        setcookie(PreviewUriBuilder::PARAMETER_NAME, $inputCode, 0, $normalizedParams->getSitePath(), '', true, true);
    }

    /**
     * Creates a preview user and sets the current page ID (for accessing the page)
     */
    protected function initializePreviewUser(SiteLanguage $language, int $targetPid): void
    {
        $previewUser = new PreviewUserAuthentication($language);
        $previewUser->setWebmounts([$targetPid]);
        $GLOBALS['BE_USER'] = $previewUser;

        $this->setBackendUserAspect($previewUser);
    }

    /**
     * Register the backend user as aspect
     */
    protected function setBackendUserAspect(?BackendUserAuthentication $user = null): void
    {
        $this->context->setAspect(
            'backend.user',
            new UserAspect($user)
        );

    }

    /**
     * Looks for the hash in the table tx_preview
     * Must not be expired yet.
     */
    protected function verifyHash(string $hash, SiteLanguage $language): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_preview');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_preview')
            ->where(
                $queryBuilder->expr()->eq(
                    'hash',
                    $queryBuilder->createNamedParameter($hash)
                ),
                $queryBuilder->expr()->gt(
                    'endtime',
                    $queryBuilder->createNamedParameter(
                        $this->context->getPropertyFromAspect('date', 'timestamp'),
                        Connection::PARAM_INT
                    )
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (empty($row)) {
            return false;
        }

        return (int)$row['sys_language_uid'] === $language->getLanguageId();
    }

    protected function findTargetPid(string $hash): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_preview');
        return (int)$queryBuilder
            ->select('pid')
            ->from('tx_preview')
            ->where(
                $queryBuilder->expr()->eq(
                    'hash',
                    $queryBuilder->createNamedParameter($hash)
                ),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }
}

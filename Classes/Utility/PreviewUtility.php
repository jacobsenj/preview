<?php

namespace F7\Preview\Utility;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PreviewUtility
{
    public static function getPreviewLink(int $uid, int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_preview');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_preview')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (empty($row)) {
            return [];
        }
        return $row;
    }

    public static function removeOutdatedLinks(): bool
    {
        $timestamp = date('U');

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_preview');
        $queryBuilder
            ->delete('tx_preview')
            ->where(
                $queryBuilder->expr()->lte(
                    'endtime',
                    $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
        return true;
    }

    public static function removeLink(int $pageId, int $languageId): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_preview');
        $queryBuilder
            ->delete('tx_preview')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    public static function generatePreviewUrl(int $pageId, int $languageId, int $lifetime): void
    {

        $context = GeneralUtility::makeInstance(Context::class);
        $hash = md5($pageId . $languageId . uniqid(microtime(), true));
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_preview')
            ->insert(
                'tx_preview',
                [
                    'hash' => $hash,
                    'pid' => $pageId,
                    'tstamp' => $context->getPropertyFromAspect('date', 'timestamp'),
                    'endtime' => $context->getPropertyFromAspect('date', 'timestamp') + ($lifetime * 24 * 60 * 60),
                    'sys_language_uid' => $languageId,
                ]
            );
    }
}

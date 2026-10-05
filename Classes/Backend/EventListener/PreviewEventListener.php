<?php

declare(strict_types=1);

namespace F7\Preview\Backend\EventListener;

use F7\Preview\Utility\PreviewUtility;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class PreviewEventListener
{
    /**
     * Page is not published in these languages.
     *
     * @var array
     */
    protected $notPublishedLanguages = [];

    /**
     * Properties of all language variants of this page
     *
     * @var array
     */
    protected $languageProperties = [];

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly SiteFinder $siteFinder,
        private readonly ConnectionPool $connectionPool,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        // Get the current page ID
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);

        // remove all outdated preview links
        PreviewUtility::removeOutdatedLinks();

        $site = $this->siteFinder->getSiteByPageId($pageId);
        $pageIsTranslatedInLanguages = $this->getLanguageVariants($pageId, (int)($request->getAttribute('beUser')?->workspace ?? 0));

        // If all translations of this page are already published, do not render anything and leave.
        if (empty($this->notPublishedLanguages)) {
            return;
        }

        $languages = [];
        foreach ($site->getLanguages() as $language) {
            if (in_array($language->getLanguageId(), $pageIsTranslatedInLanguages) && in_array($language->getLanguageId(), $this->notPublishedLanguages)) {
                $linkInformation = PreviewUtility::getPreviewLink($pageId, $language->getLanguageId());
                if ($linkInformation === []) {
                    $actionUri = $this->uriBuilder->buildUriFromRoutePath(
                        '/tx_preview/addLink',
                        [
                            'addLink' => [
                                'page' => $pageId,
                                'language' => $language->getLanguageId(),
                            ],
                        ]
                    );
                } else {
                    $actionUri = $this->uriBuilder->buildUriFromRoutePath(
                        '/tx_preview/removeLink',
                        [
                            'removeLink' => [
                                'page' => $pageId,
                                'language' => $language->getLanguageId(),
                            ],
                        ]
                    );
                }

                $parameters = [
                    'tx_preview' => $linkInformation['hash'] ?? '',
                    '_language' => $language->getLanguageId(),
                ];

                // is the page restricted by start- and/ or endtime? Then add page id and simulate time parameter
                if ($this->languageProperties[$language->getLanguageId()]['endtime'] > 0) {
                    $parameters['id'] = $pageId;
                    $parameters['ADMCMD_simTime'] = $this->languageProperties[$language->getLanguageId()]['endtime'] - 1;
                } elseif ($this->languageProperties[$language->getLanguageId()]['starttime'] > 0) {
                    $parameters['id'] = $pageId;
                    $parameters['ADMCMD_simTime'] = $this->languageProperties[$language->getLanguageId()]['starttime'] + 1;
                }

                $languages[] = [
                    'title' => $language->getNavigationTitle(),
                    'flagIdentifier' => $language->getFlagIdentifier(),
                    'previewLink' => $linkInformation !== [],
                    'url' => (string)$site->getRouter()->generateUri($pageId, $parameters),
                    'action' => $actionUri,
                ];
            }
        }

        $this->pageRenderer->loadJavaScriptModule('@f7media/preview/Preview.js');

        $view = $this->viewFactory->create(new ViewFactoryData(
            templatePathAndFilename: 'EXT:preview/Resources/Private/Templates/Backend/Show.html',
            request: $request
        ));
        $view->assignMultiple([
            'languages' => $languages,
        ]);

        $event->addHeaderContent($view->render());
    }

    /**
     * This method returns an array with all language ids the current page is translated to.
     */
    private function getLanguageVariants(int $pageId, int $workspaceId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));
        $queryBuilder->select(
            'uid',
            $GLOBALS['TCA']['pages']['ctrl']['languageField'],
            'hidden',
            'starttime',
            'endtime'
        )
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    $GLOBALS['TCA']['pages']['ctrl']['transOrigPointerField'],
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->orWhere(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            );
        $statement = $queryBuilder->executeQuery();
        $languages = [];
        $notPublishedLanguages = [];
        $languageProperties = [];
        while ($row = $statement->fetchAssociative()) {
            if ($this->isLanguageHidden($row, date('U'))) {
                $notPublishedLanguages[] = (int)$row[$GLOBALS['TCA']['pages']['ctrl']['languageField']];
                $languageProperties[(int)$row[$GLOBALS['TCA']['pages']['ctrl']['languageField']]] = [
                    'starttime' => $row['starttime'],
                    'endtime' => $row['endtime'],
                    'hidden' => $row['hidden'],
                ];
            }
            $languages[] = (int)$row[$GLOBALS['TCA']['pages']['ctrl']['languageField']];
        }
        $this->notPublishedLanguages = $notPublishedLanguages;
        $this->languageProperties = $languageProperties;
        return $languages;
    }

    public function getBackendUser(): \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    public function isLanguageHidden(array $page, $date): bool
    {
        if (!(bool)$page['hidden']
            && !(bool)($page['starttime'] > $date)
            && !(bool)($page['endtime'] > 0 && $page['endtime'] < $date)) {
            return false;
        }

        return true;
    }
}

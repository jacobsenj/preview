<?php

declare(strict_types=1);

namespace F7\Preview\Preview;

/*
 * This file is part of TYPO3 CMS extension preview by F7.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use F7\Preview\Utility\PreviewUtility;

class PreviewUriBuilder
{
    public const PARAMETER_NAME = 'tx_preview';

    public function generatePreviewUrl(int $pageId, int $languageId, int $lifetime): void
    {
        PreviewUtility::generatePreviewUrl($pageId, $languageId, $lifetime);
    }
}

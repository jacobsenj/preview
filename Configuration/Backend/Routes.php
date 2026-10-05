<?php

/**
 * Definitions for routes provided by EXT:preview
 * Contains all "regular" routes for entry points
 */
return [
    'tx_preview_addLink' => [
        'path' => '/tx_preview/addLink',
        'target' => \F7\Preview\Controller\PreviewController::class . '::addLinkAction',
    ],
    'tx_preview_removeLink' => [
        'path' => '/tx_preview/removeLink',
        'target' => \F7\Preview\Controller\PreviewController::class . '::removeLinkAction',
    ],
];

<?php

declare(strict_types=1);

defined('TYPO3') or die();

call_user_func(function () {
    $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['preview']
        = 'EXT:preview/Resources/Public/Stylesheet/preview.css';
});

<?php

if (is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-irfaq'] = 'tcarecords-pages-contains-irfaq';
}

// Adding sysfolder icon
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][$_EXTKEY]['0']
        = 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq.sysfolder';
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][$_EXTKEY]['1'] = $_EXTKEY;


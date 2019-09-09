<?php


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    ['LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1','irfaq_pi1'],
    'list_type',
    'irfaq'
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    'irfaq_pi1',
    'FILE:EXT:irfaq/Configuration/FlexForms/flexform_ds.xml'
);

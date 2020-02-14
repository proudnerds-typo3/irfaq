<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}



\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('options.saveDocNew.tx_irfaq_q=1');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('options.saveDocNew.tx_irfaq_cat=1');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('options.saveDocNew.tx_irfaq_expert=1');



//listing FAQ in Web->Page view
$TYPO3_CONF_VARS['EXTCONF']['cms']['db_layout']['addTables']['tx_irfaq_q'][0] = [
    'fList' => 'q,a,q_from,expert',
    'icon' => true
];



// Core DataHandler hooks for managing related entries
$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['irfaq']
    = \Netcreators\Irfaq\System\Backend\DataHandling\RelatedQuestionsDataHandler::class;
$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['irfaq']
    = \Netcreators\Irfaq\System\Backend\DataHandling\RelatedQuestionsDataHandler::class;


// Hook to comments for comments closing
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['comments']['closeCommentsAfter'][$_EXTKEY]
    = \Netcreators\Irfaq\Hooks\Comments\CloseCommentsAfterHook::class.'->irfaqHook';



// Backend Page Module hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['irfaq_pi1'][]
    = \Netcreators\Irfaq\System\Backend\PageModule\ExtensionSummaryProvider::class.'->getExtensionSummary';


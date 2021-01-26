<?php

namespace Netcreators\Irfaq\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004 - 2006 Ingo Renner (typo3@ingo-renner.com)
 *  (c) 2006        Netcreators (extensions@netcreators.com)
 *  (c) 2009-2017 Leonie Philine Bitto (extensions@netcreators.nl)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendWorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\PlainDataResolver;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;
use TYPO3\CMS\Frontend\Resource\FilePathSanitizer;

/**
 * Plugin 'Simple FAQ' for the 'irfaq' extension.
 *
 * @author    Netcreators <extensions@netcreators.com>
 * @package TYPO3
 * @subpackage irfaq
 */


/**
 * Creates a faq list.
 *
 * @author Netcreators <extensions@netcreators.com>
 */
class FaqController extends AbstractPlugin
{
    public $prefixId = 'tx_irfaq_faqcontroller';

    public $scriptRelPath = 'Classes/Controller/FaqController.php';

    public $extKey = 'irfaq';

    public $pi_checkCHash = false;

    protected $searchFieldList = 'q, a';

    protected $categories = [];

    protected $experts = [];

    protected $faqCount = 0;

    protected $hash = ''; // a random hash to use multiple pi on one page

    protected $showUid = 0;

    /**
     * See config.sys_language_overlay in TSRef
     *
     * @var string
     */
    protected $sys_language_contentOL;

    /**
     * See config.sys_language_mode in TSRef. This variable includes only keyword, not list of languges
     *
     * @var string
     */
    protected $sys_language_mode;

    /**
     * A list of languages to look overlays in
     *
     * @var array
     */
    protected $content_languages = [0];

    /**
     * @var array
     */
    protected $fieldNames = [];

    /**
     * @var string
     */
    protected $templateCode = '';

    /**
     * main function, which is called at startup
     * calls init() and determines view
     *
     * @param string $content : output string of page
     * @param array $conf : configuration array from TS
     *
     * @return    string        $content: output of faq plugin
     */
    function main($content, $conf)
    {
        $this->init($conf);

        if (!isset($this->conf['templateFile'])) {
            $content = $this->pi_getLL('no_ts_template');
        } else {
            $content = '';
            foreach ($this->conf['code'] as $code) {
                switch ($code) {
                    case 'SINGLE':
                        $content .= $this->singleView();
                        break;
                    case 'SEARCH':
                        $content .= $this->searchView();
                        break;
                    case 'DYNAMIC':
                        $content .= $this->dynamicView();
                        break;
                    case 'STATIC':
                        $content .= $this->staticView();
                        break;
                    case 'STATIC_SEPARATE':
                        $content .= $this->staticSeparateView();
                        break;
                    default:
                        $content .= 'unknown view!';
                        break;
                }
            }
        }

        return $this->pi_wrapInBaseClass($content);
    }

    /**
     * initializes configuration variables
     *
     * @param array $conf : configuration array from TS
     *
     * @return    void
     */
    function init($conf)
    {
        $this->conf = $conf;

        $this->pi_loadLL(); // Loading language-labels
        $this->pi_setPiVarDefaults(); // Set default piVars from TS
        $this->pi_initPIflexForm(); // Init FlexForm configuration for plugin

        $this->initPidList($conf);

        // "CODE" decides what is rendered: code can be added by TS or FF with priority on FF
        $this->showUid = intval($this->piVars['showUid']);
        if ($this->showUid) {
            $this->conf['code'] = ['SINGLE'];
            $this->conf['categoryMode'] = 0;
            $this->conf['catExclusive'] = 0;
        } else {
            $ffCode = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'what_to_display');
            $this->conf['code'] = $ffCode ? $ffCode : strtoupper($conf['code']);
            if (empty($this->conf['code'])) {
                $this->conf['code'] = strtoupper($conf['defaultCode']);
            }
            $this->conf['code'] = explode(',', $this->conf['code']);

            // categoryModes are: 0=display all categories, 1=display selected categories, -1=display deselected categories
            $ffCategoryMode = $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                'categoryMode',
                'sCATEGORIES'
            );
            $this->conf['categoryMode'] = $ffCategoryMode ?
                $ffCategoryMode :
                $this->conf['categoryMode'];

            $ffCatSelection = $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                'categorySelection',
                'sCATEGORIES'
            );
            $this->conf['catSelection'] = $ffCatSelection ?
                $ffCatSelection :
                trim($this->conf['categorySelection']);

            // ignore category selection if categoryMode isn't set
            if ($this->conf['categoryMode'] != 0) {
                $this->conf['catExclusive'] = $this->conf['catSelection'];
            } else {
                $this->conf['catExclusive'] = 0;
            }

            //set category by $_GET
            if (is_numeric($this->piVars['cat'])) {
                $this->conf['catExclusive'] = intval($this->piVars['cat']);
                $this->conf['categoryMode'] = 1;
            }

            $ffSearchPid = $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                'searchPid',
                'sSEARCH'
            );
            $this->conf['searchPid'] = $ffSearchPid ?
                $ffSearchPid :
                trim($this->conf['searchPid']);

            $ffSorting = $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                'sorting',
                'sDEF'
            );
            $this->conf['orderBy'] = ($ffSorting !== 'ts') ?
                $ffSorting :
                trim($this->conf['orderBy']);

            $ffEmptySearchAtStart = $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                'emptySearchAtStart',
                'sSEARCH'
            );
            $this->conf['emptySearchAtStart'] = $ffEmptySearchAtStart != '' ?
                $ffEmptySearchAtStart :
                trim($this->conf['emptySearchAtStart']);


            // get fieldnames from the tx_irfaq_q db-table

            $this->fieldNames = array_keys($this->getListTableColumns('tx_irfaq_q'));

            if ($this->conf['searchFieldList']) {
                $searchFieldList = $this->validateFields($this->conf['searchFieldList']);
                if ($searchFieldList) {
                    $this->searchFieldList = $searchFieldList;
                }
            }

            // max items per page
            $TSLimit = MathUtility::forceIntegerInRange($conf['limit'], 0, 1000);
            $this->conf['limit'] = $TSLimit ? $TSLimit : 50;
        }

        // ratings
        if (!ExtensionManagementUtility::isLoaded('ratings')) {
            $this->conf['enableRatings'] = false;
        } else {
            $val = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'enableRatings', 'sDEF');
            if ($val != '') {
                $this->conf['enableRatings'] = $val;
            }
        }


        $this->templateCode = file_get_contents(GeneralUtility::makeInstance(FilePathSanitizer::class)->sanitize($this->conf['templateFile']));

        $this->initCategories(); // initialize category-array
        $this->initExperts(); // initialize experts-array

        mt_srand((double)microtime() * 1000000);
        $this->hash = substr(md5(mt_rand()), 0, 5);

        // Language settings
        $sys_language_mode = (isset($this->conf['sys_language_mode'])
            ? $this->conf['sys_language_mode']
            : $this->getLanguageAspect()->getLegacyLanguageMode());

        list($this->sys_language_mode, $languages) = GeneralUtility::trimExplode(';', $sys_language_mode);

        $this->content_languages = [$this->getLanguageAspect()->getId()];

        if ($languages != '' && $this->sys_language_mode == 'content_fallback') {
            foreach (GeneralUtility::trimExplode(',', $languages, true) as $language) {
                $this->content_languages[] = $language;
            }
        }

        $this->sys_language_contentOL = isset($this->conf['sys_language_overlay'])
            ? $this->conf['sys_language_overlay']
            : $this->getTypoScriptFrontendController()->config['config']['sys_language_overlay'];

        //$this->conf['iconPlus'] = $this->getTypoScriptFrontendController()->tmpl->getFileName($this->conf['iconPlus']);

        $this->conf['iconPlus'] = file_get_contents(GeneralUtility::makeInstance(FilePathSanitizer::class)->sanitize($this->conf['iconPlus']));

        //$this->conf['iconMinus'] = $this->getTypoScriptFrontendController()->tmpl->getFileName($this->conf['iconMinus']);

        $this->conf['iconMinus'] = file_get_contents(GeneralUtility::makeInstance(FilePathSanitizer::class)->sanitize($this->conf['iconMinus']));
    }

    /**
     * Getting all tx_irfaq_cat categories into internal array
     * partly taken from tt_news - thx Rupert!!!
     *
     * @return    void
     */
    function initCategories()
    {
        $rootlineStoragePid = $this->getStorageSiterootPids();

        $storagePidCsv = trim(implode(
            ', ',
            [$rootlineStoragePid['_STORAGE_PID'], $this->conf['pidList']]
        ), ', ');

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_irfaq_cat');

        $queryBuilder
            ->select('*')
            ->from('tx_irfaq_cat', 'a')
            ->leftJoin('a', 'tx_irfaq_q_cat_mm', 'b', 'b.uid_foreign = a.uid')
            ->where(
                $queryBuilder->expr()->in('a.pid', $storagePidCsv)
            )
            ->addOrderBy('a.sorting');

        $statement = $queryBuilder->execute();
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {

            $catTitle = $row['title'];
            $catShortcut = $row['shortcut'];

            if (isset($row['uid_local'])) {
                $this->categories[$row['uid_local']][] = [
                    'title' => $catTitle,
                    'catid' => $row['uid_foreign'],
                    'shortcut' => $catShortcut
                ];
            } else {
                $this->categories['0'][$row['uid']] = $catTitle;
            }
        }
    }

    /**
     * Getting all experts into internal array
     *
     * @return    void
     */
    function initExperts()
    {
        // Fetching experts

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_irfaq_expert');
        $queryBuilder
            ->select('*')
            ->from('tx_irfaq_expert');

        $statement = $queryBuilder->execute();
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {
            if (($row = $this->getLanguageOverlay('tx_irfaq_expert', $row))) {
                $this->experts[$row['uid']]['name'] = $row['name'];
                $this->experts[$row['uid']]['url'] = $row['url'];
                $this->experts[$row['uid']]['email'] = $row['email'];
            }
        }
    }

    /**
     * Shows search.
     *
     * @return    string        Generated content
     */
    function searchView()
    {
        $template['total'] = $this->getSubpart($this->templateCode, '###TEMPLATE_SEARCH###');

        $formURL = htmlspecialchars(
            $this->pi_linkTP_keepPIvars_url(['cat' => null], 0, 1, $this->conf['searchPid'])
        );

        $content = $this->substituteMarker($template['total'], '###FORM_URL###', $formURL);
        $content = $this->substituteMarker($content, '###SWORDS###', htmlspecialchars($this->piVars['swords']));
        $content = $this->substituteMarker($content, '###SEARCH_BUTTON###', $this->pi_getLL('searchButtonLabel'));
        $content = $this->substituteMarker($content, '###SEARCH_LEGEND###', $this->pi_getLL('searchLegendLabel'));

        return $content;
    }

    /**
     * creates the dynamic view with DHTML
     *
     * @return    string        faq list
     */
    function dynamicView()
    {
        $template = [];
        $subpartArray = [];

        $template['total'] = $this->getSubpart(
            $this->templateCode,
            '###TEMPLATE_DYNAMIC###'
        );
        $template['content'] = $this->getSubpart(
            $template['total'],
            '###CONTENT###'
        );

        $subpartArray['###CONTENT###'] = $this->fillMarkers($template['content']);

        if (!empty($this->faqCount)) {

            $markerArray = [
                '###HASH###' => $this->hash,
                '###TOTALCOUNT###' => $this->faqCount,
                '###TEXT_SHOW###' => $this->pi_getLL('text_show'),
                '###TEXT_HIDE###' => $this->pi_getLL('text_hide'),
            ];
            $content = $this->substituteMarkerArrayCached(
                $template['total'],
                $markerArray,
                $subpartArray
            );
        } else {
            $content = $this->cObj->stdWrap(
                $this->pi_getLL('noResult'),
                $this->conf['no_result_stdWrap.']
            );
        }

        return $content;
    }

    /**
     * creates the static view without dhtml
     *
     * @return    string        faq list
     */
    function staticView()
    {
        $templateName = 'TEMPLATE_STATIC';

        $template = [];
        $template['total'] = $this->getSubpart(
            $this->templateCode,
            '###' . $templateName . '###'
        );

        $temp = $this->getSubpart($template['total'], '###QUESTIONS###');
        $subpartArray['###QUESTIONS###'] = $this->fillMarkers($temp);

        $temp = $this->getSubpart($template['total'], '###ANSWERS###');
        $subpartArray['###ANSWERS###'] = $this->fillMarkers($temp);

        $content = $this->substituteMarkerArrayCached(
            $template['total'],
            [],
            $subpartArray
        );

        return $content;
    }


    /**
     * Replaces markers with content
     *
     * @param string $template : the html with markers to substitude
     *
     * @return    string        template with substituted markers
     */
    function fillMarkers($template)
    {
        $content = '';

        /** @var QueryBuilder $queryBuilder */
        list($queryBuilder, $orderStatement) = $this->getSelectConfQuery();

        $statement = $queryBuilder->execute();
        $rows = $statement->fetchAll();

        $this->faqCount = count($rows);

        $rows = $this->getOverlayedFaqs($rows, $orderStatement);

        $i = 1;
        foreach ($rows as $row) {

            $markerArray = $this->fillMarkerArrayForRow($row, $i);
            $markerArray['###FAQ_ID###'] = $row['uid'];
            $markerArray['###COUNT###'] = $i++;
            $markerArray['###TOTALCOUNT###'] = $this->faqCount;

            $subpart = $this->getSubpart($template, '###FAQ###');
            $content .= $this->substituteMarkerArrayCached($subpart, $markerArray);
        }

        return $content;
    }

    /**
     * Fills in the Category markerArray with data
     * also taken from tt_news ;-)
     *
     * @param array $markerArray : partly filled marker array
     * @param array $row : result row for a news item
     *
     * @return    array        $markerArray: filled markerarray
     */
    function getCatMarkerArray($markerArray, $row)
    {
        // clear the category text marker if the FAQ item has no categories
        $markerArray['###FAQ_CATEGORY###'] = '';
        $markerArray['###TEXT_CATEGORY###'] = '';

        $faq_category = [];


        if (GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('workspace', 'id') && $row['_ORIG_uid'] > 0) {
            $row['uid'] = $row['_ORIG_uid'];
        }

        if (isset($this->categories[$row['uid']])) {
            reset($this->categories[$row['uid']]);

            foreach ($this->categories[$row['uid']] as $key => $val) {
                // find categories, wrap them with links and collect them in the array $faq_category.
                if ($this->conf['catTextMode'] == 1) {
                    // link to category shortcut page
                    $faq_category[] = $this->pi_linkToPage(
                        $this->categories[$row['uid']][$key]['title'],
                        $this->categories[$row['uid']][$key]['shortcut']
                    );
                } else {
                    if ($this->conf['catTextMode'] == 2) {
                        // act as category selector
                        $faq_category[] = $this->pi_linkToPage(
                            $this->categories[$row['uid']][$key]['title'],
                            $this->getTypoScriptFrontendController()->page['uid'],
                            '',
                            ['tx_irfaq_faqcontroller[cat]' => $this->categories[$row['uid']][$key]['catid']]
                        );
                    } else {
                        // no link
                        $faq_category[] = $this->categories[$row['uid']][$key]['title'];
                    }
                }
            }
        }

        $markerArray['###FAQ_CATEGORY###'] = implode(', ', array_slice($faq_category, 0));

        //apply the wraps if there are categories
        if (count($faq_category)) {
            $markerArray['###FAQ_CATEGORY###'] = $this->cObj->stdWrap(
                $markerArray['###FAQ_CATEGORY###'],
                $this->conf['category_stdWrap.']
            );
            $markerArray['###TEXT_CATEGORY###'] = $this->pi_getLL('text_category');
        }

        return $markerArray;
    }

    /**
     * build the selectconf (array of query-parameters) to get the faq items from the db
     *
     * @return array the selectconf for the display of a news item, and sorting statement used
     */
    function getSelectConfQuery()
    {

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_irfaq_q');
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(FrontendWorkspaceRestriction::class, $this->getCurrentWorkspaceId())
        );

        $where = "1=1";//. $this->addWorkspaceRestriction();

        $queryBuilder->selectLiteral('DISTINCT tx_irfaq_q.uid, tx_irfaq_q.pid, 
        tx_irfaq_q.q, tx_irfaq_q.q_from, tx_irfaq_q.a, tx_irfaq_q.cat, tx_irfaq_q.expert, tx_irfaq_q.related, 
        tx_irfaq_q.related_links, tx_irfaq_q.enable_ratings, tx_irfaq_q.sys_language_uid, tx_irfaq_q.l18n_parent, tx_irfaq_q.l18n_diffsource, tx_irfaq_q.sorting');

        $queryBuilder->from("tx_irfaq_q");

        $queryBuilder->where($where);


        if ((int)$this->conf['faqListRespectSysLanguageUid']) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('tx_irfaq_q.sys_language_uid',
                    $queryBuilder->createNamedParameter((int)$this->getTypoScriptFrontendController()->config['config']['sys_language_uid'],
                        \PDO::PARAM_INT))
            );
        }


        if (GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('workspace', 'id') > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('t3ver_wsid',
                        $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('t3ver_wsid',
                        $queryBuilder->createNamedParameter(GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('workspace', 'id'),
                            \PDO::PARAM_INT)))
            );
        }

        //build SQL on condition of categoryMode
        if ($this->conf['categoryMode'] == 1 && trim($this->conf['catExclusive']) != '') {

            $queryBuilder->leftJoin('tx_irfaq_q', 'tx_irfaq_q_cat_mm', 'tx_irfaq_q_cat_mm',
                ' tx_irfaq_q.uid = tx_irfaq_q_cat_mm.uid_local');
            $queryBuilder->andWhere('(IFNULL(tx_irfaq_q_cat_mm.uid_foreign,0) IN (' . $this->conf['catExclusive'] . '))');

        } elseif ($this->conf['categoryMode'] == -1) {

            $joinCondition = '(tx_irfaq_q.uid = tx_irfaq_q_cat_mm.uid_local AND (tx_irfaq_q_cat_mm.uid_foreign=';


            //multiple categories selected?
            if (strpos($this->conf['catExclusive'], ',')) {
                //yes
                $joinCondition .= preg_replace(
                    '/,/',
                    ' OR tx_irfaq_q_cat_mm.uid_foreign=',
                    $this->conf['catExclusive']
                );
            } else {
                //no
                $joinCondition .= $this->conf['catExclusive'];
            }

            $joinCondition .= '))';
            $queryBuilder->leftJoin('tx_irfaq_q', 'tx_irfaq_q_cat_mm', 'tx_irfaq_q_cat_mm', $joinCondition);
            $queryBuilder->andWhere(
                $queryBuilder->expr()->isNull('tx_irfaq_q_cat_mm.uid_foreign')
            );
        }

        // do the search and add the result to the $where string
        if ($this->piVars['swords']) {
            $queryBuilder->andWhere(
                $this->searchWhere(trim($this->piVars['swords']), $this->searchFieldList, 'tx_irfaq_q')
            );
        } elseif (in_array('SEARCH', $this->conf['code'])) {
            // display an empty list, if 'emptySearchAtStart' is set.
            $queryBuilder->andWhere(
                ($this->conf['emptySearchAtStart'] ? ' AND 1=0' : '')
            );
        }

        $sorting = $this->conf['orderBy'] ? 'tx_irfaq_q.' . $this->conf['orderBy'] : 'tx_irfaq_q.sorting';
        $queryBuilder->orderBy($sorting);


        $queryBuilder->andWhere(" pid in ('" . $this->conf['pidList'] . "')");

        return [$queryBuilder, $sorting];
    }

    /**
     * Generates a search where clause.
     *
     * @param string $searchWords
     *
     * @return    string        querypart
     */
    function searchWhere($searchWords)
    {
        $where = $this->cObj->searchWhere($searchWords, $this->searchFieldList, 'tx_irfaq_q');
        return $where;
    }

    /**
     * Format string with general_stdWrap from configuration
     *
     * @param string        string to wrap
     *
     * @return    string        wrapped string
     */
    function formatStr($str)
    {
        if (is_array($this->conf['general_stdWrap.']) || count($this->conf['general_stdWrap.']) > 0) {
            $str = $this->cObj->stdWrap(
                $str,
                $this->conf['general_stdWrap.']
            );
        }
        return $str;
    }

    /**
     * checks for each field of a list of items if it exists in the tx_irfaq_q table ($this->fieldNames) and returns
     * the validated fields
     *
     * @param string $fieldlist : a list of fields to ckeck
     *
     * @return    string        the list of validated fields
     */
    function validateFields($fieldlist)
    {
        $checkedFields = [];
        $fArr = GeneralUtility::trimExplode(',', $fieldlist, 1);
        foreach ($fArr as $key => $val) {
            if (in_array($val, $this->fieldNames)) {
                $checkedFields[] = $val;
            }
        }
        $checkedFieldlist = implode($checkedFields, ',');
        return $checkedFieldlist;
    }

    /**
     * Makes formatted list of related FAQ entries
     *
     * @param string $list Comma-separated list of related-entires
     *
     * @return    string        Generated HTML or empty string if no related entries
     */
    function getRelatedEntries($list)
    {
        $content = '';
        $list = GeneralUtility::trimExplode(',', $list, true); // Have to do that because there can be empty elements!
        if (count($list)) {
            //danilo
            //$rows = $this->getQuestionsByUids($list);
            $rows = $this->getQuestionsByUids($list,'sorting');

            if (is_array($rows)) {
                $template = $this->getSubpart($this->templateCode, '###TEMPLATE_RELATED_FAQ###');
                $templateInner = $this->getSubpart($template, '###RELATED_FAQ_ENTRY###');

                foreach ($rows as $row) {
                    // TODO Anchor is customizable in template!
                    if (($row = $this->getLanguageOverlay('tx_irfaq_q', $row))) {
                        $markers = [
                            '###RELATED_FAQ_ENTRY_TITLE###' => $this->formatStr(
                                $this->cObj->stdWrap(htmlspecialchars($row['q']), $this->conf['question_stdWrap.'])
                            ),
                            '###RELATED_FAQ_ENTRY_HREF###' => $this->pi_list_linkSingle(
                                '',
                                $row['uid'],
                                true,
                                [],
                                true
                            ),
                        ];
                        $content .= $this->substituteMarkerArrayCached($templateInner, $markers);
                    }
                }
                $content = $this->substituteMarkerArrayCached(
                    $template,
                    ['###TEXT_RELATED_FAQ###' => $this->pi_getLL('text_related_faq')],
                    ['###RELATED_FAQ_ENTRY###' => $content]
                );
            }
        }
        return $content;
    }

    /**
     * Makes formatted list of related links
     *
     * @param string $list NL-separated list of related links
     *
     * @return    string        Generated HTML or empty string if no related links
     */
    function getRelatedLinks($list)
    {
        $content = '';
        $list = GeneralUtility::trimExplode(
            chr(10),
            $list,
            true
        ); // Have to do that because there can be empty elements!
        if (count($list)) {
            $template = $this->getSubpart($this->templateCode, '###TEMPLATE_RELATED_LINKS###');
            $templateInner = $this->getSubpart($template, '###RELATED_LINK_ENTRY###');
            foreach ($list as $link) {
                $markers = [
                    '###RELATED_LINK_ENTRY_TITLE###' => htmlspecialchars($link),
                    '###RELATED_LINK_ENTRY_HREF###' => $link,
                ];
                $content .= $this->substituteMarkerArrayCached($templateInner, $markers);
            }
            $content = $this->substituteMarkerArrayCached(
                $template,
                ['###TEXT_RELATED_LINKS###' => $this->pi_getLL('text_related_links')],
                ['###RELATED_LINK_ENTRY###' => $content]
            );
        }
        return $content;
    }

    /**
     * Creates marker array for a single FAQ row
     *
     * @param array &$row A row from tx_irfaq_q. Passed by reference to save memory.
     * @param int $i Row index
     *
     * @return    array        Generated marker array
     * @see    fillMarkers()
     */
    function fillMarkerArrayForRow(&$row, $i)
    {
        $markerArray = [];

        $markerArray['###FAQ_Q###'] = $this->formatStr(
            $this->cObj->stdWrap(
                htmlspecialchars($row['q']),
                $this->conf['question_stdWrap.']
            )
        );
        $markerArray['###FAQ_A###'] = $this->formatStr(
            $this->cObj->stdWrap(
                $this->pi_RTEcssText($row['a']),
                $this->conf['answer_stdWrap.']
            )
        );

        // categories
        $markerArray = $this->getCatMarkerArray($markerArray, $row);

        $markerArray['###SINGLE_OPEN###'] = ($this->conf['singleOpen'] ? 'true' : 'false');

        if ($row['expert']) {
            $this->cObj->cObjGetSingle(
                'LOAD_REGISTER',
                [
                    'faqExpertEmail' => $this->experts[$row['expert']]['email'],
                    'faqExpertUrl' => $this->experts[$row['expert']]['url']
                ]
            );
            $markerArray['###FAQ_EXPERT###'] = $this->cObj->stdWrap(
                $this->experts[$row['expert']]['name'],
                $this->conf['expert_stdWrap.']
            );

            $markerArray['###TEXT_EXPERT###'] = $this->cObj->stdWrap(
                $this->pi_getLL('text_expert'),
                $this->conf['text_expert_stdWrap.']
            );

            $markerArray['###FAQ_EXPERT_EMAIL###'] = $this->cObj->stdWrap(
                $this->experts[$row['expert']]['email'],
                $this->conf['expertemail_stdWrap.']
            );

            if ($this->experts[$row['expert']]['url']) {
                $markerArray['###FAQ_EXPERT_URL###'] = $this->cObj->stdWrap(
                    $this->experts[$row['expert']]['url'],
                    $this->conf['experturl_stdWrap.']
                );
            } else {
                $markerArray['###FAQ_EXPERT_URL###'] = '';
            }
        } else {
            //leave everything empty if no expert assigned
            $markerArray['###FAQ_EXPERT###'] = '';
            $markerArray['###TEXT_EXPERT###'] = '';
            $markerArray['###FAQ_EXPERT_EMAIL###'] = '';
            $markerArray['###FAQ_EXPERT_URL###'] = '';
            $this->cObj->cObjGetSingle(
                'LOAD_REGISTER',
                ['faqExpertEmail' => '', 'faqExpertUrl' => '']
            );
        }

        if ($row['q_from']) {
            $markerArray['###TEXT_ASKED_BY###'] = $this->cObj->stdWrap(
                $this->pi_getLL('text_asked_by'),
                $this->conf['text_asked_by_stdWrap.']
            );
            $markerArray['###ASKED_BY###'] = $this->cObj->stdWrap(
                $row['q_from'],
                $this->conf['asked_by_stdWrap.']
            );
        } else {
            $markerArray['###TEXT_ASKED_BY###'] = '';
            $markerArray['###ASKED_BY###'] = '';
        }

        $markerArray['###RELATED_FAQ###'] = '';
        if ($row['related']) {
            $related = $this->getRelatedEntries($row['related']);
            if ($related) {
                $markerArray['###RELATED_FAQ###'] = $this->cObj->stdWrap(
                    $related,
                    $this->conf['related_entries_stdWrap.']
                );
            }
        }

        $markerArray['###RELATED_LINKS###'] = '';
        if ($row['related_links']) {
            $related_links = $this->getRelatedLinks($row['related_links']);
            if ($related_links) {
                $markerArray['###RELATED_LINKS###'] = $this->cObj->stdWrap(
                    $related_links,
                    $this->conf['related_links_stdWrap.']
                );
            }
        }

        $markerArray['###FAQ_PM_IMG###'] = '<img src="' .
            $this->conf['iconPlus'] . '" id="irfaq_pm_' . $i . '_' . $this->hash . '" alt="' .
            $this->pi_getLL('fold_faq') . '" />';

        $markerArray['###HASH###'] = $this->hash;

        $markerArray['###SINGLEVIEW_LINK###'] = $this->pi_list_linkSingle('', $row['uid'], true, [], true);

        $markerArray['###RATING###'] = $this->getRatingForRow($row);

        $this->cObj->lastChanged($row['tstamp']);

        return $markerArray;
    }

    /**
     * Shows single FAQ item
     *
     * @return    string        Generated content
     */
    function singleView()
    {

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_irfaq_q');

        $where = "1=1" . $this->addWorkspaceRestriction();

        $queryBuilder
            ->select('*')
            ->from('tx_irfaq_q')
            ->where(
                $where
            )
            ->andWhere($queryBuilder->expr()->eq('uid', intval($this->showUid)));

        $statement = $queryBuilder->execute();

        $rows = $this->getOverlayedFaqs($statement->fetchAll());

        if (count($rows)) {
            if (($row = $this->getLanguageOverlay('tx_irfaq_q', $rows[0]))) {
                $rows[0] = $row;
            }
        }
        if (count($rows) == 0) {
            $content = $this->pi_getLL('noSuchEntry');
        } else {
            $template = $this->getSubpart($this->templateCode, '###TEMPLATE_SINGLE_VIEW###');
            $markers = $this->fillMarkerArrayForRow($rows[0], 1);
            unset($this->piVars['showUid']);
            $markers['###BACK_HREF###'] = $this->cObj->typoLink_URL(
                ['parameter' => $this->getTypoScriptFrontendController()->id]
            );
            $markers['###BACK_TEXT###'] = $this->pi_getLL('back');
            $markers['###FAQ_ID###'] = $rows[0]['uid'];
            $content = $this->substituteMarkerArrayCached($template, $markers);
            $this->cObj->lastChanged($row['tstamp']);
        }
        return $content;
    }

    /**
     * Creates a set of links to a separate page with answer. This mode is suitable if you want comments for FAQ entries
     *
     * @return string Generated FAQ list
     */
    function staticSeparateView()
    {
        $template_sub = $this->getSubpart($this->templateCode, '###TEMPLATE_STATIC_SEPARATE###');
        $template = $this->getSubpart($template_sub, '###QUESTIONS###');
        $subpartArray['###QUESTIONS###'] = $this->fillMarkers($template);
        return $this->substituteMarkerArrayCached($template_sub, [], $subpartArray);
    }

    /**
     * Obtains rating HTML for row if enabled.
     *
     * @param array $row Database row from tx_irfaq_q table
     *
     * @return    string    Generated ratings
     */
    function getRatingForRow($row)
    {
        $result = '';
        if ($row['enable_ratings'] && $this->conf['enableRatings']) {
            require_once(ExtensionManagementUtility::extPath('ratings', 'class.tx_ratings_api.php'));

            $apiObj = GeneralUtility::makeInstance('tx_ratings_api');
            /* @var \tx_ratings_api $apiObj */
            $result = $apiObj->getRatingDisplay(
                'tx_irfaq_q_' . ($row['l18n_parent'] ? $row['l18n_parent'] : $row['uid'])
            );
        }
        return $result;
    }

    /**
     * Gets language overlay for the record
     *
     * @param string $table Table name
     * @param array $row Row
     *
     * @return    mixed    Row or false if not found and no fallbacks available
     */
    function getLanguageOverlay($table, $row)
    {
        foreach ($this->content_languages as $language) {
            if (($result = $this->getTypoScriptFrontendController()->sys_page->getRecordOverlay(
                $table,
                $row,
                $language,
                $this->sys_language_contentOL
            ))
            ) {
                return $result;
            }
        }
        if ($this->sys_language_mode == '') {
            return $row;
        }
        return false;
    }

    protected function getStorageSiterootPids()
    {
        $res = [];
        $rootlineUtility = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Utility\RootlineUtility::class,
            $this->getTypoScriptFrontendController()->id
        );

        foreach ($rootlineUtility->get() as $pageRecord) {
            if (!$res['_STORAGE_PID']) {
                $res['_STORAGE_PID'] = (int)$pageRecord['storage_pid'];
            }
            if (!$res['_SITEROOT']) {
                $res['_SITEROOT'] = $pageRecord['is_siteroot'] ? (int)$pageRecord['uid'] : 0;
            }
        }

        return $res;
    }

    /**
     * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        /** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $TSFE */
        global $TSFE;

        return $TSFE;
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    protected function getListTableColumns($tableName)
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($tableName)
            ->getSchemaManager()
            ->listTableColumns($tableName);
    }

    /**
     * @param $part
     * @param $marker
     *
     * @return string
     */
    protected function getSubpart($part, $marker)
    {
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        return $templateService->getSubpart($part, $marker);
    }

    /**
     * @param $content
     * @param $marker
     * @param $markContent
     *
     * @return mixed
     */
    protected function substituteMarker($content, $marker, $markContent)
    {
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        return $templateService->substituteMarker($content, $marker, $markContent);
    }

    protected function substituteMarkerArrayCached(
        $content,
        array $markContentArray = null,
        array $subpartContentArray = null,
        array $wrappedSubpartContentArray = null
    ) {
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        return $templateService->substituteMarkerArrayCached($content, $markContentArray, $subpartContentArray,
            $wrappedSubpartContentArray);
    }

    /**
     * @param $conf
     */
    protected function initPidList($conf): void
    {
// pidList is the pid/list of pids from where to fetch the faq items.
        $ffPidList = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'pages');
        $pidList = $ffPidList ?
            $ffPidList :
            trim(
                $this->cObj->stdWrap(
                    $this->conf['pid_list'],
                    $this->conf['pid_list.']
                )
            );
        $this->conf['pidList'] = $pidList ?
            implode(GeneralUtility::intExplode(',', $pidList), ',') :
            $this->getTypoScriptFrontendController()->id;


        //get items recursive
        $recursive = $this->pi_getFFvalue(
            $this->cObj->data['pi_flexform'],
            'recursive'
        );
        $recursive = is_numeric($recursive) ?
            $recursive :
            $this->cObj->stdWrap($conf['recursive'], $conf['recursive.']);
        // extend the pid_list by recursive levels
        $this->conf['pidList'] = $this->pi_getPidList(
            $this->conf['pidList'],
            $recursive
        );
    }

    protected function getCurrentWorkspaceId()
    {
        return GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('workspace', 'id');
    }

    protected function addWorkspaceRestriction()
    {
        /** @var PageRepository $pageRepository */
        return PageRepository::enableFields('tx_irfaq_q');
    }

    /**
     * @param string $tableName
     * @param int[] $ids
     * @param array $sortingStatement
     *
     * @return PlainDataResolver
     */
    protected function getResolver($tableName, array $ids, array $sortingStatement = null)
    {
        /** @var PlainDataResolver $resolver */
        $resolver = GeneralUtility::makeInstance(
            PlainDataResolver::class,
            $tableName,
            $ids,
            $sortingStatement
        );
        $resolver->setWorkspaceId($this->getCurrentWorkspaceId());
        $resolver->setKeepMovePlaceholder(true);

        return $resolver;
    }

    /**
     * @param array $questionsList
     * @param string $sortingStatement
     *
     * @return array
     */
    protected function getQuestionsByUids($questionsList, $sortingStatement = '')
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_irfaq_q');

        $queryBuilder
            ->select('*')
            ->from('tx_irfaq_q')
            ->where(
                $queryBuilder->expr()->in('uid', $questionsList)
            );
        $queryBuilder->orderBy($sortingStatement);

        $statement = $queryBuilder->execute();
        $rows = $statement->fetchAll();
        return $rows;
    }

    /**
     * @param array $rows
     *
     * @param string $sortingStatement
     *
     * @return array
     */
    protected function getOverlayedFaqs(array $rows, $sortingStatement = ''): array
    {
        $faqs = [];
        foreach ($rows as $row) {
            $faqs[$row['uid']] = $row;
        }

        $ids = $this->getResolver('tx_irfaq_q', array_keys($faqs))->get();

        if (!$ids) {
            return [];
        }

        $rows = $this->getQuestionsByUids($ids, $sortingStatement);

        return $rows;
    }

    /**
     * @return LanguageAspect
     */
    protected function getLanguageAspect()
    {
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        return $languageAspect;
    }
}

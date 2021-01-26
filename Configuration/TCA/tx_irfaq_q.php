<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q',
        'label' => 'q',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'versioningWS' => TRUE,
        'origUid' => 't3_origuid',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'languageField' => 'sys_language_uid',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'fe_group' => 'fe_group',
        ],
        'dividers2tabs' => true,
        'iconfile' => 'EXT:irfaq/Resources/Public/Icons/icon_tx_irfaq_q.gif',
        'searchFields' => 'q, q_from, a, related_links',
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden,fe_group,q,cat,a,related,faq_files'
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden,--palette--;;1,q,a,--div--;Details,q_from,expert,related,related_links,faq_files,cat,--div--;LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.extra_tab,enable_ratings,disable_comments,comments_closetime',
        ],
    ],
    'palettes' => [
        '1' => ['showitem' => 'fe_group']
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => '0',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        0                    => '',
                        1                    => '',
                        'invertStateDisplay' => true
                    ]
                ],
            ]
        ],
        'fe_group' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.fe_group',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hide_at_login', -1],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.any_login', -2],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.usergroups', '--div--']
                ],
                'foreign_table' => 'fe_groups'
            ]
        ],
        'q' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.q',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '255',
                'eval' => 'required,trim',
            ]
        ],
        'q_from' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.q_from',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ]
        ],
        'cat' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.category',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_irfaq_cat',
                'foreign_table' => 'tx_irfaq_cat',
                'size' => 3,
                'minitems' => 0,
                'maxitems' => 5,
                'MM' => 'tx_irfaq_q_cat_mm',
            ]
        ],
        'a' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.a',
            'config' => [
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
                'enableRichtext' => true,
            ],
            'softref' => 'typolink_tag,images,email[subst],url'
        ],
        'expert' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.expert',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0]
                ],
                'foreign_table' => 'tx_irfaq_expert'
            ]
        ],
        'related' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.related',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_irfaq_q',
                'foreign_table' => 'tx_irfaq_q',
                'prepand_tname' => false, // specify explicitly because we depend on it!
                'size' => 3,
                'autoSizeMax' => 10,
                'multiple' => false,
                'maxitems' => 1000, // looks reasonable
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => false,
                    ],
                    'addRecord' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
        'related_links' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.related_links',
            'config' => [
                'type' => 'text',
                'wrap' => 'off',
            ],
        ],
        'enable_ratings' => [
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.enable_ratings',
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        0 => '',
                        1 => '',
                    ]
                ],
                'default' => '1'
            ]
        ],
        'disable_comments' => [
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.disable_comments',
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        0 => '',
                        1 => '',
                    ]
                ],
                'default' => '0'
            ]
        ],
        'comments_closetime' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:irfaq/Resources/Private/Language/locallang_db.xlf:tx_irfaq_q.comments_closetime',
            'config' => [
                'renderType' => 'inputDateTime',
                'type' => 'input',
                'size' => '12',
                'eval' => 'datetime',
                'checkbox' => '0',
            ]
        ],
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0]
                ]
            ]
        ],
        'l18n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_irfaq_q',
                'foreign_table_where' => 'AND tx_irfaq_q.uid=###REC_FIELD_l18n_parent### AND tx_irfaq_q.sys_language_uid IN (-1,0)',
            ]
        ],
        'l18n_diffsource' => [
            'config' => [
                'type' => 'passthrough'
            ]
        ],
    ],
];

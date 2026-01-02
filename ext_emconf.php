<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mask to Content Blocks',
    'description' => 'One command to migrate Mask elements to Content Blocks',
    'category' => 'be',
    'author' => 'Nikita Hovratov',
    'author_email' => 'entwicklung@nikita-hovratov.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '1.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.2-13.4.99',
            'mask' => '',
            'content_blocks' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

<?php
return [
    'service_manager' => [
        'factories' => [
            'ExtractText\ExtractorManager' => ExtractText\Service\Extractor\ManagerFactory::class,
        ],
    ],
    'text_extractors' => [
        'factories' => [
            'text/html' => ExtractText\Service\Extractor\LynxFactory::class,
            'application/pdf' => ExtractText\Service\Extractor\PdftotextFactory::class,
            'application/rtf' => ExtractText\Service\Extractor\CatdocFactory::class,
            'application/msword' => ExtractText\Service\Extractor\CatdocFactory::class,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ExtractText\Service\Extractor\Docx2txtFactory::class,
        ],
        'invokables' => [
            'text/plain' => ExtractText\Extractor\Filegetcontents::class,
        ]
    ],
];

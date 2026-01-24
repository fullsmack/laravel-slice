<?php

return [
    'root' => [
        'folder' => 'src',
        'namespace' => 'slice',
        /**
         * Namespace mode determines how the root namespace is applied:
         * - 'prefix': Always prepend root namespace (e.g., Module\Api\SliceName)
         * - 'fallback': Only use root namespace when no path/dir is provided (e.g., Api\SliceName when path is specified)
         */
        'namespace-mode' => 'prefix',
    ],
    'test' => [
        'namespace' => 'test',
    ],
    'discovery' => [
        'type' => 'composer',
    ],
    'architecture' => [
        'default' => [
            'controller' => 'http.controllers',
            'middleware' => 'http.middleware',
            'model' => 'models',
            'exception' => 'exceptions',
        ],
    ],
];

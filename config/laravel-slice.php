<?php

return [
    'root' => [
        'folder' => 'src',
        'namespace' => 'slice',
        /**
         * Namespace mode determines how the root namespace is applied:
         * - 'prefix': Always prepend root namespace (e.g., Slice\Api\SliceName)
         * - 'fallback': Only use root namespace when no path/dir is provided (e.g., Api\SliceName when path is specified)
         */
        'namespace-mode' => 'prefix',
    ],
    'test' => [
        'namespace' => 'test',
    ],
    'discovery' => [
        /**
         * - 'composer': make:slice adds the slice service provider to composer.json `extra.laravel.providers`
         * - any other value: register slice service providers yourself (e.g. in bootstrap/providers.php)
         */
        'type' => 'composer',
    ],
];

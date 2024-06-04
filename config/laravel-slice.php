<?php

return [
    'root' => [
        'folder' => 'src',
        'namespace' => 'module',
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
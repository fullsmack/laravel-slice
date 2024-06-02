<?php

return [
    'root' => [
        'folder' => 'src',
        'namespace' => 'Module',
    ],
    'test' => [
        'namespace' => 'Test',
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
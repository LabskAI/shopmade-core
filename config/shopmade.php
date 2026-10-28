<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public packages
    |--------------------------------------------------------------------------
    |
    | The open-source feature packages `shopmade:install` offers, keyed by the
    | short name you pass to --packages. Each is a Composer package in its own
    | public GitHub repo that plugs into core through App\Core\Packages\Hooks;
    | the installer adds that repo to composer.json, so it works before the
    | package is on Packagist. Private and paid packages are never listed here.
    |
    */

    'packages' => [
        'jobs' => [
            'package' => 'labskai/shopmade-jobs',
            'repository' => 'https://github.com/LabskAI/shopmade-jobs',
            'label' => 'Jobs',
            'description' => 'Jobs, week schedule, crew photos and client visits',
        ],
        'invoicing' => [
            'package' => 'labskai/shopmade-invoicing',
            'repository' => 'https://github.com/LabskAI/shopmade-invoicing',
            'label' => 'Invoicing',
            'description' => 'Quotes, invoices with PDF and the portal invoices tab',
        ],
        'shop' => [
            'package' => 'labskai/shopmade-shop',
            'repository' => 'https://github.com/LabskAI/shopmade-shop',
            'label' => 'Shop',
            'description' => 'Products, cart, guest checkout and orders',
        ],
        'crm' => [
            'package' => 'labskai/shopmade-crm',
            'repository' => 'https://github.com/LabskAI/shopmade-crm',
            'label' => 'CRM',
            'description' => 'Contacts and leads linked to customers',
        ],
        'ai' => [
            'package' => 'labskai/shopmade-ai',
            'repository' => 'https://github.com/LabskAI/shopmade-ai',
            'label' => 'AI client',
            'description' => 'One chat client (xAI, OpenRouter, Anthropic) for other packages',
        ],
    ],

];

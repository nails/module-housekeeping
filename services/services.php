<?php

use Nails\Housekeeping\Service;

return [
    'services'  => [
        'Orchestrator' => function (): Service\Orchestrator {
            if (class_exists('\App\Housekeeping\Service\Orchestrator')) {
                return new \App\Housekeeping\Service\Orchestrator();
            }

            return new Service\Orchestrator();
        },
        'Logger'       => function (): Service\Logger {
            if (class_exists('\App\Housekeeping\Service\Logger')) {
                return new \App\Housekeeping\Service\Logger();
            }

            return new Service\Logger();
        },
        'Deleter'      => function (): Service\Deleter {
            if (class_exists('\App\Housekeeping\Service\Deleter')) {
                return new \App\Housekeeping\Service\Deleter();
            }

            return new Service\Deleter();
        },
    ],
    'models'    => [],
    'factories' => [],
    'resources' => [],
];
